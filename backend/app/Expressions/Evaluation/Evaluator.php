<?php

declare(strict_types=1);

namespace App\Expressions\Evaluation;

use App\Expressions\Calendars\Civil;
use App\Expressions\Calendars\UmmAlQura;
use App\Expressions\Numbers\Decimal;
use App\Expressions\Text\SafeRegex;
use App\Expressions\Text\Unicode;
use App\Expressions\Values\RecordSource;
use App\Expressions\Values\Value;

/**
 * Evaluates expression ASTs (expression-language.md §4–§9). Total: every AST
 * yields a value plus diagnostics; nothing throws to the caller. Bounded by an
 * evaluation step budget (one step per node visit and per aggregated row),
 * identical in the TypeScript evaluator, so both stop at the same point.
 */
final class Evaluator
{
    public const MAX_STEPS = 100_000;

    public const MAX_DEPTH = 32;

    public const MAX_NODES = 2000;

    public const MAX_HOPS = 4;

    public const MAX_ROWS = 10_000;

    public const MAX_LIST = 10_000;

    private int $steps = 0;

    private int $rows = 0;

    /** @var array<string, array{code: string, node: string}> */
    private array $diagnostics = [];

    private function __construct(private Context $context) {}

    public static function evaluate(array $ast, Context $context): Result
    {
        $evaluator = new self($context);
        [$depth, $nodes] = Ast::measure($ast);
        if ($depth > self::MAX_DEPTH || $nodes > self::MAX_NODES) {
            return new Result(Value::null(), [['code' => 'LIMIT_EXCEEDED', 'node' => '']]);
        }
        try {
            $value = $evaluator->eval($ast, '');
        } catch (BudgetExceeded) {
            return new Result(Value::null(), [['code' => 'LIMIT_EXCEEDED', 'node' => '']]);
        }

        return new Result($value, array_values($evaluator->diagnostics));
    }

    // ── Nodes ────────────────────────────────────────────────────────────

    /** @param  array<string, mixed>  $node */
    private function eval(array $node, string $path): Value
    {
        if (++$this->steps > self::MAX_STEPS) {
            throw new BudgetExceeded;
        }

        return match ($node['k'] ?? null) {
            'lit' => $this->literal($node, $path),
            'list' => $this->listNode($node, $path),
            'ref' => $this->reference($node, $path),
            'un' => $this->unary($node, $path),
            'bin' => $this->binary($node, $path),
            'call' => $this->call($node, $path),
            default => $this->diag('TYPE_MISMATCH', $path),
        };
    }

    /** @param  array<string, mixed>  $node */
    private function literal(array $node, string $path): Value
    {
        $v = $node['v'] ?? null;

        return match ($node['t'] ?? null) {
            'null' => Value::null(),
            'boolean' => is_bool($v) ? Value::bool($v) : $this->diag('TYPE_MISMATCH', $path),
            'number' => is_string($v) && ($d = Decimal::parse($v)) !== null ? Value::number($d) : $this->diag('TYPE_MISMATCH', $path),
            'text' => is_string($v) ? Value::text(Unicode::nfc($v)) : $this->diag('TYPE_MISMATCH', $path),
            'date' => is_string($v) && ($d = Civil::parseDate($v)) !== null ? Value::date($d) : $this->diag('INVALID_DATE', $path),
            'datetime' => is_string($v) && ($d = Civil::parseDatetime($v)) !== null ? Value::datetime($d) : $this->diag('INVALID_DATE', $path),
            'time' => is_string($v) && ($d = Civil::parseTime($v)) !== null ? Value::time($d) : $this->diag('INVALID_ARG', $path),
            default => $this->diag('TYPE_MISMATCH', $path),
        };
    }

    /** @param  array<string, mixed>  $node */
    private function listNode(array $node, string $path): Value
    {
        $items = [];
        foreach ($node['items'] ?? [] as $i => $item) {
            $items[] = $this->eval($item, $path.'.items.'.$i);
        }

        return $this->makeList($items, $path);
    }

    /** @param  list<Value>  $items */
    private function makeList(array $items, string $path): Value
    {
        if (count($items) > self::MAX_LIST) {
            return $this->diag('LIMIT_EXCEEDED', $path);
        }
        $type = null;
        foreach ($items as $item) {
            if ($item->isNull()) {
                continue;
            }
            if ($type !== null && $type !== $item->type) {
                return $this->diag('TYPE_MISMATCH', $path);
            }
            $type = $item->type;
        }

        return Value::list($items);
    }

    /** @param  array<string, mixed>  $node */
    private function reference(array $node, string $path): Value
    {
        $segments = $node['path'] ?? [];
        if (! is_array($segments) || $segments === []) {
            return $this->diag('MISSING_REF', $path);
        }
        if (count($segments) - 1 > self::MAX_HOPS) {
            return $this->diag('LIMIT_EXCEEDED', $path);
        }
        $scope = $node['scope'] ?? 'record';
        $ctx = $this->context;

        switch ($scope) {
            case 'user':
                return $this->userValue($segments, $path);
            case 'context':
                return $this->contextValue($segments, $path);
            case 'old':
                if ($ctx->mode === 'create' || $ctx->old === null) {
                    return Value::null();
                }
                $base = $ctx->old;
                break;
            case 'row':
                $base = $ctx->row;
                break;
            case 'parent':
                $base = $ctx->parent;
                break;
            case 'record':
                $base = ($ctx->row !== null && $ctx->row->get($segments[0]) !== null) ? $ctx->row : $ctx->record;
                break;
            default:
                return $this->diag('MISSING_REF', $path);
        }
        if ($base === null) {
            return $this->diag('MISSING_REF', $path);
        }

        return $this->walk(Value::record($base), $segments, $path);
    }

