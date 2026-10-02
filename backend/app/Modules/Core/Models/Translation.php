<?php

declare(strict_types=1);

namespace App\Modules\Core\Models;

use App\Support\Models\BaseModel;
use App\Support\Models\BelongsToOrganization;

/**
 * One translated attribute of one object in one locale (ADR-0007).
 *
 * @property int $id
 * @property string $object_type
 * @property int $object_id
 * @property string $field
 * @property string $locale
 * @property string $value
 */
final class Translation extends BaseModel
{
    use BelongsToOrganization;

    public const CREATED_AT = null;

    protected $fillable = ['organization_id', 'object_type', 'object_id', 'field', 'locale', 'value', 'updated_by'];
}
