<?php

declare(strict_types=1);

use App\Modules\Access\Models\PermissionAssignment;
use Illuminate\Support\Facades\DB;

require_once __DIR__.'/helpers.php';

beforeEach(function () {
    $this->completeSetup();
    $this->admin = $this->superAdmin();
    $this->flushSession();
    $this->actingAs($this->admin, 'web');
});

it('filters text by contains, starts with and equals, choices by any of, and narrows to selected records', function () {
    [, $form] = createForm($this, 'visitors');
    $table = ['visible' => true, 'filterable' => true, 'sortable' => true];
    $fields = [
        fieldDoc('name', 'text', null, ['table' => $table]),
        fieldDoc('tier', 'select', null, ['table' => $table, 'options' => ['source' => 'static', 'static' => staticOptions(['gold', 'silver', 'bronze'])]]),
    ];
    saveDraft($this, $form, [], $fields)->assertOk();
    expect(publish($this, $form)['status'])->toBe('applied');
    $uuids = [];
    foreach ([['Resan Hamad', 'gold'], ['Hamad Ali', 'silver'], ['مصطفى أسعد', 'bronze'], ['50%_off', 'gold']] as [$name, $tier]) {
        $uuids[$name] = $this->postJson("/api/v1/r/{$form}", ['values' => ['name' => $name, 'tier' => $tier]])->assertCreated()->json('data.uuid');
    }
    $names = fn (array $filter, array $extra = []) => collect($this->getJson("/api/v1/r/{$form}?".http_build_query(['filter' => $filter] + $extra))->assertOk()->json('data'))
        ->pluck('values.name')->sort()->values()->all();

    expect($names(['name' => 'hamad']))->toBe(['Hamad Ali', 'Resan Hamad'])                                   // contains (default)
        ->and($names(['name' => ['op' => 'starts_with', 'value' => 'hamad']]))->toBe(['Hamad Ali'])
        ->and($names(['name' => ['op' => 'equals', 'value' => 'resan hamad']]))->toBe(['Resan Hamad'])
        ->and($names(['name' => ['op' => 'contains', 'value' => '%_']]))->toBe(['50%_off'])                  // wildcards are literal
        ->and($names(['name' => ['op' => 'equals', 'value' => 'مصطفى أسعد']]))->toBe(['مصطفى أسعد'])
        ->and($names(['tier' => ['op' => 'in', 'values' => ['gold', 'bronze']]]))->toBe(['50%_off', 'Resan Hamad', 'مصطفى أسعد'])
        ->and($names(['tier' => ['op' => 'equals', 'value' => 'gol']]))->toBe([])                                  // equals is exact
        ->and($names(['tier' => ['op' => 'equals', 'value' => 'silver']]))->toBe(['Hamad Ali'])
        ->and($names([], ['uuids' => [$uuids['Hamad Ali'], $uuids['50%_off']]]))->toBe(['50%_off', 'Hamad Ali']);

    // Export selected honours the same narrowing.
    $csv = $this->get($this->postJson("/api/v1/r/{$form}/exports", ['format' => 'csv', 'uuids' => [$uuids['Hamad Ali']]])->assertOk()->json('data.url'))->streamedContent();
    expect($csv)->toContain('Hamad Ali')->not->toContain('Resan');
});

it('ignores filters on fields that are hidden from the user or not marked filterable', function () {
    [, $form] = createForm($this, 'staff');
    $filterable = ['visible' => true, 'filterable' => true, 'sortable' => true];
    $name = fieldDoc('name', 'text', null, ['table' => $filterable]);
    $salary = fieldDoc('salary', 'text', null, ['table' => $filterable]);
    $note = fieldDoc('note', 'text', null, ['table' => ['visible' => true, 'filterable' => false, 'sortable' => true]]);
    saveDraft($this, $form, [], [$name, $salary, $note])->assertOk();
    expect(publish($this, $form)['status'])->toBe('applied');
    foreach ([['Huda', 'high', 'a'], ['Omar', 'low', 'b']] as [$n, $s, $x]) {
        $this->postJson("/api/v1/r/{$form}", ['values' => ['name' => $n, 'salary' => $s, 'note' => $x]])->assertCreated();
    }

    // Salary is hidden from the user role; the role may view the form.
    $role = DB::table('roles')->where('key', 'user')->first();
    $this->putJson("/api/v1/forms/{$form}/access-rules", ['changes' => [
        ['target' => ['type' => 'field', 'uuid' => $salary['uuid']], 'subject' => ['type' => 'role', 'uuid' => $role->uuid], 'mode' => null, 'access' => 'hidden'],
    ]])->assertOk();
    $view = DB::table('permissions')->where('key', "form.{$form}.view")->value('id');
    PermissionAssignment::query()->create(['permission_id' => $view, 'subject_type' => 'role', 'subject_id' => $role->id, 'effect' => 'allow', 'include_descendants' => false]);

    $names = fn (array $filter) => collect($this->getJson("/api/v1/r/{$form}?".http_build_query(['filter' => $filter]))->assertOk()->json('data'))
        ->pluck('values.name')->sort()->values()->all();

    // The admin may filter by salary; the note is not filterable for anyone.
    expect($names(['salary' => ['op' => 'equals', 'value' => 'high']]))->toBe(['Huda'])
        ->and($names(['note' => ['op' => 'equals', 'value' => 'a']]))->toBe(['Huda', 'Omar']);

    // A user who cannot see salary cannot learn it by filtering: the filter is ignored.
    $this->flushSession();
    $this->actingAs($this->makeUser(['user']), 'web');
    expect($names(['salary' => ['op' => 'equals', 'value' => 'high']]))->toBe(['Huda', 'Omar'])
        ->and($names(['no_such_field' => 'x']))->toBe(['Huda', 'Omar'])
        ->and($names(['name' => ['op' => 'equals', 'value' => 'omar']]))->toBe(['Omar']);
});
