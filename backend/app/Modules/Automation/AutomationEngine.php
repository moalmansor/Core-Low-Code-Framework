<?php

declare(strict_types=1);

namespace App\Modules\Automation;

use App\Modules\Actions\ActionRunner;
use App\Modules\Actions\ActionSteps;
use App\Modules\Actions\StepFailed;
use App\Modules\Actions\StepOrigin;
use App\Modules\Assignment\Membership;
use App\Modules\Audit\AuditWriter;
use App\Modules\Automation\Jobs\ExecuteAutomationRun;
use App\Modules\Core\Correlation\CorrelationId;
use App\Modules\Core\I18n\Translator;
use App\Modules\Core\Outbox\OutboxWriter;
use App\Modules\Core\Tenancy\TenantContext;
use App\Modules\Downloads\DownloadJobs;
use App\Modules\Forms\Conditions\OwnedConditions;
use App\Modules\Forms\Models\Form;
use App\Modules\Identity\Models\User;
use App\Modules\Records\Runtime\FormRuntime;
use App\Modules\Records\Runtime\FormRuntimes;
use App\Modules\Records\Runtime\RecordException;
use App\Modules\Records\Runtime\RecordStore;
use App\Modules\Records\Runtime\RuleRuntime;
use App\Support\BatchMemo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Runs automations (specification §4.31, architecture §19.9, ADR-0043).
 *
 * A record event, a date reached or a file in a watched folder starts one
 * run for one record; a schedule or a manual run covers every record that
 * meets the condition, up to the automation's maximum, waiting for
 * confirmation above its threshold. A run executes as its run-as user, so
 * every save obeys that user's permissions, record rules and field access.
 * The events its steps cause carry the run, and a run started that way is
 * one level deeper: past the automation's maximum chain depth it is recorded
 * as skipped (loop protection). Waits park the run until it is due; failed
 * steps marked "retry" are tried again on the retry policy's schedule. A
 * test run executes against one sample record inside a transaction that is
 * rolled back, and only describes the steps that reach outside the database.
 */
final class AutomationEngine
{
    private const LOG_LIMIT = 300;

    private const WAIT_POLL_MINUTES = 1;

    private const BUSY_RETRY_SECONDS = 15;

    private const STALE_MINUTES = 60;

    private const SLICE_SECONDS = 50;

    public function __construct(
        private readonly FormRuntimes $runtimes,
        private readonly RecordStore $store,
        private readonly RuleRuntime $rules,
        private readonly OwnedConditions $conditions,
        private readonly ActionSteps $steps,
        private readonly OutboxWriter $outbox,
        private readonly DownloadJobs $downloads,
        private readonly Membership $members,
        private readonly AuditWriter $audit,
        private readonly TenantContext $tenant,
        private readonly CorrelationId $correlation,
        private readonly Translator $translator,
    ) {}

    // ----------------------------------------------------------------- intake

    /**
     * A committed record event (through the outbox): the automations of the
     * record's form whose triggers match start a run for the record.
     *
     * @param  array<string, mixed>  $p
     */
    public function onEvent(string $eventType, array $p): void
    {
        $form = is_string($p['form'] ?? null) && Str::isUuid($p['form']) ? Form::query()->where('uuid', strtolower($p['form']))->first() : null;
        $rt = $form === null ? null : $this->runtimes->forForm($form);
        if ($rt === null || ! is_string($p['record'] ?? null)) {
            return;
        }
        $record = $this->store->find($rt, (string) $p['record'], $eventType === 'record.deleted');
        if ($record === null) {
            return;
        }
        $parent = is_array($p['_context'] ?? null) && isset($p['_context']['automation_run']) ? ['run' => (int) $p['_context']['automation_run'], 'depth' => (int) ($p['_context']['depth'] ?? 0)] : null;
        $actor = isset($p['actor']) ? (int) $p['actor'] : null;
        $triggers = DB::table('automation_triggers')->join('automations', 'automations.id', '=', 'automation_triggers.automation_id')
            ->where('automations.form_id', $form->id)->where('automations.is_enabled', true)
            ->whereIn('automation_triggers.type', match ($eventType) {
                'record.saved' => ['record_created', 'record_updated', 'field_changed', 'condition_true'],
                'record.transitioned' => ['status_changed'],
                'record.deleted' => ['record_deleted'],
                default => ['-'],
            })->orderBy('automation_triggers.id')->get(['automation_triggers.*']);
        $started = [];
        foreach ($triggers as $t) {
            if (isset($started[$t->automation_id]) || ! $this->matches($t, $eventType, $p, $rt)) {
                continue;
            }
            $started[$t->automation_id] = true;
            $automation = DB::table('automations')->where('id', $t->automation_id)->first();
            if ($automation !== null) {
                $this->start($automation, (string) $t->type, ['record' => $record['uuid']], [$record['id']], $actor, $parent);
                DB::table('automation_triggers')->where('id', $t->id)->update(['last_fired_at' => Carbon::now('UTC')->format('Y-m-d H:i:s.u')]);
            }
        }
    }

