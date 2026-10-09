<?php

declare(strict_types=1);

namespace App\Expressions\Parsing;

use App\Expressions\Calendars\Civil;
use App\Expressions\Numbers\Decimal;
use App\Expressions\StaticError;

/**
 * Recursive-descent parser for expression-language.md §2, producing the JSON
 * AST of §6. This is the reference parser: stored ASTs always come from it.
 * Number literals are canonical; text literals are NFC; `-<number literal>`
 * is folded into a negative literal.
 */
final class Parser
{
    /** @var list<array{type: string, value: string, pos: int, end: int}> */
    private array $tokens;

    private int $i = 0;

    private function __construct(string $source)
    {
        $this->tokens = (new Lexer($source))->tokenize();
    }

    /** @return array<string, mixed> */
    public static function parse(string $source): array
    {
        $parser = new self($source);
        $ast = $parser->expression();
        $token = $parser->peek();
        if ($token['type'] !== 'eof') {
            throw new StaticError('SYNTAX', "Unexpected '{$token['value']}'", $token['pos']);
        }

        return $ast;
    }

    /** @return array<string, mixed> */
    private function expression(): array
    {
        return $this->orExpr();
    }

    /** @return array<string, mixed> */
    private function orExpr(): array
    {
        $left = $this->andExpr();
        while ($this->isKeyword('or')) {
            $this->next();
            $left = ['k' => 'bin', 'op' => 'or', 'a' => $left, 'b' => $this->andExpr()];
        }

        return $left;
    }

    /** @return array<string, mixed> */
    private function andExpr(): array
    {
        $left = $this->notExpr();
        while ($this->isKeyword('and')) {
            $this->next();
            $left = ['k' => 'bin', 'op' => 'and', 'a' => $left, 'b' => $this->notExpr()];
        }

        return $left;
    }

    /** @return array<string, mixed> */
    private function notExpr(): array
    {
        if ($this->isKeyword('not')) {
            $this->next();

            return ['k' => 'un', 'op' => 'not', 'a' => $this->notExpr()];
        }

        return $this->comparison();
    }

    /** @return array<string, mixed> */
    private function comparison(): array
    {
        $left = $this->concat();
        if ($this->isOp('=', '!=', '<', '<=', '>', '>=')) {
            $op = $this->next()['value'];
            $left = ['k' => 'bin', 'op' => $op, 'a' => $left, 'b' => $this->concat()];
            if ($this->isOp('=', '!=', '<', '<=', '>', '>=')) {
                throw new StaticError('SYNTAX', 'Comparison operators cannot be chained', $this->peek()['pos']);
            }
        }

        return $left;
    }

    /** @return array<string, mixed> */
    private function concat(): array
    {
        $left = $this->additive();
        while ($this->isOp('&')) {
            $this->next();
            $left = ['k' => 'bin', 'op' => '&', 'a' => $left, 'b' => $this->additive()];
        }

        return $left;
    }

    /** @return array<string, mixed> */
    private function additive(): array
    {
        $left = $this->multiplicative();
        while ($this->isOp('+', '-')) {
            $op = $this->next()['value'];
            $left = ['k' => 'bin', 'op' => $op, 'a' => $left, 'b' => $this->multiplicative()];
        }

        return $left;
    }

    /** @return array<string, mixed> */
    private function multiplicative(): array
    {
        $left = $this->unary();
        while ($this->isOp('*', '/', '%')) {
            $op = $this->next()['value'];
            $left = ['k' => 'bin', 'op' => $op, 'a' => $left, 'b' => $this->unary()];
        }

        return $left;
    }

    /** @return array<string, mixed> */
    private function unary(): array
    {
        if ($this->isOp('-')) {
            $this->next();
            $operand = $this->unary();
            if ($operand['k'] === 'lit' && $operand['t'] === 'number') {
                return ['k' => 'lit', 't' => 'number', 'v' => Decimal::of($operand['v'])->negate()->toString()];
            }

            return ['k' => 'un', 'op' => 'neg', 'a' => $operand];
        }

        return $this->primary();
    }

