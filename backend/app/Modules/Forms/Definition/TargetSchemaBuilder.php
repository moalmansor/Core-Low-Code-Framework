<?php

declare(strict_types=1);

namespace App\Modules\Forms\Definition;

use App\Infrastructure\Database\Naming;
use App\Modules\Forms\FieldTypes\FieldTypeRegistry;

/**
 * Turns a draft document into its target physical schema (architecture §11):
 * the main record table, child tables for repeaters, pivot tables for
 * many-to-many references, and foreign-key columns that inline sub-forms add to
 * the linked form's table. The result is driver-neutral and stored as the
 * `schema` section of the published definition; the schema differ compares two
 * of these, never the live database.
 *
 * Column specs: {name, type, nullable, length?, precision?, scale?, default?, field?, part?, system?}
 * where `type` is a logical type of architecture §9.2.
 */
final class TargetSchemaBuilder
{
    /**
     * @param  array<string, mixed>  $doc  draft document (§14.1 without schema)
     * @param  array<string, array{table: string, kind: string}>  $targets  form uuid => physical table of referenced forms
     * @param  array<string, array<string, mixed>>  $boundColumns  bound column name => introspected column (bound forms only)
     * @return array{tables: list<array<string, mixed>>, external: list<array<string, mixed>>}
     */
    public function build(array $doc, string $tableName, array $targets, array $boundColumns = []): array
    {
        $form = $doc['form'];
        $groups = [];
        foreach ($doc['groups'] as $g) {
            $groups[$g['uuid']] = $g;
        }
        $relations = [];
        foreach ($doc['relations'] as $r) {
            $relations[$r['uuid']] = $r;
        }
        $bound = ($form['bindingMode'] ?? 'managed') === 'bound';

        $main = $this->table($tableName, 'main');
        $main['columns'] = $this->mainSystemColumns();
        $children = [];
        $pivots = [];
        $external = [];

        // Child tables for repeater groups (one level, see DraftValidator).
        foreach ($groups as $uuid => $g) {
            if ($g['type'] === 'repeater') {
                $name = Naming::fit($tableName.'__'.$g['key']);
                $child = $this->table($name, 'child');
                $child['group'] = $uuid;
                $child['columns'] = $this->childSystemColumns();
                $child['indexes'][] = $this->index($name, ['parent_id', 'sort_order']);
                $child['foreignKeys'][] = $this->fk($name, 'parent_id', $tableName, 'cascade');
                $children[$uuid] = $child;
            }
        }

        foreach ($doc['fields'] as $field) {
            $type = FieldTypeRegistry::has($field['type']) ? FieldTypeRegistry::get($field['type']) : null;
            if ($type === null || ! $type->isStored()) {
                continue;
            }
            $repeater = $this->repeaterOf($field['group'] ?? null, $groups);
            if ($repeater !== null && isset($children[$repeater])) {
                $target = &$children[$repeater];
            } else {
                $target = &$main;
            }
            $storage = $field['storage'] ?? [];
            if ($bound && $repeater === null && ! empty($storage['boundColumn'])) {
                // A bound column already exists; it is recorded but never created or altered.
                $col = $boundColumns[$storage['boundColumn']] ?? null;
                $target['columns'][] = ['name' => $storage['boundColumn'], 'type' => $col['logical'] ?? 'string', 'nullable' => (bool) ($col['nullable'] ?? true), 'field' => $field['uuid'], 'bound' => true];
                unset($target);

                continue;
            }
            $relation = isset($field['relation']) ? ($relations[$field['relation']] ?? null) : null;
            foreach ($this->fieldColumns($field, $type->storage, $relation, $targets) as $column) {
                $fkTable = $column['fk'] ?? null;
                $onDelete = $column['onDelete'] ?? 'no action';
                unset($column['fk'], $column['onDelete']);
                $target['columns'][] = $column;
                if ($fkTable !== null) {
                    $target['indexes'][] = $this->index($target['name'], [$column['name']]);
                    $target['foreignKeys'][] = $this->fk($target['name'], $column['name'], $fkTable, $onDelete);
                }
            }
            // Pivot tables for multi-valued references.
            if ($relation !== null && $relation['type'] === 'many_to_many') {
                $pivotName = Naming::fit('p_'.$form['key'].'__'.$relation['key']);
                $targetTable = $this->referenceTable($field, $type->storage, $relation, $targets);
                if ($targetTable !== null && ! isset($pivots[$pivotName])) {
                    $pivots[$pivotName] = $this->pivot($pivotName, $target['name'], $targetTable, $relation['uuid']);
                }
            }
            // Field-level indexes.
            $index = $storage['index'] ?? 'none';
            $tableSettings = $field['table'] ?? [];
            $columnName = $this->columnName($field);
            $primary = $this->primaryColumn($target['columns'], $field['uuid']);
            if ($primary !== null && $this->indexable($primary)) {
                if ($index === 'unique' || $type->storage === 'auto_number') {
                    $scopeColumns = $this->scopeColumns($storage['uniqueScope'] ?? [], $doc['fields']);
                    $cols = [...$scopeColumns, $primary['name']];
                    $target['indexes'][] = $this->index($target['name'], $cols, true, array_values(array_filter($cols, fn (string $c) => $this->isNullable($target['columns'], $c))));
                } elseif ($index === 'index' || ($tableSettings['filterable'] ?? false) || ($tableSettings['sortable'] ?? false)) {
                    if (! $this->hasIndexOn($target['indexes'], [$primary['name']])) {
                        $target['indexes'][] = $this->index($target['name'], [$primary['name']]);
                    }
                }
            }
            unset($target);
            unset($columnName);
        }

        // Inline sub-forms of linked forms: the FK lives in the linked form's table.
        foreach ($groups as $uuid => $g) {
            if ($g['type'] !== 'subform' || empty($g['subform']['relation'])) {
                continue;
            }
            $relation = $relations[$g['subform']['relation']] ?? null;
            $targetTable = $relation !== null ? ($targets[$relation['target']]['table'] ?? null) : null;
            if ($relation === null || $targetTable === null) {
                continue;
            }
            $column = Naming::fit($form['key'].'_'.$relation['key'].'_id');
            $external[] = [
                'table' => $targetTable,
                'relation' => $relation['uuid'],
                'column' => ['name' => $column, 'type' => 'bigint', 'unsigned' => true, 'nullable' => true, 'relation' => $relation['uuid']],
                'index' => $this->index($targetTable, [$column]),
                'foreignKey' => $this->fk($targetTable, $column, $tableName, 'no action'),
            ];
        }

        $main['indexes'] = [...$this->mainSystemIndexes($tableName), ...$main['indexes']];
        $main['foreignKeys'] = [...$this->mainSystemForeignKeys($tableName), ...$main['foreignKeys']];
        $tables = [$main];
        foreach ($children as $child) {
            $child['indexes'] = [...$this->childSystemIndexes($child['name']), ...$child['indexes']];
            $child['foreignKeys'] = [...$this->childSystemForeignKeys($child['name']), ...$child['foreignKeys']];
            $tables[] = $child;
        }
        foreach ($pivots as $pivot) {
            $tables[] = $pivot;
        }

        return ['tables' => array_map($this->dedupe(...), $tables), 'external' => $external];
    }

