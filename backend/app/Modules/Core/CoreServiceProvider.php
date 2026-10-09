<?php

declare(strict_types=1);

namespace App\Modules\Core;

use App\Infrastructure\Egress\DnsHostResolver;
use App\Infrastructure\Egress\HostResolver;
use App\Modules\Access\AccessCache;
use App\Modules\Access\AccessResolver;
use App\Modules\Access\Models\Permission;
use App\Modules\Access\Models\Role;
use App\Modules\Core\Correlation\CorrelationId;
use App\Modules\Core\I18n\TranslatableRegistry;
use App\Modules\Core\I18n\Translator;
use App\Modules\Core\Mail\SettingsSmtpTransport;
use App\Modules\Core\Models\Organization;
use App\Modules\Core\Outbox\OutboxRelay;
use App\Modules\Core\Settings\SettingsRegistry;
use App\Modules\Core\Settings\SettingsService;
use App\Modules\Core\Tenancy\TenantContext;
use App\Modules\Identity\Models\User;
use App\Modules\Monitoring\ErrorReporter;
use App\Modules\Organization\Models\Department;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\Request;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Laravel\Horizon\Horizon;

/**
 * Wires the platform services shared by every module (architecture §4–§8).
 * Request-scoped state (tenant, correlation ID, access memo) is `scoped`, so it
 * is reset between requests and between queued jobs.
 */
final class CoreServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->scoped(TenantContext::class);
        $this->app->scoped(CorrelationId::class);
        $this->app->scoped(AccessResolver::class);
        $this->app->scoped(Translator::class);
        $this->app->scoped(ErrorReporter::class);
        $this->app->singleton(AccessCache::class);
        $this->app->singleton(SettingsRegistry::class);
        $this->app->singleton(TranslatableRegistry::class);
        $this->app->singleton(OutboxRelay::class);
        $this->app->bind(HostResolver::class, DnsHostResolver::class);
    }

    public function boot(): void
    {
        $this->registerTranslatables();
        $this->registerRateLimiters();

        // Every catalog ability (`system.*` and the auto-registered `form.*`,
        // `app.*`, `menu.*` object permissions) is answered by the permission
        // resolver and nothing else (specification §4.11): no role or flag
        // short-circuits it.
        Gate::before(static function (?Authenticatable $user, string $ability): ?bool {
            if (preg_match('/^(system|form|app|menu)\./', $ability) !== 1) {
                return null;
            }

            return $user instanceof User && app(AccessResolver::class)->allows($user, $ability);
        });

        // Queue dashboard: operators only, decided by the resolver.
        Horizon::auth(static fn (Request $request): bool => $request->user() instanceof User
            && app(AccessResolver::class)->allows($request->user(), 'system.manage_operations'));

        Mail::extend('lcf', static fn () => new SettingsSmtpTransport(app(SettingsService::class)));

        // Jobs carry the correlation ID and organization of the request that
        // queued them (architecture §8.4).
        Queue::createPayloadUsing(static function (): array {
            try {
                $organizationId = app(TenantContext::class)->organizationId();
            } catch (\RuntimeException) {
                $organizationId = null; // before the installation is seeded
            }

            return ['lcf_correlation_id' => app(CorrelationId::class)->get(), 'lcf_organization_id' => $organizationId];
        });
        Event::listen(JobProcessing::class, static function (JobProcessing $event): void {
            $payload = $event->job->payload();
            if (is_string($payload['lcf_correlation_id'] ?? null)) {
                app(CorrelationId::class)->set($payload['lcf_correlation_id']);
            }
            if (is_int($payload['lcf_organization_id'] ?? null)) {
                app(TenantContext::class)->set($payload['lcf_organization_id']);
            }
        });
    }

    /** Rate limiting (specification §5); the authenticated limit is a security setting. */
    private function registerRateLimiters(): void
    {
        RateLimiter::for('api', static function (Request $request): Limit {
            $user = $request->user();
            if ($user === null) {
                return Limit::perMinute(60)->by('ip:'.$request->ip());
            }
            $perMinute = (int) app(SettingsService::class)->get('security', 'api_rate_limit_per_minute');

            return Limit::perMinute(max(30, $perMinute))->by('user:'.$user->getAuthIdentifier());
        });
        RateLimiter::for('public', static fn (Request $request): Limit => Limit::perMinute(120)->by('ip:'.$request->ip()));
        RateLimiter::for('setup', static fn (Request $request): Limit => Limit::perMinute(20)->by('ip:'.$request->ip()));
    }

    private function registerTranslatables(): void
    {
        $registry = $this->app->make(TranslatableRegistry::class);
        $registry->register('organization', Organization::class, ['name'], 'translations.type.organization');
        $registry->register('department', Department::class, ['name'], 'translations.type.department');
        $registry->register('role', Role::class, ['name', 'description'], 'translations.type.role');
        $registry->register('permission', Permission::class, ['label', 'description'], 'translations.type.permission');
    }
}
