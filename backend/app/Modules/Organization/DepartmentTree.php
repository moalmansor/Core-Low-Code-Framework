<?php

declare(strict_types=1);

namespace App\Modules\Organization;

use App\Modules\Access\AccessCache;
use App\Modules\Organization\Models\Department;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Maintains the department closure table (architecture §10.4) used for
 * "department tree" scopes and for department grants that include descendants.
 */
final class DepartmentTree
{
    public function __construct(private readonly AccessCache $cache) {}

    public function create(Department $department): void
    {
        DB::transaction(function () use ($department): void {
            $department->depth = $department->parent_id === null ? 0 : (int) Department::query()->whereKey($department->parent_id)->value('depth') + 1;
            $department->save();
            $rows = [['ancestor_id' => $department->id, 'descendant_id' => $department->id, 'depth' => 0]];
            if ($department->parent_id !== null) {
                foreach (DB::table('department_closure')->where('descendant_id', $department->parent_id)->get() as $row) {
                    $rows[] = ['ancestor_id' => (int) $row->ancestor_id, 'descendant_id' => $department->id, 'depth' => (int) $row->depth + 1];
                }
            }
            DB::table('department_closure')->insert($rows);
        });
        $this->cache->bump();
    }

    /** Move a department (and its subtree) under a new parent, or to the root. */
    public function move(Department $department, ?Department $newParent): void
    {
        if ($newParent !== null) {
            $isDescendant = DB::table('department_closure')
                ->where('ancestor_id', $department->id)->where('descendant_id', $newParent->id)->exists();
            if ($isDescendant) {
                throw ValidationException::withMessages(['parent' => __('ui.departments.cycle')]);
            }
        }
        DB::transaction(function () use ($department, $newParent): void {
            $subtree = DB::table('department_closure')->where('ancestor_id', $department->id)->pluck('depth', 'descendant_id');
            $subtreeIds = $subtree->keys()->map(static fn ($id): int => (int) $id)->all();
            // Detach the subtree from its old ancestors.
            DB::table('department_closure')
                ->whereIn('descendant_id', $subtreeIds)
                ->whereNotIn('ancestor_id', $subtreeIds)
                ->delete();
            // Attach it under the new parent's ancestors.
            if ($newParent !== null) {
                $ancestors = DB::table('department_closure')->where('descendant_id', $newParent->id)->get();
                $rows = [];
                foreach ($ancestors as $a) {
                    foreach ($subtree as $descendantId => $depth) {
                        $rows[] = ['ancestor_id' => (int) $a->ancestor_id, 'descendant_id' => (int) $descendantId, 'depth' => (int) $a->depth + 1 + (int) $depth];
                    }
                }
                foreach (array_chunk($rows, 500) as $chunk) {
                    DB::table('department_closure')->insert($chunk);
                }
            }
            $department->parent_id = $newParent?->id;
            $department->save();
            $base = $newParent === null ? 0 : $newParent->depth + 1;
            foreach ($subtree as $descendantId => $depth) {
                Department::query()->whereKey((int) $descendantId)->update(['depth' => $base + (int) $depth]);
            }
        });
        $this->cache->bump();
    }

    /** Archive (soft-delete) a department that has no active children or members. */
    public function archive(Department $department): void
    {
        if ($department->children()->exists()) {
            throw ValidationException::withMessages(['department' => __('ui.departments.has_children')]);
        }
        if ($department->members()->exists()) {
            throw ValidationException::withMessages(['department' => __('ui.departments.has_members')]);
        }
        $department->forceFill(['deleted_by' => auth()->id()])->save();
        $department->delete();
        $this->cache->bump();
    }
}
