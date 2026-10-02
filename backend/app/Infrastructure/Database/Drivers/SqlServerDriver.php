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

final class SqlServerDriver extends AbstractDriver
{
    private ?bool $enterprise = null;

    public function name(): string
    {
        return 'sqlsrv';
    }

    public function nativeType(ColumnSpec $column): string
    {
        $bin = 'COLLATE Latin1_General_100_BIN2';

        return match ($column->type) {
            LogicalType::Id => 'BIGINT IDENTITY(1,1)',
            LogicalType::BigInt => 'BIGINT',
            LogicalType::Int => 'INT',
            LogicalType::SmallInt => 'SMALLINT',
            LogicalType::Bool => 'BIT',
            LogicalType::Decimal => sprintf('DECIMAL(%d,%d)', $column->precision ?? 19, $column->scale ?? 4),
            LogicalType::Str => sprintf('NVARCHAR(%d)', $column->length ?? 255),
            LogicalType::Code => sprintf('VARCHAR(%d) %s', $column->length ?? 64, $bin),
            LogicalType::Enum => sprintf('VARCHAR(%d) %s', max(32, ...array_map('strlen', $column->values ?: [''])), $bin),
            LogicalType::Hash => 'CHAR(64) '.$bin,
            LogicalType::Text, LogicalType::LongText, LogicalType::Json => 'NVARCHAR(MAX)',
            LogicalType::Uuid => 'UNIQUEIDENTIFIER',
            LogicalType::DateTime => 'DATETIME2(6)',
            LogicalType::Date => 'DATE',
            LogicalType::Time => 'TIME(0)',
        };
    }

    public function dropIndex(string $table, string $name): array
    {
        $this->assertIdentifier($table);
        $this->assertIdentifier($name);

        return [sprintf('drop index %s on %s', $this->wrap($name), $this->wrap($table))];
    }

    public function supportsOnlineDdl(DdlOperation $operation): OnlineDdlSupport
    {
        $enterprise = $this->isEnterprise();

        return match ($operation) {
            DdlOperation::CreateTable => new OnlineDdlSupport(true, 'none', 'New table; no existing rows are locked.'),
            DdlOperation::AddColumn => new OnlineDdlSupport(true, 'metadata', 'Nullable or defaulted columns are metadata-only.'),
            DdlOperation::RenameColumn => new OnlineDdlSupport(true, 'metadata', 'sp_rename is metadata-only.'),
            DdlOperation::AlterColumn => new OnlineDdlSupport(false, 'exclusive', 'Type changes rewrite the table under a schema modification lock.'),
            DdlOperation::AddIndex => $enterprise
                ? new OnlineDdlSupport(true, 'none', 'ONLINE = ON (Enterprise edition).')
                : new OnlineDdlSupport(false, 'shared', 'Offline index build: writes blocked (online builds need Enterprise edition).'),
            DdlOperation::DropIndex, DdlOperation::DropForeignKey => new OnlineDdlSupport(true, 'metadata', 'Metadata operation.'),
            DdlOperation::AddForeignKey => new OnlineDdlSupport(false, 'shared', 'Existing rows are validated under a shared lock.'),
        };
    }

    public function ddlIsTransactional(): bool
    {
        return true;
    }

    public function tableStats(string $table): TableStats
    {
        $this->assertIdentifier($table);
        $row = $this->connection->selectOne(
            'select coalesce(sum(case when index_id in (0, 1) then row_count else 0 end), 0) as row_count, '
            .'coalesce(sum(used_page_count), 0) * 8192 as data_bytes '
            .'from sys.dm_db_partition_stats where object_id = object_id(?)',
            [$table],
        );

        return new TableStats((int) ($row->row_count ?? 0), (int) ($row->data_bytes ?? 0));
    }

    public function jsonExtract(string $column, string $path): Expression
    {
        $this->assertIdentifier($column);
        $this->assertJsonPath($path);

        return new Raw(sprintf("json_value(%s, '%s')", $this->wrap($column), $path));
    }

    public function jsonColumnCheck(string $column): ?string
    {
        $this->assertIdentifier($column);

        return sprintf('isjson(%s) = 1', $this->wrap($column));
    }

    public function dateTrunc(string $column, string $unit): Expression
    {
        $this->assertIdentifier($column);
        if (! in_array($unit, ['hour', 'day', 'month', 'year'], true)) {
            throw new InvalidArgumentException("Unsupported unit [{$unit}].");
        }
        // SQL Server 2019 has no DATETRUNC; use the dateadd/datediff idiom.
        $wrapped = $this->wrap($column);

        return new Raw(sprintf('dateadd(%1$s, datediff(%1$s, 0, %2$s), 0)', $unit, $wrapped));
    }

    public function skipLocked(Builder $query): Builder
    {
        return $query->lock('with(rowlock, updlock, readpast)');
    }

    public function namedLock(string $name, int $timeoutSeconds): bool
    {
        $row = $this->connection->selectOne(
            "declare @result int; exec @result = sp_getapplock @Resource = ?, @LockMode = 'Exclusive', @LockOwner = 'Session', @LockTimeout = ?; select @result as result",
            [substr($name, 0, 255), $timeoutSeconds * 1000],
        );

        return (int) ($row->result ?? -1) >= 0;
    }

    public function releaseNamedLock(string $name): void
    {
        $this->connection->statement(
            "exec sp_releaseapplock @Resource = ?, @LockOwner = 'Session'",
            [substr($name, 0, 255)],
        );
    }

    public function createTimePartitions(string $table, string $column, array $bounds): array
    {
        $this->assertIdentifier($table);
        $this->assertIdentifier($column);
        $values = implode(', ', array_map(function (string $bound): string {
            if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $bound) !== 1) {
                throw new InvalidArgumentException("Invalid partition bound [{$bound}].");
            }

            return "'{$bound}'";
        }, $bounds));
        $function = $this->wrap('pf_'.$table.'_monthly');
        $scheme = $this->wrap('ps_'.$table.'_monthly');
        $pk = $this->wrap('pk_'.$table);

        return [
            "create partition function {$function} (datetime2(6)) as range right for values ({$values})",
            "create partition scheme {$scheme} as partition {$function} all to ([primary])",
            sprintf('alter table %s drop constraint %s', $this->wrap($table), $pk),
            sprintf('alter table %s add constraint %s primary key clustered (%s, %s) on %s(%s)', $this->wrap($table), $pk, $this->wrap($column), $this->wrap('id'), $scheme, $this->wrap($column)),
        ];
    }

    public function maxIdentifierLength(): int
    {
        return 60; // engine limit is 128; generated names are capped at 60 for MySQL parity
    }

    public function maxIndexKeyBytes(): int
    {
        return 1700;
    }

    private function isEnterprise(): bool
    {
        if ($this->enterprise === null) {
            $row = $this->connection->selectOne("select cast(serverproperty('EngineEdition') as int) as edition");
            // 3 = Enterprise/Developer/Evaluation
            $this->enterprise = (int) ($row->edition ?? 0) === 3;
        }

        return $this->enterprise;
    }
}
