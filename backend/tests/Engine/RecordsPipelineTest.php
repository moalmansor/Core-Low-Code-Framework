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

/** A published collection of departments and a request form that looks it up. */
function buildRequestForms(TestCase $t): array
{
    [, $col] = createForm($t, 'cost_centers', 'collection');
    $code = fieldDoc('code', 'text', null, ['validation' => ['required' => true], 'table' => ['filterable' => true, 'searchable' => true]]);
    $name = fieldDoc('name', 'text', null, ['table' => ['searchable' => true]]);
    saveDraft($t, $col, [], [$code, $name], formPatch: [])->assertOk();
    $doc = $t->getJson("/api/v1/forms/{$col}/draft")->json('data.document');
    $doc['collection'] = ['type' => 'table', 'labelField' => $name['uuid'], 'valueField' => $code['uuid']];
    $t->putJson("/api/v1/forms/{$col}/draft", ['document' => $doc, 'draft_updated_at' => $t->getJson("/api/v1/forms/{$col}/draft")->json('data.draft_updated_at')])->assertOk();
    expect(publish($t, $col)['status'])->toBe('applied');

    [, $form] = createForm($t, 'purchase');
    $relation = ['uuid' => uid(), 'key' => 'center', 'type' => 'many_to_one', 'target' => $col, 'kind' => 'reference', 'onDelete' => 'restrict', 'display' => $name['uuid'], 'value' => null, 'inverse' => null];
    $lines = groupDoc('lines', 'repeater', null, ['repeater' => ['minRows' => 1]]);
    $fields = [
        'subject' => fieldDoc('subject', 'text', null, ['validation' => ['required' => true, 'length' => ['max' => 50]], 'behavior' => ['transforms' => ['trim']], 'table' => ['searchable' => true, 'sortable' => true]]),
        'priority' => fieldDoc('priority', 'select', null, ['options' => ['source' => 'static', 'static' => staticOptions(['low', 'high'])]]),
        'reason' => fieldDoc('reason', 'textarea'),
        'center' => fieldDoc('center', 'lookup', null, ['relation' => $relation['uuid']]),
        'qty' => fieldDoc('qty', 'number', $lines['uuid'], ['storage' => ['precision' => 9, 'scale' => 0], 'validation' => ['required' => true, 'number' => ['min' => '1']]]),
        'price' => fieldDoc('price', 'decimal', $lines['uuid']),
        'total' => fieldDoc('total', 'formula', null, ['behavior' => ['formula' => ['k' => 'call', 'fn' => 'sum', 'args' => [
            ['k' => 'ref', 'scope' => 'record', 'path' => ['lines']],
            ['k' => 'bin', 'op' => '*', 'a' => ['k' => 'ref', 'scope' => 'record', 'path' => ['qty']], 'b' => ['k' => 'ref', 'scope' => 'record', 'path' => ['price']]],
        ]]]]),
    ];
    // Reason is required when priority is high.
    $condition = ['uuid' => uid(), 'owner' => ['type' => 'field', 'uuid' => $fields['reason']['uuid']], 'name' => 'reason when high',
        'when' => ['k' => 'bin', 'op' => '=', 'a' => ['k' => 'ref', 'scope' => 'record', 'path' => ['priority']], 'b' => ['k' => 'lit', 't' => 'text', 'v' => 'high']],
        'effects' => [['effect' => 'require', 'target' => ['type' => 'field', 'uuid' => $fields['reason']['uuid']]]], 'else' => [['effect' => 'hide', 'target' => ['type' => 'field', 'uuid' => $fields['reason']['uuid']]]],
        'evaluateOn' => 'always', 'runtime' => 'client_and_server', 'order' => 0, 'active' => true];
    $saved = saveDraft($t, $form, [$lines], array_values($fields), [$relation], [$condition])->assertOk();
    expect($saved->json('data.problems'))->toBe([]);
    expect(publish($t, $form)['status'])->toBe('applied');

    return [$col, $form];
}