    /** @param  list<string>  $segments */
    private function walk(Value $current, array $segments, string $path): Value
    {
        foreach ($segments as $segment) {
            if ($current->type === Value::RECORD) {
                $next = $current->data->get($segment);
                if ($next === null) {
                    return $this->diag('MISSING_REF', $path);
                }
                $current = $next;
            } elseif ($current->type === Value::LIST) {
                $collected = [];
                foreach ($current->items() as $item) {
                    $this->countRow();
                    if ($item->isNull()) {
                        continue;
                    }
                    if ($item->type !== Value::RECORD) {
                        return $this->diag('TYPE_MISMATCH', $path);
                    }
                    $value = $item->data->get($segment);
                    if ($value === null) {
                        return $this->diag('MISSING_REF', $path);
                    }
                    array_push($collected, ...($value->type === Value::LIST ? $value->items() : [$value]));
                    if (count($collected) > self::MAX_LIST) {
                        return $this->diag('LIMIT_EXCEEDED', $path);
                    }
                }
                $current = Value::list($collected);
            } elseif ($current->isNull()) {
                return Value::null();
            } else {
                return $this->diag('TYPE_MISMATCH', $path);
            }
        }

        return $current;
    }

    /** @param  list<string>  $segments */
    private function userValue(array $segments, string $path): Value
    {
        $user = $this->context->user;
        $key = $segments[0];
        if ($key === 'attributes') {
            if (count($segments) < 2) {
                return $this->diag('MISSING_REF', $path);
            }
            $value = $user['attributes'][$segments[1]] ?? null;

            return $value === null ? Value::null() : $this->walk($value, array_slice($segments, 2), $path);
        }
        if (count($segments) > 1) {
            return $this->diag('MISSING_REF', $path);
        }
        $text = static fn (mixed $v): Value => is_string($v) ? Value::text(Unicode::nfc($v)) : Value::null();

        return match ($key) {
            'id' => isset($user['id']) ? Value::int((int) $user['id']) : Value::null(),
            'name' => $text($user['name'] ?? null),
            'email' => $text($user['email'] ?? null),
            'department' => $text($user['department'] ?? null),
            'locale' => $text($user['locale'] ?? $this->context->locale),
            'roles' => Value::list(array_map(static fn (string $r): Value => Value::text($r), $user['roles'] ?? [])),
            'departments' => Value::list(array_map(static fn (string $d): Value => Value::text($d), $user['departments'] ?? [])),
            default => $this->diag('MISSING_REF', $path),
        };
    }

    /** @param  list<string>  $segments */
    private function contextValue(array $segments, string $path): Value
    {
        $ctx = $this->context;
        if ($segments[0] === 'param') {
            if (count($segments) !== 2) {
                return $this->diag('MISSING_REF', $path);
            }

            return $ctx->params[$segments[1]] ?? Value::null();
        }
        if (count($segments) > 1) {
            return $this->diag('MISSING_REF', $path);
        }

        return match ($segments[0]) {
            'mode' => Value::text($ctx->mode),
            'form' => $ctx->form === null ? Value::null() : Value::text($ctx->form),
            'locale' => Value::text($ctx->locale),
            'timezone' => Value::text($ctx->timezone),
            default => $this->diag('MISSING_REF', $path),
        };
    }

    /** @param  array<string, mixed>  $node */
    private function unary(array $node, string $path): Value
    {
        $a = $this->eval($node['a'] ?? [], $path.'.a');
        if ($a->isNull()) {
            return Value::null();
        }

        return match ($node['op'] ?? null) {
            'neg' => match ($a->type) {
                Value::NUMBER => Value::number($a->data->negate()),
                Value::DURATION => Value::duration($a->data->negate()),
                default => $this->diag('TYPE_MISMATCH', $path),
            },
            'not' => $a->type === Value::BOOLEAN ? Value::bool(! $a->data) : $this->diag('TYPE_MISMATCH', $path),
            default => $this->diag('TYPE_MISMATCH', $path),
        };
    }

    /** @param  array<string, mixed>  $node */
    private function binary(array $node, string $path): Value
    {
        $op = $node['op'] ?? null;
        if ($op === 'and' || $op === 'or') {
            return $this->logic($op, $node, $path);
        }
        $a = $this->eval($node['a'] ?? [], $path.'.a');
        $b = $this->eval($node['b'] ?? [], $path.'.b');

        return match ($op) {
            '+', '-', '*', '/', '%' => $this->arithmetic($op, $a, $b, $path),
            '&' => $this->textResult($this->concatText($a).$this->concatText($b), $path),
            '=' => Value::bool($this->equal($a, $b)),
            '!=' => Value::bool(! $this->equal($a, $b)),
            '<', '<=', '>', '>=' => $this->order($op, $a, $b, $path),
            default => $this->diag('TYPE_MISMATCH', $path),
        };
    }

    /** @param  array<string, mixed>  $node */
    private function logic(string $op, array $node, string $path): Value
    {
        $a = $this->eval($node['a'] ?? [], $path.'.a');
        if (! $a->isNull() && $a->type !== Value::BOOLEAN) {
            return $this->diag('TYPE_MISMATCH', $path);
        }
        if ($op === 'and' && $a->data === false) {
            return Value::bool(false);
        }
        if ($op === 'or' && $a->data === true) {
            return Value::bool(true);
        }
        $b = $this->eval($node['b'] ?? [], $path.'.b');
        if (! $b->isNull() && $b->type !== Value::BOOLEAN) {
            return $this->diag('TYPE_MISMATCH', $path);
        }
        if ($op === 'and') {
            if ($b->data === false) {
                return Value::bool(false);
            }

            return $a->isNull() || $b->isNull() ? Value::null() : Value::bool(true);
        }
        if ($b->data === true) {
            return Value::bool(true);
        }

        return $a->isNull() || $b->isNull() ? Value::null() : Value::bool(false);
    }

