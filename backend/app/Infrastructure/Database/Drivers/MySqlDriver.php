<?php

declare(strict_types=1);

namespace App\Infrastructure\Database\Drivers;

use App\Infrastructure\Database\Specs\ColumnSpec;
use App\Infrastructure\Database\Specs\DdlOperation;
use App\Infrastructure\Database\Specs\LogicalType;
use App\Infrastructure\Database\Specs\OnlineDdlSupport;
use App\Infrastructure\Database\Specs\TableStats;
use Illuminate\Contracts\Database\Query\Expression;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\Query\Expression as Raw;
use InvalidArgumentException;

final class MySqlDriver extends AbstractDriver
{
    public function name(): string
    {
        return 'mysql';
    }

    public function nativeType(ColumnSpec $column): string
    {
        $ascii = 'CHARACTER SET ascii COLLATE ascii_bin';
        $unsigned = $column->unsigned || $column->name === 'id' || str_ends_with($column->name, '_id');

        return match ($column->type) {
            LogicalType::Id => 'BIGINT UNSIGNED AUTO_INCREMENT',
            LogicalType::BigInt => $unsigned ? 'BIGINT UNSIGNED' : 'BIGINT',
            LogicalType::Int => 'INT',
            LogicalType::SmallInt => 'SMALLINT',
            LogicalType::Bool => 'TINYINT(1)',
            LogicalType::Decimal => sprintf('DECIMAL(%d,%d)', $column->precision ?? 19, $column->scale ?? 4),
            LogicalType::Str => sprintf('VARCHAR(%d)', $column->length ?? 255),
            LogicalType::Code => sprintf('VARCHAR(%d) %s', $column->length ?? 64, $ascii),
            LogicalType::Enum => sprintf('VARCHAR(%d) %s', max(32, ...array_map('strlen', $column->values ?: [''])), $ascii),
            LogicalType::Hash => 'CHAR(64) '.$ascii,
            LogicalType::Text => 'TEXT',
            LogicalType::LongText => 'LONGTEXT',
            LogicalType::Json => 'JSON',
            LogicalType::Uuid => 'CHAR(36) '.$ascii,
            LogicalType::DateTime => 'DATETIME(6)',
            LogicalType::Date => 'DATE',
            LogicalType::Time => 'TIME(0)',
        };
    }

    public function dropIndex(string $table, string $name): array
    {
        $this->assertIdentifier($table);
        $this->assertIdentifier($name);

        return [sprintf('alter table %s drop index %s', $this->wrap($table), $this->wrap($name))];
    }

    public function supportsOnlineDdl(DdlOperation $operation): OnlineDdlSupport
    {
        return match ($operation) {
            DdlOperation::CreateTable => new OnlineDdlSupport(true, 'none', 'New table; no existing rows are locked.'),
            DdlOperation::AddColumn => new OnlineDdlSupport(true, 'metadata', 'ALGORITHM=INSTANT for nullable/defaulted columns.'),
            DdlOperation::RenameColumn => new OnlineDdlSupport(true, 'metadata', 'ALGORITHM=INSTANT (MySQL 8.0.28+).'),
            DdlOperation::AlterColumn => new OnlineDdlSupport(false, 'shared', 'Type changes use ALGORITHM=COPY; writes are blocked during the copy.'),
            DdlOperation::AddIndex, DdlOperation::DropIndex => new OnlineDdlSupport(true, 'none', 'ALGORITHM=INPLACE, LOCK=NONE.'),
            DdlOperation::AddForeignKey => new OnlineDdlSupport(true, 'none', 'ALGORITHM=INPLACE when the supporting index exists.'),
            DdlOperation::DropForeignKey => new OnlineDdlSupport(true, 'metadata', 'ALGORITHM=INPLACE.'),
        };
    }

    public function ddlIsTransactional(): bool
    {
        return false;
    }

    public function tableStats(string $table): TableStats
    {
        $this->assertIdentifier($table);
        $row = $this->connection->selectOne(
            'select table_rows as row_count, data_length as data_bytes from information_schema.tables where table_schema = database() and table_name = ?',
            [$table],
        );

        return new TableStats((int) ($row->row_count ?? 0), (int) ($row->data_bytes ?? 0));
    }

    public function jsonExtract(string $column, string $path): Expression
    {
        $this->assertIdentifier($column);
        $this->assertJsonPath($path);

        return new Raw(sprintf("json_unquote(json_extract(%s, '%s'))", $this->wrap($column), $path));
    }

    public function jsonColumnCheck(string $column): ?string
    {
        return null; // the JSON type validates documents itself
    }

    public function dateTrunc(string $column, string $unit): Expression
    {
        $this->assertIdentifier($column);
        $format = match ($unit) {
            'hour' => '%Y-%m-%d %H:00:00',
            'day' => '%Y-%m-%d 00:00:00',
            'month' => '%Y-%m-01 00:00:00',
            'year' => '%Y-01-01 00:00:00',
            default => throw new InvalidArgumentException("Unsupported unit [{$unit}]."),
        };

        return new Raw(sprintf("cast(date_format(%s, '%s') as datetime)", $this->wrap($column), $format));
    }

    public function skipLocked(Builder $query): Builder
    {
        return $query->lock('for update skip locked');
    }

    public function namedLock(string $name, int $timeoutSeconds): bool
    {
        $row = $this->connection->selectOne('select get_lock(?, ?) as acquired', [substr($name, 0, 64), $timeoutSeconds]);

        return (int) ($row->acquired ?? 0) === 1;
    }

    public function releaseNamedLock(string $name): void
    {
        $this->connection->select('select release_lock(?) as released', [substr($name, 0, 64)]);
    }

    public function createTimePartitions(string $table, string $column, array $bounds): array
    {
        $this->assertIdentifier($table);
        $this->assertIdentifier($column);
        $parts = [];
        foreach ($bounds as $bound) {
            $date = $this->assertDate($bound);
            $parts[] = sprintf("partition p%s values less than ('%s')", str_replace('-', '', $date), $date);
        }
        $parts[] = 'partition pmax values less than (maxvalue)';

        return [sprintf('alter table %s partition by range columns(%s) (%s)', $this->wrap($table), $this->wrap($column), implode(', ', $parts))];
    }

    public function maxIndexKeyBytes(): int
    {
        return 3072;
    }

    private function assertDate(string $date): string
    {
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) !== 1) {
            throw new InvalidArgumentException("Invalid partition bound [{$date}].");
        }

        return $date;
    }
}
