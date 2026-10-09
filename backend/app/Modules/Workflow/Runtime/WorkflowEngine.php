<?php

declare(strict_types=1);

namespace App\Modules\Workflow\Runtime;

use App\Modules\Access\AccessResolver;
use App\Modules\Assignment\ApprovalService;
use App\Modules\Assignment\AssignmentService;
use App\Modules\Audit\AuditWriter;
use App\Modules\Core\Correlation\CorrelationId;
use App\Modules\Core\Outbox\OutboxWriter;
use App\Modules\Identity\Models\User;
use App\Modules\Records\Models\StoredFile;
use App\Modules\Records\Runtime\FormRuntime;
use App\Modules\Records\Runtime\RecordException;
use App\Modules\Records\Runtime\RecordStore;
use App\Modules\Records\Runtime\RuleRuntime;
use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Support\Facades\DB;

/**
 * The workflow engine (specification §4.12, architecture §19.2). Which
 * transitions a user may take from a record's status, and the move itself:
 * status, history, SLA timers and assignment rules, in the caller's
 * transaction. User transitions arrive through the record pipeline
 * (`RecordPipeline::transition`), approvals through the approval service and
 * escalations through the SLA tick.
 */
final class WorkflowEngine
{
    public function __construct(
        private readonly AccessResolver $access,
        private readonly RuleRuntime $rules,
        private readonly SlaTimers $sla,
        private readonly AuditWriter $audit,
        private readonly OutboxWriter $outbox,
        private readonly CorrelationId $correlation,
        private readonly RecordStore $store,
    ) {}

    /**
     * The transitions the user may perform now: leaving the record's status,
     * permitted (`transition.{uuid}.perform`), with a holding condition, and
     * no approval pending on the record.
     *
     * @param  array{id: int, uuid: string, row_version: int, values: array<string, mixed>, system: array<string, mixed>}  $record
     * @return list<array<string, mixed>>
     */
    public function available(FormRuntime $rt, array $record, User $user): array
    {
        $wf = WorkflowRuntime::for($rt);
        if (! $wf->enabled() || $record['system']['deleted_at'] !== null || $this->pendingApproval($rt, $record['id']) !== null) {
            return [];
        }
        $out = [];
        foreach ($wf->from($record['system']['status_id'] ?? null) as $t) {
            if ($t['toId'] === null || ! $this->access->allows($user, 'transition.'.$t['uuid'].'.perform')) {
                continue;
            }
            if (($t['condition'] ?? null) !== null && ! $this->rules->holds($rt, $t['condition'], $record['values'], $user)) {
                continue;
            }
            $out[] = $this->present($wf, $t);
        }

        return $out;
    }

    /**
     * Checks that the user may perform the transition on the record now.
     *
     * @param  array{id: int, uuid: string, row_version: int, values: array<string, mixed>, system: array<string, mixed>}  $record
     * @return array<string, mixed> the transition
     */
    public function authorize(FormRuntime $rt, array $record, User $user, string $transitionUuid): array
    {
        $wf = WorkflowRuntime::for($rt);
        $t = $wf->transitions[strtolower($transitionUuid)] ?? null;
        if ($t === null || $t['toId'] === null) {
            throw new RecordException(404, 'not_found', __('workflow.transition_not_found'));
        }
        if (! $this->access->allows($user, 'transition.'.$t['uuid'].'.perform')) {
            throw new RecordException(403, 'forbidden', __('workflow.transition_forbidden'));
        }
        $status = $record['system']['status_id'] ?? null;
        if ($t['fromId'] !== null && $t['fromId'] !== $status) {
            throw new RecordException(409, 'status_changed', __('workflow.status_changed'), ['status' => $this->statusPayload($wf, $status)]);
        }
        if ($this->pendingApproval($rt, $record['id']) !== null) {
            throw new RecordException(409, 'approval_pending', __('workflow.approval_pending'));
        }

        return $t;
    }

