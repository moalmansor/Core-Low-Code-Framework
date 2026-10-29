<?php

declare(strict_types=1);

namespace App\Modules\Integrations;

use App\Modules\Access\FieldAccessResolver;
use App\Modules\Access\RecordScope;
use App\Modules\Audit\AuditWriter;
use App\Modules\Core\Correlation\CorrelationId;
use App\Modules\Identity\Models\User;
use App\Modules\Integrations\Jobs\RunSync;
use App\Modules\Integrations\Models\ExternalDataSource;
use App\Modules\Integrations\Models\SyncJob;
use App\Modules\Records\FileStore;
use App\Modules\Records\Runtime\FormRuntime;
use App\Modules\Records\Runtime\FormRuntimes;
use App\Modules\Records\Runtime\RecordException;
use App\Modules\Records\Runtime\RecordPipeline;
use App\Modules\Records\Runtime\RecordStore;
use App\Modules\Scheduling\ScheduledTasks;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Throwable;

/**
 * Sync jobs (specification §4.33, architecture §19.21, ADR-0057).
 *
 * Import reads the rows of an external data source (mapped by the source to
 * value, label, parent and columns) and writes them into a form through the
 * record pipeline as the job's run-as user, so permissions, field access,
 * rules, validation, workflow and audit apply exactly as in the interface.
 * A row is matched to a record by the job's matching fields; the conflict
 * rule decides what happens to a record that already exists:
 *  - source_wins: the source's values replace the record's;
 *  - target_wins: only fields the record leaves empty are filled;
 *  - newest_wins: the record is updated when the source's "updated" column
 *    is later than the record's last change;
 *  - skip: existing records are left alone; only new ones are created.
 *
 * Export sends each record the run-as user may see, changed since the last
 * successful run, as JSON to an address on the source's host, with the
 * source's authentication, holding only fields that user may see.
 *
 * A dry run does everything inside a transaction that is rolled back, so its
 * counts and its errors are those a real run would have; an export dry run
 * sends nothing. Every run keeps a CSV log, row by row.
 */
final class SyncJobs
{
    public const DIRECTIONS = ['import', 'export'];

    public const RULES = ['source_wins', 'target_wins', 'newest_wins', 'skip'];

    /** Records sent by one export run at most. */
    public const MAX_EXPORT = 5000;

    private const NOT_WRITABLE = ['none', 'file', 'files'];

    public function __construct(
        private readonly ExternalSources $sources,
        private readonly FormRuntimes $runtimes,
        private readonly RecordPipeline $pipeline,
        private readonly RecordStore $store,
        private readonly RecordScope $scope,
        private readonly FieldAccessResolver $fieldAccess,
        private readonly ScheduledTasks $scheduler,
        private readonly FileStore $files,
        private readonly AuditWriter $audit,
        private readonly CorrelationId $correlation,
    ) {}

