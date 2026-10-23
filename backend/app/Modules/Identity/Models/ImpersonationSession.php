<?php

declare(strict_types=1);

namespace App\Modules\Identity\Models;

use App\Support\Models\BaseModel;
use App\Support\Models\BelongsToOrganization;
use App\Support\Models\HasStableUuid;

/**
 * One impersonation (specification §4.36, ADR-0061): who acted as whom,
 * why, from where, until when, and how it ended.
 *
 * @property int $id
 * @property string $uuid
 * @property int $impersonator_user_id
 * @property int $impersonated_user_id
 * @property string $reason
 * @property string $started_at
 * @property string $expires_at
 * @property string|null $ended_at
 * @property string|null $end_reason
 * @property string $ip_address
 * @property string|null $user_agent
 */
final class ImpersonationSession extends BaseModel
{
    use BelongsToOrganization, HasStableUuid;

    protected $table = 'impersonation_sessions';
}
