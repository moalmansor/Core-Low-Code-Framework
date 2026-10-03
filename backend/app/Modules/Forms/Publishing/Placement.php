<?php

declare(strict_types=1);

namespace App\Modules\Forms\Publishing;

use App\Modules\Access\AccessCache;
use App\Modules\Access\Models\PermissionAssignment;
use App\Modules\Access\ObjectPermissions;
use App\Modules\Audit\AuditWriter;
use App\Modules\Forms\Models\Application;
use App\Modules\Forms\Models\Form;
use App\Modules\Forms\Models\MenuItem;
use Illuminate\Support\Facades\DB;

/**
 * Publish choices (specification §4.13): the sidebar entry (application,
 * parent, order, icon, label) and the roles, users and departments allowed to
 * use the form. "Allowed" grants view, create and edit on the form, access to
 * its application and visibility of its menu entry (ADR-0028); finer form
 * permissions are set in the permission matrix.
 */
final class Placement
{
    public const ALLOWED_ABILITIES = ['view', 'create', 'edit'];

    public function __construct(private readonly ObjectPermissions $permissions, private readonly AccessCache $cache, private readonly AuditWriter $audit) {}

    /** @param  array{menu?: array<string, mixed>|null, allowed?: array<string, list<string>>|null}  $placement */
    public function apply(Form $form, array $placement, int $actorId): void
    {
        $menu = $placement['menu'] ?? null;
        if (is_array($menu)) {
            $app = Application::query()->where('uuid', $menu['application'])->firstOrFail();
            $item = MenuItem::query()->where('target_type', 'form')->where('target_id', $form->id)->where('application_id', $app->id)->first()
                ?? new MenuItem(['application_id' => $app->id, 'type' => $form->kind === 'collection' ? 'collection' : 'form', 'target_type' => 'form', 'target_id' => $form->id]);
            $item->fill([
                'parent_id' => isset($menu['parent']) ? MenuItem::query()->where('uuid', $menu['parent'])->where('application_id', $app->id)->value('id') : null,
                'sort_order' => $menu['sort_order'] ?? (int) MenuItem::query()->where('application_id', $app->id)->max('sort_order') + 1,
                'icon' => $menu['icon'] ?? $form->icon,
                'is_active' => true,
            ])->save();
            $item->setTranslations('label', $menu['label']);
            $this->permissions->registerMenuItem($item->id, $item->uuid);
        }
        $allowed = $placement['allowed'] ?? null;
        if (is_array($allowed)) {
            $subjects = [
                'role' => DB::table('roles')->whereIn('uuid', $allowed['roles'] ?? [])->pluck('id')->all(),
                'user' => DB::table('users')->whereIn('uuid', $allowed['users'] ?? [])->pluck('id')->all(),
                'department' => DB::table('departments')->whereIn('uuid', $allowed['departments'] ?? [])->pluck('id')->all(),
            ];
            $changes = [];
            $keys = array_map(static fn (string $a) => "form.{$form->uuid}.{$a}", self::ALLOWED_ABILITIES);
            // Users allowed to use the form also see its application and its menu entry.
            $appUuid = DB::table('applications')->where('id', $form->application_id)->value('uuid');
            $keys[] = "app.{$appUuid}.access";
            foreach (MenuItem::query()->where('target_type', 'form')->where('target_id', $form->id)->pluck('uuid') as $menuUuid) {
                $keys[] = "menu.{$menuUuid}.view";
            }
            foreach ($keys as $key) {
                $pid = DB::table('permissions')->where('key', $key)->value('id');
                if ($pid === null) {
                    continue;
                }
                foreach ($subjects as $type => $ids) {
                    foreach ($ids as $id) {
                        $exists = PermissionAssignment::query()->where(['permission_id' => $pid, 'subject_type' => $type, 'subject_id' => $id])->exists();
                        if (! $exists) {
                            PermissionAssignment::query()->create(['permission_id' => $pid, 'subject_type' => $type, 'subject_id' => $id, 'effect' => 'allow', 'include_descendants' => $type === 'department', 'granted_by' => $actorId]);
                            $changes[] = ['field_key' => $key, 'old' => null, 'new' => "allow {$type}:{$id}"];
                        }
                    }
                }
            }
            if ($changes !== []) {
                $this->audit->record('access.grants_changed', 'access', $changes, 'form', $form->id, null, $actorId);
                $this->cache->bump();
            }
        }
    }
}
