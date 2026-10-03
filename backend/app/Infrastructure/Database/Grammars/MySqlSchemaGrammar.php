<?php

declare(strict_types=1);

namespace App\Infrastructure\Database\Grammars;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Schema\Grammars\MySqlGrammar;
use Illuminate\Support\Fluent;

final class MySqlSchemaGrammar extends MySqlGrammar
{
    use AddsLogicalTypes;

    protected function asciiType(string $kind, int $length): string
    {
        return sprintf('%s(%d) character set ascii collate ascii_bin', $kind, $length);
    }

    /** UUIDs are ASCII; keep them out of the utf8mb4 default charset. */
    protected function typeUuid(Fluent $column): string
    {
        return 'char(36) character set ascii collate ascii_bin';
    }

    /** MySQL unique indexes already allow any number of NULLs. */
    protected function filteredUniqueSql(Blueprint $blueprint, Fluent $command): string
    {
        return sprintf(
            'alter table %s add unique %s(%s)',
            $this->wrapTable($blueprint),
            $this->wrap((string) $command->get('index')),
            $this->columnize((array) $command->get('columns')),
        );
    }
}
