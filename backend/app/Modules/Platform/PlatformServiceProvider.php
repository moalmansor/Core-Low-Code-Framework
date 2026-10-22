<?php

declare(strict_types=1);

namespace App\Modules\Platform;

use App\Modules\Core\I18n\TranslatableRegistry;
use App\Modules\Core\Models\OutboxEvent;
use App\Modules\Core\Outbox\OutboxRelay;
use App\Modules\Core\Settings\SettingsService;
use App\Modules\Experience\Http\Middleware\SystemMaintenance;
use App\Modules\Identity\Http\Middleware\SetTenantFromUser;
use App\Modules\Platform\Jobs\QueueWebhookEvent;
use App\Modules\Platform\Models\Webhook;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Laravel\Sanctum\Sanctum;

/**
 * The platform surfaces of specification §4.22: the token-only data API with
 * its own guard and per-token rate limit, outgoing webhooks fed by committed
 * record events, and the public inbound endpoint (ADR-0050).
 */
final class PlatformServiceProvider extends ServiceProvider
{
    /** Record events webhooks can subscribe to, by outbox event. */
    public const EVENTS = ['record.saved', 'record.transitioned', 'record.deleted'];

    public function boot(): void
    {
        $this->app->make(TranslatableRegistry::class)->register('webhook', Webhook::class, ['name'], 'translations.type.webhook');

        // The application's own endpoints take the signed-in session only: Sanctum never reads a bearer
        // token (personal API tokens have their own guard, on the data API alone).
        Sanctum::getAccessTokenFromRequestUsing(static fn () => null);

        Auth::viaRequest('api-token', static fn (Request $request) => app(ApiTokens::class)->authenticate($request));

        RateLimiter::for('api-token', static function (Request $request): Limit {
            $token = ApiTokens::current($request);
            $limit = $token->rate_limit_per_minute ?? (int) app(SettingsService::class)->get('api', 'token_rate_limit_per_minute');

            return Limit::perMinute(max(1, $limit))->by('api-token:'.($token->id ?? $request->ip()));
        });
        RateLimiter::for('inbound', static fn (Request $request): Limit => Limit::perMinute(120)->by('inbound:'.$request->route('slug').':'.$request->ip()));

        // Token requests: no session, no CSRF; the tenant is the token owner's.
        Route::middlewareGroup('lcf.token', ['auth:api-token', SetTenantFromUser::class, 'throttle:api-token', SystemMaintenance::class]);

        $relay = $this->app->make(OutboxRelay::class);
        foreach (self::EVENTS as $type) {
            $relay->on($type, static function (OutboxEvent $e): void {
                QueueWebhookEvent::dispatch($e->event_type, $e->payload, (int) $e->organization_id);
            });
        }

        if (! $this->app->routesAreCached()) {
            // Inbound endpoints live outside the versioned API: the address is given to other systems.
            Route::post('api/inbound/{slug}', [Http\Controllers\InboundController::class, 'receive'])
                ->where('slug', '[a-z0-9][a-z0-9_-]{1,63}')
                ->middleware(['throttle:inbound']);
        }
    }
}
