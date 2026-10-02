<?php

declare(strict_types=1);

namespace App\Modules\Access\Http\Controllers;

use App\Modules\Access\AccessGuard;
use App\Modules\Access\Models\PermissionAssignment;
use App\Modules\Access\Models\Role;
use App\Modules\Core\I18n\Translator;
use App\Modules\Identity\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/** Roles in the unified access interface (specification §4.11). */
final class RoleController extends Controller
{
    public function __construct(private readonly AccessGuard $guard) {}

    public function index(): JsonResponse
    {
        Gate::authorize('system.manage_permissions');
        $roles = Role::query()->withCount('users')->orderBy('sort_order')->orderBy('id')->get();

        return response()->json(['data' => $roles->map(fn (Role $r): array => $this->present($r))->values()]);
    }

    public function show(Role $role): JsonResponse
    {
        Gate::authorize('system.manage_permissions');
        $role->loadCount('users');

        return response()->json(['data' => $this->present($role)]);
    }

    public function store(Request $request): JsonResponse
    {
        Gate::authorize('system.manage_permissions');
        $data = $this->validated($request, null);
        $role = $this->guard->guarded(function () use ($data): Role {
            $role = Role::query()->create([
                'key' => $data['key'],
                'is_system' => false,
                'audience' => 'internal',
                'requires_2fa' => $data['requires_2fa'] ?? false,
                'is_admin_role' => $data['is_admin_role'] ?? false,
                'sort_order' => $data['sort_order'] ?? 100,
            ]);
            $role->setTranslations('name', $data['name']);
            $role->setTranslations('description', $data['description'] ?? []);

            return $role;
        });

        return response()->json(['data' => $this->present($role->loadCount('users'))], 201);
    }

    public function update(Request $request, Role $role): JsonResponse
    {
        Gate::authorize('system.manage_permissions');
        $data = $this->validated($request, $role);
        $this->guard->guarded(function () use ($role, $data): void {
            $updates = collect($data)->only(['requires_2fa', 'is_admin_role', 'sort_order'])->all();
            if (! $role->is_system && isset($data['key'])) {
                $updates['key'] = $data['key'];
            }
            $role->fill($updates)->save();
            if (isset($data['name'])) {
                $role->setTranslations('name', $data['name']);
            }
            if (array_key_exists('description', $data)) {
                $role->setTranslations('description', $data['description'] ?? []);
            }
        });

        return response()->json(['data' => $this->present($role->fresh()->loadCount('users'))]);
    }

    public function destroy(Role $role): JsonResponse
    {
        Gate::authorize('system.manage_permissions');
        abort_if($role->is_system, 422, __('ui.access.system_role_protected'));
        $this->guard->guarded(function () use ($role): void {
            PermissionAssignment::query()->where('subject_type', 'role')->where('subject_id', $role->id)->delete();
            $role->users()->detach();
            $role->delete();
        });

        return response()->json(null, 204);
    }

    /** Copy every grant of one role onto another (replacing the target's grants). */
    public function copyPermissions(Request $request, Role $role): JsonResponse
    {
        Gate::authorize('system.manage_permissions');
        $data = $request->validate(['from_role' => ['required', 'uuid', Rule::exists(Role::class, 'uuid')]]);
        $source = Role::query()->where('uuid', $data['from_role'])->firstOrFail();
        abort_if($source->is($role), 422, __('ui.access.copy_same_role'));
        $this->guard->guarded(function () use ($source, $role): void {
            PermissionAssignment::query()->where('subject_type', 'role')->where('subject_id', $role->id)->delete();
            foreach (PermissionAssignment::query()->where('subject_type', 'role')->where('subject_id', $source->id)->get() as $grant) {
                PermissionAssignment::query()->create([
                    'permission_id' => $grant->permission_id, 'subject_type' => 'role', 'subject_id' => $role->id,
                    'effect' => $grant->effect, 'include_descendants' => false, 'valid_until' => $grant->valid_until,
                    'granted_by' => auth()->id(),
                ]);
            }
            app(\App\Modules\Audit\AuditWriter::class)->record('access.permissions_copied', 'access', objectType: 'role', objectId: $role->id, meta: ['from_role' => $source->uuid]);
        });

        return response()->json(['data' => $this->present($role->loadCount('users'))]);
    }

    /** @return array<string, mixed> */
    private function validated(Request $request, ?Role $role): array
    {
        return $request->validate([
            'key' => [$role === null ? 'required' : 'sometimes', 'string', 'max:64', 'regex:/^[a-z][a-z0-9_]{1,63}$/',
                Rule::unique(Role::class, 'key')->where('organization_id', app(\App\Modules\Core\Tenancy\TenantContext::class)->organizationId())->ignore($role?->id)],
            'name' => [$role === null ? 'required' : 'sometimes', 'array'],
            'name.*' => ['nullable', 'string', 'max:255'],
            'name.'.(\App\Modules\Core\Models\Locale::query()->where('is_default', true)->value('code') ?? 'en') => [$role === null ? 'required' : 'sometimes', 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'array'],
            'description.*' => ['nullable', 'string', 'max:2000'],
            'requires_2fa' => ['sometimes', 'boolean'],
            'is_admin_role' => ['sometimes', 'boolean'],
            'sort_order' => ['sometimes', 'integer', 'between:0,100000'],
        ]);
    }

    /** @return array<string, mixed> */
    private function present(Role $role): array
    {
        return [
            'uuid' => $role->uuid,
            'key' => $role->key,
            'name' => $role->translate('name'),
            'names' => $role->translationsFor('name'),
            'description' => $role->translate('description'),
            'descriptions' => $role->translationsFor('description'),
            'is_system' => $role->is_system,
            'requires_2fa' => $role->requires_2fa,
            'is_admin_role' => $role->is_admin_role,
            'sort_order' => $role->sort_order,
            'users_count' => $role->users_count ?? null,
        ];
    }
}
