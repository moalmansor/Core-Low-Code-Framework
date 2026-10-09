<?php

declare(strict_types=1);

namespace App\Expressions\Calendars;

/**
 * Proleptic Gregorian calendar arithmetic on day numbers (days since
 * 1970-01-01), using Howard Hinnant's civil-from-days algorithms. Both
 * runtimes use the same integer algorithms, never the platform date library,
 * so results are identical over 0001-01-01 … 9999-12-31.
 */
final class Civil
{
    public const MIN_DAY = -719162;   // 0001-01-01

    public const MAX_DAY = 2932896;   // 9999-12-31

    public static function daysFromCivil(int $y, int $m, int $d): int
    {
        $y -= $m <= 2 ? 1 : 0;
        $era = intdiv($y >= 0 ? $y : $y - 399, 400);
        $yoe = $y - $era * 400;
        $doy = intdiv(153 * ($m + ($m > 2 ? -3 : 9)) + 2, 5) + $d - 1;
        $doe = $yoe * 365 + intdiv($yoe, 4) - intdiv($yoe, 100) + $doy;

        return $era * 146097 + $doe - 719468;
    }

    /** @return array{0: int, 1: int, 2: int} [year, month, day] */
    public static function civilFromDays(int $z): array
    {
        $z += 719468;
        $era = intdiv($z >= 0 ? $z : $z - 146096, 146097);
        $doe = $z - $era * 146097;
        $yoe = intdiv($doe - intdiv($doe, 1460) + intdiv($doe, 36524) - intdiv($doe, 146096), 365);
        $y = $yoe + $era * 400;
        $doy = $doe - (365 * $yoe + intdiv($yoe, 4) - intdiv($yoe, 100));
        $mp = intdiv(5 * $doy + 2, 153);
        $d = $doy - intdiv(153 * $mp + 2, 5) + 1;
        $m = $mp + ($mp < 10 ? 3 : -9);

        return [$y + ($m <= 2 ? 1 : 0), $m, $d];
    }

    public static function isLeap(int $y): bool
    {
        return ($y % 4 === 0 && $y % 100 !== 0) || $y % 400 === 0;
    }

    public static function daysInMonth(int $y, int $m): int
    {
        return match ($m) {
            2 => self::isLeap($y) ? 29 : 28,
            4, 6, 9, 11 => 30,
            default => 31,
        };
    }

    public static function isValid(int $y, int $m, int $d): bool
    {
        return $y >= 1 && $y <= 9999 && $m >= 1 && $m <= 12 && $d >= 1 && $d <= self::daysInMonth($y, $m);
    }

    public static function inRange(int $days): bool
    {
        return $days >= self::MIN_DAY && $days <= self::MAX_DAY;
    }

    /** 0 = Sunday … 6 = Saturday. */
    public static function weekday(int $days): int
    {
        return (($days + 4) % 7 + 7) % 7;
    }

    public static function formatDate(int $days): string
    {
        [$y, $m, $d] = self::civilFromDays($days);

        return sprintf('%04d-%02d-%02d', $y, $m, $d);
    }

    /** Parses `YYYY-MM-DD`; null when malformed or invalid. */
    public static function parseDate(string $text): ?int
    {
        if (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/D', $text, $m) !== 1) {
            return null;
        }
        [$y, $mo, $d] = [(int) $m[1], (int) $m[2], (int) $m[3]];

        return self::isValid($y, $mo, $d) ? self::daysFromCivil($y, $mo, $d) : null;
    }

    public static function formatDatetime(int $seconds): string
    {
        $days = intdiv($seconds - (($seconds % 86400 + 86400) % 86400), 86400);
        $rest = $seconds - $days * 86400;

        return self::formatDate($days).'T'.self::formatTime($rest).'Z';
    }

    /** Parses `YYYY-MM-DDTHH:MM:SSZ`. */
    public static function parseDatetime(string $text): ?int
    {
        if (preg_match('/^(\d{4}-\d{2}-\d{2})T(\d{2}:\d{2}:\d{2})Z$/D', $text, $m) !== 1) {
            return null;
        }
        $days = self::parseDate($m[1]);
        $time = self::parseTime($m[2]);

        return $days === null || $time === null ? null : $days * 86400 + $time;
    }

    public static function formatTime(int $seconds): string
    {
        return sprintf('%02d:%02d:%02d', intdiv($seconds, 3600), intdiv($seconds % 3600, 60), $seconds % 60);
    }

    public static function parseTime(string $text): ?int
    {
        if (preg_match('/^(\d{2}):(\d{2}):(\d{2})$/D', $text, $m) !== 1) {
            return null;
        }
        [$h, $i, $s] = [(int) $m[1], (int) $m[2], (int) $m[3]];

        return $h < 24 && $i < 60 && $s < 60 ? $h * 3600 + $i * 60 + $s : null;
    }
}
