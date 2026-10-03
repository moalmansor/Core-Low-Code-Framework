<?php

declare(strict_types=1);

namespace App\Expressions\Checking;

use App\Expressions\Evaluation\Ast;
use App\Expressions\Evaluation\Evaluator;
use App\Expressions\Evaluation\Signatures;
use App\Expressions\StaticError;

/**
 * Save-time checker of expression-language.md §3.1: bounds (DEPTH,
 * PATH_DEPTH), function existence and arity, operand and argument types,
 * row-scope validity, and reference existence when a resolver is supplied.
 * Static types are strings: null, boolean, number, text, date, datetime, time,
 * duration, record, any, and `list<T>`.
 *
 * Only definite conflicts are rejected: a value whose static type is `any`
 * (unresolved reference, mixed branches) is re-checked at runtime instead.
 */
final class TypeChecker
{
    private const ORDERABLE = ['number', 'text', 'date', 'datetime', 'time', 'duration'];

    private const USER_KEYS = [
        'id' => 'number', 'name' => 'text', 'email' => 'text', 'department' => 'text',
        'locale' => 'text', 'roles' => 'list<text>', 'departments' => 'list<text>',
    ];

    private const CONTEXT_KEYS = ['mode' => 'text', 'form' => 'text', 'locale' => 'text', 'timezone' => 'text'];

    /** Rows path of the enclosing row-form aggregate, when checking its per-row expression. */
    private ?array $rowScope = null;

    /**
     * @param  (callable(string $scope, list<string> $path, ?list<string> $rowsPath): ?string)|null  $resolver
     *                                                                                                          Returns the static type of a record/old/row/parent reference, or null when it does not exist.
     */
    private function __construct(private readonly mixed $resolver) {}

    /**
     * @param  array<string, mixed>  $ast
     * @param  (callable(string, list<string>, ?list<string>): ?string)|null  $resolver
     * @param  string|null  $expected  required result type (e.g. `boolean` for conditions)
     * @return string the expression's static type
     *
     * @throws StaticError
     */
    public static function check(array $ast, ?callable $resolver = null, ?string $expected = null): string
    {
        [$depth, $nodes] = Ast::measure($ast);
        if ($depth > Evaluator::MAX_DEPTH) {
            throw new StaticError('DEPTH', 'Expression nesting exceeds '.Evaluator::MAX_DEPTH.' levels');
        }
        if ($nodes > Evaluator::MAX_NODES) {
            throw new StaticError('DEPTH', 'Expression exceeds '.Evaluator::MAX_NODES.' nodes');
        }
        $checker = new self($resolver);
        $type = $checker->type($ast, '');
        if ($expected !== null && ! self::assignable($type, $expected)) {
            throw new StaticError('TYPE', "Expected {$expected}, got {$type}");
        }

        return $type;
    }

    public static function assignable(string $from, string $to): bool
    {
        if ($from === $to || $from === 'any' || $from === 'null' || $to === 'any') {
            return true;
        }
        if (str_starts_with($from, 'list<') && str_starts_with($to, 'list<')) {
            return self::assignable(self::itemType($from), self::itemType($to));
        }

        return false;
    }

    /** @param  array<string, mixed>  $node */
    private function type(array $node, string $path): string
    {
        return match ($node['k'] ?? null) {
            'lit' => $this->literal($node, $path),
            'list' => $this->listType($node, $path),
            'ref' => $this->reference($node, $path),
            'un' => $this->unary($node, $path),
            'bin' => $this->binary($node, $path),
            'call' => $this->call($node, $path),
            default => $this->fail('SYNTAX', 'Unknown node kind', $path),
        };
    }

    /** @param  array<string, mixed>  $node */
    private function literal(array $node, string $path): string
    {
        $t = $node['t'] ?? null;
        if (! in_array($t, ['null', 'boolean', 'number', 'text', 'date', 'datetime', 'time'], true)) {
            $this->fail('SYNTAX', 'Unknown literal type', $path);
        }

        return $t;
    }

