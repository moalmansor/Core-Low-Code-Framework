<?php

declare(strict_types=1);

namespace App\Modules\Monitoring;

use App\Modules\Core\Settings\SettingsService;
use App\Modules\Identity\Models\User;
use App\Modules\Monitoring\Mail\ErrorAlertMail;
use App\Modules\Monitoring\Models\ErrorGroup;
use Illuminate\Support\Facades\Mail;

/**
 * E-mail alerts for new or regressed error groups (specification §4.21),
 * sent to active users holding the configured roles, at most once per
 * cooldown period per group and only at or above the configured severity.
 */
final class ErrorAlerts
{
    private const ORDER = ['debug', 'info', 'notice', 'warning', 'error', 'critical', 'alert', 'emergency'];

    public function __construct(private readonly SettingsService $settings) {}

    public function maybeAlert(ErrorGroup $group, string $reference): void
    {
        $min = (string) $this->settings->get('monitoring', 'alert_min_severity');
        if (array_search($group->severity, self::ORDER, true) < array_search($min, self::ORDER, true)) {
            return;
        }
        $cooldown = (int) $this->settings->get('monitoring', 'alert_cooldown_minutes');
        if ($group->last_alerted_at !== null && $group->last_alerted_at->gt(now()->subMinutes($cooldown))) {
            return;
        }
        /** @var list<string> $roleKeys */
        $roleKeys = (array) $this->settings->get('monitoring', 'alert_role_keys');
        $recipients = User::query()
            ->where('status', 'active')
            ->whereHas('roles', static fn ($q) => $q->whereIn('key', $roleKeys))
            ->pluck('email')->all();
        if ($recipients === []) {
            return;
        }
        $group->forceFill(['last_alerted_at' => now()])->saveQuietly();
        foreach ($recipients as $email) {
            Mail::to($email)->queue(new ErrorAlertMail($group->id, $reference));
        }
    }
}
