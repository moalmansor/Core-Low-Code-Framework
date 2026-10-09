<?php

declare(strict_types=1);

namespace App\Modules\Schema\Models;

use App\Support\Models\BaseModel;
use Illuminate\Support\Carbon;

/**
 * One step of a migration plan with its forward and reverse specs.
 *
 * @property int $id
 * @property int $migration_plan_id
 * @property int $sequence
 * @property string $operation
 * @property string $table_name
 * @property array<string, mixed> $forward
 * @property array<string, mixed> $reverse
 * @property string|null $sql_preview
 * @property bool $is_destructive
 * @property bool $is_online
 * @property int|null $estimated_ms
 * @property string $status
 * @property Carbon|null $started_at
 * @property Carbon|null $applied_at
 * @property Carbon|null $reversed_at
 * @property int|null $duration_ms
 * @property string|null $error
 */
final class MigrationStep extends BaseModel
{
    protected $fillable = [
        'migration_plan_id', 'sequence', 'operation', 'table_name', 'forward', 'reverse', 'sql_preview', 'is_destructive',
        'is_online', 'estimated_ms', 'status', 'started_at', 'applied_at', 'reversed_at', 'duration_ms', 'error',
    ];

    protected function casts(): array
    {
        return [
            'forward' => 'array', 'reverse' => 'array', 'is_destructive' => 'boolean', 'is_online' => 'boolean',
            'started_at' => 'datetime', 'applied_at' => 'datetime', 'reversed_at' => 'datetime', 'sequence' => 'integer',
        ];
    }
}
