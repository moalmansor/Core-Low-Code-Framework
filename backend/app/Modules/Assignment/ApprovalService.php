<?php

declare(strict_types=1);

namespace App\Modules\Assignment;

use App\Infrastructure\Database\Contracts\DatabaseDriver;
use App\Modules\Audit\AuditWriter;
use App\Modules\Core\I18n\Translator;
use App\Modules\Core\Outbox\OutboxWriter;
use App\Modules\Identity\Models\User;
use App\Modules\Records\Runtime\FormRuntime;
use App\Modules\Records\Runtime\RecordException;
use App\Modules\Workflow\Runtime\WorkflowEngine;
use App\Modules\Workflow\Runtime\WorkflowRuntime;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Parallel and multi-party approvals (specification §4.25, architecture
 * §19.4). A transition with approvals opens an approval request: one
 * decision row and one assignment per approver. After each decision the rule
 * is evaluated (`all`, `any_n`, weighted `quorum`); when it is satisfied the
 * record moves through the workflow engine. Rejection either ends the request
 * at once (and may move the record to a rejection status) or waits for all
 * decisions.
 */
final class ApprovalService
{
    public function __construct(
        private readonly Membership $members,
        private readonly DelegationResolver $delegations,
        private readonly AssignmentService $assignments,
        private readonly AuditWriter $audit,
        private readonly OutboxWriter $outbox,
        private readonly DatabaseDriver $driver,
    ) {}

    /**
     * Opens the request for a transition. Runs in the caller's transaction.
     *
     * @param  array<string, mixed>  $t
     */
    public function request(FormRuntime $rt, int $recordId, array $t, int $requestedBy, int $rowVersion, ?string $comment, ?int $justificationId, ?int $onBehalfOf = null): string
    {
        $approval = $t['approval'] ?? [];
        $now = Carbon::now('UTC');
        $at = $now->format('Y-m-d H:i:s.u');
        $due = isset($approval['dueInMinutes']) ? $now->copy()->addMinutes((int) $approval['dueInMinutes'])->format('Y-m-d H:i:s.u') : null;
        $wf = WorkflowRuntime::for($rt);
        $rejection = ($approval['rejectionStatus'] ?? null) === null ? null : ($wf->statuses[$approval['rejectionStatus']]['id'] ?? null);
        $uuid = (string) Str::uuid7();
        $id = (int) DB::table('approval_requests')->insertGetId([
            'uuid' => $uuid, 'organization_id' => $rt->form->organization_id, 'created_at' => $at, 'updated_at' => $at,
            'form_id' => $rt->form->id, 'record_id' => $recordId, 'transition_id' => $t['id'], 'mode' => $approval['mode'],
            'required_count' => $approval['mode'] === 'any_n' ? (int) $approval['n'] : null,
            'quorum_weight' => $approval['mode'] === 'quorum' ? (string) $approval['quorumWeight'] : null,
            'rejection_behavior' => $approval['rejection'] ?? 'immediate', 'rejection_status_id' => $rejection,
            'status' => 'pending', 'requested_by' => $requestedBy, 'record_row_version' => $rowVersion, 'due_at' => $due, 'completed_at' => null,
        ]);
        foreach ($approval['approvers'] ?? [] as $ap) {
            $subject = $this->members->idOf((string) $ap['type'], $ap['uuid'] ?? null);
            if ($subject === null) {
                continue;
            }
            DB::table('approval_decisions')->insert([
                'created_at' => $at, 'updated_at' => $at, 'approval_request_id' => $id, 'approver_type' => $ap['type'], 'approver_id' => $subject,
                'weight' => (string) ($ap['weight'] ?? 1), 'decision' => 'pending', 'decided_by_user_id' => null, 'on_behalf_of_user_id' => null,
                'comment' => null, 'decided_at' => null, 'reminded_at' => null,
            ]);
            $this->assignments->create($rt, $recordId, (string) $ap['type'], $subject, [
                'approval_request_id' => $id, 'transition_id' => $t['id'], 'due_at' => $due, 'assigned_by' => $requestedBy,
            ], 'approval');
        }
        $meta = ['form' => $rt->form->key, 'approval' => $uuid, 'transition' => $t['key'], 'mode' => $approval['mode']] + ($comment === null || trim($comment) === '' ? [] : ['comment' => $comment]);
        $this->audit->record('approval.requested', 'workflow', null, 'record', $recordId, $meta, $requestedBy, null, $rt->form->id, $recordId, $onBehalfOf, $justificationId);
        $this->outbox->publish('approval.requested', ['form' => $rt->form->uuid, 'record' => strtolower((string) DB::table($rt->table)->where('id', $recordId)->value('uuid')), 'approval' => $uuid]);

        return $uuid;
    }

