<?php

declare(strict_types=1);

namespace App\Modules\Blueprints\Models;

use App\Modules\Audit\Auditable;
use App\Modules\Core\I18n\HasTranslations;
use App\Support\Models\BaseModel;
use App\Support\Models\BelongsToOrganization;
use App\Support\Models\HasStableUuid;
use App\Support\Models\TracksActor;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A reusable structure (specification §4.30, architecture §10.17).
 *
 * @property int $id
 * @property string $uuid
 * @property int $organization_id
 * @property string $kind
 * @property string|null $category
 * @property list<string>|null $tags
 * @property bool $is_library
 * @property int|null $current_version_id
 * @property string|null $source_type
 * @property int|null $source_id
 * @property BlueprintVersion|null $currentVersion
 */
final class Blueprint extends BaseModel
{
    use Auditable, BelongsToOrganization, HasStableUuid, HasTranslations, SoftDeletes, TracksActor;

    /** @var list<string> */
    public array $translatable = ['name', 'description'];

    protected $fillable = ['kind', 'category', 'tags', 'is_library', 'source_type', 'source_id'];

    protected function casts(): array
    {
        return ['tags' => 'array', 'is_library' => 'boolean'];
    }

    /** @return HasMany<BlueprintVersion, $this> */
    public function versions(): HasMany
    {
        return $this->hasMany(BlueprintVersion::class);
    }

    /** @return BelongsTo<BlueprintVersion, $this> */
    public function currentVersion(): BelongsTo
    {
        return $this->belongsTo(BlueprintVersion::class, 'current_version_id');
    }

    /** @return HasMany<BlueprintInstance, $this> */
    public function instances(): HasMany
    {
        return $this->hasMany(BlueprintInstance::class);
    }

    public function translationType(): string
    {
        return 'blueprint';
    }

    public function auditCategory(): string
    {
        return 'config';
    }

    public function auditType(): string
    {
        return 'blueprint';
    }
}
