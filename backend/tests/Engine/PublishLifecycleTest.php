<?php

declare(strict_types=1);

use App\Infrastructure\Database\Contracts\DatabaseDriver;
use Illuminate\Support\Facades\DB;

require_once __DIR__.'/helpers.php';

beforeEach(function () {
    $this->completeSetup();
    $this->actingAs($this->superAdmin());
});

function columnsOf(string $table): array
{
    return array_column(app(DatabaseDriver::class)->columns($table), 'type', 'name');
}

it('publishes a form, creates its tables, and evolves them safely', function () {
    [, $form] = createForm($this, 'leave_request');
    $details = groupDoc('details', 'section');
    $lines = groupDoc('lines', 'repeater');
    $title = fieldDoc('title', 'text', $details['uuid'], ['validation' => ['required' => true], 'table' => ['filterable' => true]]);
    $days = fieldDoc('days', 'number', $details['uuid'], ['storage' => ['precision' => 9, 'scale' => 2]]);
    $kind = fieldDoc('kind', 'select', $details['uuid'], ['options' => ['source' => 'static', 'static' => staticOptions(['annual', 'sick'])]]);
    $amount = fieldDoc('amount', 'decimal', $lines['uuid']);
    $total = fieldDoc('total', 'formula', $details['uuid'], ['behavior' => ['formula' => ['k' => 'call', 'fn' => 'sum', 'args' => [['k' => 'ref', 'scope' => 'record', 'path' => ['lines']], ['k' => 'ref', 'scope' => 'record', 'path' => ['amount']]]]]]);
    $heading = fieldDoc('intro', 'heading', $details['uuid']);

    $saved = saveDraft($this, $form, [$details, $lines], [$title, $days, $kind, $amount, $total, $heading])->assertOk();
    expect($saved->json('data.problems'))->toBe([]);

    $plan = publish($this, $form);
    expect($plan['status'])->toBe('applied');
    expect(columnsOf('f_leave_request'))->toHaveKeys(['id', 'uuid', 'row_version', 'title', 'days', 'kind', 'total', 'search_text'])
        ->not->toHaveKey('intro');
    expect(columnsOf('f_leave_request__lines'))->toHaveKeys(['parent_id', 'sort_order', 'amount']);
    $this->getJson("/api/v1/forms/{$form}")->assertJsonPath('data.state', 'published')->assertJsonPath('data.version', 1);

    DB::table('f_leave_request')->insert([
        'uuid' => uid(), 'organization_id' => DB::table('organizations')->value('id'), 'form_version_id' => DB::table('form_versions')->value('id'),
        'row_version' => 1, 'created_at' => now(), 'updated_at' => now(), 'legal_hold' => false, 'title' => 'Trip', 'days' => '3', 'kind' => 'annual',
    ]);

    // Version 2: rename a key (rename_column), remove a field (archive), widen a column, add a field.
    $title2 = $title;
    $title2['key'] = 'subject';
    $title2['storage']['length'] = 500;
    $note = fieldDoc('note', 'textarea', $details['uuid']);
    saveDraft($this, $form, [$details, $lines], [$title2, $days, $amount, $total, $heading, $note])->assertOk();
    $impact = $this->postJson("/api/v1/forms/{$form}/impact")->assertOk()->json('data');
    expect($impact['impact']['schema']['change_class'])->toBe('destructive')
        ->and($impact['impact']['removed_fields'])->toBe(['kind'])
        ->and($impact['impact']['records']['count'])->toBe(1);
    // Destructive changes need the typed confirmation.
    $this->postJson("/api/v1/forms/{$form}/publish", ['impact_hash' => $impact['impact_hash']])->assertStatus(422)->assertJsonPath('code', 'typed_confirmation_required');
    $plan = publish($this, $form, ['confirm_destructive' => true, 'typed_confirmation' => 'leave_request']);
    expect($plan['status'])->toBe('applied')
        ->and(collect($plan['snapshots'])->pluck('kind')->all())->toContain('data_backup');

    $cols = columnsOf('f_leave_request');
    expect($cols)->toHaveKeys(['subject', 'note'])->not->toHaveKey('title')->not->toHaveKey('kind');
    expect(collect(array_keys($cols))->first(fn ($c) => str_starts_with($c, 'zz_kind_')))->not->toBeNull();
    expect(DB::table('f_leave_request')->value('subject'))->toBe('Trip');

    // Reconciliation is clean after both publishes.
    $report = $this->postJson('/api/v1/schema/reconcile', ['form' => $form])->assertCreated()->json('data');
    expect($report['status'])->toBe('clean')->and($report['differences'])->toBe([]);

    // History and diff.
    $versions = $this->getJson("/api/v1/forms/{$form}/versions")->assertOk()->json('data');
    expect(array_column($versions, 'version'))->toBe([2, 1]);
    $diff = $this->getJson("/api/v1/forms/{$form}/versions/1/diff/2")->assertOk()->json('data');
    expect($diff['summary'])->toMatchArray(['added' => 1, 'removed' => 1]);
});