    /**
     * Records the user's decision (for every approver row the user stands
     * for, directly or as a delegate) and settles the request when its rule
     * is met.
     *
     * @return array{status: string, decided: int}
     */
    public function decide(FormRuntime $rt, string $uuid, User $user, string $decision, ?string $comment): array
    {
        return DB::transaction(function () use ($rt, $uuid, $user, $decision, $comment): array {
            $request = $this->driver->lockForUpdate(DB::table('approval_requests')->where('uuid', $uuid)->where('form_id', $rt->form->id))->first();
            if ($request === null) {
                throw new RecordException(404, 'not_found', __('assignment.approval_not_found'));
            }
            if ($request->status !== 'pending') {
                throw new RecordException(409, 'approval_closed', __('assignment.approval_closed'));
            }
            $mine = $this->rowsFor($request, $user);
            if ($mine === [] && DB::table('approval_decisions')->where('approval_request_id', $request->id)->where('decided_by_user_id', $user->id)->exists()) {
                throw new RecordException(409, 'already_decided', __('assignment.already_decided'));
            }
            if ($mine === []) {
                throw new RecordException(403, 'not_an_approver', __('assignment.not_an_approver'));
            }
            $now = Carbon::now('UTC')->format('Y-m-d H:i:s.u');
            foreach ($mine as [$row, $onBehalf]) {
                DB::table('approval_decisions')->where('id', $row->id)->where('decision', 'pending')->update([
                    'decision' => $decision, 'decided_by_user_id' => $user->id, 'on_behalf_of_user_id' => $onBehalf,
                    'comment' => $comment === null || trim($comment) === '' ? null : $comment, 'decided_at' => $now, 'updated_at' => $now,
                ]);
                DB::table('assignments')->where('approval_request_id', $request->id)->where('assignee_type', $row->approver_type)
                    ->where('assignee_id', $row->approver_id)->where('status', 'active')->update(['status' => 'completed', 'completed_at' => $now, 'updated_at' => $now]);
            }
            $onBehalf = $mine[0][1];
            $this->audit->record('approval.decided', 'workflow', [['field_key' => '@decision', 'old' => 'pending', 'new' => $decision]], 'record', (int) $request->record_id, [
                'form' => $rt->form->key, 'approval' => strtolower((string) $request->uuid), 'comment' => $comment,
            ], $user->id, null, $rt->form->id, (int) $request->record_id, $onBehalf);

            $status = $this->settle($rt, $request, $user, $onBehalf);

            return ['status' => $status, 'decided' => count($mine)];
        });
    }

    /** Cancels a record's pending request (record deleted). */
    public function cancelFor(FormRuntime $rt, int $recordId): void
    {
        $now = Carbon::now('UTC')->format('Y-m-d H:i:s.u');
        $ids = DB::table('approval_requests')->where('form_id', $rt->form->id)->where('record_id', $recordId)->where('status', 'pending')->pluck('id')->all();
        if ($ids === []) {
            return;
        }
        DB::table('approval_requests')->whereIn('id', $ids)->update(['status' => 'cancelled', 'completed_at' => $now, 'updated_at' => $now]);
        DB::table('assignments')->whereIn('approval_request_id', $ids)->where('status', 'active')->update(['status' => 'cancelled', 'completed_at' => $now, 'updated_at' => $now]);
    }

    /**
     * The pending approval of a record as shown to a user: progress, the
     * decisions so far, and whether the user may decide.
     *
     * @return array<string, mixed>|null
     */
    public function summary(FormRuntime $rt, int $recordId, User $user): ?array
    {
        $request = DB::table('approval_requests')->where('form_id', $rt->form->id)->where('record_id', $recordId)->orderByDesc('id')->first();
        if ($request === null) {
            return null;
        }
        $rows = DB::table('approval_decisions')->where('approval_request_id', $request->id)->orderBy('id')->get();
        $names = DB::table('users')->whereIn('id', $rows->pluck('decided_by_user_id')->filter()->all())->pluck('name', 'id')->all();

        return [
            'uuid' => strtolower((string) $request->uuid),
            'status' => $request->status,
            'mode' => $request->mode,
            'required_count' => $request->required_count === null ? null : (int) $request->required_count,
            'quorum_weight' => $request->quorum_weight === null ? null : (float) $request->quorum_weight,
            'transition' => WorkflowRuntime::for($rt)->transitions[strtolower((string) DB::table('transitions')->where('id', $request->transition_id)->value('uuid'))]['key'] ?? null,
            'due_at' => $request->due_at === null ? null : Carbon::parse($request->due_at, 'UTC')->toIso8601ZuluString(),
            'decisions' => $rows->map(fn ($r) => [
                'approver' => ['type' => $r->approver_type, 'uuid' => $this->members->uuidOf($r->approver_type, (int) $r->approver_id), 'name' => $this->subjectName($r->approver_type, (int) $r->approver_id)],
                'weight' => (float) $r->weight, 'decision' => $r->decision,
                'decided_by' => $r->decided_by_user_id === null ? null : ($names[$r->decided_by_user_id] ?? null),
                'on_behalf' => $r->on_behalf_of_user_id !== null,
                'comment' => $r->comment, 'decided_at' => $r->decided_at === null ? null : Carbon::parse($r->decided_at, 'UTC')->toIso8601ZuluString(),
            ])->values()->all(),
            'can_decide' => $request->status === 'pending' && $this->rowsFor($request, $user) !== [],
        ];
    }

