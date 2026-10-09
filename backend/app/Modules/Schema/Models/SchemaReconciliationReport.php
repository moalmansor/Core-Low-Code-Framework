<?php

declare(strict_types=1);

namespace App\Modules\Schema\Models;

use App\Support\Models\BaseModel;
use App\Support\Models\BelongsToOrganization;
use Illuminate\Support\Carbon;

/**
 * Result of comparing metadata with the live schema (architecture §12.6).
 *
 * @property int $id
 * @property int|null $form_id
 * @property string $trigger
 * @property string $engine
 * @property string $status
 * @property int $difference_count
 * @property list<array<string, mixed>> $differences
 * @property int|null $triggered_by
 * @property Carbon $started_at
 * @property Carbon|null $finished_at
 */
final class SchemaReconciliationReport extends BaseModel
{
    use BelongsToOrganization;

    protected $fillable = ['form_id', 'trigger', 'engine', 'status', 'difference_count', 'differences', 'triggered_by', 'started_at', 'finished_at'];

    protected function casts(): array
    {
        return ['differences' => 'array', 'started_at' => 'datetime', 'finished_at' => 'datetime', 'difference_count' => 'integer'];
    }
}
