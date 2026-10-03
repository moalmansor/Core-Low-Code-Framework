<?php

declare(strict_types=1);

namespace App\Expressions\Calendars;

/**
 * Umm al-Qura Hijri calendar, table-driven for 1300–1600 AH
 * (resources/calendars/umm-al-qura.json, generated from ICU). The TypeScript
 * runtime reads the same file, so conversions agree exactly.
 */
final class UmmAlQura
{
    /** @var list<array{start: int, months: list<int>}>|null */
    private static ?array $years = null;

    private static int $firstYear = 1300;

    /** @return list<array{start: int, months: list<int>}> */
    private static function years(): array
    {
        if (self::$years !== null) {
            return self::$years;
        }
        $data = json_decode((string) file_get_contents(dirname(__DIR__, 3).'/resources/calendars/umm-al-qura.json'), true, 8, JSON_THROW_ON_ERROR);
        self::$firstYear = (int) $data['firstYear'];
        $years = [];
        foreach ($data['years'] as $year) {
            $years[] = [
                'start' => (int) Civil::parseDate($year['start']),
                'months' => array_map(static fn (string $c): int => $c === '9' ? 29 : 30, str_split($year['months'])),
            ];
        }

        return self::$years = $years;
    }

    /** @return array{0: int, 1: int, 2: int}|null [year, month, day] or null outside the table */
    public static function fromDays(int $days): ?array
    {
        $years = self::years();
        $last = count($years) - 1;
        $end = $years[$last]['start'] + array_sum($years[$last]['months']);
        if ($days < $years[0]['start'] || $days >= $end) {
            return null;
        }
        // Binary search for the year whose start is the last one ≤ days.
        [$lo, $hi] = [0, $last];
        while ($lo < $hi) {
            $mid = intdiv($lo + $hi + 1, 2);
            if ($years[$mid]['start'] <= $days) {
                $lo = $mid;
            } else {
                $hi = $mid - 1;
            }
        }
        $offset = $days - $years[$lo]['start'];
        foreach ($years[$lo]['months'] as $i => $length) {
            if ($offset < $length) {
                return [self::$firstYear + $lo, $i + 1, $offset + 1];
            }
            $offset -= $length;
        }

        return null;
    }

    /**
     * Gregorian day number of a Hijri date.
     *
     * @return int|string the day number, or the diagnostic code (INVALID_DATE outside the table, INVALID_ARG for invalid parts)
     */
    public static function toDays(int $year, int $month, int $day): int|string
    {
        $years = self::years();
        $index = $year - self::$firstYear;
        if ($index < 0 || $index >= count($years)) {
            return 'INVALID_DATE';
        }
        if ($month < 1 || $month > 12 || $day < 1 || $day > $years[$index]['months'][$month - 1]) {
            return 'INVALID_ARG';
        }

        return $years[$index]['start'] + array_sum(array_slice($years[$index]['months'], 0, $month - 1)) + $day - 1;
    }
}
