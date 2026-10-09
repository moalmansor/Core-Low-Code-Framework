<?php

declare(strict_types=1);

namespace App\Modules\Schema\Planning;

use App\Support\Json\Canonical;

/**
 * Compares two target schemas (the `schema` sections of the current and the
 * new definition — architecture §11.4) and returns the driver-neutral
 * operations that turn one into the other, in a safe order:
 *
 *   create tables → drop changed FKs/indexes → rename tables/columns →
 *   archive removed columns → add columns → type changes (validate, add new,
 *   copy, swap) → widen columns → add indexes → add FKs → archive removed tables
 *
 * Columns are matched by the field uuid (and part) that produced them, so a
 * renamed key is a rename, never drop + add. Nothing is ever dropped: removed
 * columns and tables are archived under a `zz_` name (§11.5).
 */
final class SchemaDiffer
{
    /**
     * @param  array{tables: list<array<string, mixed>>, external?: list<array<string, mixed>>}|null  $old
     * @param  array{tables: list<array<string, mixed>>, external?: list<array<string, mixed>>}  $new
     * @param  array<string, list<string>>  $liveColumns  existing columns of bound tables (table => names)
     * @return list<array<string, mixed>>
     */
    public function diff(?array $old, array $new, string $stamp, array $liveColumns = []): array
    {
        $oldTables = $this->byIdentity($old['tables'] ?? []);
        $newTables = $this->byIdentity($new['tables']);
        $phase = array_fill_keys(['create', 'drop_fk', 'drop_index', 'rename_table', 'rename_column', 'archive_column', 'add_column', 'type_change', 'alter_column', 'add_index', 'add_fk', 'archive_table'], []);

        foreach ($newTables as $id => $t) {
            $o = $oldTables[$id] ?? null;
            if ($o === null) {
                if (isset($liveColumns[$t['name']])) {
                    // Bound table: add the missing system columns only, never touch the rest.
                    foreach ($t['columns'] as $c) {
                        if (($c['system'] ?? false) && ! in_array($c['name'], $liveColumns[$t['name']], true) && $c['type'] !== 'id') {
                            $phase['add_column'][] = ['op' => 'add_column', 'table' => $t['name'], 'column' => $c + ['nullable' => true]];
                        }
                    }
                    foreach ($t['indexes'] as $i) {
                        $phase['add_index'][] = ['op' => 'add_index', 'table' => $t['name'], 'index' => $i];
                    }
                    foreach ($t['foreignKeys'] as $f) {
                        $phase['add_fk'][] = ['op' => 'add_foreign_key', 'table' => $t['name'], 'foreignKey' => $f];
                    }

                    continue;
                }
                $phase['create'][] = ['op' => $t['role'] === 'pivot' ? 'create_pivot' : 'create_table', 'table' => $t['name'], 'spec' => $t];

                continue;
            }
            $table = $t['name'];
            if ($o['name'] !== $t['name']) {
                $phase['rename_table'][] = ['op' => 'rename_table', 'table' => $o['name'], 'from' => $o['name'], 'to' => $t['name']];
            }
            $oldCols = $this->columnsByIdentity($o['columns']);
            $newCols = $this->columnsByIdentity($t['columns']);
            $retyped = [];
            $renamedColumns = [];
            foreach ($newCols as $cid => $c) {
                $oc = $oldCols[$cid] ?? null;
                if ($oc === null) {
                    $phase['add_column'][] = ['op' => 'add_column', 'table' => $table, 'column' => $c];

                    continue;
                }
                if (($c['bound'] ?? false) || ($oc['bound'] ?? false)) {
                    continue;
                }
                if ($oc['name'] !== $c['name']) {
                    $phase['rename_column'][] = ['op' => 'rename_column', 'table' => $table, 'from' => $oc['name'], 'to' => $c['name']];
                    $renamedColumns[$oc['name']] = $c['name'];
                }
                $change = $this->columnChange($oc, $c);
                if ($change === 'widen') {
                    $phase['alter_column'][] = ['op' => 'alter_column', 'table' => $table, 'column' => $c, 'previous' => $oc + ['name' => $c['name']]];
                } elseif ($change === 'retype') {
                    $retyped[$c['name']] = true;
                    $temp = $this->tempName($c['name']);
                    $archive = $this->archiveName($c['name'], $stamp);
                    $phase['type_change'][] = ['op' => 'validate_data', 'table' => $table, 'column' => $c['name'], 'from' => $oc, 'to' => $c];
                    $phase['type_change'][] = ['op' => 'add_column', 'table' => $table, 'column' => ['name' => $temp, 'nullable' => true] + $c];
                    $phase['type_change'][] = ['op' => 'copy_data', 'table' => $table, 'from' => $c['name'], 'to' => $temp, 'fromSpec' => $oc, 'toSpec' => $c];
                    $phase['type_change'][] = ['op' => 'archive_column', 'table' => $table, 'column' => $c['name'], 'archived' => $archive, 'spec' => $oc];
                    $phase['type_change'][] = ['op' => 'rename_column', 'table' => $table, 'from' => $temp, 'to' => $c['name']];
                    if (! $c['nullable'] || ($c['default'] ?? null) !== null) {
                        $phase['type_change'][] = ['op' => 'alter_column', 'table' => $table, 'column' => $c, 'previous' => ['nullable' => true] + $c];
                    }
                }
            }
            $archived = [];
            foreach ($oldCols as $cid => $oc) {
                if (! isset($newCols[$cid]) && ! ($oc['bound'] ?? false) && ! ($oc['system'] ?? false)) {
                    $archived[$oc['name']] = true;
                    $phase['archive_column'][] = ['op' => 'archive_column', 'table' => $table, 'column' => $oc['name'], 'archived' => $this->archiveName($oc['name'], $stamp), 'spec' => $oc];
                }
            }
            // Indexes and FKs: by name; anything touching an archived/retyped column is dropped first and re-added.
            $touched = static fn (array $columns) => array_intersect($columns, [...array_keys($archived), ...array_keys($retyped), ...array_keys($renamedColumns)]) !== [];
            $oldIdx = array_column($o['indexes'], null, 'name');
            $newIdx = array_column($t['indexes'], null, 'name');
            foreach ($oldIdx as $name => $i) {
                $n = $newIdx[$name] ?? null;
                if ($n === null || $this->differs($i, $n) || $touched($i['columns'])) {
                    $phase['drop_index'][] = ['op' => 'drop_index', 'table' => $o['name'], 'index' => $i];
                    unset($oldIdx[$name]);
                }
            }
            foreach ($newIdx as $name => $i) {
                if (! isset($oldIdx[$name])) {
                    $phase['add_index'][] = ['op' => 'add_index', 'table' => $table, 'index' => $i];
                }
            }
            $oldFk = array_column($o['foreignKeys'], null, 'name');
            $newFk = array_column($t['foreignKeys'], null, 'name');
            foreach ($oldFk as $name => $f) {
                $n = $newFk[$name] ?? null;
                if ($n === null || $this->differs($f, $n) || $touched([$f['column']])) {
                    $phase['drop_fk'][] = ['op' => 'drop_foreign_key', 'table' => $o['name'], 'foreignKey' => $f];
                    unset($oldFk[$name]);
                }
            }
            foreach ($newFk as $name => $f) {
                if (! isset($oldFk[$name])) {
                    $phase['add_fk'][] = ['op' => 'add_foreign_key', 'table' => $table, 'foreignKey' => $f];
                }
            }
        }
        foreach ($oldTables as $id => $o) {
            if (! isset($newTables[$id])) {
                foreach ($o['foreignKeys'] as $f) {
                    $phase['drop_fk'][] = ['op' => 'drop_foreign_key', 'table' => $o['name'], 'foreignKey' => $f];
                }
                $phase['archive_table'][] = ['op' => 'drop_table_archive', 'table' => $o['name'], 'archived' => $this->archiveName($o['name'], $stamp)];
            }
        }

        // Foreign-key columns this form adds to linked forms' tables (inline sub-forms).
        $oldExt = array_column($old['external'] ?? [], null, 'relation');
        $newExt = array_column($new['external'] ?? [], null, 'relation');
        foreach ($newExt as $rel => $e) {
            $o = $oldExt[$rel] ?? null;
            if ($o !== null && $o['table'] === $e['table'] && $o['column']['name'] === $e['column']['name']) {
                continue;
            }
            if ($o !== null) {
                $phase['drop_fk'][] = ['op' => 'drop_foreign_key', 'table' => $o['table'], 'foreignKey' => $o['foreignKey']];
                $phase['drop_index'][] = ['op' => 'drop_index', 'table' => $o['table'], 'index' => $o['index']];
                $phase['archive_column'][] = ['op' => 'archive_column', 'table' => $o['table'], 'column' => $o['column']['name'], 'archived' => $this->archiveName($o['column']['name'], $stamp), 'spec' => $o['column']];
            }
            $phase['add_column'][] = ['op' => 'add_column', 'table' => $e['table'], 'column' => $e['column']];
            $phase['add_index'][] = ['op' => 'add_index', 'table' => $e['table'], 'index' => $e['index']];
            $phase['add_fk'][] = ['op' => 'add_foreign_key', 'table' => $e['table'], 'foreignKey' => $e['foreignKey']];
        }
        foreach ($oldExt as $rel => $o) {
            if (! isset($newExt[$rel])) {
                $phase['drop_fk'][] = ['op' => 'drop_foreign_key', 'table' => $o['table'], 'foreignKey' => $o['foreignKey']];
                $phase['drop_index'][] = ['op' => 'drop_index', 'table' => $o['table'], 'index' => $o['index']];
                $phase['archive_column'][] = ['op' => 'archive_column', 'table' => $o['table'], 'column' => $o['column']['name'], 'archived' => $this->archiveName($o['column']['name'], $stamp), 'spec' => $o['column']];
            }
        }

        // Child tables are created after their parent; pivots after both sides.
        usort($phase['create'], static fn (array $a, array $b): int => ['main' => 0, 'child' => 1, 'pivot' => 2][$a['spec']['role']] <=> ['main' => 0, 'child' => 1, 'pivot' => 2][$b['spec']['role']]);

        return array_merge(...array_values($phase));
    }