    /**
     * Required fields, comment and attachment levels of a transition for the
     * record's values after the change.
     *
     * @param  array<string, mixed>  $t
     * @param  array<string, mixed>  $values
     * @param  list<string>  $attachments
     * @return array<string, list<string>>
     */
    public function requirements(FormRuntime $rt, array $t, array $values, User $user, ?string $comment, array $attachments): array
    {
        $errors = [];
        if (($t['condition'] ?? null) !== null && ! $this->rules->holds($rt, $t['condition'], $values, $user)) {
            $errors['transition'][] = __('workflow.condition_failed');
        }
        foreach ($t['requiredFields'] ?? [] as $uuid) {
            $f = $rt->fields[$uuid] ?? null;
            if ($f === null) {
                continue;
            }
            $v = $values[$f['key']] ?? null;
            if ($v === null || $v === '' || $v === []) {
                $errors[$f['key']][] = __('workflow.field_required_for_transition');
            }
        }
        if (($t['comment'] ?? 'none') === 'mandatory' && trim((string) $comment) === '') {
            $errors['comment'][] = __('workflow.comment_required');
        }
        if (($t['comment'] ?? 'none') === 'none' && trim((string) $comment) !== '') {
            $errors['comment'][] = __('workflow.comment_not_accepted');
        }
        if (($t['attachments'] ?? 'none') === 'mandatory' && $attachments === []) {
            $errors['attachments'][] = __('workflow.attachments_required');
        }
        if (($t['attachments'] ?? 'none') === 'none' && $attachments !== []) {
            $errors['attachments'][] = __('workflow.attachments_not_accepted');
        }

        return $errors;
    }

    /**
     * Moves the record to the transition's target status: optimistic status
     * update, history entry, SLA timers (old completed, new started),
     * assignment rules, audit and outbox. Runs inside the caller's
     * transaction. Returns the history id.
     *
     * @param  array<string, mixed>  $t
     * @param  array{actor: int|null, onBehalfOf?: int|null, justification?: int|null, approval?: int|null, comment?: string|null, attachments?: list<string>, source?: string, expectedVersion?: int|null, extra?: array<string, mixed>}  $ctx
     */
    public function move(FormRuntime $rt, int $recordId, array $t, array $ctx): int
    {
        $wf = WorkflowRuntime::for($rt);
        $now = SlaTimers::nowUtc();
        $at = $now->format('Y-m-d H:i:s.u');
        $row = DB::table($rt->table)->where('id', $recordId)->first(['id', 'uuid', 'status_id', 'status_changed_at', 'row_version', 'owner_department_id']);
        if ($row === null) {
            throw new RecordException(404, 'not_found', __('records.not_found'));
        }
        $from = $row->status_id === null ? null : (int) $row->status_id;
        if ($t['fromId'] !== null && $t['fromId'] !== $from) {
            throw new RecordException(409, 'status_changed', __('workflow.status_changed'), ['status' => $this->statusPayload($wf, $from)]);
        }
        $q = DB::table($rt->table)->where('id', $recordId)->where('row_version', $ctx['expectedVersion'] ?? (int) $row->row_version);
        $from === null ? $q->whereNull('status_id') : $q->where('status_id', $from);
        $updated = $q->update(['status_id' => $t['toId'], 'status_changed_at' => $at, 'row_version' => DB::raw('row_version + 1'), 'updated_at' => $at] + ($ctx['actor'] === null ? [] : ['updated_by' => $ctx['actor']]));
        if ($updated !== 1) {
            throw new RecordException(409, 'conflict', __('records.conflict'), ['current_row_version' => (int) DB::table($rt->table)->where('id', $recordId)->value('row_version')]);
        }
        $since = $row->status_changed_at === null ? null : new DateTimeImmutable((string) $row->status_changed_at, new DateTimeZone('UTC'));
        $historyId = $this->history($rt, $recordId, $from, $t['toId'], $t['id'], $ctx, $since, $row->owner_department_id === null ? null : (int) $row->owner_department_id, $now);
        $this->attach($rt, $recordId, 'status_history', $historyId, $ctx['attachments'] ?? [], $ctx['actor']);
        $this->sla->complete($rt->form->id, $recordId, $now);
        $record = $this->store->findById($rt, $recordId);
        $values = $record['values'] ?? [];
        $this->sla->start($rt, $wf, $recordId, $t['toId'], $historyId, $values, $row->owner_department_id === null ? null : (int) $row->owner_department_id, $now);
        app(AssignmentService::class)->onStatusChange($rt, $recordId, $t, $values, $ctx['actor']);
        $changes = [['field_key' => '@status', 'old' => $wf->status($from)['key'] ?? null, 'new' => $wf->status($t['toId'])['key'] ?? null]];
        $this->audit->record('record.transitioned', 'workflow', $changes, 'record', $recordId, [
            'form' => $rt->form->key, 'transition' => $t['key'], 'source' => $ctx['source'] ?? 'user', 'row_version' => (int) $row->row_version + 1,
        ] + ($ctx['extra'] ?? []), $ctx['actor'], null, $rt->form->id, $recordId, $ctx['onBehalfOf'] ?? null, $ctx['justification'] ?? null);
        $this->outbox->publish('record.transitioned', [
            'form' => $rt->form->uuid, 'record' => strtolower((string) $row->uuid), 'transition' => $t['uuid'],
            'from' => $wf->status($from)['uuid'] ?? null, 'to' => $t['to'], 'source' => $ctx['source'] ?? 'user',
        ]);

        return $historyId;
    }

