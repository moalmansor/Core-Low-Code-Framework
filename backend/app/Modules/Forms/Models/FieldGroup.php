<?php

declare(strict_types=1);

namespace App\Modules\Forms\Models;

use App\Modules\Core\I18n\HasTranslations;
use App\Support\Models\BaseModel;
use App\Support\Models\BelongsToOrganization;
use App\Support\Models\HasStableUuid;
use App\Support\Models\TracksActor;
use Illuminate\Support\Carbon;

/**
 * A layout or data group of a draft (specification §4.5).
 *
 * @property int $id
 * @property string $uuid
 * @property int $form_id
 * @property int|null $parent_group_id
 * @property string $key
 * @property string $type
 * @property int $sort_order
 * @property array<string, mixed> $layout
 * @property bool $collapsible
 * @property string $default_state
 * @property array<string, mixed>|null $validation
 * @property array<string, mixed>|null $repeater
 * @property array<string, mixed>|null $wizard
 * @property string|null $child_table_name
 * @property int|null $subform_form_id
 * @property int|null $relation_id
 * @property string $justification_level
 * @property Carbon|null $archived_at
 */
final class FieldGroup extends BaseModel
{
    use BelongsToOrganization, HasStableUuid, HasTranslations, TracksActor;

    /** @var list<string> */
    public array $translatable = ['title', 'description'];

    protected $fillable = [
        'form_id', 'parent_group_id', 'key', 'type', 'sort_order', 'layout', 'collapsible', 'default_state', 'validation',
        'repeater', 'wizard', 'child_table_name', 'subform_form_id', 'relation_id', 'justification_level', 'archived_at',
    ];

    protected function casts(): array
    {
        return [
            'layout' => 'array', 'validation' => 'array', 'repeater' => 'array', 'wizard' => 'array',
            'collapsible' => 'boolean', 'archived_at' => 'datetime', 'sort_order' => 'integer',
        ];
    }

    public function translationType(): string
    {
        return 'field_group';
    }
}