    /**
     * Notes during a save which automations' conditions became true (held
     * after the save, not before), for their "condition became true" trigger.
     *
     * @param  array<string, mixed>|null  $old
     * @param  array<string, mixed>  $new
     * @return array<string, mixed>
     */
    public function conditionsMet(FormRuntime $rt, ?array $old, array $new): array
    {
        $met = [];
        foreach (self::conditionAutomations($rt->form->id) as $a) {
            $ast = $this->conditions->ast((int) $a->condition_id);
            if ($ast !== null && $this->rules->holds($rt, $ast, $new, null, 'view') && ($old === null || ! $this->rules->holds($rt, $ast, $old, null, 'view'))) {
                $met[] = strtolower((string) $a->uuid);
            }
        }

        return $met === [] ? [] : ['automation_conditions' => $met];
    }

    /**
     * The enabled automations of a form started by "condition became true".
     *
     * @return Collection<int, \stdClass>
     */
    public static function conditionAutomations(int $formId): Collection
    {
        return app(BatchMemo::class)->remember('automations.condition_true.'.$formId, static fn () => DB::table('automations')
            ->join('automation_triggers', 'automation_triggers.automation_id', '=', 'automations.id')
            ->where('automations.form_id', $formId)->where('automations.is_enabled', true)->where('automation_triggers.type', 'condition_true')
            ->whereNotNull('automations.condition_id')->distinct()->get(['automations.uuid', 'automations.condition_id']));
    }

    /** Whether any enabled automation of the form could start from a record event (so the event is worth queueing). */
    public static function formListens(string $formUuid): bool
    {
        return app(BatchMemo::class)->remember('automations.listens.'.strtolower($formUuid), static fn () => DB::table('automations')
            ->join('forms', 'forms.id', '=', 'automations.form_id')->where('forms.uuid', strtolower($formUuid))
            ->where('automations.is_enabled', true)->exists());
    }

    /** The scheduler's handler for a schedule trigger: one run over the records that meet the condition. */
    public function scheduled(object $task): void
    {
        $t = DB::table('automation_triggers')->where('id', $task->owner_id)->first();
        $a = $t === null ? null : DB::table('automations')->where('id', $t->automation_id)->first();
        if ($t === null || $a === null || ! $a->is_enabled) {
            return;
        }
        $this->tenant->set((int) $a->organization_id);
        $this->startSet($a, 'schedule', ['at' => Carbon::now('UTC')->toIso8601ZuluString()], null);
        DB::table('automation_triggers')->where('id', $t->id)->update(['last_fired_at' => Carbon::now('UTC')->format('Y-m-d H:i:s.u')]);
    }

    /** A file from a watched folder became a record: its run. */
    public function fileArrived(object $a, string $recordUuid, int $recordId, string $file): void
    {
        $this->start($a, 'watched_folder', ['record' => $recordUuid, 'file' => mb_substr($file, 0, 255)], [$recordId], null, null);
    }

    /** An inbound webhook created or updated a record: the runs of the automations listening to that endpoint. */
    public function inboundArrived(int $endpointId, string $recordUuid, int $recordId, string $delivery): void
    {
        $triggers = DB::table('automation_triggers')->join('automations', 'automations.id', '=', 'automation_triggers.automation_id')
            ->where('automation_triggers.type', 'inbound_webhook')->where('automation_triggers.inbound_endpoint_id', $endpointId)
            ->where('automations.is_enabled', true)->orderBy('automation_triggers.id')->get(['automation_triggers.*']);
        $started = [];
        foreach ($triggers as $t) {
            $a = isset($started[$t->automation_id]) ? null : DB::table('automations')->where('id', $t->automation_id)->first();
            if ($a === null) {
                continue;
            }
            $started[$t->automation_id] = true;
            $this->start($a, 'inbound_webhook', ['record' => $recordUuid, 'delivery' => $delivery], [$recordId], null, null);
            DB::table('automation_triggers')->where('id', $t->id)->update(['last_fired_at' => Carbon::now('UTC')->format('Y-m-d H:i:s.u')]);
        }
    }

    /** "Run now": a manual run over the records that meet the condition, started by an admin. */
    public function runNow(object $a, User $user): object
    {
        if (! DB::table('automation_triggers')->where('automation_id', $a->id)->where('type', 'manual')->exists()) {
            throw ValidationException::withMessages(['automation' => __('automations.no_manual_trigger')]);
        }
        if (! $a->is_enabled) {
            throw ValidationException::withMessages(['automation' => __('automations.disabled')]);
        }
        $run = $this->startSet($a, 'manual', ['by' => strtolower((string) $user->uuid)], $user->id);
        $this->audit->record('automation.run_manually', 'operations', null, 'automation', (int) $a->id, ['key' => $a->key, 'run' => $run->uuid], $user->id, null, (int) $a->form_id);

        return $run;
    }

    /**
     * A test run against one sample record: everything runs and is rolled
     * back; messages, webhooks, documents and downloads are only described.
     */
    public function test(object $a, string $recordUuid, User $user): object
    {
        $rt = $this->runtime($a) ?? throw ValidationException::withMessages(['automation' => __('automations.publish_first')]);
        $record = Str::isUuid($recordUuid) ? $this->store->find($rt, strtolower($recordUuid)) : null;
        if ($record === null) {
            throw ValidationException::withMessages(['record' => __('records.not_found')]);
        }
        $run = $this->insertRun($a, 'manual', ['record' => $record['uuid'], 'test' => true], [$record['id']], $user->id, null, 'queued', true);
        $this->execute((int) $run->id);
        $this->audit->record('automation.tested', 'operations', null, 'automation', (int) $a->id, ['key' => $a->key, 'run' => $run->uuid], $user->id, null, (int) $a->form_id);

        return DB::table('automation_runs')->where('id', $run->id)->first() ?? $run;
    }

