<?php

declare(strict_types=1);

namespace App\Modules\Records\Exchange;

use App\Modules\Access\FieldAccessResolver;
use App\Modules\Audit\AuditWriter;
use App\Modules\Core\Settings\SettingsService;
use App\Modules\Identity\Models\User;
use App\Modules\Records\Runtime\FormRuntime;
use App\Modules\Records\Runtime\InvalidValue;
use App\Modules\Records\Runtime\RecordException;
use App\Modules\Records\Runtime\RecordPipeline;
use App\Modules\Records\Runtime\RecordQuery;
use App\Modules\Records\Runtime\RecordStore;
use App\Modules\Records\Runtime\References;
use App\Modules\Records\Runtime\ValueCodec;
use App\Support\Csv;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use OpenSpout\Common\Entity\Cell;
use OpenSpout\Common\Entity\Cell\StringCell;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Reader\CSV\Options as CsvReaderOptions;
use OpenSpout\Reader\CSV\Reader as CsvReader;
use OpenSpout\Reader\ReaderInterface;
use OpenSpout\Reader\XLSX\Options as XlsxReaderOptions;
use OpenSpout\Reader\XLSX\Reader as XlsxReader;
use OpenSpout\Writer\CSV\Writer as CsvWriter;
use OpenSpout\Writer\WriterInterface;
use OpenSpout\Writer\XLSX\Writer as XlsxWriter;

/**
 * Excel and CSV export and import of records (specification §4.8). Export
 * writes the records the reader can list, with the fields they may see.
 * Import maps the header row to fields, converts each cell, and checks every
 * row through the record pipeline before anything is written; rows with a
 * record ID update that record with optimistic concurrency (the exported
 * Version column), other rows create records. Each committed row carries an
 * idempotency key derived from the file and the row, so importing the same
 * file again never creates duplicates. Repeater rows (child tables) are not
 * part of the spreadsheet.
 */
final class RecordExchange
{
    private const SYSTEM_IMPORT = ['id', 'version'];

    private const SYSTEM_EXPORT = ['id', 'version', 'record_number', 'created_at', 'updated_at'];

    public function __construct(
        private readonly SheetCodec $codec,
        private readonly RecordStore $store,
        private readonly RecordQuery $query,
        private readonly RecordPipeline $pipeline,
        private readonly FieldAccessResolver $fieldAccess,
        private readonly References $refs,
        private readonly SettingsService $settings,
        private readonly AuditWriter $audit,
    ) {}

    /**
     * Writes the export to a temporary file and returns its path.
     *
     * @param  array<string, mixed>  $filters  validated by RecordQuery::rules()
     * @return array{path: string, rows: int, truncated: bool}
     */
    public function export(FormRuntime $rt, User $user, array $filters, string $format): array
    {
        $levels = $this->fieldAccess->resolve($user, $rt->form->id, $rt->form->uuid, $rt->definition, 'view')['fields'];
        $fields = array_values(array_filter($rt->mainFields(), fn (array $f) => $this->codec->exportable($rt, $f) && ($levels[$f['uuid']] ?? 'hidden') !== 'hidden'));
        $max = (int) $this->settings->get('records', 'export_max_rows');
        $path = (string) tempnam(sys_get_temp_dir(), 'lcf-export-');
        $writer = $this->writer($format);
        $writer->openToFile($path);
        $headers = [];
        foreach (self::SYSTEM_EXPORT as $col) {
            $headers[] = __("records.export.columns.{$col}");
        }
        foreach ($fields as $f) {
            $headers[] = $this->codec->header($f);
        }
        $writer->addRow(new Row(array_map(fn ($h) => $this->cell($h, $format), $headers), (new Style)->setFontBold()));

        $base = $this->query->order($this->query->build($rt, $user, $levels, $filters), $rt, $levels, $filters);
        $written = 0;
        $page = 1;
        $truncated = false;
        while (true) {
            $rows = (clone $base)->forPage($page++, 500)->get()->map(static fn ($r) => (array) $r)->all();
            if ($rows === []) {
                break;
            }
            $records = $this->store->hydrate($rt, $rows, false);
            [$titles, $files] = $this->lookups($rt, $fields, $records);
            foreach ($records as $r) {
                if ($written >= $max) {
                    $truncated = true;
                    break 2;
                }
                $cells = [
                    $r['uuid'], $r['row_version'], $r['system']['record_number'] ?? null,
                    $r['system']['created_at'] ?? null, $r['system']['updated_at'] ?? null,
                ];
                foreach ($fields as $f) {
                    $cells[] = $this->codec->toCell($rt, $f, $r['values'][$f['key']] ?? null, $titles[$f['uuid']] ?? [], $files);
                }
                $writer->addRow(new Row(array_map(fn ($v) => $this->cell($v, $format), $cells)));
                $written++;
            }
            if (count($rows) < 500) {
                break;
            }
        }
        $writer->close();
        $this->audit->record('records.exported', 'data', null, 'form', $rt->form->id, [
            'format' => $format, 'rows' => $written, 'truncated' => $truncated, 'search' => $filters['search'] ?? null,
            'filter' => array_keys($filters['filter'] ?? []), 'trashed' => (bool) ($filters['trashed'] ?? false),
        ], $user->id, null, $rt->form->id);

        return ['path' => $path, 'rows' => $written, 'truncated' => $truncated];
    }

