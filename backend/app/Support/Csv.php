<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Spreadsheet-safe CSV cells (specification §4.15): values starting with
 * `=`, `+`, `-`, `@`, tab, or carriage return are prefixed with an apostrophe
 * so spreadsheet software cannot execute them as formulas.
 */
final class Csv
{
    public static function cell(mixed $value): string
    {
        $string = $value === null ? '' : (is_scalar($value) ? (string) $value : (string) json_encode($value, JSON_UNESCAPED_UNICODE));
        if ($string !== '' && in_array($string[0], ['=', '+', '-', '@', "\t", "\r"], true)) {
            return "'".$string;
        }

        return $string;
    }
}