    /** Physical column of a field (its key unless overridden). */
    public function columnName(array $field): string
    {
        return (string) (($field['storage']['column'] ?? null) ?: $field['key']);
    }

    /**
     * Columns of one field (architecture §11.3).
     *
     * @param  array<string, mixed>  $field
     * @param  array<string, mixed>|null  $relation
     * @param  array<string, array{table: string, kind: string}>  $targets
     * @return list<array<string, mixed>>
     */
    public function fieldColumns(array $field, string $storageKind, ?array $relation, array $targets): array
    {
        $s = $field['storage'] ?? [];
        $name = $this->columnName($field);
        $nullable = (bool) ($s['nullable'] ?? true);
        $flags = $field['flags'] ?? [];
        $base = ['field' => $field['uuid'], 'nullable' => $nullable];
        $default = array_key_exists('default', $s) ? $s['default'] : null;
        $type = FieldTypeRegistry::get($field['type']);

        if (($flags['encrypted'] ?? false) === true) {
            $cols = [$base + ['name' => $name, 'type' => 'text', 'nullable' => true, 'encrypted' => true]];
            if (($flags['blindIndex'] ?? false) === true) {
                $cols[] = $base + ['name' => $name.'__bidx', 'type' => 'hash', 'nullable' => true, 'part' => 'bidx'];
            }

            return $cols;
        }
        $override = $s['dbType'] ?? null;
        $col = fn (string $logical, array $extra = []): array => $base + ['name' => $name, 'type' => $logical] + $extra + ($default !== null ? ['default' => $default] : []);
        $decimal = fn (): array => $col('decimal', ['precision' => (int) ($s['precision'] ?? $type->defaultPrecision ?? 19), 'scale' => (int) ($s['scale'] ?? $type->defaultScale ?? 4)]);

        $refTable = $this->referenceTable($field, $storageKind, $relation, $targets);
        $multiRef = $relation !== null && $relation['type'] === 'many_to_many';

        return match ($storageKind) {
            'string' => [$override === 'text' ? $col('text') : $col('string', ['length' => (int) ($s['length'] ?? $type->defaultLength)])],
            'text' => [$col($override === 'longtext' ? 'longtext' : 'text')],
            'longtext' => [$col('longtext')],
            'decimal', 'number' => [match ($override) {
                'int' => $col('int'),
                'bigint' => $col('bigint'),
                default => $decimal(),
            }],
            'int' => [$override === 'decimal' ? $decimal() : $col($override === 'bigint' ? 'bigint' : 'int')],
            'bool' => [$col('bool')],
            'date' => [$col('date')],
            'time' => [$col('time')],
            'datetime' => [$col('datetime')],
            'duration' => [$col('bigint')],
            'json' => [$col('json')],
            'choice' => $refTable !== null && ! $multiRef
                ? [$base + ['name' => $name, 'type' => 'bigint', 'unsigned' => true, 'fk' => $refTable, 'onDelete' => 'no action']]
                : ($refTable !== null ? [] : [$col('string', ['length' => (int) ($s['length'] ?? 255)])]),
            'multi_choice' => $refTable !== null ? [] : [$col('json')],
            'lookup', 'user', 'role', 'department' => $multiRef || $refTable === null
                ? []
                : [$base + ['name' => $name, 'type' => 'bigint', 'unsigned' => true, 'fk' => $refTable, 'onDelete' => 'no action']],
            'file' => [$base + ['name' => $name, 'type' => 'bigint', 'unsigned' => true, 'fk' => 'files', 'onDelete' => 'no action']],
            'files' => [$col('json')],
            'range_date' => [$base + ['name' => $name.'__from', 'type' => 'date', 'part' => 'from'], $base + ['name' => $name.'__to', 'type' => 'date', 'part' => 'to']],
            'range_time' => [$base + ['name' => $name.'__from', 'type' => 'time', 'part' => 'from'], $base + ['name' => $name.'__to', 'type' => 'time', 'part' => 'to']],
            'range_datetime' => [$base + ['name' => $name.'__from', 'type' => 'datetime', 'part' => 'from'], $base + ['name' => $name.'__to', 'type' => 'datetime', 'part' => 'to']],
            'map' => [
                $base + ['name' => $name.'__lat', 'type' => 'decimal', 'precision' => 10, 'scale' => 7, 'part' => 'lat'],
                $base + ['name' => $name.'__lng', 'type' => 'decimal', 'precision' => 10, 'scale' => 7, 'part' => 'lng'],
                $base + ['name' => $name.'__label', 'type' => 'string', 'length' => 255, 'nullable' => true, 'part' => 'label'],
            ],
            'phone' => [
                $base + ['name' => $name, 'type' => 'string', 'length' => 20],
                $base + ['name' => $name.'__country', 'type' => 'code', 'length' => 2, 'nullable' => true, 'part' => 'country'],
            ],
            'currency' => array_values(array_filter([
                $decimal(),
                ($s['multiCurrency'] ?? false) ? $base + ['name' => $name.'__currency', 'type' => 'code', 'length' => 3, 'nullable' => true, 'part' => 'currency'] : null,
            ])),
            'consent' => [
                $col('bool'),
                $base + ['name' => $name.'__at', 'type' => 'datetime', 'nullable' => true, 'part' => 'at'],
            ],
            'auto_number' => [$base + ['name' => $name, 'type' => 'string', 'length' => 64, 'nullable' => true]],
            'formula' => [$this->formulaColumn($field, $base + ['name' => $name])],
            default => [],
        };
    }

