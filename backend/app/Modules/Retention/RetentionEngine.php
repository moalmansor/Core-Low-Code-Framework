<?php

declare(strict_types=1);

namespace App\Modules\Retention;

use App\Modules\Audit\AuditWriter;
use App\Modules\Audit\ChainVerifier;
use App\Modules\Core\Correlation\CorrelationId;
use App\Modules\Core\Tenancy\TenantContext;
use App\Modules\Forms\Models\Form;
use App\Modules\Records\FileStore;
use App\Modules\Records\Runtime\FormRuntime;
use App\Modules\Records\Runtime\FormRuntimes;
use App\Modules\Retention\Jobs\RunRetentionPolicy;
use App\Modules\Retention\Models\RetentionPolicy;
use Carbon\CarbonImmutable;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

/**
 * Runs retention policies (specification §4.26, architecture §19.16). A run
 * selects what is older than the policy's period by its date basis, leaves
 * out records under legal hold, and applies the end action in chunks:
 *
 * - archive: a JSON copy goes to cold storage (with the record's files) and
 *   is listed in `archived_records`, from where a record can be restored;
 * - export then delete: one JSON Lines file with everything removed is kept
 *   as the run's export, then the rows are removed;
 * - delete: the rows are removed.
 *
 * Audit logs are never removed: their policy makes a verified copy of each
 * whole month past the period (entries with their chain proof), once.
 * Every run is recorded in `retention_runs` and audited.
 */
final class RetentionEngine
{
    public const DATA_CLASSES = ['records', 'audit_logs', 'error_logs', 'email_logs', 'notification_logs', 'submission_journal', 'download_files', 'attachments', 'recycle_bin'];

    public const END_ACTIONS = ['archive', 'export_then_delete', 'delete'];

    public const DATE_BASES = ['created_at', 'updated_at', 'final_status_at'];

    /** Disks a policy may archive to. */
    public const ARCHIVE_DISKS = ['archive'];

    private const CHUNK = 200;

    /** Rows per archive file of a log. */
    private const LOG_FILE_ROWS = 1000;

    /** @var array{scanned: int, archived: int, deleted: int, skipped: int, failed: int} */
    private array $counts;

    private ?string $exportPath = null;

    private ?object $run = null;

    private ?RetentionPolicy $policy = null;

    public function __construct(
        private readonly FormRuntimes $runtimes,
        private readonly RecordRemoval $removal,
        private readonly LegalHolds $holds,
        private readonly AuditWriter $audit,
        private readonly TenantContext $tenant,
        private readonly CorrelationId $correlation,
        private readonly FileStore $files,
    ) {}

    /** Queues a run of the policy (from the screen or its schedule). */
    public function start(RetentionPolicy $policy, ?int $userId): void
    {
        if (DB::table('retention_runs')->where('retention_policy_id', $policy->id)->where('status', 'running')->exists()) {
            throw new RuntimeException(__('retention.already_running'));
        }
        RunRetentionPolicy::dispatch($policy->id, $userId)->afterCommit();
    }

    /** The scheduler's handler: a scheduled task of kind `retention` queues a run of its policy. */
    public function scheduled(object $task): void
    {
        $policy = RetentionPolicy::query()->withoutGlobalScopes()->find((int) $task->owner_id);
        if ($policy !== null && $policy->is_active && ! DB::table('retention_runs')->where('retention_policy_id', $policy->id)->where('status', 'running')->exists()) {
            RunRetentionPolicy::dispatch($policy->id, null);
        }
    }

