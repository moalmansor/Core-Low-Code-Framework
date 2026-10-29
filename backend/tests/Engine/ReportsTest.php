<?php

declare(strict_types=1);

use App\Modules\Access\AccessCache;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

require_once __DIR__.'/helpers.php';

beforeEach(function () {
    $this->completeSetup();
    $this->admin = $this->superAdmin();
    $this->flushSession();
    $this->actingAs($this->admin, 'web');
});

/**
 * Customers (name, tier, a sensitive note) ← tickets (subject, opened, customer, lines repeater) ← tasks (title, hours, ticket).
 *
 * @return array<string, mixed>
 */
function reportForms(TestCase $t): array
{
    [, $customers] = createForm($t, 'customers');
    $c = [
        'name' => fieldDoc('name', 'text'),
        'tier' => fieldDoc('tier', 'select', null, ['options' => ['source' => 'static', 'static' => staticOptions(['gold', 'silver'])]]),
        'note' => fieldDoc('note', 'text', null, ['flags' => ['sensitive' => true]]),
    ];
    saveDraft($t, $customers, [], array_values($c))->assertOk();
    expect(publish($t, $customers)['status'])->toBe('applied');

    [, $tickets] = createForm($t, 'tickets');
    $rel = ['uuid' => uid(), 'key' => 'customer', 'type' => 'many_to_one', 'target' => $customers, 'kind' => 'reference', 'onDelete' => 'restrict', 'display' => $c['name']['uuid'], 'value' => null, 'inverse' => null];
    $lines = groupDoc('lines', 'repeater');
    $tk = [
        'subject' => fieldDoc('subject', 'text'),
        'opened' => fieldDoc('opened', 'date'),
        'customer' => fieldDoc('customer', 'lookup', null, ['relation' => $rel['uuid']]),
        'item' => fieldDoc('item', 'text', $lines['uuid']),
        'qty' => fieldDoc('qty', 'number', $lines['uuid']),
    ];
    saveDraft($t, $tickets, [$lines], array_values($tk), [$rel])->assertOk();
    expect(publish($t, $tickets)['status'])->toBe('applied');

    [, $tasks] = createForm($t, 'tasks');
    $rel2 = ['uuid' => uid(), 'key' => 'ticket', 'type' => 'many_to_one', 'target' => $tickets, 'kind' => 'reference', 'onDelete' => 'restrict', 'display' => $tk['subject']['uuid'], 'value' => null, 'inverse' => null];
    $ta = ['title' => fieldDoc('title', 'text'), 'hours' => fieldDoc('hours', 'number'), 'ticket' => fieldDoc('ticket', 'lookup', null, ['relation' => $rel2['uuid']])];
    saveDraft($t, $tasks, [], array_values($ta), [$rel2])->assertOk();
    expect(publish($t, $tasks)['status'])->toBe('applied');

    $acme = $t->postJson("/api/v1/r/{$customers}", ['values' => ['name' => 'Acme', 'tier' => 'gold', 'note' => 'Pays late']])->assertCreated()->json('data');
    $globex = $t->postJson("/api/v1/r/{$customers}", ['values' => ['name' => 'Globex', 'tier' => 'silver', 'note' => 'VIP']])->assertCreated()->json('data');
    $printer = $t->postJson("/api/v1/r/{$tickets}", ['values' => ['subject' => 'Printer', 'opened' => '2026-10-01', 'customer' => $acme['uuid'],
        'lines' => [['item' => 'Toner', 'qty' => 2], ['item' => 'Drum', 'qty' => 1]]]])->assertCreated()->json('data');
    $scanner = $t->postJson("/api/v1/r/{$tickets}", ['values' => ['subject' => 'Scanner', 'opened' => '2026-11-05', 'customer' => $globex['uuid']]])->assertCreated()->json('data');
    foreach ([['Replace toner', 2, $printer], ['Test print', 1, $printer], ['Clean glass', 3, $scanner]] as [$title, $hours, $ticket]) {
        $t->postJson("/api/v1/r/{$tasks}", ['values' => ['title' => $title, 'hours' => $hours, 'ticket' => $ticket['uuid']]])->assertCreated();
    }

    return compact('customers', 'tickets', 'tasks');
}

