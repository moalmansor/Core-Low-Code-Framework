<?php

declare(strict_types=1);

namespace App\Modules\Views;

use App\Modules\Access\AccessResolver;
use App\Modules\Access\FieldAccessResolver;
use App\Modules\Access\RecordScope;
use App\Modules\Core\I18n\Translator;
use App\Modules\Identity\Models\User;
use App\Modules\Records\Runtime\FormRuntime;
use App\Modules\Records\Runtime\FormRuntimes;
use App\Modules\Records\Runtime\RecordPresenter;
use App\Modules\Records\Runtime\RecordStore;
use App\Modules\Records\Runtime\References;
use App\Modules\Workflow\Runtime\WorkflowRuntime;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Relation paths (architecture §17): `["employee", "department", "name"]`
 * reads a field of a linked form through reference fields; a final
 * `@status`, `@record_number`, `@created_at` or `@updated_at` reads a system
 * value. Each hop must be a
 * reference to another form the user may view; the last element is a stored
 * field the user can see in that form. Values are loaded in batches per hop
 * (to-many hops through the pivot table), filters become nested `IN`
 * sub-queries limited to the user's record scope at every level, and sorting
 * on a to-one path uses a correlated sub-select.
 */
final class RelationPaths
{
    public const MAX_DEPTH = 4;

    public function __construct(
        private readonly FormRuntimes $runtimes,
        private readonly AccessResolver $access,
        private readonly FieldAccessResolver $fieldAccess,
        private readonly RecordScope $scope,
        private readonly RecordStore $store,
        private readonly References $refs,
    ) {}

    /**
     * Resolves a path for the user, or null when any part is unknown, hidden
     * or not permitted. Without a user only the structure is checked (admin
     * configuration).
     *
     * @param  list<string>  $path
     * @return array{hops: list<array{rt: FormRuntime, field: array<string, mixed>, multi: bool, target: FormRuntime}>, rt: FormRuntime, field: array<string, mixed>|null, system: string|null, toMany: bool}|null
     */
    public function resolve(FormRuntime $rt, array $path, ?User $user = null): ?array
    {
        if ($path === [] || count($path) > self::MAX_DEPTH) {
            return null;
        }
        $hops = [];
        $current = $rt;
        $toMany = false;
        foreach ($path as $i => $key) {
            $last = $i === count($path) - 1;
            // System values are addressed with an "@" so they never collide with field keys.
            if ($last && in_array($key, ['@record_number', '@created_at', '@updated_at', '@status'], true)) {
                return ['hops' => $hops, 'rt' => $current, 'field' => null, 'system' => substr($key, 1), 'toMany' => $toMany];
            }
            $uuid = $current->keys[$key] ?? null;
            $f = $uuid === null ? null : $current->fields[$uuid];
            if ($f === null || ! $current->isStored($f) || ($f['flags']['encrypted'] ?? false)) {
                return null;
            }
            if ($user !== null && ! $this->visible($current, $user, $uuid)) {
                return null;
            }
            if ($last) {
                return ['hops' => $hops, 'rt' => $current, 'field' => $f, 'system' => null, 'toMany' => $toMany];
            }
            $targetUuid = $current->targetFormUuid($f);
            $target = $targetUuid === null ? null : $this->runtimes->forUuid($targetUuid);
            if ($target === null || ($user !== null && ! $this->access->allows($user, "form.{$target->form->uuid}.view") && $target->form->kind !== 'collection')) {
                return null;
            }
            $multi = $current->isMultiReference($f);
            $toMany = $toMany || $multi;
            $hops[] = ['rt' => $current, 'field' => $f, 'multi' => $multi, 'target' => $target];
            $current = $target;
        }

        return null;
    }

