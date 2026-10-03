<?php

declare(strict_types=1);

namespace App\Modules\Forms\Models;

use App\Support\Models\BaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Collection-specific settings of a `kind=collection` form (specification §4.8).
 *
 * @property int $id
 * @property int $form_id
 * @property string $collection_type
 * @property bool $is_shared_reference
 * @property int|null $owner_application_id
 * @property int|null $value_field_id
 * @property int|null $label_field_id
 * @property int|null $parent_field_id
 */
final class Collection extends BaseModel
{
    protected $fillable = ['form_id', 'collection_type', 'is_shared_reference', 'owner_application_id', 'value_field_id', 'label_field_id', 'parent_field_id'];

    protected function casts(): array
    {
        return ['is_shared_reference' => 'boolean'];
    }

    /** @return BelongsTo<Form, $this> */
    public function form(): BelongsTo
    {
        return $this->belongsTo(Form::class);
    }
}
