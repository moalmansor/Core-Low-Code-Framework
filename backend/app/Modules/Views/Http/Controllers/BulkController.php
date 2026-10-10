<?php

declare(strict_types=1);

namespace App\Modules\Views\Http\Controllers;

use App\Modules\Forms\Models\Form;
use App\Modules\Justification\JustificationGate;
use App\Modules\Records\Http\Concerns\ResolvesRecords;
use App\Modules\Records\Http\Controllers\RecordController;
use App\Modules\Records\Runtime\RecordException;
use App\Modules\Records\Runtime\RecordPipeline;
use App\Modules\Records\Runtime\RecordStore;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Bulk selection in the records table (specification §4.14): delete or
 * restore the selected records, each through the record pipeline (permission,
 * scope, claim, legal hold, optimistic version), asking for a justification
 * once for the whole selection with the count shown (§4.24). Each record's
 * outcome is reported; one failure never stops the others.
 */
final class BulkController extends Controller
{
    use ResolvesRecords;

    public function __construct(private readonly RecordPipeline $pipeline, private readonly RecordStore $store, private readonly JustificationGate $gate) {}

    public function delete(Request $request, Form $form): JsonResponse
    {
        return $this->run($request, $form, 'delete');
    }

    public function restore(Request $request, Form $form): JsonResponse
    {
        return $this->run($request, $form, 'restore');
    }

    private function run(Request $request, Form $form, string $operation): JsonResponse
    {
        $rt = $this->runtimeFor($form, $operation);
        $data = $request->validate([
            'items' => ['required', 'array', 'min:1', 'max:200'],
            'items.*.uuid' => ['required', 'uuid', 'distinct'],
            'items.*.row_version' => [$operation === 'delete' ? 'required' : 'sometimes', 'integer', 'min:1'],
        ] + RecordController::justificationRules());
        $user = $this->currentUser();

        return $this->runRecord(function () use ($rt, $data, $user, $operation): JsonResponse {
            // The strictest requirement over the selection decides whether one justification is asked for.
            $required = null;
            foreach ($data['items'] as $item) {
                $found = $this->store->find($rt, strtolower($item['uuid']), true);
                if ($found === null) {
                    continue;
                }
                $req = $this->gate->requirement($rt, $user, $operation, ['status' => $found['system']['status_id'] ?? null, 'values' => $found['values'], 'old' => $found['values']]);
                if ($req !== null && ($required === null || $req['level'] === 'mandatory')) {
                    $required = [$req, $found];
                }
            }
            $justificationId = null;
            if ($required !== null) {
                [, $sample] = $required;
                try {
                    $validated = $this->gate->enforce($rt, $user, $operation, ['status' => $sample['system']['status_id'] ?? null, 'values' => $sample['values'], 'old' => $sample['values']], $data['justification'] ?? null);
                } catch (RecordException $e) {
                    throw new RecordException($e->status, $e->reason, $e->getMessage(), $e->payload + ['count' => count($data['items'])]);
                }
                if ($validated !== null) {
                    $justificationId = DB::transaction(fn () => $this->gate->record($rt, null, $user, $operation, $validated, [], count($data['items'])));
                }
            }
            $results = [];
            foreach ($data['items'] as $item) {
                $uuid = strtolower($item['uuid']);
                try {
                    $operation === 'delete'
                        ? $this->pipeline->delete($rt, $user, $uuid, (int) $item['row_version'], (string) Str::uuid7(), null, 'delete', $justificationId)
                        : $this->pipeline->restore($rt, $user, $uuid, (string) Str::uuid7(), null, $justificationId);
                    $results[] = ['uuid' => $uuid, 'status' => 'ok'];
                } catch (RecordException $e) {
                    $results[] = ['uuid' => $uuid, 'status' => 'error', 'code' => $e->reason, 'message' => $e->getMessage()];
                }
            }

            return response()->json(['data' => ['results' => $results, 'succeeded' => count(array_filter($results, static fn ($r) => $r['status'] === 'ok'))]]);
        });
    }
}
