<?php

declare(strict_types=1);

namespace App\Modules\Records\Runtime;

use App\Expressions\Calendars\Civil;
use App\Expressions\Numbers\Decimal;
use App\Expressions\Values\RecordSource;
use App\Expressions\Values\Value;

/**
 * Exposes record values (API shape) to the expression evaluator
 * (expression-language.md §3 field type mapping). References become lazily
 * loaded records so relation paths (`employee.department.name`) resolve hop by
 * hop; repeaters become lists of row records.
 */
final class ValuesRecord implements RecordSource
{
    /** @var array<string, Value> */
    private array $cache = [];

    /**
     * @param  array<string, mixed>  $values
     * @param  array<string, string>  $keys  field key => uuid of the fields this record holds
     */
    public function __construct(
        private readonly FormRuntime $rt,
        private readonly array $values,
        private readonly array $keys,
        private readonly ?string $title = null,
        private readonly ?string $identity = null,
        private readonly ?RecordLoader $loader = null,
        private readonly bool $withRepeaters = true,
    ) {}

    public static function forRecord(FormRuntime $rt, array $values, ?RecordLoader $loader, ?string $uuid = null): self
    {
        return new self($rt, $values, $rt->keys, null, $uuid, $loader);
    }

    public function get(string $key): ?Value
    {
        if (isset($this->cache[$key])) {
            return $this->cache[$key];
        }
        if ($this->withRepeaters && isset($this->rt->repeaterKeys[$key])) {
            $repUuid = $this->rt->repeaterKeys[$key];
            $rowKeys = $this->rt->repeaters[$repUuid]['fields'];
            $rows = [];
            foreach (is_array($this->values[$key] ?? null) ? $this->values[$key] : [] as $row) {
                $rows[] = Value::record(new self($this->rt, is_array($row) ? $row : [], $rowKeys, null, $row['uuid'] ?? null, $this->loader, false));
            }

            return $this->cache[$key] = Value::list($rows);
        }
        $uuid = $this->keys[$key] ?? null;
        if ($uuid === null) {
            return null;
        }

        return $this->cache[$key] = $this->convert($this->rt->fields[$uuid], $this->values[$key] ?? null);
    }

    public function title(): ?string
    {
        return $this->title;
    }

    public function identity(): ?string
    {
        return $this->identity;
    }

    private function convert(array $field, mixed $v): Value
    {
        $type = $this->rt->type($field);
        if ($type === null || ! $type->isStored()) {
            return Value::null();
        }
        $storage = $type->storage;
        if ($v === null) {
            return in_array($storage, ['multi_choice', 'files'], true) || $this->rt->isMultiReference($field) ? Value::list([]) : Value::null();
        }
        $num = static fn (mixed $x): Value => ($d = Decimal::parse((string) $x)) === null ? Value::null() : Value::number($d);
        $ref = function (string $uuid) use ($field): Value {
            return $this->loader === null ? Value::null() : $this->loader->reference($this->rt, $field, $uuid);
        };

        return match (true) {
            $this->rt->isMultiReference($field) => Value::list(array_map($ref, (array) $v)),
            in_array($storage, ['lookup', 'user', 'role', 'department'], true), $storage === 'choice' && $this->rt->targetTable($field) !== null => $ref((string) $v),
            $storage === 'multi_choice' => Value::list(array_map(static fn ($x) => Value::text((string) $x), (array) $v)),
            $storage === 'files' => Value::list(array_map(static fn ($x) => Value::text((string) $x), (array) $v)),
            $storage === 'file' => Value::list([Value::text((string) $v)]),
            in_array($storage, ['decimal', 'number', 'int'], true) => $num($v),
            $storage === 'currency' => $num(is_array($v) ? ($v['amount'] ?? null) : $v),
            $storage === 'duration' => ($d = Decimal::parse((string) $v)) === null ? Value::null() : Value::duration($d),
            in_array($storage, ['bool', 'consent'], true) => Value::bool((bool) $v),
            $storage === 'date' => ($d = Civil::parseDate((string) $v)) === null ? Value::null() : Value::date($d),
            $storage === 'datetime' => ($d = Civil::parseDatetime((string) $v)) === null ? Value::null() : Value::datetime($d),
            $storage === 'time' => ($d = Civil::parseTime((string) $v)) === null ? Value::null() : Value::time($d),
            in_array($storage, ['range_date', 'range_datetime', 'range_time'], true) => Value::list([
                $this->scalarFor($storage, $v['from'] ?? null), $this->scalarFor($storage, $v['to'] ?? null),
            ]),
            $storage === 'map' => Value::text(($v['lat'] ?? '').','.($v['lng'] ?? '')),
            $storage === 'phone' => Value::text((string) ($v['number'] ?? '')),
            $storage === 'json' => Value::text((string) json_encode($v, JSON_UNESCAPED_UNICODE)),
            $storage === 'formula' => $this->formulaValue($field, $v),
            default => Value::text((string) $v),
        };
    }

    private function scalarFor(string $storage, mixed $v): Value
    {
        if ($v === null) {
            return Value::null();
        }

        return match ($storage) {
            'range_date' => ($d = Civil::parseDate((string) $v)) === null ? Value::null() : Value::date($d),
            'range_datetime' => ($d = Civil::parseDatetime((string) $v)) === null ? Value::null() : Value::datetime($d),
            default => ($d = Civil::parseTime((string) $v)) === null ? Value::null() : Value::time($d),
        };
    }

    private function formulaValue(array $field, mixed $v): Value
    {
        return match ($field['storage']['dbType'] ?? 'decimal') {
            'string', 'text' => Value::text((string) $v),
            'date' => ($d = Civil::parseDate((string) $v)) === null ? Value::null() : Value::date($d),
            'datetime' => ($d = Civil::parseDatetime((string) $v)) === null ? Value::null() : Value::datetime($d),
            'time' => ($d = Civil::parseTime((string) $v)) === null ? Value::null() : Value::time($d),
            'bool' => Value::bool((bool) $v),
            default => ($d = Decimal::parse((string) $v)) === null ? Value::null() : Value::number($d),
        };
    }
}