    /**
     * Physical table a reference field points at, or null when it stores plain values.
     *
     * @param  array<string, mixed>|null  $relation
     * @param  array<string, array{table: string, kind: string}>  $targets
     */
    public function referenceTable(array $field, string $storageKind, ?array $relation, array $targets): ?string
    {
        return match ($storageKind) {
            'user' => 'users',
            'role' => 'roles',
            'department' => 'departments',
            'lookup', 'choice', 'multi_choice' => $relation !== null ? ($targets[$relation['target']]['table'] ?? null) : null,
            default => null,
        };
    }

    /** @param  array<string, mixed>  $base */
    private function formulaColumn(array $field, array $base): array
    {
        $s = $field['storage'] ?? [];

        return match ($s['dbType'] ?? 'decimal') {
            'string' => $base + ['type' => 'string', 'length' => (int) ($s['length'] ?? 255), 'nullable' => true],
            'text' => $base + ['type' => 'text', 'nullable' => true],
            'date' => $base + ['type' => 'date', 'nullable' => true],
            'datetime' => $base + ['type' => 'datetime', 'nullable' => true],
            'time' => $base + ['type' => 'time', 'nullable' => true],
            'bool' => $base + ['type' => 'bool', 'nullable' => true],
            'bigint', 'int' => $base + ['type' => 'bigint', 'nullable' => true],
            'json' => $base + ['type' => 'json', 'nullable' => true],
            default => $base + ['type' => 'decimal', 'precision' => (int) ($s['precision'] ?? 19), 'scale' => (int) ($s['scale'] ?? 4), 'nullable' => true],
        };
    }

