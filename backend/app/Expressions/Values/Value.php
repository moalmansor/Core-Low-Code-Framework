<?php

declare(strict_types=1);

namespace App\Expressions\Values;

use App\Expressions\Numbers\Decimal;

/**
 * A typed value of the expression language (expression-language.md §3).
 *
 * Payloads: null → null; boolean → bool; number → Decimal; text → NFC string;
 * date → int days since 1970-01-01; datetime → int seconds since the Unix
 * epoch (UTC); time → int seconds since midnight; duration → Decimal whole
 * seconds; list → list<Value>; record → RecordSource.
 */
final class Value
{
    public const NULL = 'null';

    public const BOOLEAN = 'boolean';

    public const NUMBER = 'number';

    public const TEXT = 'text';

    public const DATE = 'date';

    public const DATETIME = 'datetime';

    public const TIME = 'time';

    public const DURATION = 'duration';

    public const LIST = 'list';

    public const RECORD = 'record';

    private static ?self $null = null;

    private function __construct(
        public readonly string $type,
        public readonly mixed $data,
    ) {}

    public static function null(): self
    {
        return self::$null ??= new self(self::NULL, null);
    }

    public static function bool(bool $value): self
    {
        return new self(self::BOOLEAN, $value);
    }

    public static function number(Decimal $value): self
    {
        return new self(self::NUMBER, $value);
    }

    public static function int(int $value): self
    {
        return new self(self::NUMBER, Decimal::of($value));
    }

    public static function text(string $value): self
    {
        return new self(self::TEXT, $value);
    }

    public static function date(int $days): self
    {
        return new self(self::DATE, $days);
    }

    public static function datetime(int $seconds): self
    {
        return new self(self::DATETIME, $seconds);
    }

    public static function time(int $seconds): self
    {
        return new self(self::TIME, $seconds);
    }

    public static function duration(Decimal $seconds): self
    {
        return new self(self::DURATION, $seconds);
    }

    /** @param  list<Value>  $items */
    public static function list(array $items): self
    {
        return new self(self::LIST, array_values($items));
    }

    public static function record(RecordSource $record): self
    {
        return new self(self::RECORD, $record);
    }

    public function isNull(): bool
    {
        return $this->type === self::NULL;
    }

    /** @return list<Value> */
    public function items(): array
    {
        return $this->type === self::LIST ? $this->data : [];
    }
}