    // ------------------------------------------------------------- run control

    public function confirm(object $run, User $user): void
    {
        $n = DB::table('automation_runs')->where('id', $run->id)->where('status', 'awaiting_confirmation')
            ->update(['status' => 'queued', 'updated_at' => Carbon::now('UTC')->format('Y-m-d H:i:s.u'), 'triggered_by' => $run->triggered_by ?? $user->id]);
        if ($n === 0) {
            throw ValidationException::withMessages(['run' => __('automations.not_awaiting')]);
        }
        $this->audit->record('automation.run_confirmed', 'operations', null, 'automation', (int) $run->automation_id, ['run' => $run->uuid, 'records' => count($this->recordsOf($run))], $user->id);
        ExecuteAutomationRun::dispatch((int) $run->id)->afterCommit();
    }

    public function cancel(object $run, User $user): void
    {
        $n = DB::table('automation_runs')->where('id', $run->id)->whereIn('status', ['queued', 'awaiting_confirmation', 'waiting'])->update([
            'status' => 'cancelled', 'finished_at' => Carbon::now('UTC')->format('Y-m-d H:i:s.u'), 'resume_at' => null, 'updated_at' => Carbon::now('UTC')->format('Y-m-d H:i:s.u'),
        ]);
        if ($n === 0) {
            throw ValidationException::withMessages(['run' => __('automations.not_cancellable')]);
        }
        $this->audit->record('automation.run_cancelled', 'operations', null, 'automation', (int) $run->automation_id, ['run' => $run->uuid], $user->id);
    }

    /**
     * Starts a failed run again: the records whose steps failed, from their
     * first step, and those it had not reached yet.
     */
    public function retry(object $run, User $user): void
    {
        if (! in_array($run->status, ['failed', 'partially_failed'], true) || (bool) $run->is_test) {
            throw ValidationException::withMessages(['run' => __('automations.not_retryable')]);
        }
        $ref = json_decode((string) $run->trigger_ref, true) ?: [];
        $records = array_map('intval', (array) ($ref['records'] ?? []));
        $left = array_values(array_unique([...array_map('intval', (array) ($ref['failedIds'] ?? [])), ...array_slice($records, (int) ($ref['cursor'] ?? 0))]));
        unset($ref['failedIds'], $ref['did'], $ref['waitStarted']);
        $ref = array_merge($ref, ['records' => $left, 'cursor' => 0, 'failed' => 0]);
        DB::table('automation_runs')->where('id', $run->id)->update([
            'status' => 'queued', 'error' => null, 'finished_at' => null, 'resume_at' => null, 'attempts' => 0, 'current_step' => null,
            'trigger_ref' => json_encode((object) $ref), 'updated_at' => Carbon::now('UTC')->format('Y-m-d H:i:s.u'),
        ]);
        $this->audit->record('automation.run_retried', 'operations', null, 'automation', (int) $run->automation_id, ['run' => $run->uuid], $user->id);
        ExecuteAutomationRun::dispatch((int) $run->id)->afterCommit();
    }

    // ---------------------------------------------------------------- the clock

    /**
     * Every minute: resumes waits and retries that are due and queued runs
     * left behind; a run still "running" long after its last progress lost
     * its worker and is marked failed (it can be retried from where it was).
     */
    public function resumeDue(): int
    {
        $stale = Carbon::now('UTC')->subMinutes(self::STALE_MINUTES)->format('Y-m-d H:i:s.u');
        foreach (DB::table('automation_runs')->where('status', 'running')->where('updated_at', '<', $stale)->limit(100)->get() as $lost) {
            $this->finish($lost, 'failed', $this->log($lost), (int) $lost->records_affected, __('automations.crashed'));
        }
        $now = Carbon::now('UTC')->format('Y-m-d H:i:s.u');
        $due = DB::table('automation_runs')->whereIn('status', ['waiting', 'queued'])
            ->where(static fn ($q) => $q->where('resume_at', '<=', $now)->orWhere(static fn ($q) => $q->whereNull('resume_at')->where('status', 'queued')
                ->where('updated_at', '<', Carbon::now('UTC')->subMinutes(5)->format('Y-m-d H:i:s.u'))))
            ->orderBy('id')->limit(500)->get(['id', 'organization_id']);
        foreach ($due as $r) {
            $this->tenant->set((int) $r->organization_id);
            ExecuteAutomationRun::dispatch((int) $r->id);
        }

        return count($due);
    }

