<?php

declare(strict_types=1);

namespace App\Modules\Records\Exchange;

use App\Modules\Access\FieldAccessResolver;
use App\Modules\Access\RecordScope;
use App\Modules\Audit\AuditWriter;
use App\Modules\Core\Correlation\CorrelationId;
use App\Modules\Core\I18n\Translator;
use App\Modules\Core\Settings\SettingsService;
use App\Modules\Identity\Models\User;
use App\Modules\Justification\JustificationGate;
use App\Modules\Notifications\InAppNotifier;
use App\Modules\Records\FileStore;
use App\Modules\Records\Jobs\RunImportJob;
use App\Modules\Records\Models\StoredFile;
use App\Modules\Records\Runtime\FormRuntime;
use App\Modules\Records\Runtime\FormRuntimes;
use App\Modules\Records\Runtime\InvalidValue;
use App\Modules\Records\Runtime\RecordException;
use App\Modules\Records\Runtime\RecordPipeline;
use App\Modules\Records\Runtime\RecordStore;
use App\Modules\Records\Runtime\SubmissionJournal;
use App\Modules\Records\Runtime\ValueCodec;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use OpenSpout\Common\Entity\Cell;
use OpenSpout\Common\Entity\Cell\StringCell;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Reader\CSV\Options as CsvReaderOptions;
use OpenSpout\Reader\CSV\Reader as CsvReader;
use OpenSpout\Reader\ReaderInterface;
use OpenSpout\Reader\XLSX\Options as XlsxReaderOptions;
use OpenSpout\Reader\XLSX\Reader as XlsxReader;
use OpenSpout\Writer\XLSX\Writer as XlsxWriter;
use Throwable;

/**
 * Background import of records from Excel or CSV (specification §4.15,
 * architecture §19.5). The user uploads a file, maps its columns to fields
 * (or applies a saved mapping), sees a validation preview of the first rows,
 * then starts a job that inserts, updates, or upserts by a key field, or only
 * checks every row (dry run). The job works in batches of 500 rows, one
 * transaction each; `last_committed_batch` and the per-row idempotency keys
 * (`import:{job}:{row}`) make a retry continue where it stopped without
 * creating duplicates. Every row goes through the record pipeline, so rules,
 * permissions, validation and optimistic concurrency apply as on a form.
 * Errors name the row and the column and are collected into an error report.
 */
final class ImportService
{
    public const BATCH = 500;

    public const PREVIEW_ROWS = 20;

    public const MODES = ['insert', 'update', 'upsert'];

    public const TRANSFORMS = ['none', 'trim', 'upper', 'lower'];

    /** Storages a key field may have: one comparable column. */
    private const KEY_STORAGES = ['string', 'text', 'email', 'url', 'int', 'decimal', 'number', 'date', 'choice'];

    public function __construct(
        private readonly SheetCodec $codec,
        private readonly RecordStore $store,
        private readonly RecordPipeline $pipeline,
        private readonly FieldAccessResolver $fieldAccess,
        private readonly RecordScope $scope,
        private readonly SettingsService $settings,
        private readonly AuditWriter $audit,
        private readonly JustificationGate $justifications,
        private readonly SubmissionJournal $journal,
        private readonly FileStore $files,
        private readonly InAppNotifier $notifier,
        private readonly CorrelationId $correlation,
        private readonly FormRuntimes $runtimes,
        private readonly Translator $translator,
    ) {}