    /** @return array<string, mixed> */
    private function primary(): array
    {
        $token = $this->peek();
        switch ($token['type']) {
            case 'number':
                $this->next();
                $value = Decimal::of($token['value']);
                if ($value->significantDigits() > Decimal::MAX_DIGITS) {
                    throw new StaticError('PRECISION', 'Number literals are limited to 34 significant digits', $token['pos']);
                }

                return ['k' => 'lit', 't' => 'number', 'v' => $value->toString()];
            case 'string':
                $this->next();

                return ['k' => 'lit', 't' => 'text', 'v' => $token['value']];
            case 'date':
                $this->next();
                if (Civil::parseDate($token['value']) === null) {
                    throw new StaticError('SYNTAX', 'Invalid date literal', $token['pos']);
                }

                return ['k' => 'lit', 't' => 'date', 'v' => $token['value']];
            case 'datetime':
                $this->next();
                if (Civil::parseDatetime($token['value']) === null) {
                    throw new StaticError('SYNTAX', 'Invalid datetime literal', $token['pos']);
                }

                return ['k' => 'lit', 't' => 'datetime', 'v' => $token['value']];
            case 'time':
                $this->next();
                if (Civil::parseTime($token['value']) === null) {
                    throw new StaticError('SYNTAX', 'Invalid time literal', $token['pos']);
                }

                return ['k' => 'lit', 't' => 'time', 'v' => $token['value']];
            case 'keyword':
                $this->next();

                return match ($token['value']) {
                    'true' => ['k' => 'lit', 't' => 'boolean', 'v' => true],
                    'false' => ['k' => 'lit', 't' => 'boolean', 'v' => false],
                    'null' => ['k' => 'lit', 't' => 'null'],
                    default => throw new StaticError('SYNTAX', "Unexpected keyword '{$token['value']}'", $token['pos']),
                };
            case 'scope':
                $this->next();
                if ($this->isPunct('.')) {
                    $this->next();
                }

                return ['k' => 'ref', 'scope' => $token['value'], 'path' => $this->path()];
            case 'ident':
                $after = $this->tokens[$this->i + 1];
                if ($after['type'] === 'punct' && $after['value'] === '(' && $after['pos'] === $token['end']) {
                    return $this->call();
                }

                return ['k' => 'ref', 'scope' => 'record', 'path' => $this->path()];
            case 'punct':
                if ($token['value'] === '(') {
                    $this->next();
                    $inner = $this->expression();
                    $this->expectPunct(')');

                    return $inner;
                }
                if ($token['value'] === '[') {
                    $this->next();
                    $items = [];
                    if (! $this->isPunct(']')) {
                        do {
                            $items[] = $this->expression();
                        } while ($this->acceptPunct(','));
                    }
                    $this->expectPunct(']');

                    return ['k' => 'list', 'items' => $items];
                }
                break;
        }

        throw new StaticError('SYNTAX', $token['type'] === 'eof' ? 'Unexpected end of expression' : "Unexpected '{$token['value']}'", $token['pos']);
    }

    /** @return array<string, mixed> */
    private function call(): array
    {
        $name = $this->next()['value'];
        $this->expectPunct('(');
        $args = [];
        if (! $this->isPunct(')')) {
            do {
                $args[] = $this->expression();
            } while ($this->acceptPunct(','));
        }
        $this->expectPunct(')');

        return ['k' => 'call', 'fn' => $name, 'args' => $args];
    }

    /** @return list<string> */
    private function path(): array
    {
        $segments = [];
        do {
            $token = $this->peek();
            if ($token['type'] !== 'ident') {
                throw new StaticError('SYNTAX', 'Expected a field or relation key', $token['pos']);
            }
            $segments[] = $this->next()['value'];
        } while ($this->acceptPunct('.'));

        return $segments;
    }

    /** @return array{type: string, value: string, pos: int, end: int} */
    private function peek(): array
    {
        return $this->tokens[$this->i];
    }

    /** @return array{type: string, value: string, pos: int, end: int} */
    private function next(): array
    {
        return $this->tokens[$this->i++];
    }

    /** @phpstan-impure */
    private function isKeyword(string $word): bool
    {
        $t = $this->peek();

        return $t['type'] === 'keyword' && $t['value'] === $word;
    }

    /** @phpstan-impure */
    private function isOp(string ...$ops): bool
    {
        $t = $this->peek();

        return $t['type'] === 'op' && in_array($t['value'], $ops, true);
    }

    /** @phpstan-impure */
    private function isPunct(string $value): bool
    {
        $t = $this->peek();

        return $t['type'] === 'punct' && $t['value'] === $value;
    }

    private function acceptPunct(string $value): bool
    {
        if ($this->isPunct($value)) {
            $this->next();

            return true;
        }

        return false;
    }

    private function expectPunct(string $value): void
    {
        if (! $this->acceptPunct($value)) {
            $t = $this->peek();
            throw new StaticError('SYNTAX', "Expected '{$value}'", $t['pos']);
        }
    }
}
