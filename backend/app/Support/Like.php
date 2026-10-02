<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Contracts\Database\Query\Builder;

/**
 * "Contains" search that behaves the same on MySQL and SQL Server: the user's
 * term is matched literally (its %, _ and [ are not wildcards), using '!' as
 * the LIKE escape character on both engines.
 */
final class Like
{
    public static function pattern(string $term): string
    {
        return '%'.str_replace(['!', '%', '_', '['], ['!!', '!%', '!_', '!['], $term).'%';
    }

    /**
     * @param  list<string>  $columns  trusted column names (never user input)
     */
    public static function any(Builder $query, array $columns, string $term): void
    {
        $pattern = self::pattern($term);
        $base = $query instanceof \Illuminate\Database\Eloquent\Builder ? $query->getQuery() : $query;
        $grammar = $base->getGrammar();
        $query->where(static function ($w) use ($columns, $pattern, $grammar): void {
            foreach ($columns as $column) {
                $w->orWhereRaw($grammar->wrap($column)." like ? escape '!'", [$pattern]);
            }
        });
    }
}
