<?php

declare(strict_types=1);

namespace App\Modules\Downloads\Http\Controllers;

use App\Modules\Access\AccessResolver;
use App\Modules\Downloads\DownloadEngine;
use App\Modules\Downloads\DownloadPaths;
use App\Modules\Downloads\DownloadProfiles;
use App\Modules\Forms\Models\Form;
use App\Modules\Identity\Models\User;
use App\Modules\Records\Runtime\FormRuntime;
use App\Modules\Records\Runtime\FormRuntimes;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Building download profiles (specification §4.23): shared profiles need
 * "Manage download profiles"; personal ones need "Create personal download
 * profiles" and only ever reach what their owner can see. The tree, the
 * live preview and the role preview serve the builder.
 */
final class DownloadProfileController extends Controller
{
    public function __construct(
        private readonly DownloadProfiles $profiles,
        private readonly DownloadPaths $paths,
        private readonly DownloadEngine $engine,
        private readonly FormRuntimes $runtimes,
        private readonly AccessResolver $access,
    ) {}

    public function index(Form $form): JsonResponse
    {
        [$manager] = $this->rights();

        return response()->json(['data' => ['profiles' => $this->profiles->list($form, $this->user(), $manager), 'published' => $this->runtimes->forForm($form) !== null,
            'can_manage' => $manager, 'can_personal' => $this->rights()[1]]]);
    }

    public function store(Request $request, Form $form): JsonResponse
    {
        $data = $request->validate(['profile' => ['required', 'array'], 'personal' => ['sometimes', 'boolean']]);
        $personal = (bool) ($data['personal'] ?? false);
        $this->authorizeKind($personal);
        $saved = $this->profiles->save($form, null, $data['profile'], $this->user(), $personal);

        return response()->json(['data' => $this->document($saved)], 201);
    }

    public function show(string $profile): JsonResponse
    {
        return response()->json(['data' => $this->document($this->editable($profile))]);
    }

    public function update(Request $request, string $profile): JsonResponse
    {
        $p = $this->editable($profile);
        $data = $request->validate(['profile' => ['required', 'array'], 'base_hash' => ['required', 'string', 'size:64']]);
        if (! hash_equals($this->profiles->hash($p), $data['base_hash'])) {
            return response()->json(['message' => __('downloads.changed_elsewhere'), 'code' => 'profile_changed', 'data' => $this->document($p)], 409);
        }
        $form = Form::query()->findOrFail($p->form_id);
        $saved = $this->profiles->save($form, $p, $data['profile'], $this->user(), (bool) $p->is_personal);

        return response()->json(['data' => $this->document($saved)]);
    }

    public function destroy(string $profile): JsonResponse
    {
        $this->profiles->delete($this->editable($profile), $this->user());

        return response()->json(null, 204);
    }

    public function duplicate(string $profile): JsonResponse
    {
        $p = $this->editable($profile);
        $copy = $this->profiles->duplicate(Form::query()->findOrFail($p->form_id), $p, $this->user(), (bool) $p->is_personal);

        return response()->json(['data' => $this->document($copy)], 201);
    }

    /** One level of the relation tree; a personal builder sees only what its user can see. */
    public function tree(Request $request, Form $form): JsonResponse
    {
        $data = $request->validate(['path' => ['sometimes', 'nullable', 'string', 'max:2000'], 'personal' => ['sometimes', 'boolean']]);
        $personal = (bool) ($data['personal'] ?? false);
        $this->authorizeKind($personal);
        $path = isset($data['path']) && $data['path'] !== '' ? json_decode($data['path'], true) : [];
        if (! is_array($path) || ! array_is_list($path) || array_filter($path, static fn ($s) => ! is_string($s)) !== []) {
            throw ValidationException::withMessages(['path' => __('downloads.bad_path')]);
        }

        return response()->json(['data' => $this->paths->tree($this->runtime($form), $path, $personal ? $this->user() : null), 'max_depth' => $this->paths->maxDepth()]);
    }

