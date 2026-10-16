<?php

declare(strict_types=1);

namespace App\Modules\DataQuality\Http\Controllers;

use App\Modules\DataQuality\RecordMerger;
use App\Modules\Forms\Models\Form;
use App\Modules\Identity\Models\User;
use App\Modules\Records\Runtime\FormRuntime;
use App\Modules\Records\Runtime\FormRuntimes;
use App\Modules\Records\Runtime\RecordException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;

/** Merging duplicate records of a form (specification §4.35), with "Merge records". */
final class MergeController extends Controller
{
    public function __construct(private readonly RecordMerger $merger, private readonly FormRuntimes $runtimes) {}

    public function preview(Request $request, Form $form): JsonResponse
    {
        $data = $request->validate(['records' => ['required', 'array', 'min:2', 'max:'.(RecordMerger::MAX_MERGED + 1)], 'records.*' => ['uuid']]);

        return $this->run(fn () => response()->json(['data' => $this->merger->preview($this->runtime($form), $this->user(), array_values($data['records']))]));
    }

    public function merge(Request $request, Form $form): JsonResponse
    {
        $data = $request->validate([
            'survivor' => ['required', 'uuid'], 'merged' => ['required', 'array', 'min:1', 'max:'.RecordMerger::MAX_MERGED], 'merged.*' => ['uuid'],
            'choices' => ['sometimes', 'array'], 'choices.*' => ['uuid'], 'versions' => ['required', 'array'], 'versions.*' => ['integer', 'min:1'],
            'justification' => ['sometimes', 'nullable', 'array'],
        ]);

        return $this->run(fn () => response()->json(['data' => $this->merger->merge($this->runtime($form), $this->user(), (string) $data['survivor'], array_values($data['merged']),
            (array) ($data['choices'] ?? []), array_change_key_case((array) $data['versions']), $data['justification'] ?? null)]));
    }

    /** @param  callable(): JsonResponse  $fn */
    private function run(callable $fn): JsonResponse
    {
        try {
            return $fn();
        } catch (RecordException $e) {
            return response()->json(['message' => $e->getMessage(), 'code' => $e->reason] + $e->payload, $e->status);
        }
    }

    private function runtime(Form $form): FormRuntime
    {
        $rt = $this->runtimes->forForm($form);
        abort_if($rt === null, 404, __('records.form_unavailable'));

        return $rt;
    }

    private function user(): User
    {
        /** @var User $u */
        $u = Auth::user();

        return $u;
    }
}
