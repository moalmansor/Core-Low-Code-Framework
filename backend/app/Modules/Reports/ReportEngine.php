<?php

declare(strict_types=1);

namespace App\Modules\Reports;

use App\Modules\Access\AccessCache;
use App\Modules\Core\I18n\Translator;
use App\Modules\Core\Settings\SettingsService;
use App\Modules\Downloads\DownloadEngine;
use App\Modules\Downloads\DownloadPaths;
use App\Modules\Identity\Models\User;
use App\Modules\Records\Exchange\ExportService;
use App\Modules\Records\Exchange\SheetCodec;
use App\Modules\Records\Runtime\FormRuntime;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Runs a report for a user (specification §4.18, ADR-0053).
 *
 * The base records are the user's own: their record scope, the report's
 * fixed filters, and nothing beyond the `reports.max_records` setting
 * (a report that stops there says so). Every path is resolved for the user:
 * a dimension or measure they may not see makes the report unavailable to
 * them rather than quietly wrong; a detail column they may not see is left
 * out and named. Sensitive values they may only see masked are not grouped
 * or added up.
 *
 * Grouping and measures are computed page by page from the same path
 * values downloads use, so a to-many path counts each related value once
 * per record, and totals are taken over records, not over groups.
 */
final class ReportEngine
{
    private const PAGE = 1000;

    public function __construct(
        private readonly DownloadPaths $paths,
        private readonly DownloadEngine $downloads,
        private readonly ReportDefinitions $definitions,
        private readonly SettingsService $settings,
        private readonly Translator $translator,
        private readonly AccessCache $access,
    ) {}

    /**
     * @param  array<string, mixed>  $definition  dimensions, measures, columns, filters, sort, limit
     * @param  string|null  $cacheKey  report identity and version; null runs without the cache
     * @return array<string, mixed>
     */
    public function run(FormRuntime $rt, array $definition, User $user, ?string $cacheKey = null, int $cacheSeconds = 0): array
    {
        if ($cacheKey !== null && $cacheSeconds > 0) {
            $key = 'report:'.$cacheKey.':'.$user->id.':'.$this->access->epoch().':'.app()->getLocale();

            return Cache::remember($key, $cacheSeconds, fn () => $this->compute($rt, $definition, $user));
        }

        return $this->compute($rt, $definition, $user);
    }

