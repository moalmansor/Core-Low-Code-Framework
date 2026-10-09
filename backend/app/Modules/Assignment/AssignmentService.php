<?php

declare(strict_types=1);

namespace App\Modules\Assignment;

use App\Infrastructure\Database\Contracts\DatabaseDriver;
use App\Modules\Audit\AuditWriter;
use App\Modules\Core\Outbox\OutboxWriter;
use App\Modules\Forms\Conditions\OwnedConditions;
use App\Modules\Records\Runtime\FormRuntime;
use App\Modules\Records\Runtime\RuleRuntime;
use App\Modules\Reference\Models\BusinessCalendar;
use App\Modules\Reference\WorkingCalendars;
use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Record assignment (specification §4.25, architecture §19.4). When a record
 * is created or changes status, its open work assignments complete and the
 * first matching assignment rule of the form (for that transition, or "on
 * create") assigns it: to a user, role or department, to the user in a
 * field, to the creator's manager, round-robin within a role (row-locked
 * cursor) or to the least-loaded member of a role. Manual assignment and
 * reassignment go through `assign()`.
 */
final class AssignmentService
{
    public const STRATEGIES = ['user', 'role', 'department', 'field_user', 'creator_manager', 'round_robin', 'least_loaded'];

    public function __construct(
        private readonly Membership $members,
        private readonly OwnedConditions $conditions,
        private readonly RuleRuntime $rules,
        private readonly DatabaseDriver $driver,
        private readonly WorkingCalendars $calendars,
        private readonly AuditWriter $audit,
        private readonly OutboxWriter $outbox,
        private readonly Claims $claims,
    ) {}

    /**
     * Applies the assignment rules of a new record (`$transition` null) or of
     * the transition just performed. Runs inside the caller's transaction.
     *
     * @param  array<string, mixed>|null  $transition
     * @param  array<string, mixed>  $values
     */
    public function onStatusChange(FormRuntime $rt, int $recordId, ?array $transition, array $values, ?int $actor): void
    {
        if ($transition !== null) {
            $this->close($rt->form->id, $recordId, 'completed', false);
            $this->claims->releaseAll($rt->form->id, $recordId, 'completed');
        }
        if ($transition !== null && ($transition['id'] ?? null) === null) {
            return; // a rejection move has no transition and no rules
        }
        $q = DB::table('assignment_rules')->where('form_id', $rt->form->id)->orderBy('sort_order')->orderBy('id');
        $transition === null ? $q->whereNull('transition_id') : $q->where('transition_id', $transition['id']);
        foreach ($q->get() as $rule) {
            $ast = $this->conditions->ast($rule->condition_id === null ? null : (int) $rule->condition_id);
            if ($ast !== null && ! $this->rules->holds($rt, $ast, $values)) {
                continue;
            }
            $target = $this->resolve($rt, $rule, $recordId, $values);
            if ($target === null) {
                continue;
            }
            $due = $rule->due_in_minutes === null ? null : $this->due($rt, (int) $rule->due_in_minutes, (bool) $rule->use_working_time, $recordId);
            $this->create($rt, $recordId, $target[0], $target[1], [
                'assignment_rule_id' => (int) $rule->id, 'transition_id' => $transition['id'] ?? null,
                'priority' => (int) $rule->priority, 'due_at' => $due, 'assigned_by' => $actor,
            ], 'rule');

            return;
        }
    }

    /**
     * Manual assignment or reassignment of a record. Earlier active work
     * assignments become `reassigned` and their claims are released.
     *
     * @param  array{due_at?: string|null, priority?: int, justification?: int|null, on_behalf_of?: int|null}  $options
     */
    public function assign(FormRuntime $rt, int $recordId, string $type, int $id, int $by, array $options = []): string
    {
        $reassign = $this->hasActive($rt->form->id, $recordId);
        $this->close($rt->form->id, $recordId, 'reassigned', false);
        $this->claims->releaseAll($rt->form->id, $recordId, 'reassigned');

        return $this->create($rt, $recordId, $type, $id, [
            'priority' => $options['priority'] ?? 0, 'due_at' => $options['due_at'] ?? null, 'assigned_by' => $by,
            'on_behalf_of_user_id' => $options['on_behalf_of'] ?? null, 'justification_id' => $options['justification'] ?? null,
        ], $reassign ? 'reassign' : 'manual');
    }

