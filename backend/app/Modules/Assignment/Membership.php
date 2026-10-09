<?php

declare(strict_types=1);

namespace App\Modules\Assignment;

use App\Modules\Identity\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Who belongs to an assignee or approver subject (user, role, department):
 * active, non-deleted users; role membership honours its validity window;
 * department membership is direct (sub-departments are separate subjects).
 */
final class Membership
{
    /** @return list<int> */
    public function userIds(string $type, int $id): array
    {
        $now = Carbon::now('UTC')->format('Y-m-d H:i:s.u');
        $q = DB::table('users')->where('status', 'active')->whereNull('deleted_at');
        match ($type) {
            'user' => $q->where('id', $id),
            'role' => $q->whereIn('id', DB::table('user_roles')->where('role_id', $id)
                ->where(static fn ($w) => $w->whereNull('valid_from')->orWhere('valid_from', '<=', $now))
                ->where(static fn ($w) => $w->whereNull('valid_until')->orWhere('valid_until', '>', $now))
                ->select('user_id')),
            'department' => $q->where('department_id', $id),
            default => $q->whereRaw('1 = 0'),
        };

        return $q->orderBy('id')->pluck('id')->map(static fn ($v) => (int) $v)->all();
    }

    /**
     * The subjects a user acts as: themselves, their active roles and their
     * department.
     *
     * @return array{user: list<int>, role: list<int>, department: list<int>}
     */
    public function subjectsOf(User $user): array
    {
        return [
            'user' => [$user->id],
            'role' => $user->activeRoles()->pluck('roles.id')->map(static fn ($v) => (int) $v)->all(),
            'department' => $user->department_id === null ? [] : [(int) $user->department_id],
        ];
    }

    public function matches(User $user, string $type, int $id): bool
    {
        return in_array($id, $this->subjectsOf($user)[$type] ?? [], true);
    }

    /** Resolves a `{type, uuid}` subject to its id. */
    public function idOf(string $type, ?string $uuid): ?int
    {
        $table = match ($type) {
            'user' => 'users', 'role' => 'roles', 'department' => 'departments', default => null,
        };
        if ($table === null || $uuid === null) {
            return null;
        }
        $id = DB::table($table)->where('uuid', $uuid)->value('id');

        return $id === null ? null : (int) $id;
    }

    public function uuidOf(string $type, int $id): ?string
    {
        $table = match ($type) {
            'user' => 'users', 'role' => 'roles', 'department' => 'departments', default => null,
        };
        $uuid = $table === null ? null : DB::table($table)->where('id', $id)->value('uuid');

        return $uuid === null ? null : strtolower((string) $uuid);
    }
}
