<?php

declare(strict_types=1);

namespace App\Modules\Identity\Auth;

use App\Modules\Access\AccessCache;
use App\Modules\Access\Models\Role;
use App\Modules\Audit\AuditWriter;
use App\Modules\Identity\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Finds or provisions users signing in through LDAP or OIDC and keeps their
 * directory-managed roles in sync (specification §5). Only roles that appear in
 * the identity provider's role map are added or removed; roles assigned by an
 * administrator are left untouched.
 */
final class UserProvisioner
{
    public function __construct(private readonly AuditWriter $audit) {}

    /**
     * @param  list<string>  $mappedRoleKeys  roles the IdP grants now
     * @param  list<string>  $managedRoleKeys  every role the IdP's map can grant
     */
    public function resolve(
        string $source,
        string $subject,
        ?string $email,
        string $name,
        ?string $username,
        array $mappedRoleKeys,
        array $managedRoleKeys,
        bool $allowCreate,
        ?User $existing = null,
        bool $linkByEmail = false,
    ): ?User {
        return DB::transaction(function () use ($source, $subject, $email, $name, $username, $mappedRoleKeys, $managedRoleKeys, $allowCreate, $existing, $linkByEmail): ?User {
            $query = User::query()->withoutGlobalScope('organization');
            $user = $existing
                ?? (clone $query)->where('auth_source', $source)->where('external_subject', $subject)->first()
                ?? ($linkByEmail && $email !== null ? (clone $query)->where('email', $email)->where('auth_source', $source)->first() : null);

            if ($user === null) {
                if (! $allowCreate || $email === null) {
                    return null;
                }
                $user = new User;
                $user->forceFill([
                    'name' => $name,
                    'email' => $email,
                    'username' => $source === 'ldap' ? $username : null,
                    'status' => 'active',
                    'auth_source' => $source,
                    'external_subject' => $subject,
                ])->save();
                $this->audit->record('user.provisioned', 'access', objectType: 'user', objectId: $user->id, meta: ['source' => $source]);
            } elseif ($user->auth_source !== $source) {
                return null; // never let an IdP take over a local account
            } else {
                $user->forceFill(['external_subject' => $subject, 'name' => $name] + ($email !== null ? ['email' => $email] : []))->save();
            }

            if ($managedRoleKeys !== []) {
                $managed = Role::query()->whereIn('key', $managedRoleKeys)->pluck('id', 'key');
                $wanted = $managed->only($mappedRoleKeys)->values()->all();
                $current = $user->roles()->whereIn('roles.id', $managed->values())->pluck('roles.id')->all();
                $add = array_diff($wanted, $current);
                $remove = array_diff($current, $wanted);
                foreach ($add as $roleId) {
                    $user->roles()->attach($roleId, ['created_at' => now()->format('Y-m-d H:i:s.u')]);
                }
                if ($remove !== []) {
                    $user->roles()->detach($remove);
                }
                if ($add !== [] || $remove !== []) {
                    $this->audit->record('user.roles_synced', 'access', objectType: 'user', objectId: $user->id, meta: ['source' => $source, 'added' => array_values($add), 'removed' => array_values($remove)]);
                    app(AccessCache::class)->bump();
                }
            }

            return $user;
        });
    }
}