    /**
     * An empty workbook with the importable columns the user may edit.
     */
    public function template(FormRuntime $rt, User $user): string
    {
        $levels = $this->fieldAccess->resolve($user, $rt->form->id, $rt->form->uuid, $rt->definition, 'create')['fields'];
        $headers = [__('records.export.columns.id'), __('records.export.columns.version')];
        foreach ($rt->mainFields() as $f) {
            if ($this->codec->importable($rt, $f) && ($levels[$f['uuid']] ?? 'editable') === 'editable') {
                $headers[] = $this->codec->header($f);
            }
        }
        $path = (string) tempnam(sys_get_temp_dir(), 'lcf-template-');
        $writer = $this->writer('xlsx');
        $writer->openToFile($path);
        $writer->addRow(new Row(array_map(fn ($h) => $this->cell($h, 'xlsx'), $headers), (new Style)->setFontBold()));
        $writer->close();

        return $path;
    }

    /**
     * Checks the file and, when `$commit` is true and every row is valid (or
     * `$skipInvalid` is set), writes the valid rows.
     *
     * @return array<string, mixed> the import report
     */
    public function import(FormRuntime $rt, User $user, UploadedFile $file, bool $commit, bool $skipInvalid): array
    {
        $format = strtolower((string) $file->getClientOriginalExtension()) === 'csv' ? 'csv' : 'xlsx';
        $path = (string) $file->getRealPath();
        $sheet = $this->read($path, $format);
        if ($sheet === []) {
            throw new RecordException(422, 'import_empty', __('records.import.empty'));
        }
        $headerRow = array_shift($sheet);
        $mapping = $this->mapHeaders($rt, $headerRow);
        $max = (int) $this->settings->get('records', 'import_max_rows');
        $dataRows = [];
        foreach ($sheet as $i => $cells) {
            if (array_filter($cells, static fn ($c) => $c !== null && $c !== '') === []) {
                continue;
            }
            $dataRows[$i + 2] = $cells;
        }
        if (count($dataRows) > $max) {
            throw new RecordException(422, 'import_too_large', __('records.import.too_large', ['max' => $max]));
        }

        $fileHash = hash_file('sha256', $path) ?: '';
        $results = [];
        $keys = [];
        foreach (array_keys($dataRows) as $rowNumber) {
            $keys[$rowNumber] = hash('sha256', "import|{$rt->form->id}|{$user->id}|{$fileHash}|{$rowNumber}");
        }
        $processed = array_flip(DB::table('submission_journal')->whereIn('idempotency_key', array_values($keys))->where('status', 'processed')->pluck('idempotency_key')->all());
        foreach ($dataRows as $rowNumber => $cells) {
            $results[$rowNumber] = isset($processed[$keys[$rowNumber]])
                ? ['input' => [], 'current' => null, 'version' => 0, 'errors' => [], 'status' => 'already_imported']
                : $this->prepareRow($rt, $user, $mapping, $cells, $format);
        }
        $invalid = count(array_filter($results, static fn ($r) => $r['errors'] !== []));
        $report = [
            'format' => $format,
            'columns' => array_values(array_map(static fn ($m) => ['header' => $m['header'], 'field' => $m['field']['key'] ?? null, 'system' => $m['system'] ?? null], $mapping['columns'])),
            'ignored' => $mapping['ignored'],
            'total' => count($results),
            'valid' => count($results) - $invalid,
            'invalid' => $invalid,
            'committed' => false,
            'created' => 0, 'updated' => 0, 'unchanged' => 0, 'already_imported' => 0, 'failed' => 0,
        ];
        if ($commit && ($invalid === 0 || $skipInvalid)) {
            foreach ($results as $rowNumber => &$row) {
                if ($row['errors'] !== []) {
                    continue;
                }
                if (($row['status'] ?? null) === 'already_imported') {
                    $report['already_imported']++;

                    continue;
                }
                $key = $keys[$rowNumber];
                try {
                    if ($row['current'] === null) {
                        $this->pipeline->create($rt, $user, $row['input'], [], $key, 'import');
                        $report['created']++;
                    } else {
                        $done = $this->pipeline->update($rt, $user, $row['current']['uuid'], $row['version'], $row['input'], $key, 'import');
                        $report[$done['changed'] === [] ? 'unchanged' : 'updated']++;
                    }
                    $row['status'] = 'imported';
                } catch (RecordException $e) {
                    $row['errors'] = $e->payload['errors'] ?? ['_' => [$e->getMessage()]];
                    $row['status'] = 'failed';
                    $report['failed']++;
                }
            }
            unset($row);
            $report['committed'] = true;
            $this->audit->record('records.imported', 'data', null, 'form', $rt->form->id, [
                'format' => $format, 'file' => $file->getClientOriginalName(), 'sha256' => $fileHash,
            ] + array_intersect_key($report, array_flip(['total', 'created', 'updated', 'unchanged', 'already_imported', 'failed', 'invalid'])), $user->id, null, $rt->form->id);
        }
        $headers = [];
        foreach ($mapping['columns'] as $m) {
            if (isset($m['field'])) {
                $headers[$m['field']['key']] = $m['header'];
            }
        }
        $report['rows'] = [];
        foreach ($results as $rowNumber => $row) {
            if ($row['errors'] === [] && ($row['status'] ?? null) !== 'failed') {
                continue;
            }
            if (count($report['rows']) >= 500) {
                break;
            }
            $errors = [];
            foreach ($row['errors'] as $key => $messages) {
                $errors[] = ['column' => $headers[explode('.', (string) $key)[0]] ?? (string) $key, 'field' => (string) $key, 'messages' => array_values((array) $messages)];
            }
            $report['rows'][] = ['row' => $rowNumber, 'action' => $row['current'] === null ? 'create' : 'update', 'errors' => $errors];
        }

        return $report;
    }