it('creates, validates, computes, updates and detects conflicts', function () {
    [$col, $form] = buildRequestForms($this);
    $center = $this->postJson("/api/v1/r/{$col}", ['values' => ['code' => 'FIN', 'name' => 'Finance']])->assertCreated()->json('data.uuid');

    // Validation: required, length, condition-driven required, repeater rows, option values.
    $bad = $this->postJson("/api/v1/r/{$form}", ['values' => ['subject' => str_repeat('x', 60), 'priority' => 'high', 'lines' => []]])->assertStatus(422);
    expect(array_keys($bad->json('errors')))->toContain('subject', 'reason', 'lines');
    $this->postJson("/api/v1/r/{$form}", ['values' => ['subject' => 'x', 'priority' => 'urgent', 'lines' => [['qty' => '1']]]])->assertStatus(422)->assertJsonStructure(['errors' => ['priority']]);

    // A valid record: trimmed, formula computed server-side, lookup resolved with its title.
    $created = $this->withHeaders(['Idempotency-Key' => 'k-0000000000000001'])->postJson("/api/v1/r/{$form}", ['values' => [
        'subject' => '  Laptops  ', 'priority' => 'low', 'center' => $center,
        'lines' => [['qty' => '2', 'price' => '1500.50'], ['qty' => '1', 'price' => '99']],
        'total' => '1',
    ]])->assertCreated()->json('data');
    expect($created['values']['subject'])->toBe('Laptops')
        ->and($created['values']['total'])->toBe('3100')
        ->and($created['values']['center'])->toBe($center)
        ->and($created['references']['center'][$center])->toBe('Finance')
        ->and($created['values']['lines'])->toHaveCount(2)
        ->and($created['row_version'])->toBe(1);

    // Same idempotency key: same record, no duplicate.
    $again = $this->withHeaders(['Idempotency-Key' => 'k-0000000000000001'])->postJson("/api/v1/r/{$form}", ['values' => ['subject' => 'Laptops', 'priority' => 'low', 'lines' => [['qty' => '1']]]])->assertCreated();
    expect($again->json('data.uuid'))->toBe($created['uuid']);
    expect(DB::table('f_purchase')->count())->toBe(1);
    expect(DB::table('submission_journal')->where('status', 'processed')->count())->toBe(2);
    // The key cannot be reused for another operation.
    $this->withHeaders(['Idempotency-Key' => 'k-0000000000000001'])->patchJson("/api/v1/r/{$form}/{$created['uuid']}", ['row_version' => 1, 'values' => ['subject' => 'x']])->assertStatus(409)->assertJsonPath('code', 'idempotency_key_reused');
    $this->flushHeaders();
    $this->withHeaders(['Origin' => 'http://localhost', 'Referer' => 'http://localhost/']);

    // Update with the loaded version.
    $uuid = $created['uuid'];
    $updated = $this->patchJson("/api/v1/r/{$form}/{$uuid}", ['row_version' => 1, 'values' => ['subject' => 'Laptops and docks']])->assertOk()->json('data');
    expect($updated['row_version'])->toBe(2)->and($updated['changed'])->toBe(['subject']);

    // A stale save is rejected with the conflict payload, never silently applied.
    $conflict = $this->patchJson("/api/v1/r/{$form}/{$uuid}", ['row_version' => 1, 'values' => ['subject' => 'Mine']])->assertStatus(409)->json();
    expect($conflict['code'])->toBe('conflict')
        ->and($conflict['current_row_version'])->toBe(2)
        ->and($conflict['changed_fields'][0])->toMatchArray(['field' => 'subject', 'base_value' => 'Laptops', 'their_value' => 'Laptops and docks', 'your_value' => 'Mine']);
    expect(DB::table('f_purchase')->value('subject'))->toBe('Laptops and docks');

    // Rows: remove one line; the total follows.
    $show = $this->getJson("/api/v1/r/{$form}/{$uuid}")->assertOk()->json('data');
    $line = $show['values']['lines'][0];
    $after = $this->patchJson("/api/v1/r/{$form}/{$uuid}", ['row_version' => 2, 'values' => ['lines' => [$line]]])->assertOk()->json('data');
    expect($after['values']['total'])->toBe('3001')->and(DB::table('f_purchase__lines')->count())->toBe(1);

    // Search, history, delete and restore.
    expect($this->getJson("/api/v1/r/{$form}?search=laptops")->json('meta.total'))->toBe(1);
    expect(collect($this->getJson("/api/v1/r/{$form}/{$uuid}/history")->assertOk()->json('data'))->pluck('event')->all())->toContain('record.created', 'record.updated');
    $this->deleteJson("/api/v1/r/{$form}/{$uuid}", ['row_version' => 3])->assertNoContent();
    expect($this->getJson("/api/v1/r/{$form}")->json('meta.total'))->toBe(0);
    $this->postJson("/api/v1/r/{$form}/{$uuid}/restore")->assertNoContent();
    expect($this->getJson("/api/v1/r/{$form}")->json('meta.total'))->toBe(1);
});