    /** Runs a policy now, in this process. Returns the run's id. */
    public function run(int $policyId, ?int $userId, ?CarbonImmutable $now = null): int
    {
        $policy = RetentionPolicy::query()->withoutGlobalScopes()->findOrFail($policyId);
        $this->tenant->set($policy->organization_id);
        $now ??= CarbonImmutable::now('UTC');
        $this->policy = $policy;
        $this->counts = ['scanned' => 0, 'archived' => 0, 'deleted' => 0, 'skipped' => 0, 'failed' => 0];
        $this->exportPath = null;
        $stamp = $now->format('Y-m-d H:i:s.u');
        $runId = (int) DB::table('retention_runs')->insertGetId([
            'uuid' => (string) Str::uuid7(), 'organization_id' => $policy->organization_id, 'created_at' => $stamp, 'updated_at' => $stamp,
            'retention_policy_id' => $policy->id, 'status' => 'running', 'skipped_legal_hold' => 0, 'started_at' => $stamp,
            'triggered_by' => $userId, 'correlation_id' => $this->correlation->get(),
        ]);
        $this->run = DB::table('retention_runs')->where('id', $runId)->first();
        $cutoff = $now->subDays($policy->retention_days);
        $error = null;
        try {
            match ($policy->data_class) {
                'records' => $this->records($cutoff, $now),
                'recycle_bin' => $this->recycleBin($cutoff, $now),
                'audit_logs' => $this->auditLogs($cutoff, $now),
                'error_logs' => $this->logRows('error_logs', 'occurred_at', $cutoff, $now),
                'email_logs' => $this->emailLogs($cutoff, $now),
                'notification_logs' => $this->notificationLogs($cutoff, $now),
                'submission_journal' => $this->logRows('submission_journal', 'created_at', $cutoff, $now, static fn ($q) => $q->whereIn('status', ['processed', 'discarded'])),
                'download_files' => $this->downloadFiles($cutoff, $now),
                'attachments' => $this->attachments($cutoff, $now),
                default => throw new RuntimeException('Unknown data class.'),
            };
        } catch (Throwable $e) {
            report($e);
            $error = mb_substr($e->getMessage(), 0, 2000);
        }
        $exportFileId = $this->finishExport();
        $status = $error !== null ? ($this->counts['archived'] + $this->counts['deleted'] > 0 ? 'partially_failed' : 'failed')
            : ($this->counts['failed'] > 0 ? 'partially_failed' : 'succeeded');
        $finished = CarbonImmutable::now('UTC')->format('Y-m-d H:i:s.u');
        DB::table('retention_runs')->where('id', $runId)->update([
            'status' => $status, 'scanned_count' => $this->counts['scanned'], 'archived_count' => $this->counts['archived'], 'deleted_count' => $this->counts['deleted'],
            'skipped_legal_hold' => $this->counts['skipped'], 'export_file_id' => $exportFileId, 'finished_at' => $finished, 'updated_at' => $finished,
            'error' => $error ?? ($this->counts['failed'] > 0 ? __('retention.some_failed', ['count' => $this->counts['failed']]) : null),
        ]);
        $this->audit->record('retention.run', 'operations', null, 'retention_policy', $policy->id, [
            'data_class' => $policy->data_class, 'end_action' => $policy->end_action, 'retention_days' => $policy->retention_days, 'cutoff' => $cutoff->toIso8601ZuluString(),
            'status' => $status, 'scanned' => $this->counts['scanned'], 'archived' => $this->counts['archived'], 'deleted' => $this->counts['deleted'],
            'skipped_legal_hold' => $this->counts['skipped'], 'failed' => $this->counts['failed'],
        ], $userId, null, $policy->form_id);

        return $runId;
    }

    /** Restores an archived record under its own id, with its rows, links and files. */
    public function restore(object $archived, int $userId): void
    {
        if ($archived->form_id === null || $archived->restored_at !== null) {
            throw new RuntimeException(__('retention.not_restorable'));
        }
        $form = Form::query()->find((int) $archived->form_id);
        $rt = $form === null ? null : $this->runtimes->forForm($form);
        if ($rt === null) {
            throw new RuntimeException(__('retention.form_gone'));
        }
        $disk = Storage::disk((string) $archived->disk);
        if (! $disk->exists((string) $archived->path)) {
            throw new RuntimeException(__('retention.archive_missing'));
        }
        $json = (string) $disk->get((string) $archived->path);
        if (! hash_equals((string) $archived->payload_hash, hash('sha256', $json))) {
            throw new RuntimeException(__('retention.archive_altered'));
        }
        $snapshot = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        DB::transaction(function () use ($rt, $snapshot, $archived, $userId): void {
            $this->removal->restore($rt, $snapshot, (string) $archived->disk);
            $now = Carbon::now('UTC')->format('Y-m-d H:i:s.u');
            DB::table('archived_records')->where('id', $archived->id)->update(['restored_at' => $now, 'restored_by' => $userId]);
            $this->audit->record('record.restored_from_archive', 'data', null, 'record', (int) $archived->record_id,
                ['form' => $rt->form->key, 'archived_at' => $archived->archived_at], $userId, null, $rt->form->id, (int) $archived->record_id);
        });
        $this->holds->recompute($rt);
    }

    // ---- records ------------------------------------------------------

