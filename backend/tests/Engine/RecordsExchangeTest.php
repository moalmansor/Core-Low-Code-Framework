<?php

declare(strict_types=1);

use App\Modules\Core\Settings\SettingsService;
use App\Modules\Records\Exchange\ImportService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Reader\XLSX\Reader as XlsxReader;
use OpenSpout\Writer\XLSX\Writer as XlsxWriter;
use Tests\TestCase;

require_once __DIR__.'/helpers.php';

beforeEach(function () {
    $this->completeSetup();
    $this->admin = $this->superAdmin();
    $this->flushSession();
    $this->actingAs($this->admin, 'web');
});

/** @return array{0: string, 1: string} [collection uuid, form uuid] */
function buildCatalog(TestCase $t): array
{
    [, $col] = createForm($t, 'suppliers', 'collection');
    $fields = [
        fieldDoc('code', 'text', null, ['validation' => ['required' => true, 'unique' => []], 'storage' => ['index' => 'unique'], 'i18n' => ['label' => ['en' => 'Code', 'ar' => 'الرمز']]]),
        fieldDoc('name', 'text', null, ['i18n' => ['label' => ['en' => 'Name', 'ar' => 'الاسم']]]),
        fieldDoc('tier', 'select', null, ['options' => ['source' => 'static', 'static' => staticOptions(['gold', 'silver'])], 'i18n' => ['label' => ['en' => 'Tier']]]),
        fieldDoc('active', 'toggle', null, ['i18n' => ['label' => ['en' => 'Active']]]),
        fieldDoc('rating', 'decimal', null, ['i18n' => ['label' => ['en' => 'Rating']]]),
    ];
    saveDraft($t, $col, [], $fields)->assertOk();
    expect(publish($t, $col)['status'])->toBe('applied');

    [, $form] = createForm($t, 'orders');
    $relation = ['uuid' => uid(), 'key' => 'supplier', 'type' => 'many_to_one', 'target' => $col, 'kind' => 'reference', 'onDelete' => 'restrict', 'display' => $fields[1]['uuid'], 'value' => null, 'inverse' => null];
    saveDraft($t, $form, [], [
        fieldDoc('subject', 'text', null, ['validation' => ['required' => true]]),
        fieldDoc('supplier', 'lookup', null, ['relation' => $relation['uuid']]),
    ], [$relation])->assertOk();
    expect(publish($t, $form)['status'])->toBe('applied');

    return [$col, $form];
}

/** @param  list<list<mixed>>  $rows */
function xlsxUpload(array $rows, string $name = 'import.xlsx'): UploadedFile
{
    $path = tempnam(sys_get_temp_dir(), 'lcf-test-').'.xlsx';
    $writer = new XlsxWriter;
    $writer->openToFile($path);
    foreach ($rows as $row) {
        $writer->addRow(Row::fromValues($row));
    }
    $writer->close();

    return new UploadedFile($path, $name, 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true);
}

/** @return list<list<mixed>> */
function readXlsx(string $content): array
{
    $path = tempnam(sys_get_temp_dir(), 'lcf-read-');
    file_put_contents($path, $content);
    $reader = new XlsxReader;
    $reader->open($path);
    $rows = [];
    foreach ($reader->getSheetIterator() as $sheet) {
        foreach ($sheet->getRowIterator() as $row) {
            $rows[] = $row->toArray();
        }
    }
    $reader->close();

    return $rows;
}

/** Exports through the job API and returns the file's content. */
function exportContent(TestCase $t, string $form, array $params): string
{
    $job = $t->postJson("/api/v1/r/{$form}/exports", $params)->assertOk()->json('data');
    expect($job['status'])->toBe('completed');

    return $t->get($job['url'])->assertOk()->streamedContent();
}

/**
 * Uploads a file, takes the suggested mapping, and starts an import job;
 * returns [inspection, job].
 *
 * @param  array<string, mixed>  $options
 * @return array{0: array<string, mixed>, 1: array<string, mixed>}
 */
function importFile(TestCase $t, string $form, UploadedFile $file, array $options = []): array
{
    $inspect = $t->post("/api/v1/r/{$form}/imports/inspect", ['file' => $file], ['Accept' => 'application/json'])->assertOk()->json('data');
    $mapping = array_map(static fn ($c) => ['column' => $c['column'], 'field' => $c['field'], 'system' => $c['system']], $inspect['columns']);
    $job = $t->postJson("/api/v1/r/{$form}/imports", ['file' => $inspect['file'], 'mapping' => $mapping, 'mode' => 'insert'] + $options)->assertStatus(202)->json('data');

    return [$inspect, $t->getJson("/api/v1/imports/{$job['uuid']}")->assertOk()->json('data')];
}