    /**
     * Date triggers: each record whose date (plus the offset) has passed
     * since the trigger last looked starts one run, once per date value.
     */
    public function datesReached(): int
    {
        $fired = 0;
        $now = Carbon::now('UTC');
        $triggers = DB::table('automation_triggers')->join('automations', 'automations.id', '=', 'automation_triggers.automation_id')
            ->where('automation_triggers.type', 'date_reached')->where('automations.is_enabled', true)->get(['automation_triggers.*']);
        foreach ($triggers as $t) {
            $a = DB::table('automations')->where('id', $t->automation_id)->first();
            $rt = $a === null ? null : $this->runtime($a);
            $fieldUuid = $t->date_field_id === null ? null : DB::table('fields')->where('id', $t->date_field_id)->value('uuid');
            $column = $rt === null || $fieldUuid === null ? null : ($rt->column(strtolower((string) $fieldUuid))['name'] ?? null);
            if ($a === null || $rt === null || $column === null) {
                continue;
            }
            $this->tenant->set((int) $a->organization_id);
            $offset = (int) ($t->offset_minutes ?? 0);
            $since = Carbon::parse((string) ($t->last_fired_at ?? $t->created_at), 'UTC');
            // A date value d is due when d + offset has passed: look between the last check and now.
            $lower = $since->copy()->subMinutes($offset);
            $upper = $now->copy()->subMinutes($offset);
            $rows = DB::table($rt->table)->whereNull('deleted_at')->whereNotNull($column)
                ->where($column, '>', $lower->format('Y-m-d H:i:s'))->where($column, '<=', $upper->format('Y-m-d H:i:s'))
                ->orderBy($column)->orderBy('id')->limit(2000)->get(['id', 'uuid', $column.' as due']);
            $last = null;
            foreach ($rows as $row) {
                $this->start($a, 'date_reached', ['record' => strtolower((string) $row->uuid), 'due' => (string) $row->due], [(int) $row->id], null, null);
                $last = (string) $row->due;
                $fired++;
            }
            // When the batch was full, continue next minute from the last date handled.
            $mark = count($rows) === 2000 && $last !== null ? Carbon::parse($last, 'UTC')->addMinutes($offset) : $now;
            DB::table('automation_triggers')->where('id', $t->id)->update(['last_fired_at' => $mark->format('Y-m-d H:i:s.u')]);
        }

        return $fired;
    }

    // ------------------------------------------------------------------ runs

    /**
     * A run over the records that meet the automation's condition now.
     *
     * @param  array<string, mixed>  $ref
     */
    private function startSet(object $a, string $type, array $ref, ?int $by): object
    {
        $rt = $this->runtime($a);
        if ($rt === null) {
            return $this->insertRun($a, $type, $ref, [], $by, null, 'failed', false, __('automations.publish_first'));
        }
        $ast = $a->condition_id === null ? null : $this->conditions->ast((int) $a->condition_id);
        $runAs = $this->runAs($a, $by);
        $ids = [];
        $over = false;
        $max = (int) $a->max_records_per_run;
        foreach (DB::table($rt->table)->whereNull('deleted_at')->orderBy('id')->lazyById(500, 'id') as $row) {
            if ($ast !== null) {
                $record = $this->store->findById($rt, (int) $row->id);
                if ($record === null || ! $this->rules->holds($rt, $ast, $record['values'], $runAs, 'view')) {
                    continue;
                }
            }
            if (count($ids) >= $max) {
                $over = true;
                break;
            }
            $ids[] = (int) $row->id;
        }
        if ($over) {
            return $this->insertRun($a, $type, $ref, [], $by, null, 'failed', false, __('automations.too_many_records', ['max' => $max]));
        }

        return $this->start($a, $type, $ref, $ids, $by, null);
    }

    /**
     * Records a run and queues it (or holds it for confirmation, or records
     * it as skipped when the chain that caused it is too deep).
     *
     * @param  array<string, mixed>  $ref
     * @param  list<int>  $records
     * @param  array{run: int, depth: int}|null  $parent
     */
    private function start(object $a, string $type, array $ref, array $records, ?int $by, ?array $parent): object
    {
        $depth = $parent === null ? 0 : $parent['depth'] + 1;
        if ($parent !== null && $depth > (int) $a->max_chain_depth) {
            $run = $this->insertRun($a, $type, $ref, $records, $by, $parent, 'skipped_loop', false, __('automations.loop_stopped', ['depth' => (int) $a->max_chain_depth]));
            $this->audit->record('automation.loop_stopped', 'operations', null, 'automation', (int) $a->id, ['key' => $a->key, 'run' => $run->uuid, 'depth' => $depth], $by, null, (int) $a->form_id);

            return $run;
        }
        $confirm = $a->confirm_above !== null && in_array($type, AutomationDocument::SET_TRIGGERS, true) && count($records) > (int) $a->confirm_above;
        $run = $this->insertRun($a, $type, $ref, $records, $by, $parent, $confirm ? 'awaiting_confirmation' : 'queued', false);
        if (! $confirm) {
            ExecuteAutomationRun::dispatch((int) $run->id)->afterCommit();
        }

        return $run;
    }