it('enforces form permissions and field access server-side', function () {
    [, $form] = buildRequestForms($this);
    $clerk = $this->makeUser(['user']);
    $this->flushSession();
    $this->actingAs($clerk, 'web');
    $this->getJson("/api/v1/r/{$form}")->assertNotFound();

    // Grant view+create to the clerk; make `priority` read-only for them in create mode.
    $this->flushSession();
    $this->actingAs($this->admin, 'web');
    $role = DB::table('roles')->where('key', 'user')->value('uuid');
    $pid = fn ($a) => DB::table('permissions')->where('key', "form.{$form}.{$a}")->value('id');
    foreach (['view', 'create'] as $a) {
        DB::table('permission_assignments')->insert(['permission_id' => $pid($a), 'subject_type' => 'role', 'subject_id' => DB::table('roles')->where('key', 'user')->value('id'), 'effect' => 'allow', 'include_descendants' => false, 'organization_id' => DB::table('organizations')->value('id'), 'created_at' => now(), 'updated_at' => now()]);
    }
    app(AccessCache::class)->bump();
    $priority = DB::table('fields')->where('key', 'priority')->value('uuid');
    $this->putJson("/api/v1/forms/{$form}/access-rules", ['changes' => [[
        'target' => ['type' => 'field', 'uuid' => $priority], 'subject' => ['type' => 'role', 'uuid' => $role],
        'mode' => 'create', 'access' => 'read_only', 'effect' => 'allow',
    ]]])->assertOk();

    $this->flushSession();
    $this->actingAs($clerk, 'web');
    $def = $this->getJson("/api/v1/r/{$form}/definition?mode=create")->assertOk()->json('data');
    expect(collect($def['fields'])->firstWhere('key', 'priority')['access'])->toBe('read_only');
    $this->postJson("/api/v1/r/{$form}", ['values' => ['subject' => 'x', 'priority' => 'high', 'reason' => 'r', 'lines' => [['qty' => '1']]]])
        ->assertStatus(422)->assertJsonPath('errors.priority.0', __('records.not_editable'));
    $created = $this->postJson("/api/v1/r/{$form}", ['values' => ['subject' => 'x', 'lines' => [['qty' => '1']]]])->assertCreated()->json('data.uuid');
    // No edit permission.
    $this->patchJson("/api/v1/r/{$form}/{$created}", ['row_version' => 1, 'values' => ['subject' => 'y']])->assertForbidden();

    // Explain access names the rule that decided.
    $this->flushSession();
    $this->actingAs($this->admin, 'web');
    $explain = $this->getJson("/api/v1/forms/{$form}/access-explain?user={$clerk->uuid}&field={$priority}&mode=create")->assertOk()->json('data');
    expect($explain['result'])->toBe('read_only')->and($explain['candidates'])->toHaveCount(1);
});
