<?php

declare(strict_types=1);

namespace App\Modules\Blueprints\Models;

use App\Support\Models\BaseModel;
use App\Support\Models\BelongsToOrganization;
use App\Support\Models\HasStableUuid;
use App\Support\Models\TracksActor;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * An object created from a blueprint version, kept linked for propagation
 * until detached.
 *
 * @property int $id
 * @property string $uuid
 * @property int $organization_id
 * @property int $blueprint_id
 * @property int $blueprint_version_id
 * @property string $object_type
 * @property int $object_id
 * @property string $include_mode
 * @property int|null $last_propagated_version_id
 * @property bool $is_detached
 */
final class BlueprintInstance extends BaseModel
{
    use BelongsToOrganization, HasStableUuid, TracksActor;

    protected $fillable = ['blueprint_id', 'blueprint_version_id', 'object_type', 'object_id', 'include_mode', 'last_propagated_version_id', 'is_detached'];

    protected function casts(): array
    {
        return ['is_detached' => 'boolean'];
    }

    /** @return BelongsTo<Blueprint, $this> */
    public function blueprint(): BelongsTo
    {
        return $this->belongsTo(Blueprint::class);
    }

    /** The version the object currently follows: the last one propagated, else the one it was created from. */
    public function baseVersionId(): int
    {
        return $this->last_propagated_version_id ?? $this->blueprint_version_id;
    }
}