    /**
     * @param  array<string, mixed>  $ref
     * @param  list<int>  $records
     * @param  array{run: int, depth: int}|null  $parent
     */
    private function insertRun(object $a, string $type, array $ref, array $records, ?int $by, ?array $parent, string $status, bool $test, ?string $error = null): object
    {
        $now = Carbon::now('UTC')->format('Y-m-d H:i:s.u');
        $finished = in_array($status, ['failed', 'skipped_loop'], true);
        $id = (int) DB::table('automation_runs')->insertGetId([
            'uuid' => (string) Str::uuid7(), 'organization_id' => (int) $a->organization_id, 'created_at' => $now, 'updated_at' => $now,
            'automation_id' => (int) $a->id, 'trigger_type' => $type, 'trigger_ref' => json_encode((object) ($ref + ['records' => $records, 'cursor' => 0])),
            'parent_run_id' => $parent['run'] ?? null, 'chain_depth' => $parent === null ? 0 : $parent['depth'] + 1, 'is_test' => $test,
            'status' => $status, 'records_affected' => 0, 'steps_log' => '[]', 'current_step' => null, 'resume_at' => null, 'attempts' => 0,
            'finished_at' => $finished ? $now : null, 'duration_ms' => $finished ? 0 : null, 'error' => $error, 'triggered_by' => $by,
            'correlation_id' => $this->correlation->get(),
        ]);

        return DB::table('automation_runs')->where('id', $id)->first() ?? throw new \LogicException('run vanished');
    }

    /** Executes a run (or continues it after a wait or a retry). Called by ExecuteAutomationRun. */
    public function execute(int $runId): void
    {
        $now = Carbon::now('UTC');
        $claimed = DB::table('automation_runs')->where('id', $runId)->whereIn('status', ['queued', 'waiting'])
            ->where(static fn ($q) => $q->whereNull('resume_at')->orWhere('resume_at', '<=', $now->format('Y-m-d H:i:s.u')))
            ->update(['status' => 'running', 'updated_at' => $now->format('Y-m-d H:i:s.u')]);
        if ($claimed === 0) {
            return; // done, cancelled, not due yet, or another worker has it
        }
        $run = DB::table('automation_runs')->where('id', $runId)->first();
        $a = $run === null ? null : DB::table('automations')->where('id', $run->automation_id)->first();
        if ($run === null || $a === null) {
            return;
        }
        $this->tenant->set((int) $a->organization_id);
        $this->correlation->set((string) $run->correlation_id);
        if (! $run->is_test && ! $a->is_enabled) {
            $this->finish($run, 'cancelled', $this->log($run), (int) $run->records_affected, __('automations.disabled'));

            return;
        }
        // The automation's concurrency limit: others running now leave this one queued a little longer.
        $busy = DB::table('automation_runs')->where('automation_id', $a->id)->where('status', 'running')->where('id', '!=', $run->id)->count();
        if (! $run->is_test && $busy >= (int) $a->concurrency_limit) {
            DB::table('automation_runs')->where('id', $run->id)->update(['status' => 'queued', 'resume_at' => $now->copy()->addSeconds(self::BUSY_RETRY_SECONDS)->format('Y-m-d H:i:s.u')]);

            return;
        }
        if ($run->started_at === null) {
            $run->started_at = $now->format('Y-m-d H:i:s.u');
            DB::table('automation_runs')->where('id', $run->id)->update(['started_at' => $run->started_at]);
        }
        $user = $this->runAs($a, $run->triggered_by === null ? null : (int) $run->triggered_by);
        $rt = $this->runtime($a);
        if ($user === null || $rt === null) {
            $this->finish($run, 'failed', $this->log($run), (int) $run->records_affected, $rt === null ? __('automations.publish_first') : __('automations.no_run_as_user'));

            return;
        }
        $previous = Auth::user();
        Auth::setUser($user);
        try {
            $this->proceed($run, $a, $rt, $user);
        } catch (Throwable $e) {
            report($e);
            $this->finish($run, 'failed', $this->log($run), (int) $run->records_affected, __('automations.crashed'));
        } finally {
            $previous === null ? Auth::guard()->forgetUser() : Auth::setUser($previous);
        }
    }