    /**
     * @param  list<mixed>  $headerRow
     * @return array{columns: array<int, array{header: string, field?: array<string, mixed>, system?: string}>, ignored: list<string>}
     */
    private function mapHeaders(FormRuntime $rt, array $headerRow): array
    {
        $aliases = [];
        foreach (self::SYSTEM_IMPORT as $col) {
            foreach (['en', 'ar'] as $locale) {
                $aliases[SheetCodec::norm((string) __("records.export.columns.{$col}", [], $locale))][] = ['system' => $col];
            }
            $aliases[SheetCodec::norm($col)][] = ['system' => $col];
        }
        $aliases[SheetCodec::norm('uuid')][] = ['system' => 'id'];
        $aliases[SheetCodec::norm('row_version')][] = ['system' => 'version'];
        foreach ($rt->mainFields() as $f) {
            if (! $this->codec->importable($rt, $f)) {
                continue;
            }
            foreach ($this->codec->aliases($f) as $alias) {
                $aliases[$alias][] = ['field' => $f];
            }
        }
        $columns = [];
        $ignored = [];
        $seen = [];
        foreach ($headerRow as $index => $raw) {
            $header = trim(is_scalar($raw) ? (string) $raw : '');
            if ($header === '') {
                continue;
            }
            $candidates = $aliases[SheetCodec::norm($header)] ?? [];
            $unique = [];
            foreach ($candidates as $c) {
                $unique[isset($c['field']) ? 'f:'.$c['field']['uuid'] : 's:'.$c['system']] = $c;
            }
            if ($unique === []) {
                $ignored[] = $header;

                continue;
            }
            if (count($unique) > 1) {
                throw new RecordException(422, 'import_header_ambiguous', __('records.import.header_ambiguous', ['header' => $header]));
            }
            $target = array_key_first($unique);
            if (isset($seen[$target])) {
                throw new RecordException(422, 'import_header_duplicate', __('records.import.header_duplicate', ['header' => $header]));
            }
            $seen[$target] = true;
            $columns[(int) $index] = ['header' => $header] + $unique[$target];
        }
        if (array_filter($columns, static fn ($c) => isset($c['field'])) === []) {
            throw new RecordException(422, 'import_no_columns', __('records.import.no_columns'));
        }

        return ['columns' => $columns, 'ignored' => $ignored];
    }

