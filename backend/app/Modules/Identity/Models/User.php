<?php

declare(strict_types=1);

namespace App\Modules\Identity\Models;

use App\Modules\Access\Models\Role;
use App\Modules\Audit\Auditable;
use App\Modules\Organization\Models\Department;
use App\Support\Models\BelongsToOrganization;
use App\Support\Models\HasStableUuid;
use App\Support\Models\TracksActor;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Fortify\TwoFactorAuthenticatable;

/**
 * @property int $id
 * @property string $uuid
 * @property int $organization_id
 * @property string $name
 * @property string $email
 * @property string|null $username
 * @property string|null $password
 * @property int|null $department_id
 * @property int|null $manager_id
 * @property string|null $job_title
 * @property string|null $phone
 * @property string $status
 * @property string $auth_source
 * @property string|null $external_subject
 * @property array<string, mixed>|null $attributes
 * @property \Illuminate\Support\Carbon|null $two_factor_confirmed_at
 * @property \Illuminate\Support\Carbon|null $password_changed_at
 * @property \Illuminate\Support\Carbon|null $last_login_at
 * @property int $failed_login_count
 * @property \Illuminate\Support\Carbon|null $locked_until
 */
final class User extends Authenticatable
{
    use Auditable, BelongsToOrganization, HasStableUuid, Notifiable, SoftDeletes, TracksActor, TwoFactorAuthenticatable;

    protected $dateFormat = 'Y-m-d H:i:s.u';

    protected $fillable = ['name', 'email', 'username', 'department_id', 'manager_id', 'job_title', 'phone', 'status', 'auth_source', 'external_subject', 'attributes'];

    /** @var list<string> */
    protected $hidden = ['password', 'remember_token', 'two_factor_secret', 'two_factor_recovery_codes'];

    /** Bookkeeping columns that are not administrative changes. */
    protected array $auditExclude = ['last_login_at', 'last_login_ip', 'failed_login_count', 'remember_token'];

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'attributes' => 'array',
            'email_verified_at' => 'datetime',
            'two_factor_confirmed_at' => 'datetime',
            'password_changed_at' => 'datetime',
            'last_login_at' => 'datetime',
            'locked_until' => 'datetime',
            'anonymized_at' => 'datetime',
            'failed_login_count' => 'integer',
        ];
    }

    /** @return BelongsToMany<Role, $this> */
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'user_roles')
            ->withPivot(['valid_from', 'valid_until', 'assigned_by', 'created_at']);
    }

    /** @return BelongsTo<Department, $this> */
    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    /** @return HasOne<UserPreference, $this> */
    public function preference(): HasOne
    {
        return $this->hasOne(UserPreference::class);
    }

    /** @return BelongsTo<User, $this> */
    public function manager(): BelongsTo
    {
        return $this->belongsTo(self::class, 'manager_id');
    }

    /** Roles whose validity window includes now. */
    public function activeRoles(): BelongsToMany
    {
        $now = now()->format('Y-m-d H:i:s.u');

        return $this->roles()
            ->where(static fn ($q) => $q->whereNull('user_roles.valid_from')->orWhere('user_roles.valid_from', '<=', $now))
            ->where(static fn ($q) => $q->whereNull('user_roles.valid_until')->orWhere('user_roles.valid_until', '>', $now));
    }

    /** @return list<string> */
    public function roleKeys(): array
    {
        return $this->activeRoles()->pluck('key')->all();
    }

    /** Whether any active role requires two-factor authentication (§2, §5). */
    public function requiresTwoFactor(): bool
    {
        return $this->activeRoles()->where('requires_2fa', true)->exists();
    }

    public function isLocked(): bool
    {
        return $this->locked_until !== null && $this->locked_until->isFuture();
    }

    public function auditCategory(): string
    {
        return 'access';
    }

    public function auditType(): string
    {
        return 'user';
    }
}