    /**
     * Reassignment by an SLA escalation: to the given subject.
     *
     * @param  array<string, mixed>  $target  {type, uuid}
     */
    public function reassignBySystem(FormRuntime $rt, int $recordId, array $target, string $source): void
    {
        $id = $this->members->idOf((string) ($target['type'] ?? ''), $target['uuid'] ?? null);
        if ($id === null) {
            return;
        }
        $this->close($rt->form->id, $recordId, 'reassigned', false);
        $this->claims->releaseAll($rt->form->id, $recordId, 'reassigned');
        $this->create($rt, $recordId, (string) $target['type'], $id, ['assigned_by' => null], $source);
    }

    /**
     * Users (uuids) behind notification targets: subjects, the record's
     * current assignees and its owner.
     *
     * @param  list<array<string, mixed>>  $targets
     * @return list<string>
     */
    public function recipients(FormRuntime $rt, int $recordId, array $targets): array
    {
        $ids = [];
        foreach ($targets as $t) {
            $type = (string) ($t['type'] ?? '');
            if ($type === 'owner') {
                $ids[] = (int) DB::table($rt->table)->where('id', $recordId)->value('owner_user_id');
            } elseif ($type === 'assignee') {
                foreach (DB::table('assignments')->where('form_id', $rt->form->id)->where('record_id', $recordId)->where('status', 'active')->get(['assignee_type', 'assignee_id']) as $a) {
                    array_push($ids, ...$this->members->userIds($a->assignee_type, (int) $a->assignee_id));
                }
            } elseif (($id = $this->members->idOf($type, $t['uuid'] ?? null)) !== null) {
                array_push($ids, ...$this->members->userIds($type, $id));
            }
        }
        $ids = array_values(array_unique(array_filter($ids)));

        return $ids === [] ? [] : DB::table('users')->whereIn('id', $ids)->orderBy('id')->pluck('uuid')->map(static fn ($u) => strtolower((string) $u))->all();
    }

    /** Whether the record has an active work assignment (approvals aside). */
    public function hasActive(int $formId, int $recordId): bool
    {
        return DB::table('assignments')->where('form_id', $formId)->where('record_id', $recordId)->where('status', 'active')->whereNull('approval_request_id')->exists();
    }

    /** Completes (or cancels) the record's active assignments; approval assignments only when asked. */
    public function close(int $formId, int $recordId, string $status, bool $withApprovals): void
    {
        $q = DB::table('assignments')->where('form_id', $formId)->where('record_id', $recordId)->where('status', 'active');
        if (! $withApprovals) {
            $q->whereNull('approval_request_id');
        }
        $now = Carbon::now('UTC')->format('Y-m-d H:i:s.u');
        $q->update(['status' => $status, 'completed_at' => $now, 'updated_at' => $now]);
    }

    /**
     * Inserts an assignment, audits and announces it. Returns its uuid.
     *
     * @param  array<string, mixed>  $extra
     */
    public function create(FormRuntime $rt, int $recordId, string $type, int $id, array $extra, string $source): string
    {
        $now = Carbon::now('UTC')->format('Y-m-d H:i:s.u');
        $uuid = (string) Str::uuid7();
        DB::table('assignments')->insert([
            'uuid' => $uuid, 'organization_id' => $rt->form->organization_id, 'created_at' => $now, 'updated_at' => $now,
            'form_id' => $rt->form->id, 'record_id' => $recordId, 'assignee_type' => $type, 'assignee_id' => $id,
            'assignment_rule_id' => $extra['assignment_rule_id'] ?? null, 'transition_id' => $extra['transition_id'] ?? null,
            'approval_request_id' => $extra['approval_request_id'] ?? null, 'status' => 'active', 'priority' => (int) ($extra['priority'] ?? 0),
            'due_at' => $extra['due_at'] ?? null, 'assigned_by' => $extra['assigned_by'] ?? null,
            'on_behalf_of_user_id' => $extra['on_behalf_of_user_id'] ?? null, 'justification_id' => $extra['justification_id'] ?? null,
            'completed_at' => null,
        ]);
        $assignee = ['type' => $type, 'uuid' => $this->members->uuidOf($type, $id)];
        $recordUuid = strtolower((string) DB::table($rt->table)->where('id', $recordId)->value('uuid'));
        $this->audit->record($source === 'reassign' ? 'record.reassigned' : 'record.assigned', 'workflow', null, 'record', $recordId, [
            'form' => $rt->form->key, 'assignment' => $uuid, 'assignee' => $assignee, 'source' => $source,
        ], $extra['assigned_by'] ?? null, null, $rt->form->id, $recordId, $extra['on_behalf_of_user_id'] ?? null, $extra['justification_id'] ?? null);
        $this->outbox->publish('record.assigned', [
            'form' => $rt->form->uuid, 'record' => $recordUuid, 'assignment' => $uuid, 'assignee' => $assignee, 'source' => $source,
            'recipients' => array_map(fn (int $u) => $this->members->uuidOf('user', $u), $this->members->userIds($type, $id)),
        ]);

        return $uuid;
    }

