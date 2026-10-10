<?php

declare(strict_types=1);

namespace App\Modules\Access\Http\Controllers;

use App\Modules\Access\AccessGuard;
use App\Modules\Access\Models\Permission;
use App\Modules\Access\Models\PermissionAssignment;
use App\Modules\Audit\AuditWriter;
use App\Modules\Core\I18n\Translator;
use App\Modules\Identity\Security\StepUp;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/** Permission catalog and grants (specification §4.11). */
final class PermissionController extends Controller
{
    public function __construct(
        private readonly AccessGuard $guard,
        private readonly Translator $translator,
        private readonly AuditWriter $audit,
    ) {}

    public function catalog(Request $request): JsonResponse
    {
        Gate::authorize('system.manage_permissions');
        $data = $request->validate(['scope_type' => ['nullable', 'string', 'max:32'], 'form' => ['sometimes', 'nullable', 'uuid']]);
        $scope = $data['scope_type'] ?? 'system';
        $formId = isset($data['form']) ? DB::table('forms')->where('uuid', $data['form'])->value('id') : null;
        // One form's objects: its own abilities, its transitions (status level) and its views.
        $permissions = Permission::query()->where('scope_type', $scope)
            ->when($formId !== null, static fn ($q) => match ($scope) {
                'form' => $q->where('scope_id', $formId),
                'transition' => $q->whereIn('scope_id', DB::table('transitions')->where('form_id', $formId)->whereNull('archived_at')->select('id')),
                'view' => $q->whereIn('scope_id', DB::table('views')->where('form_id', $formId)->select('id')),
                default => $q,
            })
            ->orderBy('category')->orderBy('key')->get();
        $labels = $this->translator->many('permission', $permissions->pluck('id')->all(), ['label', 'description']);
        // Transition and view permissions carry the name of their object.
        $objects = in_array($scope, ['transition', 'view'], true) ? $this->translator->many($scope, $permissions->pluck('scope_id')->filter()->map(static fn ($v) => (int) $v)->all(), ['name']) : [];

        return response()->json(['data' => $permissions->map(static fn (Permission $p): array => [
            'key' => $p->key,
            'category' => $p->category,
            'is_dangerous' => $p->is_dangerous,
            'label' => ($labels[$p->id]['label'] ?? Translator::humanize((string) $p->key)).(isset($objects[(int) $p->scope_id]['name']) ? ': '.$objects[(int) $p->scope_id]['name'] : ''),
            'description' => $labels[$p->id]['description'] ?? null,
        ])->values()]);
    }

    /** Grants held by one subject. */
    public function forSubject(Request $request): JsonResponse
    {
        Gate::authorize('system.manage_permissions');
        $data = $request->validate([
            'subject_type' => ['required', Rule::in(['role', 'user', 'department'])],
            'subject' => ['required', 'uuid'],
        ]);
        $subjectId = SubjectResolver::id($data['subject_type'], $data['subject']);
        $grants = PermissionAssignment::query()->with('permission')
            ->where('subject_type', $data['subject_type'])->where('subject_id', $subjectId)->get();

        return response()->json(['data' => $grants->map(static fn (PermissionAssignment $g): array => [
            'permission' => $g->permission->key,
            'effect' => $g->effect,
            'include_descendants' => $g->include_descendants,
            'valid_until' => $g->valid_until?->toIso8601String(),
        ])->values()]);
    }

    /**
     * Set, change, or remove a subject's grants in one guarded transaction.
     * Dangerous permissions and hard denies need step-up confirmation.
     */
    public function update(Request $request, StepUp $stepUp): JsonResponse
    {
        Gate::authorize('system.manage_permissions');
        $data = $request->validate([
            'subject_type' => ['required', Rule::in(['role', 'user', 'department'])],
            'subject' => ['required', 'uuid'],
            'grants' => ['required', 'array', 'min:1', 'max:500'],
            'grants.*.permission' => ['required', 'string', 'max:191'],
            'grants.*.effect' => ['present', 'nullable', Rule::in(['allow', 'deny', 'hard_deny'])],
            'grants.*.include_descendants' => ['sometimes', 'boolean'],
            'grants.*.valid_until' => ['sometimes', 'nullable', 'date', 'after:now'],
            'confirmation_code' => ['sometimes', 'nullable', 'string', 'max:64'],
        ]);
        $subjectId = SubjectResolver::id($data['subject_type'], $data['subject']);
        $permissions = Permission::query()->whereIn('key', array_column($data['grants'], 'permission'))->get()->keyBy('key');
        foreach ($data['grants'] as $i => $grant) {
            abort_unless($permissions->has($grant['permission']), 422, __('validation.exists', ['attribute' => "grants.{$i}.permission"]));
        }
        $dangerous = collect($data['grants'])->contains(static fn (array $g): bool => $g['effect'] === 'hard_deny'
            || ($g['effect'] === 'allow' && $permissions[$g['permission']]->is_dangerous));
        if ($dangerous) {
            $stepUp->require($request, Auth::user());
        }

        $this->guard->guarded(function () use ($data, $subjectId, $permissions): void {
            $changes = [];
            foreach ($data['grants'] as $grant) {
                $permission = $permissions[$grant['permission']];
                $existing = PermissionAssignment::query()->where([
                    'permission_id' => $permission->id, 'subject_type' => $data['subject_type'], 'subject_id' => $subjectId,
                ])->first();
                $old = $existing?->effect;
                if ($grant['effect'] === null) {
                    $existing?->delete();
                } else {
                    PermissionAssignment::query()->updateOrCreate(
                        ['permission_id' => $permission->id, 'subject_type' => $data['subject_type'], 'subject_id' => $subjectId],
                        [
                            'effect' => $grant['effect'],
                            'include_descendants' => $data['subject_type'] === 'department' && ($grant['include_descendants'] ?? false),
                            'valid_until' => $grant['valid_until'] ?? null,
                            'granted_by' => Auth::id(),
                        ],
                    );
                }
                if ($old !== $grant['effect']) {
                    $changes[] = ['field_key' => $permission->key, 'old' => $old, 'new' => $grant['effect']];
                }
            }
            if ($changes !== []) {
                $this->audit->record('access.grants_changed', 'access', $changes, $data['subject_type'], $subjectId);
            }
        });

        return $this->forSubject(new Request(['subject_type' => $data['subject_type'], 'subject' => $data['subject']]));
    }
}