    /**
     * Checks an admin's document.
     *
     * @param  array<string, mixed>  $d
     * @return array{row: array<string, mixed>, name: array<string, string>, schedule: array{cron: string, timezone: string}|null}
     *
     * @throws ValidationException
     */
    public function normalize(array $d): array
    {
        $errors = [];
        $source = is_string($d['source'] ?? null) ? ExternalDataSource::query()->where('uuid', strtolower($d['source']))->first() : null;
        if ($source === null) {
            $errors['source'][] = __('integrations.sync.bad_source');
        }
        $form = is_string($d['form'] ?? null) ? DB::table('forms')->where('uuid', strtolower($d['form']))->whereNull('deleted_at')->first(['id', 'uuid']) : null;
        $rt = $form === null ? null : $this->runtimes->forUuid((string) $form->uuid);
        if ($rt === null) {
            $errors['form'][] = __('integrations.sync.bad_form');
        }
        $direction = (string) ($d['direction'] ?? '');
        if (! in_array($direction, self::DIRECTIONS, true)) {
            $errors['direction'][] = __('integrations.sync.bad_direction');
        }
        $rule = (string) ($d['conflict_rule'] ?? 'source_wins');
        if (! in_array($rule, self::RULES, true)) {
            $errors['conflict_rule'][] = __('integrations.sync.bad_rule');
        }
        $user = is_string($d['run_as'] ?? null) ? User::query()->where('uuid', strtolower($d['run_as']))->where('status', 'active')->first() : null;
        if ($user === null) {
            $errors['run_as'][] = __('integrations.sync.bad_user');
        }
        $name = [];
        foreach ((array) ($d['name'] ?? []) as $loc => $v) {
            if (is_string($loc) && preg_match('/^[a-z]{2}(-[A-Za-z]{2,4})?$/', $loc) === 1 && is_string($v) && trim($v) !== '') {
                $name[$loc] = mb_substr(trim($v), 0, 255);
            }
        }
        if ($name === []) {
            $errors['name'][] = __('integrations.name_required');
        }
        $from = $source === null ? [] : ['value', 'label', 'parent', ...array_column($source->response_mapping['columns'] ?? [], 'key')];
        $fields = [];
        $seen = [];
        foreach (array_slice(array_values((array) ($d['fields'] ?? [])), 0, 200) as $i => $m) {
            $key = (string) ($m['field'] ?? '');
            $f = $rt === null ? null : ($rt->fields[$rt->keys[$key] ?? ''] ?? null);
            if ($f === null || isset($seen[$key]) || in_array($rt->type($f)?->storage, self::NOT_WRITABLE, true) || ($rt->column($f['uuid']) === null && ! $rt->isMultiReference($f))) {
                $errors["fields.{$i}.field"][] = __('integrations.sync.bad_field');

                continue;
            }
            $seen[$key] = true;
            if ($direction === 'export') {
                $to = (string) ($m['to'] ?? '');
                if (preg_match('/^[A-Za-z_][A-Za-z0-9_\-]{0,63}(\.[A-Za-z_][A-Za-z0-9_\-]{0,63}){0,3}$/', $to) !== 1) {
                    $errors["fields.{$i}.to"][] = __('integrations.sync.bad_target');

                    continue;
                }
                $fields[] = ['field' => $key, 'to' => $to];
            } else {
                $src = (string) ($m['from'] ?? '');
                if (! in_array($src, $from, true)) {
                    $errors["fields.{$i}.from"][] = __('integrations.sync.bad_from');

                    continue;
                }
                $fields[] = ['field' => $key, 'from' => $src];
            }
        }
        if ($fields === [] && ! isset($errors['fields'])) {
            $errors['fields'][] = __('integrations.sync.fields_required');
        }
        $match = [];
        $updatedFrom = null;
        $export = null;
        if ($direction === 'import') {
            foreach (array_values((array) ($d['match'] ?? [])) as $i => $key) {
                $f = $rt === null ? null : ($rt->fields[$rt->keys[(string) $key] ?? ''] ?? null);
                if ($f === null || ! isset($seen[(string) $key]) || $rt->column($f['uuid']) === null || $rt->isMultiReference($f) || $rt->type($f)?->multiple) {
                    $errors["match.{$i}"][] = __('integrations.sync.bad_match');

                    continue;
                }
                $match[] = (string) $key;
            }
            if ($match === [] && $rule !== 'skip') {
                $errors['match'][] = __('integrations.sync.match_required');
            }
            $updatedFrom = ($d['updated_from'] ?? null) === null || $d['updated_from'] === '' ? null : (string) $d['updated_from'];
            if ($updatedFrom !== null && ! in_array($updatedFrom, $from, true)) {
                $errors['updated_from'][] = __('integrations.sync.bad_from');
            }
            if ($rule === 'newest_wins' && $updatedFrom === null) {
                $errors['updated_from'][] = __('integrations.sync.updated_required');
            }
        } elseif ($direction === 'export') {
            if ($source !== null && $source->type !== 'rest') {
                $errors['source'][] = __('integrations.sync.export_rest_only');
            }
            $url = trim((string) ($d['export']['url'] ?? ''));
            $method = strtoupper((string) ($d['export']['method'] ?? 'POST'));
            if (! $this->sources->allows($url)) {
                $errors['export.url'][] = __('integrations.bad_url');
            }
            if (! in_array($method, ['POST', 'PUT'], true)) {
                $errors['export.method'][] = __('notifications.channel.bad_method');
            }
            $export = ['url' => $url, 'method' => $method];
        }
        $schedule = null;
        $cron = trim((string) ($d['schedule']['cron'] ?? ''));
        if ($cron !== '') {
            $tz = (string) ($d['schedule']['timezone'] ?? 'UTC');
            if (! ScheduledTasks::valid($cron, $tz)) {
                $errors['schedule.cron'][] = __('integrations.sync.bad_cron');
            } else {
                $schedule = ['cron' => $cron, 'timezone' => $tz];
            }
        }
        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        return ['row' => [
            'external_data_source_id' => $source->id, 'form_id' => (int) $form->id, 'direction' => $direction,
            'field_mapping' => ['fields' => $fields, 'export' => $export], 'match_keys' => ['fields' => $match, 'updated_from' => $updatedFrom],
            'conflict_rule' => $rule, 'run_as_user_id' => $user->id, 'is_active' => (bool) ($d['active'] ?? true),
        ], 'name' => $name, 'schedule' => $schedule];
    }

