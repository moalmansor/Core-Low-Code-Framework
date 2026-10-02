<?php

declare(strict_types=1);

namespace App\Infrastructure\Database\Grammars;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Fluent;

/**
 * Logical column types and constraint commands shared by both schema grammars
 * (architecture §6, §9.2). Engine-specific SQL is produced by the abstract
 * hooks implemented in each grammar.
 */
trait AddsLogicalTypes
{
    /** Machine keys, tokens, enumerations: case-sensitive ASCII. */
    protected function typeCode(Fluent $column): string
    {
        return $this->asciiType('varchar', (int) $column->length);
    }

    /** SHA-256 hex digests. */
    protected function typeHash(Fluent $column): string
    {
        return $this->asciiType('char', 64);
    }

    /**
     * CHECK constraint: `alter table … add constraint … check (…)`.
     *
     * @return list<string>
     */
    public function compileCheck(Blueprint $blueprint, Fluent $command): array
    {
        return [sprintf(
            'alter table %s add constraint %s check (%s)',
            $this->wrapTable($blueprint),
            $this->wrap($command->index),
            $command->expression,
        )];
    }

    /**
     * Unique index that ignores NULLs on both engines (ADR-0019).
     *
     * @return list<string>
     */
    public function compileFilteredUnique(Blueprint $blueprint, Fluent $command): array
    {
        return [$this->filteredUniqueSql($blueprint, $command)];
    }

    abstract protected function asciiType(string $kind, int $length): string;

    abstract protected function filteredUniqueSql(Blueprint $blueprint, Fluent $command): string;
}
