<?php

declare(strict_types=1);

namespace App\Expressions\Parsing;

use App\Expressions\StaticError;
use App\Expressions\Text\Unicode;

/**
 * Tokenizer for the text syntax of expression-language.md §2. Tokens are
 * arrays: {type, value, pos, end}. Types: number, string, date, datetime,
 * time, ident, keyword, scope, op, punct, eof.
 */
final class Lexer
{
    public const KEYWORDS = ['and', 'or', 'not', 'true', 'false', 'null'];

    public const SCOPES = ['user', 'record', 'old', 'row', 'parent', 'context'];

    private const OPERATORS = ['!=', '<=', '>=', '=', '<', '>', '&', '+', '-', '*', '/', '%'];

    /** @var list<string> */
    private array $chars;

    private int $pos = 0;

    public function __construct(string $source)
    {
        $this->chars = Unicode::codePoints($source);
    }

    /** @return list<array{type: string, value: string, pos: int, end: int}> */
    public function tokenize(): array
    {
        $tokens = [];
        $n = count($this->chars);
        while (true) {
            while ($this->pos < $n && in_array($this->chars[$this->pos], [' ', "\t", "\r", "\n"], true)) {
                $this->pos++;
            }
            if ($this->pos >= $n) {
                $tokens[] = ['type' => 'eof', 'value' => '', 'pos' => $this->pos, 'end' => $this->pos];

                return $tokens;
            }
            $start = $this->pos;
            $c = $this->chars[$this->pos];

            if (ctype_digit($c)) {
                $tokens[] = $this->number($start);
            } elseif ($c === '"') {
                $tokens[] = ['type' => 'string', 'value' => $this->string(), 'pos' => $start, 'end' => $this->pos];
            } elseif ($c === '@') {
                $this->pos++;
                $word = $this->word();
                if (! in_array($word, self::SCOPES, true)) {
                    throw new StaticError('SYNTAX', "Unknown scope '@{$word}'", $start);
                }
                $tokens[] = ['type' => 'scope', 'value' => $word, 'pos' => $start, 'end' => $this->pos];
            } elseif (self::isLetter($c)) {
                $word = $this->word();
                $next = $this->chars[$this->pos] ?? null;
                if ($next === '"' && in_array($word, ['d', 'dt', 't'], true)) {
                    $this->pos++;
                    $body = $this->untilQuote();
                    $type = ['d' => 'date', 'dt' => 'datetime', 't' => 'time'][$word];
                    $tokens[] = ['type' => $type, 'value' => $body, 'pos' => $start, 'end' => $this->pos];
                } elseif (in_array($word, self::KEYWORDS, true)) {
                    $tokens[] = ['type' => 'keyword', 'value' => $word, 'pos' => $start, 'end' => $this->pos];
                } else {
                    $tokens[] = ['type' => 'ident', 'value' => $word, 'pos' => $start, 'end' => $this->pos];
                }
            } elseif (in_array($c, ['(', ')', '[', ']', ',', '.'], true)) {
                $this->pos++;
                $tokens[] = ['type' => 'punct', 'value' => $c, 'pos' => $start, 'end' => $this->pos];
            } else {
                $two = $c.($this->chars[$this->pos + 1] ?? '');
                $op = in_array($two, self::OPERATORS, true) ? $two : (in_array($c, self::OPERATORS, true) ? $c : null);
                if ($op === null) {
                    throw new StaticError('SYNTAX', "Unexpected character '{$c}'", $start);
                }
                $this->pos += strlen($op);
                $tokens[] = ['type' => 'op', 'value' => $op, 'pos' => $start, 'end' => $this->pos];
            }
        }
    }

    public static function isLetter(string $c): bool
    {
        return strlen($c) === 1 && ctype_alpha($c);
    }

    private function word(): string
    {
        $word = '';
        while (($c = $this->chars[$this->pos] ?? null) !== null && strlen($c) === 1 && (ctype_alnum($c) || $c === '_')) {
            $word .= $c;
            $this->pos++;
        }

        return $word;
    }

    /** @return array{type: string, value: string, pos: int, end: int} */
    private function number(int $start): array
    {
        $text = '';
        while (($c = $this->chars[$this->pos] ?? null) !== null && ctype_digit($c)) {
            $text .= $c;
            $this->pos++;
        }
        if (($this->chars[$this->pos] ?? null) === '.' && ctype_digit($this->chars[$this->pos + 1] ?? 'x')) {
            $text .= '.';
            $this->pos++;
            while (($c = $this->chars[$this->pos] ?? null) !== null && ctype_digit($c)) {
                $text .= $c;
                $this->pos++;
            }
        }
        if (($c = $this->chars[$this->pos] ?? null) !== null && (self::isLetter($c) || $c === '_')) {
            throw new StaticError('SYNTAX', 'A number cannot be followed by a letter', $this->pos);
        }

        return ['type' => 'number', 'value' => $text, 'pos' => $start, 'end' => $this->pos];
    }

    private function string(): string
    {
        $this->pos++; // opening quote
        $out = '';
        while (true) {
            $c = $this->chars[$this->pos] ?? null;
            if ($c === null) {
                throw new StaticError('SYNTAX', 'Unterminated string', $this->pos);
            }
            $this->pos++;
            if ($c === '"') {
                return Unicode::nfc($out);
            }
            if ($c !== '\\') {
                $out .= $c;

                continue;
            }
            $e = $this->chars[$this->pos] ?? null;
            $this->pos++;
            if ($e === '"' || $e === '\\') {
                $out .= $e;
            } elseif ($e === 'n') {
                $out .= "\n";
            } elseif ($e === 't') {
                $out .= "\t";
            } elseif ($e === 'u') {
                $hex = implode('', array_slice($this->chars, $this->pos, 4));
                if (strlen($hex) !== 4 || ! ctype_xdigit($hex)) {
                    throw new StaticError('SYNTAX', 'Invalid \\u escape', $this->pos);
                }
                $this->pos += 4;
                $cp = hexdec($hex);
                if ($cp >= 0xD800 && $cp <= 0xDFFF) {
                    throw new StaticError('SYNTAX', 'Surrogate code points are not allowed', $this->pos);
                }
                $out .= mb_chr((int) $cp, 'UTF-8');
            } else {
                throw new StaticError('SYNTAX', 'Invalid escape', $this->pos);
            }
        }
    }

    private function untilQuote(): string
    {
        $out = '';
        while (($c = $this->chars[$this->pos] ?? null) !== '"') {
            if ($c === null) {
                throw new StaticError('SYNTAX', 'Unterminated literal', $this->pos);
            }
            $out .= $c;
            $this->pos++;
        }
        $this->pos++;

        return $out;
    }
}