    private function arithmetic(string $op, Value $a, Value $b, string $path): Value
    {
        if ($a->isNull() || $b->isNull()) {
            return Value::null();
        }
        $ta = $a->type;
        $tb = $b->type;
        if ($ta === Value::NUMBER && $tb === Value::NUMBER) {
            return $this->numberResult(match ($op) {
                '+' => $a->data->add($b->data),
                '-' => $a->data->sub($b->data),
                '*' => $a->data->mul($b->data),
                '/' => $a->data->div($b->data),
                default => $a->data->mod($b->data), // '%'
            }, $path);
        }
        if ($ta === Value::DATE && $tb === Value::NUMBER && ($op === '+' || $op === '-')) {
            if (! $b->data->isInteger()) {
                return $this->diag('INVALID_ARG', $path);
            }
            $days = $b->data->toSmallInt();

            return $this->dateResult($days === null ? null : $a->data + ($op === '+' ? $days : -$days), $path);
        }
        if ($ta === Value::DATE && $tb === Value::DATE && $op === '-') {
            return Value::int($a->data - $b->data);
        }
        if ($ta === Value::DATETIME && $tb === Value::DURATION && ($op === '+' || $op === '-')) {
            $seconds = $b->data->toSmallInt();
            $result = $seconds === null ? null : $a->data + ($op === '+' ? $seconds : -$seconds);

            return $result !== null && Civil::inRange(intdiv($result, 86400)) ? Value::datetime($result) : $this->diag('INVALID_DATE', $path);
        }
        if ($ta === Value::DATETIME && $tb === Value::DATETIME && $op === '-') {
            return Value::duration(Decimal::of($a->data - $b->data));
        }
        if ($ta === Value::DURATION && $tb === Value::DURATION && ($op === '+' || $op === '-')) {
            return $this->durationResult($op === '+' ? $a->data->add($b->data) : $a->data->sub($b->data), $path);
        }
        if ($ta === Value::DURATION && $tb === Value::NUMBER && ($op === '*' || $op === '/')) {
            $result = $op === '*' ? $a->data->mul($b->data) : $a->data->div($b->data);

            return $this->durationResult(is_string($result) ? $result : $result->trunc(0), $path);
        }

        return $this->diag('TYPE_MISMATCH', $path);
    }

    private function numberResult(Decimal|string $result, string $path): Value
    {
        return is_string($result) ? $this->diag($result, $path) : Value::number($result);
    }

    private function durationResult(Decimal|string $result, string $path): Value
    {
        return is_string($result) ? $this->diag($result, $path) : Value::duration($result);
    }

    private function dateResult(?int $days, string $path): Value
    {
        return $days !== null && Civil::inRange($days) ? Value::date($days) : $this->diag('INVALID_DATE', $path);
    }

    private function textResult(string $text, string $path): Value
    {
        return Unicode::length($text) > Unicode::MAX_LENGTH ? $this->diag('LIMIT_EXCEEDED', $path) : Value::text($text);
    }

    private function order(string $op, Value $a, Value $b, string $path): Value
    {
        if ($a->isNull() || $b->isNull()) {
            return Value::null();
        }
        $cmp = $this->compare($a, $b);
        if ($cmp === null) {
            return $this->diag('TYPE_MISMATCH', $path);
        }

        return Value::bool(match ($op) {
            '<' => $cmp < 0,
            '<=' => $cmp <= 0,
            '>' => $cmp > 0,
            default => $cmp >= 0, // '>='
        });
    }

    /** Ordering of two non-null values of one orderable type, else null. */
    private function compare(Value $a, Value $b): ?int
    {
        if ($a->type !== $b->type) {
            return null;
        }

        return match ($a->type) {
            Value::NUMBER, Value::DURATION => $a->data->compare($b->data),
            Value::TEXT => strcmp($a->data, $b->data) <=> 0,
            Value::DATE, Value::DATETIME, Value::TIME => $a->data <=> $b->data,
            default => null,
        };
    }

    private function equal(Value $a, Value $b): bool
    {
        if ($a->isNull() || $b->isNull()) {
            return $a->isNull() && $b->isNull();
        }
        if ($a->type !== $b->type) {
            return false;
        }

        return match ($a->type) {
            Value::NUMBER, Value::DURATION => $a->data->equals($b->data),
            Value::LIST => count($a->data) === count($b->data) && $this->listsEqual($a->data, $b->data),
            Value::RECORD => $this->sameRecord($a->data, $b->data),
            default => $a->data === $b->data,
        };
    }

    /**
     * @param  list<Value>  $a
     * @param  list<Value>  $b
     */
    private function listsEqual(array $a, array $b): bool
    {
        foreach ($a as $i => $item) {
            if (! $this->equal($item, $b[$i])) {
                return false;
            }
        }

        return true;
    }

    private function sameRecord(RecordSource $a, RecordSource $b): bool
    {
        $ia = $a->identity();

        return $ia !== null ? $ia === $b->identity() : $a === $b;
    }

    // ── Text conversion ──────────────────────────────────────────────────

    /** `&` / concat: null → "" */
    private function concatText(Value $v): string
    {
        return $v->isNull() ? '' : ($this->toText($v) ?? '');
    }

    private function toText(Value $v): ?string
    {
        return match ($v->type) {
            Value::NULL => null,
            Value::TEXT => $v->data,
            Value::NUMBER, Value::DURATION => $v->data->toString(),
            Value::BOOLEAN => $v->data ? 'true' : 'false',
            Value::DATE => Civil::formatDate($v->data),
            Value::DATETIME => Civil::formatDatetime($v->data),
            Value::TIME => Civil::formatTime($v->data),
            Value::LIST => implode(', ', array_values(array_filter(array_map(fn (Value $i): ?string => $this->toText($i), $v->data), static fn (?string $s): bool => $s !== null))),
            Value::RECORD => $v->data->title(),
            default => null,
        };
    }

    // ── Functions ────────────────────────────────────────────────────────

