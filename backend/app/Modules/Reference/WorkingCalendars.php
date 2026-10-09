<?php

declare(strict_types=1);

namespace App\Modules\Reference;

use App\Expressions\Calendars\Civil;
use App\Expressions\Calendars\WorkingCalendar;
use App\Modules\Reference\Models\BusinessCalendar;
use App\Modules\Reference\Models\Holiday;
use DateInterval;
use DateTimeImmutable;
use DateTimeZone;

/**
 * Business calendars (specification §4.34, architecture §19.19): working days,
 * working-hour spans, one-off and recurring (Gregorian or Hijri) holidays. Used
 * by expressions (`is_working_day`, `add_working_days`) and by working-time
 * arithmetic (SLA timers and due dates in later phases).
 */
final class WorkingCalendars
{
    /** @var array<int, WorkingCalendar> */
    private array $memo = [];

    public function expressionCalendar(int $calendarId): ?WorkingCalendar
    {
        if (isset($this->memo[$calendarId])) {
            return $this->memo[$calendarId];
        }
        $cal = BusinessCalendar::query()->find($calendarId);
        if ($cal === null) {
            return null;
        }
        $holidays = [];
        $yearlyGregorian = [];
        $yearlyHijri = [];
        foreach (Holiday::query()->where('business_calendar_id', $cal->id)->get() as $h) {
            $from = (int) Civil::parseDate((string) $h->starts_on);
            $to = (int) Civil::parseDate((string) $h->ends_on);
            if ($h->recurrence === 'none') {
                for ($d = $from; $d <= $to && $d - $from < 366; $d++) {
                    $holidays[] = Civil::formatDate($d);
                }
            } elseif ($h->recurrence === 'yearly_gregorian') {
                for ($d = $from; $d <= $to && $d - $from < 31; $d++) {
                    [, $m, $day] = Civil::civilFromDays($d);
                    $yearlyGregorian[] = sprintf('%02d-%02d', $m, $day);
                }
            } elseif ($h->hijri_month !== null && $h->hijri_day !== null) {
                $span = max(1, $to - $from + 1);
                for ($i = 0; $i < min($span, 30); $i++) {
                    $yearlyHijri[] = sprintf('%02d-%02d', $h->hijri_month, min(30, $h->hijri_day + $i));
                }
            }
        }

        return $this->memo[$calendarId] = WorkingCalendar::fromArray([
            'working_days' => $cal->working_days,
            'holidays' => array_values(array_unique($holidays)),
            'yearly_gregorian' => array_values(array_unique($yearlyGregorian)),
            'yearly_hijri' => array_values(array_unique($yearlyHijri)),
        ]);
    }

    /**
     * Adds working minutes to an instant, honouring working days, hour spans
     * and holidays in the calendar's timezone.
     */
    public function addWorkingMinutes(BusinessCalendar $cal, DateTimeImmutable $start, int $minutes): DateTimeImmutable
    {
        $tz = new DateTimeZone($cal->timezone);
        $t = $start->setTimezone($tz);
        $expr = $this->expressionCalendar($cal->id);
        $remaining = $minutes;
        for ($guard = 0; $guard < 3660 && $remaining > 0; $guard++) {
            $day = (int) Civil::parseDate($t->format('Y-m-d'));
            foreach ($this->spans($cal, $expr, $day) as [$from, $to]) {
                $spanStart = $t->setTime(...$from);
                $spanEnd = $t->setTime(...$to);
                $cursor = $t > $spanStart ? $t : $spanStart;
                if ($cursor >= $spanEnd) {
                    continue;
                }
                $available = intdiv($spanEnd->getTimestamp() - $cursor->getTimestamp(), 60);
                if ($available >= $remaining) {
                    return $cursor->add(new DateInterval('PT'.$remaining.'M'))->setTimezone(new DateTimeZone('UTC'));
                }
                $remaining -= $available;
                $t = $spanEnd;
            }
            $t = $t->modify('+1 day')->setTime(0, 0);
        }

        return $t->setTimezone(new DateTimeZone('UTC'));
    }

    /** Working minutes between two instants. */
    public function workingMinutesBetween(BusinessCalendar $cal, DateTimeImmutable $a, DateTimeImmutable $b): int
    {
        if ($b <= $a) {
            return 0;
        }
        $tz = new DateTimeZone($cal->timezone);
        $expr = $this->expressionCalendar($cal->id);
        $t = $a->setTimezone($tz);
        $end = $b->setTimezone($tz);
        $total = 0;
        for ($guard = 0; $guard < 3660 && $t < $end; $guard++) {
            $day = (int) Civil::parseDate($t->format('Y-m-d'));
            foreach ($this->spans($cal, $expr, $day) as [$from, $to]) {
                $s = max($t->setTime(...$from)->getTimestamp(), $t->getTimestamp());
                $e = min($t->setTime(...$to)->getTimestamp(), $end->getTimestamp());
                if ($e > $s) {
                    $total += intdiv($e - $s, 60);
                }
            }
            $t = $t->modify('+1 day')->setTime(0, 0);
        }

        return $total;
    }

    /** @return list<array{0: array{0: int, 1: int}, 1: array{0: int, 1: int}}> */
    private function spans(BusinessCalendar $cal, ?WorkingCalendar $expr, int $day): array
    {
        if ($expr !== null && ! $expr->isWorkingDay($day)) {
            return [];
        }
        $weekday = Civil::weekday($day);
        $out = [];
        foreach ($cal->working_hours as $span) {
            if ((int) $span['day'] !== $weekday) {
                continue;
            }
            [$fh, $fm] = array_map('intval', explode(':', $span['start']));
            [$th, $tm] = array_map('intval', explode(':', $span['end']));
            $out[] = [[$fh, $fm], [$th, $tm]];
        }
        usort($out, static fn ($x, $y) => $x[0] <=> $y[0]);

        return $out;
    }
}