    /**
     * @param  array<string, mixed>  $d
     * @return array<string, mixed>
     */
    private function compute(FormRuntime $rt, array $d, User $user): array
    {
        $max = (int) $this->settings->get('reports', 'max_records');
        $q = app(ExportService::class)->plan($rt, $user, [])['query'];
        $this->downloads->filter($q, $rt, array_map(static fn ($f) => $f + ['parameter' => null, 'valueExpression' => null], (array) ($d['filters'] ?? [])), $user);
        $dims = [];
        foreach ((array) ($d['dimensions'] ?? []) as $x) {
            $r = $this->paths->resolve($rt, (array) $x['path'], $user);
            $label = $this->label($rt, $x['i18n']['label'] ?? [], (array) $x['path']);
            if ($r === null || ($r['field'] !== null && $this->paths->masked($r['field'], $user))) {
                throw ValidationException::withMessages(['report' => __('reports.not_visible', ['name' => $label])]);
            }
            $dims[] = ['uuid' => $x['uuid'], 'r' => $r, 'bucket' => $x['bucket'] ?? null, 'label' => $label, 'date' => $this->definitions->isDate($r),
                'datetime' => $r['system'] !== null || $r['rt']->type($r['field'])?->storage === 'datetime',
                'multi' => $r['field'] !== null && ($r['rt']->type($r['field'])?->storage === 'multi_choice' || $r['rt']->isMultiReference($r['field']))];
        }
        $measures = [];
        foreach ((array) ($d['measures'] ?? []) as $x) {
            $r = $x['path'] === null ? null : $this->paths->resolve($rt, (array) $x['path'], $user);
            $label = $this->measureLabel($rt, $x);
            if ($x['path'] !== null && ($r === null || ($r['field'] !== null && $this->paths->masked($r['field'], $user)))) {
                throw ValidationException::withMessages(['report' => __('reports.not_visible', ['name' => $label])]);
            }
            $measures[] = ['uuid' => $x['uuid'], 'r' => $r, 'aggregate' => $x['aggregate'], 'label' => $label,
                'type' => $r !== null && $x['aggregate'] !== 'count' && $this->definitions->isDate($r) ? 'date' : 'number'];
        }
        $base = [
            'records' => 0, 'truncated' => false, 'omitted' => [],
            'generatedAt' => Carbon::now('UTC')->toIso8601ZuluString(), 'form' => $this->paths->formName($rt),
        ];
        if ($dims === [] && $measures === []) {
            return $base + $this->detail($rt, $d, $user, $q, $max);
        }

        $groups = [];
        $totals = array_map(static fn () => self::acc(), $measures);
        $records = 0;
        $last = 0;
        $truncated = false;
        while (true) {
            $ids = (clone $q)->where($rt->table.'.id', '>', $last)->reorder()->orderBy($rt->table.'.id')->limit(self::PAGE)->pluck($rt->table.'.id')->map(static fn ($v) => (int) $v)->all();
            if ($ids === []) {
                break;
            }
            if ($records + count($ids) > $max) {
                $ids = array_slice($ids, 0, $max - $records);
                $truncated = true;
            }
            $last = (int) end($ids);
            $records += count($ids);
            $dimValues = array_map(fn (array $dim) => $this->dimensionKeys($dim, $ids, $user), $dims);
            $measureValues = array_map(fn (array $m) => $m['r'] === null ? null : $this->measureValues($m, $ids, $user), $measures);
            foreach ($ids as $id) {
                $combos = [[]];
                foreach ($dimValues as $values) {
                    $next = [];
                    foreach ($combos as $c) {
                        foreach ($values[$id] ?? [[null, null]] as $kv) {
                            $next[] = [...$c, $kv];
                        }
                    }
                    $combos = $next;
                }
                foreach ($combos as $combo) {
                    $gk = json_encode(array_column($combo, 0));
                    $groups[$gk] ??= ['keys' => array_column($combo, 0), 'labels' => array_column($combo, 1), 'acc' => array_map(static fn () => self::acc(), $measures)];
                    foreach ($measures as $i => $m) {
                        self::add($groups[$gk]['acc'][$i], $m, $measureValues[$i][$id] ?? null);
                    }
                }
                foreach ($measures as $i => $m) {
                    self::add($totals[$i], $m, $measureValues[$i][$id] ?? null);
                }
            }
            if ($truncated || count($ids) < self::PAGE) {
                break;
            }
        }
        $rows = [];
        foreach ($groups as $g) {
            $rows[] = ['keys' => $g['keys'], 'labels' => $g['labels'], 'values' => array_map(static fn ($acc, $m) => self::result($acc, $m), $g['acc'], $measures)];
        }
        $rows = $this->sortGroups($rows, $d, $dims, $measures);
        $limit = ($d['limit'] ?? null) === null ? null : (int) $d['limit'];
        if ($limit !== null) {
            if (count($dims) >= 2) {
                $keep = array_flip(array_slice(array_values(array_unique(array_map(static fn ($r) => json_encode($r['keys'][0]), $rows))), 0, $limit));
                $rows = array_values(array_filter($rows, static fn ($r) => isset($keep[json_encode($r['keys'][0])])));
            } else {
                $rows = array_slice($rows, 0, $limit);
            }
        }

        return [
            'mode' => 'grouped', 'records' => $records, 'truncated' => $truncated,
            'dimensions' => array_map(static fn ($x) => ['uuid' => $x['uuid'], 'label' => $x['label'], 'bucket' => $x['bucket'], 'type' => $x['date'] && $x['bucket'] === null ? 'date' : 'text'], $dims),
            'measures' => array_map(static fn ($m) => ['uuid' => $m['uuid'], 'label' => $m['label'], 'aggregate' => $m['aggregate'], 'type' => $m['type']], $measures),
            'rows' => $rows, 'totals' => array_map(static fn ($acc, $m) => self::result($acc, $m), $totals, $measures),
        ] + $base;
    }

