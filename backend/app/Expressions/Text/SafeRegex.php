<?php

declare(strict_types=1);

namespace App\Expressions\Text;

/**
 * The regex safe subset of expression-language.md §9.3, valid and identical
 * in PCRE2 (`u`, `D` flags) and ECMAScript (`u` flag): literals, `.`, classes
 * with ranges, `\d \w \s` and escaped syntax characters, anchors, capturing
 * and non-capturing groups, alternation, and quantifiers `* + ? {m,n}` with
 * n ≤ 100. No backreferences, lookaround, lazy/possessive quantifiers, flags,
 * or Unicode properties. Both runtimes validate with the same rules before
 * executing, so they accept and reject exactly the same patterns.
 */
final class SafeRegex
{
    public const MAX_LENGTH = 256;

    private const SYNTAX = '^$\\.*+?()[]{}|/';

    /** @return bool true when the pattern is inside the safe subset */
    public static function isSafe(string $pattern): bool
    {
        $chars = Unicode::codePoints($pattern);
        if (count($chars) > self::MAX_LENGTH) {
            return false;
        }
        $n = count($chars);
        $i = 0;
        $depth = 0;
        $canQuantify = false;
        while ($i < $n) {
            $c = $chars[$i];
            if ($c === "\x00") {
                return false;
            }
            switch ($c) {
                case '\\':
                    $next = $chars[$i + 1] ?? null;
                    if ($next === null || ! self::validEscape($next, false)) {
                        return false;
                    }
                    $i += 2;
                    $canQuantify = true;
                    break;
                case '[':
                    $end = self::classEnd($chars, $i);
                    if ($end === null) {
                        return false;
                    }
                    $i = $end + 1;
                    $canQuantify = true;
                    break;
                case '(':
                    if (($chars[$i + 1] ?? null) === '?') {
                        if (($chars[$i + 2] ?? null) !== ':') {
                            return false;
                        }
                        $i += 3;
                    } else {
                        $i++;
                    }
                    $depth++;
                    $canQuantify = false;
                    break;
                case ')':
                    if ($depth === 0) {
                        return false;
                    }
                    $depth--;
                    $i++;
                    $canQuantify = true;
                    break;
                case '*':
                case '+':
                case '?':
                    if (! $canQuantify) {
                        return false;
                    }
                    $i++;
                    if (self::isQuantifierStart($chars, $i)) {
                        return false; // lazy, possessive, or stacked quantifier
                    }
                    $canQuantify = false;
                    break;
                case '{':
                    $end = self::braceQuantifierEnd($chars, $i);
                    if ($end === null || ! $canQuantify) {
                        return false;
                    }
                    $i = $end + 1;
                    if (self::isQuantifierStart($chars, $i)) {
                        return false;
                    }
                    $canQuantify = false;
                    break;
                case '}':
                case ']':
                    return false; // unescaped closer is a syntax error in ECMAScript `u` mode
                case '|':
                    $i++;
                    $canQuantify = false;
                    break;
                case '^':
                case '$':
                    $i++;
                    $canQuantify = false;
                    break;
                default:
                    $i++;
                    $canQuantify = true;
            }
        }

        return $depth === 0;
    }

    /** Full-string search semantics (`preg_match` / `RegExp.test`); null when unsafe. */
    public static function matches(string $subject, string $pattern): ?bool
    {
        if (! self::isSafe($pattern) || str_contains($pattern, "\x01")) {
            return null;
        }
        $result = @preg_match("\x01".self::portable($pattern)."\x01uD", $subject);

        return $result === false ? null : $result === 1;
    }