    /** Puts the job's schedule in the scheduler, or switches it off. @param  array{cron: string, timezone: string}|null  $schedule */
    public function schedule(SyncJob $job, ?array $schedule, string $label): void
    {
        if ($schedule === null) {
            $this->scheduler->disable($job->scheduled_task_id);

            return;
        }
        $id = $this->scheduler->put($job->scheduled_task_id, 'sync', 'sync_job', $job->id, mb_substr($label, 0, 255), $schedule['cron'], $schedule['timezone'], $job->is_active);
        if ($id !== $job->scheduled_task_id) {
            $job->forceFill(['scheduled_task_id' => $id])->save();
        }
    }

    /** Queues a run. */
    public function start(SyncJob $job, bool $dryRun, ?int $triggeredBy): int
    {
        $running = DB::table('sync_runs')->where('sync_job_id', $job->id)->whereIn('status', ['queued', 'running'])->where('dry_run', false)->exists();
        if ($running && ! $dryRun) {
            throw ValidationException::withMessages(['job' => __('integrations.sync.already_running')]);
        }
        $now = Carbon::now('UTC')->format('Y-m-d H:i:s.u');
        $id = (int) DB::table('sync_runs')->insertGetId([
            'uuid' => (string) Str::uuid7(), 'organization_id' => $job->organization_id, 'created_at' => $now, 'updated_at' => $now,
            'sync_job_id' => $job->id, 'dry_run' => $dryRun, 'status' => 'queued', 'triggered_by' => $triggeredBy, 'correlation_id' => $this->correlation->get(),
        ]);
        RunSync::dispatch($id)->afterCommit();

        return $id;
    }

    /** The scheduler's handler: a scheduled task of kind `sync` starts a real run of its job. */
    public function scheduled(object $task): void
    {
        $job = SyncJob::query()->find((int) $task->owner_id);
        if ($job === null || ! $job->is_active) {
            return;
        }
        $this->start($job, false, null);
    }

    /** Runs a queued run (the queue job). */
    public function execute(int $runId): void
    {
        $run = DB::table('sync_runs')->where('id', $runId)->first();
        if ($run === null || $run->status !== 'queued') {
            return;
        }
        $now = static fn () => Carbon::now('UTC')->format('Y-m-d H:i:s.u');
        DB::table('sync_runs')->where('id', $runId)->update(['status' => 'running', 'started_at' => $now(), 'updated_at' => $now()]);
        $log = [];
        $counts = ['created' => 0, 'updated' => 0, 'skipped' => 0, 'errors' => 0];
        $fatal = null;
        $job = SyncJob::query()->find((int) $run->sync_job_id);
        $user = $job === null ? null : User::query()->whereKey($job->run_as_user_id)->where('status', 'active')->first();
        $source = $job === null ? null : ExternalDataSource::query()->find($job->external_data_source_id);
        $form = $job === null ? null : DB::table('forms')->where('id', $job->form_id)->value('uuid');
        $rt = $form === null ? null : $this->runtimes->forUuid((string) $form);
        $previous = Auth::user();
        try {
            if ($job === null || $user === null || $source === null || $rt === null) {
                throw new RuntimeException(__('integrations.sync.cannot_run'));
            }
            Auth::setUser($user);
            if ($job->direction === 'import') {
                $rows = $this->sources->rows($source, [], ExternalSources::MAX_ROWS);
                $work = function () use ($job, $rt, $user, $rows, &$log, &$counts): void {
                    foreach ($rows as $i => $row) {
                        $this->importRow($job, $rt, $user, $row, $i + 1, $log, $counts);
                    }
                };
                if ((bool) $run->dry_run) {
                    DB::beginTransaction();
                    try {
                        $work();
                    } finally {
                        DB::rollBack();
                    }
                } else {
                    $work();
                }
            } else {
                $this->export($job, $rt, $user, $source, (bool) $run->dry_run, $log, $counts);
            }
        } catch (SourceFailure|RuntimeException $e) {
            $fatal = $e->getMessage();
        } catch (Throwable $e) {
            report($e);
            $fatal = mb_substr($e->getMessage(), 0, 1000);
        } finally {
            $previous === null ? Auth::guard()->forgetUser() : Auth::setUser($previous);
        }
        $processed = $counts['created'] + $counts['updated'] + $counts['skipped'];
        $status = $fatal !== null ? 'failed' : ($counts['errors'] === 0 ? 'succeeded' : ($processed > 0 ? 'partially_failed' : 'failed'));
        $file = $this->writeLog($runId, $log, $job, $run);
        DB::table('sync_runs')->where('id', $runId)->update([
            'status' => $status, 'created_count' => $counts['created'], 'updated_count' => $counts['updated'], 'skipped_count' => $counts['skipped'],
            'error_count' => $counts['errors'], 'log_file_id' => $file, 'finished_at' => $now(), 'updated_at' => $now(), 'error' => $fatal,
        ]);
        if ($job !== null) {
            $this->audit->record('integration.sync_run', 'operations', null, 'sync_job', $job->id, ['run' => $runId, 'status' => $status, 'dry_run' => (bool) $run->dry_run] + $counts, $run->triggered_by === null ? null : (int) $run->triggered_by, null, $job->form_id);
        }
    }