    /**
     * A list of records (no grouping): the detail columns, sorted, up to the limit.
     *
     * @param  array<string, mixed>  $d
     * @return array<string, mixed>
     */
    private function detail(FormRuntime $rt, array $d, User $user, mixed $q, int $max): array
    {
        $columns = [];
        $omitted = [];
        foreach ((array) ($d['columns'] ?? []) as $c) {
            $r = $this->paths->resolve($rt, (array) $c['path'], $user);
            $label = $this->label($rt, $c['i18n']['header'] ?? [], (array) $c['path']);
            if ($r === null) {
                $omitted[] = $label;

                continue;
            }
            $type = $r['field'] !== null && $this->definitions->isNumeric($r) ? 'number' : ($this->definitions->isDate($r) ? 'date' : 'text');
            $columns[] = ['uuid' => $c['uuid'], 'r' => $r, 'label' => $label, 'type' => $type];
        }
        $limit = min((int) ($d['limit'] ?? 1000), $max);
        $sort = $d['sort'] ?? null;
        $sortIndex = $sort !== null && $sort['by'] === 'column' ? array_search($sort['ref'], array_column($columns, 'uuid'), true) : false;
        $rows = [];
        $records = 0;
        $last = 0;
        $truncated = false;
        while (true) {
            $ids = (clone $q)->where($rt->table.'.id', '>', $last)->reorder()->orderBy($rt->table.'.id')->limit(self::PAGE)->pluck($rt->table.'.id')->map(static fn ($v) => (int) $v)->all();
            if ($ids === []) {
                break;
            }
            $last = (int) end($ids);
            if ($sortIndex === false && count($rows) + count($ids) > $limit) {
                $ids = array_slice($ids, 0, $limit - count($rows));
                $truncated = true;
            }
            if ($records + count($ids) > $max) {
                $ids = array_slice($ids, 0, $max - $records);
                $truncated = true;
            }
            $records += count($ids);
            $values = [];
            foreach ($columns as $i => $c) {
                $reach = $this->paths->reach($c['r']['hops'], $ids, $user);
                $cells = $this->paths->cells($c['r'], array_merge([], ...array_values($reach)), $user);
                foreach ($ids as $id) {
                    $list = array_values(array_filter(array_map(static fn ($f) => $cells[$f] ?? null, $reach[$id] ?? []), static fn ($v) => $v !== null && $v !== ''));
                    $values[$i][$id] = $c['r']['toMany'] ? ($list === [] ? null : implode(', ', array_map(static fn ($v) => is_bool($v) ? ($v ? __('reports.yes') : __('reports.no')) : (string) $v, $list))) : ($list[0] ?? null);
                }
            }
            foreach ($ids as $id) {
                $rows[] = array_map(static fn ($i) => self::cell($values[$i][$id] ?? null), array_keys($columns));
            }
            if ($truncated || count($ids) < self::PAGE) {
                break;
            }
        }
        if ($sortIndex !== false) {
            $dir = $sort['direction'] === 'desc' ? -1 : 1;
            usort($rows, static fn ($a, $b) => $dir * self::compare($a[$sortIndex], $b[$sortIndex]));
            if (count($rows) > $limit) {
                $rows = array_slice($rows, 0, $limit);
                $truncated = true;
            }
        }

        return [
            'mode' => 'detail', 'records' => $records, 'truncated' => $truncated, 'omitted' => $omitted,
            'columns' => array_map(static fn ($c) => ['uuid' => $c['uuid'], 'label' => $c['label'], 'type' => $c['type']], $columns), 'rows' => $rows,
        ];
    }

