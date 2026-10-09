<?php

declare(strict_types=1);

namespace App\Expressions\Values;

use App\Expressions\Calendars\Civil;
use App\Expressions\Numbers\Decimal;
use InvalidArgumentException;

/**
 * The typed value encoding of expression-language.md §6/§10:
 * `{"t": "number", "v": "12.5"}`, lists as `{"t":"list","v":[…]}`, repeater
 * rows as `{"t":"rows","v":[{field: <typed>…}…]}` and records as
 * `{"t":"record","v":{field: <typed>…}}`. A record may carry its display
 * title and identity under the reserved keys `@title` and `@id` (field keys
 * never start with `@`).
 */
final class Envelope
{
    /** @return array<string, mixed> */
    public static function encode(Value $value): array
    {
        return match ($value->type) {
            Value::NULL => ['t' => 'null'],
            Value::BOOLEAN => ['t' => 'boolean', 'v' => $value->data],
            Value::NUMBER, Value::DURATION => ['t' => $value->type, 'v' => $value->data->toString()],
            Value::TEXT => ['t' => 'text', 'v' => $value->data],
            Value::DATE => ['t' => 'date', 'v' => Civil::formatDate($value->data)],
            Value::DATETIME => ['t' => 'datetime', 'v' => Civil::formatDatetime($value->data)],
            Value::TIME => ['t' => 'time', 'v' => Civil::formatTime($value->data)],
            Value::LIST => ['t' => 'list', 'v' => array_map(self::encode(...), $value->data)],
            Value::RECORD => ['t' => 'record', 'v' => array_filter([
                '@title' => $value->data->title(),
                '@id' => $value->data->identity(),
            ], static fn ($v) => $v !== null)],
            default => throw new InvalidArgumentException("Unknown value type {$value->type}"),
        };
    }

    /** @param  array<string, mixed>|null  $envelope */
    public static function decode(?array $envelope): Value
    {
        if ($envelope === null) {
            return Value::null();
        }
        $t = $envelope['t'] ?? 'null';
        $v = $envelope['v'] ?? null;

        return match ($t) {
            'null' => Value::null(),
            'boolean' => Value::bool((bool) $v),
            'number' => Value::number(Decimal::parse((string) $v) ?? throw new InvalidArgumentException("Invalid number {$v}")),
            'duration' => Value::duration(Decimal::parse((string) $v) ?? throw new InvalidArgumentException("Invalid duration {$v}")),
            'text' => Value::text((string) $v),
            'date' => Value::date(Civil::parseDate((string) $v) ?? throw new InvalidArgumentException("Invalid date {$v}")),
            'datetime' => Value::datetime(Civil::parseDatetime((string) $v) ?? throw new InvalidArgumentException("Invalid datetime {$v}")),
            'time' => Value::time(Civil::parseTime((string) $v) ?? throw new InvalidArgumentException("Invalid time {$v}")),
            'list' => Value::list(array_map(self::decode(...), (array) $v)),
            'rows' => Value::list(array_map(static fn (array $row): Value => Value::record(self::record($row)), (array) $v)),
            'record' => Value::record(self::record((array) $v)),
            default => throw new InvalidArgumentException("Unknown envelope type {$t}"),
        };
    }

    /** @param  array<string, mixed>  $fields */
    public static function record(array $fields): ArrayRecord
    {
        $values = [];
        $title = isset($fields['@title']) ? (string) $fields['@title'] : null;
        $identity = isset($fields['@id']) ? (string) $fields['@id'] : null;
        unset($fields['@title'], $fields['@id']);
        foreach ($fields as $key => $envelope) {
            $values[(string) $key] = self::decode($envelope);
        }

        return new ArrayRecord($values, $title, $identity);
    }
}
