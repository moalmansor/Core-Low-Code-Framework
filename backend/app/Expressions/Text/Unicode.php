<?php

declare(strict_types=1);

namespace App\Expressions\Text;

use Normalizer;

/**
 * Text semantics of the expression language (§3, §9.3): NFC-normalized
 * strings measured and indexed in Unicode code points, simple case mapping,
 * White_Space trimming, and Arabic normalization.
 */
final class Unicode
{
    public const MAX_LENGTH = 65535;

    /** Unicode White_Space (PropList.txt). */
    private const WHITE_SPACE = "\u{0009}\u{000A}\u{000B}\u{000C}\u{000D}\u{0020}\u{0085}\u{00A0}\u{1680}"
        ."\u{2000}\u{2001}\u{2002}\u{2003}\u{2004}\u{2005}\u{2006}\u{2007}\u{2008}\u{2009}\u{200A}"
        ."\u{2028}\u{2029}\u{202F}\u{205F}\u{3000}";

    public static function nfc(string $text): string
    {
        if (! mb_check_encoding($text, 'UTF-8')) {
            $text = mb_convert_encoding($text, 'UTF-8', 'UTF-8');
        }
        $normalized = Normalizer::normalize($text, Normalizer::FORM_C);

        return $normalized === false ? $text : $normalized;
    }

    public static function length(string $text): int
    {
        return mb_strlen($text, 'UTF-8');
    }

    /** @return list<string> */
    public static function codePoints(string $text): array
    {
        return $text === '' ? [] : mb_str_split($text, 1, 'UTF-8');
    }

    public static function substr(string $text, int $start, ?int $length = null): string
    {
        return mb_substr($text, $start, $length, 'UTF-8');
    }

    public static function upper(string $text): string
    {
        return mb_convert_case($text, MB_CASE_UPPER_SIMPLE, 'UTF-8');
    }

    public static function lower(string $text): string
    {
        return mb_convert_case($text, MB_CASE_LOWER_SIMPLE, 'UTF-8');
    }

    public static function isWhiteSpace(string $codePoint): bool
    {
        return $codePoint !== '' && str_contains(self::WHITE_SPACE, $codePoint);
    }

    public static function trim(string $text): string
    {
        $chars = self::codePoints($text);
        $start = 0;
        $end = count($chars);
        while ($start < $end && self::isWhiteSpace($chars[$start])) {
            $start++;
        }
        while ($end > $start && self::isWhiteSpace($chars[$end - 1])) {
            $end--;
        }

        return implode('', array_slice($chars, $start, $end - $start));
    }

    /** Arabic-Indic (U+0660…) and Extended Arabic-Indic (U+06F0…) digits → ASCII. */
    public static function asciiDigits(string $text): string
    {
        $map = [];
        for ($i = 0; $i < 10; $i++) {
            $map[mb_chr(0x0660 + $i, 'UTF-8')] = (string) $i;
            $map[mb_chr(0x06F0 + $i, 'UTF-8')] = (string) $i;
        }

        return strtr($text, $map);
    }

    /**
     * Removes tashkeel (U+064B–U+065F, U+0670) and tatweel (U+0640); folds
     * أ إ آ → ا, ى → ي, ة → ه; converts Arabic-Indic digits to ASCII.
     */
    public static function normalizeArabic(string $text): string
    {
        $out = '';
        foreach (self::codePoints(self::asciiDigits($text)) as $char) {
            $cp = mb_ord($char, 'UTF-8');
            if (($cp >= 0x064B && $cp <= 0x065F) || $cp === 0x0670 || $cp === 0x0640) {
                continue;
            }
            $out .= match ($cp) {
                0x0623, 0x0625, 0x0622 => "\u{0627}",
                0x0649 => "\u{064A}",
                0x0629 => "\u{0647}",
                default => $char,
            };
        }

        return $out;
    }
}