    /**
     * The first status of a new record: history entry, SLA timers and the
     * "on create" assignment rules. Runs inside the create transaction.
     *
     * @param  array<string, mixed>  $values
     */
    public function enter(FormRuntime $rt, int $recordId, array $values, ?int $actor, ?int $onBehalfOf, ?int $ownerDepartmentId): void
    {
        $wf = WorkflowRuntime::for($rt);
        $initial = $wf->initialId();
        if ($initial !== null) {
            $now = SlaTimers::nowUtc();
            $historyId = $this->history($rt, $recordId, null, $initial, null, ['actor' => $actor, 'onBehalfOf' => $onBehalfOf, 'source' => 'user'], null, $ownerDepartmentId, $now);
            $this->sla->start($rt, $wf, $recordId, $initial, $historyId, $values, $ownerDepartmentId, $now);
        }
        app(AssignmentService::class)->onStatusChange($rt, $recordId, null, $values, $actor);
    }

    /**
     * A transition performed by the system (SLA escalation): no permission
     * check, condition and requirements are the administrator's choice when
     * configuring the escalation. A transition with approvals opens the
     * approval request instead of moving.
     *
     * @param  array<string, mixed>  $t
     */
    public function performBySystem(FormRuntime $rt, int $recordId, array $t, string $source): void
    {
        $row = DB::table($rt->table)->where('id', $recordId)->first(['id', 'status_id', 'deleted_at', 'owner_user_id', 'created_by', 'row_version']);
        if ($row === null || $row->deleted_at !== null || $t['toId'] === null) {
            return;
        }
        $from = $row->status_id === null ? null : (int) $row->status_id;
        if (($t['fromId'] !== null && $t['fromId'] !== $from) || $this->pendingApproval($rt, $recordId) !== null) {
            return;
        }
        if (($t['approval']['mode'] ?? 'none') !== 'none') {
            app(ApprovalService::class)->request($rt, $recordId, $t, (int) ($row->owner_user_id ?? $row->created_by), (int) $row->row_version, null, null);

            return;
        }
        $this->move($rt, $recordId, $t, ['actor' => null, 'source' => $source]);
    }

    /** Uuid of the pending approval request of a record, if any. */
    public function pendingApproval(FormRuntime $rt, int $recordId): ?string
    {
        $uuid = DB::table('approval_requests')->where('form_id', $rt->form->id)->where('record_id', $recordId)->where('status', 'pending')->value('uuid');

        return $uuid === null ? null : strtolower((string) $uuid);
    }