it('exports what the table shows, escapes formulas, and audits every export', function () {
    [$col] = buildCatalog($this);
    $acme = $this->postJson("/api/v1/r/{$col}", ['values' => ['code' => 'ACME', 'name' => 'Acme Ltd', 'tier' => 'gold', 'active' => true, 'rating' => '4.5']])->assertCreated()->json('data');
    $this->postJson("/api/v1/r/{$col}", ['values' => ['code' => 'EVIL', 'name' => '=HYPERLINK("http://x")', 'tier' => 'silver', 'active' => false]])->assertCreated();

    // XLSX: system columns, headers in the reader's language, labels instead of codes.
    $rows = readXlsx(exportContent($this, $col, ['format' => 'xlsx', 'sort' => 'record_number', 'direction' => 'asc']));
    expect($rows[0])->toBe(['Record ID', 'Version', 'Record number', 'Created at', 'Updated at', 'Code', 'Name', 'Tier', 'Active', 'Rating'])
        ->and($rows)->toHaveCount(3)
        ->and($rows[1][0])->toBe($acme['uuid'])
        ->and(array_slice($rows[1], 5))->toBe(['ACME', 'Acme Ltd', 'Gold', true, 4.5]);

    // Only the columns asked for (the table's visible columns).
    $narrow = readXlsx(exportContent($this, $col, ['format' => 'xlsx', 'columns' => ['code', 'name']]));
    expect($narrow[0])->toBe(['Code', 'Name']);

    // CSV never emits a formula; PDF is a PDF.
    expect(exportContent($this, $col, ['format' => 'csv']))->toContain("'=HYPERLINK")
        ->and(substr(exportContent($this, $col, ['format' => 'pdf']), 0, 5))->toBe('%PDF-')
        ->and(DB::table('audit_logs')->where('event', 'records.exported')->count())->toBe(4);
});

it('runs a large export as a background job and lets only its owner download it', function () {
    [$col] = buildCatalog($this);
    app(SettingsService::class)->write('records', 'export_sync_rows', 0);
    $this->postJson("/api/v1/r/{$col}", ['values' => ['code' => 'ACME', 'name' => 'Acme Ltd']])->assertCreated();
    $job = $this->postJson("/api/v1/r/{$col}/exports", ['format' => 'csv'])->assertStatus(202)->json('data');
    // The queue runs jobs at once in tests: the job has finished and notified its owner.
    $done = $this->getJson("/api/v1/exports/{$job['uuid']}")->assertOk()->json('data');
    expect($done['status'])->toBe('completed')->and($done['rows'])->toBe(1)
        ->and(DB::table('in_app_notifications')->where('user_id', $this->admin->id)->where('type', 'export.ready')->count())->toBe(1);

    $other = $this->makeUser();
    $this->flushSession();
    $this->actingAs($other, 'web');
    $this->getJson("/api/v1/exports/{$job['uuid']}")->assertNotFound();
    $file = DB::table('files')->where('owner_type', 'export_job')->value('uuid');
    $this->getJson("/api/v1/files/{$file}/url")->assertNotFound();
});

