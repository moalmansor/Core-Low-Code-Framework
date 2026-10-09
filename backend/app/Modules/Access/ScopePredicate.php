<?php

declare(strict_types=1);

namespace App\Modules\Access;

use App\Expressions\Calendars\Civil;
use App\Expressions\Evaluation\Context;
use App\Expressions\Evaluation\Evaluator;
use App\Modules\Identity\Models\User;
use App\Modules\Records\Runtime\ExpressionContext;
use App\Modules\Records\Runtime\FormRuntime;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Throwable;

/**
 * Compiles the condition of a `custom` record scope (architecture §16.6) into
 * a parameterized WHERE clause. Only a safe subset is accepted: `and`, `or`,
 * `not`, comparisons, `in`, `is_empty`/`is_null` and `contains`, where one
 * side is a stored, unencrypted field of the record and the other side does
 * not read the record (literals, `@user`, `today()`, …), which is evaluated
 * once in PHP and bound as a parameter. Anything else is rejected when the
 * rule is saved, and never matches at run time.
 */
final class ScopePredicate
{
    private const COMPARISONS = ['=' => '=', '!=' => '<>', '<' => '<', '<=' => '<=', '>' => '>', '>=' => '>='];

    public function __construct(private readonly ExpressionContext $userContext) {}

    /**
     * Throws InvalidArgumentException naming the first unsupported part.
     *
     * @param  array<string, mixed>  $ast
     * @param  array<string, array<string, mixed>>  $fieldsByKey  draft fields by key
     */
    public static function validate(array $ast, array $fieldsByKey): void
    {
        self::walk($ast, static function (string $key) use ($fieldsByKey): void {
            $f = $fieldsByKey[$key] ?? null;
            if ($f === null || ($f['flags']['encrypted'] ?? false)) {
                throw new InvalidArgumentException(__('access.scope_field_unsupported', ['key' => $key]));
            }
        });
    }

    /**
     * Adds the predicate to the query. When it cannot be compiled the query
     * matches nothing (or everything, for exclusions), so failure never widens access.
     *
     * @param  array<string, mixed>  $ast
     */
    public function apply(Builder $q, FormRuntime $rt, array $ast, User $user, string $table, bool $matchAllOnFailure = false): void
    {
        $ctx = $this->context($rt, $user);
        try {
            $inner = DB::query();
            $this->node($inner, $rt, $ast, $ctx, $table);
            $q->addNestedWhereQuery($inner);
        } catch (Throwable) {
            // Allow-scopes fail closed by matching nothing; exclusions by matching everything.
            $q->whereRaw($matchAllOnFailure ? '1 = 1' : '1 = 0');
        }
    }