    /**
     * The status as shown to clients.
     *
     * @return array<string, mixed>|null
     */
    public function statusPayload(WorkflowRuntime $wf, ?int $statusId): ?array
    {
        $s = $wf->status($statusId);

        return $s === null ? null : [
            'uuid' => $s['uuid'], 'key' => $s['key'], 'name' => WorkflowRuntime::label($s), 'color' => $s['color'], 'icon' => $s['icon'] ?? null,
            'initial' => (bool) ($s['initial'] ?? false), 'final' => (bool) ($s['final'] ?? false),
        ];
    }

    /**
     * @param  array<string, mixed>  $t
     * @return array<string, mixed>
     */
    public function present(WorkflowRuntime $wf, array $t): array
    {
        return [
            'uuid' => $t['uuid'], 'key' => $t['key'], 'name' => WorkflowRuntime::label($t),
            'to' => $this->statusPayload($wf, $t['toId']),
            'comment' => $t['comment'] ?? 'none', 'attachments' => $t['attachments'] ?? 'none',
            'required_fields' => array_values($t['requiredFields'] ?? []),
            'confirmation' => (bool) ($t['confirmation'] ?? false),
            'style' => $t['style'] ?? null,
            'approval' => ($t['approval']['mode'] ?? 'none') === 'none' ? null : ['mode' => $t['approval']['mode']],
        ];
    }

    /** @param  array<string, mixed>  $ctx */
    private function history(FormRuntime $rt, int $recordId, ?int $from, int $to, ?int $transitionId, array $ctx, ?DateTimeImmutable $since, ?int $ownerDepartmentId, DateTimeImmutable $now): int
    {
        return (int) DB::table('status_history')->insertGetId([
            'organization_id' => $rt->form->organization_id, 'form_id' => $rt->form->id, 'record_id' => $recordId,
            'from_status_id' => $from, 'to_status_id' => $to, 'transition_id' => $transitionId, 'source' => $ctx['source'] ?? 'user',
            'comment' => ($c = trim((string) ($ctx['comment'] ?? ''))) === '' ? null : $c,
            'attachment_file_ids' => ($ctx['attachments'] ?? []) === [] ? null : json_encode(array_values($ctx['attachments'])),
            'acted_by' => $ctx['actor'], 'on_behalf_of_user_id' => $ctx['onBehalfOf'] ?? null,
            'justification_id' => $ctx['justification'] ?? null, 'approval_request_id' => $ctx['approval'] ?? null,
            'seconds_in_previous' => $since === null ? null : max(0, $now->getTimestamp() - $since->getTimestamp()),
            'working_seconds_in_previous' => $since === null ? null : $this->sla->workingSeconds($rt, $ownerDepartmentId, $since, $now),
            'correlation_id' => mb_substr($this->correlation->get(), 0, 36),
            'acted_at' => $now->format('Y-m-d H:i:s.u'),
        ]);
    }

    /**
     * Transition attachments: temporary uploads of the actor become files of
     * the record owned by the history entry (or the approval request while
     * the transition waits for approvals).
     *
     * @param  list<string>  $uuids
     */
    public function attach(FormRuntime $rt, int $recordId, string $ownerType, int $ownerId, array $uuids, ?int $actor): void
    {
        if ($uuids === []) {
            return;
        }
        $files = StoredFile::query()->whereIn('uuid', $uuids)->get();
        if ($files->count() !== count(array_unique($uuids))) {
            throw new RecordException(422, 'invalid', __('records.invalid'), ['errors' => ['attachments' => [__('records.rules.file_missing')]]]);
        }
        foreach ($files as $file) {
            if (! $file->is_temporary || $file->uploaded_by !== $actor) {
                throw new RecordException(422, 'invalid', __('records.invalid'), ['errors' => ['attachments' => [__('records.rules.file_missing')]]]);
            }
            $file->forceFill(['is_temporary' => false, 'form_id' => $rt->form->id, 'record_id' => $recordId, 'field_id' => null, 'owner_type' => $ownerType, 'owner_id' => $ownerId])->save();
        }
    }
}
