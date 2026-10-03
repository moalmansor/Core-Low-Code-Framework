<?php

declare(strict_types=1);

namespace App\Modules\Access;

use App\Modules\Access\Models\Permission;
use App\Modules\Access\Models\PermissionAssignment;
use App\Modules\Identity\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Permission resolution (specification §4.11, architecture §16.2, ADR-0009).
 *
 * Tiers, least to most specific: department → role → specific user.
 *  1. Within a tier, deny beats allow.
 *  2. A more specific tier overrides a less specific tier, including its deny.
 *  3. A hard deny overrides every tier and cannot be overridden.
 *  4. No applicable grant → denied.
 *
 * Effective permissions are computed once per user and access epoch, then cached.
 */
final class AccessResolver
{
    public const TIERS = ['department', 'role', 'user'];

    /** @var array<int, array<string, bool>> per-request memo */
    private array $memo = [];

    public function __construct(private readonly AccessCache $cache) {}

    public function allows(User $user, string $permissionKey): bool
    {
        return $this->effective($user)[$permissionKey] ?? false;
    }

    /** @return array<string, bool> permission key => granted */
    public function effective(User $user): array
    {
        if ($user->status !== 'active' || $user->trashed()) {
            return [];
        }

        return $this->memo[$user->id] ??= $this->cache->remember($user->id, fn (): array => $this->compute($user));
    }

    public function forget(?int $userId = null): void
    {
        if ($userId === null) {
            $this->memo = [];

            return;
        }
        unset($this->memo[$userId]);
    }

    /** Fresh computation that bypasses every cache (used by the lockout guard). */
    public function allowsFresh(User $user, string $permissionKey): bool
    {
        if ($user->status !== 'active' || $user->trashed()) {
            return false;
        }

        return $this->compute($user, [$permissionKey])[$permissionKey] ?? false;
    }

    /**
     * Every grant that applies to the user for a permission, with the tier walk
     * and the outcome (the "explain access" tool, architecture §16.7).
     *
     * @return array{permission: string, granted: bool, decided_by: string, grants: list<array<string, mixed>>, steps: list<array<string, mixed>>}
     */
    public function explain(User $user, string $permissionKey): array
    {
        $grants = $this->applicableGrants($user, [$permissionKey])[$permissionKey] ?? [];
        [$granted, $steps, $decidedBy] = $this->decide($grants);
        if ($user->status !== 'active' || $user->trashed()) {
            $granted = false;
            $decidedBy = 'inactive_user';
        }

        return ['permission' => $permissionKey, 'granted' => $granted, 'decided_by' => $decidedBy, 'grants' => $grants, 'steps' => $steps];
    }

    /**
     * @param  list<string>|null  $onlyKeys
     * @return array<string, bool>
     */
    private function compute(User $user, ?array $onlyKeys = null): array
    {
        $result = [];
        $keys = $onlyKeys ?? Permission::query()->pluck('key')->all();
        $grants = $this->applicableGrants($user, $keys);
        foreach ($keys as $key) {
            [$granted] = $this->decide($grants[$key] ?? []);
            $result[$key] = $granted;
        }

        return $result;
    }

    /**
     * The tier walk of §16.2 over one permission's applicable grants.
     *
     * @param  list<array{tier: string, effect: string}>  $grants
     * @return array{0: bool, 1: list<array<string, mixed>>, 2: string}
     */
    public function decide(array $grants): array
    {
        $value = false;
        $decidedBy = 'default_deny';
        $steps = [];
        foreach (self::TIERS as $tier) {
            $inTier = array_filter($grants, static fn (array $g): bool => $g['tier'] === $tier && $g['effect'] !== 'hard_deny');
            if ($inTier === []) {
                continue;
            }
            $effects = array_column($inTier, 'effect');
            if (in_array('allow', $effects, true)) {
                $value = true;
                $decidedBy = $tier.'_allow';
            }
            if (in_array('deny', $effects, true)) {
                $value = false;
                $decidedBy = $tier.'_deny';
            }
            $steps[] = ['tier' => $tier, 'effects' => array_values(array_unique($effects)), 'value_after' => $value];
        }
        $hard = array_filter($grants, static fn (array $g): bool => $g['effect'] === 'hard_deny');
        if ($hard !== []) {
            $value = false;
            $decidedBy = 'hard_deny';
            $steps[] = ['tier' => 'hard_deny', 'effects' => ['hard_deny'], 'value_after' => false];
        }

        return [$value, $steps, $decidedBy];
    }

    /**
     * Grants on the given permissions whose subject matches the user: their
     * active roles, their department (and ancestors whose grant includes
     * descendants), and the user themself. Expired grants are ignored.
     *
     * @param  list<string>  $keys
     * @return array<string, list<array{tier: string, effect: string, subject_type: string, subject_id: int, include_descendants: bool, assignment_id: int}>>
     */
    private function applicableGrants(User $user, array $keys): array
    {
        if ($keys === []) {
            return [];
        }
        $roleIds = $user->activeRoles()->pluck('roles.id')->all();
        $deptIds = [];
        $ownDept = $user->department_id === null ? null : (int) $user->department_id;
        if ($ownDept !== null) {
            $deptIds = DB::table('department_closure')->where('descendant_id', $ownDept)->pluck('ancestor_id')->map(static fn ($id): int => (int) $id)->all();
        }
        $now = now()->format('Y-m-d H:i:s.u');
        $rows = PermissionAssignment::query()
            ->join('permissions', 'permissions.id', '=', 'permission_assignments.permission_id')
            ->whereIn('permissions.key', $keys)
            ->where(static fn ($q) => $q->whereNull('permission_assignments.valid_until')->orWhere('permission_assignments.valid_until', '>', $now))
            ->where(static function ($q) use ($user, $roleIds, $deptIds): void {
                $q->where(static fn ($w) => $w->where('subject_type', 'user')->where('subject_id', $user->id));
                if ($roleIds !== []) {
                    $q->orWhere(static fn ($w) => $w->where('subject_type', 'role')->whereIn('subject_id', $roleIds));
                }
                if ($deptIds !== []) {
                    $q->orWhere(static fn ($w) => $w->where('subject_type', 'department')->whereIn('subject_id', $deptIds));
                }
            })
            ->get(['permissions.key as permission_key', 'permission_assignments.id', 'subject_type', 'subject_id', 'effect', 'include_descendants']);

        $out = [];
        foreach ($rows as $row) {
            $subjectId = (int) $row->subject_id;
            if ($row->subject_type === 'department' && $subjectId !== $ownDept && ! $row->include_descendants) {
                continue; // an ancestor's grant reaches sub-departments only when it says so
            }
            $out[(string) $row->getAttribute('permission_key')][] = [
                'tier' => (string) $row->subject_type,
                'effect' => (string) $row->effect,
                'subject_type' => (string) $row->subject_type,
                'subject_id' => $subjectId,
                'include_descendants' => (bool) $row->include_descendants,
                'assignment_id' => (int) $row->id,
            ];
        }

        return $out;
    }
}