    /** Runs the steps for each record, from where the run stopped. */
    private function proceed(object $run, object $a, FormRuntime $rt, User $user): void
    {
        $ref = json_decode((string) $run->trigger_ref, true) ?: [];
        $records = array_map('intval', (array) ($ref['records'] ?? []));
        $cursor = (int) ($ref['cursor'] ?? 0);
        $failedRecords = (int) ($ref['failed'] ?? 0);
        $affected = (int) $run->records_affected;
        $log = $this->log($run);
        $steps = DB::table('automation_steps')->where('automation_id', $a->id)->orderBy('sort_order')->orderBy('id')->get()->all();
        $asts = $this->conditions->many([$a->condition_id, ...array_map(static fn ($s) => $s->condition_id, $steps)]);
        $condition = $a->condition_id === null ? null : ($asts[(int) $a->condition_id] ?? null);
        $label = $this->translator->labelOf($this->translator->all('automation', (int) $a->id, 'name'), (string) $a->key);
        $origin = new StepOrigin('automation', strtolower((string) $a->uuid), $label, (bool) $run->is_test);
        $context = ['automation_run' => (int) $run->id, 'depth' => (int) $run->chain_depth];
        $deleted = $run->trigger_type === 'record_deleted';
        $began = microtime(true);

        for ($i = $cursor; $i < count($records); $i++) {
            if ($i > $cursor && ! $run->is_test && microtime(true) - $began > self::SLICE_SECONDS) {
                // A long run continues in a fresh job, so no worker holds it past its time limit.
                DB::table('automation_runs')->where('id', $run->id)->update(['status' => 'queued', 'resume_at' => null, 'updated_at' => Carbon::now('UTC')->format('Y-m-d H:i:s.u')]);
                ExecuteAutomationRun::dispatch((int) $run->id);

                return;
            }
            $from = $i === $cursor && $run->current_step !== null ? (int) $run->current_step : 0;
            $recordId = $records[$i];
            $outcome = $run->is_test
                ? $this->rolledBack(function () use ($run, $a, $rt, $user, $recordId, $steps, $asts, $condition, $from, $origin, $context, $deleted, $ref, &$log) {
                    return $this->recordSteps($run, $a, $rt, $user, $recordId, $steps, $asts, $condition, $from, $origin, $context, $deleted, $ref, $log);
                })
                : $this->recordSteps($run, $a, $rt, $user, $recordId, $steps, $asts, $condition, $from, $origin, $context, $deleted, $ref, $log);
            [$state, $detail, $did] = $outcome;
            // Whether this record was changed counts once, across waits and retries.
            $did = $did || ($i === $cursor && (bool) ($ref['did'] ?? false));
            if ($state === 'wait' || $state === 'retry') {
                // Park the run at this record and step until it is due.
                unset($ref['waitStarted']);
                $ref = array_merge($ref, $detail['ref'] ?? [], ['cursor' => $i, 'did' => $did]);
                DB::table('automation_runs')->where('id', $run->id)->update([
                    'status' => $state === 'wait' ? 'waiting' : 'queued', 'current_step' => $detail['step'], 'resume_at' => $detail['at'],
                    'attempts' => $state === 'retry' ? (int) $run->attempts + 1 : 0, 'trigger_ref' => json_encode((object) $ref),
                    'records_affected' => $affected, 'steps_log' => $this->encodeLog($log), 'updated_at' => Carbon::now('UTC')->format('Y-m-d H:i:s.u'),
                ]);

                return;
            }
            if ($did) {
                $affected++;
            }
            if ($state === 'failed') {
                $failedRecords++;
                $ref['failedIds'] = [...(array) ($ref['failedIds'] ?? []), $recordId];
            }
            $run->current_step = null;
            $run->attempts = 0;
            unset($ref['waitStarted'], $ref['did']);
            $ref['cursor'] = $i + 1;
            $ref['failed'] = $failedRecords;
            DB::table('automation_runs')->where('id', $run->id)->update([
                'trigger_ref' => json_encode((object) $ref), 'current_step' => null, 'attempts' => 0, 'records_affected' => $affected,
                'steps_log' => $this->encodeLog($log), 'updated_at' => Carbon::now('UTC')->format('Y-m-d H:i:s.u'),
            ]);
        }
        $status = match (true) {
            $failedRecords === 0 => 'succeeded',
            $failedRecords >= max(1, count($records)) => 'failed',
            default => 'partially_failed',
        };
        $this->finish($run, $status, $log, $affected, $status === 'succeeded' ? null : __('automations.records_failed', ['n' => $failedRecords]));
    }

