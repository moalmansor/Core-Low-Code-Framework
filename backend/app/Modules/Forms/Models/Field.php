<?php

declare(strict_types=1);

namespace App\Modules\Forms\Models;

use App\Modules\Core\I18n\HasTranslations;
use App\Support\Models\BaseModel;
use App\Support\Models\BelongsToOrganization;
use App\Support\Models\HasStableUuid;
use App\Support\Models\TracksActor;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * A field of a draft (specification §4.6). Removing a published field archives
 * it (`archived_at`) and its column; nothing is dropped.
 *
 * @property int $id
 * @property string $uuid
 * @property int $form_id
 * @property int|null $group_id
 * @property string $key
 * @property string $type
 * @property int $sort_order
 * @property bool $is_stored
 * @property string|null $column_name
 * @property string|null $db_type
 * @property int|null $length
 * @property int|null $precision
 * @property int|null $scale
 * @property bool $is_nullable
 * @property mixed $db_default
 * @property string $index_type
 * @property list<string>|null $unique_scope
 * @property bool $is_encrypted
 * @property bool $blind_index
 * @property bool $is_sensitive
 * @property bool $is_personal_data
 * @property bool $track_changes
 * @property int|null $relation_id
 * @property array<string, mixed>|null $options_source
 * @property array<string, mixed> $validation
 * @property array<string, mixed> $behavior
 * @property array<string, mixed> $ui
 * @property array<string, mixed> $table_settings
 * @property array<string, mixed> $export_settings
 * @property list<array<string, mixed>>|null $events
 * @property array<string, mixed>|null $hook_binding
 * @property string $justification_level
 * @property int|null $template_id
 * @property Carbon|null $archived_at
 * @property string|null $archived_column_name
 */
final class Field extends BaseModel
{
    use BelongsToOrganization, HasStableUuid, HasTranslations, TracksActor;

    /** @var list<string> */
    public array $translatable = ['label', 'placeholder', 'help_text', 'tooltip', 'description', 'prefix', 'suffix', 'column_label', 'content', 'consent_terms'];

    protected $fillable = [
        'form_id', 'group_id', 'key', 'type', 'sort_order', 'is_stored', 'column_name', 'db_type', 'length', 'precision', 'scale',
        'is_nullable', 'db_default', 'index_type', 'unique_scope', 'is_encrypted', 'blind_index', 'is_sensitive', 'is_personal_data',
        'track_changes', 'relation_id', 'options_source', 'validation', 'behavior', 'ui', 'table_settings', 'export_settings',
        'events', 'hook_binding', 'justification_level', 'template_id', 'archived_at', 'archived_column_name',
    ];

    protected function casts(): array
    {
        return [
            'is_stored' => 'boolean', 'is_nullable' => 'boolean', 'is_encrypted' => 'boolean', 'blind_index' => 'boolean',
            'is_sensitive' => 'boolean', 'is_personal_data' => 'boolean', 'track_changes' => 'boolean',
            'db_default' => 'array', 'unique_scope' => 'array', 'options_source' => 'array', 'validation' => 'array',
            'behavior' => 'array', 'ui' => 'array', 'table_settings' => 'array', 'export_settings' => 'array',
            'events' => 'array', 'hook_binding' => 'array', 'archived_at' => 'datetime',
            'sort_order' => 'integer', 'length' => 'integer', 'precision' => 'integer', 'scale' => 'integer',
        ];
    }

    /** @return HasMany<FieldOption, $this> */
    public function options(): HasMany
    {
        return $this->hasMany(FieldOption::class);
    }

    public function translationType(): string
    {
        return 'field';
    }
}