/** @return array<string, mixed> */
function reportDoc(string $form, array $extra = []): array
{
    return array_replace(['key' => 'r'.substr(str_replace('-', '', uid()), -10), 'form' => $form, 'visualization' => 'table', 'i18n' => ['name' => ['en' => 'Hours', 'ar' => 'الساعات']],
        'dimensions' => [], 'measures' => [], 'columns' => [], 'filters' => [], 'sort' => null, 'limit' => null, 'options' => []], $extra);
}

function dim(array $path, ?string $bucket = null): array
{
    return ['uuid' => uid(), 'path' => $path, 'bucket' => $bucket, 'i18n' => ['label' => []]];
}

function measure(string $aggregate, ?array $path = null): array
{
    return ['uuid' => uid(), 'aggregate' => $aggregate, 'path' => $path, 'i18n' => ['label' => []]];
}

it('groups and adds up through links, with totals over records', function () {
    $f = reportForms($this);
    $doc = reportDoc($f['tasks'], ['visualization' => 'bar', 'dimensions' => [dim(['ticket', 'customer', 'tier'])], 'measures' => [measure('sum', ['hours']), measure('count')]]);
    $r = $this->postJson('/api/v1/reports/preview', ['report' => $doc])->assertOk()->json('data');

    expect($r['mode'])->toBe('grouped')->and($r['records'])->toBe(3)
        ->and(array_map(static fn ($x) => [$x['labels'][0], $x['values']], $r['rows']))->toBe([['Gold', [3, 2]], ['Silver', [3, 1]]])
        ->and($r['totals'])->toBe([6, 3])
        ->and($r['dimensions'][0]['label'])->toBe('Ticket › Customer › Tier')
        ->and($r['measures'][0]['label'])->toBe('Total Hours');

    // Dates by month; a to-many measure (the rows of a repeating section) counts every row once.
    $months = $this->postJson('/api/v1/reports/preview', ['report' => reportDoc($f['tickets'], ['visualization' => 'line',
        'dimensions' => [dim(['opened'], 'month')], 'measures' => [measure('sum', ['#lines', 'qty'])]])])->assertOk()->json('data');
    expect(array_map(static fn ($x) => [$x['keys'][0], $x['values'][0]], $months['rows']))->toBe([['2026-10', 3], ['2026-11', 0]]);

    // Sorted by a value, largest first, the top one only.
    $m = measure('sum', ['hours']);
    $top = $this->postJson('/api/v1/reports/preview', ['report' => reportDoc($f['tasks'], ['dimensions' => [dim(['title'])], 'measures' => [$m],
        'sort' => ['by' => 'measure', 'ref' => $m['uuid'], 'direction' => 'desc'], 'limit' => 1])])->assertOk()->json('data');
    expect($top['rows'])->toHaveCount(1)->and($top['rows'][0]['labels'][0])->toBe('Clean glass');
});

