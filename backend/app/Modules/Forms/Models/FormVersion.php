<?php

declare(strict_types=1);

namespace App\Modules\Forms\Models;

use App\Support\Models\BaseModel;
use App\Support\Models\BelongsToOrganization;
use App\Support\Models\HasStableUuid;
use App\Support\Models\TracksActor;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * An immutable published definition (specification §4.10). Only `state`
 * changes after insert (published → superseded / rolled_back).
 *
 * @property int $id
 * @property string $uuid
 * @property int $form_id
 * @property int $version_number
 * @property string $state
 * @property array<string, mixed> $definition
 * @property string $definition_hash
 * @property string $schema_hash
 * @property string $change_class
 * @property array<string, mixed>|null $diff_from_previous
 * @property array<string, mixed>|null $impact_report
 * @property int|null $migration_plan_id
 * @property int|null $snapshot_id
 * @property int|null $rollback_of_version_id
 * @property Carbon $published_at
 * @property int $published_by
 * @property string|null $change_note
 */
final class FormVersion extends BaseModel
{
    use BelongsToOrganization, HasStableUuid, TracksActor;

    protected $fillable = [
        'form_id', 'version_number', 'state', 'definition', 'definition_hash', 'schema_hash', 'change_class',
        'diff_from_previous', 'impact_report', 'migration_plan_id', 'snapshot_id', 'rollback_of_version_id',
        'published_at', 'published_by', 'change_note',
    ];

    protected function casts(): array
    {
        return [
            'definition' => 'array',
            'diff_from_previous' => 'array',
            'impact_report' => 'array',
            'published_at' => 'datetime',
            'version_number' => 'integer',
        ];
    }

    /** @return BelongsTo<Form, $this> */
    public function form(): BelongsTo
    {
        return $this->belongsTo(Form::class);
    }
}