    /** @param  array<string, mixed>  $node */
    private function call(array $node, string $path): Value
    {
        $fn = $node['fn'] ?? '';
        $args = $node['args'] ?? [];
        $arity = Signatures::arity($fn);
        if ($arity === null) {
            return $this->diag('TYPE_MISMATCH', $path);
        }
        [$min, $max] = $arity;
        if (count($args) < $min || ($max !== null && count($args) > $max)) {
            return $this->diag('TYPE_MISMATCH', $path);
        }

        // Lazily evaluated and row-scoped functions.
        switch ($fn) {
            case 'if':
                $cond = $this->eval($args[0], $path.'.args.0');
                if (! $cond->isNull() && $cond->type !== Value::BOOLEAN) {
                    return $this->diag('TYPE_MISMATCH', $path);
                }

                return $cond->data === true ? $this->eval($args[1], $path.'.args.1') : $this->eval($args[2], $path.'.args.2');
            case 'switch':
                $x = $this->eval($args[0], $path.'.args.0');
                $n = count($args);
                for ($i = 1; $i + 1 < $n; $i += 2) {
                    if ($this->equal($x, $this->eval($args[$i], $path.'.args.'.$i))) {
                        return $this->eval($args[$i + 1], $path.'.args.'.($i + 1));
                    }
                }

                return ($n - 1) % 2 === 1 ? $this->eval($args[$n - 1], $path.'.args.'.($n - 1)) : Value::null();
            case 'coalesce':
                foreach ($args as $i => $arg) {
                    $v = $this->eval($arg, $path.'.args.'.$i);
                    if (! $v->isNull()) {
                        return $v;
                    }
                }

                return Value::null();
            case 'changed':
            case 'changed_from_to':
                return $this->changed($fn, $args, $path);
        }
        if (Signatures::isRowForm($fn, count($args))) {
            return $this->rowAggregate($fn, $args, $path);
        }

        $values = [];
        foreach ($args as $i => $arg) {
            $values[] = $this->eval($arg, $path.'.args.'.$i);
        }

        return $this->apply($fn, $values, $path);
    }

    /**
     * @param  list<array<string, mixed>>  $args
     */
    private function changed(string $fn, array $args, string $path): Value
    {
        $ref = $args[0];
        if (($ref['k'] ?? null) !== 'ref') {
            return $this->diag('TYPE_MISMATCH', $path);
        }
        $new = $this->eval($ref, $path.'.args.0');
        $old = $this->eval(['k' => 'ref', 'scope' => 'old', 'path' => $ref['path'] ?? []], $path.'.args.0');
        if ($fn === 'changed') {
            return Value::bool($this->context->mode !== 'create' && ! $this->equal($new, $old));
        }
        $from = $this->eval($args[1], $path.'.args.1');
        $to = $this->eval($args[2], $path.'.args.2');

        return Value::bool($this->equal($old, $from) && $this->equal($new, $to));
    }

    /** @param  list<array<string, mixed>>  $args */
    private function rowAggregate(string $fn, array $args, string $path): Value
    {
        $rows = $this->eval($args[0], $path.'.args.0');
        if ($rows->isNull()) {
            return $fn === 'sum' || $fn === 'count' ? Value::int(0) : ($fn === 'join' ? Value::text('') : Value::null());
        }
        if ($rows->type !== Value::LIST) {
            return $this->diag('TYPE_MISMATCH', $path);
        }
        $results = [];
        $outer = $this->context;
        foreach ($rows->items() as $row) {
            $this->countRow();
            if ($row->isNull()) {
                continue;
            }
            if ($row->type !== Value::RECORD) {
                return $this->diag('TYPE_MISMATCH', $path);
            }
            $this->context = $outer->withRow($row->data);
            try {
                $results[] = $this->eval($args[1], $path.'.args.1');
            } finally {
                $this->context = $outer;
            }
        }
        if ($fn === 'count') {
            $count = 0;
            foreach ($results as $r) {
                if (! $r->isNull() && $r->type !== Value::BOOLEAN) {
                    return $this->diag('TYPE_MISMATCH', $path);
                }
                $count += $r->data === true ? 1 : 0;
            }

            return Value::int($count);
        }
        if ($fn === 'join') {
            $sep = $this->eval($args[2], $path.'.args.2');

            return $this->join($results, $sep, $path);
        }

        return $this->apply($fn, [Value::list($results)], $path);
    }

    private function countRow(): void
    {
        if (++$this->steps > self::MAX_STEPS) {
            throw new BudgetExceeded;
        }
        if (++$this->rows > self::MAX_ROWS) {
            throw new BudgetExceeded;
        }
    }