    /**
     * Reads an uploaded file's header row and first rows, and suggests a
     * mapping: each column whose header names a field (its label in any
     * language, its Excel column name or its key) is mapped to it.
     *
     * @return array<string, mixed>
     */
    public function inspect(FormRuntime $rt, User $user, StoredFile $file): array
    {
        $format = self::format($file);
        $header = null;
        $samples = [];
        $total = 0;
        foreach ($this->rows($this->localPath($file), $format) as $cells) {
            if ($header === null) {
                $header = $cells;

                continue;
            }
            if (self::blank($cells)) {
                continue;
            }
            $total++;
            if (count($samples) < 5) {
                $samples[] = $cells;
            }
        }
        if ($header === null || array_filter($header, static fn ($h) => is_scalar($h) && trim((string) $h) !== '') === []) {
            throw new RecordException(422, 'import_empty', __('records.import.empty'));
        }
        $fields = $this->targets($rt, $user);
        $aliases = [];
        foreach ($fields as $f) {
            foreach ($this->codec->aliases($rt->fields[$f['uuid']]) as $alias) {
                $aliases[$alias][$f['uuid']] = true;
            }
        }
        foreach (['id', 'version'] as $col) {
            foreach ($this->translator->enabledLocales() as $locale) {
                $aliases[SheetCodec::norm((string) __("records.export.columns.{$col}", [], $locale))]["system:{$col}"] = true;
            }
        }
        $columns = [];
        $taken = [];
        foreach ($header as $index => $raw) {
            $text = trim(is_scalar($raw) ? (string) $raw : '');
            if ($text === '') {
                continue;
            }
            $match = array_keys($aliases[SheetCodec::norm($text)] ?? []);
            $target = count($match) === 1 && ! isset($taken[$match[0]]) ? $match[0] : null;
            if ($target !== null) {
                $taken[$target] = true;
            }
            $columns[] = [
                'column' => (int) $index,
                'header' => $text,
                'field' => $target !== null && ! str_starts_with($target, 'system:') ? $target : null,
                'system' => $target !== null && str_starts_with($target, 'system:') ? substr($target, 7) : null,
                'transform' => 'none',
                'samples' => array_map(static fn (array $row) => self::display($row[$index] ?? null), $samples),
            ];
        }

        return [
            'file' => $file->uuid,
            'name' => $file->original_name,
            'format' => $format,
            'total_rows' => $total,
            'max_rows' => (int) $this->settings->get('records', 'import_max_rows'),
            'columns' => $columns,
            'fields' => $fields,
        ];
    }

    /**
     * The fields a user may import into: importable, and editable by them on
     * create or on edit.
     *
     * @return list<array{uuid: string, key: string, label: string, required: bool, key_candidate: bool}>
     */
    public function targets(FormRuntime $rt, User $user): array
    {
        $create = $this->fieldAccess->resolve($user, $rt->form->id, $rt->form->uuid, $rt->definition, 'create')['fields'];
        $edit = $this->fieldAccess->resolve($user, $rt->form->id, $rt->form->uuid, $rt->definition, 'edit')['fields'];
        $out = [];
        foreach ($rt->mainFields() as $f) {
            if (! $this->codec->importable($rt, $f)) {
                continue;
            }
            if (($create[$f['uuid']] ?? 'editable') !== 'editable' && ($edit[$f['uuid']] ?? 'editable') !== 'editable') {
                continue;
            }
            $out[] = [
                'uuid' => $f['uuid'], 'key' => $f['key'], 'label' => $this->codec->header($f),
                'required' => (bool) ($f['validation']['required'] ?? false),
                'key_candidate' => $this->keyCandidate($rt, $f),
            ];
        }

        return $out;
    }

    /**
     * Checks a mapping and returns it in the form the job stores: the column
     * index, its header, and the field uuid or system column it fills.
     *
     * @param  list<array<string, mixed>>  $mapping
     * @return array{columns: list<array{column: int, header: string, field: string|null, system: string|null, transform: string}>, key: array<string, mixed>|null}
     */
    public function normalize(FormRuntime $rt, User $user, StoredFile $file, array $mapping, string $mode, ?string $keyField): array
    {
        $header = [];
        foreach ($this->rows($this->localPath($file), self::format($file)) as $cells) {
            $header = $cells;
            break;
        }
        $allowed = array_column($this->targets($rt, $user), null, 'uuid');
        $errors = [];
        $columns = [];
        $seen = [];
        foreach ($mapping as $i => $m) {
            $column = (int) ($m['column'] ?? -1);
            $field = $m['field'] ?? null;
            $system = $m['system'] ?? null;
            if ($field === null && $system === null) {
                continue;
            }
            $headerText = trim(is_scalar($header[$column] ?? null) ? (string) $header[$column] : '');
            if ($headerText === '') {
                $errors["mapping.{$i}"][] = __('records.import.mapping.no_column');

                continue;
            }
            if ($field !== null && ! isset($allowed[$field])) {
                $errors["mapping.{$i}"][] = __('records.import.mapping.field_not_allowed', ['column' => $headerText]);

                continue;
            }
            if ($system !== null && ($mode === 'insert' || ! in_array($system, ['id', 'version'], true))) {
                $errors["mapping.{$i}"][] = __('records.import.mapping.system_not_allowed', ['column' => $headerText]);

                continue;
            }
            $target = $field ?? "system:{$system}";
            if (isset($seen[$target])) {
                $errors["mapping.{$i}"][] = __('records.import.mapping.duplicate', ['column' => $headerText, 'other' => $seen[$target]]);

                continue;
            }
            $seen[$target] = $headerText;
            $transform = in_array($m['transform'] ?? 'none', self::TRANSFORMS, true) ? ($m['transform'] ?? 'none') : 'none';
            $columns[] = ['column' => $column, 'header' => $headerText, 'field' => $field, 'system' => $system, 'transform' => $transform];
        }
        if (array_filter($columns, static fn ($c) => $c['field'] !== null) === []) {
            $errors['mapping'][] = __('records.import.no_columns');
        }
        $key = null;
        if ($mode !== 'insert') {
            if ($keyField !== null) {
                $key = $rt->fields[$keyField] ?? null;
                if ($key === null || ! isset($allowed[$keyField]) || ! $this->keyCandidate($rt, $key)) {
                    $errors['key_field'][] = __('records.import.mapping.key_not_allowed');
                } elseif (! isset($seen[$keyField])) {
                    $errors['key_field'][] = __('records.import.mapping.key_not_mapped', ['field' => $allowed[$keyField]['label']]);
                }
            } elseif (! isset($seen['system:id'])) {
                $errors['key_field'][] = __('records.import.mapping.key_required');
            }
        }
        if ($errors !== []) {
            throw new RecordException(422, 'import_mapping_invalid', __('records.import.mapping.invalid'), ['errors' => $errors]);
        }

        return ['columns' => $columns, 'key' => $key];
    }