    /**
     * Rewrites the shorthand classes and `.` to explicit ASCII classes so PCRE
     * (whose `u` modifier enables Unicode properties) and ECMAScript `u` mode
     * agree (expression-language.md §9.3, ADR-0027): `\d` → 0-9, `\w` →
     * A-Za-z0-9_, `\s` → space, tab, LF, CR, FF, VT; `.` → any code point but LF.
     * The pattern must already have passed isSafe().
     */
    public static function portable(string $pattern): string
    {
        $map = ['d' => '0-9', 'w' => 'A-Za-z0-9_', 's' => ' \t\n\r\f\x{0B}'];
        $chars = Unicode::codePoints($pattern);
        $out = '';
        $inClass = false;
        $n = count($chars);
        for ($i = 0; $i < $n; $i++) {
            $c = $chars[$i];
            if ($c === '\\' && $i + 1 < $n) {
                $next = $chars[++$i];
                if (isset($map[$next])) {
                    $out .= $inClass ? $map[$next] : '['.$map[$next].']';
                } else {
                    $out .= '\\'.$next;
                }

                continue;
            }
            if ($inClass) {
                if ($c === ']') {
                    $inClass = false;
                }
                $out .= $c;

                continue;
            }
            if ($c === '[') {
                $inClass = true;
                $out .= $c;
                if (($chars[$i + 1] ?? null) === '^') {
                    $out .= '^';
                    $i++;
                }
                if (($chars[$i + 1] ?? null) === ']') {
                    $out .= ']';
                    $i++;
                }

                continue;
            }
            $out .= $c === '.' ? '[^\n]' : $c;
        }

        return $out;
    }

    private static function validEscape(string $next, bool $inClass): bool
    {
        if (in_array($next, ['d', 'w', 's', 'n', 't', 'r'], true)) {
            return true;
        }
        if ($inClass && $next === '-') {
            return true;
        }

        return str_contains(self::SYNTAX, $next);
    }

    /** @param  list<string>  $chars */
    private static function isQuantifierStart(array $chars, int $i): bool
    {
        $c = $chars[$i] ?? null;

        return $c === '*' || $c === '+' || $c === '?' || ($c === '{' && self::braceQuantifierEnd($chars, $i) !== null);
    }

    /**
     * End index of `{m}`, `{m,}`, or `{m,n}` (n ≤ 100, m ≤ n), else null.
     *
     * @param  list<string>  $chars
     */
    private static function braceQuantifierEnd(array $chars, int $start): ?int
    {
        $text = '';
        for ($i = $start + 1; $i < count($chars) && $chars[$i] !== '}'; $i++) {
            $text .= $chars[$i];
        }
        if (($chars[$i] ?? null) !== '}' || preg_match('/^(\d{1,3})(,(\d{1,3})?)?$/D', $text, $m) !== 1) {
            return null;
        }
        $min = (int) $m[1];
        $max = isset($m[3]) ? (int) $m[3] : (isset($m[2]) ? null : $min);
        if ($min > 100 || ($max !== null && ($max > 100 || $max < $min))) {
            return null;
        }

        return $i;
    }

    /**
     * End index (the `]`) of a class starting at `$start`, else null.
     *
     * @param  list<string>  $chars
     */
    private static function classEnd(array $chars, int $start): ?int
    {
        $i = $start + 1;
        if (($chars[$i] ?? null) === '^') {
            $i++;
        }
        $items = 0;
        while ($i < count($chars)) {
            $c = $chars[$i];
            if ($c === ']') {
                return $items > 0 ? $i : null;
            }
            if ($c === '[') {
                return null;
            }
            if ($c === '\\') {
                $next = $chars[$i + 1] ?? null;
                if ($next === null || ! self::validEscape($next, true)) {
                    return null;
                }
                $single = in_array($next, ['d', 'w', 's'], true) ? null : self::escapedChar($next);
                $i += 2;
            } else {
                $single = $c;
                $i++;
            }
            $items++;
            // Range a-b
            if (($chars[$i] ?? null) === '-' && ($chars[$i + 1] ?? ']') !== ']') {
                if ($single === null) {
                    return null;
                }
                $endChar = $chars[$i + 1];
                if ($endChar === '\\') {
                    $next = $chars[$i + 2] ?? null;
                    if ($next === null || in_array($next, ['d', 'w', 's'], true) || ! self::validEscape($next, true)) {
                        return null;
                    }
                    $endChar = self::escapedChar($next);
                    $i += 3;
                } elseif ($endChar === '[') {
                    return null;
                } else {
                    $i += 2;
                }
                if (mb_ord($single, 'UTF-8') > mb_ord($endChar, 'UTF-8')) {
                    return null;
                }

                continue;
            }
        }

        return null;
    }

    private static function escapedChar(string $next): string
    {
        return match ($next) {
            'n' => "\n",
            't' => "\t",
            'r' => "\r",
            default => $next,
        };
    }
}