    /** @param  array<string, mixed>  $n */
    private function node(Builder $q, FormRuntime $rt, array $n, Context $ctx, string $table, bool $negate = false): void
    {
        $k = $n['k'] ?? null;
        if ($k === 'bin' && in_array($n['op'], ['and', 'or'], true)) {
            $and = ($n['op'] === 'and') !== $negate; // De Morgan under negation
            $q->where(function (Builder $w) use ($rt, $n, $ctx, $table, $negate, $and): void {
                $this->node($w, $rt, $n['a'], $ctx, $table, $negate);
                $and ? $w->where(fn (Builder $x) => $this->node($x, $rt, $n['b'], $ctx, $table, $negate))
                    : $w->orWhere(fn (Builder $x) => $this->node($x, $rt, $n['b'], $ctx, $table, $negate));
            });

            return;
        }
        if ($k === 'un' && $n['op'] === 'not') {
            $this->node($q, $rt, $n['a'], $ctx, $table, ! $negate);

            return;
        }
        if ($k === 'bin' && isset(self::COMPARISONS[$n['op']])) {
            [$column, $other, $op] = $this->sides($rt, $n['a'], $n['b'], $n['op'], $table);
            $value = $this->constant($rt, $column['field'], $other, $ctx);
            if ($value === null) {
                // Comparisons with empty follow the expression language: only `=`/`!=` are meaningful.
                $isNull = ($op === '=') !== $negate;
                $isNull ? $q->whereNull($column['sql']) : $q->whereNotNull($column['sql']);

                return;
            }
            $sqlOp = self::COMPARISONS[$op];
            if ($negate) {
                $q->where(fn (Builder $w) => $w->whereNull($column['sql'])->orWhereNot($column['sql'], $sqlOp, $value));
            } else {
                $q->where($column['sql'], $sqlOp, $value);
            }

            return;
        }
        if ($k === 'call' && in_array($n['fn'], ['is_empty', 'is_null'], true) && count($n['args']) === 1) {
            $column = $this->column($rt, $n['args'][0], $table);
            $empty = static function (Builder $w) use ($column, $n): void {
                $w->whereNull($column['sql']);
                if ($n['fn'] === 'is_empty' && in_array($column['type'], ['string', 'code', 'text'], true)) {
                    $w->orWhere($column['sql'], '');
                }
            };
            $negate ? $q->whereNot($empty) : $q->where($empty);

            return;
        }
        if ($k === 'call' && $n['fn'] === 'in' && count($n['args']) === 2) {
            $column = $this->column($rt, $n['args'][0], $table);
            $list = $n['args'][1];
            if (($list['k'] ?? null) !== 'list' || $this->readsRecord($list)) {
                throw new InvalidArgumentException('in() needs a list of values');
            }
            $values = [];
            foreach ($list['items'] as $item) {
                $v = $this->constant($rt, $column['field'], $item, $ctx);
                if ($v !== null) {
                    $values[] = $v;
                }
            }
            $negate ? $q->whereNotIn($column['sql'], $values ?: [null]) : $q->whereIn($column['sql'], $values ?: [null]);

            return;
        }
        if ($k === 'call' && $n['fn'] === 'contains' && count($n['args']) === 2) {
            $column = $this->column($rt, $n['args'][0], $table);
            $term = $this->constant($rt, null, $n['args'][1], $ctx);
            $like = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], (string) $term).'%';
            $negate ? $q->where($column['sql'], 'not like', $like) : $q->where($column['sql'], 'like', $like);

