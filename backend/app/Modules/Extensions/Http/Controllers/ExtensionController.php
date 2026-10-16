<?php

declare(strict_types=1);

namespace App\Modules\Extensions\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Core\I18n\Translator;
use App\Modules\Core\Tenancy\TenantContext;
use App\Modules\Extensions\Extensions;
use App\Modules\Extensions\HookRunner;
use App\Modules\Extensions\Models\Extension;
use App\Modules\Extensions\Models\ExtensionVersion;
use App\Modules\Forms\Models\Form;
use App\Modules\Identity\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/**
 * Developer extensions (specification §4.19): Manage code writes, tests,
 * submits and deploys; Approve code approves or rejects. Both can read.
 */
final class ExtensionController extends Controller
{
    public function __construct(private readonly Extensions $extensions) {}

    public function index(): JsonResponse
    {
        $this->canRead();
        $rows = Extension::query()->orderBy('key')->get();
        $versions = ExtensionVersion::query()->whereIn('id', [...$rows->pluck('current_version_id')->filter(), ...$rows->pluck('deployed_version_id')->filter(), 0])->get()->keyBy('id');

        return response()->json(['data' => $rows->map(fn (Extension $e) => $this->summary($e, $versions[$e->current_version_id] ?? null, $versions[$e->deployed_version_id] ?? null))->all(),
            'meta' => ['can_write' => Gate::allows('system.manage_code'), 'can_approve' => Gate::allows('system.approve_code')]]);
    }

    public function show(string $extension): JsonResponse
    {
        $this->canRead();

        return response()->json(['data' => $this->detail($this->find($extension))]);
    }

    public function store(Request $request): JsonResponse
    {
        Gate::authorize('system.manage_code');
        $data = $this->validated($request, null);
        $ext = $this->extensions->create($this->user(), $data);

        return response()->json(['data' => $this->detail($ext)], 201);
    }

    public function update(Request $request, string $extension): JsonResponse
    {
        Gate::authorize('system.manage_code');
        $ext = $this->find($extension);
        $ext = $this->extensions->save($ext, $this->user(), $this->validated($request, $ext));

        return response()->json(['data' => $this->detail($ext)]);
    }

    public function test(Request $request, string $extension): JsonResponse
    {
        Gate::authorize('system.manage_code');
        $data = $request->validate(['record' => ['sometimes', 'nullable', 'uuid'], 'values' => ['sometimes', 'nullable', 'array'], 'extra' => ['sometimes', 'nullable', 'array'], 'body' => ['sometimes', 'nullable', 'array']]);
        $ext = $this->find($extension);
        $report = $this->extensions->test($ext, $this->user(), $data);

        return response()->json(['data' => ['report' => $report, 'extension' => $this->detail($ext->refresh())]]);
    }

    public function submit(string $extension): JsonResponse
    {
        Gate::authorize('system.manage_code');
        $ext = $this->find($extension);
        $this->extensions->submit($ext, $this->user());

        return response()->json(['data' => $this->detail($ext->refresh())]);
    }

    public function approve(string $extension): JsonResponse
    {
        Gate::authorize('system.approve_code');
        $ext = $this->find($extension);
        $this->extensions->approve($ext, $this->user());

        return response()->json(['data' => $this->detail($ext->refresh())]);
    }

    public function reject(Request $request, string $extension): JsonResponse
    {
        Gate::authorize('system.approve_code');
        $data = $request->validate(['reason' => ['required', 'string', 'max:2000']]);
        $ext = $this->find($extension);
        $this->extensions->reject($ext, $this->user(), $data['reason']);

        return response()->json(['data' => $this->detail($ext->refresh())]);
    }

    public function deploy(Request $request, string $extension): JsonResponse
    {
        Gate::authorize('system.manage_code');
        $data = $request->validate(['version' => ['sometimes', 'nullable', 'uuid']]);
        $ext = $this->find($extension);
        $this->extensions->deploy($ext, $this->user(), $data['version'] ?? null);

        return response()->json(['data' => $this->detail($ext->refresh())]);
    }

    public function disable(Request $request, string $extension): JsonResponse
    {
        abort_unless(Gate::any(['system.manage_code', 'system.approve_code']), 403);
        $data = $request->validate(['off' => ['required', 'boolean']]);
        $ext = $this->find($extension);
        $this->extensions->disable($ext, $this->user(), (bool) $data['off']);

        return response()->json(['data' => $this->detail($ext->refresh())]);
    }

    public function destroy(string $extension): JsonResponse
    {
        Gate::authorize('system.manage_code');
        $this->extensions->delete($this->find($extension), $this->user());

        return response()->json(null, 204);
    }

