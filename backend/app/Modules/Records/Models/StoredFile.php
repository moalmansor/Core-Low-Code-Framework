<?php

declare(strict_types=1);

namespace App\Modules\Records\Models;

use App\Support\Models\BaseModel;
use App\Support\Models\BelongsToOrganization;
use App\Support\Models\HasStableUuid;

/**
 * @property int $id
 * @property string $uuid
 * @property string $disk
 * @property string $path
 * @property string $original_name
 * @property string $mime_type
 * @property string $extension
 * @property int $size_bytes
 * @property string $sha256
 * @property string $scan_status
 * @property string|null $owner_type
 * @property int|null $owner_id
 * @property bool $is_temporary
 * @property int|null $uploaded_by
 * @property int|null $form_id
 * @property int|null $record_id
 * @property int|null $field_id
 * @property int|null $width
 * @property int|null $height
 */
final class StoredFile extends BaseModel
{
    use BelongsToOrganization, HasStableUuid;

    protected $table = 'files';

    protected $fillable = ['disk', 'path', 'original_name', 'mime_type', 'extension', 'size_bytes', 'sha256', 'scan_status', 'scanned_at', 'width', 'height', 'is_encrypted', 'is_temporary', 'owner_type', 'owner_id', 'uploaded_by', 'deleted_at', 'form_id', 'record_id', 'field_id'];

    protected function casts(): array
    {
        return ['scanned_at' => 'datetime', 'deleted_at' => 'datetime', 'is_encrypted' => 'boolean', 'is_temporary' => 'boolean', 'size_bytes' => 'integer'];
    }
}