    /** @return list<array<string, mixed>> */
    private function mainSystemColumns(): array
    {
        return [
            ['name' => 'id', 'type' => 'id', 'nullable' => false, 'system' => true],
            ['name' => 'uuid', 'type' => 'uuid', 'nullable' => false, 'system' => true],
            ['name' => 'organization_id', 'type' => 'bigint', 'nullable' => false, 'system' => true],
            ['name' => 'form_version_id', 'type' => 'bigint', 'nullable' => false, 'system' => true],
            ['name' => 'record_number', 'type' => 'string', 'length' => 64, 'nullable' => true, 'system' => true],
            ['name' => 'status_id', 'type' => 'bigint', 'nullable' => true, 'system' => true],
            ['name' => 'status_changed_at', 'type' => 'datetime', 'nullable' => true, 'system' => true],
            ['name' => 'row_version', 'type' => 'bigint', 'nullable' => false, 'default' => 1, 'system' => true],
            ['name' => 'owner_user_id', 'type' => 'bigint', 'nullable' => true, 'system' => true],
            ['name' => 'owner_department_id', 'type' => 'bigint', 'nullable' => true, 'system' => true],
            ['name' => 'created_by', 'type' => 'bigint', 'nullable' => true, 'system' => true],
            ['name' => 'updated_by', 'type' => 'bigint', 'nullable' => true, 'system' => true],
            ['name' => 'created_at', 'type' => 'datetime', 'nullable' => false, 'system' => true],
            ['name' => 'updated_at', 'type' => 'datetime', 'nullable' => false, 'system' => true],
            ['name' => 'deleted_at', 'type' => 'datetime', 'nullable' => true, 'system' => true],
            ['name' => 'deleted_by', 'type' => 'bigint', 'nullable' => true, 'system' => true],
            ['name' => 'legal_hold', 'type' => 'bool', 'nullable' => false, 'default' => false, 'system' => true],
            ['name' => 'search_text', 'type' => 'text', 'nullable' => true, 'system' => true],
            ['name' => 'external_user_id', 'type' => 'bigint', 'nullable' => true, 'system' => true],
        ];
    }