    /**
     * The subject a rule assigns to, or null when it cannot (empty field,
     * no manager, no role member).
     *
     * @param  array<string, mixed>  $values
     * @return array{0: string, 1: int}|null
     */
    private function resolve(FormRuntime $rt, object $rule, int $recordId, array $values): ?array
    {
        switch ($rule->strategy) {
            case 'user':
            case 'role':
            case 'department':
                return $rule->target_id === null ? null : [$rule->strategy, (int) $rule->target_id];
            case 'field_user':
                $uuid = DB::table('fields')->where('id', $rule->field_id)->value('uuid');
                $field = $uuid === null ? null : ($rt->fields[strtolower((string) $uuid)] ?? null);
                $value = $field === null ? null : ($values[$field['key']] ?? null);
                $value = is_array($value) ? ($value[0] ?? null) : $value;
                $id = is_string($value) ? DB::table('users')->where('uuid', $value)->where('status', 'active')->whereNull('deleted_at')->value('id') : null;

                return $id === null ? null : ['user', (int) $id];
            case 'creator_manager':
                $creator = DB::table($rt->table)->where('id', $recordId)->value('created_by');
                $manager = $creator === null ? null : DB::table('users')->where('id', $creator)->value('manager_id');
                $active = $manager !== null && DB::table('users')->where('id', $manager)->where('status', 'active')->whereNull('deleted_at')->exists();

                return $active ? ['user', (int) $manager] : null;
            case 'round_robin':
                // The rule row is locked for the rest of the transaction, so concurrent records take turns.
                $locked = $this->driver->lockForUpdate(DB::table('assignment_rules')->where('id', $rule->id))->first();
                $candidates = $this->members->userIds('role', (int) $rule->target_id);
                if ($candidates === [] || $locked === null) {
                    return null;
                }
                $cursor = $locked->round_robin_cursor_user_id === null ? null : (int) $locked->round_robin_cursor_user_id;
                $next = $candidates[0];
                foreach ($candidates as $c) {
                    if ($cursor !== null && $c > $cursor) {
                        $next = $c;
                        break;
                    }
                }
                DB::table('assignment_rules')->where('id', $rule->id)->update(['round_robin_cursor_user_id' => $next]);

                return ['user', $next];
            case 'least_loaded':
                $candidates = $this->members->userIds('role', (int) $rule->target_id);
                if ($candidates === []) {
                    return null;
                }
                $load = DB::table('assignments')->where('assignee_type', 'user')->whereIn('assignee_id', $candidates)->where('status', 'active')
                    ->groupBy('assignee_id')->select(['assignee_id', DB::raw('count(*) as n')])->pluck('n', 'assignee_id')->all();
                usort($candidates, static fn (int $a, int $b) => [(int) ($load[$a] ?? 0), $a] <=> [(int) ($load[$b] ?? 0), $b]);

                return ['user', $candidates[0]];
        }

        return null;
    }

    private function due(FormRuntime $rt, int $minutes, bool $workingTime, int $recordId): string
    {
        $now = new DateTimeImmutable('now', new DateTimeZone('UTC'));
        if ($workingTime) {
            $department = DB::table($rt->table)->where('id', $recordId)->value('owner_department_id');
            $id = $rt->form->business_calendar_id
                ?? ($department === null ? null : DB::table('departments')->where('id', $department)->value('business_calendar_id'))
                ?? DB::table('business_calendars')->where('organization_id', $rt->form->organization_id)->where('is_default', true)->value('id');
            $calendar = $id === null ? null : BusinessCalendar::query()->find($id);
            if ($calendar !== null) {
                return $this->calendars->addWorkingMinutes($calendar, $now, $minutes)->format('Y-m-d H:i:s.u');
            }
        }

        return $now->modify("+{$minutes} minutes")->format('Y-m-d H:i:s.u');
    }
}
