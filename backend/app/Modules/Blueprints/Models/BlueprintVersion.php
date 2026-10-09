<?php

declare(strict_types=1);

namespace App\Modules\Blueprints\Models;

use App\Support\Models\BaseModel;
use App\Support\Models\HasStableUuid;
use App\Support\Models\TracksActor;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * An immutable version of a blueprint's content.
 *
 * @property int $id
 * @property string $uuid
 * @property int $blueprint_id
 * @property int $version
 * @property array<string, mixed> $content
 * @property string $content_hash
 * @property string $include_mode
 * @property string|null $changelog
 * @property Carbon|null $created_at
 */
final class BlueprintVersion extends BaseModel
{
    use HasStableUuid, TracksActor;

    protected $fillable = ['blueprint_id', 'version', 'content', 'content_hash', 'include_mode', 'changelog'];

    protected function casts(): array
    {
        return ['content' => 'array', 'version' => 'integer'];
    }

    /** @return BelongsTo<Blueprint, $this> */
    public function blueprint(): BelongsTo
    {
        return $this->belongsTo(Blueprint::class);
    }
}