    /** @return list<array<string, mixed>> */
    private function mainSystemIndexes(string $t): array
    {
        return [
            $this->index($t, ['uuid'], true),
            $this->index($t, ['organization_id', 'deleted_at', 'id']),
            $this->index($t, ['status_id', 'updated_at']),
            $this->index($t, ['owner_user_id']),
            $this->index($t, ['owner_department_id']),
            $this->index($t, ['created_at']),
            $this->index($t, ['record_number'], true, ['record_number']),
            $this->index($t, ['form_version_id']),
            $this->index($t, ['created_by']),
            $this->index($t, ['updated_by']),
            $this->index($t, ['deleted_by']),
            $this->index($t, ['external_user_id']),
        ];
    }

    /** @return list<array<string, mixed>> */
    private function mainSystemForeignKeys(string $t): array
    {
        return [
            $this->fk($t, 'organization_id', 'organizations'),
            $this->fk($t, 'form_version_id', 'form_versions'),
            $this->fk($t, 'owner_user_id', 'users'),
            $this->fk($t, 'owner_department_id', 'departments'),
            $this->fk($t, 'created_by', 'users'),
            $this->fk($t, 'updated_by', 'users'),
            $this->fk($t, 'deleted_by', 'users'),
        ];
    }

    /** @return list<array<string, mixed>> */
    private function childSystemColumns(): array
    {
        return [
            ['name' => 'id', 'type' => 'id', 'nullable' => false, 'system' => true],
            ['name' => 'uuid', 'type' => 'uuid', 'nullable' => false, 'system' => true],
            ['name' => 'organization_id', 'type' => 'bigint', 'nullable' => false, 'system' => true],
            ['name' => 'parent_id', 'type' => 'bigint', 'nullable' => false, 'system' => true],
            ['name' => 'sort_order', 'type' => 'int', 'nullable' => false, 'default' => 0, 'system' => true],
            ['name' => 'created_by', 'type' => 'bigint', 'nullable' => true, 'system' => true],
            ['name' => 'updated_by', 'type' => 'bigint', 'nullable' => true, 'system' => true],
            ['name' => 'created_at', 'type' => 'datetime', 'nullable' => false, 'system' => true],
            ['name' => 'updated_at', 'type' => 'datetime', 'nullable' => false, 'system' => true],
        ];
    }

    /** @return list<array<string, mixed>> */
    private function childSystemIndexes(string $t): array
    {
        return [
            $this->index($t, ['uuid'], true),
            $this->index($t, ['organization_id']),
            $this->index($t, ['created_by']),
            $this->index($t, ['updated_by']),
        ];
    }

    /** @return list<array<string, mixed>> */
    private function childSystemForeignKeys(string $t): array
    {
        return [
            $this->fk($t, 'organization_id', 'organizations'),
            $this->fk($t, 'created_by', 'users'),
            $this->fk($t, 'updated_by', 'users'),
        ];
    }

    /** @return array<string, mixed> */
    private function pivot(string $name, string $sourceTable, string $targetTable, string $relationUuid): array
    {
        $pivot = $this->table($name, 'pivot');
        $pivot['relation'] = $relationUuid;
        $pivot['columns'] = [
            ['name' => 'id', 'type' => 'id', 'nullable' => false, 'system' => true],
            ['name' => 'source_id', 'type' => 'bigint', 'nullable' => false, 'system' => true],
            ['name' => 'target_id', 'type' => 'bigint', 'nullable' => false, 'system' => true],
            ['name' => 'sort_order', 'type' => 'int', 'nullable' => false, 'default' => 0, 'system' => true],
            ['name' => 'created_at', 'type' => 'datetime', 'nullable' => false, 'system' => true],
            ['name' => 'created_by', 'type' => 'bigint', 'nullable' => true, 'system' => true],
        ];
        $pivot['indexes'] = [
            $this->index($name, ['source_id', 'target_id'], true),
            $this->index($name, ['target_id']),
            $this->index($name, ['created_by']),
        ];
        $pivot['foreignKeys'] = [
            $this->fk($name, 'source_id', $sourceTable, 'cascade'),
            $this->fk($name, 'target_id', $targetTable),
            $this->fk($name, 'created_by', 'users'),
        ];

        return $pivot;
    }