    /**
     * Pending decision rows the user may decide: their own subjects, then
     * those of active delegators (recorded on behalf of the delegator).
     *
     * @return list<array{0: object, 1: int|null}>
     */
    private function rowsFor(object $request, User $user): array
    {
        $pending = DB::table('approval_decisions')->where('approval_request_id', $request->id)->where('decision', 'pending')->get();
        $out = [];
        $subjects = $this->members->subjectsOf($user);
        foreach ($pending as $row) {
            if (in_array((int) $row->approver_id, $subjects[$row->approver_type] ?? [], true)) {
                $out[(int) $row->id] = [$row, null];
            }
        }
        foreach ($this->delegations->principalsFor($user, (int) $request->form_id) as $principal) {
            if ($principal->id === $user->id) {
                continue;
            }
            $theirs = $this->members->subjectsOf($principal);
            foreach ($pending as $row) {
                if (! isset($out[(int) $row->id]) && in_array((int) $row->approver_id, $theirs[$row->approver_type] ?? [], true)) {
                    $out[(int) $row->id] = [$row, $principal->id];
                }
            }
        }

        return array_values($out);
    }

    private function settle(FormRuntime $rt, object $request, User $user, ?int $onBehalf): string
    {
        $rows = DB::table('approval_decisions')->where('approval_request_id', $request->id)->get();
        $approved = $rows->where('decision', 'approved');
        $rejected = $rows->where('decision', 'rejected');
        $pending = $rows->where('decision', 'pending');
        $met = match ($request->mode) {
            'all' => $pending->isEmpty() && $rejected->isEmpty(),
            'any_n' => $approved->count() >= (int) $request->required_count,
            default => $approved->sum(static fn ($r) => (float) $r->weight) >= (float) $request->quorum_weight - 1e-9,
        };
        $impossible = match ($request->mode) {
            'all' => $rejected->isNotEmpty(),
            'any_n' => $approved->count() + $pending->count() < (int) $request->required_count,
            default => $approved->sum(static fn ($r) => (float) $r->weight) + $pending->sum(static fn ($r) => (float) $r->weight) < (float) $request->quorum_weight - 1e-9,
        };
        $rejectNow = ! $met && $rejected->isNotEmpty() && ($request->rejection_behavior === 'immediate' || $pending->isEmpty());
        $failNow = ! $met && $impossible && $pending->isEmpty();
        if (! $met && ! $rejectNow && ! $failNow) {
            return 'pending';
        }
        $now = Carbon::now('UTC')->format('Y-m-d H:i:s.u');
        $status = $met ? 'approved' : 'rejected';
        DB::table('approval_requests')->where('id', $request->id)->update(['status' => $status, 'completed_at' => $now, 'updated_at' => $now]);
        DB::table('assignments')->where('approval_request_id', $request->id)->where('status', 'active')->update(['status' => 'completed', 'completed_at' => $now, 'updated_at' => $now]);
        $wf = WorkflowRuntime::for($rt);
        $engine = app(WorkflowEngine::class);
        $ctx = ['actor' => $user->id, 'onBehalfOf' => $onBehalf, 'approval' => (int) $request->id, 'source' => 'user'];
        if ($met) {
            $uuid = strtolower((string) DB::table('transitions')->where('id', $request->transition_id)->value('uuid'));
            $t = $wf->transitions[$uuid] ?? null;
            if ($t !== null) {
                $engine->move($rt, (int) $request->record_id, $t, $ctx);
            }
        } elseif ($request->rejection_status_id !== null) {
            $current = DB::table($rt->table)->where('id', $request->record_id)->value('status_id');
            $target = (int) $request->rejection_status_id;
            if ((int) $current !== $target) {
                $engine->move($rt, (int) $request->record_id, [
                    'id' => null, 'uuid' => null, 'key' => 'approval_rejected', 'fromId' => $current === null ? null : (int) $current,
                    'toId' => $target, 'to' => $wf->statusUuid[$target] ?? null,
                ], $ctx);
            }
        }
        $this->audit->record('approval.'.$status, 'workflow', null, 'record', (int) $request->record_id, ['form' => $rt->form->key, 'approval' => strtolower((string) $request->uuid)], $user->id, null, $rt->form->id, (int) $request->record_id, $onBehalf);
        $this->outbox->publish('approval.'.$status, ['form' => $rt->form->uuid, 'approval' => strtolower((string) $request->uuid)]);

        return $status;
    }

    private function subjectName(string $type, int $id): string
    {
        return match ($type) {
            'user' => (string) DB::table('users')->where('id', $id)->value('name'),
            'role' => (string) (app(Translator::class)->get('role', $id, 'name') ?? DB::table('roles')->where('id', $id)->value('key')),
            default => (string) (app(Translator::class)->get('department', $id, 'name') ?? DB::table('departments')->where('id', $id)->value('code')),
        };
    }
}