    /** @param  array<string, mixed>  $node */
    private function listType(array $node, string $path): string
    {
        $types = [];
        foreach ($node['items'] ?? [] as $i => $item) {
            $types[] = $this->type($item, $path.'.items.'.$i);
        }
        $item = $this->common($types, $path, 'List items must share one type');

        return 'list<'.$item.'>';
    }

    /** @param  array<string, mixed>  $node */
    private function reference(array $node, string $path): string
    {
        $segments = array_values($node['path'] ?? []);
        $scope = (string) ($node['scope'] ?? 'record');
        if ($segments === []) {
            $this->fail('SYNTAX', 'Empty reference', $path);
        }
        if (count($segments) - 1 > Evaluator::MAX_HOPS) {
            $this->fail('PATH_DEPTH', 'Relation paths are limited to '.Evaluator::MAX_HOPS.' hops', $path);
        }
        if ($scope === 'user') {
            if ($segments[0] === 'attributes') {
                return count($segments) >= 2 ? 'any' : $this->fail('TYPE', 'User attribute key missing', $path);
            }

            return count($segments) === 1 && isset(self::USER_KEYS[$segments[0]])
                ? self::USER_KEYS[$segments[0]]
                : $this->fail('TYPE', "Unknown user property '".implode('.', $segments)."'", $path);
        }
        if ($scope === 'context') {
            if ($segments[0] === 'param') {
                return count($segments) === 2 ? 'any' : $this->fail('TYPE', 'Parameter key missing', $path);
            }

            return count($segments) === 1 && isset(self::CONTEXT_KEYS[$segments[0]])
                ? self::CONTEXT_KEYS[$segments[0]]
                : $this->fail('TYPE', "Unknown context property '".implode('.', $segments)."'", $path);
        }
        if (! in_array($scope, ['record', 'old', 'row', 'parent'], true)) {
            $this->fail('SYNTAX', "Unknown scope '{$scope}'", $path);
        }
        if (($scope === 'row' || $scope === 'parent') && $this->rowScope === null) {
            $this->fail('TYPE', "@{$scope} is only valid inside a row-form aggregate", $path);
        }
        if ($this->resolver === null) {
            return 'any';
        }
        $type = ($this->resolver)($scope, $segments, $this->rowScope);

        return $type ?? $this->fail('TYPE', "Unknown reference '".implode('.', $segments)."'", $path);
    }

    /** @param  array<string, mixed>  $node */
    private function unary(array $node, string $path): string
    {
        $a = $this->type($node['a'] ?? [], $path.'.a');

        return match ($node['op'] ?? null) {
            'neg' => in_array($a, ['any', 'null', 'number', 'duration'], true) ? ($a === 'null' ? 'number' : $a) : $this->fail('TYPE', "Cannot negate {$a}", $path),
            'not' => in_array($a, ['any', 'null', 'boolean'], true) ? 'boolean' : $this->fail('TYPE', "'not' needs a boolean, got {$a}", $path),
            default => $this->fail('SYNTAX', 'Unknown unary operator', $path),
        };
    }

    /** @param  array<string, mixed>  $node */
    private function binary(array $node, string $path): string
    {
        $op = $node['op'] ?? null;
        $a = $this->type($node['a'] ?? [], $path.'.a');
        $b = $this->type($node['b'] ?? [], $path.'.b');
        $loose = static fn (string $t): bool => $t === 'any' || $t === 'null';

        switch ($op) {
            case 'and':
            case 'or':
                foreach ([$a, $b] as $t) {
                    if (! $loose($t) && $t !== 'boolean') {
                        $this->fail('TYPE', "'{$op}' needs boolean operands, got {$t}", $path);
                    }
                }

                return 'boolean';
            case '&':
                return 'text';
            case '=':
            case '!=':
                return 'boolean';
            case '<':
            case '<=':
            case '>':
            case '>=':
                foreach ([$a, $b] as $t) {
                    if (! $loose($t) && ! in_array($t, self::ORDERABLE, true)) {
                        $this->fail('TYPE', "{$t} values cannot be ordered", $path);
                    }
                }
                if (! $loose($a) && ! $loose($b) && $a !== $b) {
                    $this->fail('TYPE', "Cannot compare {$a} with {$b}", $path);
                }

                return 'boolean';
            case '+':
            case '-':
            case '*':
            case '/':
            case '%':
                return $this->arithmetic($op, $a, $b, $path);
        }

        return $this->fail('SYNTAX', 'Unknown binary operator', $path);
    }