    /** The first rows of an unsaved profile, as the person building it would receive them. */
    public function preview(Request $request, Form $form): JsonResponse
    {
        $data = $request->validate(['profile' => ['required', 'array'], 'personal' => ['sometimes', 'boolean'], 'parameters' => ['sometimes', 'array']]);
        $personal = (bool) ($data['personal'] ?? false);
        $this->authorizeKind($personal);
        $rt = $this->runtime($form);
        $existing = isset($data['profile']['uuid']) ? $this->profiles->row((string) $data['profile']['uuid']) : null;
        $doc = $this->profiles->check($form, $rt, $existing !== null && (int) $existing->form_id === $form->id ? $existing : null, $data['profile'], $personal ? $this->user() : null, true);

        return response()->json(['data' => $this->engine->preview($rt, $doc, $this->user(), ['parameters' => (array) ($data['parameters'] ?? [])])]);
    }

    /** Which columns members of a role would receive (field access at every level). */
    public function effective(Request $request, Form $form): JsonResponse
    {
        [$manager] = $this->rights();
        abort_unless($manager, 403);
        $data = $request->validate(['profile' => ['required', 'array'], 'role' => ['required', 'uuid']]);
        $roleId = DB::table('roles')->where('uuid', strtolower($data['role']))->value('id');
        abort_if($roleId === null, 404);
        $rt = $this->runtime($form);
        $doc = $this->profiles->check($form, $rt, null, $data['profile'], null, true);
        $cols = $this->engine->columns($rt, $doc, null, (int) $roleId);

        return response()->json(['data' => ['columns' => array_map(static fn ($c) => $c['header'], $cols['columns']), 'omitted' => $cols['omitted']]]);
    }

    /** @return array<string, mixed> */
    private function document(object $p): array
    {
        $form = Form::query()->findOrFail($p->form_id);
        $rt = $this->runtimes->forForm($form);
        // The builder shows each column's full path, and whether it reaches several records.
        $labels = [];
        foreach ($this->profiles->load($p)['columns'] as $c) {
            if ($rt !== null && is_array($c['path'])) {
                $r = $this->paths->resolve($rt, $c['path']);
                $labels[$c['uuid']] = ['label' => $this->paths->label($rt, $c['path']), 'many' => (bool) ($r['toMany'] ?? false), 'broken' => $r === null];
            }
        }

        return ['profile' => $this->profiles->load($p), 'hash' => $this->profiles->hash($p), 'form' => strtolower((string) $form->uuid),
            'personal' => (bool) $p->is_personal, 'mine' => (int) $p->owner_user_id === $this->user()->id, 'columns' => (object) $labels];
    }

    /** A profile the user may edit: shared ones for managers, their own personal ones. */
    private function editable(string $uuid): object
    {
        $p = $this->profiles->row($uuid);
        abort_if($p === null, 404);
        [$manager, $personal] = $this->rights();
        $ok = $p->is_personal ? ((int) $p->owner_user_id === $this->user()->id && $personal) : $manager;
        abort_unless($ok, $p->is_personal ? 404 : 403);

        return $p;
    }

    private function authorizeKind(bool $personal): void
    {
        [$manager, $own] = $this->rights();
        abort_unless($personal ? $own : $manager, 403);
    }

    /** @return array{0: bool, 1: bool} manage shared profiles, create personal ones */
    private function rights(): array
    {
        $u = $this->user();
        $manager = $this->access->allows($u, 'system.manage_download_profiles');
        $personal = $this->access->allows($u, 'system.create_personal_download_profiles');
        abort_unless($manager || $personal, 403);

        return [$manager, $personal];
    }

    private function runtime(Form $form): FormRuntime
    {
        return $this->runtimes->forForm($form) ?? throw ValidationException::withMessages(['form' => __('downloads.publish_first')]);
    }

    private function user(): User
    {
        /** @var User $u */
        $u = Auth::user();

        return $u;
    }
}
