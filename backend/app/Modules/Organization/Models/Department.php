<?php

declare(strict_types=1);

namespace App\Modules\Organization\Models;

use App\Modules\Audit\Auditable;
use App\Modules\Core\I18n\HasTranslations;
use App\Modules\Identity\Models\User;
use App\Support\Models\BaseModel;
use App\Support\Models\BelongsToOrganization;
use App\Support\Models\HasStableUuid;
use App\Support\Models\TracksActor;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property int $id
 * @property string $uuid
 * @property int|null $parent_id
 * @property string $code
 * @property int|null $manager_user_id
 * @property int $depth
 * @property int $sort_order
 * @property bool $is_active
 */
final class Department extends BaseModel
{
    use Auditable, BelongsToOrganization, HasStableUuid, HasTranslations, SoftDeletes, TracksActor;

    /** @var list<string> */
    public array $translatable = ['name'];

    protected $fillable = ['parent_id', 'code', 'manager_user_id', 'depth', 'sort_order', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'depth' => 'integer', 'sort_order' => 'integer'];
    }

    /** @return BelongsTo<Department, $this> */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    /** @return HasMany<Department, $this> */
    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    /** @return BelongsTo<User, $this> */
    public function manager(): BelongsTo
    {
        return $this->belongsTo(User::class, 'manager_user_id');
    }

    /** @return HasMany<User, $this> */
    public function members(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function translationType(): string
    {
        return 'department';
    }

    public function auditCategory(): string
    {
        return 'access';
    }

    public function auditType(): string
    {
        return 'department';
    }
}