    private function arithmetic(string $op, string $a, string $b, string $path): string
    {
        $table = [
            'number|number' => ['+' => 'number', '-' => 'number', '*' => 'number', '/' => 'number', '%' => 'number'],
            'date|number' => ['+' => 'date', '-' => 'date'],
            'date|date' => ['-' => 'number'],
            'datetime|duration' => ['+' => 'datetime', '-' => 'datetime'],
            'datetime|datetime' => ['-' => 'duration'],
            'duration|duration' => ['+' => 'duration', '-' => 'duration'],
            'duration|number' => ['*' => 'duration', '/' => 'duration'],
        ];
        $loose = static fn (string $t): bool => $t === 'any' || $t === 'null';
        if ($loose($a) || $loose($b)) {
            $candidates = [];
            foreach ($table as $pair => $ops) {
                [$x, $y] = explode('|', $pair);
                if (isset($ops[$op]) && ($loose($a) || $a === $x) && ($loose($b) || $b === $y)) {
                    $candidates[$ops[$op]] = true;
                }
            }
            if ($candidates === []) {
                $this->fail('TYPE', "Operator '{$op}' does not apply to {$a} and {$b}", $path);
            }

            return count($candidates) === 1 ? array_key_first($candidates) : 'any';
        }

        return $table["{$a}|{$b}"][$op] ?? $this->fail('TYPE', "Operator '{$op}' does not apply to {$a} and {$b}", $path);
    }

    /** @param  array<string, mixed>  $node */
    private function call(array $node, string $path): string
    {
        $fn = (string) ($node['fn'] ?? '');
        $args = array_values($node['args'] ?? []);
        if (! Signatures::exists($fn)) {
            $this->fail('UNKNOWN_FUNCTION', "Unknown function '{$fn}'", $path);
        }
        [$min, $max] = Signatures::arity($fn);
        $n = count($args);
        if ($n < $min || ($max !== null && $n > $max)) {
            $this->fail('ARITY', "'{$fn}' takes ".($max === $min ? $min : ($max === null ? "at least {$min}" : "{$min}–{$max}")).' arguments', $path);
        }
        if (Signatures::requiresReference($fn) && ($args[0]['k'] ?? null) !== 'ref') {
            $this->fail('TYPE', "The first argument of '{$fn}' must be a field reference", $path.'.args.0');
        }

        if (Signatures::isRowForm($fn, $n)) {
            return $this->rowForm($fn, $args, $path);
        }

        $types = [];
        foreach ($args as $i => $arg) {
            $types[$i] = $this->type($arg, $path.'.args.'.$i);
            $this->expectArg($fn, $i, $n, $types[$i], $path.'.args.'.$i);
        }

        return $this->resultType($fn, $types, $path);
    }

