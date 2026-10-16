<?php

declare(strict_types=1);

namespace App\Modules\Downloads;

use App\Expressions\Calendars\Civil;
use App\Expressions\Values\Value;
use App\Modules\Core\I18n\Translator;
use App\Modules\Core\Settings\SettingsService;
use App\Modules\Identity\Models\User;
use App\Modules\Records\Exchange\ExportService;
use App\Modules\Records\Runtime\FormRuntime;
use App\Modules\Records\Runtime\RecordStore;
use App\Modules\Records\Runtime\RuleRuntime;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Plans and reads a download (architecture §19.8): which records (the
 * user's scope, optionally their current table filters or selection, the
 * profile's fixed filters and the parameters they filled), which columns
 * (only those whose every level the user may see), and the rows: one per
 * record, repeated per related record for a "one row per related record"
 * path, with aggregates in one cell, and separate sheets per related form.
 * Values are read in batches per hop, so no query runs per row.
 */
final class DownloadEngine
{
    public const PREVIEW_ROWS = 20;

    private const PAGE = 500;

    public function __construct(
        private readonly DownloadPaths $paths,
        private readonly RecordStore $store,
        private readonly RuleRuntime $rules,
        private readonly Translator $translator,
        private readonly SettingsService $settings,
    ) {}

    /**
     * The columns of a profile a user (or, for the builder's preview, a
     * role) receives; the others are listed as omitted.
     *
     * @param  array<string, mixed>  $profile  as DownloadProfiles::load() returns it
     * @return array{columns: list<array<string, mixed>>, omitted: list<string>}
     */
    public function columns(FormRuntime $rt, array $profile, ?User $user, ?int $roleId = null): array
    {
        $paths = $roleId === null ? $this->paths : $this->paths->forRole($roleId);
        $out = [];
        $omitted = [];
        foreach ($profile['columns'] as $c) {
            $header = $this->translator->pick((array) $c['i18n']['header']);
            $col = $c + ['r' => null];
            if (in_array($c['kind'], ['field', 'system'], true)) {
                $r = $paths->resolve($rt, (array) $c['path'], $user);
                $header ??= $this->defaultHeader($rt, (array) $c['path']);
                if ($r === null) {
                    $omitted[] = $header;

                    continue;
                }
                $col['r'] = $r;
            }
            if ($c['kind'] === 'calculated' && ($user !== null || $roleId !== null)) {
                // A formula must not reveal a field the user cannot see: every record field it reads must be visible.
                foreach (self::references((array) $c['expression']) as $path) {
                    if ($paths->resolve($rt, $path, $user) === null) {
                        $omitted[] = $header ?? '';

                        continue 2;
                    }
                }
            }
            $col['header'] = $header ?? '';
            $out[] = $col;
        }

        return ['columns' => $out, 'omitted' => $omitted];
    }

    /**
     * The record paths an expression reads.
     *
     * @param  array<mixed>  $ast
     * @return list<list<string>>
     */
    public static function references(array $ast): array
    {
        $out = [];
        if (($ast['k'] ?? null) === 'ref' && ($ast['scope'] ?? null) === 'record' && is_array($ast['path'] ?? null)) {
            $out[] = array_values(array_map('strval', $ast['path']));
        }
        foreach ($ast as $v) {
            if (is_array($v)) {
                $out = [...$out, ...self::references($v)];
            }
        }

        return $out;
    }

    /**
     * What a download contains, for a user, right now.
     *
     * @param  array<string, mixed>  $profile
     * @param  array{filters?: array<string, mixed>|null, uuids?: list<string>|null, record?: string|null, parameters?: array<string, mixed>}  $input
     * @return array{query: Builder, columns: list<array<string, mixed>>, omitted: list<string>, sheets: array<string, array<string, mixed>>, branch: array<string, mixed>|null, parameters: array<string, mixed>, max: int}
     */
    public function plan(FormRuntime $rt, array $profile, User $user, array $input): array
    {
        $parameters = $this->parameters($profile, (array) ($input['parameters'] ?? []));
        $filters = [];
        if (($input['record'] ?? null) !== null) {
            $filters['uuids'] = [strtolower((string) $input['record'])];
        } else {
            if ($profile['applyUserFilters'] && is_array($input['filters'] ?? null)) {
                $filters = array_intersect_key($input['filters'], array_flip(['search', 'filter', 'view', 'vf', 'sort', 'direction']));
            }
            if ($profile['allowSelected'] && ($input['uuids'] ?? []) !== []) {
                $filters['uuids'] = array_values(array_map('strtolower', (array) $input['uuids']));
            }
        }
        // The user's scope (and their table filters when the profile follows them).
        $q = app(ExportService::class)->plan($rt, $user, $filters)['query'];
        $this->applyFilters($q, $rt, $profile, $user, $parameters);
        $cols = $this->columns($rt, $profile, $user);
        $main = [];
        $sheets = [];
        $branch = null;
        foreach ($cols['columns'] as $c) {
            $r = $c['r'];
            if ($r !== null && $r['toMany'] && $c['toMany'] === 'separate_sheet') {
                $key = (string) $c['sheet'];
                $sheets[$key] ??= ['key' => $key, 'hops' => array_slice($r['hops'], 0, (int) $r['branch'] + 1), 'columns' => [], 'title' => $this->sheetTitle($r)];
                $sheets[$key]['columns'][] = $c;

                continue;
            }
            if ($r !== null && $r['toMany'] && $c['toMany'] === 'flatten') {
                $branch ??= ['hops' => array_slice($r['hops'], 0, (int) $r['branch'] + 1)];
            }
            $main[] = $c;
        }
        $max = (int) $this->settings->get('downloads', 'max_rows');
        if ($profile['maxRows'] > 0) {
            $max = min($max, (int) $profile['maxRows']);
        }

        return ['query' => $q, 'columns' => $main, 'omitted' => $cols['omitted'], 'sheets' => $sheets, 'branch' => $branch, 'parameters' => $parameters, 'max' => $max];
    }

    /**
     * Reads the rows of the main sheet and of each separate sheet, page by
     * page; the callback receives each page (sheet key '' is the main
     * sheet). Stops at the row limit; returns the number of main rows and
     * whether the limit cut it short.
     *
     * @param  array<string, mixed>  $plan
     * @param  callable(string, list<list<mixed>>): bool  $page  return false to stop (cancelled)
     * @return array{rows: int, truncated: bool}
     */
    public function rows(FormRuntime $rt, array $plan, User $user, callable $page, ?int $limit = null): array
    {
        $limit = min($limit ?? PHP_INT_MAX, $plan['max']);
        $written = 0;
        $truncated = false;
        $last = 0;
        while (true) {
            $ids = (clone $plan['query'])->where($rt->table.'.id', '>', $last)->reorder()->orderBy($rt->table.'.id')->limit(self::PAGE)->pluck($rt->table.'.id')->map(static fn ($v) => (int) $v)->all();
            if ($ids === []) {
                break;
            }
            $last = (int) end($ids);
            [$rows, $sheetRows] = $this->page($rt, $plan, $user, $ids);
            if ($written + count($rows) > $limit) {
                $rows = array_slice($rows, 0, $limit - $written);
                $truncated = true;
            }
            $written += count($rows);
            if (! $page('', $rows)) {
                break;
            }
            foreach ($sheetRows as $key => $sr) {
                $page($key, $sr);
            }
            if ($truncated || count($ids) < self::PAGE) {
                break;
            }
        }

        return ['rows' => $written, 'truncated' => $truncated];
    }

    /**
     * Rows of one page of base records.
     *
     * @param  array<string, mixed>  $plan
     * @param  list<int>  $ids
     * @return array{0: list<list<mixed>>, 1: array<string, list<list<mixed>>>}
     */
    private function page(FormRuntime $rt, array $plan, User $user, array $ids): array
    {
        $records = [];
        $needValues = array_filter($plan['columns'], static fn ($c) => $c['kind'] === 'calculated') !== [];
        if ($needValues) {
            $rows = DB::table($rt->table)->whereIn('id', $ids)->get()->map(static fn ($x) => (array) $x)->all();
            foreach ($this->store->hydrate($rt, $rows, true) as $rec) {
                $records[(int) $rec['id']] = $rec['values'];
            }
        }
        // The related records each row repeats for, when a column asks for one row per related record.
        $branchMap = $plan['branch'] === null ? null : $this->paths->reach($plan['branch']['hops'], $ids, $user);
        $branchDepth = $plan['branch'] === null ? 0 : count($plan['branch']['hops']);
        $values = [];
        foreach ($plan['columns'] as $i => $c) {
            $values[$i] = $this->columnValues($c, $ids, $user, $branchMap, $branchDepth, $rt, $records);
        }
        $out = [];
        foreach ($ids as $id) {
            $items = $branchMap === null ? [null] : (($branchMap[$id] ?? []) === [] ? [null] : $branchMap[$id]);
            foreach ($items as $item) {
                $row = [];
                foreach ($plan['columns'] as $i => $c) {
                    $v = $values[$i];
                    $row[] = $c['format'] === null ? $v['base'][$id] ?? ($item === null ? null : ($v['branch'][$item] ?? null)) : self::formatted(
                        $v['base'][$id] ?? ($item === null ? null : ($v['branch'][$item] ?? null)), $c['format']);
                }
                $out[] = $row;
            }
        }
        $sheets = [];
        foreach ($plan['sheets'] as $key => $sheet) {
            $map = $this->paths->reach($sheet['hops'], $ids, $user);
            $depth = count($sheet['hops']);
            $items = array_values(array_unique(array_merge([], ...array_values($map))));
            $cols = [];
            foreach ($sheet['columns'] as $i => $c) {
                $sub = array_slice($c['r']['hops'], $depth);
                $reach = $this->paths->reach($sub, $items, $user);
                $cells = $this->paths->cells($c['r'], array_merge([], ...array_values($reach)), $user);
                foreach ($reach as $item => $finals) {
                    $cols[$i][$item] = self::join(array_map(static fn ($f) => $cells[$f] ?? null, $finals), ', ');
                }
            }
            $numbers = $this->recordNumbers($rt, $ids);
            foreach ($ids as $id) {
                foreach ($map[$id] ?? [] as $item) {
                    $row = [$numbers[$id] ?? null];
                    foreach ($sheet['columns'] as $i => $c) {
                        $v = $cols[$i][$item] ?? null;
                        $row[] = $c['format'] === null ? $v : self::formatted($v, $c['format']);
                    }
                    $sheets[$key][] = $row;
                }
            }
        }

        return [$out, $sheets];
    }

    /**
     * One column's values for a page: by base record id ('base') or, for a
     * column under the repeated branch, by related record id ('branch').
     *
     * @param  array<string, mixed>  $c
     * @param  list<int>  $ids
     * @param  array<int, list<int>>|null  $branchMap
     * @param  array<int, array<string, mixed>>  $records
     * @return array{base: array<int, mixed>, branch: array<int, mixed>}
     */
    private function columnValues(array $c, array $ids, User $user, ?array $branchMap, int $branchDepth, FormRuntime $rt, array $records): array
    {
        $base = [];
        $branch = [];
        if ($c['kind'] === 'static') {
            foreach ($ids as $id) {
                $base[$id] = $c['staticValue'];
            }

            return ['base' => $base, 'branch' => []];
        }
        if ($c['kind'] === 'calculated') {
            foreach ($ids as $id) {
                $base[$id] = self::scalar($this->rules->evaluate($rt, (array) $c['expression'], $records[$id] ?? [], $user, 'view'));
            }

            return ['base' => $base, 'branch' => []];
        }
        $r = $c['r'];
        if ($r['toMany'] && $c['toMany'] === 'flatten' && $branchMap !== null) {
            $items = array_values(array_unique(array_merge([], ...array_values($branchMap))));
            $reach = $this->paths->reach(array_slice($r['hops'], $branchDepth), $items, $user);
            $cells = $this->paths->cells($r, array_merge([], ...array_values($reach)), $user);
            foreach ($reach as $item => $finals) {
                $branch[$item] = self::join(array_map(static fn ($f) => $cells[$f] ?? null, $finals), ', ');
            }

            return ['base' => [], 'branch' => $branch];
        }
        $reach = $this->paths->reach($r['hops'], $ids, $user);
        $cells = $this->paths->cells($r, array_merge([], ...array_values($reach)), $user);
        foreach ($ids as $id) {
            $list = array_map(static fn ($f) => $cells[$f] ?? null, $reach[$id] ?? []);
            $base[$id] = $r['toMany'] ? self::aggregate($list, (string) ($c['aggregate'] ?? 'join'), (string) ($c['separator'] ?? ', ')) : ($list[0] ?? null);
        }

        return ['base' => $base, 'branch' => []];
    }

    /**
     * Header row of each sheet.
     *
     * @param  array<string, mixed>  $plan
     * @return array<string, list<string>>
     */
    public function headers(FormRuntime $rt, array $plan): array
    {
        $out = ['' => array_map(static fn ($c) => (string) $c['header'], $plan['columns'])];
        foreach ($plan['sheets'] as $key => $sheet) {
            $out[$key] = [__('downloads.system.record_number').' ('.$this->paths->formName($rt).')', ...array_map(static fn ($c) => (string) $c['header'], $sheet['columns'])];
        }

        return $out;
    }

    /**
     * The first rows, as the user will receive them (the builder's live preview).
     *
     * @param  array<string, mixed>  $profile
     * @param  array<string, mixed>  $input
     * @return array{headers: array<string, list<string>>, rows: array<string, list<list<mixed>>>, omitted: list<string>, sheets: array<string, string>}
     */
    public function preview(FormRuntime $rt, array $profile, User $user, array $input): array
    {
        $plan = $this->plan($rt, $profile, $user, $input);
        $rows = ['' => []];
        $this->rows($rt, $plan, $user, function (string $sheet, array $page) use (&$rows): bool {
            $rows[$sheet] = array_slice([...($rows[$sheet] ?? []), ...$page], 0, self::PREVIEW_ROWS);

            return count($rows['']) < self::PREVIEW_ROWS;
        }, self::PREVIEW_ROWS);

        return [
            'headers' => $this->headers($rt, $plan), 'rows' => $rows, 'omitted' => $plan['omitted'],
            'sheets' => array_map(static fn ($s) => (string) $s['title'], $plan['sheets']),
        ];
    }

    /**
     * Parameter values checked against their definitions: required ones
     * present, types respected, defaults applied.
     *
     * @param  array<string, mixed>  $profile
     * @param  array<string, mixed>  $given
     * @return array<string, mixed>
     */
    public function parameters(array $profile, array $given): array
    {
        $out = [];
        $errors = [];
        foreach ($profile['parameters'] as $p) {
            $k = $p['key'];
            $v = $given[$k] ?? $p['default'] ?? null;
            if ($v === '' || $v === []) {
                $v = null;
            }
            if ($v === null) {
                if ($p['required']) {
                    $errors["parameters.{$k}"][] = __('downloads.parameter_required', ['name' => $this->translator->pick((array) $p['i18n']['label']) ?? $k]);
                }
                $out[$k] = null;

                continue;
            }
            $ok = match ($p['type']) {
                'number' => is_numeric($v),
                'date' => is_string($v) && Civil::parseDate($v) !== null,
                'date_range' => is_array($v) && (($v['from'] ?? null) === null || Civil::parseDate((string) $v['from']) !== null) && (($v['to'] ?? null) === null || Civil::parseDate((string) $v['to']) !== null),
                'choice' => is_string($v) && in_array($v, array_column($p['options'], 'value'), true),
                'user' => is_string($v) && DB::table('users')->where('uuid', strtolower($v))->exists(),
                'department' => is_string($v) && DB::table('departments')->where('uuid', strtolower($v))->exists(),
                default => is_scalar($v) && mb_strlen((string) $v) <= 255,
            };
            if (! $ok) {
                $errors["parameters.{$k}"][] = __('downloads.parameter_invalid', ['name' => $this->translator->pick((array) $p['i18n']['label']) ?? $k]);
            }
            $out[$k] = $v;
        }
        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $profile
     * @param  array<string, mixed>  $parameters
     */
    private function applyFilters(Builder $q, FormRuntime $rt, array $profile, User $user, array $parameters): void
    {
        foreach ($profile['filters'] as $f) {
            $op = (string) $f['operator'];
            if ($f['parameter'] !== null) {
                $value = $parameters[$f['parameter']] ?? null;
                if ($value === null) {
                    continue; // an optional parameter left empty does not filter
                }
                if (is_array($value) && array_key_exists('from', $value)) {
                    $value = [$value['from'] ?? null, $value['to'] ?? null];
                    $op = 'between';
                }
            } elseif ($f['valueExpression'] !== null) {
                $value = self::scalar($this->rules->evaluate($rt, (array) $f['valueExpression'], [], $user, 'view'));
            } else {
                $value = $f['value'];
            }
            $applied = $this->paths->where($q, $rt, (array) $f['path'], $user, function (Builder $sub, string $column, array $spec, array $field) use ($op, $value, $rt): void {
                self::condition($sub, $column, $op, $this->comparable($rt, $field, $value));
            }, $rt->table);
            if (! $applied) {
                // A filter the user cannot apply (a field hidden from them) must not widen what they get.
                $q->whereRaw('1 = 0');
            }
        }
    }

    /** Reference values are compared by row id; other values as given. */
    private function comparable(FormRuntime $rt, array $field, mixed $value): mixed
    {
        $table = $rt->targetTable($field);
        if ($table === null || $value === null) {
            return $value;
        }
        $uuids = array_values(array_filter(array_map(static fn ($v) => is_string($v) && Str::isUuid($v) ? strtolower($v) : null, (array) $value)));
        $ids = $uuids === [] ? [] : DB::table($table)->whereIn('uuid', $uuids)->pluck('id')->map(static fn ($v) => (int) $v)->all();

        return is_array($value) ? $ids : ($ids[0] ?? -1);
    }

    private static function condition(Builder $q, string $column, string $op, mixed $value): void
    {
        $like = static fn (string $v): string => str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $v);
        match ($op) {
            'eq' => is_array($value) ? $q->whereIn($column, $value) : $q->where($column, '=', $value),
            'neq' => is_array($value) ? $q->whereNotIn($column, $value) : $q->where(static fn ($w) => $w->where($column, '!=', $value)->orWhereNull($column)),
            'gt' => $q->where($column, '>', $value),
            'gte' => $q->where($column, '>=', $value),
            'lt' => $q->where($column, '<', $value),
            'lte' => $q->where($column, '<=', $value),
            'contains' => $q->where($column, 'like', '%'.$like((string) $value).'%'),
            'starts_with' => $q->where($column, 'like', $like((string) $value).'%'),
            'in' => $q->whereIn($column, array_values((array) $value)),
            'between' => (static function () use ($q, $column, $value): void {
                [$from, $to] = array_pad(array_values((array) $value), 2, null);
                if ($from !== null) {
                    $q->where($column, '>=', $from);
                }
                if ($to !== null) {
                    $q->where($column, '<=', $to);
                }
            })(),
            'empty' => $q->whereNull($column),
            'not_empty' => $q->whereNotNull($column),
            default => $q->whereRaw('1 = 0'),
        };
    }

    /**
     * @param  list<mixed>  $values
     */
    public static function aggregate(array $values, string $fn, string $separator): mixed
    {
        $present = array_values(array_filter($values, static fn ($v) => $v !== null && $v !== ''));
        $numbers = array_map('floatval', array_values(array_filter($present, 'is_numeric')));

        return match ($fn) {
            'count' => count($present),
            'sum' => array_sum($numbers),
            'avg' => $numbers === [] ? null : round(array_sum($numbers) / count($numbers), 6),
            'min' => $numbers === [] ? null : min($numbers),
            'max' => $numbers === [] ? null : max($numbers),
            'first' => $present[0] ?? null,
            'last' => $present === [] ? null : $present[count($present) - 1],
            default => self::join($present, $separator),
        };
    }

    /** @param  list<mixed>  $values */
    private static function join(array $values, string $separator): mixed
    {
        $present = array_values(array_filter($values, static fn ($v) => $v !== null && $v !== ''));
        if (count($present) <= 1) {
            return $present[0] ?? null;
        }

        return implode($separator, array_map(static fn ($v) => is_bool($v) ? ($v ? '✓' : '') : (string) $v, $present));
    }

    /** A value of the expression language as a cell. */
    public static function scalar(Value $v): bool|int|float|string|null
    {
        return match ($v->type) {
            Value::NULL => null,
            Value::BOOLEAN => (bool) $v->data,
            Value::NUMBER, Value::DURATION => is_numeric($s = $v->data->toString()) ? (str_contains($s, '.') ? (float) $s : (int) $s) : $s,
            Value::TEXT => (string) $v->data,
            Value::DATE => Civil::formatDate($v->data),
            Value::DATETIME => Civil::formatDatetime($v->data),
            Value::TIME => Civil::formatTime($v->data),
            Value::LIST => implode(', ', array_map(static fn ($x) => (string) self::scalar($x), $v->items())),
            default => null,
        };
    }

    /**
     * A display format applied to a cell: dates and numbers written the
     * profile's way, Eastern Arabic digits when asked.
     *
     * @param  array<string, mixed>  $format
     */
    public static function formatted(mixed $value, array $format): mixed
    {
        if ($value === null || $value === '') {
            return $value;
        }
        $out = match ($format['type']) {
            'date' => self::date((string) $value, 'Y-m-d'),
            'datetime' => self::date((string) $value, 'Y-m-d H:i'),
            'number' => is_numeric($value) ? number_format((float) $value, (int) ($format['decimals'] ?? 0), '.', ',') : $value,
            'currency' => is_numeric($value) ? number_format((float) $value, (int) ($format['decimals'] ?? 2), '.', ',').' '.$format['currency'] : $value,
            'percent' => is_numeric($value) ? number_format((float) $value, (int) ($format['decimals'] ?? 0), '.', ',').'%' : $value,
            default => is_bool($value) ? ($value ? '✓' : '') : (string) $value,
        };
        if (($format['digits'] ?? 'latin') === 'arabic' && is_string($out)) {
            $out = strtr($out, ['0' => '٠', '1' => '١', '2' => '٢', '3' => '٣', '4' => '٤', '5' => '٥', '6' => '٦', '7' => '٧', '8' => '٨', '9' => '٩']);
        }

        return $out;
    }

    private static function date(string $value, string $pattern): string
    {
        try {
            return Carbon::parse($value)->setTimezone(config('app.timezone', 'UTC'))->format($pattern);
        } catch (\Throwable) {
            return $value;
        }
    }

    /**
     * @param  list<string>  $path
     */
    private function defaultHeader(FormRuntime $rt, array $path): string
    {
        $parts = explode(' › ', $this->paths->label($rt, $path));
        array_shift($parts); // the base form's name

        return implode(' › ', $parts);
    }

    /** @param  array<string, mixed>  $r */
    private function sheetTitle(array $r): string
    {
        $hop = $r['hops'][(int) $r['branch']];

        return $hop['kind'] === 'repeater'
            ? $this->translator->labelOf($hop['rt']->repeaters[$hop['repeater']]['group']['i18n']['label'] ?? [], (string) $hop['rt']->repeaters[$hop['repeater']]['group']['key'])
            : $this->paths->formName($hop['target']);
    }

    /**
     * @param  list<int>  $ids
     * @return array<int, string>
     */
    private function recordNumbers(FormRuntime $rt, array $ids): array
    {
        return DB::table($rt->table)->whereIn('id', $ids)->get(['id', 'uuid', 'record_number'])
            ->mapWithKeys(static fn ($r) => [(int) $r->id => (string) (($r->record_number ?? null) ?: $r->uuid)])->all();
    }
}