    /**
     * Validation preview: the first rows checked through the pipeline without
     * writing anything.
     *
     * @param  array{columns: list<array<string, mixed>>, key: array<string, mixed>|null}  $mapping
     * @return array<string, mixed>
     */
    public function preview(FormRuntime $rt, User $user, StoredFile $file, array $mapping, string $mode): array
    {
        $rows = [];
        $n = 0;
        foreach ($this->dataRows($this->localPath($file), self::format($file)) as $rowNumber => $cells) {
            if ($n++ >= self::PREVIEW_ROWS) {
                break;
            }
            $prepared = $this->prepare($rt, $user, $mapping, $cells, $mode);
            $rows[] = ['row' => $rowNumber, 'action' => $prepared['action'], 'values' => $this->previewValues($mapping, $cells), 'errors' => $this->errorList($rt, $mapping, $prepared['errors'])];
        }

        return [
            'rows' => $rows,
            'valid' => count(array_filter($rows, static fn ($r) => $r['errors'] === [])),
            'invalid' => count(array_filter($rows, static fn ($r) => $r['errors'] !== [])),
        ];
    }

    /**
     * Starts an import job (or a dry run). Imports governed by an import
     * justification rule need the justification here, once for the whole job.
     *
     * @param  array{columns: list<array<string, mixed>>, key: array<string, mixed>|null}  $mapping
     * @param  array<string, mixed>|null  $justification
     */
    public function start(FormRuntime $rt, User $user, StoredFile $file, array $mapping, string $mode, bool $dryRun, ?int $savedMappingId, ?array $justification): object
    {
        $validated = $dryRun ? null : $this->justifications->enforce($rt, $user, 'import', [], $justification);
        $now = Carbon::now('UTC')->format('Y-m-d H:i:s.u');
        $uuid = (string) Str::uuid7();
        $id = DB::transaction(function () use ($rt, $user, $file, $mapping, $mode, $dryRun, $savedMappingId, $validated, $now, $uuid): int {
            $justificationId = $validated === null ? null : $this->justifications->record($rt, null, $user, 'import', $validated, [], 0);
            $id = (int) DB::table('import_jobs')->insertGetId([
                'uuid' => $uuid, 'organization_id' => $rt->form->organization_id, 'created_at' => $now, 'updated_at' => $now,
                'form_id' => $rt->form->id, 'user_id' => $user->id, 'file_id' => $file->id, 'import_mapping_id' => $savedMappingId,
                'mapping' => json_encode($mapping['columns'], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR), 'mode' => $mode,
                'key_field_id' => $mapping['key'] === null ? null : DB::table('fields')->where('uuid', $mapping['key']['uuid'])->value('id'),
                'dry_run' => $dryRun, 'status' => 'queued', 'justification_id' => $justificationId, 'correlation_id' => $this->correlation->get(),
            ]);
            if ($justificationId !== null) {
                DB::table('justifications')->where('id', $justificationId)->update(['import_job_id' => $id]);
            }
            $file->forceFill(['is_temporary' => false, 'owner_type' => 'import_job', 'owner_id' => $id])->save();
            $this->audit->record('records.import_started', 'data', null, 'form', $rt->form->id, [
                'job' => $uuid, 'mode' => $mode, 'dry_run' => $dryRun, 'file' => $file->original_name, 'sha256' => $file->sha256,
            ], $user->id, null, $rt->form->id, null, null, $justificationId);

            return $id;
        });
        RunImportJob::dispatch($id)->afterCommit();

        return DB::table('import_jobs')->where('id', $id)->first();
    }

