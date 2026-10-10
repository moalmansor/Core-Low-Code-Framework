<?php

declare(strict_types=1);

namespace App\Modules\Organization\Http\Controllers;

use App\Modules\Core\I18n\Translator;
use App\Modules\Core\Models\Locale;
use App\Modules\Core\Tenancy\TenantContext;
use App\Modules\Identity\Models\User;
use App\Modules\Organization\DepartmentTree;
use App\Modules\Organization\Models\Department;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/** Departments and the department tree (specification §4.11, Phase 1 scope). */
final class DepartmentController extends Controller
{
    public function __construct(
        private readonly DepartmentTree $tree,
        private readonly Translator $translator,
    ) {}

    public function tree(): JsonResponse
    {
        Gate::authorize('system.manage_users');
        $departments = Department::query()->with('manager:id,uuid,name')->withCount('members')->orderBy('sort_order')->orderBy('code')->get();
        $names = $this->translator->many('department', $departments->pluck('id')->all(), ['name']);
        $byParent = $departments->groupBy(static fn (Department $d): string => (string) ($d->parent_id ?? 'root'));
        $build = function (string $parentKey) use (&$build, $byParent, $names): array {
            return ($byParent[$parentKey] ?? collect())->map(fn (Department $d): array => [
                'uuid' => $d->uuid,
                'code' => $d->code,
                'name' => $names[$d->id]['name'] ?? Translator::humanize((string) $d->code),
                'names' => $d->translationsFor('name'),
                'is_active' => $d->is_active,
                'depth' => $d->depth,
                'sort_order' => $d->sort_order,
                'members_count' => $d->members_count,
                'manager' => $d->manager === null ? null : ['uuid' => $d->manager->uuid, 'name' => $d->manager->name],
                'children' => $build((string) $d->id),
            ])->values()->all();
        };

        return response()->json(['data' => $build('root')]);
    }

    public function store(Request $request): JsonResponse
    {
        Gate::authorize('system.manage_users');
        $data = $this->validated($request, null);
        $department = new Department([
            'code' => $data['code'],
            'parent_id' => isset($data['parent']) ? Department::query()->where('uuid', $data['parent'])->value('id') : null,
            'manager_user_id' => isset($data['manager']) ? User::query()->where('uuid', $data['manager'])->value('id') : null,
            'sort_order' => $data['sort_order'] ?? 0,
            'is_active' => $data['is_active'] ?? true,
        ]);
        $this->tree->create($department);
        $department->setTranslations('name', $data['name']);

        return response()->json(['data' => ['uuid' => $department->uuid]], 201);
    }

    public function update(Request $request, Department $department): JsonResponse
    {
        Gate::authorize('system.manage_users');
        $data = $this->validated($request, $department);
        $updates = collect($data)->only(['code', 'sort_order', 'is_active'])->all();
        if (array_key_exists('manager', $data)) {
            $updates['manager_user_id'] = $data['manager'] === null ? null : User::query()->where('uuid', $data['manager'])->value('id');
        }
        $department->fill($updates)->save();
        if (isset($data['name'])) {
            $department->setTranslations('name', $data['name']);
        }
        if (array_key_exists('parent', $data)) {
            $parent = $data['parent'] === null ? null : Department::query()->where('uuid', $data['parent'])->firstOrFail();
            if ($parent?->id !== $department->parent_id) {
                $this->tree->move($department, $parent);
            }
        }

        return response()->json(['data' => ['uuid' => $department->uuid]]);
    }

    public function destroy(Department $department): JsonResponse
    {
        Gate::authorize('system.manage_users');
        $this->tree->archive($department);

        return response()->json(null, 204);
    }

    /** @return array<string, mixed> */
    private function validated(Request $request, ?Department $department): array
    {
        $org = app(TenantContext::class)->organizationId();
        $default = Locale::query()->where('is_default', true)->value('code') ?? 'en';

        return $request->validate([
            'code' => [$department === null ? 'required' : 'sometimes', 'string', 'max:64', 'regex:/^[A-Za-z0-9][A-Za-z0-9_.-]{0,63}$/',
                Rule::unique(Department::class, 'code')->where('organization_id', $org)->ignore($department?->id)],
            'name' => [$department === null ? 'required' : 'sometimes', 'array'],
            'name.'.$default => [$department === null ? 'required' : 'sometimes', 'string', 'max:255'],
            'name.*' => ['nullable', 'string', 'max:255'],
            'parent' => ['sometimes', 'nullable', 'uuid', Rule::exists(Department::class, 'uuid')->whereNull('deleted_at')],
            'manager' => ['sometimes', 'nullable', 'uuid', Rule::exists(User::class, 'uuid')->whereNull('deleted_at')],
            'sort_order' => ['sometimes', 'integer', 'between:0,100000'],
            'is_active' => ['sometimes', 'boolean'],
        ]);
    }
}
