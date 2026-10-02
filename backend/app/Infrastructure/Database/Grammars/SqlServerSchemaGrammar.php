<?php

declare(strict_types=1);

namespace App\Infrastructure\Database\Grammars;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Schema\Grammars\SqlServerGrammar;
use Illuminate\Support\Fluent;

final class SqlServerSchemaGrammar extends SqlServerGrammar
{
    use AddsLogicalTypes;

    protected function asciiType(string $kind, int $length): string
    {
        return sprintf('%s(%d) collate Latin1_General_100_BIN2', $kind, $length);
    }

    /** SQL Server unique indexes treat NULLs as equal, so filter them out. */
    protected function filteredUniqueSql(Blueprint $blueprint, Fluent $command): string
    {
        /** @var list<string> $nullable */
        $nullable = $command->nullable ?? [];
        $where = $nullable === []
            ? ''
            : ' where '.implode(' and ', array_map(fn (string $c): string => $this->wrap($c).' is not null', $nullable));

        return sprintf(
            'create unique index %s on %s (%s)%s',
            $this->wrap($command->index),
            $this->wrapTable($blueprint),
            $this->columnize($command->columns),
            $where,
        );
    }

    /** Name the primary key `pk_{table}` instead of a system-generated name. */
    protected function modifyIncrement(Blueprint $blueprint, Fluent $column)
    {
        if (! $column->change && in_array($column->type, $this->serials, true) && $column->autoIncrement) {
            return $this->hasCommand($blueprint, 'primary')
                ? ' identity'
                : ' identity constraint '.$this->wrap('pk_'.$blueprint->getTable()).' primary key';
        }

        return null;
    }
}