    /** Cancels a queued or running job; a running job stops at its next batch. */
    public function cancel(object $job, User $user): void
    {
        $done = DB::table('import_jobs')->where('id', $job->id)->whereIn('status', ['queued', 'validating', 'running'])
            ->update(['status' => 'cancelled', 'updated_at' => Carbon::now('UTC')->format('Y-m-d H:i:s.u')]);
        if ($done > 0) {
            $this->audit->record('records.import_cancelled', 'data', null, 'form', (int) $job->form_id, ['job' => $job->uuid], $user->id, null, (int) $job->form_id);
        }
    }

    /** Runs (or resumes) a job; called by RunImportJob. */
    public function run(int $jobId): void
    {
        $job = DB::table('import_jobs')->where('id', $jobId)->first();
        if ($job === null || ! in_array($job->status, ['queued', 'validating', 'running', 'failed'], true)) {
            return;
        }
        $user = User::query()->find($job->user_id);
        $form = DB::table('forms')->where('id', $job->form_id)->first();
        $rt = $form === null ? null : $this->runtimes->forUuid((string) $form->uuid);
        if ($user === null || $rt === null || $user->status !== 'active') {
            $this->finishFailed($job, __('records.import.job_unavailable'));

            return;
        }
        Auth::setUser($user);
        $file = StoredFile::query()->withoutGlobalScopes()->find($job->file_id);
        if ($file === null) {
            $this->finishFailed($job, __('records.import.job_unavailable'));

            return;
        }
        $started = microtime(true);
        $limitSeconds = 60 * (int) $this->settings->get('records', 'import_time_limit_minutes');
        $path = $this->localPath($file);
        $format = self::format($file);
        $this->update($job->id, ['status' => 'validating', 'started_at' => $job->started_at ?? Carbon::now('UTC')->format('Y-m-d H:i:s.u'), 'error' => null]);

        $total = 0;
        foreach ($this->dataRows($path, $format) as $ignored) {
            $total++;
        }
        $max = (int) $this->settings->get('records', 'import_max_rows');
        if ($total > $max) {
            $this->finishFailed($job, __('records.import.too_large', ['max' => $max]));

            return;
        }
        $this->update($job->id, ['status' => 'running', 'total_rows' => $total]);
        $columns = json_decode((string) $job->mapping, true);
        $keyUuid = $job->key_field_id === null ? null : DB::table('fields')->where('id', $job->key_field_id)->value('uuid');
        $mapping = ['columns' => $columns, 'key' => $keyUuid === null ? null : ($rt->fields[strtolower((string) $keyUuid)] ?? null)];
        $errorsPath = "imports/{$job->uuid}";
        $batch = [];
        $batchNo = 0;
        $last = (int) $job->last_committed_batch;
        $stopped = null;
        foreach ($this->dataRows($path, $format) as $rowNumber => $cells) {
            $batch[$rowNumber] = $cells;
            if (count($batch) < self::BATCH) {
                continue;
            }
            $batchNo++;
            if ($batchNo > $last && ($stopped = $this->batch($job, $rt, $user, $mapping, $batch, $batchNo, $errorsPath, $started, $limitSeconds)) !== null) {
                break;
            }
            $batch = [];
        }
        if ($stopped === null && $batch !== []) {
            $batchNo++;
            if ($batchNo > $last) {
                $stopped = $this->batch($job, $rt, $user, $mapping, $batch, $batchNo, $errorsPath, $started, $limitSeconds);
            }
        }
        $this->finish($job->id, $rt, $user, $mapping, $errorsPath, $stopped);
    }

