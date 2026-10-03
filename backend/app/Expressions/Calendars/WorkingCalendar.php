<?php

declare(strict_types=1);

namespace App\Expressions\Calendars;

/**
 * The business calendar in an evaluation context (expression-language.md §9.4:
 * form → department → default calendar): working weekdays, explicit holiday
 * dates, and yearly recurring holidays in the Gregorian or Hijri calendar.
 */
final class WorkingCalendar
{
    /** @var array<int, true> */
    private array $workingDays;

    /** @var array<int, true> */
    private array $holidays;

    /**
     * @param  list<int>  $workingDays  weekdays 0 = Sunday … 6 = Saturday
     * @param  list<int>  $holidays  day numbers
     * @param  list<array{0: int, 1: int}>  $yearlyGregorian  [month, day]
     * @param  list<array{0: int, 1: int}>  $yearlyHijri  [hijri month, hijri day]
     */
    public function __construct(
        array $workingDays,
        array $holidays = [],
        private readonly array $yearlyGregorian = [],
        private readonly array $yearlyHijri = [],
    ) {
        $this->workingDays = array_fill_keys($workingDays, true);
        $this->holidays = array_fill_keys($holidays, true);
    }

    /** Sunday–Thursday with no holidays: used when no calendar is configured. */
    public static function default(): self
    {
        return new self([0, 1, 2, 3, 4]);
    }

    /**
     * From the corpus/runtime JSON shape: {working_days:[…], holidays:["YYYY-MM-DD"…],
     * yearly_gregorian:["MM-DD"…], yearly_hijri:["MM-DD"…]}.
     *
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        $pairs = static fn (array $list): array => array_map(static function (string $md): array {
            [$m, $d] = array_map('intval', explode('-', $md));

            return [$m, $d];
        }, $list);

        return new self(
            array_map('intval', $data['working_days'] ?? [0, 1, 2, 3, 4]),
            array_values(array_filter(array_map(static fn (string $date): ?int => Civil::parseDate($date), $data['holidays'] ?? []), static fn (?int $d): bool => $d !== null)),
            $pairs($data['yearly_gregorian'] ?? []),
            $pairs($data['yearly_hijri'] ?? []),
        );
    }

    public function isWorkingDay(int $days): bool
    {
        if (! isset($this->workingDays[Civil::weekday($days)]) || isset($this->holidays[$days])) {
            return false;
        }
        if ($this->yearlyGregorian !== []) {
            [, $m, $d] = Civil::civilFromDays($days);
            foreach ($this->yearlyGregorian as [$hm, $hd]) {
                if ($hm === $m && $hd === $d) {
                    return false;
                }
            }
        }
        if ($this->yearlyHijri !== []) {
            $hijri = UmmAlQura::fromDays($days);
            if ($hijri !== null) {
                foreach ($this->yearlyHijri as [$hm, $hd]) {
                    if ($hm === $hijri[1] && $hd === $hijri[2]) {
                        return false;
                    }
                }
            }
        }

        return true;
    }

    public function hasWorkingDays(): bool
    {
        return $this->workingDays !== [];
    }
}