    /**
     * @param  array{value: string, label: string, parent: string|null, cells: array<string, mixed>}  $row
     * @param  list<list<mixed>>  $log
     * @param  array{created: int, updated: int, skipped: int, errors: int}  $counts
     */
    private function importRow(SyncJob $job, FormRuntime $rt, User $user, array $row, int $n, array &$log, array &$counts): void
    {
        $read = static fn (string $from): mixed => match ($from) {
            'value' => $row['value'], 'label' => $row['label'], 'parent' => $row['parent'], default => $row['cells'][$from] ?? null,
        };
        $values = [];
        foreach ($job->field_mapping['fields'] ?? [] as $m) {
            $values[$m['field']] = $read($m['from']);
        }
        $key = $row['value'];
        try {
            $current = $this->match($job, $rt, $values);
            if ($current === null) {
                $done = $this->pipeline->create($rt, $user, $values, [], 'sync-'.Str::uuid7(), 'sync');
                $counts['created']++;
                $log[] = [$n, $key, 'created', (string) $done['uuid'], ''];

                return;
            }
            $rule = $job->conflict_rule;
            if ($rule === 'skip') {
                $counts['skipped']++;
                $log[] = [$n, $key, 'skipped', (string) $current['uuid'], __('integrations.sync.log_exists')];

                return;
            }
            if ($rule === 'newest_wins') {
                $stamp = $read((string) $job->match_keys['updated_from']);
                $sourceAt = null;
                try {
                    $sourceAt = is_scalar($stamp) && $stamp !== '' ? Carbon::parse((string) $stamp, 'UTC') : null;
                } catch (Throwable) {
                    $sourceAt = null;
                }
                $recordAt = isset($current['system']['updated_at']) ? Carbon::parse((string) $current['system']['updated_at'], 'UTC') : null;
                if ($sourceAt === null || ($recordAt !== null && ! $sourceAt->greaterThan($recordAt))) {
                    $counts['skipped']++;
                    $log[] = [$n, $key, 'skipped', (string) $current['uuid'], __('integrations.sync.log_not_newer')];

                    return;
                }
            }
            if ($rule === 'target_wins') {
                $values = array_filter($values, static fn ($v, $k) => self::blank($current['values'][$k] ?? null), ARRAY_FILTER_USE_BOTH);
            }
            $changed = array_filter($values, static fn ($v, $k) => self::norm($current['values'][$k] ?? null) !== self::norm($v), ARRAY_FILTER_USE_BOTH);
            if ($changed === []) {
                $counts['skipped']++;
                $log[] = [$n, $key, 'skipped', (string) $current['uuid'], __('integrations.sync.log_unchanged')];

                return;
            }
            $done = $this->pipeline->update($rt, $user, (string) $current['uuid'], (int) $current['row_version'], $changed, 'sync-'.Str::uuid7(), 'sync');
            $counts['updated']++;
            $log[] = [$n, $key, 'updated', (string) $done['uuid'], ''];
        } catch (RecordException $e) {
            $counts['errors']++;
            $log[] = [$n, $key, 'failed', '', $e->getMessage()];
        } catch (ValidationException $e) {
            $counts['errors']++;
            $log[] = [$n, $key, 'failed', '', implode(' ', array_map(static fn ($k, $m) => $k.': '.implode(' ', (array) $m), array_keys($e->errors()), $e->errors()))];
        }
    }

