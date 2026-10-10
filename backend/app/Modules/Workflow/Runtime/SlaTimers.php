<?php

declare(strict_types=1);

namespace App\Modules\Workflow\Runtime;

use App\Infrastructure\Database\Contracts\DatabaseDriver;
use App\Modules\Assignment\AssignmentService;
use App\Modules\Audit\AuditWriter;
use App\Modules\Core\Outbox\OutboxWriter;
use App\Modules\Forms\Models\Form;
use App\Modules\Records\Runtime\FormRuntime;
use App\Modules\Records\Runtime\FormRuntimes;
use App\Modules\Records\Runtime\RuleRuntime;
use App\Modules\Reference\Models\BusinessCalendar;
use App\Modules\Reference\WorkingCalendars;
use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * SLA timers (specification §4.12, architecture §19.2): one timer per SLA
 * rule of the status a record enters; the deadline counts working time when
 * the rule says so. The scheduler's tick (every minute) warns, marks
 * breaches and runs the escalations in order: notify (an outbox event and an
 * audit entry for the notifications module), reassign, or an automatic
 * transition through the workflow engine (`source = sla_escalation`).
 */
final class SlaTimers
{
    public function __construct(
        private readonly WorkingCalendars $calendars,
        private readonly RuleRuntime $rules,
        private readonly OutboxWriter $outbox,
        private readonly AuditWriter $audit,
        private readonly DatabaseDriver $driver,
        private readonly FormRuntimes $runtimes,
    ) {}

    /**
     * Starts the timers of the status a record just entered.
     *
     * @param  array<string, mixed>  $values  record values (rule conditions)
     */
    public function start(FormRuntime $rt, WorkflowRuntime $wf, int $recordId, int $statusId, int $historyId, array $values, ?int $ownerDepartmentId, DateTimeImmutable $now): void
    {
        $uuid = $wf->statusUuid[$statusId] ?? null;
        foreach ($wf->sla[$uuid] ?? [] as $rule) {
            if (($rule['condition'] ?? null) !== null && ! $this->rules->holds($rt, $rule['condition'], $values)) {
                continue;
            }
            $calendar = $rule['workingTime'] ?? false ? $this->calendar($rt, $rule, $ownerDepartmentId) : null;
            $due = $this->add($calendar, $now, (int) $rule['durationMinutes']);
            $warnAt = isset($rule['warnBeforeMinutes']) ? $this->add($calendar, $now, (int) $rule['durationMinutes'] - (int) $rule['warnBeforeMinutes']) : null;
            $at = $now->format('Y-m-d H:i:s.u');
            DB::table('sla_timers')->insert([
                'organization_id' => $rt->form->organization_id, 'created_at' => $at, 'updated_at' => $at,
                'form_id' => $rt->form->id, 'record_id' => $recordId, 'sla_rule_id' => $rule['id'], 'status_history_id' => $historyId,
                'started_at' => $at, 'due_at' => $due->format('Y-m-d H:i:s.u'), 'warned_at' => null, 'breached_at' => null,
                'escalation_level' => 0, 'next_check_at' => ($warnAt ?? $due)->format('Y-m-d H:i:s.u'), 'state' => 'running', 'completed_at' => null,
            ]);
        }
    }

    /** Completes the running timers of a record (it left the status, or was deleted). */
    public function complete(int $formId, int $recordId, DateTimeImmutable $now, string $state = 'completed'): void
    {
        $at = $now->format('Y-m-d H:i:s.u');
        DB::table('sla_timers')->where('form_id', $formId)->where('record_id', $recordId)->whereIn('state', ['running', 'warned', 'breached'])
            ->update(['state' => $state, 'completed_at' => $at, 'updated_at' => $at]);
    }

    /**
     * Working seconds between two instants in the calendar that applies to
     * the record, or null when the form has no calendar.
     */
    public function workingSeconds(FormRuntime $rt, ?int $ownerDepartmentId, DateTimeImmutable $from, DateTimeImmutable $to): ?int
    {
        $calendar = $this->calendar($rt, [], $ownerDepartmentId);

        return $calendar === null ? null : $this->calendars->workingMinutesBetween($calendar, $from, $to) * 60;
    }

