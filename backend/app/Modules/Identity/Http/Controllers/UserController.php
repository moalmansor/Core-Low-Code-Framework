<?php

declare(strict_types=1);

namespace App\Modules\Identity\Http\Controllers;

use App\Modules\Access\AccessGuard;
use App\Modules\Access\EscalationGuard;
use App\Modules\Access\Models\Role;
use App\Modules\Audit\AuditWriter;
use App\Modules\Core\Mail\SettingsSmtpTransport;
use App\Modules\Identity\Models\User;
use App\Modules\Identity\Sessions\SessionRevoker;
use App\Modules\Monitoring\ErrorReporter;
use App\Modules\Organization\Models\Department;
use App\Support\Like;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\Rule;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;

/** User administration (Phase 1 scope: Users & Departments management). */
final class UserController extends Controller
{
    public function __construct(
        private readonly AccessGuard $guard,
        private readonly AuditWriter $audit,
        private readonly EscalationGuard $escalation,
        private readonly ErrorReporter $errors,
    ) {}

    private function actor(): User
    {
        /** @var User */
        return Auth::user();
    }

    public function index(Request $request): JsonResponse
    {
        Gate::authorize('system.manage_users');
        $f = $request->validate([
            'search' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', Rule::in(['pending', 'active', 'suspended', 'disabled'])],
            'role' => ['nullable', 'uuid'],
            'department' => ['nullable', 'uuid'],
            'per_page' => ['nullable', 'integer', 'between:1,100'],
        ]);
        $q = User::query()->with(['roles:id,uuid,key', 'department:id,uuid,code']);
        if (! empty($f['search'])) {
            Like::any($q, ['name', 'email', 'username'], $f['search']);
        }
        if (! empty($f['status'])) {
            $q->where('status', $f['status']);
        }
        if (! empty($f['role'])) {
            $q->whereHas('roles', static fn ($r) => $r->where('uuid', $f['role']));
        }
        if (! empty($f['department'])) {
            $q->whereHas('department', static fn ($d) => $d->where('uuid', $f['department']));
        }
        $page = $q->orderBy('name')->paginate((int) ($f['per_page'] ?? 25));
        $page->getCollection()->transform(fn (User $u): array => $this->present($u));

        return response()->json($page);
    }

    public function show(User $user): JsonResponse
    {
        Gate::authorize('system.manage_users');

        return response()->json(['data' => $this->present($user->load(['roles', 'department', 'manager']))]);
    }

    /** Create a user; local users receive a password-setup link by e-mail. */
    public function store(Request $request): JsonResponse
    {
        Gate::authorize('system.manage_users');
        $data = $this->validated($request, null);
        if (isset($data['department'])) {
            $this->escalation->assertCanChangeDepartment($this->actor(), null, $this->departmentId($data['department']));
        }
        $user = $this->guard->guarded(function () use ($data, $request): User {
            $user = new User;
            $user->forceFill([
                'name' => $data['name'],
                'email' => mb_strtolower($data['email']),
                'username' => $data['username'] ?? null,
                'job_title' => $data['job_title'] ?? null,
                'phone' => $data['phone'] ?? null,
                'department_id' => $this->departmentId($data['department'] ?? null),
                'manager_id' => $this->userId($data['manager'] ?? null),
                'status' => 'active',
                'auth_source' => $data['auth_source'] ?? 'local',
                'attributes' => $data['attributes'] ?? null,
            ])->save();
            $this->syncRoles($request, $user, $data['roles'] ?? [], isNew: true);

            return $user;
        });
        // The account exists even when the link cannot be e-mailed (for example
        // before SMTP is configured); the response says so, and the link can be
        // sent later from the user's actions.
        $link = $user->auth_source === 'local' ? $this->sendPasswordSetupLink($user) : null;

        return response()->json([
            'data' => $this->present($user->load(['roles', 'department'])),
            'meta' => ['password_link' => $link],
        ], 201);
    }

    public function update(Request $request, User $user): JsonResponse
    {
        Gate::authorize('system.manage_users');
        $this->escalation->assertCanManage($this->actor(), $user);
        $data = $this->validated($request, $user);
        if (array_key_exists('department', $data) && $this->departmentId($data['department']) !== $user->department_id) {
            $this->escalation->assertCanChangeDepartment($this->actor(), $user, $this->departmentId($data['department']));
        }
        $this->guard->guarded(function () use ($user, $data, $request): void {
            $updates = collect($data)->only(['name', 'username', 'job_title', 'phone', 'attributes'])->all();
            if (isset($data['email'])) {
                $updates['email'] = mb_strtolower($data['email']);
            }
            if (array_key_exists('department', $data)) {
                $updates['department_id'] = $this->departmentId($data['department']);
            }
            if (array_key_exists('manager', $data)) {
                abort_if($data['manager'] === $user->uuid, 422, __('ui.users.self_manager'));
                $updates['manager_id'] = $this->userId($data['manager']);
            }
            $user->forceFill($updates)->save();
            if (array_key_exists('roles', $data)) {
                $this->syncRoles($request, $user, $data['roles']);
            }
        });

        return response()->json(['data' => $this->present($user->fresh(['roles', 'department']))]);
    }

