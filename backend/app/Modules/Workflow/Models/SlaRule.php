<?php

declare(strict_types=1);

namespace App\Modules\Workflow\Models;

use App\Support\Models\BaseModel;
use App\Support\Models\BelongsToOrganization;
use App\Support\Models\HasStableUuid;
use App\Support\Models\TracksActor;

/**
 * Time allowed in a status, with warnings and escalations (specification
 * §4.12): `escalations` is `[{afterMinutes, action: notify|reassign|transition, params}]`.
 *
 * @property int $id
 * @property string $uuid
 * @property int $form_id
 * @property int $status_id
 * @property int $duration_minutes
 * @property bool $use_working_time
 * @property int|null $business_calendar_id
 * @property int|null $warn_before_minutes
 * @property list<array<string, mixed>> $escalations
 * @property int|null $condition_id
 * @property bool $is_active
 */
final class SlaRule extends BaseModel
{
    use BelongsToOrganization, HasStableUuid, TracksActor;

    protected $fillable = ['form_id', 'status_id', 'duration_minutes', 'use_working_time', 'business_calendar_id', 'warn_before_minutes', 'escalations', 'condition_id', 'is_active', 'archived_at'];

    protected function casts(): array
    {
        return ['use_working_time' => 'boolean', 'is_active' => 'boolean', 'escalations' => 'array', 'duration_minutes' => 'integer', 'warn_before_minutes' => 'integer', 'archived_at' => 'datetime'];
    }
}
