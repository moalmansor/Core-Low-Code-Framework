<?php

declare(strict_types=1);

namespace App\Modules\Forms\Models;

use App\Support\Models\BaseModel;
use App\Support\Models\BelongsToOrganization;
use App\Support\Models\HasStableUuid;
use App\Support\Models\TracksActor;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A relation between two forms/collections (specification §4.8, §4.9).
 *
 * @property int $id
 * @property string $uuid
 * @property string $key
 * @property int $source_form_id
 * @property int $target_form_id
 * @property string $type
 * @property string $kind
 * @property string $fk_table
 * @property string|null $fk_column
 * @property string|null $pivot_table
 * @property int|null $display_field_id
 * @property int|null $value_field_id
 * @property string $on_delete
 * @property string|null $inverse_key
 * @property bool $is_cross_application
 */
final class Relation extends BaseModel
{
    use BelongsToOrganization, HasStableUuid, TracksActor;

    protected $fillable = ['key', 'source_form_id', 'target_form_id', 'type', 'kind', 'fk_table', 'fk_column', 'pivot_table', 'display_field_id', 'value_field_id', 'on_delete', 'inverse_key', 'is_cross_application'];

    protected function casts(): array
    {
        return ['is_cross_application' => 'boolean'];
    }

    /** @return BelongsTo<Form, $this> */
    public function target(): BelongsTo
    {
        return $this->belongsTo(Form::class, 'target_form_id');
    }

    /** @return BelongsTo<Form, $this> */
    public function source(): BelongsTo
    {
        return $this->belongsTo(Form::class, 'source_form_id');
    }
}