    /** @param  list<Value>  $a */
    private function apply(string $fn, array $a, string $path): Value
    {
        // Functions that accept null arguments.
        switch ($fn) {
            case 'is_empty':
                $v = $a[0];

                return Value::bool($v->isNull() || ($v->type === Value::TEXT && $v->data === '') || ($v->type === Value::LIST && $v->data === []));
            case 'is_null':
                return Value::bool($a[0]->isNull());
            case 'in':
                if ($a[1]->isNull()) {
                    return Value::bool(false);
                }
                if ($a[1]->type !== Value::LIST) {
                    return $this->diag('TYPE_MISMATCH', $path);
                }
                foreach ($a[1]->items() as $item) {
                    if ($this->equal($a[0], $item)) {
                        return Value::bool(true);
                    }
                }

                return Value::bool(false);
            case 'concat':
                return $this->textResult(implode('', array_map(fn (Value $v): string => $this->concatText($v), $a)), $path);
            case 'min':
            case 'max':
                return $this->minMax($fn, $a, $path);
            case 'sum':
            case 'avg':
            case 'count':
            case 'first':
            case 'last':
            case 'distinct':
                return $this->aggregate($fn, $a[0], $path);
            case 'join':
                if ($a[0]->isNull()) {
                    return Value::text('');
                }
                if ($a[0]->type !== Value::LIST) {
                    return $this->diag('TYPE_MISMATCH', $path);
                }

                return $this->join($a[0]->items(), $a[1], $path);
            case 'to_text':
                return $a[0]->isNull() ? Value::null() : $this->nullableText($this->toText($a[0]), $path);
            case 'today':
                return Value::date($this->context->today);
            case 'now':
                return Value::datetime($this->context->now);
            case 'has_role':
                return $a[0]->isNull() ? Value::null() : ($a[0]->type === Value::TEXT ? Value::bool(in_array($a[0]->data, $this->context->user['roles'] ?? [], true)) : $this->diag('TYPE_MISMATCH', $path));
            case 'in_department':
                return $this->inDepartment($a, $path);
        }
        foreach ($a as $v) {
            if ($v->isNull()) {
                return Value::null();
            }
        }

        return match ($fn) {
            // Math
            'abs' => $this->num1($a, $path, static fn (Decimal $x) => $x->abs()),
            'round' => $this->rounding($a, $path, 'round'),
            'trunc' => $this->rounding($a, $path, 'trunc'),
            'floor' => $this->num1($a, $path, static fn (Decimal $x) => $x->floor()),
            'ceil' => $this->num1($a, $path, static fn (Decimal $x) => $x->ceil()),
            'mod' => $this->arithmetic('%', $a[0], $a[1], $path),
            'power' => $this->power($a, $path),
            'sqrt' => $this->num1($a, $path, static fn (Decimal $x) => $x->isNegative() ? 'INVALID_ARG' : $x->sqrt()),
            'clamp' => $this->clamp($a, $path),
            'sign' => $this->num1($a, $path, static fn (Decimal $x) => Decimal::of($x->sign)),
            // Text
            'len' => $this->text1($a, $path, static fn (string $s) => Value::int(Unicode::length($s))),
            'upper' => $this->text1($a, $path, fn (string $s) => $this->textResult(Unicode::nfc(Unicode::upper($s)), $path)),
            'lower' => $this->text1($a, $path, fn (string $s) => $this->textResult(Unicode::nfc(Unicode::lower($s)), $path)),
            'trim' => $this->text1($a, $path, static fn (string $s) => Value::text(Unicode::trim($s))),
            'left', 'right' => $this->leftRight($fn, $a, $path),
            'mid' => $this->mid($a, $path),
            'contains', 'starts_with', 'ends_with' => $this->textPredicate($fn, $a, $path),
            'replace' => $this->replace($a, $path),
            'split' => $this->split($a, $path),
            'pad_left' => $this->padLeft($a, $path),
            'matches' => $this->matches($a, $path),
            'normalize_arabic' => $this->text1($a, $path, static fn (string $s) => Value::text(Unicode::nfc(Unicode::normalizeArabic($s)))),
            // Dates
            'date' => $this->makeDate($a, $path),
            'datetime' => $a[0]->type === Value::DATE && $a[1]->type === Value::TIME ? Value::datetime($a[0]->data * 86400 + $a[1]->data) : $this->diag('TYPE_MISMATCH', $path),
            'year', 'month', 'day' => $this->dateParts($fn, $a, $path),
            'weekday' => $a[0]->type === Value::DATE ? Value::int(Civil::weekday($a[0]->data)) : $this->diag('TYPE_MISMATCH', $path),
            'add_days', 'add_months', 'add_years' => $this->addToDate($fn, $a, $path),
            'diff_days' => $a[0]->type === Value::DATE && $a[1]->type === Value::DATE ? Value::int($a[1]->data - $a[0]->data) : $this->diag('TYPE_MISMATCH', $path),
            'diff_months' => $this->diffMonths($a, $path),
            'start_of_month', 'end_of_month' => $this->monthBoundary($fn, $a, $path),
            'seconds', 'minutes', 'hours', 'days' => $this->makeDuration($fn, $a, $path),
            'duration_seconds' => $a[0]->type === Value::DURATION ? Value::number($a[0]->data) : $this->diag('TYPE_MISMATCH', $path),
            'to_date' => $this->toDate($a, $path),
            'hijri_year', 'hijri_month', 'hijri_day' => $this->hijriPart($fn, $a, $path),
            'from_hijri' => $this->fromHijri($a, $path),
            'hijri_text' => $this->hijriText($a, $path),
            'is_working_day' => $a[0]->type === Value::DATE ? Value::bool($this->context->calendar()->isWorkingDay($a[0]->data)) : $this->diag('TYPE_MISMATCH', $path),
            'add_working_days' => $this->addWorkingDays($a, $path),
            // Conversion
            'to_number' => $this->toNumber($a, $path),
            'to_boolean' => $this->toBoolean($a, $path),
            default => $this->diag('TYPE_MISMATCH', $path),
        };
    }

    private function nullableText(?string $s, string $path): Value
    {
        return $s === null ? Value::null() : $this->textResult($s, $path);
    }

    /**
     * @param  list<Value>  $a
     * @param  callable(Decimal): (Decimal|string)  $f
     */
    private function num1(array $a, string $path, callable $f): Value
    {
        return $a[0]->type === Value::NUMBER ? $this->numberResult($f($a[0]->data), $path) : $this->diag('TYPE_MISMATCH', $path);
    }

    /**
     * @param  list<Value>  $a
     * @param  callable(string): Value  $f
     */
    private function text1(array $a, string $path, callable $f): Value
    {
        return $a[0]->type === Value::TEXT ? $f($a[0]->data) : $this->diag('TYPE_MISMATCH', $path);
    }

    /** An integer argument within [min, max], or null. */
    private function intArg(Value $v, int $min, int $max): ?int
    {
        if ($v->type !== Value::NUMBER || ! $v->data->isInteger()) {
            return null;
        }
        $n = $v->data->toSmallInt();

        return $n !== null && $n >= $min && $n <= $max ? $n : null;
    }

    /** @param  list<Value>  $a */
    private function rounding(array $a, string $path, string $mode): Value
    {
        if ($a[0]->type !== Value::NUMBER || (isset($a[1]) && $a[1]->type !== Value::NUMBER)) {
            return $this->diag('TYPE_MISMATCH', $path);
        }
        $digits = isset($a[1]) ? $this->intArg($a[1], -10, 20) : 0;
        if ($digits === null) {
            return $this->diag('INVALID_ARG', $path);
        }

        return $this->numberResult($mode === 'round' ? $a[0]->data->round($digits) : $a[0]->data->trunc($digits), $path);
    }

