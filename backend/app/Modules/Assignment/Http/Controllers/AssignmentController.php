<?php

declare(strict_types=1);

namespace App\Modules\Assignment\Http\Controllers;

use App\Modules\Access\AccessResolver;
use App\Modules\Assignment\ApprovalService;
use App\Modules\Assignment\AssignmentRules;
use App\Modules\Assignment\AssignmentService;
use App\Modules\Assignment\Claims;
use App\Modules\Assignment\Membership;
use App\Modules\Forms\Models\Form;
use App\Modules\Justification\JustificationGate;
use App\Modules\Records\Http\Concerns\ResolvesRecords;
use App\Modules\Records\Http\Controllers\RecordController;
use App\Modules\Records\Runtime\FormRuntimes;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/**
 * Assignment endpoints (specification §4.25, architecture §21 Assignment):
 * manual assignment and reassignment (permission-controlled, audited,
 * with a justification when the rules ask for one), claim and release on
 * queues, approval decisions, and the form's assignment rules.
 */
final class AssignmentController extends Controller
{
    use ResolvesRecords;

    public function __construct(
        private readonly AssignmentService $assignments,
        private readonly Claims $claims,
        private readonly Membership $members,
        private readonly AccessResolver $access,
    ) {}

    public function assign(Request $request, Form $form, string $record, JustificationGate $gate): JsonResponse
    {
        $rt = $this->runtimeFor($form);
        $data = $request->validate([
            'assignee.type' => ['required', Rule::in(['user', 'role', 'department'])],
            'assignee.uuid' => ['required', 'uuid'],
            'due_at' => ['sometimes', 'nullable', 'date', 'after:now'],
            'priority' => ['sometimes', 'integer', 'between:-100,100'],
        ] + RecordController::justificationRules());
        $found = $this->recordFor($rt, $record, 'edit');
        $user = $this->currentUser();
        $reassign = $this->assignments->hasActive($form->id, $found['id']);
        abort_unless($this->access->allows($user, $reassign ? 'system.reassign_records' : 'system.assign_records'), 403, __('records.forbidden'));
        $id = $this->members->idOf($data['assignee']['type'], strtolower($data['assignee']['uuid']));
        abort_if($id === null, 422, __('workflow.unknown_approver'));
        if ($data['assignee']['type'] === 'user') {
            abort_unless(DB::table('users')->where('id', $id)->where('status', 'active')->whereNull('deleted_at')->exists(), 422, __('assignment.inactive_user'));
        }

        return $this->runRecord(function () use ($rt, $found, $data, $user, $id, $reassign, $gate): JsonResponse {
            $validated = $reassign ? $gate->enforce($rt, $user, 'reassign', ['status' => $found['system']['status_id'] ?? null, 'values' => $found['values'], 'old' => $found['values']], $data['justification'] ?? null) : null;
            $uuid = DB::transaction(function () use ($rt, $found, $data, $user, $id, $validated): string {
                $justification = $validated === null ? null : app(JustificationGate::class)->record($rt, $found['id'], $user, 'reassign', $validated, [], 1);

                return $this->assignments->assign($rt, $found['id'], $data['assignee']['type'], $id, $user->id, [
                    'due_at' => isset($data['due_at']) ? Carbon::parse($data['due_at'])->utc()->format('Y-m-d H:i:s.u') : null,
                    'priority' => $data['priority'] ?? 0, 'justification' => $justification,
                ]);
            });

            return response()->json(['data' => ['assignment' => $uuid, 'reassigned' => $reassign]], 201);
        });
    }

    public function claim(Form $form, string $record): JsonResponse
    {
        $rt = $this->runtimeFor($form);
        $found = $this->recordFor($rt, $record, 'view');

        return $this->runRecord(fn () => response()->json(['data' => $this->claims->claim($rt, $found['id'], $this->currentUser())]));
    }

    public function release(Form $form, string $record): JsonResponse
    {
        $rt = $this->runtimeFor($form);
        $found = $this->recordFor($rt, $record, 'view');

        return $this->runRecord(function () use ($rt, $found): JsonResponse {
            $this->claims->release($rt, $found['id'], $this->currentUser());

            return response()->json(null, 204);
        });
    }

    public function decide(Request $request, string $approval, ApprovalService $approvals): JsonResponse
    {
        $data = $request->validate(['decision' => ['required', Rule::in(['approved', 'rejected'])], 'comment' => ['sometimes', 'nullable', 'string', 'max:5000']]);
        $row = DB::table('approval_requests')->where('uuid', strtolower($approval))->first(['form_id', 'record_id']);
        abort_if($row === null, 404);
        $form = Form::query()->findOrFail($row->form_id);
        $rt = app(FormRuntimes::class)->forForm($form);
        abort_if($rt === null, 404);
        if ($data['decision'] === 'rejected' && trim((string) ($data['comment'] ?? '')) === '') {
            return response()->json(['message' => __('records.invalid'), 'code' => 'invalid', 'errors' => ['comment' => [__('assignment.rejection_comment_required')]]], 422);
        }

        return $this->runRecord(fn () => response()->json(['data' => $approvals->decide($rt, strtolower($approval), $this->currentUser(), $data['decision'], $data['comment'] ?? null)]));
    }

    public function rules(Form $form, AssignmentRules $rules): JsonResponse
    {
        Gate::authorize('system.manage_forms');

        return response()->json(['data' => ['rules' => $rules->load($form), 'hash' => $rules->hash($form)]]);
    }

    public function saveRules(Request $request, Form $form, AssignmentRules $rules): JsonResponse
    {
        Gate::authorize('system.manage_forms');
        $data = $request->validate(['rules' => ['present', 'array', 'max:200'], 'base_hash' => ['required', 'string', 'size:64']]);
        if (! hash_equals($rules->hash($form), $data['base_hash'])) {
            return response()->json(['message' => __('assignment.changed_elsewhere'), 'code' => 'rules_changed', 'data' => ['rules' => $rules->load($form), 'hash' => $rules->hash($form)]], 409);
        }
        $rules->save($form, array_values(json_decode((string) json_encode($request->input('rules')), true)), (int) Auth::id());

        return $this->rules($form, $rules);
    }
}
