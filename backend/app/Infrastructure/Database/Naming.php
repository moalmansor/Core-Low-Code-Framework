<?php

declare(strict_types=1);

namespace App\Infrastructure\Database;

/**
 * Identifier naming rules (architecture §9.1): constraint and index names are
 * `{prefix}_{table}_{cols}`; anything longer than 60 characters is cut to 51
 * characters plus `_` and an 8-character hash so both engines accept it.
 */
final class Naming
{
    public const MAX_IDENTIFIER = 60;

    /**
     * @param  list<string>  $columns
     */
    public static function constraint(string $prefix, string $table, array $columns): string
    {
        return self::fit($prefix.'_'.$table.'_'.implode('_', $columns));
    }

    public static function fit(string $name): string
    {
        if (strlen($name) <= self::MAX_IDENTIFIER) {
            return $name;
        }

        return substr($name, 0, 51).'_'.substr(sha1($name), 0, 8);
    }
}
