<?php

declare(strict_types=1);

namespace App\Modules\Records\Exchange;

use App\Modules\Access\FieldAccessResolver;
use App\Modules\Audit\AuditWriter;
use App\Modules\Core\Correlation\CorrelationId;
use App\Modules\Core\I18n\Translator;
use App\Modules\Core\Settings\SettingsService;
use App\Modules\Identity\Models\User;
use App\Modules\Notifications\InAppNotifier;
use App\Modules\Records\FileStore;
use App\Modules\Records\Jobs\RunExportJob;
use App\Modules\Records\Models\StoredFile;
use App\Modules\Records\Runtime\FormRuntime;
use App\Modules\Records\Runtime\FormRuntimes;
use App\Modules\Records\Runtime\RecordQuery;
use App\Modules\Records\Runtime\RecordStore;
use App\Modules\Records\Runtime\References;
use App\Modules\Views\PrintRenderer;
use App\Modules\Views\ViewRuntime;
use App\Support\Csv;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use OpenSpout\Common\Entity\Cell;
use OpenSpout\Common\Entity\Cell\StringCell;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Writer\CSV\Writer as CsvWriter;
use OpenSpout\Writer\XLSX\Entity\SheetView;
use OpenSpout\Writer\XLSX\Options as XlsxOptions;
use OpenSpout\Writer\XLSX\Writer as XlsxWriter;
use Throwable;

/**
 * Built-in export to Excel, CSV or PDF (specification §4.15, architecture
 * §19.5). An export writes what the user sees in the records table: the
 * active view's visible columns (linked columns included) or, without a
 * view, every field the user may see; the current filters, search and
 * selection; only records within the user's record-level scope. Small
 * exports are produced at once; larger ones run as a background job with
 * progress and a notification. The file is the user's own and expires.
 * CSV and Excel cells that start with a formula character are escaped.
 */
final class ExportService
{
    public const FORMATS = ['xlsx', 'csv', 'pdf'];

    private const SYSTEM = ['id', 'version', 'record_number', 'created_at', 'updated_at'];

    private const PAGE = 500;

    public function __construct(
        private readonly SheetCodec $codec,
        private readonly RecordStore $store,
        private readonly RecordQuery $query,
        private readonly FieldAccessResolver $fieldAccess,
        private readonly References $refs,
        private readonly SettingsService $settings,
        private readonly AuditWriter $audit,
        private readonly FileStore $files,
        private readonly InAppNotifier $notifier,
        private readonly CorrelationId $correlation,
        private readonly FormRuntimes $runtimes,
        private readonly Translator $translator,
        private readonly PrintRenderer $printer,
    ) {}

