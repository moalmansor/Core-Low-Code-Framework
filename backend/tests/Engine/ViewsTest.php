<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

require_once __DIR__.'/helpers.php';

beforeEach(function () {
    $this->completeSetup();
    $this->admin = $this->superAdmin();
    $this->flushSession();
    $this->actingAs($this->admin, 'web');
});

/** Departments ← employees ← requests: a three-form chain for linked columns. */
function buildLinkedForms(TestCase $t): array
{
    [, $dept] = createForm($t, 'depts', 'collection');
    $dname = fieldDoc('name', 'text');
    saveDraft($t, $dept, [], [$dname, fieldDoc('budget', 'decimal')])->assertOk();
    expect(publish($t, $dept)['status'])->toBe('applied');

    [, $emp] = createForm($t, 'employees');
    $rel = ['uuid' => uid(), 'key' => 'department', 'type' => 'many_to_one', 'target' => $dept, 'kind' => 'reference', 'onDelete' => 'restrict', 'display' => $dname['uuid'], 'value' => null, 'inverse' => null];
    $ename = fieldDoc('name', 'text');
    saveDraft($t, $emp, [], [$ename, fieldDoc('department', 'lookup', null, ['relation' => $rel['uuid']])], [$rel])->assertOk();
    expect(publish($t, $emp)['status'])->toBe('applied');

    [, $req] = createForm($t, 'requests');
    $rel2 = ['uuid' => uid(), 'key' => 'employee', 'type' => 'many_to_one', 'target' => $emp, 'kind' => 'reference', 'onDelete' => 'restrict', 'display' => $ename['uuid'], 'value' => null, 'inverse' => null];
    $title = fieldDoc('title', 'text');
    $amount = fieldDoc('amount', 'decimal');
    $note = fieldDoc('note', 'text');
    saveDraft($t, $req, [], [$title, $amount, $note, fieldDoc('employee', 'lookup', null, ['relation' => $rel2['uuid']])], [$rel2])->assertOk();
    expect(publish($t, $req)['status'])->toBe('applied');

    $d1 = $t->postJson("/api/v1/r/{$dept}", ['values' => ['name' => 'Finance', 'budget' => '10']])->assertCreated()->json('data.uuid');
    $d2 = $t->postJson("/api/v1/r/{$dept}", ['values' => ['name' => 'Sales', 'budget' => '20']])->assertCreated()->json('data.uuid');
    $e1 = $t->postJson("/api/v1/r/{$emp}", ['values' => ['name' => 'Huda', 'department' => $d1]])->assertCreated()->json('data.uuid');
    $e2 = $t->postJson("/api/v1/r/{$emp}", ['values' => ['name' => 'Omar', 'department' => $d2]])->assertCreated()->json('data.uuid');
    $r1 = $t->postJson("/api/v1/r/{$req}", ['values' => ['title' => 'Laptop', 'amount' => '100', 'employee' => $e1]])->assertCreated()->json('data.uuid');
    $r2 = $t->postJson("/api/v1/r/{$req}", ['values' => ['title' => 'Phone', 'amount' => '50', 'employee' => $e2]])->assertCreated()->json('data.uuid');

    return compact('dept', 'emp', 'req', 'e1', 'e2', 'r1', 'r2', 'title', 'amount', 'note');
}

/** @return array<string, mixed> */
function viewDoc(string $key, array $columns, array $filters = [], array $extra = []): array
{
    return array_replace([
        'uuid' => uid(), 'key' => $key, 'i18n' => ['name' => ['en' => ucfirst($key)]], 'default' => true, 'priority' => 0, 'pageSize' => 25,
        'defaultSort' => [], 'showTotals' => false, 'columnChooser' => true, 'globalSearch' => true, 'rowOptions' => ['view' => true, 'edit' => true, 'log' => true],
        'includeInQueues' => false,
        'columns' => array_map(static fn ($p) => ['path' => $p, 'i18n' => ['label' => []], 'pinned' => 'none', 'visible' => true, 'sortable' => true, 'aggregate' => is_array($p) && $p === ['amount'] ? 'sum' : 'none'], $columns),
        'filters' => array_map(static fn ($p) => ['path' => $p, 'i18n' => ['label' => []]], $filters),
    ], $extra);
}

function saveViews(TestCase $t, string $form, array $views): TestResponse
{
    $hash = $t->getJson("/api/v1/forms/{$form}/views")->assertOk()->json('data.hash');

    return $t->putJson("/api/v1/forms/{$form}/views", ['views' => $views, 'base_hash' => $hash]);
}

