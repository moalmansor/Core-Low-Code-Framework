<?php

declare(strict_types=1);

namespace App\Modules\Schema\Models;

use App\Support\Models\BaseModel;
use App\Support\Models\BelongsToOrganization;
use App\Support\Models\HasStableUuid;
use App\Support\Models\TracksActor;
use Illuminate\Support\Carbon;

/**
 * A pre-publish snapshot (architecture §12.5): metadata, physical schema, or
 * a data backup of the affected tables.
 *
 * @property int $id
 * @property string $uuid
 * @property int $form_id
 * @property int|null $migration_plan_id
 * @property string $kind
 * @property array<string, mixed> $tables
 * @property string $disk
 * @property string $path
 * @property int $size_bytes
 * @property string $checksum
 * @property Carbon $expires_at
 * @property Carbon|null $restored_at
 */
final class SchemaSnapshot extends BaseModel
{
    use BelongsToOrganization, HasStableUuid, TracksActor;

    protected $fillable = ['form_id', 'migration_plan_id', 'kind', 'tables', 'disk', 'path', 'size_bytes', 'checksum', 'expires_at', 'restored_at'];

    protected function casts(): array
    {
        return ['tables' => 'array', 'expires_at' => 'datetime', 'restored_at' => 'datetime', 'size_bytes' => 'integer'];
    }
}