    /**
     * One scheduler tick: processes the timers due for a check, skipping rows
     * another worker holds. Returns the number processed.
     */
    public function tick(?DateTimeImmutable $now = null, int $limit = 200): int
    {
        $now ??= new DateTimeImmutable('now', new DateTimeZone('UTC'));
        $at = $now->format('Y-m-d H:i:s.u');
        $processed = 0;
        $ids = DB::table('sla_timers')->whereIn('state', ['running', 'warned', 'breached'])->where('next_check_at', '<=', $at)
            ->orderBy('next_check_at')->limit($limit)->pluck('id')->all();
        foreach ($ids as $id) {
            try {
                DB::transaction(function () use ($id, $now, &$processed): void {
                    $timer = $this->driver->skipLocked($this->driver->lockForUpdate(DB::table('sla_timers')->where('id', $id)))->first();
                    if ($timer === null || ! in_array($timer->state, ['running', 'warned', 'breached'], true)) {
                        return;
                    }
                    $this->process($timer, $now);
                    $processed++;
                });
            } catch (Throwable $e) {
                report($e);
            }
        }

        return $processed;
    }

    private function process(object $timer, DateTimeImmutable $now): void
    {
        $form = Form::query()->find($timer->form_id);
        $rt = $form === null ? null : $this->runtimes->forForm($form);
        $rule = DB::table('sla_rules')->where('id', $timer->sla_rule_id)->first();
        if ($rt === null || $rule === null) {
            $this->finish($timer, $now, 'cancelled');

            return;
        }
        $record = DB::table($rt->table)->where('id', $timer->record_id)->first(['id', 'uuid', 'status_id', 'deleted_at', 'owner_department_id']);
        $history = DB::table('status_history')->where('id', $timer->status_history_id)->value('to_status_id');
        if ($record === null || $record->deleted_at !== null || (int) $record->status_id !== (int) $history) {
            $this->finish($timer, $now, $record !== null && $record->deleted_at === null ? 'completed' : 'cancelled');

            return;
        }
        $wf = WorkflowRuntime::for($rt);
        $definition = null;
        foreach ($wf->sla as $list) {
            foreach ($list as $r) {
                if ($r['id'] === (int) $rule->id) {
                    $definition = $r;
                }
            }
        }
        // A rule removed by a later version: the timer runs out without escalations.
        $definition ??= ['uuid' => strtolower((string) $rule->uuid), 'durationMinutes' => (int) $rule->duration_minutes, 'warnBeforeMinutes' => $rule->warn_before_minutes, 'workingTime' => (bool) $rule->use_working_time, 'calendar' => null, 'escalations' => []];
        $calendar = ($definition['workingTime'] ?? false) ? $this->calendar($rt, $definition, $record->owner_department_id === null ? null : (int) $record->owner_department_id) : null;
        $due = new DateTimeImmutable((string) $timer->due_at, new DateTimeZone('UTC'));
        $update = ['updated_at' => $now->format('Y-m-d H:i:s.u')];
        $meta = ['form' => $rt->form->uuid, 'record' => strtolower((string) $record->uuid), 'rule' => $definition['uuid'], 'due_at' => $due->format(DATE_ATOM)];
        $state = $timer->state;
        if ($timer->warned_at === null && $state === 'running' && $now < $due) {
            $update += ['warned_at' => $now->format('Y-m-d H:i:s.u'), 'state' => 'warned'];
            $state = 'warned';
            $this->outbox->publish('sla.warning', $meta);
            $this->audit->record('sla.warning', 'workflow', null, 'record', (int) $record->id, $meta, null, null, $rt->form->id, (int) $record->id);
        }
        $level = (int) $timer->escalation_level;
        $escalations = array_values($definition['escalations'] ?? []);
        usort($escalations, static fn (array $a, array $b) => ($a['afterMinutes'] ?? 0) <=> ($b['afterMinutes'] ?? 0));
        if ($now >= $due) {
            if ($timer->breached_at === null) {
                $update += ['breached_at' => $now->format('Y-m-d H:i:s.u')];
                $this->outbox->publish('sla.breached', $meta);
                $this->audit->record('sla.breached', 'workflow', null, 'record', (int) $record->id, $meta, null, null, $rt->form->id, (int) $record->id);
            }
            $update['state'] = 'breached';
            $state = 'breached';
            while ($level < count($escalations) && $now >= $this->add($calendar, $due, (int) ($escalations[$level]['afterMinutes'] ?? 0))) {
                $this->escalate($rt, $wf, $escalations[$level], (int) $record->id, $meta + ['level' => $level + 1], $now);
                $level++;
                if (DB::table($rt->table)->where('id', $record->id)->value('status_id') != $record->status_id) {
                    // The escalation moved the record: this status' timers are done.
                    break;
                }
            }
            $update['escalation_level'] = $level;
        }
        $next = match (true) {
            $state !== 'breached' => $due,
            $level < count($escalations) => $this->add($calendar, $due, (int) ($escalations[$level]['afterMinutes'] ?? 0)),
            default => null,
        };
        $current = DB::table('sla_timers')->where('id', $timer->id)->value('state');
        if (! in_array($current, ['running', 'warned', 'breached'], true)) {
            return; // completed by an escalating transition
        }
        // Breached without further escalations: stays breached (visible in My Work) until the record moves.
        $update['next_check_at'] = ($next ?? $now->modify('+100 years'))->format('Y-m-d H:i:s.u');
        DB::table('sla_timers')->where('id', $timer->id)->update($update);
    }