    /**
     * none | widen (in-place, data always fits) | retype (new column + copy).
     *
     * @param  array<string, mixed>  $old
     * @param  array<string, mixed>  $new
     */
    public function columnChange(array $old, array $new): string
    {
        $same = static fn (string $k): bool => ($old[$k] ?? null) === ($new[$k] ?? null);
        if ($old['type'] === $new['type'] && $same('length') && $same('precision') && $same('scale') && $same('nullable') && ($old['default'] ?? null) == ($new['default'] ?? null)) {
            return 'none';
        }
        if ($old['type'] === $new['type']) {
            $lengthOk = ($new['length'] ?? 0) >= ($old['length'] ?? 0);
            $intDigitsOld = ($old['precision'] ?? 0) - ($old['scale'] ?? 0);
            $intDigitsNew = ($new['precision'] ?? 0) - ($new['scale'] ?? 0);
            $decimalOk = $old['type'] !== 'decimal' || ($intDigitsNew >= $intDigitsOld && ($new['scale'] ?? 0) >= ($old['scale'] ?? 0));
            $nullOk = $new['nullable'] || ! $old['nullable'];
            if ($lengthOk && $decimalOk && $nullOk) {
                return 'widen';
            }
        }
        $widenings = ['string>text', 'string>longtext', 'text>longtext', 'int>bigint', 'smallint>int', 'smallint>bigint'];
        if (in_array($old['type'].'>'.$new['type'], $widenings, true) && ($new['nullable'] || ! $old['nullable'])) {
            return 'widen';
        }

        return 'retype';
    }

