<?php

declare(strict_types=1);

namespace App\Modules\Forms\Models;

use App\Modules\Audit\Auditable;
use App\Modules\Core\I18n\HasTranslations;
use App\Support\Models\BaseModel;
use App\Support\Models\BelongsToOrganization;
use App\Support\Models\HasStableUuid;
use App\Support\Models\TracksActor;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A sidebar entry of an application (specification §4.13, §4.29).
 *
 * @property int $id
 * @property string $uuid
 * @property int $application_id
 * @property int|null $parent_id
 * @property string $type
 * @property string|null $target_type
 * @property int|null $target_id
 * @property string|null $url
 * @property bool $open_in_new_tab
 * @property string|null $icon
 * @property array<string, mixed>|null $badge
 * @property int|null $visibility_condition_id
 * @property int $sort_order
 * @property bool $is_active
 */
final class MenuItem extends BaseModel
{
    use Auditable, BelongsToOrganization, HasStableUuid, HasTranslations, TracksActor;

    /** @var list<string> */
    public array $translatable = ['label'];

    protected $fillable = ['application_id', 'parent_id', 'type', 'target_type', 'target_id', 'url', 'open_in_new_tab', 'icon', 'badge', 'visibility_condition_id', 'sort_order', 'is_active'];

    protected function casts(): array
    {
        return ['open_in_new_tab' => 'boolean', 'badge' => 'array', 'is_active' => 'boolean', 'sort_order' => 'integer'];
    }

    /** @return BelongsTo<Application, $this> */
    public function application(): BelongsTo
    {
        return $this->belongsTo(Application::class);
    }

    /** @return HasMany<MenuItem, $this> */
    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    public function translationType(): string
    {
        return 'menu_item';
    }

    public function auditCategory(): string
    {
        return 'config';
    }

    public function auditType(): string
    {
        return 'menu_item';
    }
}