    /**
     * Group keys of each record for one dimension: [key, label] pairs (several for a to-many path).
     *
     * @param  array<string, mixed>  $dim
     * @param  list<int>  $ids
     * @return array<int, list<array{0: mixed, 1: string}>>
     */
    private function dimensionKeys(array $dim, array $ids, User $user): array
    {
        $r = $dim['r'];
        $reach = $this->paths->reach($r['hops'], $ids, $user);
        $finals = array_merge([], ...array_values($reach));
        $tz = (string) $this->settings->get('formats', 'timezone');
        if ($dim['bucket'] !== null) {
            $raw = $this->paths->rawValues($r, $finals, $user);
            $keys = [];
            foreach ($raw as $f => $v) {
                $keys[$f] = self::bucket($v, (string) $dim['bucket'], $dim['datetime'] ? $tz : null);
            }
            $labels = $keys;
        } else {
            $labels = $this->paths->cells($r, $finals, $user);
            $keys = $labels;
        }
        $out = [];
        foreach ($ids as $id) {
            $pairs = [];
            foreach ($reach[$id] ?? [] as $f) {
                $k = $keys[$f] ?? null;
                if ($k === null || $k === '') {
                    continue;
                }
                foreach ($dim['multi'] ? array_values(array_filter(explode(SheetCodec::LIST_SEPARATOR, (string) $k), static fn ($x) => $x !== '')) : [$k] as $part) {
                    $pairs[json_encode($part)] = [$part, is_bool($part) ? (string) __($part ? 'reports.yes' : 'reports.no') : (string) $part];
                }
            }
            $out[$id] = $pairs === [] ? [[null, (string) __('reports.empty')]] : array_values($pairs);
        }

        return $out;
    }

    /**
     * The values a measure reads for each record (a list for a to-many path).
     *
     * @param  array<string, mixed>  $m
     * @param  list<int>  $ids
     * @return array<int, list<mixed>>
     */
    private function measureValues(array $m, array $ids, User $user): array
    {
        $r = $m['r'];
        $reach = $this->paths->reach($r['hops'], $ids, $user);
        $raw = $this->paths->rawValues($r, array_merge([], ...array_values($reach)), $user);
        $out = [];
        foreach ($ids as $id) {
            $list = [];
            foreach ($reach[$id] ?? [] as $f) {
                $v = $raw[$f] ?? null;
                if ($v === null || $v === '' || $v === []) {
                    continue;
                }
                if ($m['aggregate'] === 'count') {
                    $list[] = 1;
                } elseif ($m['type'] === 'date') {
                    $list[] = is_string($v) ? $v : null;
                } else {
                    $list[] = self::number($v);
                }
            }
            $out[$id] = array_values(array_filter($list, static fn ($v) => $v !== null));
        }

        return $out;
    }

    /** @return array{n: int, records: int, sum: float, min: mixed, max: mixed} */
    private static function acc(): array
    {
        return ['n' => 0, 'records' => 0, 'sum' => 0.0, 'min' => null, 'max' => null];
    }

    /**
     * @param  array{n: int, records: int, sum: float, min: mixed, max: mixed}  $acc
     * @param  array<string, mixed>  $m
     * @param  list<mixed>|null  $values  null for "count of records"
     */
    private static function add(array &$acc, array $m, ?array $values): void
    {
        $acc['records']++;
        foreach ($values ?? [] as $v) {
            $acc['n']++;
            if (is_float($v) || is_int($v)) {
                $acc['sum'] += $v;
            }
            $acc['min'] = $acc['min'] === null || self::compare($v, $acc['min']) < 0 ? $v : $acc['min'];
            $acc['max'] = $acc['max'] === null || self::compare($v, $acc['max']) > 0 ? $v : $acc['max'];
        }
    }