it('lists records with linked columns when nothing is grouped, and checks each visualization\'s shape', function () {
    $f = reportForms($this);
    $subject = ['uuid' => uid(), 'path' => ['subject'], 'i18n' => ['header' => ['en' => 'Subject']]];
    $doc = reportDoc($f['tickets'], ['columns' => [$subject, ['uuid' => uid(), 'path' => ['customer', 'name'], 'i18n' => ['header' => []]]],
        'sort' => ['by' => 'column', 'ref' => $subject['uuid'], 'direction' => 'desc']]);
    $r = $this->postJson('/api/v1/reports/preview', ['report' => $doc])->assertOk()->json('data');
    expect($r['mode'])->toBe('detail')->and($r['rows'])->toBe([['Scanner', 'Globex'], ['Printer', 'Acme']])
        ->and(array_column($r['columns'], 'label'))->toBe(['Subject', 'Customer › Name']);

    $this->postJson('/api/v1/reports/preview', ['report' => reportDoc($f['tasks'], ['visualization' => 'pie', 'measures' => [measure('count')]])])
        ->assertStatus(422)->assertJsonValidationErrors(['visualization']);
    $this->postJson('/api/v1/reports/preview', ['report' => reportDoc($f['tasks'], ['measures' => [measure('sum', ['title'])]])])
        ->assertStatus(422)->assertJsonValidationErrors(['measures.0.path']);
    $this->postJson('/api/v1/reports/preview', ['report' => reportDoc($f['tasks'], ['visualization' => 'bar', 'dimensions' => [dim(['title'], 'month')], 'measures' => [measure('count')]])])
        ->assertStatus(422)->assertJsonValidationErrors(['dimensions.0.bucket']);
    $kpi = $this->postJson('/api/v1/reports/preview', ['report' => reportDoc($f['tasks'], ['visualization' => 'kpi', 'measures' => [measure('avg', ['hours']), measure('max', ['hours'])]])])->assertOk()->json('data');
    expect($kpi['rows'][0]['values'])->toBe([2, 3])->and($kpi['totals'])->toBe([2, 3]);
});

it('saves with a concurrency hash, and shows viewers only their own permitted data', function () {
    $f = reportForms($this);
    $made = $this->postJson('/api/v1/reports', ['report' => reportDoc($f['tasks'], ['key' => 'hours_by_tier', 'visualization' => 'bar',
        'dimensions' => [dim(['ticket', 'customer', 'tier'])], 'measures' => [measure('sum', ['hours'])]])])->assertCreated()->json('data');
    $this->putJson("/api/v1/reports/{$made['uuid']}", ['report' => array_replace($made, ['visualization' => 'line']), 'base_hash' => $made['hash']])->assertOk();
    $this->putJson("/api/v1/reports/{$made['uuid']}", ['report' => $made, 'base_hash' => $made['hash']])->assertStatus(409);

    $viewer = $this->makeUser();
    $this->flushSession();
    $this->actingAs($viewer, 'web');
    $this->getJson('/api/v1/reports')->assertForbidden();
    $this->getJson("/api/v1/reports/{$made['uuid']}/data")->assertForbidden();
    expect($this->getJson('/api/v1/insights')->assertOk()->json('data.reports'))->toBe([]);

    grantPermission($viewer->id, "report.{$made['uuid']}.view");
    $this->getJson("/api/v1/reports/{$made['uuid']}/data")->assertForbidden(); // the form itself is not theirs
    foreach (['tasks', 'tickets', 'customers'] as $form) {
        grantPermission($viewer->id, "form.{$f[$form]}.view");
    }
    $data = $this->getJson("/api/v1/reports/{$made['uuid']}/data")->assertOk()->json('data');
    expect($data['visualization'])->toBe('line')->and($data['result']['totals'])->toBe([6])
        ->and($this->getJson('/api/v1/insights')->json('data.reports.0.uuid'))->toBe($made['uuid']);

    // A dimension on a value the viewer sees masked is refused, not quietly grouped.
    $this->flushSession();
    $this->actingAs($this->admin, 'web');
    $masked = $this->postJson('/api/v1/reports', ['report' => reportDoc($f['customers'], ['key' => 'by_note', 'visualization' => 'pie', 'dimensions' => [dim(['note'])], 'measures' => [measure('count')]])])->assertCreated()->json('data');
    $this->flushSession();
    $this->actingAs($viewer, 'web');
    grantPermission($viewer->id, "report.{$masked['uuid']}.view");
    $this->getJson("/api/v1/reports/{$masked['uuid']}/data")->assertStatus(422)->assertJsonValidationErrors(['report']);

    $csv = $this->post("/api/v1/reports/{$made['uuid']}/export", ['format' => 'csv'], ['Accept' => 'application/json'])->assertOk();
    $text = file_get_contents($csv->baseResponse->getFile()->getPathname());
    expect($text)->toContain('Gold,3')->toContain('Silver,3')
        ->and(DB::table('audit_logs')->where('event', 'report.exported')->count())->toBe(1);
});