it('shows linked forms\' fields as columns, filters and sorts on them, and totals the view', function () {
    $f = buildLinkedForms($this);
    $view = viewDoc('main', [['title'], ['amount'], ['employee', 'name'], ['employee', 'department', 'name'], ['@status']], [['employee', 'department', 'name'], ['amount']], ['showTotals' => true]);
    $saved = saveViews($this, $f['req'], [$view])->assertOk()->json('data');
    expect($saved['views'][0]['filters'][0]['type'])->toBe('text')->and($saved['views'][0]['filters'][1]['type'])->toBe('number_range');
    // The admin picker offers the linked forms' fields.
    expect(collect($saved['fields'])->firstWhere('key', 'employee')['children'])->not->toBeEmpty();

    $filterUuid = $saved['views'][0]['filters'][0]['uuid'];
    $res = $this->getJson("/api/v1/r/{$f['req']}?vf[{$filterUuid}][op]=contains&vf[{$filterUuid}][value]=fin")->assertOk()->json();
    expect($res['meta']['total'])->toBe(1)->and($res['data'][0]['uuid'])->toBe($f['r1'])
        ->and($res['data'][0]['linked']['employee.department.name'])->toBe('Finance')
        ->and($res['data'][0]['linked']['employee.name'])->toBe('Huda')
        ->and($res['meta']['totals']['amount'])->toBe('100.0000');
    expect(collect($res['meta']['view']['columns'])->pluck('key')->all())->toContain('employee.department.name');

    $sorted = $this->getJson("/api/v1/r/{$f['req']}?sort=employee.department.name&direction=desc")->assertOk()->json('data');
    expect(array_column($sorted, 'uuid'))->toBe([$f['r2'], $f['r1']]);
    expect($this->getJson("/api/v1/r/{$f['req']}")->json('meta.totals.amount'))->toBe('150.0000');

    // Bad paths are rejected when saving.
    saveViews($this, $f['req'], [viewDoc('bad', [['employee', 'nope']])])->assertStatus(422);
});

it('gives each role its view, hides what the user cannot see and saves personal and shared views', function () {
    $f = buildLinkedForms($this);
    $general = viewDoc('general', [['title']]);
    $finance = viewDoc('finance', [['title'], ['amount'], ['employee', 'department', 'name']], [], ['default' => false, 'priority' => 0]);
    $general['priority'] = 1;
    saveViews($this, $f['req'], [$general, $finance])->assertOk();
    $clerk = $this->makeUser();
    $other = $this->makeUser();
    foreach ([$clerk, $other] as $u) {
        grantPermission($u->id, "form.{$f['req']}.view");
    }
    grantPermission($clerk->id, "view.{$finance['uuid']}.use");

    $this->flushSession();
    $this->actingAs($clerk, 'web');
    $meta = $this->getJson("/api/v1/r/{$f['req']}")->assertOk()->json('meta');
    expect($meta['view']['key'])->toBe('finance');
    // The linked column needs access to the employees form.
    expect(collect($meta['view']['columns'])->pluck('key')->all())->toBe(['title', 'amount']);
    $this->getJson("/api/v1/r/{$f['req']}?view={$general['uuid']}")->assertForbidden();

    $saved = $this->postJson("/api/v1/r/{$f['req']}/saved-views", ['view' => $finance['uuid'], 'name' => 'Big ones', 'state' => ['columns' => ['title'], 'sort' => ['key' => 'amount', 'dir' => 'desc']], 'shares' => [['type' => 'user', 'uuid' => $other->uuid]]])
        ->assertCreated()->json('data.uuid');
    expect($this->getJson("/api/v1/r/{$f['req']}/saved-views")->json('data.0.mine'))->toBeTrue();

    // Shared with "other", but their only view is the default one: a saved view never widens what they may use.
    $this->flushSession();
    $this->actingAs($other, 'web');
    expect($this->getJson("/api/v1/r/{$f['req']}")->json('meta.view.key'))->toBe('general');
    expect($this->getJson("/api/v1/r/{$f['req']}/saved-views")->json('data'))->toBe([]);
    $this->patchJson("/api/v1/r/{$f['req']}/saved-views/{$saved}", ['name' => 'Mine now'])->assertForbidden();
});

