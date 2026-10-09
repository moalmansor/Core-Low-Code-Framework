<?php

declare(strict_types=1);

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
    $csv = $this->get("/api/v1/r/{$form}/export?".http_build_query(['format' => 'csv', 'uuids' => [$uuids['Hamad Ali']]]))->assertOk()->streamedContent();
    expect($csv)->toContain('Hamad Ali')->not->toContain('Resan');
});
