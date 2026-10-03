<?php

declare(strict_types=1);

namespace App\Modules\Access\Models;

use App\Modules\Audit\Auditable;
use App\Modules\Core\I18n\HasTranslations;
use App\Modules\Identity\Models\User;
use App\Support\Models\BaseModel;
use App\Support\Models\BelongsToOrganization;
use App\Support\Models\HasStableUuid;
use App\Support\Models\TracksActor;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * @property int $id
 * @property string $uuid
 * @property string $key
 * @property bool $is_system
 * @property string $audience
 * @property bool $requires_2fa
 * @property bool $is_admin_role
 * @property int $sort_order
 */
final class Role extends BaseModel
{
    use Auditable, BelongsToOrganization, HasStableUuid, HasTranslations, TracksActor;

    /** @var list<string> */
    public array $translatable = ['name', 'description'];

    protected $fillable = ['key', 'is_system', 'audience', 'requires_2fa', 'is_admin_role', 'sort_order'];

    protected function casts(): array
    {
        return ['is_system' => 'boolean', 'requires_2fa' => 'boolean', 'is_admin_role' => 'boolean', 'sort_order' => 'integer'];
    }

    /** @return BelongsToMany<User, $this> */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'user_roles')->withPivot(['valid_from', 'valid_until', 'assigned_by', 'created_at']);
    }

    public function translationType(): string
    {
        return 'role';
    }

    public function auditCategory(): string
    {
        return 'access';
    }

    public function auditType(): string
    {
        return 'role';
    }
}
