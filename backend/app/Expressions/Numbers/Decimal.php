<?php

declare(strict_types=1);

namespace App\Expressions\Numbers;

/**
 * Exact decimal number of the expression language (expression-language.md §3,
 * §4.1): value = sign × magnitude / 10^scale. Results are limited to 34
 * significant digits: more than 34 integer digits is an overflow, extra
 * fractional digits are rounded half away from zero. Division rounds to 16
 * fractional digits. The TypeScript runtime implements the same algorithm on
 * BigInt, so both return identical canonical strings.
 */
final class Decimal
{
    public const MAX_DIGITS = 34;

    public const DIV_SCALE = 16;

    private function __construct(
        public readonly int $sign,      // -1, 0, 1
        public readonly string $magnitude, // BigNat digits
        public readonly int $scale,     // ≥ 0
    ) {}

    public static function zero(): self
    {
        return new self(0, '0', 0);
    }

    public static function of(int|string $value): self
    {
        $parsed = self::parse((string) $value);
        if ($parsed === null) {
            throw new \InvalidArgumentException("Not a decimal: {$value}");
        }

        return $parsed;
    }

    /** Parses `-?digits(.digits)?`; returns null for anything else. */
    public static function parse(string $text): ?self
    {
        if (preg_match('/^(-?)(\d+)(?:\.(\d+))?$/D', $text, $m) !== 1) {
            return null;
        }
        $fraction = $m[3] ?? '';

        return self::make($m[1] === '-' ? -1 : 1, $m[2].$fraction, strlen($fraction));
    }

    private static function make(int $sign, string $digits, int $scale): self
    {
        $magnitude = BigNat::normalize($digits);
        if ($magnitude === '0') {
            return new self(0, '0', 0);
        }
        // Drop trailing fractional zeros (canonical form).
        while ($scale > 0 && str_ends_with($magnitude, '0')) {
            $magnitude = substr($magnitude, 0, -1);
            $scale--;
        }

        return new self($sign, $magnitude, $scale);
    }

    /** Canonical text: no exponent, no trailing fractional zeros, `0` for zero. */
    public function toString(): string
    {
        if ($this->sign === 0) {
            return '0';
        }
        $digits = $this->magnitude;
        if ($this->scale > 0) {
            $digits = str_pad($digits, $this->scale + 1, '0', STR_PAD_LEFT);
            $digits = substr($digits, 0, -$this->scale).'.'.substr($digits, -$this->scale);
        }

        return ($this->sign < 0 ? '-' : '').$digits;
    }

    public function __toString(): string
    {
        return $this->toString();
    }

    /** Significant digits of the canonical form (leading zeros excluded). */
    public function significantDigits(): int
    {
        return $this->sign === 0 ? 1 : strlen($this->magnitude);
    }

    public function integerDigits(): int
    {
        return max(0, strlen($this->magnitude) - $this->scale);
    }

    public function isZero(): bool
    {
        return $this->sign === 0;
    }

    public function isNegative(): bool
    {
        return $this->sign < 0;
    }

    public function isInteger(): bool
    {
        return $this->scale === 0;
    }

    /** The value as a PHP int when it is an integer of at most 15 digits. */
    public function toSmallInt(): ?int
    {
        if ($this->scale !== 0 || strlen($this->magnitude) > 15) {
            return null;
        }

        return $this->sign * (int) $this->magnitude;
    }

    public function negate(): self
    {
        return new self(-$this->sign, $this->magnitude, $this->scale);
    }

    public function abs(): self
    {
        return new self(abs($this->sign), $this->magnitude, $this->scale);
    }

    public function compare(self $other): int
    {
        if ($this->sign !== $other->sign) {
            return $this->sign <=> $other->sign;
        }
        if ($this->sign === 0) {
            return 0;
        }
        [$a, $b] = self::aligned($this, $other);
        $cmp = BigNat::compare($a, $b);

        return $this->sign > 0 ? $cmp : -$cmp;
    }

    public function equals(self $other): bool
    {
        return $this->compare($other) === 0;
    }

    /** @return self|string a decimal or the diagnostic code OVERFLOW */
    public function add(self $other): self|string
    {
        return self::limit(self::exactAdd($this, $other));
    }

    public function sub(self $other): self|string
    {
        return self::limit(self::exactAdd($this, $other->negate()));
    }

    public function mul(self $other): self|string
    {
        if ($this->sign === 0 || $other->sign === 0) {
            return self::zero();
        }

        return self::limit(self::make($this->sign * $other->sign, BigNat::mul($this->magnitude, $other->magnitude), $this->scale + $other->scale));
    }

    /** @return self|string DIV_ZERO, OVERFLOW, or the quotient rounded to 16 fractional digits */
    public function div(self $other, int $scale = self::DIV_SCALE): self|string
    {
        if ($other->sign === 0) {
            return 'DIV_ZERO';
        }
        if ($this->sign === 0) {
            return self::zero();
        }
        // a/b = (ma·10^sb) / (mb·10^sa)
        $numerator = $this->magnitude.str_repeat('0', $other->scale + $scale);
        $denominator = $other->magnitude.str_repeat('0', $this->scale);
        [$q, $r] = BigNat::divmod($numerator, $denominator);
        if (BigNat::compare(BigNat::mul($r, '2'), $denominator) >= 0) {
            $q = BigNat::add($q, '1');
        }

        return self::limit(self::make($this->sign * $other->sign, $q, $scale));
    }