            return;
        }
        if ($k === 'lit' && ($n['t'] ?? null) === 'boolean') {
            ($n['v'] xor $negate) ? $q->whereRaw('1 = 1') : $q->whereRaw('1 = 0');

            return;
        }
        throw new InvalidArgumentException('unsupported');
    }

    /**
     * @param  array<string, mixed>  $a
     * @param  array<string, mixed>  $b
     * @return array{0: array{sql: string, type: string, field: array<string, mixed>}, 1: array<string, mixed>, 2: string}
     */
    private function sides(FormRuntime $rt, array $a, array $b, string $op, string $table): array
    {
        if ($this->isRecordRef($a) && ! $this->readsRecord($b)) {
            return [$this->column($rt, $a, $table), $b, $op];
        }
        if ($this->isRecordRef($b) && ! $this->readsRecord($a)) {
            $flipped = ['<' => '>', '<=' => '>=', '>' => '<', '>=' => '<='][$op] ?? $op;

            return [$this->column($rt, $b, $table), $a, $flipped];
        }
        throw new InvalidArgumentException('a comparison needs one record field');
    }

    /**
     * @param  array<string, mixed>  $ref
     * @return array{sql: string, type: string, field: array<string, mixed>}
     */
    private function column(FormRuntime $rt, array $ref, string $table): array
    {
        if (! $this->isRecordRef($ref)) {
            throw new InvalidArgumentException('not a record field');
        }
        $uuid = $rt->keys[$ref['path'][0]] ?? null;
        $col = $uuid === null ? null : $rt->column($uuid);
        if ($col === null || ($col['encrypted'] ?? false) || isset($rt->fieldRepeater[$uuid])) {
            throw new InvalidArgumentException('field not usable');
        }

        return ['sql' => $table.'.'.$col['name'], 'type' => (string) $col['type'], 'field' => $rt->fields[$uuid]];
    }

    /**
     * The database value of a side that does not read the record.
     *
     * @param  array<string, mixed>|null  $field
     * @param  array<string, mixed>  $node
     */
    private function constant(FormRuntime $rt, ?array $field, array $node, Context $ctx): mixed
    {
        $v = Evaluator::evaluate($node, $ctx)->value;
        if ($v->type === 'null') {
            return null;
        }
        $data = $v->data;
        if ($field !== null && ($table = $rt->targetTable($field)) !== null && is_string($data)) {
            // References compare by uuid in expressions and by id in the table.
            return DB::table($table)->where('uuid', strtolower($data))->value('id') ?? -1;
        }
        if ($field !== null && ($rt->type($field)?->storage === 'user') && is_string($data)) {
            return DB::table('users')->where('uuid', strtolower($data))->value('id') ?? -1;
        }

        return match ($v->type) {
            'boolean' => $data ? 1 : 0,
            'number' => (string) $data,
            'date' => Civil::formatDate((int) $data),
            'datetime' => gmdate('Y-m-d H:i:s', (int) $data),
            'time' => gmdate('H:i:s', (int) $data),
            default => is_scalar($data) ? $data : (string) json_encode($data),
        };
    }

    private function context(FormRuntime $rt, User $user): Context
    {
        return Context::now(config('app.timezone', 'UTC'))->with(['mode' => 'view', 'form' => $rt->form->key, 'user' => $this->userContext->user($user)]);
    }

    /** @param  array<string, mixed>  $n */
    private function isRecordRef(array $n): bool
    {
        return ($n['k'] ?? null) === 'ref' && ($n['scope'] ?? null) === 'record' && count($n['path'] ?? []) === 1;
    }

    /** @param  array<string, mixed>  $n */
    private function readsRecord(array $n): bool
    {
        if (($n['k'] ?? null) === 'ref') {
            return in_array($n['scope'] ?? null, ['record', 'old', 'row', 'parent'], true);
        }
        foreach (['a', 'b'] as $k) {
            if (isset($n[$k]) && is_array($n[$k]) && $this->readsRecord($n[$k])) {
                return true;
            }
        }
        foreach ([...($n['args'] ?? []), ...($n['items'] ?? [])] as $c) {
            if (is_array($c) && $this->readsRecord($c)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Structural check used at save time.
     *
     * @param  array<string, mixed>  $n
     * @param  callable(string): void  $field
     */
    private static function walk(array $n, callable $field): void
    {
        $k = $n['k'] ?? null;
        $isRef = static fn (array $x): bool => ($x['k'] ?? null) === 'ref' && ($x['scope'] ?? null) === 'record' && count($x['path'] ?? []) === 1;
        $reads = static function (array $x) use (&$reads): bool {
            if (($x['k'] ?? null) === 'ref') {
                return in_array($x['scope'] ?? null, ['record', 'old', 'row', 'parent'], true);
            }
            foreach ([...array_filter([$x['a'] ?? null, $x['b'] ?? null]), ...($x['args'] ?? []), ...($x['items'] ?? [])] as $c) {
                if (is_array($c) && $reads($c)) {
                    return true;
                }
            }

            return false;
        };
        if ($k === 'bin' && in_array($n['op'], ['and', 'or'], true)) {
            self::walk($n['a'], $field);
            self::walk($n['b'], $field);

            return;
        }
        if ($k === 'un' && $n['op'] === 'not') {
            self::walk($n['a'], $field);

            return;
        }
        if ($k === 'bin' && isset(self::COMPARISONS[$n['op']])) {
            if ($isRef($n['a']) && ! $reads($n['b'])) {
                $field($n['a']['path'][0]);

                return;
            }
            if ($isRef($n['b']) && ! $reads($n['a'])) {
                $field($n['b']['path'][0]);

                return;
            }
        }
        if ($k === 'call' && in_array($n['fn'], ['is_empty', 'is_null', 'in', 'contains'], true) && isset($n['args'][0]) && $isRef($n['args'][0])) {
            $rest = array_slice($n['args'], 1);
            foreach ($rest as $r) {
                if ($reads($r)) {
                    throw new InvalidArgumentException(__('access.scope_condition_unsupported'));
                }
            }
            $field($n['args'][0]['path'][0]);

            return;
        }
        if ($k === 'lit' && ($n['t'] ?? null) === 'boolean') {
            return;
        }
        throw new InvalidArgumentException(__('access.scope_condition_unsupported'));
    }
}