    /**
     * Display values of a path for many records of the source form: record
     * id => value (a list for to-many paths). Targets outside the user's
     * scope read as empty.
     *
     * @param  list<string>  $path
     * @param  list<int>  $ids  source record ids
     * @return array<int, mixed>
     */
    public function values(FormRuntime $rt, array $path, array $ids, User $user): array
    {
        $r = $this->resolve($rt, $path, $user);
        if ($r === null || $ids === []) {
            return [];
        }
        // map: source id => list of current-level record ids
        $map = [];
        foreach ($ids as $id) {
            $map[$id] = [$id];
        }
        foreach ($r['hops'] as $hop) {
            $level = array_values(array_unique(array_merge([], ...array_values($map))));
            $links = $this->links($hop, $level);
            $targets = array_values(array_unique(array_merge([], ...array_values($links))));
            $visible = $targets === [] ? [] : array_flip($this->scope->apply(DB::table($hop['target']->table)->whereIn('id', $targets)->whereNull('deleted_at'), $hop['target'], $user, 'view')->pluck('id')->map(static fn ($v) => (int) $v)->all());
            foreach ($map as $source => $currentIds) {
                $next = [];
                foreach ($currentIds as $c) {
                    foreach ($links[$c] ?? [] as $t) {
                        if (isset($visible[$t])) {
                            $next[] = $t;
                        }
                    }
                }
                $map[$source] = $next;
            }
        }
        $final = array_values(array_unique(array_merge([], ...array_values($map))));
        $display = $this->display($r, $final, $user);
        $out = [];
        foreach ($map as $source => $finalIds) {
            $vals = array_values(array_filter(array_map(static fn ($id) => $display[$id] ?? null, $finalIds), static fn ($v) => $v !== null && $v !== ''));
            $out[$source] = $r['toMany'] ? $vals : ($vals[0] ?? null);
        }

        return $out;
    }

    /**
     * Restricts a query of the source form to records whose path value
     * satisfies the callback's condition on the final column.
     *
     * @param  list<string>  $path
     * @param  callable(Builder, string, array<string, mixed>): void  $condition  receives the query, the final column and its spec
     */
    public function whereHas(Builder $q, FormRuntime $rt, array $path, User $user, callable $condition, string $sourceTable): bool
    {
        $r = $this->resolve($rt, $path, $user);
        if ($r === null || $r['field'] === null) {
            return false;
        }
        $col = $r['rt']->column($r['field']['uuid']);
        if ($col === null) {
            return false;
        }
        $build = function (int $i) use (&$build, $r, $user, $condition, $col): Builder {
            $hop = $r['hops'][$i];
            $target = $hop['target'];
            $sub = DB::table($target->table)->select('id')->whereNull('deleted_at');
            $this->scope->apply($sub, $target, $user, 'view');
            if ($i === count($r['hops']) - 1) {
                $condition($sub, $target->table.'.'.$col['name'], $col);
            } else {
                $this->linkIn($sub, $r['hops'][$i + 1], $build($i + 1), $target->table);
            }

            return $sub;
        };
        if ($r['hops'] === []) {
            $condition($q, $sourceTable.'.'.$col['name'], $col);

            return true;
        }
        $this->linkIn($q, $r['hops'][0], $build(0), $sourceTable);

        return true;
    }

    /**
     * A correlated sub-select of the final column for sorting (to-one paths only).
     *
     * @param  list<string>  $path
     */
    public function sortExpression(FormRuntime $rt, array $path, User $user, string $sourceTable): ?Builder
    {
        $r = $this->resolve($rt, $path, $user);
        if ($r === null || $r['toMany'] || $r['field'] === null || $r['hops'] === []) {
            return null;
        }
        $col = $r['rt']->column($r['field']['uuid']);
        if ($col === null) {
            return null;
        }
        // Innermost level first: each level selects the next one's value and
        // is correlated to its parent's foreign key (the source table at the top).
        $sub = null;
        for ($i = count($r['hops']) - 1; $i >= 0; $i--) {
            $hop = $r['hops'][$i];
            $fk = $hop['rt']->column($hop['field']['uuid']);
            if ($fk === null) {
                return null;
            }
            $a = 'lcf_s'.$i;
            $select = $sub === null ? DB::table($hop['target']->table.' as '.$a)->select($a.'.'.$col['name']) : DB::table($hop['target']->table.' as '.$a)->selectSub($sub, 'v');
            $sub = $select->limit(1);
            $parent = $i === 0 ? $sourceTable : 'lcf_s'.($i - 1);
            $sub->whereColumn($a.'.id', $parent.'.'.$fk['name']);
        }

        return $sub;
    }