    public function setStatus(Request $request, User $user, SessionRevoker $sessions): JsonResponse
    {
        Gate::authorize('system.manage_users');
        $this->escalation->assertCanManage($this->actor(), $user);
        $data = $request->validate(['status' => ['required', Rule::in(['active', 'suspended', 'disabled'])]]);
        abort_if($user->is(Auth::user()) && $data['status'] !== 'active', 422, __('ui.users.cannot_suspend_self'));
        $this->guard->guarded(fn () => $user->forceFill(['status' => $data['status']])->save());
        if ($data['status'] !== 'active') {
            $sessions->revokeAllFor($user->id);
        }

        return response()->json(['data' => $this->present($user->fresh(['roles', 'department']))]);
    }

    public function unlock(User $user): JsonResponse
    {
        Gate::authorize('system.manage_users');
        $this->escalation->assertCanManage($this->actor(), $user);
        $user->forceFill(['failed_login_count' => 0, 'locked_until' => null])->saveQuietly();
        $this->audit->record('user.unlocked', 'access', objectType: 'user', objectId: $user->id);

        return response()->json(null, 204);
    }

    public function resetTwoFactor(User $user, SessionRevoker $sessions): JsonResponse
    {
        Gate::authorize('system.manage_users');
        $this->escalation->assertCanManage($this->actor(), $user);
        abort_if($user->is(Auth::user()), 422, __('ui.users.reset_own_2fa'));
        $user->forceFill(['two_factor_secret' => null, 'two_factor_recovery_codes' => null, 'two_factor_confirmed_at' => null])->saveQuietly();
        $sessions->revokeAllFor($user->id);
        $this->audit->record('user.2fa_reset', 'security', objectType: 'user', objectId: $user->id);

        return response()->json(null, 204);
    }

    public function sendPasswordLink(User $user): JsonResponse
    {
        Gate::authorize('system.manage_users');
        $this->escalation->assertCanManage($this->actor(), $user);
        abort_if($user->auth_source !== 'local', 422, __('ui.auth.external_account'));
        $link = $this->sendPasswordSetupLink($user);
        abort_if($link === 'mail_not_configured', 422, __('ui.users.mail_not_configured'));
        abort_if($link === 'throttled', 429, __('ui.users.link_throttled'));
        abort_if($link === 'failed', 422, __('ui.users.mail_failed'));
        $this->audit->record('user.password_link_sent', 'access', objectType: 'user', objectId: $user->id);

        return response()->json(null, 204);
    }

    /**
     * E-mails a password-setup link.
     *
     * @return 'sent'|'mail_not_configured'|'throttled'|'failed'
     */
    private function sendPasswordSetupLink(User $user): string
    {
        if (config('mail.default') === 'lcf' && app(SettingsSmtpTransport::class)->config() === null) {
            return 'mail_not_configured';
        }
        try {
            $status = Password::broker()->sendResetLink(['email' => $user->email]);
        } catch (TransportExceptionInterface $e) {
            $this->errors->report($e);

            return 'failed';
        }

        return match ($status) {
            Password::RESET_LINK_SENT => 'sent',
            Password::RESET_THROTTLED => 'throttled',
            default => 'failed',
        };
    }

    public function sessions(User $user): JsonResponse
    {
        Gate::authorize('system.manage_users');
        $rows = DB::table('sessions')->where('user_id', $user->id)->orderByDesc('last_activity')
            ->get(['ip_address', 'user_agent', 'last_activity', 'created_at', 'absolute_expires_at']);

        return response()->json(['data' => $rows->map(static fn ($s): array => [
            'ip_address' => $s->ip_address, 'user_agent' => $s->user_agent,
            'last_activity' => date(DATE_ATOM, (int) $s->last_activity), 'created_at' => $s->created_at,
        ])->values()]);
    }

    public function revokeSessions(User $user, SessionRevoker $sessions): JsonResponse
    {
        Gate::authorize('system.manage_users');
        $this->escalation->assertCanManage($this->actor(), $user);
        $count = $sessions->revokeAllFor($user->id);
        $this->audit->record('user.sessions_revoked', 'auth', objectType: 'user', objectId: $user->id, meta: ['count' => $count]);

        return response()->json(['data' => ['revoked' => $count]]);
    }

