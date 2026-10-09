<?php

declare(strict_types=1);

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

it('exports records with labels and imports them back with validation, updates and idempotency', function () {
    [$col, $form] = buildCatalog($this);
    $acme = $this->postJson("/api/v1/r/{$col}", ['values' => ['code' => 'ACME', 'name' => 'Acme Ltd', 'tier' => 'gold', 'active' => true, 'rating' => '4.5']])->assertCreated()->json('data');
    $this->postJson("/api/v1/r/{$col}", ['values' => ['code' => 'EVIL', 'name' => '=HYPERLINK("http://x")', 'tier' => 'silver', 'active' => false]])->assertCreated();

    // XLSX export: system columns, headers in the reader's language, labels instead of codes.
    $response = $this->get("/api/v1/r/{$col}/export?format=xlsx&sort=record_number&direction=asc")->assertOk();
    $rows = readXlsx($response->streamedContent());
    expect($rows[0])->toBe(['Record ID', 'Version', 'Record number', 'Created at', 'Updated at', 'Code', 'Name', 'Tier', 'Active', 'Rating'])
        ->and($rows)->toHaveCount(3)
        ->and($rows[1][0])->toBe($acme['uuid'])
        ->and(array_slice($rows[1], 5))->toBe(['ACME', 'Acme Ltd', 'Gold', true, 4.5]);

    // CSV export never emits a formula.
    $csv = $this->get("/api/v1/r/{$col}/export?format=csv")->assertOk()->streamedContent();
    expect($csv)->toContain("'=HYPERLINK")->and(DB::table('audit_logs')->where('event', 'records.exported')->count())->toBe(2);

    // Import: one update (by ID and version), one create (labels and yes/no), one invalid row.
    $file = xlsxUpload([
        ['Record ID', 'Version', 'code', 'Name', 'Tier', 'Active', 'Unknown column'],
        [$acme['uuid'], 1, 'ACME', 'Acme International', 'Silver', 'no', 'x'],
        [null, null, 'NEW', 'Newco', 'gold', 'نعم', null],
        [null, null, null, 'No code', 'platinum', 'maybe', null],
    ]);
    $preview = $this->post("/api/v1/r/{$col}/import", ['file' => $file])->assertOk()->json('data');
    expect($preview['total'])->toBe(3)
        ->and($preview['valid'])->toBe(2)
        ->and($preview['invalid'])->toBe(1)
        ->and($preview['committed'])->toBeFalse()
        ->and($preview['ignored'])->toBe(['Unknown column'])
        ->and($preview['rows'][0]['row'])->toBe(4)
        ->and(array_column($preview['rows'][0]['errors'], 'field'))->toContain('code', 'tier', 'active');

    // Commit refuses while rows are invalid, unless invalid rows are skipped.
    $refused = $this->post("/api/v1/r/{$col}/import", ['file' => $file, 'commit' => 1])->assertOk()->json('data');
    expect($refused['committed'])->toBeFalse()->and(DB::table('c_suppliers')->count())->toBe(2);
    $done = $this->post("/api/v1/r/{$col}/import", ['file' => $file, 'commit' => 1, 'skip_invalid' => 1])->assertOk()->json('data');
    expect($done['committed'])->toBeTrue()->and($done['created'])->toBe(1)->and($done['updated'])->toBe(1);
    $updated = $this->getJson("/api/v1/r/{$col}/{$acme['uuid']}")->json('data');
    expect($updated['values']['name'])->toBe('Acme International')
        ->and($updated['values']['tier'])->toBe('silver')
        ->and($updated['values']['active'])->toBeFalse()
        ->and($updated['values']['rating'])->toBe('4.5')
        ->and($updated['row_version'])->toBe(2);
    $newco = DB::table('c_suppliers')->where('code', 'NEW')->first();
    expect($newco)->not->toBeNull();

    // The same file again: rows already imported are recognised, nothing is duplicated.
    $again = $this->post("/api/v1/r/{$col}/import", ['file' => $file, 'commit' => 1, 'skip_invalid' => 1])->assertOk()->json('data');
    expect(DB::table('c_suppliers')->count())->toBe(3)
        ->and($again['already_imported'])->toBe(2)
        ->and($again['created'] + $again['updated'])->toBe(0);

    // References resolve by the display title; unknown titles are reported.
    $orders = xlsxUpload([['Subject', 'Supplier'], ['Laptops', 'Acme International'], ['Chairs', 'Nobody']]);
    $report = $this->post("/api/v1/r/{$form}/import", ['file' => $orders, 'commit' => 1, 'skip_invalid' => 1])->assertOk()->json('data');
    expect($report['created'])->toBe(1)->and($report['rows'][0]['errors'][0]['field'])->toBe('supplier');
    $order = $this->getJson("/api/v1/r/{$form}")->json('data.0');
    expect($order['values']['supplier'])->toBe($acme['uuid']);
});

it('requires the export and import permissions', function () {
    [$col] = buildCatalog($this);
    $user = $this->makeUser();
    $this->flushSession();
    $this->actingAs($user, 'web');
    $this->get("/api/v1/r/{$col}/export")->assertNotFound();
    $this->post("/api/v1/r/{$col}/import", ['file' => xlsxUpload([['code'], ['X']])])->assertNotFound();
});
