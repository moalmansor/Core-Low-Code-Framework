<?php

declare(strict_types=1);

namespace App\Expressions\Numbers;

/**
 * Arbitrary-precision natural numbers as decimal digit strings (no leading
 * zeros; zero is "0"). Exact integer arithmetic for the expression language's
 * decimal type, with no dependency on the bcmath or gmp extensions. Operands
 * are bounded by the language (≤ 34 significant digits plus a few rounding
 * digits), so schoolbook algorithms are fast enough.
 */
final class BigNat
{
    private const LIMB = 7;

    private const BASE = 10_000_000;

    public static function normalize(string $digits): string
    {
        $digits = ltrim($digits, '0');

        return $digits === '' ? '0' : $digits;
    }

    public static function compare(string $a, string $b): int
    {
        $la = strlen($a);
        $lb = strlen($b);
        if ($la !== $lb) {
            return $la <=> $lb;
        }

        return strcmp($a, $b) <=> 0;
    }

    public static function isZero(string $a): bool
    {
        return $a === '0';
    }

    public static function add(string $a, string $b): string
    {
        $x = self::toLimbs($a);
        $y = self::toLimbs($b);
        $n = max(count($x), count($y));
        $out = [];
        $carry = 0;
        for ($i = 0; $i < $n; $i++) {
            $sum = ($x[$i] ?? 0) + ($y[$i] ?? 0) + $carry;
            $out[] = $sum % self::BASE;
            $carry = intdiv($sum, self::BASE);
        }
        if ($carry > 0) {
            $out[] = $carry;
        }

        return self::fromLimbs($out);
    }

    /** a − b, requires a ≥ b. */
    public static function sub(string $a, string $b): string
    {
        $x = self::toLimbs($a);
        $y = self::toLimbs($b);
        $out = [];
        $borrow = 0;
        foreach ($x as $i => $limb) {
            $diff = $limb - ($y[$i] ?? 0) - $borrow;
            if ($diff < 0) {
                $diff += self::BASE;
                $borrow = 1;
            } else {
                $borrow = 0;
            }
            $out[] = $diff;
        }

        return self::fromLimbs($out);
    }

    public static function mul(string $a, string $b): string
    {
        if ($a === '0' || $b === '0') {
            return '0';
        }
        $x = self::toLimbs($a);
        $y = self::toLimbs($b);
        $out = array_fill(0, count($x) + count($y), 0);
        foreach ($x as $i => $xi) {
            $carry = 0;
            foreach ($y as $j => $yj) {
                $cur = $out[$i + $j] + $xi * $yj + $carry;
                $out[$i + $j] = $cur % self::BASE;
                $carry = intdiv($cur, self::BASE);
            }
            $k = $i + count($y);
            while ($carry > 0) {
                $cur = $out[$k] + $carry;
                $out[$k] = $cur % self::BASE;
                $carry = intdiv($cur, self::BASE);
                $k++;
            }
        }

        return self::fromLimbs($out);
    }

    /**
     * Integer division with remainder (b > 0).
     *
     * @return array{0: string, 1: string} [quotient, remainder]
     */
    public static function divmod(string $a, string $b): array
    {
        if ($b === '0') {
            throw new \DivisionByZeroError('BigNat division by zero');
        }
        if (self::compare($a, $b) < 0) {
            return ['0', $a];
        }
        // Small divisor: native long division.
        if (strlen($b) <= 15) {
            $d = (int) $b;
            $q = '';
            $r = 0;
            foreach (str_split($a) as $digit) {
                $r = $r * 10 + (int) $digit;
                $q .= (string) intdiv($r, $d);
                $r %= $d;
            }

            return [self::normalize($q), (string) $r];
        }
        // Long division digit by digit; each quotient digit found by at most
        // nine subtractions of the divisor.
        $q = '';
        $r = '0';
        foreach (str_split($a) as $digit) {
            $r = self::normalize($r.$digit);
            $count = 0;
            while (self::compare($r, $b) >= 0) {
                $r = self::sub($r, $b);
                $count++;
            }
            $q .= (string) $count;
        }

        return [self::normalize($q), $r];
    }

    public static function pow10(int $n): string
    {
        return '1'.str_repeat('0', $n);
    }

    /** Floor of the square root. */
    public static function isqrt(string $n): string
    {
        if ($n === '0') {
            return '0';
        }
        // Newton iteration starting from a power of ten above the root.
        $x = self::pow10(intdiv(strlen($n) + 1, 2));
        while (true) {
            [$q] = self::divmod($n, $x);
            [$y] = self::divmod(self::add($x, $q), '2');
            if (self::compare($y, $x) >= 0) {
                return $x;
            }
            $x = $y;
        }
    }

    /** @return list<int> little-endian limbs */
    private static function toLimbs(string $digits): array
    {
        $limbs = [];
        for ($end = strlen($digits); $end > 0; $end -= self::LIMB) {
            $start = max(0, $end - self::LIMB);
            $limbs[] = (int) substr($digits, $start, $end - $start);
        }

        return $limbs;
    }

    /** @param  array<int, int>  $limbs */
    private static function fromLimbs(array $limbs): string
    {
        $out = '';
        $last = count($limbs) - 1;
        while ($last > 0 && $limbs[$last] === 0) {
            $last--;
        }
        for ($i = $last; $i >= 0; $i--) {
            $out .= $i === $last ? (string) $limbs[$i] : str_pad((string) $limbs[$i], self::LIMB, '0', STR_PAD_LEFT);
        }

        return $out === '' ? '0' : $out;
    }
}
