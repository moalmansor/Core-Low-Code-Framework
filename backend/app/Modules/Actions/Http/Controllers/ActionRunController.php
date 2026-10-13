<?php

declare(strict_types=1);

namespace App\Modules\Actions\Http\Controllers;

use App\Modules\Actions\ActionRunner;
use App\Modules\Forms\Models\Form;
use App\Modules\Records\Http\Concerns\ResolvesRecords;
use App\Modules\Records\Http\Controllers\RecordController;
use App\Modules\Records\Runtime\FormRuntime;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * Running actions from the records table and the record page (§4.15): the
 * actions offered here, what a run will ask (count, justification, inputs),
 * the run itself, and its progress.
 */
final class ActionRunController extends Controller
{
    use ResolvesRecords;

    public function __construct(private readonly ActionRunner $runner) {}

    public function index(Request $request, Form $form): JsonResponse
    {
        $rt = $this->runtimeFor($form);
        $data = $request->validate(['placement' => ['sometimes', 'nullable', Rule::in(['row', 'bulk', 'toolbar', 'view_page'])], 'record' => ['sometimes', 'nullable', 'uuid']]);
        $record = isset($data['record']) ? $this->recordFor($rt, $data['record']) : null;

        return response()->json(['data' => $this->runner->available($rt, $this->currentUser(), $data['placement'] ?? null, $record)]);
    }

    public function prepare(Request $request, Form $form, string $action): JsonResponse
    {
        [$rt, $row, $targets] = $this->resolve($request, $form, $action);

        return $this->runRecord(fn () => response()->json(['data' => $this->runner->prepare($row, $rt, $this->currentUser(), $targets, (array) $request->input('inputs', []))]));
    }

    /** Tries a bulk built-in on its records without keeping anything (specification §4.35). */
    public function dryRun(Request $request, Form $form, string $action): JsonResponse
    {
        $request->validate(['inputs' => ['sometimes', 'array']]);
        [$rt, $row, $targets] = $this->resolve($request, $form, $action);

        return $this->runRecord(fn () => response()->json(['data' => $this->runner->dryRun($row, $rt, $this->currentUser(), $targets, (array) $request->input('inputs', []))]));
    }

    public function run(Request $request, Form $form, string $action): JsonResponse
    {
        $request->validate(RecordController::justificationRules() + ['inputs' => ['sometimes', 'array']]);
        [$rt, $row, $targets] = $this->resolve($request, $form, $action);

        return $this->runRecord(fn () => response()->json(['data' => $this->runner->start($row, $rt, $this->currentUser(), $targets, (array) $request->input('inputs', []), $request->input('justification'))], 201));
    }

    public function status(string $run): JsonResponse
    {
        $id = DB::table('bulk_operations')->where('uuid', strtolower($run))->value('id');
        abort_if($id === null, 404);

        return response()->json(['data' => $this->runner->present((int) $id, $this->currentUser())]);
    }

    public function cancel(string $run): JsonResponse
    {
        $this->runner->cancel($run, $this->currentUser());

        return $this->status($run);
    }

    /** @return array{0: FormRuntime, 1: object, 2: list<array{uuid: string, row_version: int|null}>} */
    private function resolve(Request $request, Form $form, string $action): array
    {
        $rt = $this->runtimeFor($form);
        $request->validate([
            'items' => ['required_without:filters', 'array', 'max:100000'],
            'items.*.uuid' => ['required', 'uuid', 'distinct'],
            'items.*.row_version' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'filters' => ['sometimes', 'array'],
        ]);
        $row = $this->runner->find($rt, $action);
        abort_if($row === null || ! $this->runner->allowed($row, $rt, $this->currentUser()), 404, __('actions.not_available'));
        $targets = $this->runner->targets($row, $rt, $this->currentUser(), $request->only(['items', 'filters']));
        // A single record must be one the user may see, and the action's condition must hold for it.
        foreach (count($targets) <= 50 ? $targets : [] as $t) {
            $found = $this->recordFor($rt, $t['uuid'], 'view', $row->builtin === 'bulk_delete');
            if ($row->condition_id !== null && $this->runner->available($rt, $this->currentUser(), null, $found) === []) {
                abort(422, __('actions.condition_not_met'));
            }
        }

        return [$rt, $row, $targets];
    }
}