    private function records(CarbonImmutable $cutoff, CarbonImmutable $now): void
    {
        foreach ($this->forms() as $rt) {
            $this->holds->recompute($rt);
            $q = fn () => $this->recordCandidates($rt, $cutoff)->whereNull('deleted_at');
            $this->counts['skipped'] += $q()->where('legal_hold', true)->count();
            $this->eachId($q()->where('legal_hold', false), function (int $id) use ($rt, $now): void {
                $this->takeRecord($rt, $id, $now, null);
            });
        }
    }

    private function recycleBin(CarbonImmutable $cutoff, CarbonImmutable $now): void
    {
        $entries = DB::table('recycle_bin')->whereNull('restored_at')->whereNull('purged_at')->where('deleted_at', '<', $cutoff->format('Y-m-d H:i:s.u'));
        if ($this->policy?->form_id !== null) {
            $entries->where('form_id', $this->policy->form_id);
        } elseif ($this->policy?->application_id !== null) {
            $entries->whereIn('form_id', DB::table('forms')->where('application_id', $this->policy->application_id)->select('id'));
        }
        $byForm = [];
        foreach ((clone $entries)->orderBy('id')->get(['id', 'form_id', 'record_id']) as $e) {
            $byForm[(int) $e->form_id][] = $e;
        }
        foreach ($byForm as $formId => $list) {
            $form = Form::query()->find($formId);
            $rt = $form === null ? null : $this->runtimes->forForm($form);
            if ($rt === null) {
                continue;
            }
            $this->holds->recompute($rt);
            foreach ($list as $e) {
                $this->counts['scanned']++;
                $row = DB::table($rt->table)->where('id', $e->record_id)->first(['id', 'deleted_at', 'legal_hold']);
                if ($row === null || $row->deleted_at === null) {
                    DB::table('recycle_bin')->where('id', $e->id)->update(['purged_at' => $now->format('Y-m-d H:i:s.u')]);

                    continue;
                }
                if ((bool) $row->legal_hold) {
                    $this->counts['skipped']++;

                    continue;
                }
                $this->takeRecord($rt, (int) $e->record_id, $now, (int) $e->id, false);
            }
        }
    }

