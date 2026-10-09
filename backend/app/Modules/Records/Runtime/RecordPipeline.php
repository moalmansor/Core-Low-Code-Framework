<?php

declare(strict_types=1);

namespace App\Modules\Records\Runtime;

use App\Expressions\Text\Unicode;
use App\Modules\Access\AccessResolver;
use App\Modules\Access\FieldAccessResolver;
use App\Modules\Access\RecordScope;
use App\Modules\Assignment\ApprovalService;
use App\Modules\Assignment\AssignmentService;
use App\Modules\Assignment\Claims;
use App\Modules\Assignment\DelegationResolver;
use App\Modules\Audit\AuditWriter;
use App\Modules\Core\Outbox\OutboxWriter;
use App\Modules\Identity\Models\User;
use App\Modules\Justification\JustificationGate;
use App\Modules\Records\Models\StoredFile;
use App\Modules\Reference\Models\NumberSequence;
use App\Modules\Reference\NumberGenerator;
use App\Modules\Workflow\Runtime\SlaTimers;
use App\Modules\Workflow\Runtime\WorkflowEngine;
use App\Modules\Workflow\Runtime\WorkflowRuntime;
use App\Support\Json\Canonical;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * The one write path for records (architecture §3.3): journal → authorize
 * (form permission, record scope, claim) → load and version check → normalize
 * → rules (defaults, formulas, condition effects) → field access (for the
 * record's status) → validate → justification (§19.3) → one transaction
 * (numbers, files, row, child rows, pivots, workflow, justification, audit,
 * outbox) → journal processed. Duplicate checks arrive with their module in
 * Phase 4. Actions taken only through a delegator's access are recorded as
 * on behalf of the delegator (§19.4).
 */
final class RecordPipeline
{
    public function __construct(
        private readonly ValueCodec $codec,
        private readonly RecordStore $store,
        private readonly RuleRuntime $rules,
        private readonly RecordValidator $validator,
        private readonly SubmissionJournal $journal,
        private readonly FieldAccessResolver $fieldAccess,
        private readonly AccessResolver $access,
        private readonly AuditWriter $audit,
        private readonly OutboxWriter $outbox,
        private readonly NumberGenerator $numbers,
        private readonly References $refs,
        private readonly ReferentialIntegrity $integrity,
        private readonly RecordScope $scope,
        private readonly WorkflowEngine $workflow,
        private readonly JustificationGate $justifications,
        private readonly Claims $claims,
        private readonly DelegationResolver $delegations,
        private readonly SlaTimers $sla,
    ) {}

    /**
     * @param  array<string, mixed>  $input  values by field key (repeater keys hold row lists)
     * @param  array<string, mixed>  $params  URL parameters (defaults)
     * @param  array<string, int>  $links  foreign-key columns outside the definition set at insert (inline sub-form parent)
     * @return array{id: int, uuid: string}
     */
    public function create(FormRuntime $rt, User $user, array $input, array $params, string $key, string $source = 'ui', array $links = []): array
    {
        $onBehalf = $this->guardForm($rt, $user, 'create');
        $entry = $this->journal->open($key, $rt->form->id, $rt->versionId, 'create', $source, $user->id, $input, null, null);
        if (($done = $this->replay($rt, $entry)) !== null) {
            return $done;
        }
        try {
            $levels = $this->fieldAccess->resolve($user, $rt->form->id, $rt->form->uuid, $rt->definition, 'create');
            [$normalized, $errors] = $this->normalize($rt, $input);
            [$normalized, $rowErrors] = $this->guardRows($rt, $normalized, null, $levels['fields']);
            $errors += $rowErrors + $this->rowPermissions($rt, $normalized, null, $user);
            $result = $this->rules->run($rt, $normalized, null, 'create', $user, true, $params);
            $blocked = $this->blocked($rt, $levels['fields'], $result['state']);
            if (array_intersect_key($normalized, $blocked) !== []) {
                // Values of fields the user may not edit never reach the record.
                $result = $this->rules->run($rt, array_diff_key($normalized, $blocked), null, 'create', $user, true, $params);
            }
            $values = $result['values'];
            $errors += $this->accessViolations($rt, $input, $values, null, $levels['fields'], $result['state']);
            $ctx = $this->rules->context($rt, 'create', $user, $params);
            $errors += $this->validator->validate($rt, $values, $result['state'], $levels['fields'], $ctx, null);
            if ($errors !== []) {
                throw new RecordException(422, 'invalid', __('records.invalid'), ['errors' => $errors]);
            }
            // A crash between commit and journal update: the record exists under the journal's uuid.
            if (($existing = $this->store->find($rt, $entry['uuid'], true)) !== null) {
                $this->journal->processed($entry['id'], $existing['id']);

                return ['id' => $existing['id'], 'uuid' => $existing['uuid']];
            }
            $id = DB::transaction(function () use ($rt, $user, $values, $entry, $links, $onBehalf): int {
                $values = $this->assignNumbers($rt, $values);
                $wf = WorkflowRuntime::for($rt);
                $now = SlaTimers::nowUtc()->format('Y-m-d H:i:s.u');
                $system = [
                    'organization_id' => $rt->form->organization_id,
                    'form_version_id' => $rt->versionId,
                    'record_number' => $this->recordNumber($rt),
                    'owner_user_id' => $user->id,
                    'owner_department_id' => $user->department_id,
                    'created_by' => $user->id,
                    'updated_by' => $user->id,
                    'search_text' => $this->searchText($rt, $values),
                    'status_id' => $wf->initialId(),
                    'status_changed_at' => $wf->initialId() === null ? null : $now,
                ] + $links;
                $id = $this->store->insert($rt, $entry['uuid'], $values, $system);
                $this->attachFiles($rt, $id, $values, $user);
                $this->audit->record('record.created', 'data', $this->diff($rt, [], $values), 'record', $id, ['form' => $rt->form->key, 'row_version' => 1], $user->id, null, $rt->form->id, $id, $onBehalf);
                $this->workflow->enter($rt, $id, $values, $user->id, $onBehalf, $user->department_id === null ? null : (int) $user->department_id);
                $this->outbox->publish('record.saved', ['form' => $rt->form->uuid, 'record' => $entry['uuid'], 'operation' => 'create']);

                return $id;
            });
            $this->journal->processed($entry['id'], $id);

            return ['id' => $id, 'uuid' => $entry['uuid']];
        } catch (RecordException $e) {
            $this->journal->rejected($entry['id'], $e->reason);
            throw $e;
        } catch (Throwable $e) {
            $this->journal->failed($entry['id'], $e);
            throw $e;
        }
    }

    /**
     * @param  array<string, mixed>  $input  only the submitted keys change
     * @return array{id: int, uuid: string, changed: list<string>}
     */
    public function update(FormRuntime $rt, User $user, string $uuid, int $expectedVersion, array $input, string $key, string $source = 'ui', ?array $justification = null): array
    {
        $onBehalf = $this->guardForm($rt, $user, 'edit');
        $current = $this->store->find($rt, $uuid) ?? throw new RecordException(404, 'not_found', __('records.not_found'));
        $entry = $this->journal->open($key, $rt->form->id, $rt->versionId, 'update', $source, $user->id, $input, $expectedVersion, $current['id']);
        if (($done = $this->replay($rt, $entry)) !== null) {
            return $done + ['changed' => []];
        }
        try {
            $onBehalf = $this->guardRow($rt, $user, 'edit', $current['id']) ?? $onBehalf;
            $this->claims->guard($rt, $current['id'], $user);
            $statusId = $current['system']['status_id'] ?? null;
            $levels = $this->fieldAccess->resolve($user, $rt->form->id, $rt->form->uuid, $rt->definition, 'edit', $statusId);
            if ($current['row_version'] !== $expectedVersion) {
                throw $this->conflict($rt, $current, $expectedVersion, $input, $levels['fields']);
            }
            [$normalized, $errors] = $this->normalize($rt, $input);
            [$normalized, $rowErrors] = $this->guardRows($rt, $normalized, $current['values'], $levels['fields']);
            $errors += $rowErrors + $this->rowPermissions($rt, $normalized, $current['values'], $user);
            $merged = array_replace($current['values'], $normalized);
            $result = $this->rules->run($rt, $merged, $current['values'], 'edit', $user, false);
            $blocked = $this->blocked($rt, $levels['fields'], $result['state']);
            if (array_intersect_key($normalized, $blocked) !== []) {
                $result = $this->rules->run($rt, array_replace($current['values'], array_diff_key($normalized, $blocked)), $current['values'], 'edit', $user, false);
            }
            $values = $result['values'];
            $errors += $this->accessViolations($rt, $input, $values, $current['values'], $levels['fields'], $result['state']);
            $ctx = $this->rules->context($rt, 'edit', $user);
            $errors += $this->validator->validate($rt, $values, $result['state'], $levels['fields'], $ctx, $current['id']);
            if ($errors !== []) {
                throw new RecordException(422, 'invalid', __('records.invalid'), ['errors' => $errors]);
            }
            $changed = [];
            foreach ($values as $k => $v) {
                if (! Canonical::same($v, $current['values'][$k] ?? null)) {
                    $changed[] = (string) $k;
                }
            }
            $validated = $changed === [] ? null : $this->justifications->enforce($rt, $user, 'edit', [
                'fields' => $changed, 'status' => $statusId, 'values' => $values, 'old' => $current['values'],
            ], $justification);
            if ($changed !== []) {
                DB::transaction(function () use ($rt, $user, $current, $values, $changed, $expectedVersion, $input, $levels, $validated, $onBehalf): void {
                    $values = $this->assignNumbers($rt, $values, $current['values']);
                    $diff = $this->diff($rt, $current['values'], $values);
                    $justificationId = $validated === null ? null : $this->justifications->record($rt, $current['id'], $user, 'edit', $validated, $this->justified($diff), 1, $onBehalf);
                    $ok = $this->store->update($rt, $current['id'], $expectedVersion, $values, $changed, [
                        'updated_by' => $user->id, 'form_version_id' => $rt->versionId, 'search_text' => $this->searchText($rt, $values),
                    ], $rt->form->organization_id, $user->id);
                    if (! $ok) {
                        throw $this->conflict($rt, $this->store->find($rt, $current['uuid']) ?? $current, $expectedVersion, $input, $levels['fields']);
                    }
                    $this->attachFiles($rt, $current['id'], $values, $user);
                    $this->audit->record('record.updated', 'data', $diff, 'record', $current['id'], ['form' => $rt->form->key, 'row_version' => $expectedVersion + 1], $user->id, null, $rt->form->id, $current['id'], $onBehalf, $justificationId);
                    $this->outbox->publish('record.saved', ['form' => $rt->form->uuid, 'record' => $current['uuid'], 'operation' => 'update', 'changed' => $changed]);
                });
            }
            $this->journal->processed($entry['id'], $current['id']);

            return ['id' => $current['id'], 'uuid' => $current['uuid'], 'changed' => $changed];
        } catch (RecordException $e) {
            $this->journal->rejected($entry['id'], $e->reason);
            throw $e;
        } catch (Throwable $e) {
            $this->journal->failed($entry['id'], $e);
            throw $e;
        }
    }

    /**
     * Performs a workflow transition (architecture §19.2) with
     * `operation = transition`: transition permission (directly or through a
     * delegator), record scope and claim, the record's current status, values
     * submitted with the transition (through the same rules, access and
     * validation as an edit), required fields, comment and attachments, then
     * justification. With approvals the record waits for the decisions;
     * otherwise it moves now.
     *
     * @param  array{values?: array<string, mixed>, comment?: string|null, attachments?: list<string>, justification?: array<string, mixed>|null}  $input
     * @return array{state: string, approval: string|null, row_version: int, status: array<string, mixed>|null}
     */
    public function transition(FormRuntime $rt, User $user, string $uuid, string $transitionUuid, int $expectedVersion, array $input, string $key, string $source = 'ui'): array
    {
        $onBehalf = $this->guardForm($rt, $user, 'view');
        $current = $this->store->find($rt, $uuid) ?? throw new RecordException(404, 'not_found', __('records.not_found'));
        $entry = $this->journal->open($key, $rt->form->id, $rt->versionId, 'transition', $source, $user->id, ['transition' => $transitionUuid] + $input, $expectedVersion, $current['id']);
        if ($entry['state'] === 'processed') {
            $row = DB::table($rt->table)->where('id', $current['id'])->first(['row_version', 'status_id']);

            return ['state' => 'moved', 'approval' => null, 'row_version' => (int) $row->row_version, 'status' => $this->workflow->statusPayload(WorkflowRuntime::for($rt), $row->status_id === null ? null : (int) $row->status_id)];
        }
        if ($entry['state'] === 'foreign' || $entry['state'] === 'processing') {
            $this->replay($rt, $entry);
        }
        try {
            $onBehalf = $this->guardRow($rt, $user, 'edit', $current['id']) ?? $onBehalf;
            $this->claims->guard($rt, $current['id'], $user);
            $wf = WorkflowRuntime::for($rt);
            $t = $wf->transitions[strtolower($transitionUuid)] ?? null;
            if ($t === null || $t['toId'] === null) {
                throw new RecordException(404, 'not_found', __('workflow.transition_not_found'));
            }
            $via = $this->permitted($rt, $user, ['transition.'.$t['uuid'].'.perform']);
            if ($via === false) {
                throw new RecordException(403, 'forbidden', __('workflow.transition_forbidden'));
            }
            $onBehalf = $via ?? $onBehalf;
            $statusId = $current['system']['status_id'] ?? null;
            if ($t['fromId'] !== null && $t['fromId'] !== $statusId) {
                throw new RecordException(409, 'status_changed', __('workflow.status_changed'), ['status' => $this->workflow->statusPayload($wf, $statusId)]);
            }
            if ($this->workflow->pendingApproval($rt, $current['id']) !== null) {
                throw new RecordException(409, 'approval_pending', __('workflow.approval_pending'));
            }
            $levels = $this->fieldAccess->resolve($user, $rt->form->id, $rt->form->uuid, $rt->definition, 'edit', $statusId);
            if ($current['row_version'] !== $expectedVersion) {
                throw $this->conflict($rt, $current, $expectedVersion, $input['values'] ?? [], $levels['fields']);
            }
            $submitted = $input['values'] ?? [];
            $errors = [];
            $values = $current['values'];
            if ($submitted !== []) {
                if ($this->permitted($rt, $user, ["form.{$rt->form->uuid}.edit"]) === false) {
                    throw new RecordException(403, 'forbidden', __('records.forbidden'));
                }
                [$normalized, $errors] = $this->normalize($rt, $submitted);
                [$normalized, $rowErrors] = $this->guardRows($rt, $normalized, $current['values'], $levels['fields']);
                $errors += $rowErrors + $this->rowPermissions($rt, $normalized, $current['values'], $user);
                $result = $this->rules->run($rt, array_replace($current['values'], $normalized), $current['values'], 'edit', $user, false);
                $blocked = $this->blocked($rt, $levels['fields'], $result['state']);
                if (array_intersect_key($normalized, $blocked) !== []) {
                    $result = $this->rules->run($rt, array_replace($current['values'], array_diff_key($normalized, $blocked)), $current['values'], 'edit', $user, false);
                }
                $values = $result['values'];
                $errors += $this->accessViolations($rt, $submitted, $values, $current['values'], $levels['fields'], $result['state']);
                $errors += $this->validator->validate($rt, $values, $result['state'], $levels['fields'], $this->rules->context($rt, 'edit', $user), $current['id']);
            }
            $attachments = array_values(array_unique(array_filter((array) ($input['attachments'] ?? []), 'is_string')));
            $comment = isset($input['comment']) && is_string($input['comment']) ? mb_substr($input['comment'], 0, 5000) : null;
            $errors += $this->workflow->requirements($rt, $t, $values, $user, $comment, $attachments);
            if ($errors !== []) {
                throw new RecordException(422, 'invalid', __('records.invalid'), ['errors' => $errors]);
            }
            $changed = [];
            foreach ($values as $k => $v) {
                if (! Canonical::same($v, $current['values'][$k] ?? null)) {
                    $changed[] = (string) $k;
                }
            }
            $validated = $this->justifications->enforce($rt, $user, 'transition', [
                'fields' => $changed, 'status' => $statusId, 'transition' => $t['id'], 'values' => $values, 'old' => $current['values'],
            ], $input['justification'] ?? null);
            $result = DB::transaction(function () use ($rt, $user, $current, $values, $changed, $expectedVersion, $levels, $t, $validated, $onBehalf, $comment, $attachments, $source): array {
                $diff = $this->diff($rt, $current['values'], $values);
                $justificationId = $validated === null ? null : $this->justifications->record($rt, $current['id'], $user, 'transition', $validated, $this->justified($diff), 1, $onBehalf);
                $version = $expectedVersion;
                if ($changed !== []) {
                    $values = $this->assignNumbers($rt, $values, $current['values']);
                    $ok = $this->store->update($rt, $current['id'], $expectedVersion, $values, $changed, [
                        'updated_by' => $user->id, 'form_version_id' => $rt->versionId, 'search_text' => $this->searchText($rt, $values),
                    ], $rt->form->organization_id, $user->id);
                    if (! $ok) {
                        throw $this->conflict($rt, $this->store->find($rt, $current['uuid']) ?? $current, $expectedVersion, $values, $levels['fields']);
                    }
                    $this->attachFiles($rt, $current['id'], $values, $user);
                    $this->audit->record('record.updated', 'data', $diff, 'record', $current['id'], ['form' => $rt->form->key, 'row_version' => $expectedVersion + 1, 'transition' => $t['key']], $user->id, null, $rt->form->id, $current['id'], $onBehalf, $justificationId);
                    $version++;
                }
                if (($t['approval']['mode'] ?? 'none') !== 'none') {
                    $approval = app(ApprovalService::class)->request($rt, $current['id'], $t, $user->id, $version, $comment, $justificationId, $onBehalf);
                    $this->workflow->attach($rt, $current['id'], 'approval_request', (int) DB::table('approval_requests')->where('uuid', $approval)->value('id'), $attachments, $user->id);

                    return ['state' => 'approval_pending', 'approval' => $approval, 'row_version' => $version];
                }
                $this->workflow->move($rt, $current['id'], $t, [
                    'actor' => $user->id, 'onBehalfOf' => $onBehalf, 'justification' => $justificationId, 'comment' => $comment,
                    'attachments' => $attachments, 'source' => $source === 'api' ? 'api' : 'user', 'expectedVersion' => $version,
                ]);

                return ['state' => 'moved', 'approval' => null, 'row_version' => $version + 1];
            });
            $this->journal->processed($entry['id'], $current['id']);
            $status = DB::table($rt->table)->where('id', $current['id'])->value('status_id');

            return $result + ['status' => $this->workflow->statusPayload($wf, $status === null ? null : (int) $status)];
        } catch (RecordException $e) {
            $this->journal->rejected($entry['id'], $e->reason);
            throw $e;
        } catch (Throwable $e) {
            $this->journal->failed($entry['id'], $e);
            throw $e;
        }
    }

    /**
     * Runs the same checks as create/update (field access, rules, validation)
     * without writing anything; used to preview imports. Returns the errors by
     * field key, empty when the submission would be accepted. Uniqueness among
     * rows of the same batch is checked when the batch is committed.
     *
     * @param  array<string, mixed>  $input
     * @param  array{id: int, uuid: string, row_version: int, values: array<string, mixed>, system: array<string, mixed>}|null  $current
     * @return array<string, list<string>>
     */
    public function check(FormRuntime $rt, User $user, array $input, ?array $current): array
    {
        $mode = $current === null ? 'create' : 'edit';
        $this->guardForm($rt, $user, $mode);
        if ($current !== null) {
            $this->guardRow($rt, $user, 'edit', $current['id']);
        }
        $levels = $this->fieldAccess->resolve($user, $rt->form->id, $rt->form->uuid, $rt->definition, $mode, $current['system']['status_id'] ?? null);
        [$normalized, $errors] = $this->normalize($rt, $input);
        [$normalized, $rowErrors] = $this->guardRows($rt, $normalized, $current['values'] ?? null, $levels['fields']);
        $errors += $rowErrors + $this->rowPermissions($rt, $normalized, $current['values'] ?? null, $user);
        $base = $current === null ? $normalized : array_replace($current['values'], $normalized);
        $result = $this->rules->run($rt, $base, $current['values'] ?? null, $mode, $user, $current === null);
        $blocked = $this->blocked($rt, $levels['fields'], $result['state']);
        if (array_intersect_key($normalized, $blocked) !== []) {
            $kept = array_diff_key($normalized, $blocked);
            $result = $this->rules->run($rt, $current === null ? $kept : array_replace($current['values'], $kept), $current['values'] ?? null, $mode, $user, $current === null);
        }
        $errors += $this->accessViolations($rt, $input, $result['values'], $current['values'] ?? null, $levels['fields'], $result['state']);
        $ctx = $this->rules->context($rt, $mode, $user);
        $errors += $this->validator->validate($rt, $result['values'], $result['state'], $levels['fields'], $ctx, $current['id'] ?? null);

        return $errors;
    }

    public function delete(FormRuntime $rt, User $user, string $uuid, int $expectedVersion, string $key, ?array $justification = null, string $context = 'delete'): void
    {
        $onBehalf = $this->guardForm($rt, $user, 'delete');
        $current = $this->store->find($rt, $uuid) ?? throw new RecordException(404, 'not_found', __('records.not_found'));
        $entry = $this->journal->open($key, $rt->form->id, $rt->versionId, 'delete', 'ui', $user->id, [], $expectedVersion, $current['id']);
        if ($entry['state'] === 'processed') {
            return;
        }
        try {
            $onBehalf = $this->guardRow($rt, $user, 'delete', $current['id']) ?? $onBehalf;
            $this->claims->guard($rt, $current['id'], $user);
            if (DB::table($rt->table)->where('id', $current['id'])->value('legal_hold')) {
                throw new RecordException(423, 'legal_hold', __('records.legal_hold'));
            }
            if ($current['row_version'] !== $expectedVersion) {
                $levels = $this->fieldAccess->resolve($user, $rt->form->id, $rt->form->uuid, $rt->definition, 'edit', $current['system']['status_id'] ?? null);
                throw $this->conflict($rt, $current, $expectedVersion, [], $levels['fields']);
            }
            $validated = $this->justifications->enforce($rt, $user, 'delete', ['status' => $current['system']['status_id'] ?? null, 'values' => $current['values'], 'old' => $current['values']], $justification);
            DB::transaction(function () use ($rt, $user, $current, $expectedVersion, $validated, $onBehalf, $context): void {
                $justificationId = $validated === null ? null : $this->justifications->record($rt, $current['id'], $user, $context, $validated, [], 1, $onBehalf);
                $this->integrity->beforeDelete($rt, $current['id'], $user->id);
                if (! $this->store->softDelete($rt, $current['id'], $expectedVersion, $user->id)) {
                    $levels = $this->fieldAccess->resolve($user, $rt->form->id, $rt->form->uuid, $rt->definition, 'edit');
                    throw $this->conflict($rt, $this->store->find($rt, $current['uuid'], true) ?? $current, $expectedVersion, [], $levels['fields']);
                }
                // Open work on a deleted record ends: approvals, assignments, claims and SLA timers.
                app(ApprovalService::class)->cancelFor($rt, $current['id']);
                app(AssignmentService::class)->close($rt->form->id, $current['id'], 'cancelled', true);
                $this->claims->releaseAll($rt->form->id, $current['id'], 'admin');
                $this->sla->complete($rt->form->id, $current['id'], SlaTimers::nowUtc(), 'cancelled');
                $this->audit->record('record.deleted', 'data', null, 'record', $current['id'], ['form' => $rt->form->key, 'row_version' => $expectedVersion + 1], $user->id, null, $rt->form->id, $current['id'], $onBehalf, $justificationId);
                $this->outbox->publish('record.deleted', ['form' => $rt->form->uuid, 'record' => $current['uuid']]);
            });
            $this->journal->processed($entry['id'], $current['id']);
        } catch (RecordException $e) {
            $this->journal->rejected($entry['id'], $e->reason);
            throw $e;
        } catch (Throwable $e) {
            $this->journal->failed($entry['id'], $e);
            throw $e;
        }
    }

    public function restore(FormRuntime $rt, User $user, string $uuid, string $key, ?array $justification = null): void
    {
        $onBehalf = $this->guardForm($rt, $user, 'restore');
        $current = $this->store->find($rt, $uuid, true) ?? throw new RecordException(404, 'not_found', __('records.not_found'));
        $onBehalf = $this->guardRow($rt, $user, 'delete', $current['id']) ?? $onBehalf;
        if ($current['system']['deleted_at'] === null) {
            return;
        }
        $entry = $this->journal->open($key, $rt->form->id, $rt->versionId, 'restore', 'ui', $user->id, [], null, $current['id']);
        if ($entry['state'] === 'processed') {
            return;
        }
        try {
            $validated = $this->justifications->enforce($rt, $user, 'restore', ['status' => $current['system']['status_id'] ?? null, 'values' => $current['values'], 'old' => $current['values']], $justification);
            DB::transaction(function () use ($rt, $user, $current, $validated, $onBehalf): void {
                $justificationId = $validated === null ? null : $this->justifications->record($rt, $current['id'], $user, 'restore', $validated, [], 1, $onBehalf);
                $this->store->restore($rt, $current['id'], $user->id);
                $this->audit->record('record.restored', 'data', null, 'record', $current['id'], ['form' => $rt->form->key, 'row_version' => $current['row_version'] + 1], $user->id, null, $rt->form->id, $current['id'], $onBehalf, $justificationId);
                $this->outbox->publish('record.restored', ['form' => $rt->form->uuid, 'record' => $current['uuid']]);
            });
            $this->journal->processed($entry['id'], $current['id']);
        } catch (RecordException $e) {
            $this->journal->rejected($entry['id'], $e->reason);
            throw $e;
        }
    }

    /**
     * Form-level gate: published, application usable, permission and mode
     * allowed. Returns the delegator whose permission was needed, if any.
     */
    private function guardForm(FormRuntime $rt, User $user, string $ability): ?int
    {
        $form = $rt->form;
        if ($form->state === 'schema_inconsistent') {
            throw new RecordException(423, 'schema_inconsistent', __('forms.schema_inconsistent'));
        }
        if ($form->state !== 'published') {
            throw new RecordException(404, 'not_found', __('records.form_unavailable'));
        }
        $app = DB::table('applications')->where('id', $form->application_id)->first(['status', 'maintenance_mode', 'maintenance_until']);
        if ($app === null || $app->status !== 'active') {
            throw new RecordException(404, 'not_found', __('records.form_unavailable'));
        }
        if ($app->maintenance_mode && ($app->maintenance_until === null || Carbon::parse($app->maintenance_until, 'UTC')->isFuture()) && ! $this->access->allows($user, 'system.enable_maintenance_mode')) {
            throw new RecordException(423, 'maintenance', __('records.maintenance'));
        }
        $onBehalf = $this->permitted($rt, $user, ["form.{$form->uuid}.view", "form.{$form->uuid}.{$ability}"]);
        if ($onBehalf === false) {
            throw new RecordException(403, 'forbidden', __('records.forbidden'));
        }
        $modes = $rt->definition['form']['settings']['modes'] ?? [];
        if (in_array($ability, ['create', 'edit'], true) && ($modes[$ability] ?? true) === false) {
            throw new RecordException(403, 'mode_disabled', __('records.mode_disabled'));
        }
        if (PublishLockedRecords::locked($form->id)) {
            throw new RecordException(423, 'locked', __('forms.locked'));
        }

        return $onBehalf;
    }

    /**
     * Whether the user holds the permissions, themselves (null) or through
     * an active delegator for this form (the delegator's id); false when
     * neither does.
     *
     * @param  list<string>  $keys
     */
    public function permitted(FormRuntime $rt, User $user, array $keys): int|false|null
    {
        $all = fn (User $u): bool => array_reduce($keys, fn (bool $ok, string $k) => $ok && $this->access->allows($u, $k), true);
        if ($all($user)) {
            return null;
        }
        foreach ($this->delegations->principalsFor($user, $rt->form->id) as $p) {
            if ($p->id !== $user->id && $all($p)) {
                return $p->id;
            }
        }

        return false;
    }

    /**
     * Record-level scope (architecture §16.6): outside it the record does not
     * exist for the user (404). Returns the delegator whose scope was needed.
     */
    private function guardRow(FormRuntime $rt, User $user, string $op, int $id): ?int
    {
        if (! $this->scope->allows($rt, $user, $op, $id)) {
            throw new RecordException(404, 'not_found', __('records.not_found'));
        }

        return $this->scope->onBehalfOf($rt, $user, $op, $id);
    }

    /**
     * Changed fields as kept on a justification: keys with old and new values
     * (already masked for sensitive fields).
     *
     * @param  list<array{field_key: string, old: mixed, new: mixed}>  $diff
     * @return list<array{field: string, old: mixed, new: mixed}>
     */
    private function justified(array $diff): array
    {
        return array_map(static fn (array $d) => ['field' => $d['field_key'], 'old' => $d['old'], 'new' => $d['new']], $diff);
    }

    /** @return array{id: int, uuid: string}|null */
    private function replay(FormRuntime $rt, array $entry): ?array
    {
        if ($entry['state'] === 'foreign') {
            throw new RecordException(409, 'idempotency_key_reused', __('records.key_reused'));
        }
        if ($entry['state'] === 'processing') {
            throw new RecordException(409, 'in_progress', __('records.in_progress'));
        }
        if ($entry['state'] === 'processed' && $entry['record_id'] !== null) {
            $uuid = DB::table($rt->table)->where('id', $entry['record_id'])->value('uuid');

            return ['id' => $entry['record_id'], 'uuid' => strtolower((string) $uuid)];
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array{0: array<string, mixed>, 1: array<string, list<string>>}
     */
    private function normalize(FormRuntime $rt, array $input): array
    {
        $out = [];
        $errors = [];
        foreach ($input as $key => $raw) {
            $key = (string) $key;
            if (isset($rt->repeaterKeys[$key])) {
                $repUuid = $rt->repeaterKeys[$key];
                $rows = [];
                foreach (is_array($raw) ? array_values($raw) : [] as $i => $row) {
                    $clean = isset($row['uuid']) && is_string($row['uuid']) && preg_match(ValueCodec::UUID, $row['uuid']) === 1 ? ['uuid' => strtolower($row['uuid'])] : [];
                    foreach ($rt->rowFields($repUuid) as $f) {
                        if (! is_array($row) || ! array_key_exists($f['key'], $row)) {
                            continue;
                        }
                        try {
                            $clean[$f['key']] = $this->codec->normalize($rt, $f, $this->transform($f, $row[$f['key']]));
                        } catch (InvalidValue $e) {
                            $errors["{$key}.{$i}.{$f['key']}"][] = __($e->key);
                        }
                    }
                    $rows[] = $clean;
                }
                $out[$key] = $rows;

                continue;
            }
            $uuid = $rt->keys[$key] ?? null;
            if ($uuid === null) {
                $errors[$key][] = __('records.unknown_field');

                continue;
            }
            $f = $rt->fields[$uuid];
            if (! $rt->isStored($f)) {
                continue;
            }
            try {
                $out[$key] = $this->codec->normalize($rt, $f, $this->transform($f, $raw));
            } catch (InvalidValue $e) {
                $errors[$key][] = __($e->key, $e->params);
            }
        }

        return [$out, $errors];
    }

    /** Field transforms (specification §4.6 Behavior): trim, upper/lower case, collapse spaces. */
    private function transform(array $f, mixed $value): mixed
    {
        if (! is_string($value)) {
            return $value;
        }
        foreach ($f['behavior']['transforms'] ?? [] as $t) {
            $value = match ($t) {
                'trim' => Unicode::trim($value),
                'uppercase' => Unicode::upper($value),
                'lowercase' => Unicode::lower($value),
                'collapse_spaces' => (string) preg_replace('/\s+/u', ' ', $value),
                default => $value,
            };
        }

        return $value;
    }

    /**
     * Repeater rows: values of row fields the user may not edit (hidden or
     * read-only by access) keep their stored value; a submitted change to one
     * is reported.
     *
     * @param  array<string, mixed>  $normalized
     * @param  array<string, mixed>|null  $current
     * @param  array<string, string>  $levels
     * @return array{0: array<string, mixed>, 1: array<string, list<string>>}
     */
    private function guardRows(FormRuntime $rt, array $normalized, ?array $current, array $levels): array
    {
        $errors = [];
        foreach ($rt->repeaters as $repUuid => $rep) {
            $key = $rep['group']['key'];
            if (! isset($normalized[$key])) {
                continue;
            }
            $blocked = array_filter($rt->rowFields($repUuid), static fn (array $f) => in_array($levels[$f['uuid']] ?? 'editable', ['hidden', 'read_only'], true));
            if ($blocked === []) {
                continue;
            }
            $stored = [];
            foreach (($current[$key] ?? []) as $row) {
                $stored[$row['uuid'] ?? ''] = $row;
            }
            foreach ($normalized[$key] as $i => $row) {
                $before = $stored[$row['uuid'] ?? ''] ?? [];
                foreach ($blocked as $f) {
                    $old = $before[$f['key']] ?? null;
                    if (array_key_exists($f['key'], $row) && ! Canonical::same($row[$f['key']], $old)) {
                        $errors["{$key}.{$i}.{$f['key']}"][] = __('records.not_editable');
                    }
                    $normalized[$key][$i][$f['key']] = $old;
                }
            }
        }

        return [$normalized, $errors];
    }

    /**
     * Repeater row permissions (group `repeater.rowPermissions`): when a list
     * of roles is set for adding, removing or reordering rows, only holders of
     * one of those roles may do it.
     *
     * @param  array<string, mixed>  $normalized
     * @param  array<string, mixed>|null  $current
     * @return array<string, list<string>>
     */
    private function rowPermissions(FormRuntime $rt, array $normalized, ?array $current, User $user): array
    {
        $errors = [];
        $roles = null;
        foreach ($rt->repeaters as $rep) {
            $key = $rep['group']['key'];
            $rules = $rep['group']['repeater']['rowPermissions'] ?? [];
            if (! isset($normalized[$key]) || ! is_array($rules) || array_filter($rules) === []) {
                continue;
            }
            $roles ??= $user->activeRoles()->pluck('roles.uuid')->map(static fn ($u) => strtolower((string) $u))->all();
            $may = static fn (string $kind): bool => ($rules[$kind] ?? []) === [] || array_intersect(array_map('strtolower', $rules[$kind]), $roles) !== [];
            $stored = array_values(array_filter(array_map(static fn ($r) => $r['uuid'] ?? null, $current[$key] ?? [])));
            $submitted = array_map(static fn ($r) => $r['uuid'] ?? null, $normalized[$key]);
            $kept = array_values(array_filter($submitted, static fn ($u) => $u !== null && in_array($u, $stored, true)));
            if (! $may('add') && count($kept) < count($submitted)) {
                $errors[$key][] = __('records.rows.add_forbidden');
            }
            if (! $may('remove') && count(array_diff($stored, $kept)) > 0) {
                $errors[$key][] = __('records.rows.remove_forbidden');
            }
            if (! $may('reorder') && $kept !== array_values(array_intersect($stored, $kept))) {
                $errors[$key][] = __('records.rows.reorder_forbidden');
            }
        }

        return $errors;
    }

    /**
     * Keys of fields the user may not change: hidden or read-only by access,
     * read-only or disabled by a condition.
     *
     * @param  array<string, string>  $levels
     * @return array<string, true>
     */
    private function blocked(FormRuntime $rt, array $levels, RuleState $state): array
    {
        $out = [];
        foreach ($rt->mainFields() as $f) {
            $level = $levels[$f['uuid']] ?? 'editable';
            if (in_array($level, ['hidden', 'read_only'], true) || $state->flag($f['uuid'], 'readOnly') || $state->flag($f['uuid'], 'disabled')) {
                $out[$f['key']] = true;
            }
        }

        return $out;
    }

    /**
     * Rejects submitted changes to fields the user may not edit (access level
     * or condition): read-only, disabled and hidden fields keep their value.
     *
     * @param  array<string, mixed>  $input
     * @param  array<string, mixed>  $final
     * @param  array<string, mixed>|null  $current
     * @param  array<string, string>  $levels
     * @return array<string, list<string>>
     */
    private function accessViolations(FormRuntime $rt, array $input, array $final, ?array $current, array $levels, RuleState $state): array
    {
        $errors = [];
        foreach ($input as $key => $raw) {
            $uuid = $rt->keys[(string) $key] ?? null;
            if ($uuid === null) {
                continue;
            }
            $f = $rt->fields[$uuid];
            $type = $rt->type($f);
            if ($type === null || $type->calculated || $type->storage === 'auto_number') {
                continue;
            }
            $level = $levels[$uuid] ?? 'editable';
            $blocked = in_array($level, ['hidden', 'read_only'], true) || $state->flag($uuid, 'readOnly') || $state->flag($uuid, 'disabled');
            $before = $current === null ? null : ($current[$f['key']] ?? null);
            try {
                $submitted = $this->codec->normalize($rt, $f, $raw);
            } catch (InvalidValue) {
                continue;
            }
            if ($blocked && ! Canonical::same($submitted, $before) && ! Canonical::same($submitted, $final[$f['key']] ?? null)) {
                $errors[(string) $key][] = __('records.not_editable');
            }
        }

        return $errors;
    }

    /** @return array<string, mixed> */
    private function assignNumbers(FormRuntime $rt, array $values, array $current = []): array
    {
        foreach ($rt->mainFields() as $f) {
            if ($rt->type($f)?->storage !== 'auto_number' || ($current[$f['key']] ?? null) !== null) {
                continue;
            }
            $sequenceUuid = $f['behavior']['autoNumber'] ?? null;
            $sequence = $sequenceUuid === null ? null : NumberSequence::query()->where('uuid', $sequenceUuid)->first();
            if ($sequence !== null) {
                $values[$f['key']] = $this->numbers->next($sequence);
            }
        }

        return $values;
    }

    private function recordNumber(FormRuntime $rt): ?string
    {
        $id = $rt->form->numbering_sequence_id;
        $sequence = $id === null ? null : NumberSequence::query()->find($id);

        return $sequence === null ? null : $this->numbers->next($sequence);
    }

    /** Temporary uploads referenced by the record become owned by it. */
    private function attachFiles(FormRuntime $rt, int $recordId, array $values, User $user): void
    {
        $byField = [];
        foreach ($rt->mainFields() as $f) {
            if (in_array($rt->type($f)?->storage, ['file', 'files'], true)) {
                $byField[$f['uuid']] = array_filter((array) ($values[$f['key']] ?? []), 'is_string');
            }
        }
        foreach ($rt->repeaters as $repUuid => $rep) {
            foreach ($values[$rep['group']['key']] ?? [] as $row) {
                foreach ($rt->rowFields($repUuid) as $f) {
                    if (in_array($rt->type($f)?->storage, ['file', 'files'], true)) {
                        $byField[$f['uuid']] = [...($byField[$f['uuid']] ?? []), ...array_filter((array) ($row[$f['key']] ?? []), 'is_string')];
                    }
                }
            }
        }
        foreach ($byField as $fieldUuid => $uuids) {
            if ($uuids === []) {
                continue;
            }
            $fieldId = DB::table('fields')->where('uuid', $fieldUuid)->value('id');
            $files = StoredFile::query()->whereIn('uuid', $uuids)->get();
            foreach ($files as $file) {
                $mine = $file->is_temporary && $file->uploaded_by === $user->id;
                $already = $file->form_id === $rt->form->id && $file->record_id === $recordId;
                if (! $mine && ! $already) {
                    throw new RecordException(422, 'invalid', __('records.invalid'), ['errors' => [$rt->fields[$fieldUuid]['key'] => [__('records.rules.file_missing')]]]);
                }
                $file->forceFill(['is_temporary' => false, 'form_id' => $rt->form->id, 'record_id' => $recordId, 'field_id' => $fieldId, 'owner_type' => 'record', 'owner_id' => $recordId])->save();
            }
        }
    }

    /** Normalized text of the searchable fields (architecture §11.2 `search_text`). */
    private function searchText(FormRuntime $rt, array $values): ?string
    {
        $wanted = $rt->definition['form']['settings']['searchFields'] ?? [];
        $parts = [];
        foreach ($rt->mainFields() as $f) {
            $searchable = in_array($f['uuid'], $wanted, true) || ($f['table']['searchable'] ?? false);
            if (! $searchable || ($f['flags']['encrypted'] ?? false) || ($f['flags']['sensitive'] ?? false)) {
                continue;
            }
            $v = $values[$f['key']] ?? null;
            if ($v === null) {
                continue;
            }
            if ($rt->targetTable($f) !== null) {
                $v = implode(' ', $this->refs->titles($rt, $f, array_values(array_filter((array) $v, 'is_string'))));
            }
            $parts[] = is_array($v) ? implode(' ', array_filter(array_map(static fn ($x) => is_scalar($x) ? (string) $x : '', $v))) : (string) $v;
        }
        if ($parts === []) {
            return null;
        }

        return mb_substr(mb_strtolower(Unicode::normalizeArabic(implode(' ', $parts))), 0, 60000);
    }

    /**
     * Field-level diff for the audit log (track-changes fields only; sensitive values masked).
     *
     * @return list<array{field_key: string, old: mixed, new: mixed}>
     */
    private function diff(FormRuntime $rt, array $before, array $after): array
    {
        $out = [];
        foreach ($rt->mainFields() as $f) {
            if (! $rt->isStored($f) || ! ($f['flags']['trackChanges'] ?? true)) {
                continue;
            }
            $old = $before[$f['key']] ?? null;
            $new = $after[$f['key']] ?? null;
            if (Canonical::same($old, $new)) {
                continue;
            }
            $mask = ($f['flags']['sensitive'] ?? false) || ($f['flags']['encrypted'] ?? false);
            $out[] = ['field_key' => $f['key'], 'old' => $mask && $old !== null ? '«masked»' : $old, 'new' => $mask && $new !== null ? '«masked»' : $new];
        }
        foreach ($rt->repeaters as $rep) {
            $k = $rep['group']['key'];
            if (! Canonical::same($before[$k] ?? [], $after[$k] ?? [])) {
                $out[] = ['field_key' => $k, 'old' => count($before[$k] ?? []).' rows', 'new' => count($after[$k] ?? []).' rows'];
            }
        }

        return $out;
    }

    /**
     * 409 payload (architecture §19.1): which fields changed since the version
     * the user loaded, their base value, the other user's value and the
     * user's own, plus who changed the record and when. Hidden fields are
     * never included.
     *
     * @param  array<string, mixed>  $current
     * @param  array<string, mixed>  $input
     * @param  array<string, string>  $levels
     */
    private function conflict(FormRuntime $rt, array $current, int $expected, array $input, array $levels): RecordException
    {
        $entries = DB::table('audit_logs')->where('form_id', $rt->form->id)->where('record_id', $current['id'])
            ->whereIn('event', ['record.updated', 'record.deleted', 'record.restored'])->orderBy('id')->get(['changes', 'meta', 'actor_user_id', 'occurred_at']);
        $changed = [];
        $last = null;
        foreach ($entries as $e) {
            $meta = json_decode((string) $e->meta, true) ?: [];
            if ((int) ($meta['row_version'] ?? 0) <= $expected) {
                continue;
            }
            $last = $e;
            foreach (json_decode((string) $e->changes, true) ?: [] as $c) {
                $changed[$c['field_key']] ??= ['base' => $c['old']];
            }
        }
        $fields = [];
        foreach ($changed as $key => $info) {
            $uuid = $rt->keys[$key] ?? ($rt->repeaterKeys[$key] ?? null);
            if ($uuid !== null && ($levels[$uuid] ?? 'editable') === 'hidden') {
                continue;
            }
            $fields[] = ['field' => $key, 'base_value' => $info['base'], 'their_value' => $current['values'][$key] ?? null, 'your_value' => $input[$key] ?? null];
        }

        return new RecordException(409, 'conflict', __('records.conflict'), [
            'current_row_version' => $current['row_version'],
            'changed_fields' => $fields,
            'changed_by' => $last === null ? null : DB::table('users')->where('id', $last->actor_user_id)->value('name'),
            'changed_at' => $last === null ? null : Carbon::parse($last->occurred_at, 'UTC')->toIso8601ZuluString(),
        ]);
    }
}
