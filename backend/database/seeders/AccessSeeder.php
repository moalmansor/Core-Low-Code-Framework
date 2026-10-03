<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Modules\Access\AccessCache;
use App\Modules\Access\Models\Permission;
use App\Modules\Access\Models\PermissionAssignment;
use App\Modules\Access\Models\Role;
use App\Modules\Access\PermissionCatalog;
use Illuminate\Database\Seeder;

/**
 * Core roles, the full system permission catalog, and the seeded role grants
 * (specification §2, architecture §21.1). Grants are ordinary rows that an
 * administrator can see and change in the permission matrix.
 */
final class AccessSeeder extends Seeder
{
    public function run(AccessCache $cache): void
    {
        $roles = [];
        foreach (PermissionCatalog::coreRoles() as $key => $def) {
            $role = Role::query()->where('key', $key)->first();
            if ($role === null) {
                $role = Role::query()->create([
                    'key' => $key, 'is_system' => true, 'audience' => 'internal',
                    'requires_2fa' => $def['admin'], 'is_admin_role' => $def['admin'], 'sort_order' => $def['sort'],
                ]);
                $role->setTranslations('name', ['en' => $def['en'], 'ar' => $def['ar']]);
            }
            $roles[$key] = $role;
        }

        $permissions = [];
        foreach (PermissionCatalog::SYSTEM as $key => [$category, $dangerous, $en, $ar]) {
            $permission = Permission::query()->where('key', $key)->first();
            if ($permission === null) {
                $permission = Permission::query()->create([
                    'key' => $key, 'scope_type' => 'system', 'scope_id' => null,
                    'ability' => substr($key, strlen('system.')), 'category' => $category,
                    'is_system' => true, 'is_dangerous' => $dangerous,
                ]);
                $permission->setTranslations('label', ['en' => $en, 'ar' => $ar]);
            }
            $permissions[$key] = $permission;
        }

        // Grants are seeded only on a fresh install, so re-running the seeder
        // never re-adds a grant an administrator removed.
        if (PermissionAssignment::query()->exists()) {
            return;
        }
        foreach (PermissionCatalog::seededGrants() as $roleKey => $keys) {
            foreach ($keys as $permissionKey) {
                PermissionAssignment::query()->create([
                    'permission_id' => $permissions[$permissionKey]->id,
                    'subject_type' => 'role',
                    'subject_id' => $roles[$roleKey]->id,
                    'effect' => 'allow',
                    'include_descendants' => false,
                ]);
            }
        }
        $cache->bump();
    }
}