    /**
     * Creates the export job; runs it at once when it is small.
     *
     * @param  array<string, mixed>  $filters  validated by RecordQuery::rules() plus `view`, `vf`, `columns`
     */
    public function start(FormRuntime $rt, User $user, string $format, array $filters): object
    {
        $plan = $this->plan($rt, $user, $filters);
        $count = min((clone $plan['query'])->count(), (int) $this->settings->get('records', 'export_max_rows'));
        $now = Carbon::now('UTC')->format('Y-m-d H:i:s.u');
        $id = (int) DB::table('export_jobs')->insertGetId([
            'uuid' => (string) Str::uuid7(), 'organization_id' => $rt->form->organization_id, 'created_at' => $now, 'updated_at' => $now,
            'form_id' => $rt->form->id, 'view_id' => $plan['view'] === null ? null : DB::table('views')->where('uuid', $plan['view']['uuid'])->value('id'),
            'user_id' => $user->id, 'format' => $format,
            'columns' => json_encode(array_map(static fn ($c) => ['key' => $c['key'], 'label' => $c['label']], $plan['columns']), JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
            'filters' => json_encode(array_diff_key($filters, ['uuids' => true]), JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
            'selected_ids' => isset($filters['uuids']) ? json_encode(array_values($filters['uuids']), JSON_THROW_ON_ERROR) : null,
            'status' => 'queued', 'row_count' => $count, 'correlation_id' => $this->correlation->get(),
        ]);
        $queued = $count > (int) $this->settings->get('records', 'export_sync_rows');
        $queued ? RunExportJob::dispatch($id) : $this->run($id, false);
        $job = DB::table('export_jobs')->where('id', $id)->first();
        $job->queued = $queued;

        return $job;
    }

    /** Runs a queued export; called by RunExportJob (and inline for small exports). */
    public function run(int $jobId, bool $notify = true): void
    {
        $job = DB::table('export_jobs')->where('id', $jobId)->first();
        if ($job === null || ! in_array($job->status, ['queued', 'failed'], true)) {
            return;
        }
        $user = User::query()->find($job->user_id);
        $form = DB::table('forms')->where('id', $job->form_id)->first();
        $rt = $form === null ? null : $this->runtimes->forUuid((string) $form->uuid);
        if ($user === null || $rt === null || $user->status !== 'active') {
            $this->fail($job, __('records.export.job_unavailable'), $notify);

            return;
        }
        Auth::setUser($user);
        $this->update($jobId, ['status' => 'running', 'progress' => 0, 'error' => null]);
        $filters = (array) json_decode((string) $job->filters, true);
        if ($job->selected_ids !== null) {
            $filters['uuids'] = json_decode((string) $job->selected_ids, true);
        }
        // The plan is made again under the user's permissions as they are now.
        $plan = $this->plan($rt, $user, $filters);
        $path = (string) tempnam(sys_get_temp_dir(), 'lcf-export-');
        $written = $this->write($rt, $user, $plan, $job, $path);
        $name = $this->translator->labelOf($rt->form->translationsFor('name'), (string) $rt->form->key).' '.Carbon::now('UTC')->format('Y-m-d His');
        $mime = match ($job->format) {
            'csv' => 'text/csv',
            'pdf' => 'application/pdf',
            default => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        };
        $stored = $this->files->storeGenerated($path, $name, (string) $job->format, $mime, 'export_job', ['owner_id' => $jobId, 'user_id' => $user->id]);
        @unlink($path);
        $hours = (int) $this->settings->get('records', 'job_file_hours');
        $now = Carbon::now('UTC');
        $this->update($jobId, ['status' => 'completed', 'progress' => 100, 'row_count' => $written['rows'], 'file_id' => $stored->id, 'expires_at' => $now->copy()->addHours($hours)->format('Y-m-d H:i:s.u')]);
        $this->audit->record('records.exported', 'data', null, 'form', $rt->form->id, [
            'job' => $job->uuid, 'format' => $job->format, 'rows' => $written['rows'], 'truncated' => $written['truncated'],
            'view' => $plan['view']['uuid'] ?? null, 'search' => $filters['search'] ?? null, 'filter' => array_keys((array) ($filters['filter'] ?? [])),
            'selected' => isset($filters['uuids']) ? count((array) $filters['uuids']) : null,
        ], $user->id, null, $rt->form->id);
        if ($notify) {
            $form = $this->translator->labelOf($rt->form->translationsFor('name'), (string) $rt->form->key);
            $this->notifier->notify($user->id, 'export.ready', 'records.export.notify.ready', ['form' => $form, 'rows' => $written['rows']],
                "/app/{$rt->form->uuid}?export={$job->uuid}", ['export_job' => $job->uuid], ['form_id' => $rt->form->id]);
        }
    }

    public function crashed(int $jobId, Throwable $e): void
    {
        $job = DB::table('export_jobs')->where('id', $jobId)->first();
        if ($job !== null && $job->status !== 'cancelled') {
            $this->fail($job, __('records.export.crashed', ['reference' => $this->correlation->get()]), true);
        }
    }

    public function cancel(object $job): void
    {
        DB::table('export_jobs')->where('id', $job->id)->whereIn('status', ['queued', 'running'])
            ->update(['status' => 'cancelled', 'updated_at' => Carbon::now('UTC')->format('Y-m-d H:i:s.u')]);
    }

    /** Marks finished exports whose file is past its time as expired and deletes the file. */
    public function expire(): int
    {
        $count = 0;
        DB::table('export_jobs')->where('status', 'completed')->where('expires_at', '<', Carbon::now('UTC')->format('Y-m-d H:i:s.u'))
            ->orderBy('id')->limit(500)->get()->each(function (object $job) use (&$count): void {
                $file = $job->file_id === null ? null : StoredFile::query()->withoutGlobalScopes()->find($job->file_id);
                if ($file !== null) {
                    Storage::disk($file->disk)->delete($file->path);
                    $file->forceFill(['deleted_at' => now()])->save();
                }
                DB::table('export_jobs')->where('id', $job->id)->update(['status' => 'expired', 'updated_at' => Carbon::now('UTC')->format('Y-m-d H:i:s.u')]);
                $count++;
            });

        return $count;
    }

    /**
     * What the export contains: the query (scope, filters, view filters,
     * order) and the columns.
     *
     * @param  array<string, mixed>  $filters
     * @return array{query: Builder, columns: list<array{key: string, label: string, field: array<string, mixed>|null, system: string|null, linked: bool}>, view: array<string, mixed>|null, presented: array<string, mixed>|null}
     */
    public function plan(FormRuntime $rt, User $user, array $filters): array
    {
        $levels = $this->fieldAccess->resolve($user, $rt->form->id, $rt->form->uuid, $rt->definition, 'view')['fields'];
        $q = $this->query->build($rt, $user, $levels, $filters);
        $views = app(ViewRuntime::class);
        $view = isset($filters['view']) ? $views->pick($rt, $user, strtolower((string) $filters['view'])) : null;
        $presented = $view === null ? null : $views->present($rt, $user, $view);
        $chosen = isset($filters['columns']) ? array_flip(array_map('strval', (array) $filters['columns'])) : null;
        $columns = [];
        if ($view !== null) {
            $views->filter($q, $rt, $user, $view, (array) ($filters['vf'] ?? []));
            $sortPath = isset($filters['sort']) ? explode('.', (string) $filters['sort']) : null;
            $views->order($q, $rt, $user, $view, $sortPath === null ? null : ['path' => $sortPath, 'dir' => $filters['direction'] ?? 'asc']);
            foreach ($presented['columns'] as $c) {
                if ($chosen !== null ? ! isset($chosen[$c['key']]) : ! $c['visible']) {
                    continue;
                }
                $field = ! $c['linked'] && isset($rt->keys[$c['path'][0]]) ? $rt->fields[$rt->keys[$c['path'][0]]] : null;
                $system = ! $c['linked'] && $field === null ? ltrim((string) $c['path'][0], '@') : null;
                $columns[] = ['key' => $c['key'], 'label' => (string) $c['label'], 'field' => $field, 'system' => $system, 'linked' => (bool) $c['linked']];
            }
        } else {
            $this->query->order($q, $rt, $levels, $filters);
            foreach (self::SYSTEM as $col) {
                if ($chosen !== null && ! isset($chosen["@{$col}"])) {
                    continue;
                }
                $columns[] = ['key' => "@{$col}", 'label' => __("records.export.columns.{$col}"), 'field' => null, 'system' => $col, 'linked' => false];
            }
            foreach ($rt->mainFields() as $f) {
                if ($this->codec->exportable($rt, $f) && ($levels[$f['uuid']] ?? 'hidden') !== 'hidden' && ($chosen === null || isset($chosen[$f['key']]))) {
                    $columns[] = ['key' => $f['key'], 'label' => $this->codec->header($f), 'field' => $f, 'system' => null, 'linked' => false];
                }
            }
        }

        return ['query' => $q, 'columns' => $columns, 'view' => $view, 'presented' => $presented];
    }

    /**
     * @param  array{query: Builder, columns: list<array<string, mixed>>, view: array<string, mixed>|null, presented: array<string, mixed>|null}  $plan
     * @return array{rows: int, truncated: bool}
     */
    private function write(FormRuntime $rt, User $user, array $plan, object $job, string $path): array
    {
        $max = (int) $this->settings->get('records', 'export_max_rows');
        $total = max(1, min((int) $job->row_count, $max));
        $format = (string) $job->format;
        $rtl = in_array(app()->getLocale(), ['ar', 'fa', 'he', 'ur'], true);
        $writer = null;
        $html = '';
        if ($format === 'pdf') {
            $html = '<table class="export"><thead><tr>'.implode('', array_map(static fn ($c) => '<th>'.e($c['label']).'</th>', $plan['columns'])).'</tr></thead><tbody>';
        } else {
            if ($format === 'csv') {
                $writer = new CsvWriter;
            } else {
                $options = new XlsxOptions;
                $writer = new XlsxWriter($options);
            }
            $writer->openToFile($path);
            if ($writer instanceof XlsxWriter) {
                // A frozen header row; Arabic sheets read right to left.
                $writer->getCurrentSheet()->setSheetView((new SheetView)->setFreezeRow(2)->setRightToLeft($rtl));
            }
            $writer->addRow(new Row(array_map(fn ($c) => $this->cell($c['label'], $format), $plan['columns']), (new Style)->setFontBold()));
        }
        $written = 0;
        $page = 1;
        $truncated = false;
        $views = app(ViewRuntime::class);
        while (true) {
            $rows = (clone $plan['query'])->forPage($page++, self::PAGE)->get()->map(static fn ($r) => (array) $r)->all();
            if ($rows === []) {
                break;
            }
            $records = $this->store->hydrate($rt, $rows, false);
            $fields = array_values(array_filter(array_map(static fn ($c) => $c['field'], $plan['columns'])));
            [$titles, $files] = $this->lookups($rt, $fields, $records);
            $linked = $plan['presented'] === null ? [] : $views->linkedValues($rt, $user, $plan['presented'], array_map(static fn ($r) => $r['id'], $records));
            foreach ($records as $r) {
                if ($written >= $max) {
                    $truncated = true;
                    break 2;
                }
                $cells = [];
                foreach ($plan['columns'] as $c) {
                    $cells[] = match (true) {
                        $c['linked'] => self::flatten($linked[$r['id']][$c['key']] ?? null),
                        $c['field'] !== null => $this->codec->toCell($rt, $c['field'], $r['values'][$c['field']['key']] ?? null, $titles[$c['field']['uuid']] ?? [], $files),
                        default => $this->system($r, (string) $c['system']),
                    };
                }
                if ($format === 'pdf') {
                    $html .= '<tr>'.implode('', array_map(static fn ($v) => '<td>'.e(is_bool($v) ? ($v ? '✓' : '') : (string) ($v ?? '')).'</td>', $cells)).'</tr>';
                } else {
                    $writer->addRow(new Row(array_map(fn ($v) => $this->cell($v, $format), $cells)));
                }
                $written++;
            }
            $this->update((int) $job->id, ['progress' => min(99, (int) floor(100 * $written / $total))]);
            if (DB::table('export_jobs')->where('id', $job->id)->value('status') === 'cancelled') {
                break;
            }
            if (count($rows) < self::PAGE) {
                break;
            }
        }
        if ($format === 'pdf') {
            $title = e($this->translator->labelOf($rt->form->translationsFor('name'), (string) $rt->form->key));
            $doc = '<!doctype html><html lang="'.e(app()->getLocale()).'" dir="'.($rtl ? 'rtl' : 'ltr').'"><head><meta charset="utf-8"><style>'
                .'body{font-family:dejavusans;font-size:8pt}h1{font-size:12pt}table.export{border-collapse:collapse;width:100%}th,td{border:0.5pt solid #999;padding:2pt 3pt;text-align:'.($rtl ? 'right' : 'left').'}th{background:#eee}'
                .'</style></head><body><h1>'.$title.'</h1>'.$html.'</tbody></table></body></html>';
            file_put_contents($path, $this->printer->pdf($doc, ['orientation' => count($plan['columns']) > 6 ? 'landscape' : 'portrait', 'paper' => 'a4']));
        } else {
            $writer->close();
        }

        return ['rows' => $written, 'truncated' => $truncated];
    }

    /** @param  array<string, mixed>  $r */
    private function system(array $r, string $column): mixed
    {
        return match ($column) {
            'id' => $r['uuid'],
            'version' => $r['row_version'],
            'record_number' => $r['system']['record_number'] ?? null,
            'created_at' => $r['system']['created_at'] ?? null,
            'updated_at' => $r['system']['updated_at'] ?? null,
            'status' => $r['system']['status']['name'] ?? null,
            default => null,
        };
    }

    private static function flatten(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }
        if (is_array($value)) {
            $parts = array_map(static fn ($v) => is_array($v) ? (string) ($v['title'] ?? $v['label'] ?? $v['name'] ?? json_encode($v, JSON_UNESCAPED_UNICODE)) : (string) $v, $value);

            return implode('; ', $parts);
        }

        return is_bool($value) ? ($value ? '1' : '0') : (string) $value;
    }

    /** Strings never become formulas: XLSX stores them as text, CSV prefixes risky first characters. */
    private function cell(mixed $value, string $format): Cell
    {
        if (is_string($value)) {
            return new StringCell($format === 'csv' ? Csv::cell($value) : $value, null);
        }

        return Cell::fromValue(is_scalar($value) || $value === null ? $value : (string) json_encode($value, JSON_UNESCAPED_UNICODE));
    }

    /**
     * Titles of referenced records and names of files on one page of records.
     *
     * @param  list<array<string, mixed>>  $fields
     * @param  list<array{values: array<string, mixed>}>  $records
     * @return array{0: array<string, array<string, string>>, 1: array<string, string>}
     */
    private function lookups(FormRuntime $rt, array $fields, array $records): array
    {
        $titles = [];
        $fileUuids = [];
        foreach ($fields as $f) {
            $values = [];
            foreach ($records as $r) {
                $v = $r['values'][$f['key']] ?? null;
                if ($v !== null) {
                    $values = [...$values, ...array_values(array_filter((array) $v, 'is_string'))];
                }
            }
            if ($values === []) {
                continue;
            }
            $storage = $rt->type($f)?->storage;
            if (in_array($storage, ['file', 'files'], true)) {
                $fileUuids = [...$fileUuids, ...$values];
            } elseif ($rt->targetTable($f) !== null) {
                $titles[$f['uuid']] = $this->refs->titles($rt, $f, array_values(array_unique($values)));
            }
        }
        $files = $fileUuids === [] ? [] : DB::table('files')->whereIn('uuid', array_values(array_unique($fileUuids)))->pluck('original_name', 'uuid')
            ->mapWithKeys(static fn ($n, $u) => [strtolower((string) $u) => (string) $n])->all();

        return [$titles, $files];
    }

    private function fail(object $job, string $error, bool $notify): void
    {
        $this->update((int) $job->id, ['status' => 'failed', 'error' => mb_substr($error, 0, 2000)]);
        if ($notify) {
            $this->notifier->notify((int) $job->user_id, 'export.failed', 'records.export.notify.failed', ['error' => $error], null, ['export_job' => $job->uuid], ['form_id' => (int) $job->form_id]);
        }
    }

    /** @param  array<string, mixed>  $values */
    private function update(int $id, array $values): void
    {
        DB::table('export_jobs')->where('id', $id)->update($values + ['updated_at' => Carbon::now('UTC')->format('Y-m-d H:i:s.u')]);
    }
}