    /**
     * The steps for one record. Returns the outcome (done, failed, wait,
     * retry), where to resume for a wait or retry, and whether anything ran.
     *
     * @param  list<object>  $steps
     * @param  array<int, array<string, mixed>>  $asts
     * @param  array<string, mixed>|null  $condition
     * @param  array<string, mixed>  $context
     * @param  array<string, mixed>  $ref
     * @param  list<array<string, mixed>>  $log
     * @return array{0: string, 1: array<string, mixed>, 2: bool}
     */
    private function recordSteps(object $run, object $a, FormRuntime $rt, User $user, int $recordId, array $steps, array $asts, ?array $condition, int $from, StepOrigin $origin, array $context, bool $deleted, array $ref, array &$log): array
    {
        $found = $deleted ? $this->findWithDeleted($rt, $recordId) : $this->store->findById($rt, $recordId);
        if ($found === null) {
            $this->note($log, null, null, 'skipped', __('automations.record_gone'), 0);

            return ['done', [], false];
        }
        if ($from === 0 && $condition !== null && ! $this->rules->holds($rt, $condition, $found['values'], $user, 'view')) {
            $this->note($log, null, $found['uuid'], 'skipped', __('automations.condition_not_met'), 0);

            return ['done', [], false];
        }
        $did = false;
        $test = (bool) $run->is_test;
        $policy = json_decode((string) $a->retry_policy, true) ?: [];
        foreach (array_slice($steps, $from, null, true) as $j => $step) {
            $started = microtime(true);
            if (! $deleted) {
                $found = $this->store->findById($rt, $recordId) ?? $found;
            }
            $ast = $step->condition_id === null ? null : ($asts[(int) $step->condition_id] ?? null);
            if ($ast !== null && ! $this->rules->holds($rt, $ast, $found['values'], $user, 'view')) {
                $this->note($log, $step, $found['uuid'], 'skipped', __('automations.step_condition_not_met'), 0);

                continue;
            }
            $config = json_decode((string) $step->config, true) ?: [];
            // A test run never waits for a retry; a real one retries as long as the policy allows.
            $retryable = $step->on_failure === 'retry' && ! $test && (int) $run->attempts + 1 < (int) ($policy['max_attempts'] ?? 1);
            try {
                if ($step->type === 'wait_delay') {
                    if ($test) {
                        $this->note($log, $step, $found['uuid'], 'simulated', __('automations.simulated.wait_delay', ['minutes' => (int) $config['minutes']]), 0);

                        continue;
                    }
                    // The run resumes after this step (a resumed run starts past it).
                    $this->note($log, $step, $found['uuid'], 'waiting', __('automations.waiting_until', ['minutes' => (int) $config['minutes']]), 0);

                    return ['wait', ['step' => $j + 1, 'at' => Carbon::now('UTC')->addMinutes((int) $config['minutes'])->format('Y-m-d H:i:s.u')], $did];
                }
                if ($step->type === 'wait_condition') {
                    if ($this->rules->holds($rt, (array) $config['condition'], $found['values'], $user, 'view')) {
                        $this->note($log, $step, $found['uuid'], 'succeeded', __('automations.condition_reached'), $started);

                        continue;
                    }
                    if ($test) {
                        $this->note($log, $step, $found['uuid'], 'simulated', __('automations.simulated.wait_condition'), 0);

                        continue;
                    }
                    $since = isset($ref['waitStarted']) ? Carbon::parse((string) $ref['waitStarted'], 'UTC') : Carbon::now('UTC');
                    if ($since->copy()->addMinutes((int) $config['timeoutMinutes'])->isPast()) {
                        if (($config['onTimeout'] ?? 'stop') === 'continue') {
                            $this->note($log, $step, $found['uuid'], 'warning', __('automations.wait_timed_out'), 0);

                            continue;
                        }
                        $this->note($log, $step, $found['uuid'], 'failed', __('automations.wait_timed_out'), 0);

                        return ['failed', [], $did];
                    }

                    return ['wait', ['step' => $j, 'at' => Carbon::now('UTC')->addMinutes(self::WAIT_POLL_MINUTES)->format('Y-m-d H:i:s.u'),
                        'ref' => ['waitStarted' => $since->toIso8601ZuluString()]], $did];
                }
                $key = substr(hash('sha256', $run->uuid.':'.$recordId.':'.$step->uuid.':'.(int) $run->attempts), 0, 40);
                $outputs = $this->outbox->within($context, fn () => $step->type === 'run_download'
                    ? $this->download($config, $run, $found, $origin)
                    : $this->steps->one($step, $rt, $user, $found, 'auto-'.$key, null, $origin));
                $simulated = array_filter($outputs, static fn ($o) => ($o['type'] ?? null) === 'simulated');
                $this->note($log, $step, $found['uuid'], $simulated === [] ? 'succeeded' : 'simulated', (string) (array_values($simulated)[0]['message'] ?? ''), $started);
                $did = true;
            } catch (RecordException|ValidationException|StepFailed $e) {
                $message = ActionRunner::message($e);
                if ($retryable) {
                    $backoff = array_values((array) ($policy['backoff_seconds'] ?? [60]));
                    $delay = (int) ($backoff[min((int) $run->attempts, count($backoff) - 1)] ?? 60);
                    $this->note($log, $step, $found['uuid'], 'retrying', __('automations.will_retry', ['reason' => $message, 'seconds' => $delay]), $started);

                    return ['retry', ['step' => $j, 'at' => Carbon::now('UTC')->addSeconds($delay)->format('Y-m-d H:i:s.u')], $did];
                }
                if ($step->on_failure === 'continue') {
                    $this->note($log, $step, $found['uuid'], 'warning', $message, $started);

                    continue;
                }
                $this->note($log, $step, $found['uuid'], 'failed', $message, $started);

                return ['failed', [], $did];
            }
        }

        return ['done', [], $did];
    }

    /**
     * Runs `$fn` in a transaction that is always rolled back (a test run).
     *
     * @template T
     *
     * @param  callable(): T  $fn
     * @return T
     */
    private function rolledBack(callable $fn): mixed
    {
        DB::beginTransaction();
        try {
            return $fn();
        } finally {
            DB::rollBack();
        }
    }

    /**
     * A download delivered by email to each recipient, each with their own
     * run and what they may see; for this record only, or the profile's records.
     *
     * @param  array<string, mixed>  $c
     * @param  array<string, mixed>  $found
     * @return list<array<string, mixed>>
     */
    private function download(array $c, object $run, array $found, StepOrigin $origin): array
    {
        $profile = DB::table('download_profiles')->where('uuid', (string) $c['profile'])->whereNull('deleted_at')->first();
        if ($profile === null || ! $profile->is_active) {
            throw new StepFailed(__('actions.download_unavailable'));
        }
        if ($origin->simulate) {
            return [['type' => 'simulated', 'message' => __('automations.simulated.run_download')]];
        }
        $ids = [];
        foreach (DB::table('users')->whereIn('uuid', (array) ($c['deliverTo']['users'] ?? []))->pluck('id') as $id) {
            $ids[] = (int) $id;
        }
        foreach (DB::table('roles')->whereIn('uuid', (array) ($c['deliverTo']['roles'] ?? []))->pluck('id') as $id) {
            $ids = [...$ids, ...$this->members->userIds('role', (int) $id)];
        }
        $sent = 0;
        foreach (array_values(array_unique($ids)) as $userId) {
            $recipient = User::query()->find($userId);
            if ($recipient === null || $recipient->status !== 'active' || ! $this->downloads->mayUse($profile, $recipient)) {
                continue;
            }
            $input = ($c['scope'] ?? 'profile') === 'record' ? ['record' => $found['uuid']] : [];
            $this->downloads->start($profile, $recipient, (string) $c['format'], $input, null, false, (int) $run->id);
            $sent++;
        }
        if ($sent === 0) {
            throw new StepFailed(__('automations.no_download_recipients'));
        }

        return [];
    }