    public function destroy(User $user, SessionRevoker $sessions): JsonResponse
    {
        Gate::authorize('system.manage_users');
        $this->escalation->assertCanManage($this->actor(), $user);
        abort_if($user->is(Auth::user()), 422, __('ui.users.cannot_delete_self'));
        $this->guard->guarded(function () use ($user): void {
            $user->forceFill(['status' => 'disabled', 'deleted_by' => Auth::id()])->save();
            $user->delete();
        });
        $sessions->revokeAllFor($user->id);

        return response()->json(null, 204);
    }

    /** @param list<string> $roleUuids */
    private function syncRoles(Request $request, User $user, array $roleUuids, bool $isNew = false): void
    {
        $ids = Role::query()->whereIn('uuid', $roleUuids)->pluck('id')->map(static fn ($id): int => (int) $id)->all();
        $current = $user->roles()->pluck('roles.id')->map(static fn ($id): int => (int) $id)->all();
        $this->escalation->assertCanChangeRoles($request, $this->actor(), $isNew ? null : $user, array_values(array_diff($ids, $current)), array_values(array_diff($current, $ids)));
        $now = now()->format('Y-m-d H:i:s.u');
        foreach (array_diff($ids, $current) as $id) {
            $user->roles()->attach($id, ['assigned_by' => Auth::id(), 'created_at' => $now]);
        }
        $removed = array_diff($current, $ids);
        if ($removed !== []) {
            $user->roles()->detach($removed);
        }
        if (array_diff($ids, $current) !== [] || $removed !== []) {
            $this->audit->record('user.roles_changed', 'access', [[
                'field_key' => 'roles',
                'old' => Role::query()->whereIn('id', $current)->pluck('key')->all(),
                'new' => Role::query()->whereIn('id', $ids)->pluck('key')->all(),
            ]], 'user', $user->id);
        }
    }

    private function departmentId(?string $uuid): ?int
    {
        return $uuid === null ? null : (int) Department::query()->where('uuid', $uuid)->value('id');
    }

    private function userId(?string $uuid): ?int
    {
        return $uuid === null ? null : (int) User::query()->where('uuid', $uuid)->value('id');
    }

    /** @return array<string, mixed> */
    private function validated(Request $request, ?User $user): array
    {
        $required = $user === null ? 'required' : 'sometimes';

        return $request->validate([
            'name' => [$required, 'string', 'max:255'],
            'email' => [$required, 'email:rfc', 'max:255', Rule::unique(User::class, 'email')->ignore($user?->id)],
            'username' => ['sometimes', 'nullable', 'string', 'max:128', 'regex:/^[A-Za-z0-9._@-]+$/', Rule::unique(User::class, 'username')->ignore($user?->id)],
            'job_title' => ['sometimes', 'nullable', 'string', 'max:255'],
            'phone' => ['sometimes', 'nullable', 'string', 'max:32', 'regex:/^[+0-9 ()-]{3,32}$/'],
            'department' => ['sometimes', 'nullable', 'uuid', Rule::exists(Department::class, 'uuid')->whereNull('deleted_at')],
            'manager' => ['sometimes', 'nullable', 'uuid', Rule::exists(User::class, 'uuid')->whereNull('deleted_at')],
            'auth_source' => [$user === null ? 'sometimes' : 'prohibited', Rule::in(['local', 'ldap', 'oidc'])],
            'roles' => ['sometimes', 'array', 'max:50'],
            'roles.*' => ['uuid', Rule::exists(Role::class, 'uuid')],
            'attributes' => ['sometimes', 'nullable', 'array', 'max:50'],
            'attributes.*' => ['nullable', 'max:1000'],
        ]);
    }

    /** @return array<string, mixed> */
    private function present(User $u): array
    {
        return [
            'uuid' => $u->uuid,
            'name' => $u->name,
            'email' => $u->email,
            'username' => $u->username,
            'job_title' => $u->job_title,
            'phone' => $u->phone,
            'status' => $u->status,
            'auth_source' => $u->auth_source,
            'locked' => $u->isLocked(),
            'two_factor_enabled' => $u->hasEnabledTwoFactorAuthentication(),
            'last_login_at' => $u->last_login_at?->toIso8601String(),
            'department' => $u->relationLoaded('department') && $u->department !== null ? ['uuid' => $u->department->uuid, 'code' => $u->department->code, 'name' => $u->department->translate('name')] : null,
            'manager' => $u->relationLoaded('manager') && $u->manager !== null ? ['uuid' => $u->manager->uuid, 'name' => $u->manager->name] : null,
            'roles' => $u->relationLoaded('roles') ? $u->roles->map(static fn (Role $r): array => ['uuid' => $r->uuid, 'key' => $r->key, 'name' => $r->translate('name')])->values() : [],
            'attributes' => $u->getAttribute('attributes'),
        ];
    }
}