    /** @param  list<Value>  $a */
    private function power(array $a, string $path): Value
    {
        if ($a[0]->type !== Value::NUMBER || $a[1]->type !== Value::NUMBER) {
            return $this->diag('TYPE_MISMATCH', $path);
        }
        $n = $this->intArg($a[1], 0, 64);

        return $n === null ? $this->diag('INVALID_ARG', $path) : $this->numberResult($a[0]->data->pow($n), $path);
    }

    /** @param  list<Value>  $a */
    private function clamp(array $a, string $path): Value
    {
        [$x, $lo, $hi] = $a;
        $c1 = $this->compare($x, $lo);
        $c2 = $this->compare($x, $hi);
        if ($c1 === null || $c2 === null) {
            return $this->diag('TYPE_MISMATCH', $path);
        }

        return $c1 < 0 ? $lo : ($c2 > 0 ? $hi : $x);
    }

    /** @param  list<Value>  $a */
    private function minMax(string $fn, array $a, string $path): Value
    {
        $values = $a;
        if (count($a) === 1 && $a[0]->type === Value::LIST) {
            $values = $a[0]->items();
        }
        $best = null;
        foreach ($values as $v) {
            if ($v->isNull()) {
                continue;
            }
            if (! in_array($v->type, [Value::NUMBER, Value::TEXT, Value::DATE, Value::DATETIME, Value::TIME, Value::DURATION], true)) {
                return $this->diag('TYPE_MISMATCH', $path);
            }
            if ($best === null) {
                $best = $v;

                continue;
            }
            $cmp = $this->compare($v, $best);
            if ($cmp === null) {
                return $this->diag('TYPE_MISMATCH', $path);
            }
            if (($fn === 'min' && $cmp < 0) || ($fn === 'max' && $cmp > 0)) {
                $best = $v;
            }
        }

        return $best ?? Value::null();
    }

    private function aggregate(string $fn, Value $list, string $path): Value
    {
        if ($list->isNull()) {
            return match ($fn) {
                'sum', 'count' => Value::int(0),
                'distinct' => Value::list([]),
                default => Value::null(),
            };
        }
        if ($list->type !== Value::LIST) {
            return $this->diag('TYPE_MISMATCH', $path);
        }
        $values = array_values(array_filter($list->items(), static fn (Value $v): bool => ! $v->isNull()));
        switch ($fn) {
            case 'count':
                return Value::int(count($values));
            case 'first':
                return $values[0] ?? Value::null();
            case 'last':
                return $values === [] ? Value::null() : $values[count($values) - 1];
            case 'distinct':
                $out = [];
                foreach ($values as $v) {
                    foreach ($out as $seen) {
                        if ($this->equal($seen, $v)) {
                            continue 2;
                        }
                    }
                    $out[] = $v;
                }

                return Value::list($out);
        }
        // sum / avg
        if ($values === []) {
            return $fn === 'sum' ? Value::int(0) : Value::null();
        }
        $type = $values[0]->type;
        if ($type !== Value::NUMBER && $type !== Value::DURATION) {
            return $this->diag('TYPE_MISMATCH', $path);
        }
        $total = Decimal::zero();
        foreach ($values as $v) {
            if ($v->type !== $type) {
                return $this->diag('TYPE_MISMATCH', $path);
            }
            $total = $total->add($v->data);
            if (is_string($total)) {
                return $this->diag($total, $path);
            }
        }
        if ($fn === 'avg') {
            $total = $total->div(Decimal::of(count($values)));
            if (is_string($total)) {
                return $this->diag($total, $path);
            }
            if ($type === Value::DURATION) {
                $total = $total->trunc(0);
                if (is_string($total)) {
                    return $this->diag($total, $path);
                }
            }
        }

        return $type === Value::NUMBER ? Value::number($total) : Value::duration($total);
    }

    /** @param  list<Value>  $items */
    private function join(array $items, Value $sep, string $path): Value
    {
        if ($sep->isNull()) {
            return Value::null();
        }
        if ($sep->type !== Value::TEXT) {
            return $this->diag('TYPE_MISMATCH', $path);
        }
        $parts = [];
        foreach ($items as $item) {
            if (! $item->isNull()) {
                $parts[] = $this->toText($item) ?? '';
            }
        }

        return $this->textResult(implode($sep->data, $parts), $path);
    }

    /** @param  list<Value>  $a */
    private function inDepartment(array $a, string $path): Value
    {
        if ($a[0]->isNull()) {
            return Value::null();
        }
        if ($a[0]->type !== Value::TEXT || (isset($a[1]) && ! $a[1]->isNull() && $a[1]->type !== Value::BOOLEAN)) {
            return $this->diag('TYPE_MISMATCH', $path);
        }
        $user = $this->context->user;
        if (isset($a[1]) && $a[1]->data === true) {
            return Value::bool(in_array($a[0]->data, $user['departments'] ?? [], true));
        }

        return Value::bool(($user['department'] ?? null) === $a[0]->data);
    }

    /** @param  list<Value>  $a */
    private function leftRight(string $fn, array $a, string $path): Value
    {
        if ($a[0]->type !== Value::TEXT || $a[1]->type !== Value::NUMBER) {
            return $this->diag('TYPE_MISMATCH', $path);
        }
        $n = $this->intArg($a[1], 0, PHP_INT_MAX);
        if ($n === null) {
            return $this->diag('INVALID_ARG', $path);
        }
        $len = Unicode::length($a[0]->data);

        return Value::text($fn === 'left' ? Unicode::substr($a[0]->data, 0, $n) : Unicode::substr($a[0]->data, max(0, $len - $n)));
    }

    /** @param  list<Value>  $a */
    private function mid(array $a, string $path): Value
    {
        if ($a[0]->type !== Value::TEXT || $a[1]->type !== Value::NUMBER || $a[2]->type !== Value::NUMBER) {
            return $this->diag('TYPE_MISMATCH', $path);
        }
        $start = $this->intArg($a[1], 1, PHP_INT_MAX);
        $count = $this->intArg($a[2], 0, PHP_INT_MAX);
        if ($start === null || $count === null) {
            return $this->diag('INVALID_ARG', $path);
        }

        return Value::text(Unicode::substr($a[0]->data, $start - 1, $count));
    }

