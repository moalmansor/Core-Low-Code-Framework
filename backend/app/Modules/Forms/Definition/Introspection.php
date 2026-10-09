<?php

declare(strict_types=1);

namespace App\Modules\Forms\Definition;

/**
 * Maps introspected native column types of either engine to the logical
 * types of architecture §9.2 (used for bound tables and the schema explorer).
 */
final class Introspection
{
    public static function logicalType(string $native): string
    {
        $t = strtolower($native);

        return match (true) {
            str_starts_with($t, 'bigint') => 'bigint',
            str_starts_with($t, 'int'), str_starts_with($t, 'mediumint') => 'int',
            str_starts_with($t, 'smallint') => 'smallint',
            str_starts_with($t, 'tinyint(1)'), $t === 'bit', str_starts_with($t, 'bool') => 'bool',
            str_starts_with($t, 'tinyint') => 'smallint',
            str_starts_with($t, 'decimal'), str_starts_with($t, 'numeric'), str_starts_with($t, 'money'), str_starts_with($t, 'float'), str_starts_with($t, 'double'), str_starts_with($t, 'real') => 'decimal',
            str_starts_with($t, 'datetime'), str_starts_with($t, 'timestamp'), str_starts_with($t, 'datetimeoffset'), str_starts_with($t, 'smalldatetime') => 'datetime',
            $t === 'date' => 'date',
            str_starts_with($t, 'time') => 'time',
            str_starts_with($t, 'json') => 'json',
            str_starts_with($t, 'uniqueidentifier'), str_starts_with($t, 'uuid') => 'uuid',
            str_contains($t, 'text'), str_contains($t, '(max)') => 'text',
            default => 'string',
        };
    }
}