    public function archiveName(string $name, string $stamp): string
    {
        return substr('zz_'.$name, 0, 60 - strlen($stamp) - 1).'_'.$stamp;
    }

    private function tempName(string $name): string
    {
        return substr($name, 0, 55).'__new';
    }

    /**
     * @param  list<array<string, mixed>>  $tables
     * @return array<string, array<string, mixed>>
     */
    private function byIdentity(array $tables): array
    {
        $out = [];
        foreach ($tables as $t) {
            $id = match ($t['role']) {
                'main' => 'main',
                'child' => 'child:'.$t['group'],
                'pivot' => 'pivot:'.$t['relation'],
                default => 'table:'.$t['name'],
            };
            $out[$id] = $t;
        }

        return $out;
    }

    /**
     * @param  list<array<string, mixed>>  $columns
     * @return array<string, array<string, mixed>>
     */
    private function columnsByIdentity(array $columns): array
    {
        $out = [];
        foreach ($columns as $c) {
            $id = isset($c['field']) ? 'f:'.$c['field'].':'.($c['part'] ?? '') : 's:'.$c['name'];
            $out[$id] = $c;
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $a
     * @param  array<string, mixed>  $b
     */
    private function differs(array $a, array $b): bool
    {
        return ! Canonical::same($a, $b);
    }
}
