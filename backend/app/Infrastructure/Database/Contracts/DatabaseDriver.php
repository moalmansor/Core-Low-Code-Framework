<?php

declare(strict_types=1);

namespace App\Infrastructure\Database\Contracts;

use App\Infrastructure\Database\Specs\BackupResult;
use App\Infrastructure\Database\Specs\ColumnSpec;
use App\Infrastructure\Database\Specs\DdlImpact;
use App\Infrastructure\Database\Specs\DdlOperation;
use App\Infrastructure\Database\Specs\ForeignKeySpec;
use App\Infrastructure\Database\Specs\IndexSpec;
use App\Infrastructure\Database\Specs\OnlineDdlSupport;
use App\Infrastructure\Database\Specs\TableSpec;
use App\Infrastructure\Database\Specs\TableStats;
use Illuminate\Contracts\Database\Query\Expression;
use Illuminate\Database\Query\Builder;

/**
 * Everything that differs between MySQL 8 and SQL Server 2019 lives behind this
 * interface (architecture §6). Nothing outside the driver layer issues
 * engine-specific SQL. DDL methods return statements; callers decide when to run
 * them (migration plans persist them before execution, §12).
 */
interface DatabaseDriver
{
    public function name(): string;

    /** Native column type for a logical type, e.g. `VARCHAR(255)` / `NVARCHAR(255)`. */
    public function nativeType(ColumnSpec $column): string;

    /** @return list<string> */
    public function createTable(TableSpec $table): array;

    /** @return list<string> */
    public function addColumn(string $table, ColumnSpec $column): array;

    /** @return list<string> */
    public function renameTable(string $from, string $to): array;

    /** @return list<string> */
    public function renameColumn(string $table, string $from, string $to): array;

    /** @return list<string> */
    public function alterColumnType(string $table, ColumnSpec $column): array;

    /** @return list<string> */
    public function addIndex(string $table, IndexSpec $index): array;

    /** @return list<string> */
    public function dropIndex(string $table, string $name): array;

    /** @return list<string> */
    public function addForeignKey(string $table, ForeignKeySpec $foreignKey): array;

    /** @return list<string> */
    public function dropForeignKey(string $table, string $name): array;

    public function supportsOnlineDdl(DdlOperation $operation): OnlineDdlSupport;

    public function estimateDdlImpact(string $table, DdlOperation $operation): DdlImpact;

    /** Whether DDL participates in transactions (MySQL: no; SQL Server: yes). */
    public function ddlIsTransactional(): bool;

    /** @return list<string> */
    public function tables(): array;

    /** @return list<array{name: string, type: string, nullable: bool, default: mixed}> */
    public function columns(string $table): array;

    /** @return list<array{name: string, columns: list<string>, unique: bool, primary: bool}> */
    public function indexes(string $table): array;

    /** @return list<array{name: string|null, columns: list<string>, foreign_table: string, foreign_columns: list<string>, on_delete: string|null}> */
    public function foreignKeys(string $table): array;

    public function tableStats(string $table): TableStats;

    /** Scalar value at a JSON path (`$.a.b`). */
    public function jsonExtract(string $column, string $path): Expression;

    /** Predicate: JSON array/object at path contains the scalar value (bound). */
    public function jsonContains(Builder $query, string $column, string $path, string|int|float|bool $value): Builder;

    /** CHECK expression that validates a JSON column, or null when the type validates itself. */
    public function jsonColumnCheck(string $column): ?string;

    /** Case- and accent-insensitive LIKE predicate (collation-aware). */
    /**
     * Case- and accent-insensitive text match: `contains` (default), `starts`
     * (prefix) or `equals` (whole value), with LIKE wildcards in the term escaped.
     */
    public function caseInsensitiveLike(Builder $query, string $column, string $term, string $mode = 'contains'): Builder;

    public function dateTrunc(string $column, string $unit): Expression;

    public function lockForUpdate(Builder $query): Builder;

    /** Row lock that skips rows locked by other transactions (queue claims). */
    public function skipLocked(Builder $query): Builder;

    public function namedLock(string $name, int $timeoutSeconds): bool;

    public function releaseNamedLock(string $name): void;

    /**
     * Monthly range partitioning on a datetime column (used from Phase 6).
     *
     * @param  list<string>  $bounds  ISO dates of partition upper bounds
     * @return list<string>
     */
    public function createTimePartitions(string $table, string $column, array $bounds): array;

    /**
     * Chunked, engine-neutral export of rows (JSON Lines + schema) to private
     * storage; restorable to either engine.
     *
     * @param  list<string>  $tables
     */
    public function logicalBackup(array $tables, string $disk, string $directory): BackupResult;

    public function maxIdentifierLength(): int;

    public function maxIndexKeyBytes(): int;
}