    public function version(string $extension, string $version): JsonResponse
    {
        $this->canRead();
        $ext = $this->find($extension);
        $v = ExtensionVersion::query()->where('extension_id', $ext->id)->where('uuid', strtolower($version))->firstOrFail();

        return response()->json(['data' => $this->versionRow($v) + ['source' => $this->extensions->source($ext, $v)]]);
    }

    /** What the editor needs: hook points, the permissions an endpoint can require. */
    public function meta(): JsonResponse
    {
        $this->canRead();

        return response()->json(['data' => [
            'points' => HookRunner::POINTS,
            'permissions' => DB::table('permissions')->orderBy('key')->pluck('key')->map(static fn ($k) => (string) $k)->values()->all(),
            'timeout' => ['min' => Extensions::MIN_TIMEOUT, 'max' => Extensions::MAX_TIMEOUT],
        ]]);
    }

    /** @return array<string, mixed> */
    private function validated(Request $request, ?Extension $current): array
    {
        $org = app(TenantContext::class)->organizationId();

        return $request->validate([
            'key' => $current === null ? ['required', 'string', 'regex:/^[a-z][a-z0-9_]{1,47}$/', Rule::unique('extensions', 'key')->where('organization_id', $org)] : ['prohibited'],
            'type' => $current === null ? ['required', Rule::in([...Extension::SERVER, ...Extension::CLIENT])] : ['prohibited'],
            'hook_point' => ['nullable', Rule::in(HookRunner::POINTS)],
            'form' => ['nullable', 'uuid'],
            'permission' => ['nullable', 'string', 'max:150'],
            'timeout_ms' => ['nullable', 'integer', 'between:'.Extensions::MIN_TIMEOUT.','.Extensions::MAX_TIMEOUT],
            'description' => ['nullable', 'string', 'max:2000'],
            'label' => ['nullable', 'array'],
            'label.*' => ['nullable', 'string', 'max:255'],
            'source' => ['required', 'string', 'max:200000'],
        ]) + ['type' => $current?->type];
    }

    private function find(string $uuid): Extension
    {
        return Extension::query()->where('uuid', strtolower($uuid))->firstOrFail();
    }

    private function canRead(): void
    {
        abort_unless(Gate::any(['system.manage_code', 'system.approve_code']), 403);
    }

    private function user(): User
    {
        /** @var User $u */
        $u = Auth::user();

        return $u;
    }

    /** @return array<string, mixed> */
    private function summary(Extension $e, ?ExtensionVersion $current, ?ExtensionVersion $deployed): array
    {
        $form = $e->form_id === null ? null : Form::query()->find($e->form_id);

        return [
            'uuid' => $e->uuid, 'key' => $e->key, 'type' => $e->type, 'hook_point' => $e->hook_point, 'status' => $e->status, 'timeout_ms' => $e->timeout_ms,
            'form' => $form?->uuid, 'form_name' => $form === null ? null : ($form->translate('name') ?? Translator::humanize((string) $form->key)),
            'version' => $current?->version, 'deployed_version' => $deployed?->version,
        ];
    }

    /** @return array<string, mixed> */
    private function detail(Extension $e): array
    {
        $versions = ExtensionVersion::query()->where('extension_id', $e->id)->orderByDesc('version')->get();
        $current = $versions->firstWhere('id', $e->current_version_id);
        $deployed = $versions->firstWhere('id', $e->deployed_version_id);
        $user = $this->user();

        return $this->summary($e, $current, $deployed) + [
            'description' => $current?->manifest['description'] ?? null, 'permission' => $current?->manifest['permission'] ?? null,
            'label' => (object) ($current?->manifest['label'] ?? []),
            'source' => $this->extensions->source($e, $current),
            'current' => $current === null ? null : $this->versionRow($current),
            'versions' => $versions->map(fn (ExtensionVersion $v) => $this->versionRow($v) + ['deployed' => $v->id === $e->deployed_version_id])->all(),
            // Approval is refused to the author and the last person who changed it; the screen says so before trying.
            'can_approve' => Gate::allows('system.approve_code') && $current !== null && $current->submitted_by !== $user->id && $e->updated_by !== $user->id,
        ];
    }

    /** @return array<string, mixed> */
    private function versionRow(ExtensionVersion $v): array
    {
        $names = DB::table('users')->whereIn('id', array_filter([$v->submitted_by, $v->approved_by]))->pluck('name', 'id');

        return [
            'uuid' => $v->uuid, 'version' => $v->version, 'checksum' => $v->checksum, 'test_status' => $v->test_status, 'test_report' => $v->test_report,
            'author' => $names[$v->submitted_by] ?? null, 'approved_by' => $v->approved_by === null ? null : ($names[$v->approved_by] ?? null),
            'approved_at' => $v->approved_at?->toIso8601String(), 'deployed_at' => $v->deployed_at?->toIso8601String(), 'rejected_reason' => $v->rejected_reason,
            'created_at' => $v->created_at?->toIso8601String(),
        ];
    }
}
