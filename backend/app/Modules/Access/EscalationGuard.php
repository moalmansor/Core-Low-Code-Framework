<?php

declare(strict_types=1);

namespace App\Modules\Access;

use App\Modules\Access\Models\PermissionAssignment;
use App\Modules\Identity\Models\User;
use App\Modules\Identity\Security\StepUp;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Privilege-escalation safeguards for user administration (specification §4.11,
 * §5): `system.manage_users` lets an administrator manage people, never gain or
 * hand out more power than they hold themselves.
 *
 *  - A user can be managed only by someone who holds every permission that user
 *    holds (no acting on more privileged accounts).
 *  - A role or department can be given only if every permission it allows is
 *    held by the administrator giving it.
 *  - Nobody changes their own roles or department.
 *  - Giving an administrative role, or one that allows a dangerous permission,
 *    needs step-up confirmation.
 */
final class EscalationGuard
{
    public function __construct(
        private readonly AccessResolver $resolver,
        private readonly StepUp $stepUp,
    ) {}

    public function assertCanManage(User $actor, User $target): void
    {
        if ($actor->is($target)) {
            return;
        }
        $missing = array_diff($this->held($target), $this->held($actor));
        abort_if($missing !== [], 403, __('ui.users.target_more_privileged'));
    }

    /**
     * @param  list<int>  $addedRoleIds
     * @param  list<int>  $removedRoleIds
     */
    public function assertCanChangeRoles(Request $request, User $actor, ?User $target, array $addedRoleIds, array $removedRoleIds): void
    {
        if ($addedRoleIds === [] && $removedRoleIds === []) {
            return;
        }
        abort_if($target !== null && $actor->is($target), 403, __('ui.users.cannot_change_own_access'));
        $roleIds = array_merge($addedRoleIds, $removedRoleIds);
        $allowed = $this->allowedBy('role', $roleIds);
        abort_if(array_diff($allowed, $this->held($actor)) !== [], 403, __('ui.users.role_exceeds_own_permissions'));

        $sensitive = DB::table('roles')->whereIn('id', $addedRoleIds)->where('is_admin_role', true)->exists()
            || $this->allowsDangerous('role', $addedRoleIds);
        if ($sensitive) {
            $this->stepUp->require($request, $actor);
        }
    }

    public function assertCanChangeDepartment(User $actor, ?User $target, ?int $newDepartmentId): void
    {
        abort_if($target !== null && $actor->is($target), 403, __('ui.users.cannot_change_own_access'));
        if ($newDepartmentId === null) {
            return;
        }
        $ancestors = DB::table('department_closure')->where('descendant_id', $newDepartmentId)->pluck('ancestor_id')->map(static fn ($id): int => (int) $id)->all();
        $allowed = PermissionAssignment::query()->join('permissions', 'permissions.id', '=', 'permission_assignments.permission_id')
            ->where('subject_type', 'department')->where('effect', 'allow')
            ->where(static fn ($q) => $q->where('subject_id', $newDepartmentId)
                ->orWhere(static fn ($w) => $w->whereIn('subject_id', $ancestors)->where('include_descendants', true)))
            ->pluck('permissions.key')->all();
        abort_if(array_diff($allowed, $this->held($actor)) !== [], 403, __('ui.users.department_exceeds_own_permissions'));
    }

    /** @return list<string> */
    private function held(User $user): array
    {
        return array_keys(array_filter($this->resolver->effective($user)));
    }

    /**
     * @param  list<int>  $ids
     * @return list<string>
     */
    private function allowedBy(string $subjectType, array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        return PermissionAssignment::query()->join('permissions', 'permissions.id', '=', 'permission_assignments.permission_id')
            ->where('subject_type', $subjectType)->whereIn('subject_id', $ids)->where('effect', 'allow')
            ->distinct()->pluck('permissions.key')->all();
    }

    /** @param list<int> $ids */
    private function allowsDangerous(string $subjectType, array $ids): bool
    {
        return $ids !== [] && PermissionAssignment::query()->join('permissions', 'permissions.id', '=', 'permission_assignments.permission_id')
            ->where('subject_type', $subjectType)->whereIn('subject_id', $ids)->where('effect', 'allow')
            ->where('permissions.is_dangerous', true)->exists();
    }
}