    /** Applies the end action to one record, in its own transaction; a failure is counted and the run goes on. */
    private function takeRecord(FormRuntime $rt, int $id, CarbonImmutable $now, ?int $binEntry, bool $count = true): void
    {
        if ($count) {
            $this->counts['scanned']++;
        }
        $stamp = $now->format('Y-m-d H:i:s.u');
        try {
            $snapshot = $this->removal->snapshot($rt, $id);
            if ($snapshot === null) {
                return;
            }
            $archive = null;
            $action = (string) $this->policy?->end_action;
            if ($action === 'archive') {
                $disk = $this->disk();
                $dir = $this->directory().'/'.$rt->form->key.'/'.$id;
                $snapshot = $this->removal->archiveFiles($snapshot, $disk, $dir);
                $json = (string) json_encode($snapshot, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                Storage::disk($disk)->put($dir.'/record.json', $json);
                $archive = ['disk' => $disk, 'path' => $dir.'/record.json', 'hash' => hash('sha256', $json)];
            } elseif ($action === 'export_then_delete') {
                $this->exportLine($snapshot);
            }
            DB::transaction(function () use ($rt, $id, $stamp, $archive, $binEntry, $action): void {
                if ($archive !== null) {
                    DB::table('archived_records')->insert([
                        'organization_id' => $rt->form->organization_id, 'source_table' => $rt->table, 'form_id' => $rt->form->id, 'record_id' => $id,
                        'retention_run_id' => $this->run?->id, 'disk' => $archive['disk'], 'path' => $archive['path'], 'payload_hash' => $archive['hash'], 'archived_at' => $stamp,
                    ]);
                }
                $this->removal->remove($rt, $id, $stamp);
                if ($binEntry !== null) {
                    DB::table('recycle_bin')->where('id', $binEntry)->update(['purged_at' => $stamp]);
                }
                $this->audit->record($archive !== null ? 'record.archived' : 'record.purged', 'data', null, 'record', $id,
                    ['form' => $rt->form->key, 'retention_run' => $this->run?->uuid, 'end_action' => $action], null, null, $rt->form->id, $id);
            });
            $archive !== null ? $this->counts['archived']++ : $this->counts['deleted']++;
        } catch (Throwable $e) {
            report($e);
            $this->counts['failed']++;
        }
    }

    /** @return Builder */
    private function recordCandidates(FormRuntime $rt, CarbonImmutable $cutoff)
    {
        $q = DB::table($rt->table);
        $at = $cutoff->format('Y-m-d H:i:s.u');
        match ($this->policy?->date_basis) {
            'updated_at' => $q->where('updated_at', '<', $at),
            'final_status_at' => $q->where('status_changed_at', '<', $at)
                ->whereIn('status_id', DB::table('statuses')->where('form_id', $rt->form->id)->where('is_final', true)->select('id')),
            default => $q->where('created_at', '<', $at),
        };

        return $q;
    }

    /** @return list<FormRuntime> the forms the policy covers */
    private function forms(): array
    {
        $q = Form::query()->whereNotNull('current_version_id');
        if ($this->policy?->form_id !== null) {
            $q->whereKey($this->policy->form_id);
        } elseif ($this->policy?->application_id !== null) {
            $q->where('application_id', $this->policy->application_id);
        }
        $out = [];
        foreach ($q->orderBy('id')->get() as $form) {
            if (($rt = $this->runtimes->forForm($form)) !== null) {
                $out[] = $rt;
            }
        }

        return $out;
    }

    // ---- logs ---------------------------------------------------------

    /**
     * Audit logs are archived, never removed (CLAUDE.md: audit entries are
     * immutable): each whole month past the period is copied once, with a
     * proof per chain, after every entry's hash has been recomputed.
     */
    private function auditLogs(CarbonImmutable $cutoff, CarbonImmutable $now): void
    {
        $first = DB::table('audit_logs')->min('occurred_at');
        if ($first === null) {
            return;
        }
        $month = CarbonImmutable::parse((string) $first, 'UTC')->startOfMonth();
        $disk = $this->disk();
        while ($month->addMonth() <= $cutoff) {
            $to = $month->addMonth();
            $key = (int) $to->format('Ymd');
            if (DB::table('archived_records')->where('source_table', 'audit_logs')->where('record_id', $key)->exists()) {
                $month = $to;

                continue;
            }
            $path = $this->directory().'/audit_logs/'.$month->format('Y-m').'.jsonl';
            $tmp = tempnam(sys_get_temp_dir(), 'lcf-audit-');
            $out = fopen((string) $tmp, 'wb');
            $chains = [];
            $broken = [];
            $rows = 0;
            DB::table('audit_logs')->where('occurred_at', '>=', $month->format('Y-m-d H:i:s.u'))->where('occurred_at', '<', $to->format('Y-m-d H:i:s.u'))
                ->orderBy('id')->chunk(1000, function ($chunk) use ($out, &$chains, &$broken, &$rows): void {
                    foreach ($chunk as $row) {
                        $entry = (array) $row;
                        if (ChainVerifier::contentHash($entry) !== $row->hash) {
                            $broken[] = (int) $row->id;
                        }
                        $c = (int) $row->chain_id;
                        $chains[$c] ??= ['chain_id' => $c, 'first_seq' => (int) $row->chain_seq, 'first_prev_hash' => $row->prev_hash, 'last_seq' => (int) $row->chain_seq, 'last_hash' => $row->hash, 'entries' => 0];
                        if ((int) $row->chain_seq < $chains[$c]['first_seq']) {
                            $chains[$c]['first_seq'] = (int) $row->chain_seq;
                            $chains[$c]['first_prev_hash'] = $row->prev_hash;
                        }
                        if ((int) $row->chain_seq > $chains[$c]['last_seq']) {
                            $chains[$c]['last_seq'] = (int) $row->chain_seq;
                            $chains[$c]['last_hash'] = $row->hash;
                        }
                        $chains[$c]['entries']++;
                        fwrite($out, json_encode($entry, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)."\n");
                        $rows++;
                    }
                });
            fclose($out);
            $this->counts['scanned'] += $rows;
            if ($broken !== []) {
                @unlink((string) $tmp);
                throw new RuntimeException(__('retention.audit_chain_broken', ['month' => $month->format('Y-m'), 'count' => count($broken)]));
            }
            $hash = (string) hash_file('sha256', (string) $tmp);
            $proof = ['format' => 'lcf.audit-archive.v1', 'from' => $month->toIso8601ZuluString(), 'to' => $to->toIso8601ZuluString(), 'entries' => $rows,
                'sha256' => $hash, 'verified_at' => $now->toIso8601ZuluString(), 'chains' => array_values($chains)];
            $stream = fopen((string) $tmp, 'rb');
            Storage::disk($disk)->writeStream($path, $stream);
            is_resource($stream) && fclose($stream);
            @unlink((string) $tmp);
            Storage::disk($disk)->put(substr($path, 0, -6).'.proof.json', (string) json_encode($proof, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
            DB::table('archived_records')->insert([
                'organization_id' => $this->policy?->organization_id, 'source_table' => 'audit_logs', 'form_id' => null, 'record_id' => $key,
                'retention_run_id' => $this->run?->id, 'disk' => $disk, 'path' => $path, 'payload_hash' => $hash, 'archived_at' => $now->format('Y-m-d H:i:s.u'),
            ]);
            $this->counts['archived'] += $rows;
            $month = $to;
        }
    }

    private function emailLogs(CarbonImmutable $cutoff, CarbonImmutable $now): void
    {
        $this->logRows('email_queue', 'created_at', $cutoff, $now, static fn ($q) => $q->whereIn('status', ['sent', 'failed', 'cancelled']), static function (array $ids): void {
            // A resend points at the e-mail it repeated; it outlives it.
            DB::table('email_queue')->whereIn('resent_from_id', $ids)->update(['resent_from_id' => null]);
        });
    }

    private function notificationLogs(CarbonImmutable $cutoff, CarbonImmutable $now): void
    {
        $this->logRows('in_app_notifications', 'created_at', $cutoff, $now);
        $this->logRows('notification_deliveries', 'created_at', $cutoff, $now, static fn ($q) => $q->whereIn('status', ['sent', 'failed', 'cancelled']));
    }

    /**
     * Removes old rows of a log table in chunks, archiving or exporting them
     * first as the policy says.
     *
     * @param  (callable(Builder): mixed)|null  $where
     * @param  (callable(list<int>): void)|null  $before  run before a chunk is deleted
     */
    private function logRows(string $table, string $column, CarbonImmutable $cutoff, CarbonImmutable $now, ?callable $where = null, ?callable $before = null): void
    {
        $action = (string) $this->policy?->end_action;
        while (true) {
            $q = DB::table($table)->where($column, '<', $cutoff->format('Y-m-d H:i:s.u'));
            if ($where !== null) {
                $where($q);
            }
            $rows = $q->orderBy('id')->limit($action === 'archive' ? self::LOG_FILE_ROWS : 500)->get();
            if ($rows->isEmpty()) {
                return;
            }
            $ids = $rows->pluck('id')->map(static fn ($v) => (int) $v)->all();
            $this->counts['scanned'] += count($ids);
            if ($action === 'archive') {
                $lines = $rows->map(static fn ($r) => json_encode((array) $r, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES))->implode("\n")."\n";
                $disk = $this->disk();
                $path = $this->directory().'/'.$table.'/'.$ids[0].'.jsonl';
                Storage::disk($disk)->put($path, $lines);
                DB::table('archived_records')->insert([
                    'organization_id' => $this->policy?->organization_id, 'source_table' => $table, 'form_id' => null, 'record_id' => $ids[0],
                    'retention_run_id' => $this->run?->id, 'disk' => $disk, 'path' => $path, 'payload_hash' => hash('sha256', $lines), 'archived_at' => $now->format('Y-m-d H:i:s.u'),
                ]);
            } elseif ($action === 'export_then_delete') {
                foreach ($rows as $r) {
                    $this->exportLine(['table' => $table, 'row' => (array) $r]);
                }
            }
            foreach (array_chunk($ids, 500) as $chunk) {
                if ($before !== null) {
                    $before($chunk);
                }
                DB::table($table)->whereIn('id', $chunk)->delete();
            }
            $action === 'archive' ? $this->counts['archived'] += count($ids) : $this->counts['deleted'] += count($ids);
        }
    }

    // ---- files --------------------------------------------------------

    /** Finished custom downloads past the period: their files and job rows are removed. */
    private function downloadFiles(CarbonImmutable $cutoff, CarbonImmutable $now): void
    {
        $this->logRows('download_jobs', 'created_at', $cutoff, $now, static fn ($q) => $q->whereIn('status', ['completed', 'failed', 'expired', 'cancelled']), function (array $ids) use ($now): void {
            $fileIds = DB::table('download_jobs')->whereIn('id', $ids)->whereNotNull('file_id')->pluck('file_id')->map(static fn ($v) => (int) $v)->all();
            $this->dropFileContents($fileIds, $now, (string) $this->policy?->end_action === 'archive');
        });
    }

    /** Record attachments uploaded before the period: the file content is removed (or moved to cold storage), the record keeps a deleted reference. */
    private function attachments(CarbonImmutable $cutoff, CarbonImmutable $now): void
    {
        foreach ($this->forms() as $rt) {
            $this->holds->recompute($rt);
            $held = DB::table($rt->table)->where('legal_hold', true)->select('id');
            $base = fn () => DB::table('files')->where('form_id', $rt->form->id)->whereNotNull('record_id')->whereNull('deleted_at')->where('is_temporary', false)
                ->where('created_at', '<', $cutoff->format('Y-m-d H:i:s.u'));
            $this->counts['skipped'] += $base()->whereIn('record_id', $held)->count();
            $this->eachId($base()->whereNotIn('record_id', $held), function (int $id) use ($now): void {
                $this->counts['scanned']++;
                $this->dropFileContents([$id], $now, (string) $this->policy?->end_action === 'archive');
            });
        }
    }

    /** @param list<int> $fileIds */
    private function dropFileContents(array $fileIds, CarbonImmutable $now, bool $archive): void
    {
        foreach (DB::table('files')->whereIn('id', $fileIds ?: [0])->get() as $f) {
            try {
                $live = Storage::disk((string) $f->disk);
                if ($live->exists((string) $f->path)) {
                    if ($archive) {
                        $disk = $this->disk();
                        $path = $this->directory().'/files/'.$f->uuid;
                        Storage::disk($disk)->writeStream($path, $live->readStream((string) $f->path));
                        DB::table('archived_records')->insert([
                            'organization_id' => $f->organization_id, 'source_table' => 'files', 'form_id' => $f->form_id, 'record_id' => $f->id,
                            'retention_run_id' => $this->run?->id, 'disk' => $disk, 'path' => $path, 'payload_hash' => (string) $f->sha256, 'archived_at' => $now->format('Y-m-d H:i:s.u'),
                        ]);
                    } elseif ((string) $this->policy?->end_action === 'export_then_delete') {
                        $this->exportLine(['table' => 'files', 'row' => (array) $f, 'content_base64' => base64_encode((string) $live->get((string) $f->path))]);
                    }
                    $live->delete((string) $f->path);
                }
                DB::table('files')->where('id', $f->id)->update(['deleted_at' => $now->format('Y-m-d H:i:s.u')]);
                if ((string) $this->policy?->data_class === 'attachments') {
                    $archive ? $this->counts['archived']++ : $this->counts['deleted']++;
                }
            } catch (Throwable $e) {
                report($e);
                $this->counts['failed']++;
            }
        }
    }

    // ---- helpers ------------------------------------------------------

    /** Calls back with each id of the query, in chunks, while rows are removed underneath. */
    private function eachId(Builder $q, callable $fn): void
    {
        $last = 0;
        while (true) {
            $ids = (clone $q)->where('id', '>', $last)->orderBy('id')->limit(self::CHUNK)->pluck('id')->map(static fn ($v) => (int) $v)->all();
            if ($ids === []) {
                return;
            }
            foreach ($ids as $id) {
                $fn($id);
                $last = $id;
            }
        }
    }

    private function disk(): string
    {
        $disk = (string) ($this->policy->archive_disk ?? 'archive');

        return in_array($disk, self::ARCHIVE_DISKS, true) ? $disk : 'archive';
    }

    private function directory(): string
    {
        return 'retention/'.$this->policy?->organization_id.'/'.CarbonImmutable::parse((string) $this->run?->started_at)->format('Y/m').'/'.$this->run?->uuid;
    }

    /** @param array<string, mixed> $line */
    private function exportLine(array $line): void
    {
        $this->exportPath ??= (string) tempnam(sys_get_temp_dir(), 'lcf-retention-');
        file_put_contents($this->exportPath, json_encode($line, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)."\n", FILE_APPEND);
    }

    /** Keeps the run's export (export then delete) as a private file; returns its id. */
    private function finishExport(): ?int
    {
        if ($this->exportPath === null) {
            return null;
        }
        try {
            $name = 'retention-'.$this->policy?->data_class.'-'.CarbonImmutable::now('UTC')->format('Ymd-His').'.jsonl';
            $file = $this->files->storeGenerated($this->exportPath, $name, 'jsonl', 'application/x-ndjson', 'retention_run', ['owner_id' => $this->run?->id]);

            return $file->id;
        } finally {
            @unlink($this->exportPath);
            $this->exportPath = null;
        }
    }
}