it('imports in the background: suggested mapping, preview, error report, idempotent re-run', function () {
    [$col, $form] = buildCatalog($this);
    $acme = $this->postJson("/api/v1/r/{$col}", ['values' => ['code' => 'ACME', 'name' => 'Acme Ltd', 'tier' => 'gold', 'active' => true, 'rating' => '4.5']])->assertCreated()->json('data');

    $file = xlsxUpload([
        ['code', 'Name', 'Tier', 'Active', 'Unknown column'],
        ['NEW', 'Newco', 'gold', 'نعم', null],
        [null, 'No code', 'platinum', 'maybe', null],
        ['=1+1', 'Formula', 'Silver', 'no', 'x'],
    ]);
    $inspect = $this->post("/api/v1/r/{$col}/imports/inspect", ['file' => $file], ['Accept' => 'application/json'])->assertOk()->json('data');
    $byHeader = array_column($inspect['columns'], null, 'header');
    expect($inspect['total_rows'])->toBe(3)
        ->and($byHeader['Unknown column']['field'])->toBeNull()
        ->and($byHeader['Name']['field'])->not->toBeNull()
        ->and($byHeader['code']['samples'][0])->toBe('NEW');
    $mapping = array_map(static fn ($c) => ['column' => $c['column'], 'field' => $c['field'], 'system' => $c['system']], $inspect['columns']);

    // Validation preview: nothing is written; the bad row names its columns.
    $preview = $this->postJson("/api/v1/r/{$col}/imports/preview", ['file' => $inspect['file'], 'mapping' => $mapping, 'mode' => 'insert'])->assertOk()->json('data');
    expect($preview['valid'])->toBe(2)->and($preview['invalid'])->toBe(1)
        ->and($preview['rows'][1]['row'])->toBe(3)
        ->and(array_column($preview['rows'][1]['errors'], 'column'))->toContain('code', 'Tier', 'Active')
        ->and(DB::table('c_suppliers')->count())->toBe(1);

    // Dry run: every row checked, nothing written, a report of the problems.
    $dry = $this->postJson("/api/v1/r/{$col}/imports", ['file' => $inspect['file'], 'mapping' => $mapping, 'mode' => 'insert', 'dry_run' => true])->assertStatus(202)->json('data');
    $dry = $this->getJson("/api/v1/imports/{$dry['uuid']}")->json('data');
    expect($dry['status'])->toBe('completed_with_errors')->and($dry['created'])->toBe(2)->and($dry['errors'])->toBe(1)->and($dry['has_report'])->toBeTrue()
        ->and(DB::table('c_suppliers')->count())->toBe(1);

    // The real run, saving the mapping for next time.
    $run = $this->postJson("/api/v1/r/{$col}/imports", ['file' => $inspect['file'], 'mapping' => $mapping, 'mode' => 'insert', 'save_as' => 'Supplier sheet'])->assertStatus(202)->json('data');
    $run = $this->getJson("/api/v1/imports/{$run['uuid']}")->json('data');
    expect($run['status'])->toBe('completed_with_errors')->and($run['created'])->toBe(2)->and($run['errors'])->toBe(1)
        ->and(DB::table('c_suppliers')->count())->toBe(3)
        // A formula is imported as its text, never evaluated.
        ->and(DB::table('c_suppliers')->where('name', 'Formula')->value('code'))->toBe('=1+1')
        ->and(DB::table('submission_journal')->whereNotNull('import_job_id')->count())->toBe(2)
        ->and($this->getJson("/api/v1/r/{$col}/import-mappings")->json('data.0.name'))->toBe('Supplier sheet');

    // The error report names the row, keeps its cells, and lists the problems by column.
    $url = $this->getJson("/api/v1/imports/{$run['uuid']}/report")->assertOk()->json('data.url');
    $report = readXlsx($this->get($url)->assertOk()->streamedContent());
    expect($report[0])->toBe(['Row', 'code', 'Name', 'Tier', 'Active', 'Unknown column', 'Problems'])
        ->and($report[1][0])->toBe(3)
        ->and($report[1][2])->toBe('No code')
        ->and($report[1][6])->toContain('Tier:');

    // Running the same job again (a retry) creates nothing twice.
    $job = DB::table('import_jobs')->where('uuid', $run['uuid'])->first();
    DB::table('import_jobs')->where('id', $job->id)->update(['status' => 'failed', 'last_committed_batch' => 0, 'processed_rows' => 0, 'created_count' => 0, 'error_count' => 0]);
    app(ImportService::class)->run((int) $job->id);
    expect(DB::table('c_suppliers')->count())->toBe(3);

    // Upsert by a key field: existing rows update, new ones are created.
    $upsert = xlsxUpload([['code', 'Name'], ['ACME', 'Acme International'], ['FRESH', 'Fresh Co']]);
    $inspect = $this->post("/api/v1/r/{$col}/imports/inspect", ['file' => $upsert], ['Accept' => 'application/json'])->assertOk()->json('data');
    $mapping = array_map(static fn ($c) => ['column' => $c['column'], 'field' => $c['field']], $inspect['columns']);
    $codeField = $inspect['columns'][0]['field'];
    $job = $this->postJson("/api/v1/r/{$col}/imports", ['file' => $inspect['file'], 'mapping' => $mapping, 'mode' => 'upsert', 'key_field' => $codeField])->assertStatus(202)->json('data');
    $job = $this->getJson("/api/v1/imports/{$job['uuid']}")->json('data');
    expect($job['status'])->toBe('completed')->and($job['created'])->toBe(1)->and($job['updated'])->toBe(1);
    $updated = $this->getJson("/api/v1/r/{$col}/{$acme['uuid']}")->json('data');
    expect($updated['values']['name'])->toBe('Acme International')->and($updated['values']['tier'])->toBe('gold')->and($updated['row_version'])->toBe(2);

    // Update mode refuses rows that match nothing.
    $update = xlsxUpload([['code', 'Name'], ['GHOST', 'Nobody']]);
    $inspect = $this->post("/api/v1/r/{$col}/imports/inspect", ['file' => $update], ['Accept' => 'application/json'])->assertOk()->json('data');
    $mapping = array_map(static fn ($c) => ['column' => $c['column'], 'field' => $c['field']], $inspect['columns']);
    $preview = $this->postJson("/api/v1/r/{$col}/imports/preview", ['file' => $inspect['file'], 'mapping' => $mapping, 'mode' => 'update', 'key_field' => $codeField])->assertOk()->json('data');
    expect($preview['rows'][0]['errors'][0]['messages'][0])->toBe(__('records.import.no_match'));

    // References resolve by the display title; unknown titles are reported.
    [, $orders] = importFile($this, $form, xlsxUpload([['Subject', 'Supplier'], ['Laptops', 'Acme International'], ['Chairs', 'Nobody']]));
    expect($orders['created'])->toBe(1)->and($orders['errors'])->toBe(1);
    expect($this->getJson("/api/v1/r/{$form}")->json('data.0.values.supplier'))->toBe($acme['uuid']);
});