it('changes a column type through a validated copy and blocks conflicting data', function () {
    [, $form] = createForm($this, 'inventory');
    $code = fieldDoc('code', 'text');
    saveDraft($this, $form, [], [$code])->assertOk();
    publish($this, $form);
    $org = DB::table('organizations')->value('id');
    $ver = DB::table('form_versions')->value('id');
    foreach (['12', 'x9'] as $v) {
        DB::table('f_inventory')->insert(['uuid' => uid(), 'organization_id' => $org, 'form_version_id' => $ver, 'row_version' => 1, 'created_at' => now(), 'updated_at' => now(), 'legal_hold' => false, 'code' => $v]);
    }
    $code['type'] = 'number';
    $code['storage'] = ['nullable' => true, 'index' => 'none', 'precision' => 10, 'scale' => 0];
    saveDraft($this, $form, [], [$code])->assertOk();
    $impact = $this->postJson("/api/v1/forms/{$form}/impact")->assertOk()->json('data.impact');
    expect($impact['records']['type_conflicts'][0]['records'][0]['value'])->toBe('x9')
        ->and(collect($impact['blocking'])->pluck('code'))->toContain('type_conflict');

    DB::table('f_inventory')->where('code', 'x9')->update(['code' => '9']);
    $plan = publish($this, $form, ['confirm_destructive' => true, 'typed_confirmation' => 'inventory']);
    expect($plan['status'])->toBe('applied');
    expect(DB::table('f_inventory')->orderBy('id')->pluck('code')->map(fn ($v) => (string) (int) $v)->all())->toBe(['12', '9']);
});

it('reverses applied steps when a step fails and keeps the previous version', function () {
    [, $form] = createForm($this, 'tickets');
    $subject = fieldDoc('subject', 'text');
    saveDraft($this, $form, [], [$subject])->assertOk();
    publish($this, $form);
    // Sabotage: a column with the name the next publish will add already exists.
    DB::statement(DB::getDriverName() === 'mysql' ? 'alter table f_tickets add body varchar(10) null' : 'alter table f_tickets add body nvarchar(10) null');
    DB::statement(DB::getDriverName() === 'mysql' ? 'alter table f_tickets add priority varchar(10) null' : 'alter table f_tickets add priority nvarchar(10) null');
    saveDraft($this, $form, [], [$subject, fieldDoc('aaa_first', 'text'), fieldDoc('priority', 'number')])->assertOk();
    $plan = publish($this, $form);
    expect($plan['status'])->toBe('reversed');
    $this->getJson("/api/v1/forms/{$form}")->assertJsonPath('data.version', 1)->assertJsonPath('data.state', 'published');
    expect(columnsOf('f_tickets'))->not->toHaveKey('aaa_first');
});

it('places a published form in the sidebar and grants the allowed roles', function () {
    [$app, $form] = createForm($this, 'permits');
    saveDraft($this, $form, [], [fieldDoc('title', 'text')])->assertOk();
    $role = DB::table('roles')->where('key', 'user')->first();
    $plan = publish($this, $form, [
        'placement' => ['application' => $app, 'label' => ['en' => 'Permits', 'ar' => 'التصاريح']],
        'allowed' => ['roles' => [$role->uuid]],
    ]);
    expect($plan['status'])->toBe('applied');
    $formId = DB::table('forms')->where('uuid', $form)->value('id');
    expect(DB::table('menu_items')->where('target_type', 'form')->where('target_id', $formId)->where('is_active', true)->exists())->toBeTrue();
    $granted = DB::table('permission_assignments')->join('permissions', 'permissions.id', '=', 'permission_id')
        ->where('subject_type', 'role')->where('subject_id', $role->id)->where('permissions.key', 'like', "form.{$form}.%")->pluck('permissions.key')->all();
    expect($granted)->toContain("form.{$form}.view", "form.{$form}.create", "form.{$form}.edit");
    $this->getJson('/api/v1/navigation')->assertOk()->assertSee('Permits');
});