    /** @return array<string, mixed> */
    private function table(string $name, string $role): array
    {
        return ['name' => $name, 'role' => $role, 'columns' => [], 'indexes' => [], 'foreignKeys' => []];
    }

    /**
     * @param  list<string>  $columns
     * @param  list<string>  $nullable
     * @return array<string, mixed>
     */
    private function index(string $table, array $columns, bool $unique = false, array $nullable = []): array
    {
        return ['name' => Naming::constraint($unique ? 'uq' : 'ix', $table, $columns), 'columns' => $columns, 'unique' => $unique, 'nullable' => $nullable];
    }

    /** @return array<string, mixed> */
    private function fk(string $table, string $column, string $references, string $onDelete = 'no action'): array
    {
        return ['name' => Naming::constraint('fk', $table, [$column]), 'column' => $column, 'references' => $references, 'referencesColumn' => 'id', 'onDelete' => $onDelete];
    }

    /** @param  array<string, array<string, mixed>>  $groups */
    private function repeaterOf(?string $groupUuid, array $groups): ?string
    {
        $seen = [];
        while ($groupUuid !== null && isset($groups[$groupUuid]) && ! isset($seen[$groupUuid])) {
            $seen[$groupUuid] = true;
            if ($groups[$groupUuid]['type'] === 'repeater') {
                return $groupUuid;
            }
            $groupUuid = $groups[$groupUuid]['parent'] ?? null;
        }

        return null;
    }

    /**
     * @param  list<string>  $scopeUuids
     * @param  list<array<string, mixed>>  $fields
     * @return list<string>
     */
    private function scopeColumns(array $scopeUuids, array $fields): array
    {
        $out = [];
        foreach ($fields as $f) {
            if (in_array($f['uuid'], $scopeUuids, true)) {
                $out[] = $this->columnName($f);
            }
        }

        return $out;
    }

    /** @param  list<array<string, mixed>>  $columns */
    private function primaryColumn(array $columns, string $fieldUuid): ?array
    {
        foreach ($columns as $c) {
            if (($c['field'] ?? null) === $fieldUuid && ! isset($c['part'])) {
                return $c;
            }
        }

        return null;
    }

    /** @param  array<string, mixed>  $column */
    private function indexable(array $column): bool
    {
        return in_array($column['type'], ['string', 'code', 'int', 'bigint', 'decimal', 'bool', 'date', 'time', 'datetime', 'hash', 'uuid'], true)
            && ! ($column['type'] === 'string' && (int) ($column['length'] ?? 255) > 400)
            && ! ($column['bound'] ?? false);
    }

    /** @param  list<array<string, mixed>>  $columns */
    private function isNullable(array $columns, string $name): bool
    {
        foreach ($columns as $c) {
            if ($c['name'] === $name) {
                return (bool) $c['nullable'];
            }
        }

        return true;
    }

    /**
     * @param  list<array<string, mixed>>  $indexes
     * @param  list<string>  $columns
     */
    private function hasIndexOn(array $indexes, array $columns): bool
    {
        foreach ($indexes as $i) {
            if ($i['columns'] === $columns) {
                return true;
            }
        }

        return false;
    }

    /**
     * Drops duplicate index / FK names (e.g. an FK column that is also
     * filterable) keeping the first.
     *
     * @param  array<string, mixed>  $table
     * @return array<string, mixed>
     */
    private function dedupe(array $table): array
    {
        foreach (['indexes', 'foreignKeys'] as $key) {
            $seen = [];
            $table[$key] = array_values(array_filter($table[$key], static function (array $o) use (&$seen): bool {
                if (isset($seen[$o['name']])) {
                    return false;
                }

                return $seen[$o['name']] = true;
            }));
        }

        return $table;
    }
}
