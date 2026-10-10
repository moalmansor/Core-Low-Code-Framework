<?php

declare(strict_types=1);

namespace App\Modules\Views;

use App\Infrastructure\Database\Contracts\DatabaseDriver;
use App\Modules\Access\AccessResolver;
use App\Modules\Core\I18n\Translator;
use App\Modules\Identity\Models\User;
use App\Modules\Records\Runtime\FormRuntime;
use App\Modules\Records\Runtime\References;
use App\Modules\Workflow\Runtime\WorkflowRuntime;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Table views at run time (specification §4.14, architecture §17): which
 * views a user may use (their `view.{uuid}.use` permissions, by priority,
 * else the default view), and for the chosen view its columns the user can
 * see, its filters (including linked forms' fields, every level limited to
 * the user's record scope), sorting by a path, the values of linked and
 * virtual columns, and the totals row.
 */
final class ViewRuntime
{
    public function __construct(
        private readonly ViewDocument $documents,
        private readonly RelationPaths $paths,
        private readonly AccessResolver $access,
        private readonly Translator $translator,
        private readonly DatabaseDriver $driver,
        private readonly References $refs,
    ) {}

    /**
     * The views the user may use, best first (the default view when no
     * other applies). Empty when the form has no views.
     *
     * @return list<array<string, mixed>>
     */
    public function available(FormRuntime $rt, User $user): array
    {
        $views = $this->documents->load($rt->form);
        $mine = array_values(array_filter($views, fn ($v) => $this->access->allows($user, 'view.'.$v['uuid'].'.use')));
        if ($mine === []) {
            $mine = array_values(array_filter($views, static fn ($v) => $v['default']));
        }
        usort($mine, static fn ($a, $b) => $a['priority'] <=> $b['priority']);

        return $mine;
    }

    /**
     * The chosen view (by uuid among the available ones, else the first).
     *
     * @return array<string, mixed>|null
     */
    public function pick(FormRuntime $rt, User $user, ?string $uuid): ?array
    {
        $available = $this->available($rt, $user);
        foreach ($available as $v) {
            if ($v['uuid'] === $uuid) {
                return $v;
            }
        }
        abort_if($uuid !== null && $available !== [], 403, __('views.not_allowed'));

        return $available[0] ?? null;
    }

    /**
     * The view as the client renders it: only columns and filters the user
     * can resolve (visible fields of forms they may view), labels in the
     * user's language.
     *
     * @param  array<string, mixed>  $view
     * @return array<string, mixed>
     */
    public function present(FormRuntime $rt, User $user, array $view): array
    {
        $columns = [];
        foreach ($view['columns'] as $c) {
            $label = $this->label($rt, $user, $c['path'], (array) $c['i18n']['label']);
            if ($label === null) {
                continue;
            }
            $columns[] = ['key' => implode('.', $c['path']), 'path' => $c['path'], 'label' => $label, 'width' => $c['width'], 'pinned' => $c['pinned'],
                'visible' => $c['visible'], 'sortable' => $c['sortable'] && $this->sortable($rt, $user, $c['path']), 'format' => $c['format'], 'aggregate' => $c['aggregate'],
                'linked' => count($c['path']) > 1 || str_starts_with($c['path'][0], '@')];
        }
        $filters = [];
        foreach ($view['filters'] as $f) {
            $label = $this->label($rt, $user, $f['path'], (array) $f['i18n']['label']);
            if ($label === null) {
                continue;
            }
            $filters[] = ['uuid' => $f['uuid'], 'path' => $f['path'], 'label' => $label, 'type' => $f['type'], 'operators' => $f['operators'], 'quick' => $f['quick'],
                'default' => $f['default'], 'options' => $this->filterOptions($rt, $user, $f)];
        }

        return [
            'uuid' => $view['uuid'], 'key' => $view['key'], 'name' => $this->pickLocale((array) $view['i18n']['name']) ?? $view['key'],
            'page_size' => $view['pageSize'], 'default_sort' => $view['defaultSort'], 'show_totals' => $view['showTotals'],
            'column_chooser' => $view['columnChooser'], 'global_search' => $view['globalSearch'], 'row_options' => $view['rowOptions'],
            'columns' => $columns, 'filters' => $filters,
        ];
    }

    /**
     * Applies the view's filters: `{filter uuid: {op, value|values|from|to}}`.
     * Unknown filters, and filters on paths the user cannot see, are ignored.
     *
     * @param  array<string, mixed>  $view
     * @param  array<string, mixed>  $input
     */
    public function filter(Builder $q, FormRuntime $rt, User $user, array $view, array $input): void
    {
        $byUuid = array_column($view['filters'], null, 'uuid');
        foreach ($input as $uuid => $cond) {
            $f = $byUuid[strtolower((string) $uuid)] ?? null;
            if ($f === null || ! is_array($cond) || ! in_array($cond['op'] ?? null, $f['operators'], true)) {
                continue;
            }
            $op = $cond['op'];
            $r = $this->paths->resolve($rt, $f['path'], $user);
            if ($r === null) {
                continue;
            }
            if ($r['system'] !== null) {
                $this->systemFilter($q, $rt, $r, $op, $cond, $rt->table);

                continue;
            }
            $this->paths->whereHas($q, $rt, $f['path'], $user, function (Builder $w, string $column, array $spec) use ($op, $cond, $r): void {
                $this->condition($w, $column, $spec, $op, $cond, $r['rt'], $r['field']);
            }, $rt->table);
        }
    }

    /**
     * Sorts by a path of the view (`{path, dir}`), falling back to the
     * view's default sort and then the newest records.
     *
     * @param  array<string, mixed>  $view
     * @param  array<string, mixed>|null  $sort
     */
    public function order(Builder $q, FormRuntime $rt, User $user, array $view, ?array $sort): void
    {
        $sorts = $sort !== null ? [$sort] : $view['defaultSort'];
        foreach ($sorts as $s) {
            $path = $s['path'] ?? null;
            $dir = ($s['dir'] ?? 'asc') === 'desc' ? 'desc' : 'asc';
            if (! is_array($path) || ! $this->inView($view, $path) || ! $this->sortable($rt, $user, $path)) {
                continue;
            }
            $r = $this->paths->resolve($rt, $path, $user);
            if ($r['system'] !== null && $r['hops'] === []) {
                $q->orderBy($rt->table.'.'.match ($r['system']) {
                    'status' => 'status_id', default => $r['system'],
                }, $dir);
            } elseif ($r['hops'] === []) {
                $q->orderBy($rt->table.'.'.$rt->column($r['field']['uuid'])['name'], $dir);
            } else {
                $sub = $this->paths->sortExpression($rt, $path, $user, $rt->table);
                if ($sub !== null) {
                    $q->orderBy($sub, $dir);
                }
            }
        }
        $q->orderBy($rt->table.'.updated_at', 'desc')->orderBy($rt->table.'.id', 'desc');
    }

    /**
     * Values of the view's linked and virtual columns for a page of records:
     * record id => column key => value.
     *
     * @param  array<string, mixed>  $presented  from present()
     * @param  list<int>  $ids
     * @return array<int, array<string, mixed>>
     */
    public function linkedValues(FormRuntime $rt, User $user, array $presented, array $ids): array
    {
        $out = [];
        foreach ($presented['columns'] as $c) {
            if (! $c['linked']) {
                continue;
            }
            if ($c['path'][0] === '@justification') {
                foreach ($this->justificationValues($rt, $ids, $c['path'][1]) as $id => $v) {
                    $out[$id][$c['key']] = $v;
                }

                continue;
            }
            foreach ($this->paths->values($rt, $c['path'], $ids, $user) as $id => $v) {
                $out[$id][$c['key']] = $v;
            }
        }

        return $out;
    }

    /**
     * The totals row over the whole filtered result (main-table columns).
     *
     * @param  array<string, mixed>  $presented
     * @return array<string, mixed>
     */
    public function totals(Builder $filtered, FormRuntime $rt, User $user, array $presented): array
    {
        if (! $presented['show_totals']) {
            return [];
        }
        $out = [];
        foreach ($presented['columns'] as $c) {
            if ($c['aggregate'] === 'none') {
                continue;
            }
            if ($c['aggregate'] === 'count') {
                $out[$c['key']] = (clone $filtered)->count();

                continue;
            }
            $r = $this->paths->resolve($rt, $c['path'], $user);
            $col = $r === null || $r['field'] === null ? null : $rt->column($r['field']['uuid']);
            if ($col === null || $r['hops'] !== []) {
                continue;
            }
            $value = (clone $filtered)->reorder()->{$c['aggregate']}($rt->table.'.'.$col['name']);
            $out[$c['key']] = $value === null ? null : (string) $value;
        }

        return $out;
    }

    /** @param  array<string, mixed>  $view @param  list<string>  $path */
    public function inView(array $view, array $path): bool
    {
        foreach ($view['columns'] as $c) {
            if ($c['path'] === $path) {
                return true;
            }
        }

        return false;
    }

    /** @param  list<string>  $path */
    private function sortable(FormRuntime $rt, User $user, array $path): bool
    {
        if ($path[0] === '@justification') {
            return false;
        }
        $r = $this->paths->resolve($rt, $path, $user);
        if ($r === null || $r['toMany']) {
            return false;
        }
        if ($r['system'] !== null) {
            return true;
        }
        $col = $r['rt']->column($r['field']['uuid']);

        return $col !== null && ! in_array($col['type'], ['json', 'text'], true);
    }

    /**
     * The column label, or null when the user cannot see the path.
     *
     * @param  list<string>  $path
     * @param  array<string, string>  $custom
     */
    private function label(FormRuntime $rt, User $user, array $path, array $custom): ?string
    {
        if ($path[0] === '@justification') {
            return $this->access->allows($user, 'system.view_justifications') ? ($this->pickLocale($custom) ?? __('views.justification_'.$path[1])) : null;
        }
        $r = $this->paths->resolve($rt, $path, $user);
        if ($r === null) {
            return null;
        }
        if (($c = $this->pickLocale($custom)) !== null) {
            return $c;
        }
        if ($r['system'] !== null) {
            return __('views.system_'.$r['system']);
        }
        $parts = [];
        foreach ($r['hops'] as $hop) {
            $parts[] = $this->pickLocale((array) ($hop['field']['i18n']['label'] ?? [])) ?? $hop['field']['key'];
        }
        $parts[] = $this->pickLocale((array) ($r['field']['i18n']['label'] ?? [])) ?? $r['field']['key'];

        return implode(' › ', $parts);
    }

    /**
     * Choices offered by option, status, user and reference filters.
     *
     * @param  array<string, mixed>  $f
     * @return list<array{value: string, label: string, color?: string|null}>|null
     */
    private function filterOptions(FormRuntime $rt, User $user, array $f): ?array
    {
        $r = $this->paths->resolve($rt, $f['path'], $user);
        if ($r === null) {
            return null;
        }
        if ($f['type'] === 'status') {
            $wf = WorkflowRuntime::for($r['rt']);

            return array_values(array_map(static fn ($s) => ['value' => $s['uuid'], 'label' => WorkflowRuntime::label($s), 'color' => $s['color']], $wf->statuses));
        }
        if ($f['type'] === 'options') {
            return array_values(array_map(fn ($o) => ['value' => (string) $o['value'], 'label' => $this->pickLocale((array) ($o['i18n']['label'] ?? [])) ?? (string) $o['value'], 'color' => $o['color'] ?? null], array_filter($r['field']['options']['static'] ?? [], static fn ($o) => $o['active'] ?? true)));
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $spec  final column spec
     * @param  array<string, mixed>  $cond
     * @param  array<string, mixed>  $field
     */
    private function condition(Builder $q, string $column, array $spec, string $op, array $cond, FormRuntime $rt, array $field): void
    {
        $text = in_array($spec['type'], ['string', 'code', 'text'], true);
        switch ($op) {
            case 'is_empty':
                $q->where(static function (Builder $w) use ($column, $text): void {
                    $w->whereNull($column);
                    if ($text) {
                        $w->orWhere($column, '');
                    }
                });
                break;
            case 'contains':
            case 'starts_with':
                $term = is_scalar($cond['value'] ?? null) ? (string) $cond['value'] : '';
                $term === '' ? null : $this->driver->caseInsensitiveLike($q, $column, $term, $op === 'starts_with' ? 'starts' : 'contains');
                break;
            case 'equals':
                $v = $cond['value'] ?? null;
                if (! is_scalar($v) || $v === '') {
                    break;
                }
                if ($text) {
                    $this->driver->caseInsensitiveLike($q, $column, (string) $v, 'equals');
                } else {
                    $q->where($column, $spec['type'] === 'bool' ? (int) filter_var($v, FILTER_VALIDATE_BOOLEAN) : $v);
                }
                break;
            case 'between':
                if (($cond['from'] ?? null) !== null && is_scalar($cond['from'])) {
                    $q->where($column, '>=', $cond['from']);
                }
                if (($cond['to'] ?? null) !== null && is_scalar($cond['to'])) {
                    $q->where($column, '<=', $field['type'] === 'date' || ! is_string($cond['to']) || strlen($cond['to']) !== 10 ? $cond['to'] : $cond['to'].' 23:59:59.999999');
                }
                break;
            case 'in':
                $values = array_values(array_filter(array_slice((array) ($cond['values'] ?? []), 0, 50), static fn ($v) => is_scalar($v) && $v !== ''));
                if ($values === []) {
                    break;
                }
                $table = $rt->targetTable($field);
                if ($table !== null) {
                    $uuids = array_values(array_filter(array_map('strval', $values), static fn ($v) => preg_match('/^[0-9a-f-]{36}$/i', $v) === 1));
                    $ids = array_values($this->refs->ids($table, array_map('strtolower', $uuids)));
                    $q->whereIn($column, $ids ?: [0]);
                } else {
                    $q->whereIn($column, array_map('strval', $values));
                }
                break;
        }
    }

    /**
     * @param  array<string, mixed>  $r
     * @param  array<string, mixed>  $cond
     */
    private function systemFilter(Builder $q, FormRuntime $rt, array $r, string $op, array $cond, string $table): void
    {
        if ($r['hops'] !== []) {
            return; // linked forms' system values are shown, not filtered
        }
        if ($r['system'] === 'status') {
            $wf = WorkflowRuntime::for($rt);
            if ($op === 'is_empty') {
                $q->whereNull($table.'.status_id');
            } elseif ($op === 'in') {
                $ids = array_values(array_filter(array_map(static fn ($u) => is_string($u) ? ($wf->statuses[strtolower($u)]['id'] ?? null) : null, (array) ($cond['values'] ?? []))));
                $q->whereIn($table.'.status_id', $ids ?: [0]);
            }

            return;
        }
        $column = $table.'.'.$r['system'];
        $this->condition($q, $column, ['type' => $r['system'] === 'record_number' ? 'string' : 'datetime'], $op, $cond, $rt, ['type' => 'datetime', 'options' => null]);
    }

    /**
     * Latest justification per record for the virtual columns.
     *
     * @param  list<int>  $ids
     * @return array<int, string|null>
     */
    private function justificationValues(FormRuntime $rt, array $ids, string $which): array
    {
        if ($ids === []) {
            return [];
        }
        $latest = DB::table('justifications')->where('form_id', $rt->form->id)->whereIn('record_id', $ids)->groupBy('record_id')->select(['record_id', DB::raw('max(id) as id')])->pluck('id', 'record_id')->all();
        $rows = DB::table('justifications')->whereIn('id', array_values($latest) ?: [0])->get()->keyBy('id');
        $names = DB::table('users')->whereIn('id', $rows->pluck('user_id')->filter())->pluck('name', 'id');
        $out = [];
        foreach ($latest as $recordId => $id) {
            $j = $rows[$id] ?? null;
            $out[(int) $recordId] = $j === null ? null : match ($which) {
                'last_reason' => $j->reason_text,
                'last_code' => $j->reason_code_label_snapshot,
                'last_at' => Carbon::parse($j->created_at, 'UTC')->toIso8601ZuluString(),
                default => $names[$j->user_id] ?? null,
            };
        }

        return $out;
    }

    /** @param  array<string, string|null>  $values */
    private function pickLocale(array $values): ?string
    {
        return $this->translator->pick($values);
    }
}
