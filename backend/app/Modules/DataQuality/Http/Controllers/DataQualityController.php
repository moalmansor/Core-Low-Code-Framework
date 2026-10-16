<?php

declare(strict_types=1);

namespace App\Modules\DataQuality\Http\Controllers;

use App\Modules\Access\AccessResolver;
use App\Modules\DataQuality\DataRepair;
use App\Modules\DataQuality\DuplicateRules;
use App\Modules\Forms\Models\Form;
use App\Modules\Identity\Models\User;
use App\Modules\Records\Runtime\FormRuntimes;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

/**
 * A form's data quality (specification §4.35): duplicate rules and their
 * sweeps (with "Manage forms"), and the repair scans and fixes (with
 * "Repair data").
 */
final class DataQualityController extends Controller
{
    public function __construct(private readonly DuplicateRules $rules, private readonly DataRepair $repair, private readonly AccessResolver $access) {}

    public function rules(Form $form, FormRuntimes $runtimes): JsonResponse
    {
        Gate::authorize('system.manage_forms');

        return response()->json(['data' => ['rules' => $this->rules->load($form), 'hash' => $this->rules->hash($form), 'sweeps' => (object) $this->rules->sweeps($form),
            'published' => $runtimes->forForm($form) !== null]]);
    }

    public function saveRules(Request $request, Form $form, FormRuntimes $runtimes): JsonResponse
    {
        Gate::authorize('system.manage_forms');
        $data = $request->validate(['rules' => ['present', 'array', 'max:50'], 'base_hash' => ['required', 'string', 'size:64']]);
        if (! hash_equals($this->rules->hash($form), $data['base_hash'])) {
            return response()->json(['message' => __('data_quality.changed_elsewhere'), 'code' => 'rules_changed', 'data' => ['rules' => $this->rules->load($form), 'hash' => $this->rules->hash($form)]], 409);
        }
        $this->rules->save($form, array_values((array) $request->input('rules')), $this->user()->id);

        return $this->rules($form, $runtimes);
    }

    public function sweep(string $rule): JsonResponse
    {
        Gate::authorize('system.manage_forms');
        $r = Str::isUuid($rule) ? DB::table('duplicate_rules')->where('uuid', strtolower($rule))->first() : null;
        abort_if($r === null, 404);
        $op = $this->rules->startSweep($r, $this->user());

        return response()->json(['data' => $this->rules->presentSweep(DB::table('bulk_operations')->where('id', $op->id)->first() ?? $op, $this->user())], 202);
    }

    /** A sweep or a repair scan, as its starter's kind of user sees it. */
    public function operation(string $operation): JsonResponse
    {
        $op = Str::isUuid($operation) ? DB::table('bulk_operations')->where('uuid', strtolower($operation))->whereIn('type', ['duplicate_sweep', ...DataRepair::SCANS])->first() : null;
        abort_if($op === null, 404);
        if ($op->type === 'duplicate_sweep') {
            abort_unless($this->access->allows($this->user(), 'system.manage_forms') || $this->access->allows($this->user(), 'system.merge_records'), 403);

            return response()->json(['data' => $this->rules->presentSweep($op, $this->user())]);
        }
        Gate::authorize('system.repair_data');

        return response()->json(['data' => $this->repair->present($op, $this->user())]);
    }

    /** The latest scan of each kind. */
    public function repairState(Form $form): JsonResponse
    {
        Gate::authorize('system.repair_data');
        $out = [];
        foreach (DataRepair::SCANS as $type) {
            $op = DB::table('bulk_operations')->where('form_id', $form->id)->where('type', $type)->orderByDesc('id')->first();
            $out[$type] = $op === null ? null : $this->repair->present($op, $this->user());
        }

        return response()->json(['data' => $out]);
    }

    public function scan(Request $request, Form $form): JsonResponse
    {
        Gate::authorize('system.repair_data');
        $data = $request->validate(['type' => ['required', 'in:'.implode(',', DataRepair::SCANS)]]);
        $op = $this->repair->start($form, (string) $data['type'], $this->user());

        return response()->json(['data' => $this->repair->present(DB::table('bulk_operations')->where('id', $op->id)->first() ?? $op, $this->user())], 202);
    }

    public function clearLinks(Request $request, Form $form): JsonResponse
    {
        Gate::authorize('system.repair_data');
        $data = $request->validate(['items' => ['required', 'array', 'min:1', 'max:500'], 'items.*.record' => ['required', 'uuid'], 'items.*.field' => ['required', 'string', 'max:48']]);

        return response()->json(['data' => $this->repair->clearLinks($form, array_values($data['items']), $this->user())]);
    }

    private function user(): User
    {
        /** @var User $u */
        $u = Auth::user();

        return $u;
    }
}