it('deletes and restores a selection asking for one justification for all', function () {
    $f = buildLinkedForms($this);
    $hash = $this->getJson("/api/v1/forms/{$f['req']}/justification-rules")->json('data.hash');
    $this->putJson("/api/v1/forms/{$f['req']}/justification-rules", ['base_hash' => $hash, 'rules' => [[
        'uuid' => uid(), 'scope' => 'delete', 'target' => null, 'subject' => ['type' => 'everyone'], 'level' => 'mandatory', 'condition' => null, 'levelWhen' => null,
        'text' => ['min' => 3], 'reasonCodes' => ['mode' => 'none', 'source' => 'codes'], 'attachments' => ['mode' => 'none'], 'showSummary' => false, 'active' => true, 'i18n' => ['title' => [], 'help' => []],
    ]]])->assertOk();
    $items = [['uuid' => $f['r1'], 'row_version' => 1], ['uuid' => $f['r2'], 'row_version' => 1]];
    $this->postJson("/api/v1/r/{$f['req']}/bulk-delete", ['items' => $items])->assertStatus(422)->assertJsonPath('code', 'justification_required')->assertJsonPath('count', 2);
    $res = $this->postJson("/api/v1/r/{$f['req']}/bulk-delete", ['items' => $items, 'justification' => ['reason_text' => 'Duplicates']])->assertOk()->json('data');
    expect($res['succeeded'])->toBe(2);
    $j = DB::table('justifications')->first();
    expect((int) $j->affected_count)->toBe(2)->and(DB::table('audit_logs')->where('event', 'record.deleted')->where('justification_id', $j->id)->count())->toBe(2);
    expect($this->getJson("/api/v1/r/{$f['req']}?trashed=1")->json('meta.total'))->toBe(2);
    $this->postJson("/api/v1/r/{$f['req']}/bulk-restore", ['items' => [['uuid' => $f['r1']]]])->assertOk()->assertJsonPath('data.succeeded', 1);
    expect($this->getJson("/api/v1/r/{$f['req']}")->json('meta.total'))->toBe(1);
});

it('saves a view as a blueprint, creates views from it and propagates new versions', function () {
    $f = buildLinkedForms($this);
    $view = viewDoc('main', [['title'], ['amount']]);
    saveViews($this, $f['req'], [$view])->assertOk();
    $bp = $this->postJson('/api/v1/blueprints', ['source_type' => 'view', 'source' => $view['uuid'], 'name' => ['en' => 'Money view']])->assertCreated()->json('data.uuid');

    // A copy of the form with the same keys receives the view.
    $copy = $this->postJson("/api/v1/forms/{$f['req']}/duplicate", ['key' => 'requests_two', 'name' => ['en' => 'Requests 2']])->assertCreated()->json('data.uuid');
    expect(publish($this, $copy)['status'])->toBe('applied');
    $made = $this->postJson("/api/v1/blueprints/{$bp}/instantiate", ['form' => $copy, 'key' => 'money', 'name' => ['en' => 'Money']])->assertCreated()->json('data');
    expect($made['skipped'])->toBe([]);
    expect(array_column($this->getJson("/api/v1/forms/{$copy}/views")->json('data.views.0.columns'), 'path'))->toBe([['title'], ['amount']]);

    // Version 2 adds a column; the copy receives it after the preview.
    $views = $this->getJson("/api/v1/forms/{$f['req']}/views")->json('data.views');
    $views[0]['columns'][] = ['path' => ['note'], 'i18n' => ['label' => []], 'pinned' => 'none', 'visible' => true, 'sortable' => true, 'aggregate' => 'none'];
    saveViews($this, $f['req'], $views)->assertOk();
    $this->postJson("/api/v1/blueprints/{$bp}/versions", [])->assertCreated();
    $preview = $this->getJson("/api/v1/blueprints/{$bp}/propagation-preview")->assertOk()->json('data');
    expect($preview[0]['changes'][0]['label'])->toBe('note')->and($preview[0]['changes'][0]['status'])->toBe('apply');
    $this->postJson("/api/v1/blueprints/{$bp}/propagate")->assertOk()->assertJsonPath('data.0.status', 'applied');
    expect(array_column($this->getJson("/api/v1/forms/{$copy}/views")->json('data.views.0.columns'), 'path'))->toContain(['note']);
});

