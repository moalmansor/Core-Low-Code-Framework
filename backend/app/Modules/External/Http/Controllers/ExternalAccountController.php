<?php

declare(strict_types=1);

namespace App\Modules\External\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Access\Models\Role;
use App\Modules\Audit\AuditWriter;
use App\Modules\Core\I18n\Translator;
use App\Modules\Core\Settings\SettingsService;
use App\Modules\External\ExternalAccounts;
use App\Modules\External\Models\ExternalUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * External accounts, external roles and the portal's registration settings
 * (specification §4.32, ADR-0058); everything needs "Manage external access".
 */
final class ExternalAccountController extends Controller
{
    public function __construct(
        private readonly ExternalAccounts $accounts,
        private readonly SettingsService $settings,
        private readonly AuditWriter $audit,
        private readonly Translator $translator,
    ) {}

    public function index(Request $request): JsonResponse
    {
        Gate::authorize('system.manage_external_access');
        $data = $request->validate(['status' => ['sometimes', 'nullable', Rule::in(ExternalUser::STATUSES)], 'q' => ['sometimes', 'nullable', 'string', 'max:255'], 'page' => ['sometimes', 'integer', 'min:1']]);
        $q = ExternalUser::query()->whereNull('deleted_at')
            ->when($data['status'] ?? null, fn ($q, $s) => $q->where('status', $s))
            ->when(isset($data['q']) && $data['q'] !== '', fn ($q) => $q->where(fn ($w) => $w->where('email', 'like', '%'.addcslashes(mb_strtolower((string) $data['q']), '%_\\').'%')->orWhere('name', 'like', '%'.addcslashes((string) $data['q'], '%_\\').'%')));
        $total = (clone $q)->count();
        $page = (int) ($data['page'] ?? 1);
        $roles = $this->roleNames();
        $rows = $q->orderByDesc('id')->forPage($page, 25)->get()->map(fn (ExternalUser $u) => [
            'uuid' => strtolower($u->uuid), 'name' => $u->name, 'email' => $u->email, 'status' => $u->status,
            'role' => strtolower((string) DB::table('roles')->where('id', $u->role_id)->value('uuid')), 'role_name' => $roles[$u->role_id] ?? null,
            'created_at' => $u->created_at?->toIso8601ZuluString(), 'last_login_at' => $u->last_login_at?->toIso8601ZuluString(),
            'verified' => $u->email_verified_at !== null,
        ])->values()->all();

        return response()->json(['data' => $rows, 'meta' => ['total' => $total, 'page' => $page, 'per_page' => 25]]);
    }

    public function decide(Request $request, string $account): JsonResponse
    {
        Gate::authorize('system.manage_external_access');
        $data = $request->validate(['decision' => ['required', Rule::in(['approve', 'reject', 'suspend', 'reactivate'])]]);
        $this->accounts->decide($this->find($account), $data['decision'], (int) Auth::id());

        return response()->json(['data' => ['status' => $this->find($account)->status]]);
    }

    public function changeRole(Request $request, string $account): JsonResponse
    {
        Gate::authorize('system.manage_external_access');
        $data = $request->validate(['role' => ['required', 'uuid']]);
        $user = $this->find($account);
        $role = Role::query()->where('uuid', strtolower($data['role']))->where('audience', 'external')->first();
        if ($role === null) {
            throw ValidationException::withMessages(['role' => __('external.role_unknown')]);
        }
        $old = $user->role_id;
        $user->forceFill(['role_id' => $role->id])->save();
        $this->audit->record('external.account_role_changed', 'access', [['field_key' => 'role', 'old' => $old, 'new' => $role->id]], 'external_user', $user->id);

        return response()->json(null, 204);
    }

    /** Deletes an account: it is anonymized and can no longer sign in; its records stay. */
    public function destroy(string $account): JsonResponse
    {
        Gate::authorize('system.manage_external_access');
        $user = $this->find($account);
        DB::transaction(function () use ($user): void {
            DB::table('sessions')->where('external_user_id', $user->id)->delete();
            $user->forceFill([
                'email' => 'deleted-'.strtolower($user->uuid).'@invalid', 'name' => __('external.deleted_account'), 'password' => Str::random(64),
                'status' => 'suspended', 'deleted_at' => Carbon::now('UTC'), 'deleted_by' => Auth::id(), 'anonymized_at' => Carbon::now('UTC'),
            ])->save();
            $this->audit->record('external.account_deleted', 'access', null, 'external_user', $user->id);
        });

        return response()->json(null, 204);
    }

    /** Registration settings and the external roles. */
    public function settings(): JsonResponse
    {
        Gate::authorize('system.manage_external_access');

        return response()->json(['data' => [
            'registration_open' => (bool) $this->settings->get('external', 'registration_open'),
            'registration_role' => $this->settings->get('external', 'registration_role'),
            'registration_requires_approval' => (bool) $this->settings->get('external', 'registration_requires_approval'),
            'portal_url' => url('/x/portal'),
        ]]);
    }

