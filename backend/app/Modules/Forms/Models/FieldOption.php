<?php

declare(strict_types=1);

namespace App\Modules\Forms\Models;

use App\Modules\Core\I18n\HasTranslations;
use App\Support\Models\BaseModel;
use App\Support\Models\BelongsToOrganization;
use App\Support\Models\HasStableUuid;

/**
 * A static option of a choice field.
 *
 * @property int $id
 * @property string $uuid
 * @property int $field_id
 * @property string $value
 * @property string|null $group_key
 * @property string|null $parent_value
 * @property string|null $color
 * @property string|null $icon
 * @property bool $is_default
 * @property bool $is_active
 * @property int $sort_order
 * @property int|null $condition_id
 */
final class FieldOption extends BaseModel
{
    use BelongsToOrganization, HasStableUuid, HasTranslations;

    /** @var list<string> */
    public array $translatable = ['label'];

    protected $fillable = ['field_id', 'value', 'group_key', 'parent_value', 'color', 'icon', 'is_default', 'is_active', 'sort_order', 'condition_id'];

    protected function casts(): array
    {
        return ['is_default' => 'boolean', 'is_active' => 'boolean', 'sort_order' => 'integer'];
    }

    public function translationType(): string
    {
        return 'field_option';
    }
}
