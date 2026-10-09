<?php

declare(strict_types=1);

namespace App\Modules\Schema\Planning;

use App\Infrastructure\Database\Contracts\DatabaseDriver;
use App\Infrastructure\Database\Specs\DdlOperation;
use Throwable;

/**
 * Turns differ operations into migration steps (architecture §11.4, §12.1):
 * forward and reverse specs, destructive/online flags, an estimated duration
 * and the SQL each engine would run.
 */
final class MigrationPlanner
{
    /** Steps that hide or rewrite existing data and therefore take a data backup first. */
    public const DESTRUCTIVE = ['archive_column', 'drop_table_archive', 'copy_data'];

    public function __construct(private readonly DatabaseDriver $driver, private readonly PreviewDrivers $preview) {}

    /**
     * @param  list<array<string, mixed>>  $operations
     * @return list<array<string, mixed>> step rows (without plan id)
     */
    public function plan(array $operations, string $stamp): array
    {
        $existing = array_flip(array_map('strtolower', $this->driver->tables()));
        $steps = [];
        foreach ($operations as $i => $op) {
            $impact = $this->impact($op, isset($existing[strtolower($op['table'])]));
            $preview = [];
            foreach ($this->preview->all() as $engine => $driver) {
                try {
                    $sql = StepSql::for($driver, $op);
                } catch (Throwable $e) {
                    $sql = ['-- '.$e->getMessage()];
                }
                $preview[] = "-- {$engine}\n".($sql === [] ? '-- data step: executed by the framework in batches' : implode(";\n", $sql).';');
            }
            $steps[] = [
                'sequence' => $i + 1,
                'operation' => $op['op'] === 'create_pivot' ? 'create_pivot' : $op['op'],
                'table_name' => $op['table'],
                'forward' => $op,
                'reverse' => StepSql::reverse($op, $stamp),
                'sql_preview' => implode("\n\n", $preview),
                'is_destructive' => in_array($op['op'], self::DESTRUCTIVE, true),
                'is_online' => $impact['online'],
                'estimated_ms' => $impact['estimatedMs'],
                'lock_level' => $impact['lockLevel'],
                'rows' => $impact['rows'],
            ];
        }

        return $steps;
    }

    /**
     * Rollback class of a plan (architecture §13.4).
     *
     * @param  list<array<string, mixed>>  $operations
     */
    public static function changeClass(array $operations): string
    {
        if ($operations === []) {
            return 'metadata_only';
        }
        foreach ($operations as $op) {
            if (in_array($op['op'], ['archive_column', 'drop_table_archive', 'copy_data', 'validate_data'], true)) {
                return 'destructive';
            }
        }

        return 'additive_schema';
    }

    /**
     * @param  array<string, mixed>  $op
     * @return array{online: bool, estimatedMs: int, lockLevel: string, rows: int}
     */
    private function impact(array $op, bool $tableExists): array
    {
        $ddl = match ($op['op']) {
            'create_table', 'create_pivot' => DdlOperation::CreateTable,
            'add_column' => DdlOperation::AddColumn,
            'rename_column', 'archive_column', 'restore_column', 'rename_table', 'drop_table_archive' => DdlOperation::RenameColumn,
            'alter_column' => DdlOperation::AlterColumn,
            'add_index' => DdlOperation::AddIndex,
            'drop_index' => DdlOperation::DropIndex,
            'add_foreign_key' => DdlOperation::AddForeignKey,
            'drop_foreign_key' => DdlOperation::DropForeignKey,
            default => null,
        };
        if (! $tableExists || $ddl === null) {
            $rows = $tableExists ? $this->driver->tableStats($op['table'])->rows : 0;

            return ['online' => true, 'estimatedMs' => $ddl === null ? max(50, intdiv($rows * 20, 1000)) : 50, 'lockLevel' => 'none', 'rows' => $rows];
        }
        $impact = $this->driver->estimateDdlImpact($op['table'], $ddl);

        return ['online' => $impact->online, 'estimatedMs' => $impact->estimatedMs, 'lockLevel' => $impact->lockLevel, 'rows' => $impact->rows];
    }
}
