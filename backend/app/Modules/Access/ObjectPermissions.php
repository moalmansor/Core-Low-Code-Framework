<?php

declare(strict_types=1);

namespace App\Modules\Access;

use App\Modules\Access\Models\Permission;
use App\Modules\Access\Models\PermissionAssignment;
use App\Modules\Access\Models\Role;

/**
 * Auto-registered object permissions (specification §4.11, architecture §21):
 * every form gets `form.{uuid}.{ability}`, every application
 * `app.{uuid}.access`, every menu item `menu.{uuid}.view`. Each new permission
 * receives a Super Admin allow as an ordinary, editable grant; everyone else
 * gets access only when an administrator grants it.
 */
final class ObjectPermissions
{
    /** ability => [en, ar] */
    public const FORM_ABILITIES = [
        'view' => ['View records', 'عرض السجلات'],
        'create' => ['Create records', 'إنشاء السجلات'],
        'edit' => ['Edit records', 'تعديل السجلات'],
        'delete' => ['Delete records', 'حذف السجلات'],
        'restore' => ['Restore records', 'استعادة السجلات'],
        'export' => ['Export records', 'تصدير السجلات'],
        'import' => ['Import records', 'استيراد السجلات'],
        'print' => ['Print records', 'طباعة السجلات'],
        'view_log' => ['View record log', 'عرض سجل السجل'],
    ];

    public function __construct(private readonly AccessCache $cache) {}

    public function registerForm(int $formId, string $uuid): void
    {
        foreach (self::FORM_ABILITIES as $ability => [$en, $ar]) {
            $this->register("form.{$uuid}.{$ability}", 'form', $formId, $ability, 'form', $en, $ar, false);
        }
    }

    public function registerApplication(int $id, string $uuid): void
    {
        $this->register("app.{$uuid}.access", 'application', $id, 'access', 'application', 'Use application', 'استخدام التطبيق', false);
    }

    public function registerMenuItem(int $id, string $uuid): void
    {
        $this->register("menu.{$uuid}.view", 'menu_item', $id, 'view', 'menu', 'See menu item', 'رؤية عنصر القائمة', false);
    }

    /** Removes an object's permissions and their grants (the object itself was deleted). */
    public function forget(string $prefix): void
    {
        $ids = Permission::query()->where('key', 'like', $prefix.'.%')->pluck('id');
        PermissionAssignment::query()->whereIn('permission_id', $ids)->delete();
        Permission::query()->whereIn('id', $ids)->delete();
        $this->cache->bump();
    }

    private function register(string $key, string $scopeType, int $scopeId, string $ability, string $category, string $en, string $ar, bool $dangerous): void
    {
        if (Permission::query()->where('key', $key)->exists()) {
            return;
        }
        $permission = Permission::query()->create([
            'key' => $key, 'scope_type' => $scopeType, 'scope_id' => $scopeId, 'ability' => $ability,
            'category' => $category, 'is_system' => false, 'is_dangerous' => $dangerous,
        ]);
        $permission->setTranslations('label', ['en' => $en, 'ar' => $ar]);
        $superAdmin = Role::query()->where('key', 'super_admin')->value('id');
        if ($superAdmin !== null) {
            PermissionAssignment::query()->create([
                'permission_id' => $permission->id, 'subject_type' => 'role', 'subject_id' => $superAdmin,
                'effect' => 'allow', 'include_descendants' => false,
            ]);
        }
        $this->cache->bump();
    }
}
