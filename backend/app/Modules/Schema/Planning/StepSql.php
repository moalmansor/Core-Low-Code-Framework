<?php

declare(strict_types=1);

namespace App\Modules\Schema\Planning;

use App\Infrastructure\Database\Contracts\DatabaseDriver;

/**
 * DDL statements of one step spec for a driver. Data steps (validate_data,
 * copy_data, map_status) have no DDL; the executor runs them in PHP.
 */
final class StepSql
{
    /**
     * @param  array<string, mixed>  $spec  a step's forward or reverse spec
     * @return list<string>
     */
    public static function for(DatabaseDriver $d, array $spec): array
    {
        $t = $spec['table'] ?? '';

        return match ($spec['op']) {
            'create_table', 'create_pivot' => $d->createTable(Specs::table($spec['spec'])),
            'rename_table', 'drop_table_archive', 'restore_table' => $d->renameTable($spec['from'] ?? $t, $spec['to'] ?? $spec['archived']),
            'add_column' => $d->addColumn($t, Specs::column($spec['column'])),
            'rename_column' => $d->renameColumn($t, $spec['from'], $spec['to']),
            'alter_column' => $d->alterColumnType($t, Specs::column($spec['column'])),
            'archive_column' => [
                ...$d->renameColumn($t, $spec['column'], $spec['archived']),
                ...(($spec['spec']['nullable'] ?? true) ? [] : $d->alterColumnType($t, Specs::column($spec['spec'], $spec['archived'], true))),
            ],
            'restore_column' => [
                ...(($spec['spec']['nullable'] ?? true) ? [] : $d->alterColumnType($t, Specs::column($spec['spec'], $spec['archived'], false))),
                ...$d->renameColumn($t, $spec['archived'], $spec['column']),
            ],
            'add_index' => $d->addIndex($t, Specs::index($spec['index'])),
            'drop_index' => $d->dropIndex($t, $spec['index']['name']),
            'add_foreign_key' => $d->addForeignKey($t, Specs::foreignKey($spec['foreignKey'])),
            'drop_foreign_key' => $d->dropForeignKey($t, $spec['foreignKey']['name']),
            default => [],
        };
    }

    /**
     * Reverse spec of a forward operation (architecture §12.1).
     *
     * @param  array<string, mixed>  $op
     * @return array<string, mixed>
     */
    public static function reverse(array $op, string $stamp): array
    {
        $t = $op['table'];

        return match ($op['op']) {
            'create_table', 'create_pivot' => ['op' => 'rename_table', 'table' => $t, 'from' => $t, 'to' => (new SchemaDiffer)->archiveName($t, $stamp)],
            'drop_table_archive' => ['op' => 'restore_table', 'table' => $op['archived'], 'from' => $op['archived'], 'to' => $t],
            'rename_table' => ['op' => 'rename_table', 'table' => $op['to'], 'from' => $op['to'], 'to' => $op['from']],
            'add_column' => ['op' => 'archive_column', 'table' => $t, 'column' => $op['column']['name'], 'archived' => (new SchemaDiffer)->archiveName($op['column']['name'], $stamp.'r'), 'spec' => $op['column']],
            'rename_column' => ['op' => 'rename_column', 'table' => $t, 'from' => $op['to'], 'to' => $op['from']],
            'alter_column' => ['op' => 'alter_column', 'table' => $t, 'column' => $op['previous']],
            'archive_column' => ['op' => 'restore_column', 'table' => $t, 'column' => $op['column'], 'archived' => $op['archived'], 'spec' => $op['spec']],
            'add_index' => ['op' => 'drop_index', 'table' => $t, 'index' => $op['index']],
            'drop_index' => ['op' => 'add_index', 'table' => $t, 'index' => $op['index']],
            'add_foreign_key' => ['op' => 'drop_foreign_key', 'table' => $t, 'foreignKey' => $op['foreignKey']],
            'drop_foreign_key' => ['op' => 'add_foreign_key', 'table' => $t, 'foreignKey' => $op['foreignKey']],
            'map_status' => ['op' => 'unmap_status'] + $op,
            default => ['op' => 'noop', 'table' => $t],
        };
    }
}
