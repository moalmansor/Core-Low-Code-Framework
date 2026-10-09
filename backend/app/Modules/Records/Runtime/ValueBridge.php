<?php

declare(strict_types=1);

namespace App\Modules\Records\Runtime;

use App\Expressions\Calendars\Civil;
use App\Expressions\Values\Value;

/** Converts an expression result into a field's API value. */
final class ValueBridge
{
    public static function toApi(FormRuntime $rt, array $field, Value $v): mixed
    {
        if ($v->isNull()) {
            return null;
        }
        $storage = $rt->type($field)?->storage;
        $scalar = static fn (Value $x): mixed => match ($x->type) {
            Value::NUMBER, Value::DURATION => $x->data->toString(),
            Value::TEXT => $x->data,
            Value::BOOLEAN => $x->data,
            Value::DATE => Civil::formatDate($x->data),
            Value::DATETIME => Civil::formatDatetime($x->data),
            Value::TIME => Civil::formatTime($x->data),
            Value::RECORD => $x->data->identity(),
            default => null,
        };
        if ($v->type === Value::LIST) {
            $items = array_values(array_filter(array_map($scalar, $v->items()), static fn ($x) => $x !== null));

            return match ($storage) {
                'range_date', 'range_time', 'range_datetime' => ['from' => $items[0] ?? null, 'to' => $items[1] ?? null],
                'multi_choice', 'files' => $items,
                default => $rt->isMultiReference($field) ? $items : ($items[0] ?? null),
            };
        }
        $s = $scalar($v);

        return match ($storage) {
            'bool', 'consent' => is_bool($s) ? $s : (bool) $s,
            'decimal', 'number', 'int', 'currency', 'duration' => is_bool($s) ? ($s ? '1' : '0') : $s,
            'multi_choice', 'files' => $s === null ? [] : [(string) $s],
            'formula' => is_bool($s) && ($field['storage']['dbType'] ?? null) !== 'bool' ? ($s ? '1' : '0') : $s,
            default => is_bool($s) ? ($s ? 'true' : 'false') : $s,
        };
    }
}