    /** Remainder with the sign of the dividend. */
    public function mod(self $other): self|string
    {
        if ($other->sign === 0) {
            return 'DIV_ZERO';
        }
        [$a, $b, $scale] = self::alignedWithScale($this, $other);
        [, $r] = BigNat::divmod($a, $b);

        return self::limit(self::make($this->sign, $r, $scale));
    }

    /** Integer power, exact; `$n` 0…64. */
    public function pow(int $n): self|string
    {
        $result = self::of(1);
        for ($i = 0; $i < $n; $i++) {
            $result = self::make($result->sign * $this->sign, BigNat::mul($result->magnitude, $this->magnitude), $result->scale + $this->scale);
            if ($result->integerDigits() > self::MAX_DIGITS) {
                return 'OVERFLOW';
            }
        }

        return self::limit($result);
    }

    /** Square root rounded to 16 fractional digits; caller rejects negatives. */
    public function sqrt(): self|string
    {
        if ($this->sign === 0) {
            return self::zero();
        }
        // floor(x · 10^34), whose integer square root is floor(√x · 10^17).
        $shift = 2 * (self::DIV_SCALE + 1) - $this->scale;
        $n = $shift >= 0
            ? $this->magnitude.str_repeat('0', $shift)
            : BigNat::divmod($this->magnitude, BigNat::pow10(-$shift))[0];
        [$q, $lastDigit] = BigNat::divmod(BigNat::isqrt($n), '10');
        if ((int) $lastDigit >= 5) {
            $q = BigNat::add($q, '1');
        }

        return self::limit(self::make(1, $q, self::DIV_SCALE));
    }

    /** Round half away from zero to `$digits` fractional digits (may be negative). */
    public function round(int $digits): self|string
    {
        return self::limit($this->rescale($digits, 'half_away'));
    }

    public function trunc(int $digits = 0): self|string
    {
        return self::limit($this->rescale($digits, 'trunc'));
    }

    public function floor(): self|string
    {
        return self::limit($this->rescale(0, 'floor'));
    }

    public function ceil(): self|string
    {
        return self::limit($this->rescale(0, 'ceil'));
    }

    /** @param  'half_away'|'trunc'|'floor'|'ceil'  $mode */
    private function rescale(int $digits, string $mode): self
    {
        if ($this->sign === 0 || $digits >= $this->scale) {
            return $this;
        }
        $drop = $this->scale - $digits;
        [$q, $r] = BigNat::divmod($this->magnitude, BigNat::pow10($drop));
        $up = match ($mode) {
            'half_away' => BigNat::compare(BigNat::mul($r, '2'), BigNat::pow10($drop)) >= 0,
            'trunc' => false,
            'floor' => $this->sign < 0 && $r !== '0',
            'ceil' => $this->sign > 0 && $r !== '0',
        };
        if ($up) {
            $q = BigNat::add($q, '1');
        }
        if ($digits < 0) {
            return self::make($this->sign, $q.str_repeat('0', -$digits), 0);
        }

        return self::make($this->sign, $q, $digits);
    }

    /**
     * Applies the 34-significant-digit rule: more than 34 integer digits is an
     * overflow; extra fractional digits are rounded half away from zero.
     */
    public static function limit(self $value): self|string
    {
        if ($value->sign === 0) {
            return $value;
        }
        $integerDigits = $value->integerDigits();
        if ($integerDigits > self::MAX_DIGITS) {
            return 'OVERFLOW';
        }
        $leadingZeros = $integerDigits > 0 ? 0 : $value->scale - strlen($value->magnitude);
        $allowedFraction = $integerDigits > 0 ? self::MAX_DIGITS - $integerDigits : self::MAX_DIGITS + $leadingZeros;
        if ($value->scale > $allowedFraction) {
            $value = $value->rescale($allowedFraction, 'half_away');
            if ($value->integerDigits() > self::MAX_DIGITS) {
                return 'OVERFLOW';
            }
        }

        return $value;
    }

    private static function exactAdd(self $a, self $b): self
    {
        if ($a->sign === 0) {
            return $b;
        }
        if ($b->sign === 0) {
            return $a;
        }
        [$x, $y, $scale] = self::alignedWithScale($a, $b);
        if ($a->sign === $b->sign) {
            return self::make($a->sign, BigNat::add($x, $y), $scale);
        }
        $cmp = BigNat::compare($x, $y);
        if ($cmp === 0) {
            return self::zero();
        }

        return $cmp > 0
            ? self::make($a->sign, BigNat::sub($x, $y), $scale)
            : self::make($b->sign, BigNat::sub($y, $x), $scale);
    }

    /** @return array{0: string, 1: string} */
    private static function aligned(self $a, self $b): array
    {
        [$x, $y] = self::alignedWithScale($a, $b);

        return [$x, $y];
    }

    /** @return array{0: string, 1: string, 2: int} */
    private static function alignedWithScale(self $a, self $b): array
    {
        $scale = max($a->scale, $b->scale);

        return [
            BigNat::normalize($a->magnitude.str_repeat('0', $scale - $a->scale)),
            BigNat::normalize($b->magnitude.str_repeat('0', $scale - $b->scale)),
            $scale,
        ];
    }
}
