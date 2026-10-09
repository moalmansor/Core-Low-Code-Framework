<?php

declare(strict_types=1);

use App\Modules\Schema\Planning\SchemaDiffer;
use App\Support\Json\Canonical;

/** Object keys in the order MySQL's JSON type returns them (by length, then alphabetically). */
function mysqlKeyOrder(mixed $v): mixed
{
    if (! is_array($v)) {
        return $v;
    }
    if (! array_is_list($v)) {
        uksort($v, static fn (string $a, string $b): int => [strlen($a), $a] <=> [strlen($b), $b]);
    }

    return array_map(mysqlKeyOrder(...), $v);
}

it('sees no change in a stored spec whose keys were reordered by the database', function () {
    $spec = ['tables' => [[
        'role' => 'main', 'name' => 'f_visits',
        'columns' => [['name' => 'id', 'type' => 'id', 'system' => true, 'nullable' => false], ['field' => 'u1', 'name' => 'visitor', 'type' => 'string', 'length' => 255, 'nullable' => true]],
        'indexes' => [['name' => 'ix_f_visits_visitor', 'columns' => ['visitor'], 'unique' => false]],
        'foreignKeys' => [['name' => 'fk_f_visits_created_by', 'column' => 'created_by', 'references' => 'users', 'referencesColumn' => 'id', 'onDelete' => 'no_action']],
    ]]];

    $stored = mysqlKeyOrder($spec);
    expect(json_encode($stored))->not->toBe(json_encode($spec));
    expect((new SchemaDiffer)->diff($stored, $spec, '20261009'))->toBe([]);

    // A real change is still seen.
    $changed = $spec;
    $changed['tables'][0]['indexes'][0]['unique'] = true;
    expect(array_column((new SchemaDiffer)->diff($stored, $changed, '20261009'), 'op'))->toBe(['drop_index', 'add_index']);
});

it('compares JSON values regardless of object key order but keeps list order', function () {
    expect(Canonical::same(['a' => 1, 'b' => ['y' => 2, 'x' => 1]], ['b' => ['x' => 1, 'y' => 2], 'a' => 1]))->toBeTrue()
        ->and(Canonical::same([1, 2], [2, 1]))->toBeFalse()
        ->and(Canonical::same(['a' => 1], ['a' => '1']))->toBeFalse()
        ->and(Canonical::same(null, null))->toBeTrue();
});
