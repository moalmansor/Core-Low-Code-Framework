<?php

declare(strict_types=1);

namespace App\Support\Json;

/**
 * Order-independent comparison of decoded JSON. Object keys are sorted
 * (lists keep their order), because a document read back from a MySQL JSON
 * column has its keys reordered while SQL Server returns them as written:
 * comparing encoded strings would see a change where there is none.
 */
final class Canonical
{
    public static function of(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }
        if (! array_is_list($value)) {
            ksort($value, SORT_STRING);
        }

        return array_map(self::of(...), $value);
    }

    public static function same(mixed $a, mixed $b): bool
    {
        return json_encode(self::of($a)) === json_encode(self::of($b));
    }
}