    public function updateSettings(Request $request): JsonResponse
    {
        Gate::authorize('system.manage_external_access');
        $data = $request->validate([
            'registration_open' => ['required', 'boolean'], 'registration_role' => ['present', 'nullable', 'uuid'], 'registration_requires_approval' => ['required', 'boolean'],
        ]);
        $role = $data['registration_role'] === null ? null : Role::query()->where('uuid', strtolower((string) $data['registration_role']))->where('audience', 'external')->first();
        if ($data['registration_role'] !== null && $role === null) {
            throw ValidationException::withMessages(['registration_role' => __('external.role_unknown')]);
        }
        if ($data['registration_open'] && $role === null) {
            throw ValidationException::withMessages(['registration_role' => __('external.registration_needs_role')]);
        }
        $this->settings->update('external', ['registration_open' => $data['registration_open'], 'registration_role' => $role === null ? null : strtolower($role->uuid), 'registration_requires_approval' => $data['registration_requires_approval']]);

        return $this->settings();
    }

    public function roles(): JsonResponse
    {
        Gate::authorize('system.manage_external_access');
        $roles = Role::query()->where('audience', 'external')->orderBy('sort_order')->orderBy('id')->get();
        $counts = DB::table('external_users')->whereNull('deleted_at')->whereIn('role_id', $roles->pluck('id'))->groupBy('role_id')->selectRaw('role_id, count(*) as n')->pluck('n', 'role_id');

        return response()->json(['data' => $roles->map(fn (Role $r) => [
            'uuid' => strtolower((string) $r->uuid), 'key' => $r->key, 'name' => (object) $r->translationsFor('name'),
            'label' => $this->translator->labelOf($r->translationsFor('name'), Translator::humanize((string) $r->key)), 'accounts' => (int) ($counts[$r->id] ?? 0),
        ])->values()->all()]);
    }

    public function storeRole(Request $request): JsonResponse
    {
        Gate::authorize('system.manage_external_access');
        $data = $request->validate(['key' => ['required', 'string', 'regex:/^[a-z][a-z0-9_]{1,47}$/', Rule::unique('roles', 'key')], 'name' => ['required', 'array'], 'name.*' => ['nullable', 'string', 'max:255']]);
        $names = array_filter((array) $data['name'], static fn ($v) => is_string($v) && trim($v) !== '');
        if ($names === []) {
            throw ValidationException::withMessages(['name' => __('external.role_name_required')]);
        }
        $role = DB::transaction(function () use ($data, $names): Role {
            $role = Role::query()->create(['key' => $data['key'], 'is_system' => false, 'audience' => 'external', 'requires_2fa' => false, 'is_admin_role' => false, 'sort_order' => 100]);
            $role->setTranslations('name', $names);

            return $role;
        });
        $this->audit->record('external.role_created', 'access', null, 'role', $role->id, ['key' => $role->key]);

        return response()->json(['data' => ['uuid' => strtolower((string) $role->uuid)]], 201);
    }

    public function updateRole(Request $request, string $role): JsonResponse
    {
        Gate::authorize('system.manage_external_access');
        $r = $this->role($role);
        $data = $request->validate(['name' => ['required', 'array'], 'name.*' => ['nullable', 'string', 'max:255']]);
        $names = array_filter((array) $data['name'], static fn ($v) => is_string($v) && trim($v) !== '');
        if ($names === []) {
            throw ValidationException::withMessages(['name' => __('external.role_name_required')]);
        }
        $r->setTranslations('name', $names);
        $this->audit->record('external.role_updated', 'access', null, 'role', $r->id, ['key' => $r->key]);

        return response()->json(null, 204);
    }

    public function destroyRole(string $role): JsonResponse
    {
        Gate::authorize('system.manage_external_access');
        $r = $this->role($role);
        if (DB::table('external_users')->where('role_id', $r->id)->whereNull('deleted_at')->exists()
            || DB::table('external_forms')->whereNotNull('role_ids')->pluck('role_ids')->contains(fn ($ids) => in_array($r->id, (array) json_decode((string) $ids, true), true))
            || $this->settings->get('external', 'registration_role') === strtolower((string) $r->uuid)) {
            throw ValidationException::withMessages(['role' => __('external.role_in_use')]);
        }
        $this->audit->record('external.role_deleted', 'access', null, 'role', $r->id, ['key' => $r->key]);
        $r->delete();

        return response()->json(null, 204);
    }

    /** @return array<int, string> */
    private function roleNames(): array
    {
        $out = [];
        foreach (Role::query()->where('audience', 'external')->get() as $r) {
            $out[$r->id] = $this->translator->labelOf($r->translationsFor('name'), Translator::humanize((string) $r->key));
        }

        return $out;
    }

    private function role(string $uuid): Role
    {
        $r = Str::isUuid($uuid) ? Role::query()->where('uuid', strtolower($uuid))->where('audience', 'external')->first() : null;
        abort_if($r === null, 404);

        return $r;
    }

    private function find(string $uuid): ExternalUser
    {
        $u = Str::isUuid($uuid) ? ExternalUser::query()->where('uuid', strtolower($uuid))->whereNull('deleted_at')->first() : null;
        abort_if($u === null, 404);

        return $u;
    }
}