it('cancels an import and asks for an import justification where a rule requires one', function () {
    [$col] = buildCatalog($this);
    $hash = $this->getJson("/api/v1/forms/{$col}/justification-rules")->assertOk()->json('data.hash');
    $this->putJson("/api/v1/forms/{$col}/justification-rules", ['base_hash' => $hash, 'rules' => [[
        'uuid' => uid(), 'scope' => 'import', 'target' => null, 'subject' => ['type' => 'everyone', 'uuid' => null], 'level' => 'mandatory',
        'condition' => null, 'levelWhen' => null, 'text' => ['min' => 5, 'max' => 500],
        'reasonCodes' => ['mode' => 'none', 'source' => 'codes', 'set' => null, 'collection' => null],
        'attachments' => ['mode' => 'none', 'max' => null, 'rules' => null], 'showSummary' => false, 'active' => true, 'i18n' => ['title' => ['en' => 'Why import?'], 'help' => []],
    ]]])->assertOk();
    $inspect = $this->post("/api/v1/r/{$col}/imports/inspect", ['file' => xlsxUpload([['code', 'Name'], ['ONE', 'One']])], ['Accept' => 'application/json'])->assertOk()->json('data');
    $mapping = array_map(static fn ($c) => ['column' => $c['column'], 'field' => $c['field']], $inspect['columns']);
    $this->postJson("/api/v1/r/{$col}/imports", ['file' => $inspect['file'], 'mapping' => $mapping, 'mode' => 'insert'])
        ->assertStatus(422)->assertJsonPath('code', 'justification_required');
    $job = $this->postJson("/api/v1/r/{$col}/imports", ['file' => $inspect['file'], 'mapping' => $mapping, 'mode' => 'insert', 'justification' => ['reason_text' => 'Yearly supplier list']])
        ->assertStatus(202)->json('data');
    $row = DB::table('import_jobs')->where('uuid', $job['uuid'])->first();
    expect($row->justification_id)->not->toBeNull()
        ->and(DB::table('justifications')->where('id', $row->justification_id)->value('context'))->toBe('import')
        ->and(DB::table('justifications')->where('id', $row->justification_id)->value('import_job_id'))->toBe($row->id);

    // A finished job cannot be cancelled; a queued one can.
    $this->postJson("/api/v1/imports/{$job['uuid']}/cancel")->assertStatus(409);
    DB::table('import_jobs')->where('id', $row->id)->update(['status' => 'queued']);
    expect($this->postJson("/api/v1/imports/{$job['uuid']}/cancel")->assertOk()->json('data.status'))->toBe('cancelled');
});

it('requires the export and import permissions', function () {
    [$col] = buildCatalog($this);
    $user = $this->makeUser();
    $this->flushSession();
    $this->actingAs($user, 'web');
    $this->postJson("/api/v1/r/{$col}/exports", ['format' => 'csv'])->assertNotFound();
    $this->post("/api/v1/r/{$col}/imports/inspect", ['file' => xlsxUpload([['code'], ['X']])], ['Accept' => 'application/json'])->assertNotFound();
});