    /** @param  list<array<string, mixed>>  $args */
    private function rowForm(string $fn, array $args, string $path): string
    {
        if (($args[0]['k'] ?? null) !== 'ref') {
            $this->fail('TYPE', "The first argument of row-form '{$fn}' must reference a repeater or to-many relation", $path.'.args.0');
        }
        if ($this->rowScope !== null) {
            $this->fail('TYPE', 'Row-form aggregates cannot be nested', $path);
        }
        $rows = $this->type($args[0], $path.'.args.0');
        $this->expectArg($fn, 0, count($args), $rows, $path.'.args.0');
        $this->rowScope = array_values($args[0]['path'] ?? []);
        try {
            $exprType = $this->type($args[1], $path.'.args.1');
        } finally {
            $this->rowScope = null;
        }
        $this->expectArg($fn, 1, count($args), $exprType, $path.'.args.1');
        if ($fn === 'join') {
            $sep = $this->type($args[2], $path.'.args.2');
            $this->expectArg($fn, 2, 3, $sep, $path.'.args.2');

            return 'text';
        }

        return match ($fn) {
            'count' => 'number',
            'sum', 'avg' => $this->numericAggregate($exprType, $path),
            default => $exprType,
        };
    }

    private function expectArg(string $fn, int $index, int $count, string $type, string $path): void
    {
        if ($type === 'any' || $type === 'null') {
            return;
        }
        $allowed = Signatures::paramTypes($fn, $index, $count);
        if (in_array('any', $allowed, true) || in_array($type, $allowed, true)) {
            return;
        }
        if (str_starts_with($type, 'list<') && in_array('list', $allowed, true)) {
            return;
        }
        $this->fail('TYPE', 'Argument '.($index + 1)." of '{$fn}' must be ".implode(' or ', $allowed).", got {$type}", $path);
    }

    /** @param  array<int, string>  $types */
    private function resultType(string $fn, array $types, string $path): string
    {
        $returns = Signatures::returns($fn);
        if (str_starts_with($returns, 'arg:')) {
            return $types[(int) substr($returns, 4)] ?? 'any';
        }

        return match ($returns) {
            'branches' => $this->branches($fn, $types, $path),
            'minmax' => count($types) === 1 && str_starts_with($types[0], 'list<')
                ? self::itemType($types[0])
                : $this->common(array_values($types), $path, "Arguments of '{$fn}' must share one type"),
            'aggregate' => $this->listAggregate($fn, $types[0], $path),
            default => $returns,
        };
    }

    /** @param  array<int, string>  $types */
    private function branches(string $fn, array $types, string $path): string
    {
        $results = match ($fn) {
            'if' => [$types[1], $types[2]],
            'coalesce' => array_values($types),
            'switch' => array_values(array_filter($types, static fn (int $i): bool => $i > 0 && ($i % 2 === 0 || $i === count($types) - 1), ARRAY_FILTER_USE_KEY)),
            default => [],
        };

        return $this->common($results, $path, "Results of '{$fn}' must share one type");
    }

    private function listAggregate(string $fn, string $list, string $path): string
    {
        $item = str_starts_with($list, 'list<') ? self::itemType($list) : 'any';

        return in_array($fn, ['sum', 'avg'], true) ? $this->numericAggregate($item, $path) : $item;
    }

    private function numericAggregate(string $item, string $path): string
    {
        if (in_array($item, ['any', 'null'], true)) {
            return 'any';
        }

        return in_array($item, ['number', 'duration'], true) ? $item : $this->fail('TYPE', "Cannot sum {$item} values", $path);
    }

    /** @param  list<string>  $types */
    private function common(array $types, string $path, string $message): string
    {
        $result = null;
        $sawAny = false;
        foreach ($types as $t) {
            if ($t === 'null') {
                continue;
            }
            if ($t === 'any') {
                $sawAny = true;

                continue;
            }
            if ($result !== null && $result !== $t) {
                if (str_starts_with($result, 'list<') && str_starts_with($t, 'list<')) {
                    $result = self::assignable($t, $result) ? $result : (self::assignable($result, $t) ? $t : $this->fail('TYPE', $message, $path));

                    continue;
                }
                $this->fail('TYPE', $message, $path);
            }
            $result = $t;
        }

        return $sawAny ? 'any' : ($result ?? 'null');
    }

    private static function itemType(string $list): string
    {
        return substr($list, 5, -1);
    }

    private function fail(string $code, string $message, string $path): never
    {
        throw new StaticError($code, $message, null, ltrim($path, '.'));
    }
}