    /**
     * Ids reached through one hop: current id => target ids.
     *
     * @param  array{rt: FormRuntime, field: array<string, mixed>, multi: bool, target: FormRuntime}  $hop
     * @param  list<int>  $ids
     * @return array<int, list<int>>
     */
    private function links(array $hop, array $ids): array
    {
        $out = [];
        if ($ids === []) {
            return $out;
        }
        if ($hop['multi']) {
            $pivot = $hop['rt']->pivots[$hop['field']['relation']] ?? null;
            if ($pivot === null) {
                return [];
            }
            foreach (array_chunk($ids, 1000) as $chunk) {
                foreach (DB::table($pivot)->whereIn('source_id', $chunk)->orderBy('sort_order')->get(['source_id', 'target_id']) as $p) {
                    $out[(int) $p->source_id][] = (int) $p->target_id;
                }
            }

            return $out;
        }
        $col = $hop['rt']->column($hop['field']['uuid']);
        if ($col === null) {
            return [];
        }
        foreach (array_chunk($ids, 1000) as $chunk) {
            foreach (DB::table($hop['rt']->table)->whereIn('id', $chunk)->whereNotNull($col['name'])->get(['id', $col['name'].' as fk']) as $row) {
                $out[(int) $row->id][] = (int) $row->fk;
            }
        }

        return $out;
    }

    /**
     * `source.fk IN (sub)` for a to-one hop, `source.id IN (pivot.source_id …)` for to-many.
     *
     * @param  array{rt: FormRuntime, field: array<string, mixed>, multi: bool, target: FormRuntime}  $hop
     */
    private function linkIn(Builder $q, array $hop, Builder $targets, string $sourceTable): void
    {
        if ($hop['multi']) {
            $pivot = $hop['rt']->pivots[$hop['field']['relation']] ?? null;
            $pivot === null ? $q->whereRaw('1 = 0') : $q->whereIn($sourceTable.'.id', DB::table($pivot)->select('source_id')->whereIn('target_id', $targets));

            return;
        }
        $col = $hop['rt']->column($hop['field']['uuid']);
        $col === null ? $q->whereRaw('1 = 0') : $q->whereIn($sourceTable.'.'.$col['name'], $targets);
    }

    /**
     * Display text of the final field (or system value) of the given records.
     *
     * @param  array<string, mixed>  $r
     * @param  list<int>  $ids
     * @return array<int, mixed>
     */
    private function display(array $r, array $ids, User $user): array
    {
        if ($ids === []) {
            return [];
        }
        /** @var FormRuntime $rt */
        $rt = $r['rt'];
        $rows = DB::table($rt->table)->whereIn('id', $ids)->get()->map(static fn ($x) => (array) $x)->all();
        $out = [];
        if ($r['system'] !== null) {
            $wf = WorkflowRuntime::for($rt);
            foreach ($rows as $row) {
                $out[(int) $row['id']] = match ($r['system']) {
                    'status' => RecordPresenter::status($wf, isset($row['status_id']) ? (int) $row['status_id'] : null)['name'] ?? null,
                    default => $row[$r['system']] ?? null,
                };
            }

            return $out;
        }
        $f = $r['field'];
        $records = $this->store->hydrate($rt, $rows, false);
        $refs = [];
        if ($rt->targetTable($f) !== null) {
            $uuids = [];
            foreach ($records as $rec) {
                $uuids = [...$uuids, ...array_filter((array) ($rec['values'][$f['key']] ?? []), 'is_string')];
            }
            $refs = $this->refs->titles($rt, $f, array_values(array_unique($uuids)));
        }
        $labels = [];
        foreach ($f['options']['static'] ?? [] as $o) {
            $labels[$o['value']] = app(Translator::class)->pick($o['i18n']['label'] ?? null) ?? (string) $o['value'];
        }
        foreach ($records as $rec) {
            $v = $rec['values'][$f['key']] ?? null;
            if ($refs !== []) {
                $v = is_array($v) ? implode(', ', array_map(static fn ($u) => $refs[$u] ?? $u, $v)) : ($v === null ? null : ($refs[$v] ?? $v));
            } elseif ($labels !== [] && $v !== null) {
                $v = is_array($v) ? implode(', ', array_map(static fn ($x) => $labels[$x] ?? $x, $v)) : ($labels[$v] ?? $v);
            }
            $out[$rec['id']] = $v;
        }

        return $out;
    }

    /** Whether a field of a form is visible to the user (view mode). */
    private function visible(FormRuntime $rt, User $user, string $fieldUuid): bool
    {
        $levels = $this->fieldAccess->resolve($user, $rt->form->id, $rt->form->uuid, $rt->definition, 'view');

        return ($levels['fields'][$fieldUuid] ?? 'hidden') !== 'hidden';
    }
}