    /** @param  list<array<string, mixed>>  $log */
    private function finish(object $run, string $status, array $log, int $affected, ?string $error): void
    {
        $now = Carbon::now('UTC');
        $started = $run->started_at === null ? $now : Carbon::parse((string) $run->started_at, 'UTC');
        DB::table('automation_runs')->where('id', $run->id)->update([
            'status' => $status, 'finished_at' => $now->format('Y-m-d H:i:s.u'), 'duration_ms' => max(0, (int) $started->diffInMilliseconds($now)),
            'records_affected' => $affected, 'steps_log' => $this->encodeLog($log), 'error' => $error, 'current_step' => null, 'resume_at' => null,
            'updated_at' => $now->format('Y-m-d H:i:s.u'),
        ]);
    }

    // ---------------------------------------------------------------- helpers

    /**
     * Whether a trigger matches a record event.
     *
     * @param  array<string, mixed>  $p
     */
    private function matches(object $t, string $eventType, array $p, FormRuntime $rt): bool
    {
        return match ($t->type) {
            'record_created' => $eventType === 'record.saved' && ($p['operation'] ?? null) === 'create',
            'record_updated' => $eventType === 'record.saved' && ($p['operation'] ?? null) === 'update',
            'field_changed' => $eventType === 'record.saved' && ($p['operation'] ?? null) === 'update' && $this->fieldChanged($t, (array) ($p['changed'] ?? []), $rt),
            'condition_true' => $eventType === 'record.saved'
                && in_array(strtolower((string) DB::table('automations')->where('id', $t->automation_id)->value('uuid')), (array) ($p['automation_conditions'] ?? []), true),
            'status_changed' => $eventType === 'record.transitioned'
                && ($t->from_status_id === null || $this->statusUuid((int) $t->from_status_id) === strtolower((string) ($p['from'] ?? '')))
                && ($t->to_status_id === null || $this->statusUuid((int) $t->to_status_id) === strtolower((string) ($p['to'] ?? ''))),
            'record_deleted' => $eventType === 'record.deleted',
            default => false,
        };
    }

    /** @param  list<mixed>  $changed  field keys */
    private function fieldChanged(object $t, array $changed, FormRuntime $rt): bool
    {
        $uuid = $t->field_id === null ? null : strtolower((string) DB::table('fields')->where('id', $t->field_id)->value('uuid'));
        $field = $uuid === null ? null : ($rt->fields[$uuid] ?? null);

        return $field !== null && in_array($field['key'], $changed, true);
    }

    private function statusUuid(int $id): ?string
    {
        $uuid = DB::table('statuses')->where('id', $id)->value('uuid');

        return $uuid === null ? null : strtolower((string) $uuid);
    }

    private function runtime(object $a): ?FormRuntime
    {
        $form = $a->form_id === null ? null : Form::query()->find($a->form_id);

        return $form === null ? null : $this->runtimes->forForm($form);
    }

    /** The user a run acts as: the chosen user, or whoever caused the event — active, or nobody. */
    private function runAs(object $a, ?int $triggeredBy): ?User
    {
        $id = $a->run_as === 'specific_user' ? $a->run_as_user_id : $triggeredBy;
        $user = $id === null ? null : User::query()->find((int) $id);

        return $user !== null && $user->status === 'active' ? $user : null;
    }

    /** @return array<string, mixed>|null a record even when it is in the recycle bin */
    private function findWithDeleted(FormRuntime $rt, int $id): ?array
    {
        $uuid = DB::table($rt->table)->where('id', $id)->value('uuid');

        return $uuid === null ? null : $this->store->find($rt, strtolower((string) $uuid), true);
    }

    /** @return list<int> */
    private function recordsOf(object $run): array
    {
        return array_map('intval', (array) ((json_decode((string) $run->trigger_ref, true) ?: [])['records'] ?? []));
    }

    /** @return list<array<string, mixed>> */
    private function log(object $run): array
    {
        $log = json_decode((string) $run->steps_log, true);

        return is_array($log) ? array_values($log) : [];
    }

    /** @param  list<array<string, mixed>>  $log */
    private function encodeLog(array $log): string
    {
        return (string) json_encode(array_slice($log, -self::LOG_LIMIT), JSON_UNESCAPED_UNICODE);
    }

    /** @param  list<array<string, mixed>>  $log */
    private function note(array &$log, ?object $step, ?string $record, string $status, string $message, float|int $started): void
    {
        $log[] = [
            'step' => $step === null ? null : strtolower((string) $step->uuid), 'type' => $step?->type, 'record' => $record, 'status' => $status,
            'message' => $message === '' ? null : mb_substr($message, 0, 500), 'ms' => $started === 0 ? 0 : (int) round((microtime(true) - $started) * 1000),
            'at' => Carbon::now('UTC')->toIso8601ZuluString(),
        ];
    }
}
