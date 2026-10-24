<?php

declare(strict_types=1);

namespace App\Modules\Extensions\Models;

use App\Support\Models\BaseModel;
use App\Support\Models\HasStableUuid;
use Illuminate\Support\Carbon;

/**
 * One version of an extension: where its files are, the checksum of what
 * runs, its manifest, its last test and who submitted and approved it.
 *
 * @property int $id
 * @property string $uuid
 * @property int $extension_id
 * @property int $version
 * @property string $source_path
 * @property string $checksum
 * @property array<string, mixed> $manifest
 * @property string $test_status
 * @property array<string, mixed>|null $test_report
 * @property int $submitted_by
 * @property int|null $approved_by
 * @property Carbon|null $approved_at
 * @property Carbon|null $deployed_at
 * @property string|null $rejected_reason
 * @property Carbon|null $created_at
 */
final class ExtensionVersion extends BaseModel
{
    use HasStableUuid;

    protected $table = 'extension_versions';

    protected function casts(): array
    {
        return ['manifest' => 'array', 'test_report' => 'array', 'approved_at' => 'datetime', 'deployed_at' => 'datetime', 'version' => 'integer'];
    }
}