it('builds dashboards whose widgets follow roles and report permissions, and exports them', function () {
    $f = reportForms($this);
    $report = $this->postJson('/api/v1/reports', ['report' => reportDoc($f['tasks'], ['key' => 'hours', 'visualization' => 'kpi', 'measures' => [measure('sum', ['hours'])]])])->assertCreated()->json('data');
    $role = $this->postJson('/api/v1/roles', ['key' => 'leads', 'name' => ['en' => 'Leads', 'ar' => 'القادة']])->assertCreated()->json('data.uuid');
    $dashboard = $this->postJson('/api/v1/dashboards', ['dashboard' => ['key' => 'ops', 'i18n' => ['name' => ['en' => 'Operations', 'ar' => 'العمليات']], 'widgets' => [
        ['type' => 'text', 'i18n' => ['text' => ['en' => 'Weekly figures']], 'layout' => ['w' => 12, 'h' => 1]],
        ['type' => 'report', 'report' => $report['uuid'], 'i18n' => ['title' => ['en' => 'Hours booked']], 'layout' => ['w' => 4, 'h' => 2]],
        ['type' => 'report', 'report' => $report['uuid'], 'roles' => [$role], 'layout' => ['w' => 4, 'h' => 2]],
    ]]])->assertCreated()->json('data');
    expect($dashboard['widgets'])->toHaveCount(3);
    $this->postJson('/api/v1/dashboards', ['dashboard' => ['key' => 'bad', 'i18n' => ['name' => ['en' => 'Bad']], 'widgets' => [['type' => 'report', 'layout' => ['w' => 1, 'h' => 9]]]]])
        ->assertStatus(422)->assertJsonValidationErrors(['widgets.0.report', 'widgets.0.layout']);

    $viewer = $this->makeUser();
    grantPermission($viewer->id, "dashboard.{$dashboard['uuid']}.view");
    $this->flushSession();
    $this->actingAs($viewer, 'web');
    // No report permission yet: only the text.
    expect(array_column($this->getJson("/api/v1/dashboards/{$dashboard['uuid']}/view")->assertOk()->json('data.widgets'), 'type'))->toBe(['text']);
    grantPermission($viewer->id, "report.{$report['uuid']}.view");
    grantPermission($viewer->id, "form.{$f['tasks']}.view");
    $widgets = $this->getJson("/api/v1/dashboards/{$dashboard['uuid']}/view")->assertOk()->json('data.widgets');
    expect($widgets)->toHaveCount(2)->and($widgets[1]['title'])->toBe('Hours booked')->and($widgets[1]['result']['totals'])->toBe([6]);
    // In the role, the third one appears too.
    $viewer->roles()->attach(DB::table('roles')->where('uuid', $role)->value('id'), ['created_at' => now()->format('Y-m-d H:i:s.u')]);
    app(AccessCache::class)->bump();
    expect($this->getJson("/api/v1/dashboards/{$dashboard['uuid']}/view")->json('data.widgets'))->toHaveCount(3);

    $this->post("/api/v1/dashboards/{$dashboard['uuid']}/export", ['format' => 'xlsx'], ['Accept' => 'application/json'])->assertOk();
    $png = 'data:image/png;base64,'.base64_encode((string) base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg=='));
    $pdf = $this->post("/api/v1/dashboards/{$dashboard['uuid']}/export", ['format' => 'pdf', 'images' => [$widgets[1]['uuid'] => $png]], ['Accept' => 'application/json'])->assertOk();
    expect(substr((string) file_get_contents($pdf->baseResponse->getFile()->getPathname()), 0, 5))->toBe('%PDF-');
    $this->getJson('/api/v1/dashboards')->assertForbidden();
});