    /**
     * One batch in one transaction. Returns why the job stopped before it
     * (cancelled, time limit), or null.
     *
     * @param  array{columns: list<array<string, mixed>>, key: array<string, mixed>|null}  $mapping
     * @param  array<int, list<mixed>>  $rows
     */
    private function batch(object $job, FormRuntime $rt, User $user, array $mapping, array $rows, int $batchNo, string $errorsPath, float $started, int $limitSeconds): ?string
    {
        $status = DB::table('import_jobs')->where('id', $job->id)->value('status');
        if ($status === 'cancelled') {
            return 'cancelled';
        }
        if ($limitSeconds > 0 && microtime(true) - $started > $limitSeconds) {
            return 'time_limit';
        }
        $counts = ['created' => 0, 'updated' => 0, 'errors' => 0, 'processed' => 0];
        $failed = [];
        DB::transaction(function () use ($job, $rt, $user, $mapping, $rows, $batchNo, $errorsPath, &$counts, &$failed): void {
            $this->journal->forImportJob((int) $job->id, function () use ($job, $rt, $user, $mapping, $rows, &$counts, &$failed): void {
                foreach ($rows as $rowNumber => $cells) {
                    $counts['processed']++;
                    $prepared = $this->prepare($rt, $user, $mapping, $cells, (string) $job->mode);
                    if ($prepared['errors'] === [] && ! $job->dry_run) {
                        $key = "import:{$job->uuid}:{$rowNumber}";
                        try {
                            if ($prepared['current'] === null) {
                                $this->pipeline->create($rt, $user, $prepared['input'], [], $key, 'import');
                            } else {
                                $this->pipeline->update($rt, $user, $prepared['current']['uuid'], $prepared['version'], $prepared['input'], $key, 'import');
                            }
                        } catch (RecordException $e) {
                            $prepared['errors'] = $e->payload['errors'] ?? ['_' => [$e->getMessage()]];
                        }
                    }
                    if ($prepared['errors'] !== []) {
                        $counts['errors']++;
                        $failed[] = ['row' => $rowNumber, 'cells' => array_map(static fn ($c) => self::display($c), $cells), 'errors' => $this->errorList($rt, $mapping, $prepared['errors'])];
                    } else {
                        $counts[$prepared['action'] === 'create' ? 'created' : 'updated']++;
                    }
                }
            });
            DB::table('import_jobs')->where('id', $job->id)->update([
                'processed_rows' => DB::raw('processed_rows + '.$counts['processed']),
                'created_count' => DB::raw('created_count + '.$counts['created']),
                'updated_count' => DB::raw('updated_count + '.$counts['updated']),
                'error_count' => DB::raw('error_count + '.$counts['errors']),
                'last_committed_batch' => $batchNo,
                'updated_at' => Carbon::now('UTC')->format('Y-m-d H:i:s.u'),
            ]);
            // The batch's errors, written before it commits; a batch that runs again overwrites its own file.
            $file = "{$errorsPath}/batch-{$batchNo}.jsonl";
            $failed === []
                ? Storage::disk(FileStore::DISK)->delete($file)
                : Storage::disk(FileStore::DISK)->put($file, implode("\n", array_map(static fn (array $f) => json_encode($f, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR), $failed)));
        });

        return null;
    }

    /**
     * @param  array{columns: list<array<string, mixed>>, key: array<string, mixed>|null}  $mapping
     */
    private function finish(int $jobId, FormRuntime $rt, User $user, array $mapping, string $errorsPath, ?string $stopped): void
    {
        $job = DB::table('import_jobs')->where('id', $jobId)->first();
        $reportId = null;
        if ((int) $job->error_count > 0) {
            $reportId = $this->errorReport($job, $rt, $mapping, $errorsPath)->id;
        }
        $status = match (true) {
            $stopped === 'cancelled' || $job->status === 'cancelled' => 'cancelled',
            $stopped === 'time_limit' => 'failed',
            (int) $job->error_count > 0 => 'completed_with_errors',
            default => 'completed',
        };
        $now = Carbon::now('UTC')->format('Y-m-d H:i:s.u');
        DB::table('import_jobs')->where('id', $jobId)->update([
            'status' => $status, 'finished_at' => $now, 'updated_at' => $now, 'error_report_file_id' => $reportId,
            'error' => $stopped === 'time_limit' ? __('records.import.time_limit', ['minutes' => (int) $this->settings->get('records', 'import_time_limit_minutes')]) : null,
        ]);
        $this->audit->record($job->dry_run ? 'records.import_checked' : 'records.imported', 'data', null, 'form', $rt->form->id, [
            'job' => $job->uuid, 'status' => $status, 'mode' => $job->mode, 'total' => (int) $job->total_rows,
            'created' => (int) $job->created_count, 'updated' => (int) $job->updated_count, 'errors' => (int) $job->error_count,
        ], $user->id, null, $rt->form->id);
        $form = $this->translator->labelOf($rt->form->translationsFor('name'), (string) $rt->form->key);
        $this->notifier->notify($user->id, 'import.finished', $job->dry_run ? "records.import.notify.checked_{$status}" : "records.import.notify.{$status}", [
            'form' => $form, 'created' => (int) $job->created_count, 'updated' => (int) $job->updated_count, 'errors' => (int) $job->error_count,
        ], "/app/{$rt->form->uuid}?import={$job->uuid}", ['import_job' => $job->uuid], ['form_id' => $rt->form->id]);
    }

