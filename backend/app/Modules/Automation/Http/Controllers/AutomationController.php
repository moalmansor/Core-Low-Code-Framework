<?php

declare(strict_types=1);

namespace App\Modules\Automation\Http\Controllers;

use App\Modules\Access\AccessResolver;
use App\Modules\Automation\AutomationDocument;
use App\Modules\Automation\AutomationEngine;
use App\Modules\Automation\AutomationRuns;
use App\Modules\Forms\Models\Form;
use App\Modules\Identity\Models\User;
use App\Modules\Records\Runtime\FormRuntimes;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

/**
 * Automations (specification §4.31): built and changed with "Manage
 * automations"; started by hand with "Run automations manually".
 */
final class AutomationController extends Controller
{
    public function __construct(
        private readonly AutomationDocument $automations,
        private readonly AutomationEngine $engine,
        private readonly AutomationRuns $runs,
        private readonly AccessResolver $access,
    ) {}

    /** Every automation, across forms. */
    public function all(): JsonResponse
    {
        Gate::authorize('system.manage_automations');

        return response()->json(['data' => $this->automations->list(null)]);
    }

    public function index(Form $form, FormRuntimes $runtimes): JsonResponse
    {
        Gate::authorize('system.manage_automations');

        return response()->json(['data' => ['automations' => $this->automations->list($form), 'published' => $runtimes->forForm($form) !== null]]);
    }

    public function store(Request $request, Form $form): JsonResponse
    {
        Gate::authorize('system.manage_automations');
        $data = $request->validate(['automation' => ['required', 'array']]);
        $a = $this->automations->save($form, null, (array) $data['automation'], $this->user());

        return response()->json(['data' => $this->present($a)], 201);
    }

    public function show(string $automation): JsonResponse
    {
        Gate::authorize('system.manage_automations');

        return response()->json(['data' => $this->present($this->find($automation))]);
    }

    public function update(Request $request, string $automation): JsonResponse
    {
        Gate::authorize('system.manage_automations');
        $a = $this->find($automation);
        $data = $request->validate(['automation' => ['required', 'array'], 'base_hash' => ['required', 'string', 'size:64']]);
        if (! hash_equals($this->automations->hash($a), $data['base_hash'])) {
            return response()->json(['message' => __('automations.changed_elsewhere'), 'code' => 'automation_changed', 'data' => $this->present($a)], 409);
        }
        $form = Form::query()->findOrFail($a->form_id);

        return response()->json(['data' => $this->present($this->automations->save($form, $a, (array) $data['automation'], $this->user()))]);
    }

    public function enable(Request $request, string $automation): JsonResponse
    {
        Gate::authorize('system.manage_automations');
        $a = $this->find($automation);
        $this->automations->enable($a, (bool) $request->validate(['enabled' => ['required', 'boolean']])['enabled'], $this->user()->id);

        return response()->json(['data' => $this->present($this->find($automation))]);
    }

    public function destroy(string $automation): JsonResponse
    {
        Gate::authorize('system.manage_automations');
        $this->automations->delete($this->find($automation), $this->user()->id);

        return response()->json(null, 204);
    }

    /** "Run now" over the records that meet the condition. */
    public function run(string $automation): JsonResponse
    {
        Gate::authorize('system.run_automations');
        $run = $this->engine->runNow($this->find($automation), $this->user());

        return response()->json(['data' => $this->runs->summary($run)], 202);
    }

    /** A test against one sample record; nothing it changes is kept. */
    public function test(Request $request, string $automation): JsonResponse
    {
        Gate::authorize('system.manage_automations');
        $data = $request->validate(['record' => ['required', 'uuid']]);
        $run = $this->engine->test($this->find($automation), (string) $data['record'], $this->user());

        return response()->json(['data' => $this->runs->detail($run)]);
    }

    /** @return array<string, mixed> */
    private function present(object $a): array
    {
        $form = Form::query()->find($a->form_id);

        return [
            'automation' => $this->automations->load($a),
            'hash' => $this->automations->hash($a),
            'form' => $form === null ? null : strtolower((string) $form->uuid),
            'can_run' => $this->access->allows($this->user(), 'system.run_automations'),
            'run_as_name' => $a->run_as_user_id === null ? null : DB::table('users')->where('id', $a->run_as_user_id)->value('name'),
        ];
    }

    private function find(string $uuid): object
    {
        $a = $this->automations->row($uuid);
        abort_if($a === null, 404);

        return $a;
    }

    private function user(): User
    {
        /** @var User $u */
        $u = Auth::user();

        return $u;
    }
}
