<?php

declare(strict_types=1);

namespace App\Expressions\Evaluation;

use App\Expressions\Calendars\Civil;
use App\Expressions\Calendars\WorkingCalendar;
use App\Expressions\Values\RecordSource;
use App\Expressions\Values\Value;

/**
 * Everything an expression may read (expression-language.md §2 reference
 * scopes, §9.4 calendar): the record, its previous values, the acting user,
 * and the evaluation context. Immutable; `withRow` derives the row scope used
 * by row-form aggregates.
 */
final class Context
{
    /**
     * @param  array{id?: int|null, name?: string|null, email?: string|null, roles?: list<string>, department?: string|null, departments?: list<string>, attributes?: array<string, Value>, locale?: string|null}  $user
     * @param  array<string, Value>  $params
     */
    public function __construct(
        public readonly int $today,                 // day number
        public readonly int $now,                   // epoch seconds
        public readonly string $timezone = 'UTC',
        public readonly string $mode = 'edit',
        public readonly string $locale = 'en',
        public readonly ?string $form = null,
        public readonly array $user = [],
        public readonly ?RecordSource $record = null,
        public readonly ?RecordSource $old = null,
        public readonly ?RecordSource $row = null,
        public readonly ?RecordSource $parent = null,
        public readonly array $params = [],
        public readonly ?WorkingCalendar $calendar = null,
    ) {}

    public static function now(string $timezone = 'UTC'): self
    {
        $now = time();
        $local = (new \DateTimeImmutable('@'.$now))->setTimezone(new \DateTimeZone($timezone));

        return new self(
            today: (int) Civil::parseDate($local->format('Y-m-d')),
            now: $now,
            timezone: $timezone,
        );
    }

    /**
     * @param  array<string, mixed>  $changes
     */
    public function with(array $changes): self
    {
        $args = get_object_vars($this);

        return new self(...array_merge($args, $changes));
    }

    public function withRow(RecordSource $row): self
    {
        return $this->with(['row' => $row, 'parent' => $this->record]);
    }

    public function calendar(): WorkingCalendar
    {
        return $this->calendar ?? WorkingCalendar::default();
    }
}
