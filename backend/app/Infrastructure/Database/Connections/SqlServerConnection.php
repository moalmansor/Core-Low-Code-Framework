<?php

declare(strict_types=1);

namespace App\Infrastructure\Database\Connections;

use App\Infrastructure\Database\Grammars\SqlServerSchemaGrammar;
use Generator;
use Illuminate\Database\SqlServerConnection as BaseConnection;
use PDOStatement;

/**
 * Result values in the same PHP types as on MySQL: pdo_sqlsrv returns BIGINT
 * as a string and UNIQUEIDENTIFIER in upper case, while MySQL returns integers
 * and the canonical lower-case uuid text. Columns are converted by their
 * declared SQL Server type, never by name, so user text is never touched.
 */
class SqlServerConnection extends BaseConnection
{
    protected function getDefaultSchemaGrammar()
    {
        return new SqlServerSchemaGrammar($this);
    }

    public function select($query, $bindings = [], $useReadPdo = true)
    {
        return $this->run($query, $bindings, function ($query, $bindings) use ($useReadPdo) {
            if ($this->pretending()) {
                return [];
            }
            $statement = $this->prepared($this->getPdoForSelect($useReadPdo)->prepare($query));
            $this->bindValues($statement, $this->prepareBindings($bindings));
            $statement->execute();
            $converters = self::converters($statement);
            $rows = $statement->fetchAll();
            if ($converters !== []) {
                foreach ($rows as $row) {
                    self::convert($row, $converters);
                }
            }

            return $rows;
        });
    }

    public function cursor($query, $bindings = [], $useReadPdo = true): Generator
    {
        /** @var PDOStatement|array<never> $statement */
        $statement = $this->run($query, $bindings, function ($query, $bindings) use ($useReadPdo) {
            if ($this->pretending()) {
                return [];
            }
            $statement = $this->prepared($this->getPdoForSelect($useReadPdo)->prepare($query));
            $this->bindValues($statement, $this->prepareBindings($bindings));
            $statement->execute();

            return $statement;
        });
        if (! $statement instanceof PDOStatement) {
            return;
        }
        $converters = self::converters($statement);
        while ($record = $statement->fetch()) {
            self::convert($record, $converters);
            yield $record;
        }
    }

    /** @return array<string, 'int'|'uuid'> result column => conversion */
    private static function converters(PDOStatement $statement): array
    {
        $out = [];
        for ($i = 0, $n = $statement->columnCount(); $i < $n; $i++) {
            $meta = $statement->getColumnMeta($i);
            if ($meta === false) {
                continue;
            }
            $type = strtolower((string) ($meta['sqlsrv:decl_type'] ?? ''));
            if (str_starts_with($type, 'bigint')) {
                $out[(string) $meta['name']] = 'int';
            } elseif ($type === 'uniqueidentifier') {
                $out[(string) $meta['name']] = 'uuid';
            }
        }

        return $out;
    }

    /** @param  array<string, 'int'|'uuid'>  $converters */
    private static function convert(mixed $row, array $converters): void
    {
        foreach ($converters as $column => $kind) {
            if (is_object($row) && isset($row->{$column}) && is_string($row->{$column})) {
                $row->{$column} = $kind === 'int' ? (int) $row->{$column} : strtolower($row->{$column});
            } elseif (is_array($row) && isset($row[$column]) && is_string($row[$column])) {
                $row[$column] = $kind === 'int' ? (int) $row[$column] : strtolower($row[$column]);
            }
        }
    }
}
