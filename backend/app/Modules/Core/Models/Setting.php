<?php

declare(strict_types=1);

namespace App\Modules\Core\Models;

use App\Support\Casts\JsonEnvelope;
use App\Support\Models\BaseModel;
use App\Support\Models\BelongsToOrganization;

/**
 * @property int $id
 * @property string $group
 * @property string $key
 * @property mixed $value
 * @property string|null $encrypted_value
 * @property bool $is_encrypted
 */
final class Setting extends BaseModel
{
    use BelongsToOrganization;

    protected $fillable = ['organization_id', 'group', 'key', 'value', 'encrypted_value', 'is_encrypted', 'updated_by'];

    /** @var list<string> */
    protected $hidden = ['encrypted_value'];

    protected function casts(): array
    {
        return ['value' => JsonEnvelope::class, 'is_encrypted' => 'boolean'];
    }
}