    /**
     * @param  array{n: int, records: int, sum: float, min: mixed, max: mixed}  $acc
     * @param  array<string, mixed>  $m
     */
    private static function result(array $acc, array $m): int|float|string|null
    {
        return match ($m['aggregate']) {
            'count' => $m['r'] === null ? $acc['records'] : $acc['n'],
            'sum' => round($acc['sum'], 6),
            'avg' => $acc['n'] === 0 ? null : round($acc['sum'] / $acc['n'], 6),
            'min' => $acc['min'],
            'max' => $acc['max'],
            default => null,
        };
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @param  array<string, mixed>  $d
     * @param  list<array<string, mixed>>  $dims
     * @param  list<array<string, mixed>>  $measures
     * @return list<array<string, mixed>>
     */
    private function sortGroups(array $rows, array $d, array $dims, array $measures): array
    {
        $sort = $d['sort'] ?? null;
        $dir = ($sort['direction'] ?? 'asc') === 'desc' ? -1 : 1;
        $measureIndex = $sort !== null && $sort['by'] === 'measure' ? array_search($sort['ref'], array_column($measures, 'uuid'), true) : false;
        $dimIndex = $sort !== null && $sort['by'] === 'dimension' ? array_search($sort['ref'], array_column($dims, 'uuid'), true) : false;
        usort($rows, static function ($a, $b) use ($dir, $measureIndex, $dimIndex, $dims): int {
            if ($measureIndex !== false) {
                $c = self::compare($a['values'][$measureIndex], $b['values'][$measureIndex]);
                if ($c !== 0) {
                    return $dir * $c;
                }
            }
            $first = $dimIndex === false ? 0 : (int) $dimIndex;
            $order = [$first, ...array_values(array_diff(array_keys($dims), [$first]))];
            foreach ($order as $i) {
                // Empty values go last whatever the direction.
                if (($a['keys'][$i] === null) !== ($b['keys'][$i] === null)) {
                    return $a['keys'][$i] === null ? 1 : -1;
                }
                $c = self::compare($a['keys'][$i], $b['keys'][$i]);
                if ($c !== 0) {
                    return ($i === $first && $measureIndex === false ? $dir : 1) * $c;
                }
            }

            return 0;
        });

        return $rows;
    }

    private static function compare(mixed $a, mixed $b): int
    {
        if ($a === null || $b === null) {
            return ($a === null ? 1 : 0) - ($b === null ? 1 : 0);
        }
        if (is_numeric($a) && is_numeric($b)) {
            return (float) $a <=> (float) $b;
        }

        return strnatcasecmp((string) (is_bool($a) ? (int) $a : $a), (string) (is_bool($b) ? (int) $b : $b));
    }

    /** A date (or date and time, read in the organization's time zone) as its bucket key. */
    private static function bucket(mixed $v, string $bucket, ?string $tz): ?string
    {
        if (! is_string($v) || $v === '') {
            return null;
        }
        try {
            $at = $tz === null || strlen($v) <= 10 ? Carbon::parse(substr($v, 0, 10)) : Carbon::parse($v, 'UTC')->setTimezone($tz);
        } catch (Throwable) {
            return null;
        }

        return match ($bucket) {
            'day' => $at->format('Y-m-d'),
            'week' => $at->format('o-\WW'),
            'month' => $at->format('Y-m'),
            'quarter' => $at->format('Y').'-Q'.$at->quarter,
            default => $at->format('Y'),
        };
    }

    private static function number(mixed $v): ?float
    {
        if (is_array($v)) {
            $v = $v['amount'] ?? null;
        }

        return is_numeric($v) ? (float) $v : null;
    }

    private static function cell(mixed $v): int|float|string|null
    {
        return match (true) {
            $v === null => null,
            is_bool($v) => (string) __($v ? 'reports.yes' : 'reports.no'),
            is_int($v), is_float($v) => $v,
            default => (string) $v,
        };
    }

    /** @param  array<string, string>|object  $i18n */
    private function label(FormRuntime $rt, array|object $i18n, array $path): string
    {
        return $this->translator->pick((array) $i18n) ?? $this->pathLabel($rt, $path);
    }

    /** A path's readable name without the report's own form in front: `Employee › Department`. */
    private function pathLabel(FormRuntime $rt, array $path): string
    {
        $full = $this->paths->label($rt, $path);
        $prefix = $this->paths->formName($rt).' › ';

        return str_starts_with($full, $prefix) ? substr($full, strlen($prefix)) : $full;
    }

    /** @param  array<string, mixed>  $m */
    private function measureLabel(FormRuntime $rt, array $m): string
    {
        $own = $this->translator->pick((array) ($m['i18n']['label'] ?? []));
        if ($own !== null) {
            return $own;
        }
        if ($m['path'] === null) {
            return (string) __('reports.agg_records');
        }

        return (string) __('reports.agg.'.$m['aggregate'], ['name' => $this->pathLabel($rt, (array) $m['path'])]);
    }
}