it('renders View Mode panels, preview cards with auto-fill, and the print view and PDF', function () {
    $f = buildLinkedForms($this);
    $tabs = ['uuid' => uid(), 'parent' => null, 'type' => 'tabs', 'i18n' => ['title' => []], 'config' => [], 'visibility' => null, 'order' => 0];
    $tab = ['uuid' => uid(), 'parent' => $tabs['uuid'], 'type' => 'tab', 'i18n' => ['title' => ['en' => 'Details']], 'config' => [], 'visibility' => null, 'order' => 0];
    $derived = ['uuid' => uid(), 'parent' => $tab['uuid'], 'type' => 'derived_fields', 'i18n' => ['title' => ['en' => 'Employee']], 'config' => ['paths' => [['employee', 'department', 'name']]], 'visibility' => null, 'order' => 0];
    $hidden = ['uuid' => uid(), 'parent' => null, 'type' => 'html', 'i18n' => ['title' => [], 'content' => ['en' => '<p>Only big <script>alert(1)</script></p>']], 'config' => [],
        'visibility' => ['k' => 'bin', 'op' => '>', 'a' => ['k' => 'ref', 'scope' => 'record', 'path' => ['amount']], 'b' => ['k' => 'lit', 't' => 'number', 'v' => '75']], 'order' => 1];
    $hash = $this->getJson("/api/v1/forms/{$f['req']}/view-panels")->assertOk()->json('data.hash');
    $this->putJson("/api/v1/forms/{$f['req']}/view-panels", ['panels' => [$tabs, $tab, $derived, $hidden], 'base_hash' => $hash])->assertOk();
    // Employees show their requests and their total.
    $related = ['uuid' => uid(), 'parent' => null, 'type' => 'related_table', 'i18n' => ['title' => ['en' => 'Requests']], 'config' => ['source' => $f['req'], 'via' => 'employee', 'columns' => [['title'], ['amount']]], 'visibility' => null, 'order' => 0];
    $sum = ['uuid' => uid(), 'parent' => null, 'type' => 'summary_widget', 'i18n' => ['title' => ['en' => 'Total']], 'config' => ['source' => $f['req'], 'via' => 'employee', 'aggregate' => 'sum', 'field' => 'amount'], 'visibility' => null, 'order' => 1];
    $hash = $this->getJson("/api/v1/forms/{$f['emp']}/view-panels")->json('data.hash');
    $this->putJson("/api/v1/forms/{$f['emp']}/view-panels", ['panels' => [$related, $sum], 'base_hash' => $hash])->assertOk();

    $panels = $this->getJson("/api/v1/r/{$f['req']}/{$f['r1']}/panels")->assertOk()->json('data');
    expect(array_column($panels, 'type'))->toBe(['tabs', 'tab', 'derived_fields', 'html'])
        ->and($panels[2]['data'][0]['value'])->toBe('Finance')
        ->and($panels[3]['content'])->not->toContain('<script>');
    expect(array_column($this->getJson("/api/v1/r/{$f['req']}/{$f['r2']}/panels")->json('data'), 'type'))->not->toContain('html');
    $emp = $this->getJson("/api/v1/r/{$f['emp']}/{$f['e1']}/panels")->assertOk()->json('data');
    expect($emp[0]['data']['rows'][0]['cells']['title'])->toBe('Laptop')->and($emp[1]['data']['value'])->toBe('100.0000');

    // Preview card and auto-fill: picking an employee copies their name into "note".
    $hash = $this->getJson("/api/v1/forms/{$f['req']}/reference-previews")->assertOk()->json('data.hash');
    $field = collect($this->getJson("/api/v1/forms/{$f['req']}/draft")->json('data.document.fields'))->firstWhere('key', 'employee');
    $this->putJson("/api/v1/forms/{$f['req']}/reference-previews", ['base_hash' => $hash, 'default' => null, 'fields' => [[
        'field' => $field['uuid'], 'displayPaths' => [['name'], ['department', 'name']], 'layout' => ['columns' => 1],
        'autofill' => [['from' => ['name'], 'to' => $f['note']['uuid'], 'overwrite' => false]], 'drawer' => true,
    ]]])->assertOk();
    $card = $this->getJson("/api/v1/r/{$f['req']}/preview/employee/{$f['e2']}")->assertOk()->json('data');
    expect(array_column($card['items'], 'value'))->toBe(['Omar', 'Sales'])->and($card['autofill'][0])->toBe(['field' => 'note', 'value' => 'Omar', 'overwrite' => false]);

    // Print view in HTML and PDF.
    $hash = $this->getJson("/api/v1/forms/{$f['req']}/print-layouts")->assertOk()->json('data.hash');
    $this->putJson("/api/v1/forms/{$f['req']}/print-layouts", ['base_hash' => $hash, 'layouts' => [[
        'uuid' => uid(), 'key' => 'standard', 'i18n' => ['name' => ['en' => 'Standard'], 'header' => ['en' => 'Purchase request'], 'footer' => []],
        'paper' => 'a4', 'orientation' => 'portrait', 'showLogo' => true, 'default' => true,
        'layout' => ['sections' => [['type' => 'form_body'], ['type' => 'panel', 'panel' => $derived['uuid']], ['type' => 'page_break'], ['type' => 'status_history']]],
    ]]])->assertOk();
    $html = $this->get("/api/v1/r/{$f['req']}/{$f['r1']}/print")->assertOk()->getContent();
    expect($html)->toContain('Laptop')->toContain('Purchase request')->toContain('Finance');
    $this->withHeader('Accept-Language', 'ar');
    $pdf = $this->get("/api/v1/r/{$f['req']}/{$f['r1']}/print?format=pdf")->assertOk();
    expect($pdf->headers->get('Content-Type'))->toBe('application/pdf')->and(substr((string) $pdf->getContent(), 0, 5))->toBe('%PDF-');
});
