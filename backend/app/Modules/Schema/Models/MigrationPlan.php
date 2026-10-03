<?php

declare(strict_types=1);

namespace App\Modules\Schema\Models;

use App\Support\Models\BaseModel;
use App\Support\Models\BelongsToOrganization;
use App\Support\Models\HasStableUuid;
use App\Support\Models\TracksActor;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * An ordered, persisted list of reversible schema steps (architecture §12.1).
 *
 * @property int $id
 * @property string $uuid
 * @property int $organization_id
 * @property int $form_id
 * @property int|null $from_version_id
 * @property int $to_version_number
 * @property string $purpose
 * @property string $status
 * @property int $steps_total
 * @property int $steps_applied
 * @property int|null $snapshot_id
 * @property array<string, mixed> $impact
 * @property string|null $lock_token
 * @property int|null $confirmed_by
 * @property Carbon|null $confirmed_at
 * @property Carbon|null $started_at
 * @property Carbon|null $finished_at
 * @property string|null $error
 * @property string $correlation_id
 * @property \Illuminate\Support\Carbon|null $created_at
 */
final class MigrationPlan extends BaseModel
{
    use BelongsToOrganization, HasStableUuid, TracksActor;

    protected $fillable = [
        'form_id', 'from_version_id', 'to_version_number', 'purpose', 'status', 'steps_total', 'steps_applied',
        'snapshot_id', 'impact', 'lock_token', 'confirmed_by', 'confirmed_at', 'started_at', 'finished_at', 'error', 'correlation_id',
    ];

    protected function casts(): array
    {
        return ['impact' => 'array', 'confirmed_at' => 'datetime', 'started_at' => 'datetime', 'finished_at' => 'datetime', 'steps_total' => 'integer', 'steps_applied' => 'integer'];
    }

    /** @return HasMany<MigrationStep, $this> */
    public function steps(): HasMany
    {
        return $this->hasMany(MigrationStep::class)->orderBy('sequence');
    }
}
