<?php

declare(strict_types=1);

namespace App\Modules\Schema\Execution;

use DateTimeImmutable;

/**
 * Converts stored values between logical column types for type changes
 * (architecture §11.5). A conversion that would lose information fails, so
 * `validate_data` can list the conflicting records before anything changes.
 */
final class ValueConverter
{
    /**
     * @param  array<string, mixed>  $to  target column spec
     * @return array{0: bool, 1: mixed} [ok, converted]
     */
    public static function convert(mixed $value, array $to): array
    {
        if ($value === null) {
            return [true, null];
        }
        $s = is_string($value) ? trim($value) : $value;

        return match ($to['type']) {
            'string', 'code' => self::text((string) self::scalar($value), (int) ($to['length'] ?? 255)),
            'text', 'longtext' => [true, (string) self::scalar($value)],
            'int', 'smallint', 'bigint' => self::integer($s, $to['type']),
            'decimal' => self::decimal($s, (int) ($to['precision'] ?? 19), (int) ($to['scale'] ?? 4)),
            'bool' => self::boolean($s),
            'date' => self::date($s, 'Y-m-d'),
            'datetime' => self::date($s, 'Y-m-d H:i:s.u'),
            'time' => self::date($s, 'H:i:s'),
            'json' => is_string($value) && json_validate($value) ? [true, $value] : [true, json_encode($value, JSON_UNESCAPED_UNICODE)],
            default => [false, null],
        };
    }

    private static function scalar(mixed $value): mixed
    {
        return is_bool($value) ? ($value ? '1' : '0') : $value;
    }

    /** @return array{0: bool, 1: mixed} */
    private static function text(string $value, int $length): array
    {
        return mb_strlen($value) <= $length ? [true, $value] : [false, null];
    }

    /** @return array{0: bool, 1: mixed} */
    private static function integer(mixed $value, string $type): array
    {
        if (is_bool($value)) {
            return [true, (int) $value];
        }
        $str = (string) $value;
        if (preg_match('/^-?\d+(\.0+)?$/', $str) !== 1) {
            return [false, null];
        }
        $int = explode('.', $str)[0];
        $max = match ($type) {
            'smallint' => '32767',
            'int' => '2147483647',
            default => '9223372036854775807',
        };
        $abs = ltrim($int, '-');

        return strlen($abs) < strlen($max) || (strlen($abs) === strlen($max) && strcmp($abs, $max) <= 0) ? [true, $int] : [false, null];
    }

    /** @return array{0: bool, 1: mixed} */
    private static function decimal(mixed $value, int $precision, int $scale): array
    {
        $str = is_bool($value) ? ($value ? '1' : '0') : (string) $value;
        if (preg_match('/^(-?)(\d+)(?:\.(\d+))?$/', $str, $m) !== 1) {
            return [false, null];
        }
        $int = ltrim($m[2], '0');
        $frac = rtrim($m[3] ?? '', '0');
        if (strlen($int) > $precision - $scale || strlen($frac) > $scale) {
            return [false, null];
        }

        return [true, $m[1].($int === '' ? '0' : $int).($frac === '' ? '' : '.'.$frac)];
    }

    /** @return array{0: bool, 1: mixed} */
    private static function boolean(mixed $value): array
    {
        $v = is_string($value) ? strtolower($value) : $value;

        return match (true) {
            in_array($v, [true, 1, '1', 'true', 'yes'], true) => [true, true],
            in_array($v, [false, 0, '0', 'false', 'no'], true) => [true, false],
            default => [false, null],
        };
    }

    /** @return array{0: bool, 1: mixed} */
    private static function date(mixed $value, string $format): array
    {
        if (! is_string($value) || $value === '') {
            return [false, null];
        }
        foreach (['Y-m-d H:i:s.u', 'Y-m-d H:i:s', 'Y-m-d\TH:i:sP', 'Y-m-d\TH:i:s\Z', 'Y-m-d', 'H:i:s', 'H:i'] as $in) {
            $d = DateTimeImmutable::createFromFormat('!'.$in, $value);
            if ($d !== false && $d->format($in) === $value || ($d !== false && $in === 'Y-m-d\TH:i:s\Z')) {
                if ($format !== 'H:i:s' && in_array($in, ['H:i:s', 'H:i'], true)) {
                    return [false, null];
                }

                return [true, $d->format($format)];
            }
        }

        return [false, null];
    }
}
