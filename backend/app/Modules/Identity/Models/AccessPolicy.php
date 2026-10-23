<?php

declare(strict_types=1);

namespace App\Modules\Identity\Models;

use App\Modules\Core\I18n\HasTranslations;
use App\Support\Models\BaseModel;
use App\Support\Models\BelongsToOrganization;
use App\Support\Models\HasStableUuid;
use App\Support\Models\TracksActor;

/**
 * An access policy given to roles (specification §4.36, ADR-0060): where
 * and when its users may work, how many sessions they may hold and for how
 * long, and whether a second factor and a trusted device are required. An
 * administrative policy also applies to everyone holding an administrative
 * role. Its name is translatable.
 *
 * @property int $id
 * @property string $uuid
 * @property string $key
 * @property list<string>|null $ip_allowlist
 * @property list<array{days: list<int>, from: string, to: string, timezone: string}>|null $time_windows
 * @property int|null $max_concurrent_sessions
 * @property int $session_idle_minutes
 * @property int $session_absolute_minutes
 * @property bool $require_trusted_device
 * @property bool $require_2fa
 * @property bool $is_admin_policy
 */
final class AccessPolicy extends BaseModel
{
    use BelongsToOrganization, HasStableUuid, HasTranslations, TracksActor;

    /** @var list<string> */
    public array $translatable = ['name'];

    protected $table = 'access_policies';

    protected function casts(): array
    {
        return [
            'ip_allowlist' => 'array', 'time_windows' => 'array', 'max_concurrent_sessions' => 'integer',
            'session_idle_minutes' => 'integer', 'session_absolute_minutes' => 'integer',
            'require_trusted_device' => 'boolean', 'require_2fa' => 'boolean', 'is_admin_policy' => 'boolean',
        ];
    }

    public function translationType(): string
    {
        return 'access_policy';
    }
}