    /** @param  list<Value>  $a */
    private function textPredicate(string $fn, array $a, string $path): Value
    {
        if ($a[0]->type !== Value::TEXT || $a[1]->type !== Value::TEXT) {
            return $this->diag('TYPE_MISMATCH', $path);
        }

        return Value::bool(match ($fn) {
            'contains' => str_contains($a[0]->data, $a[1]->data),
            'starts_with' => str_starts_with($a[0]->data, $a[1]->data),
            default => str_ends_with($a[0]->data, $a[1]->data), // ends_with
        });
    }

    /** @param  list<Value>  $a */
    private function replace(array $a, string $path): Value
    {
        if ($a[0]->type !== Value::TEXT || $a[1]->type !== Value::TEXT || $a[2]->type !== Value::TEXT) {
            return $this->diag('TYPE_MISMATCH', $path);
        }
        if ($a[1]->data === '') {
            return $a[0];
        }

        return $this->textResult(Unicode::nfc(str_replace($a[1]->data, $a[2]->data, $a[0]->data)), $path);
    }

    /** @param  list<Value>  $a */
    private function split(array $a, string $path): Value
    {
        if ($a[0]->type !== Value::TEXT || $a[1]->type !== Value::TEXT) {
            return $this->diag('TYPE_MISMATCH', $path);
        }
        if ($a[1]->data === '') {
            return $this->diag('INVALID_ARG', $path);
        }
        $parts = explode($a[1]->data, $a[0]->data);
        if (count($parts) > self::MAX_LIST) {
            return $this->diag('LIMIT_EXCEEDED', $path);
        }

        return Value::list(array_map(static fn (string $p): Value => Value::text(Unicode::nfc($p)), $parts));
    }

    /** @param  list<Value>  $a */
    private function padLeft(array $a, string $path): Value
    {
        if ($a[0]->type !== Value::TEXT || $a[1]->type !== Value::NUMBER || $a[2]->type !== Value::TEXT) {
            return $this->diag('TYPE_MISMATCH', $path);
        }
        if (Unicode::length($a[2]->data) !== 1) {
            return $this->diag('INVALID_ARG', $path);
        }
        $n = $this->intArg($a[1], 0, PHP_INT_MAX);
        if ($n === null) {
            return $this->diag('INVALID_ARG', $path);
        }
        if ($n > Unicode::MAX_LENGTH) {
            return $this->diag('LIMIT_EXCEEDED', $path);
        }
        $len = Unicode::length($a[0]->data);

        return $this->textResult(str_repeat($a[2]->data, max(0, $n - $len)).$a[0]->data, $path);
    }

    /** @param  list<Value>  $a */
    private function matches(array $a, string $path): Value
    {
        if ($a[0]->type !== Value::TEXT || $a[1]->type !== Value::TEXT) {
            return $this->diag('TYPE_MISMATCH', $path);
        }
        $result = SafeRegex::matches($a[0]->data, $a[1]->data);

        return $result === null ? $this->diag('INVALID_ARG', $path) : Value::bool($result);
    }

    /** @param  list<Value>  $a */
    private function makeDate(array $a, string $path): Value
    {
        foreach ($a as $v) {
            if ($v->type !== Value::NUMBER) {
                return $this->diag('TYPE_MISMATCH', $path);
            }
        }
        $y = $this->intArg($a[0], 1, 9999);
        $m = $this->intArg($a[1], 1, 12);
        $d = $this->intArg($a[2], 1, 31);
        if ($y === null || $m === null || $d === null || ! Civil::isValid($y, $m, $d)) {
            return $this->diag('INVALID_ARG', $path);
        }

        return Value::date(Civil::daysFromCivil($y, $m, $d));
    }

    /** @param  list<Value>  $a */
    private function dateParts(string $fn, array $a, string $path): Value
    {
        if ($a[0]->type !== Value::DATE) {
            return $this->diag('TYPE_MISMATCH', $path);
        }
        [$y, $m, $d] = Civil::civilFromDays($a[0]->data);

        return Value::int(match ($fn) {
            'year' => $y,
            'month' => $m,
            default => $d, // day
        });
    }

    /** @param  list<Value>  $a */
    private function addToDate(string $fn, array $a, string $path): Value
    {
        if ($a[0]->type !== Value::DATE || $a[1]->type !== Value::NUMBER) {
            return $this->diag('TYPE_MISMATCH', $path);
        }
        $n = $this->intArg($a[1], -4_000_000, 4_000_000);
        if ($n === null) {
            return $a[1]->data->isInteger() ? $this->diag('INVALID_DATE', $path) : $this->diag('INVALID_ARG', $path);
        }
        if ($fn === 'add_days') {
            return $this->dateResult($a[0]->data + $n, $path);
        }
        [$y, $m, $d] = Civil::civilFromDays($a[0]->data);
        $months = $y * 12 + ($m - 1) + ($fn === 'add_months' ? $n : $n * 12);
        $ny = intdiv($months, 12);
        $nm = $months % 12 + 1;
        if ($ny < 1 || $ny > 9999) {
            return $this->diag('INVALID_DATE', $path);
        }

        return Value::date(Civil::daysFromCivil($ny, $nm, min($d, Civil::daysInMonth($ny, $nm))));
    }

    /** @param  list<Value>  $a */
    private function diffMonths(array $a, string $path): Value
    {
        if ($a[0]->type !== Value::DATE || $a[1]->type !== Value::DATE) {
            return $this->diag('TYPE_MISMATCH', $path);
        }
        [$y1, $m1, $d1] = Civil::civilFromDays($a[0]->data);
        [$y2, $m2, $d2] = Civil::civilFromDays($a[1]->data);
        $months = ($y2 - $y1) * 12 + ($m2 - $m1);
        if ($months > 0 && $d2 < $d1) {
            $months--;
        } elseif ($months < 0 && $d2 > $d1) {
            $months++;
        }

        return Value::int($months);
    }

