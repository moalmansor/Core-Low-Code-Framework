<?php

declare(strict_types=1);

namespace App\Modules\Access;

use App\Modules\Access\Models\Role;
use App\Modules\Identity\Models\User;
use Closure;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Lockout guard (specification §4.11): no change to grants, roles, memberships,
 * or users may leave the installation without an active Super Admin who can
 * manage permissions. The change runs in a transaction, the invariant is checked
 * against the uncached state, and the transaction is rolled back if it fails.
 */
final class AccessGuard
{
    public function __construct(
        private readonly AccessResolver $resolver,
        private readonly AccessCache $cache,
    ) {}

    /**
     * @template T
     *
     * @param  Closure(): T  $change
     * @return T
     */
    public function guarded(Closure $change): mixed
    {
        return DB::transaction(function () use ($change): mixed {
            $result = $change();
            $this->resolver->forget();
            if (! $this->hasManagingSuperAdmin()) {
                throw ValidationException::withMessages(['access' => __('ui.access.lockout_guard')]);
            }
            $this->cache->bump();

            return $result;
        });
    }

    public function hasManagingSuperAdmin(): bool
    {
        $role = Role::query()->where('key', 'super_admin')->first();
        if ($role === null) {
            return false;
        }
        $candidates = User::query()->where('status', 'active')
            ->whereHas('activeRoles', static fn ($q) => $q->where('roles.id', $role->id))
            ->get();
        foreach ($candidates as $user) {
            if ($this->resolver->allowsFresh($user, 'system.manage_permissions')) {
                return true;
            }
        }

        return false;
    }
}
