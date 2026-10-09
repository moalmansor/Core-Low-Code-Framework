<?php

declare(strict_types=1);

namespace App\Support\Color;

/**
 * WCAG 2.1 contrast arithmetic, the server twin of frontend/src/theme/color.ts
 * (docs/design-system.md). Used to refuse a brand colour that would not read
 * as text on the theme's surfaces; the surfaces below are the theme's
 * --bg-surface and --bg-subtle values in light and dark mode.
 */
final class Contrast
{
    public const AA_TEXT = 4.5;

    /** @var array<string, list<string>> */
    public const SURFACES = ['light' => ['#ffffff', '#f8fafc'], 'dark' => ['#171e26', '#1e2733']];

    public static function isHex(mixed $value): bool
    {
        return is_string($value) && preg_match('/^#[0-9a-fA-F]{6}$/', $value) === 1;
    }

    public static function luminance(string $hex): float
    {
        $c = array_map(static function (int $v): float {
            $s = $v / 255;

            return $s <= 0.04045 ? $s / 12.92 : (($s + 0.055) / 1.055) ** 2.4;
        }, self::rgb($hex));

        return 0.2126 * $c[0] + 0.7152 * $c[1] + 0.0722 * $c[2];
    }

    public static function ratio(string $a, string $b): float
    {
        $x = self::luminance($a);
        $y = self::luminance($b);

        return (max($x, $y) + 0.05) / (min($x, $y) + 0.05);
    }

    /** Lowest ratio of the colour as text on the given mode's surfaces. */
    public static function onSurfaces(string $hex, string $mode): float
    {
        return min(array_map(static fn (string $bg): float => self::ratio($hex, $bg), self::SURFACES[$mode]));
    }

    /** The nearest shade (same hue and saturation) that reaches AA on the mode's surfaces, or null. */
    public static function nearestPassing(string $hex, string $mode): ?string
    {
        [$h, $s, $l] = self::hsl($hex);
        $direction = $mode === 'light' ? -1 : 1;
        for ($step = 0; $step <= 400; $step++) {
            $candidate = $step === 0 ? strtolower($hex) : self::fromHsl($h, $s, max(0.0, min(1.0, $l + $direction * $step * 0.0025)));
            if (self::onSurfaces($candidate, $mode) >= self::AA_TEXT) {
                return $candidate;
            }
        }

        return null;
    }

    /** @return array{0: int, 1: int, 2: int} */
    private static function rgb(string $hex): array
    {
        $n = (int) hexdec(ltrim($hex, '#'));

        return [($n >> 16) & 255, ($n >> 8) & 255, $n & 255];
    }

    /** @return array{0: float, 1: float, 2: float} */
    private static function hsl(string $hex): array
    {
        [$r, $g, $b] = array_map(static fn (int $v): float => $v / 255, self::rgb($hex));
        $max = max($r, $g, $b);
        $min = min($r, $g, $b);
        $l = ($max + $min) / 2;
        if ($max === $min) {
            return [0.0, 0.0, $l];
        }
        $d = $max - $min;
        $s = $l > 0.5 ? $d / (2 - $max - $min) : $d / ($max + $min);
        $h = match ($max) {
            $r => ($g - $b) / $d + ($g < $b ? 6 : 0),
            $g => ($b - $r) / $d + 2,
            default => ($r - $g) / $d + 4,
        };

        return [$h / 6, $s, $l];
    }

    private static function fromHsl(float $h, float $s, float $l): string
    {
        if ($s === 0.0) {
            $v = (int) round($l * 255);

            return sprintf('#%02x%02x%02x', $v, $v, $v);
        }
        $q = $l < 0.5 ? $l * (1 + $s) : $l + $s - $l * $s;
        $p = 2 * $l - $q;
        $hue = static function (float $t) use ($p, $q): float {
            if ($t < 0) {
                $t += 1;
            }
            if ($t > 1) {
                $t -= 1;
            }

            return match (true) {
                $t < 1 / 6 => $p + ($q - $p) * 6 * $t,
                $t < 1 / 2 => $q,
                $t < 2 / 3 => $p + ($q - $p) * (2 / 3 - $t) * 6,
                default => $p,
            };
        };

        return sprintf('#%02x%02x%02x', (int) round(min(255, max(0, $hue($h + 1 / 3) * 255))), (int) round(min(255, max(0, $hue($h) * 255))), (int) round(min(255, max(0, $hue($h - 1 / 3) * 255))));
    }
}