    /** @param  list<Value>  $a */
    private function monthBoundary(string $fn, array $a, string $path): Value
    {
        if ($a[0]->type !== Value::DATE) {
            return $this->diag('TYPE_MISMATCH', $path);
        }
        [$y, $m] = Civil::civilFromDays($a[0]->data);

        return Value::date(Civil::daysFromCivil($y, $m, $fn === 'start_of_month' ? 1 : Civil::daysInMonth($y, $m)));
    }

    /** @param  list<Value>  $a */
    private function makeDuration(string $fn, array $a, string $path): Value
    {
        if ($a[0]->type !== Value::NUMBER) {
            return $this->diag('TYPE_MISMATCH', $path);
        }
        $factor = ['seconds' => 1, 'minutes' => 60, 'hours' => 3600, 'days' => 86400][$fn];
        $seconds = $a[0]->data->mul(Decimal::of($factor));

        return $this->durationResult(is_string($seconds) ? $seconds : $seconds->trunc(0), $path);
    }

    /** @param  list<Value>  $a */
    private function toDate(array $a, string $path): Value
    {
        $v = $a[0];
        if ($v->type === Value::DATE) {
            return $v;
        }
        if ($v->type === Value::TEXT) {
            $days = Civil::parseDate(Unicode::trim($v->data));

            return $days === null ? $this->diag('INVALID_ARG', $path) : Value::date($days);
        }
        if ($v->type === Value::DATETIME) {
            try {
                $local = (new \DateTimeImmutable('@'.$v->data))->setTimezone(new \DateTimeZone($this->context->timezone));
            } catch (\Exception) {
                return $this->diag('INVALID_ARG', $path);
            }

            return $this->dateResult(Civil::parseDate($local->format('Y-m-d')), $path);
        }

        return $this->diag('TYPE_MISMATCH', $path);
    }

    /** @param  list<Value>  $a */
    private function hijriPart(string $fn, array $a, string $path): Value
    {
        if ($a[0]->type !== Value::DATE) {
            return $this->diag('TYPE_MISMATCH', $path);
        }
        $h = UmmAlQura::fromDays($a[0]->data);
        if ($h === null) {
            return $this->diag('INVALID_DATE', $path);
        }

        return Value::int(match ($fn) {
            'hijri_year' => $h[0],
            'hijri_month' => $h[1],
            default => $h[2], // hijri_day
        });
    }

    /** @param  list<Value>  $a */
    private function fromHijri(array $a, string $path): Value
    {
        foreach ($a as $v) {
            if ($v->type !== Value::NUMBER) {
                return $this->diag('TYPE_MISMATCH', $path);
            }
        }
        $y = $this->intArg($a[0], 1, 9999);
        $m = $this->intArg($a[1], -99, 99);
        $d = $this->intArg($a[2], -99, 99);
        if ($y === null || $m === null || $d === null) {
            return $this->diag('INVALID_ARG', $path);
        }
        $days = UmmAlQura::toDays($y, $m, $d);

        return is_string($days) ? $this->diag($days, $path) : Value::date($days);
    }

    /** @param  list<Value>  $a */
    private function hijriText(array $a, string $path): Value
    {
        if ($a[0]->type !== Value::DATE) {
            return $this->diag('TYPE_MISMATCH', $path);
        }
        $h = UmmAlQura::fromDays($a[0]->data);

        return $h === null ? $this->diag('INVALID_DATE', $path) : Value::text(sprintf('%04d-%02d-%02d', $h[0], $h[1], $h[2]));
    }

    /** @param  list<Value>  $a */
    private function addWorkingDays(array $a, string $path): Value
    {
        if ($a[0]->type !== Value::DATE || $a[1]->type !== Value::NUMBER) {
            return $this->diag('TYPE_MISMATCH', $path);
        }
        $n = $this->intArg($a[1], -36600, 36600);
        if ($n === null) {
            return $this->diag('INVALID_ARG', $path);
        }
        $calendar = $this->context->calendar();
        if (! $calendar->hasWorkingDays()) {
            return $this->diag('INVALID_ARG', $path);
        }
        $day = $a[0]->data;
        $step = $n >= 0 ? 1 : -1;
        $remaining = abs($n);
        while ($remaining > 0) {
            $this->countRow();
            $day += $step;
            if (! Civil::inRange($day)) {
                return $this->diag('INVALID_DATE', $path);
            }
            if ($calendar->isWorkingDay($day)) {
                $remaining--;
            }
        }

        return Value::date($day);
    }

    /** @param  list<Value>  $a */
    private function toNumber(array $a, string $path): Value
    {
        $v = $a[0];
        if ($v->type === Value::NUMBER) {
            return $v;
        }
        if ($v->type === Value::BOOLEAN) {
            return Value::int($v->data ? 1 : 0);
        }
        if ($v->type !== Value::TEXT) {
            return $this->diag('TYPE_MISMATCH', $path);
        }
        $text = Unicode::asciiDigits(Unicode::trim($v->data));
        $d = Decimal::parse($text);
        if ($d === null) {
            return $this->diag('INVALID_ARG', $path);
        }

        return $this->numberResult(Decimal::limit($d), $path);
    }

    /** @param  list<Value>  $a */
    private function toBoolean(array $a, string $path): Value
    {
        $v = $a[0];
        if ($v->type === Value::BOOLEAN) {
            return $v;
        }
        if ($v->type === Value::NUMBER) {
            if ($v->data->isZero()) {
                return Value::bool(false);
            }

            return $v->data->equals(Decimal::of(1)) ? Value::bool(true) : $this->diag('INVALID_ARG', $path);
        }
        if ($v->type === Value::TEXT) {
            return match (strtolower(Unicode::trim($v->data))) {
                'true' => Value::bool(true),
                'false' => Value::bool(false),
                default => $this->diag('INVALID_ARG', $path),
            };
        }

        return $this->diag('TYPE_MISMATCH', $path);
    }

    private function diag(string $code, string $path): Value
    {
        $this->diagnostics[$code.'|'.$path] ??= ['code' => $code, 'node' => ltrim($path, '.')];

        return Value::null();
    }
}