    /**
     * @param  array{columns: array<int, array{header: string, field?: array<string, mixed>, system?: string}>, ignored: list<string>}  $mapping
     * @param  list<mixed>  $cells
     * @return array{input: array<string, mixed>, current: array<string, mixed>|null, version: int, errors: array<string, list<string>>, status?: string}
     */
    private function prepareRow(FormRuntime $rt, User $user, array $mapping, array $cells, string $format): array
    {
        $input = [];
        $errors = [];
        $id = null;
        $version = 0;
        foreach ($mapping['columns'] as $index => $m) {
            $cell = $cells[$index] ?? null;
            if ($format === 'csv' && is_string($cell) && preg_match("/^'[=+\\-@\t\r]/", $cell) === 1) {
                $cell = substr($cell, 1);
            }
            if (isset($m['system'])) {
                if ($m['system'] === 'id') {
                    $id = is_scalar($cell) && trim((string) $cell) !== '' ? trim((string) $cell) : null;
                } else {
                    $version = is_numeric($cell) ? (int) $cell : 0;
                }

                continue;
            }
            $f = $m['field'];
            try {
                $input[$f['key']] = $this->codec->fromCell($rt, $f, $cell);
            } catch (InvalidValue $e) {
                $errors[$f['key']][] = __($e->key, $e->params);
            }
        }
        $current = null;
        if ($id !== null) {
            if (preg_match(ValueCodec::UUID, $id) !== 1 || ($current = $this->store->find($rt, strtolower($id))) === null) {
                return ['input' => $input, 'current' => null, 'version' => 0, 'errors' => ['_id' => [__('records.import.record_not_found', ['id' => $id])]]];
            }
            if ($version < 1) {
                $errors['_version'][] = __('records.row_version_required');
            }
        }
        if ($current !== null) {
            // Empty cells of an update leave the stored value untouched.
            $input = array_filter($input, static fn ($v) => $v !== null);
        }
        try {
            $errors += $this->pipeline->check($rt, $user, $input, $current);
        } catch (RecordException $e) {
            $errors['_'][] = $e->getMessage();
        }
        if ($current !== null && $version >= 1 && $current['row_version'] !== $version) {
            $errors['_version'][] = __('records.import.version_changed', ['expected' => $version, 'current' => $current['row_version']]);
        }

        return ['input' => $input, 'current' => $current, 'version' => $version, 'errors' => $errors];
    }

    /** @return list<list<mixed>> rows of the first sheet */
    private function read(string $path, string $format): array
    {
        $reader = $this->reader($path, $format);
        $reader->open($path);
        $rows = [];
        try {
            foreach ($reader->getSheetIterator() as $sheet) {
                foreach ($sheet->getRowIterator() as $row) {
                    $rows[] = array_map(static fn (Cell $c) => $c->getValue(), $row->getCells());
                }
                break;
            }
        } finally {
            $reader->close();
        }

        return $rows;
    }

    private function reader(string $path, string $format): ReaderInterface
    {
        if ($format === 'xlsx') {
            $options = new XlsxReaderOptions;
            $options->SHOULD_FORMAT_DATES = false;

            return new XlsxReader($options);
        }
        $options = new CsvReaderOptions;
        $first = (string) fgets(fopen($path, 'r') ?: throw new RecordException(422, 'import_unreadable', __('records.import.unreadable')));
        $options->FIELD_DELIMITER = substr_count($first, ';') > substr_count($first, ',') ? ';' : ',';

        return new CsvReader($options);
    }

    private function writer(string $format): WriterInterface
    {
        return $format === 'csv' ? new CsvWriter : new XlsxWriter;
    }

    /** Strings never become formulas: XLSX stores them as text, CSV prefixes risky first characters. */
    private function cell(bool|int|float|string|null $value, string $format): Cell
    {
        if (is_string($value)) {
            return new StringCell($format === 'csv' ? Csv::cell($value) : $value, null);
        }

        return Cell::fromValue($value);
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
}
