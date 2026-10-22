<?php

declare(strict_types=1);

namespace App\Modules\Experience;

use App\Modules\Access\AccessResolver;
use App\Modules\Core\I18n\Translator;
use App\Modules\Core\Models\Organization;
use App\Modules\Core\Settings\SettingsService;
use App\Modules\Core\Tenancy\TenantContext;
use App\Modules\Forms\Models\Application;
use App\Modules\Identity\Models\User;
use Illuminate\Support\Carbon;

/**
 * Maintenance mode (specification §4.29, architecture §19.17): the whole
 * system or one application taken offline with a message. People who may
 * switch maintenance mode keep their access and see a banner instead.
 */
final class Maintenance
{
    public function __construct(
        private readonly AccessResolver $access,
        private readonly SettingsService $settings,
        private readonly Translator $translator,
    ) {}

    public function applicationOffline(Application $app): bool
    {
        return $app->maintenance_mode && ($app->maintenance_until === null || Carbon::parse($app->maintenance_until, 'UTC')->isFuture());
    }

    /** Whether the application is offline for this person. */
    public function blocks(Application $app, User $user): bool
    {
        return $this->applicationOffline($app) && ! $this->exempt($user);
    }

    public function systemOffline(): bool
    {
        if (! (bool) $this->settings->get('maintenance', 'enabled')) {
            return false;
        }
        $until = $this->settings->get('maintenance', 'until');

        return $until === null || Carbon::parse((string) $until, 'UTC')->isFuture();
    }

    public function blocksSystem(User $user): bool
    {
        return $this->systemOffline() && ! $this->exempt($user);
    }

    public function exempt(User $user): bool
    {
        return $this->access->allows($user, 'system.enable_maintenance_mode');
    }

    /** @return array{active: bool, until: string|null, message: string|null, messages: array<string, string>} */
    public function system(): array
    {
        $platform = Organization::query()->find(app(TenantContext::class)->platformOrganizationId());
        $messages = $platform?->translationsFor('maintenance_message') ?? [];
        $until = $this->settings->get('maintenance', 'until');

        return [
            'active' => $this->systemOffline(),
            'until' => $until === null ? null : Carbon::parse((string) $until, 'UTC')->toIso8601ZuluString(),
            'message' => $this->translator->pick($messages) ?? __('experience.system_maintenance'),
            'messages' => $messages,
        ];
    }
}