    private function finishFailed(object $job, string $error): void
    {
        $now = Carbon::now('UTC')->format('Y-m-d H:i:s.u');
        DB::table('import_jobs')->where('id', $job->id)->update(['status' => 'failed', 'error' => mb_substr($error, 0, 2000), 'finished_at' => $now, 'updated_at' => $now]);
        $this->notifier->notify((int) $job->user_id, 'import.finished', 'records.import.notify.failed', ['error' => $error], null, ['import_job' => $job->uuid], ['form_id' => (int) $job->form_id]);
    }

    /** Called when the queue gives up on the job (an unexpected error). */
    public function crashed(int $jobId, Throwable $e): void
    {
        $job = DB::table('import_jobs')->where('id', $jobId)->first();
        if ($job !== null && $job->status !== 'cancelled') {
            $this->finishFailed($job, __('records.import.crashed', ['reference' => $this->correlation->get()]));
        }
    }

    /**
     * The error report: the rows that failed, with their original cells and a
     * last column naming each problem by column.
     *
     * @param  array{columns: list<array<string, mixed>>, key: array<string, mixed>|null}  $mapping
     */
    private function errorReport(object $job, FormRuntime $rt, array $mapping, string $errorsPath): StoredFile
    {
        $file = StoredFile::query()->withoutGlobalScopes()->find($job->file_id);
        $header = [];
        foreach ($this->rows($this->localPath($file), self::format($file)) as $cells) {
            $header = array_map(static fn ($c) => self::display($c), $cells);
            break;
        }
        $rows = [];
        for ($b = 1; $b <= (int) $job->last_committed_batch; $b++) {
            foreach (explode("\n", (string) Storage::disk(FileStore::DISK)->get("{$errorsPath}/batch-{$b}.jsonl")) as $line) {
                if (trim($line) !== '') {
                    $e = json_decode($line, true);
                    $rows[(int) $e['row']] = $e;
                }
            }
        }
        ksort($rows);
        $path = (string) tempnam(sys_get_temp_dir(), 'lcf-import-report-');
        $writer = new XlsxWriter;
        $writer->openToFile($path);
        $writer->addRow(new Row(array_map(static fn ($h) => new StringCell((string) $h, null), [__('records.import.report.row'), ...$header, __('records.import.report.errors')]), (new Style)->setFontBold()));
        foreach ($rows as $r) {
            $problems = array_map(static fn (array $e) => ($e['column'] !== null ? $e['column'].': ' : '').implode(' ', $e['messages']), $r['errors']);
            $cells = [Cell::fromValue((int) $r['row'])];
            foreach (array_keys($header) as $i) {
                $cells[] = new StringCell((string) ($r['cells'][$i] ?? ''), null);
            }
            $cells[] = new StringCell(implode("\n", $problems), null);
            $writer->addRow(new Row($cells));
        }
        $writer->close();
        $name = Str::slug((string) $rt->form->key).'-'.__('records.import.report.file_suffix');
        $stored = $this->files->storeGenerated($path, $name, 'xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'import_report', ['owner_id' => (int) $job->id, 'user_id' => (int) $job->user_id]);
        @unlink($path);

        return $stored;
    }

    /**
     * One spreadsheet row as pipeline input: mapped cells converted, the
     * record it updates found (by ID or key field), and every check of the
     * pipeline run without writing.
     *
     * @param  array{columns: list<array<string, mixed>>, key: array<string, mixed>|null}  $mapping
     * @param  list<mixed>  $cells
     * @return array{input: array<string, mixed>, current: array<string, mixed>|null, version: int, errors: array<string, list<string>>, action: string}
     */
    public function prepare(FormRuntime $rt, User $user, array $mapping, array $cells, string $mode): array
    {
        $input = [];
        $errors = [];
        $id = null;
        $version = null;
        foreach ($mapping['columns'] as $m) {
            $cell = $cells[$m['column']] ?? null;
            if (is_string($cell) && preg_match("/^'[=+\\-@\t\r]/", $cell) === 1) {
                $cell = substr($cell, 1); // a value escaped on export reads back as itself
            }
            if (is_string($cell)) {
                $cell = match ($m['transform']) {
                    'trim' => trim($cell),
                    'upper' => mb_strtoupper($cell),
                    'lower' => mb_strtolower($cell),
                    default => $cell,
                };
            }
            if ($m['system'] === 'id') {
                $id = is_scalar($cell) && trim((string) $cell) !== '' ? strtolower(trim((string) $cell)) : null;

                continue;
            }
            if ($m['system'] === 'version') {
                $version = is_numeric($cell) ? (int) $cell : null;

                continue;
            }
            $f = $rt->fields[$m['field']] ?? null;
            if ($f === null) {
                continue;
            }
            try {
                $input[$f['key']] = $this->codec->fromCell($rt, $f, $cell);
            } catch (InvalidValue $e) {
                $errors[$f['key']][] = __($e->key, $e->params);
            }
        }
        $current = null;
        if ($mode !== 'insert') {
            if ($id !== null) {
                if (preg_match(ValueCodec::UUID, $id) !== 1 || ($current = $this->visible($rt, $user, 'uuid', $id)) === null) {
                    $errors['_id'][] = __('records.import.record_not_found', ['id' => $id]);
                }
            } elseif ($mapping['key'] !== null) {
                $value = $input[$mapping['key']['key']] ?? null;
                if ($value === null || $value === '') {
                    $errors[$mapping['key']['key']][] = __('records.import.key_empty');
                } else {
                    $column = $rt->column($mapping['key']['uuid'])['name'] ?? null;
                    $matches = $column === null ? [] : $this->matches($rt, $user, $column, $value);
                    if (count($matches) > 1) {
                        $errors[$mapping['key']['key']][] = __('records.import.key_ambiguous');
                    } elseif ($matches !== []) {
                        $current = $this->store->find($rt, $matches[0]);
                    }
                }
            }
            if ($current === null && $mode === 'update' && ! isset($errors['_id']) && ! isset($errors[$mapping['key']['key'] ?? '_id'])) {
                $errors['_'][] = __('records.import.no_match');
            }
        }
        if ($current !== null) {
            if ($version !== null && $version !== (int) $current['row_version']) {
                $errors['_version'][] = __('records.import.version_changed', ['expected' => $version, 'current' => $current['row_version']]);
            }
            // The import read the record just now: its version is the one it updates (a change in between is a conflict).
            $version ??= (int) $current['row_version'];
            // Empty cells of an update leave the stored value untouched.
            $input = array_filter($input, static fn ($v) => $v !== null);
        }
        // Every problem of the row at once: the pipeline's checks add to the conversion errors.
        try {
            $errors += $this->pipeline->check($rt, $user, $input, $current);
        } catch (RecordException $e) {
            $errors['_'][] = $e->getMessage();
        }

        return ['input' => $input, 'current' => $current, 'version' => (int) ($version ?? 0), 'errors' => $errors, 'action' => $current === null ? 'create' : 'update'];
    }

    /** @return array<string, mixed>|null a record the user may see */
    private function visible(FormRuntime $rt, User $user, string $column, string $value): ?array
    {
        $uuid = $this->scope->apply(DB::table($rt->table)->whereNull('deleted_at')->where($column, $value), $rt, $user, 'view')->value('uuid');

        return $uuid === null ? null : $this->store->find($rt, strtolower((string) $uuid));
    }

    /** @return list<string> uuids of the records (that the user may see) whose key column holds the value */
    private function matches(FormRuntime $rt, User $user, string $column, mixed $value): array
    {
        return $this->scope->apply(DB::table($rt->table)->whereNull('deleted_at')->where($column, is_scalar($value) ? $value : json_encode($value)), $rt, $user, 'view')
            ->limit(2)->pluck('uuid')->map(static fn ($u) => strtolower((string) $u))->all();
    }

    /** @param  array<string, mixed>  $field */
    private function keyCandidate(FormRuntime $rt, array $field): bool
    {
        $type = $rt->type($field);

        return in_array($type?->storage, self::KEY_STORAGES, true) && $rt->targetTable($field) === null && $rt->column($field['uuid']) !== null;
    }

    /**
     * Errors by column header (or a general error) for reports and the preview.
     *
     * @param  array{columns: list<array<string, mixed>>, key: array<string, mixed>|null}  $mapping
     * @param  array<string, list<string>|string>  $errors
     * @return list<array{column: string|null, messages: list<string>}>
     */
    private function errorList(FormRuntime $rt, array $mapping, array $errors): array
    {
        $headers = [];
        foreach ($mapping['columns'] as $m) {
            if ($m['field'] !== null && isset($rt->fields[$m['field']])) {
                $headers[$rt->fields[$m['field']]['key']] = $m['header'];
            } elseif ($m['system'] !== null) {
                $headers['_'.$m['system']] = $m['header'];
            }
        }
        $out = [];
        foreach ($errors as $key => $messages) {
            $base = explode('.', (string) $key)[0];
            $label = $headers[$base] ?? (isset($rt->keys[$base]) ? $this->codec->header($rt->fields[$rt->keys[$base]]) : null);
            $out[] = ['column' => $label, 'messages' => array_values(array_map('strval', (array) $messages))];
        }

        return $out;
    }

    /**
     * @param  array{columns: list<array<string, mixed>>, key: array<string, mixed>|null}  $mapping
     * @param  list<mixed>  $cells
     * @return list<string|null>
     */
    private function previewValues(array $mapping, array $cells): array
    {
        return array_map(static fn (array $m) => self::display($cells[$m['column']] ?? null), $mapping['columns']);
    }

    /** @param  array<string, mixed>  $values */
    private function update(int $id, array $values): void
    {
        DB::table('import_jobs')->where('id', $id)->update($values + ['updated_at' => Carbon::now('UTC')->format('Y-m-d H:i:s.u')]);
    }

    /**
     * Data rows by sheet row number (the header is row 1), skipping blank rows.
     *
     * @return \Generator<int, list<mixed>>
     */
    private function dataRows(string $path, string $format): \Generator
    {
        $n = 0;
        foreach ($this->rows($path, $format) as $cells) {
            $n++;
            if ($n === 1 || self::blank($cells)) {
                continue;
            }
            yield $n => $cells;
        }
    }

    /**
     * Rows of the first sheet, read as a stream (external entities off,
     * formulas read as their text, never evaluated).
     *
     * @return \Generator<int, list<mixed>>
     */
    private function rows(string $path, string $format): \Generator
    {
        $reader = $this->reader($path, $format);
        $reader->open($path);
        try {
            foreach ($reader->getSheetIterator() as $sheet) {
                foreach ($sheet->getRowIterator() as $row) {
                    // A formula cell yields its formula text: nothing is evaluated.
                    yield array_map(static fn (Cell $c) => $c->getValue(), $row->getCells());
                }
                break;
            }
        } finally {
            $reader->close();
        }
    }

    private function reader(string $path, string $format): ReaderInterface
    {
        if ($format === 'xlsx') {
            $options = new XlsxReaderOptions;
            $options->SHOULD_FORMAT_DATES = false;

            return new XlsxReader($options);
        }
        $options = new CsvReaderOptions;
        $handle = fopen($path, 'r') ?: throw new RecordException(422, 'import_unreadable', __('records.import.unreadable'));
        $first = (string) fgets($handle);
        fclose($handle);
        $options->FIELD_DELIMITER = substr_count($first, ';') > substr_count($first, ',') ? ';' : ',';

        return new CsvReader($options);
    }

    /** A local path of a stored file (the private disk may be remote). */
    private function localPath(StoredFile $file): string
    {
        $disk = Storage::disk($file->disk);
        if (method_exists($disk, 'path') && is_file($p = $disk->path($file->path))) {
            return $p;
        }
        $tmp = (string) tempnam(sys_get_temp_dir(), 'lcf-import-');
        file_put_contents($tmp, (string) $disk->get($file->path));

        return $tmp;
    }

    public static function format(StoredFile $file): string
    {
        return $file->extension === 'csv' || $file->extension === 'txt' ? 'csv' : 'xlsx';
    }

    /** @param  list<mixed>  $cells */
    private static function blank(array $cells): bool
    {
        return array_filter($cells, static fn ($c) => $c !== null && $c !== '') === [];
    }

    private static function display(mixed $cell): ?string
    {
        return match (true) {
            $cell === null => null,
            $cell instanceof \DateTimeInterface => $cell->format('Y-m-d H:i:s'),
            is_bool($cell) => $cell ? 'TRUE' : 'FALSE',
            is_scalar($cell) => (string) $cell,
            default => null,
        };
    }
}