    /**
     * The record whose matching fields hold the row's values, if any.
     *
     * @param  array<string, mixed>  $values
     * @return array<string, mixed>|null
     */
    private function match(SyncJob $job, FormRuntime $rt, array $values): ?array
    {
        $fields = $job->match_keys['fields'] ?? [];
        if ($fields === []) {
            return null;
        }
        $q = DB::table($rt->table)->whereNull('deleted_at');
        foreach ($fields as $key) {
            $v = $values[$key] ?? null;
            if (! is_scalar($v) || $v === '') {
                throw new RecordException(422, 'sync_match', __('integrations.sync.log_no_key', ['field' => $key]));
            }
            $q->where($rt->column($rt->keys[$key])['name'], $v);
        }
        $ids = $q->limit(2)->pluck('id');
        if ($ids->count() > 1) {
            throw new RecordException(409, 'sync_ambiguous', __('integrations.sync.log_ambiguous'));
        }

        return $ids->isEmpty() ? null : $this->store->findById($rt, (int) $ids->first());
    }

    /**
     * @param  list<list<mixed>>  $log
     * @param  array{created: int, updated: int, skipped: int, errors: int}  $counts
     */
    private function export(SyncJob $job, FormRuntime $rt, User $user, ExternalDataSource $source, bool $dryRun, array &$log, array &$counts): void
    {
        $since = DB::table('sync_runs')->where('sync_job_id', $job->id)->where('dry_run', false)->where('status', 'succeeded')->max('started_at');
        $q = DB::table($rt->table)->whereNull('deleted_at');
        $this->scope->apply($q, $rt, $user, 'view');
        if ($since !== null) {
            $q->where('updated_at', '>=', $since);
        }
        $rows = $q->orderBy('id')->limit(self::MAX_EXPORT)->get()->map(static fn ($x) => (array) $x)->all();
        $records = $this->store->hydrate($rt, $rows, false);
        $target = (array) ($job->field_mapping['export'] ?? []);
        foreach ($records as $n => $record) {
            $levels = $this->fieldAccess->resolve($user, $rt->form->id, $rt->form->uuid, $rt->definition, 'view', $record['system']['status_id'] ?? null)['fields'];
            $payload = [];
            foreach ($job->field_mapping['fields'] ?? [] as $m) {
                $uuid = $rt->keys[$m['field']] ?? null;
                if ($uuid === null || ($levels[$uuid] ?? 'hidden') === 'hidden') {
                    continue;
                }
                data_set($payload, $m['to'], $record['values'][$m['field']] ?? null);
            }
            $reference = (string) ($record['system']['record_number'] ?? $record['uuid']);
            if ($dryRun) {
                $counts['created']++;
                $log[] = [$n + 1, $reference, 'would_send', (string) $record['uuid'], ''];

                continue;
            }
            try {
                $status = $this->sources->push($source, (string) $target['url'], (string) $target['method'], $payload);
                $counts['created']++;
                $log[] = [$n + 1, $reference, 'sent', (string) $record['uuid'], 'HTTP '.$status];
            } catch (SourceFailure $e) {
                $counts['errors']++;
                $log[] = [$n + 1, $reference, 'failed', (string) $record['uuid'], $e->getMessage()];
            }
        }
    }

    /** @param  list<list<mixed>>  $log */
    private function writeLog(int $runId, array $log, ?SyncJob $job, object $run): ?int
    {
        $tmp = tempnam(sys_get_temp_dir(), 'sync');
        if ($tmp === false) {
            return null;
        }
        $h = fopen($tmp, 'w');
        if ($h === false) {
            return null;
        }
        fwrite($h, "\xEF\xBB\xBF");
        fputcsv($h, [__('integrations.sync.col_row'), __('integrations.sync.col_key'), __('integrations.sync.col_outcome'), __('integrations.sync.col_record'), __('integrations.sync.col_message')], escape: '');
        foreach ($log as $line) {
            // Spreadsheet formulas never run from a log cell.
            fputcsv($h, array_map(static fn ($c) => is_string($c) && preg_match('/^[=+\-@\t\r]/', $c) === 1 ? "'".$c : $c, $line), escape: '');
        }
        fclose($h);
        try {
            $file = $this->files->storeGenerated($tmp, 'sync-run-'.$runId, 'csv', 'text/csv', 'sync_run', ['owner_id' => $runId, 'form_id' => $job?->form_id,
                'user_id' => $run->triggered_by === null ? null : (int) $run->triggered_by]);

            return $file->id;
        } finally {
            @unlink($tmp);
        }
    }

    private static function blank(mixed $v): bool
    {
        return $v === null || $v === '' || $v === [];
    }

    private static function norm(mixed $v): string
    {
        return is_array($v) ? (string) json_encode($v) : (is_bool($v) ? ($v ? '1' : '0') : (string) $v);
    }
}
