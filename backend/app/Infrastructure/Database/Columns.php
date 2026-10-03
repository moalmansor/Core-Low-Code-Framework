<?php

declare(strict_types=1);

namespace App\Infrastructure\Database;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Schema\ColumnDefinition;
use Illuminate\Support\Facades\DB;

/**
 * Column helpers that implement the logical types and mixins of architecture
 * §9.2–§9.3 identically on MySQL 8 and SQL Server 2019. Migrations use these
 * instead of raw Blueprint calls so that every table matches the ERD.
 */
final class Columns
{
    /**
     * `@pk @uuid @org @ts @by`. Pass `$orgIndex = false` when another index of the
     * table already leads with organization_id (as listed in the ERD).
     */
    public static function meta(Blueprint $t, bool $orgIndex = true): void
    {
        self::pk($t);
        self::uuid($t);
        self::org($t, $orgIndex);
        self::timestamps($t);
        self::by($t);
    }

    public static function pk(Blueprint $t): void
    {
        $t->id();
    }

    public static function uuid(Blueprint $t): void
    {
        $t->uuid('uuid');
        $t->unique(['uuid'], Naming::constraint('uq', $t->getTable(), ['uuid']));
    }

    public static function org(Blueprint $t, bool $index = true): void
    {
        self::fk($t, 'organization_id', 'organizations', nullable: false, index: $index);
    }

    public static function timestamps(Blueprint $t): void
    {
        self::dt($t, 'created_at');
        self::dt($t, 'updated_at');
    }

    public static function by(Blueprint $t): void
    {
        self::fk($t, 'created_by', 'users');
        self::fk($t, 'updated_by', 'users');
    }

    public static function soft(Blueprint $t, bool $orgIndex = true): void
    {
        self::dt($t, 'deleted_at', nullable: true);
        self::fk($t, 'deleted_by', 'users');
        if ($orgIndex) {
            self::index($t, ['organization_id', 'deleted_at']);
        }
    }

    public static function dt(Blueprint $t, string $name, bool $nullable = false): ColumnDefinition
    {
        return $t->dateTime($name, 6)->nullable($nullable);
    }

    public static function code(Blueprint $t, string $name, int $length, bool $nullable = false): ColumnDefinition
    {
        return $t->addColumn('code', $name, ['length' => $length])->nullable($nullable);
    }

    public static function hash(Blueprint $t, string $name, bool $nullable = false): ColumnDefinition
    {
        return $t->addColumn('hash', $name)->nullable($nullable);
    }

    /**
     * Enumeration stored as ASCII string with a CHECK constraint (ADR-0005).
     *
     * @param  list<string>  $values
     */
    public static function enum(Blueprint $t, string $name, array $values, bool $nullable = false): ColumnDefinition
    {
        $length = max(32, ...array_map('strlen', $values));
        $column = self::code($t, $name, $length, $nullable);
        $list = implode(', ', array_map(static fn (string $v): string => "'".str_replace("'", "''", $v)."'", $values));
        $t->check(Naming::constraint('ck', $t->getTable(), [$name]), sprintf('%s in (%s)', self::quote($name), $list));

        return $column;
    }

    /** JSON column; SQL Server stores NVARCHAR(MAX) guarded by ISJSON. */
    public static function json(Blueprint $t, string $name, bool $nullable = false): ColumnDefinition
    {
        $column = $t->json($name)->nullable($nullable);
        if (self::driver() === 'sqlsrv') {
            $t->check(Naming::constraint('ck', $t->getTable(), [$name, 'json']), sprintf('isjson(%s) = 1', self::quote($name)));
        }

        return $column;
    }

    /**
     * Foreign key column with its supporting index (SQL Server does not create
     * one automatically) and a named constraint.
     */
    public static function fk(
        Blueprint $t,
        string $column,
        string $table,
        string $onDelete = 'no action',
        bool $nullable = true,
        string $references = 'id',
        bool $index = true,
    ): ColumnDefinition {
        $definition = $t->unsignedBigInteger($column)->nullable($nullable);
        if ($index) {
            self::index($t, [$column]);
        }
        self::foreign($t, $column, $table, $onDelete, $references);

        return $definition;
    }

    public static function foreign(Blueprint $t, string $column, string $table, string $onDelete = 'no action', string $references = 'id'): void
    {
        $t->foreign($column, Naming::constraint('fk', $t->getTable(), [$column]))
            ->references($references)->on($table)->onDelete($onDelete);
    }

    /**
     * @param  list<string>  $columns
     */
    public static function index(Blueprint $t, array $columns): void
    {
        $t->index($columns, Naming::constraint('ix', $t->getTable(), $columns));
    }

    /**
     * @param  list<string>  $columns
     * @param  list<string>  $nullable  columns of the key that allow NULL
     */
    public static function unique(Blueprint $t, array $columns, array $nullable = []): void
    {
        $name = Naming::constraint('uq', $t->getTable(), $columns);
        if ($nullable === []) {
            $t->unique($columns, $name);

            return;
        }
        $t->filteredUnique($name, $columns, $nullable);
    }

    public static function driver(): string
    {
        return DB::connection()->getDriverName();
    }

    private static function quote(string $column): string
    {
        return self::driver() === 'sqlsrv' ? '['.$column.']' : '`'.$column.'`';
    }
}
