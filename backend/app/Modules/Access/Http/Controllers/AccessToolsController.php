<?php

declare(strict_types=1);

namespace App\Modules\Access\Http\Controllers;

use App\Modules\Access\AccessGuard;
use App\Modules\Access\AccessResolver;
use App\Modules\Access\Models\Permission;
use App\Modules\Access\Models\PermissionAssignment;
use App\Modules\Audit\AuditWriter;
use App\Modules\Core\I18n\Translator;
use App\Modules\Identity\Models\User;
use App\Modules\Identity\Security\StepUp;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/** "View as user", explain access, and permission-set export/import (§4.11). */
final class AccessToolsController extends Controller
{
    public function __construct(
        private readonly AccessResolver $resolver,
        private readonly Translator $translator,
    ) {}

    public function viewAs(User $user): JsonResponse
    {
        Gate::authorize('system.manage_permissions');
        $permissions = Permission::query()->where('scope_type', 'system')->orderBy('category')->orderBy('key')->get();
        $labels = $this->translator->many('permission', $permissions->pluck('id')->all(), ['label']);
        $rows = $permissions->map(function (Permission $p) use ($user, $labels): array {
            $explain = $this->resolver->explain($user, $p->key);

            return [
                'key' => $p->key,
                'category' => $p->category,
                'label' => $labels[$p->id]['label'] ?? Translator::humanize((string) $p->key),
                'granted' => $explain['granted'],
                'decided_by' => $explain['decided_by'],
            ];
        });

        return response()->json(['data' => [
            'user' => ['uuid' => $user->uuid, 'name' => $user->name, 'email' => $user->email, 'roles' => $user->roleKeys()],
            'permissions' => $rows->values(),
        ]]);
    }

    public function explain(Request $request): JsonResponse
    {
        Gate::authorize('system.manage_permissions');
        $data = $request->validate([
            'user' => ['required', 'uuid', Rule::exists(User::class, 'uuid')],
            'permission' => ['required', 'string', Rule::exists(Permission::class, 'key')],
        ]);
        $user = User::query()->where('uuid', $data['user'])->firstOrFail();

        return response()->json(['data' => $this->resolver->explain($user, $data['permission'])]);
    }

    public function export(Request $request): JsonResponse
    {
        Gate::authorize('system.manage_permissions');
        $data = $request->validate([
            'subject_type' => ['required', Rule::in(['role', 'user', 'department'])],
            'subject' => ['required', 'uuid'],
        ]);
        $subjectId = SubjectResolver::id($data['subject_type'], $data['subject']);
        $grants = PermissionAssignment::query()->with('permission')
            ->where('subject_type', $data['subject_type'])->where('subject_id', $subjectId)->get()
            ->map(static fn (PermissionAssignment $g): array => [
                'permission' => $g->permission->key, 'effect' => $g->effect, 'include_descendants' => $g->include_descendants,
            ])->values();
        app(AuditWriter::class)->record('access.permissions_exported', 'export', objectType: $data['subject_type'], objectId: $subjectId);

        return response()->json(['data' => ['format' => 'lcf.permission-set/v1', 'grants' => $grants]]);
    }

    /** Replace a subject's grants with an exported set. */
    public function import(Request $request, AccessGuard $guard, StepUp $stepUp): JsonResponse
    {
        Gate::authorize('system.manage_permissions');
        $data = $request->validate([
            'subject_type' => ['required', Rule::in(['role', 'user', 'department'])],
            'subject' => ['required', 'uuid'],
            'set.format' => ['required', Rule::in(['lcf.permission-set/v1'])],
            'set.grants' => ['present', 'array', 'max:2000'],
            'set.grants.*.permission' => ['required', 'string', Rule::exists(Permission::class, 'key')],
            'set.grants.*.effect' => ['required', Rule::in(['allow', 'deny', 'hard_deny'])],
            'set.grants.*.include_descendants' => ['sometimes', 'boolean'],
            'confirmation_code' => ['sometimes', 'nullable', 'string', 'max:64'],
        ]);
        $subjectId = SubjectResolver::id($data['subject_type'], $data['subject']);
        $permissions = Permission::query()->whereIn('key', array_column($data['set']['grants'], 'permission'))->get()->keyBy('key');
        $dangerous = collect($data['set']['grants'])->contains(static fn (array $g): bool => $g['effect'] === 'hard_deny'
            || ($g['effect'] === 'allow' && $permissions[$g['permission']]->is_dangerous));
        if ($dangerous) {
            $stepUp->require($request, Auth::user());
        }
        $guard->guarded(function () use ($data, $subjectId, $permissions): void {
            PermissionAssignment::query()->where('subject_type', $data['subject_type'])->where('subject_id', $subjectId)->delete();
            foreach ($data['set']['grants'] as $grant) {
                PermissionAssignment::query()->create([
                    'permission_id' => $permissions[$grant['permission']]->id,
                    'subject_type' => $data['subject_type'],
                    'subject_id' => $subjectId,
                    'effect' => $grant['effect'],
                    'include_descendants' => $data['subject_type'] === 'department' && ($grant['include_descendants'] ?? false),
                    'granted_by' => Auth::id(),
                ]);
            }
            app(AuditWriter::class)->record('access.permissions_imported', 'access', objectType: $data['subject_type'], objectId: $subjectId, meta: ['grants' => count($data['set']['grants'])]);
        });

        return response()->json(['data' => ['imported' => count($data['set']['grants'])]]);
    }
}
