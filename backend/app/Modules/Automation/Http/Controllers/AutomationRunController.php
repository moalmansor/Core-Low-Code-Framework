<?php

declare(strict_types=1);

namespace App\Modules\Automation\Http\Controllers;

use App\Modules\Automation\AutomationDocument;
use App\Modules\Automation\AutomationEngine;
use App\Modules\Automation\AutomationRuns;
use App\Modules\Identity\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

/**
 * Run history of an automation; confirming a large run, cancelling a
 * waiting one and retrying a failed one need "Run automations manually".
 */
final class AutomationRunController extends Controller
{
    public function __construct(private readonly AutomationRuns $runs, private readonly AutomationEngine $engine, private readonly AutomationDocument $automations) {}

    public function index(Request $request, string $automation): JsonResponse
    {
        Gate::authorize('system.manage_automations');
        $a = $this->automations->row($automation);
        abort_if($a === null, 404);
        $data = $request->validate([
            'page' => ['sometimes', 'integer', 'min:1'],
            'status' => ['sometimes', 'nullable', 'in:queued,awaiting_confirmation,running,waiting,succeeded,failed,partially_failed,cancelled,skipped_loop'],
        ]);

        return response()->json(['data' => $this->runs->list($a, (int) ($data['page'] ?? 1), $data['status'] ?? null)]);
    }

    public function show(string $run): JsonResponse
    {
        Gate::authorize('system.manage_automations');

        return response()->json(['data' => $this->runs->detail($this->find($run))]);
    }

    public function confirm(string $run): JsonResponse
    {
        Gate::authorize('system.run_automations');
        $r = $this->find($run);
        $this->engine->confirm($r, $this->user());

        return response()->json(['data' => $this->runs->detail($this->find($run))]);
    }

    public function cancel(string $run): JsonResponse
    {
        Gate::authorize('system.run_automations');
        $this->engine->cancel($this->find($run), $this->user());

        return response()->json(['data' => $this->runs->detail($this->find($run))]);
    }

    public function retry(string $run): JsonResponse
    {
        Gate::authorize('system.run_automations');
        $this->engine->retry($this->find($run), $this->user());

        return response()->json(['data' => $this->runs->detail($this->find($run))]);
    }

    private function find(string $uuid): object
    {
        $r = Str::isUuid($uuid) ? DB::table('automation_runs')->where('uuid', strtolower($uuid))->first() : null;
        abort_if($r === null, 404);

        return $r;
    }

    private function user(): User
    {
        /** @var User $u */
        $u = Auth::user();

        return $u;
    }
}