    /**
     * @param  array<string, mixed>  $escalation
     * @param  array<string, mixed>  $meta
     */
    private function escalate(FormRuntime $rt, WorkflowRuntime $wf, array $escalation, int $recordId, array $meta, DateTimeImmutable $now): void
    {
        $params = $escalation['params'] ?? [];
        $meta += ['action' => $escalation['action']];
        switch ($escalation['action']) {
            case 'notify':
                $meta['recipients'] = app(AssignmentService::class)->recipients($rt, $recordId, $params['to'] ?? []);
                $this->outbox->publish('sla.escalation', $meta);
                break;
            case 'reassign':
                app(AssignmentService::class)->reassignBySystem($rt, $recordId, $params['to'] ?? [], 'sla_escalation');
                break;
            case 'transition':
                $transition = $wf->transitions[$params['transition'] ?? ''] ?? null;
                if ($transition !== null) {
                    app(WorkflowEngine::class)->performBySystem($rt, $recordId, $transition, 'sla_escalation');
                }
                break;
        }
        $this->audit->record('sla.escalated', 'workflow', null, 'record', $recordId, $meta, null, null, $rt->form->id, $recordId);
    }

    private function finish(object $timer, DateTimeImmutable $now, string $state): void
    {
        DB::table('sla_timers')->where('id', $timer->id)->update(['state' => $state, 'completed_at' => $now->format('Y-m-d H:i:s.u'), 'updated_at' => $now->format('Y-m-d H:i:s.u')]);
    }

    private function add(?BusinessCalendar $calendar, DateTimeImmutable $from, int $minutes): DateTimeImmutable
    {
        if ($minutes <= 0) {
            return $from;
        }

        return $calendar === null ? $from->modify("+{$minutes} minutes") : $this->calendars->addWorkingMinutes($calendar, $from, $minutes);
    }

    /**
     * The calendar of a rule: its own, else the form's, else the owning
     * department's, else the organization default.
     *
     * @param  array<string, mixed>  $rule
     */
    private function calendar(FormRuntime $rt, array $rule, ?int $departmentId): ?BusinessCalendar
    {
        $id = null;
        if (($rule['calendar'] ?? null) !== null) {
            $id = DB::table('business_calendars')->where('uuid', $rule['calendar'])->value('id');
        }
        $id ??= $rt->form->business_calendar_id;
        $id ??= $departmentId === null ? null : DB::table('departments')->where('id', $departmentId)->value('business_calendar_id');
        $id ??= DB::table('business_calendars')->where('organization_id', $rt->form->organization_id)->where('is_default', true)->value('id');

        return $id === null ? null : BusinessCalendar::query()->find($id);
    }

    public static function nowUtc(): DateTimeImmutable
    {
        return new DateTimeImmutable(Carbon::now('UTC')->format('Y-m-d H:i:s.u'), new DateTimeZone('UTC'));
    }
}
