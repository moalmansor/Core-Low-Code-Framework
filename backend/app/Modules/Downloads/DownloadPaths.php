<?php

declare(strict_types=1);

namespace App\Modules\Downloads;

use App\Modules\Access\AccessResolver;
use App\Modules\Access\FieldAccessResolver;
use App\Modules\Access\RecordScope;
use App\Modules\Core\I18n\Translator;
use App\Modules\Core\Settings\SettingsService;
use App\Modules\Identity\Models\User;
use App\Modules\Records\Exchange\SheetCodec;
use App\Modules\Records\Runtime\FormRuntime;
use App\Modules\Records\Runtime\FormRuntimes;
use App\Modules\Records\Runtime\RecordPresenter;
use App\Modules\Records\Runtime\RecordStore;
use App\Modules\Records\Runtime\References;
use App\Modules\Workflow\Runtime\WorkflowRuntime;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Relation paths of a download profile (specification §4.23, architecture
 * §17, §19.8), in both directions and at any depth up to the
 * `downloads.max_depth` setting. A path is a list of steps:
 *
 * - `"customer"`: a reference field of the current form (to one record, or
 *   to many through a multi-reference);
 * - `"~{form uuid}.{field key}"`: the records of another form whose
 *   reference field points at the current record (child records);
 * - `"#{repeater key}"`: the rows of a repeating section (last hop only);
 * - the last step is a field key, or `@record_number`, `@status`,
 *   `@created_at`, `@created_by`, `@updated_at`, `@updated_by`,
 *   `@last_transition_at`.
 *
 * For a user, every hop must be a form they may view and a field they can
 * see, and every level is limited to the records within their record scope.
 * Without a user only the structure is checked (the builder).
 */
final class DownloadPaths
{
    public const SYSTEM = ['record_number', 'status', 'created_at', 'created_by', 'updated_at', 'updated_by', 'last_transition_at'];

    public const MASK = '••••••';

    /** @var array<string, list<array{rt: FormRuntime, field: array<string, mixed>}>> form uuid => fields of other forms that reference it */
    private array $incoming = [];

    /** @var array<string, array<string, string>> */
    private array $levels = [];

    /** Set on a copy that checks visibility for a role instead of a user (the builder's preview). */
    private ?int $roleId = null;

    public function __construct(
        private readonly FormRuntimes $runtimes,
        private readonly AccessResolver $access,
        private readonly FieldAccessResolver $fieldAccess,
        private readonly RecordScope $scope,
        private readonly RecordStore $store,
        private readonly References $refs,
        private readonly SheetCodec $codec,
        private readonly SettingsService $settings,
        private readonly Translator $translator,
    ) {}

    /** A copy that resolves paths as members of a role would see them (field access only). */
    public function forRole(int $roleId): self
    {
        $copy = clone $this;
        $copy->roleId = $roleId;
        $copy->levels = [];

        return $copy;
    }

    public function maxDepth(): int
    {
        return max(1, (int) $this->settings->get('downloads', 'max_depth'));
    }

    /**
     * @param  list<string>  $path
     * @return array{hops: list<array{kind: string, rt: FormRuntime, field: array<string, mixed>|null, target: FormRuntime, repeater: string|null}>, rt: FormRuntime, repeater: string|null, field: array<string, mixed>|null, system: string|null, toMany: bool, branch: int|null}|null
     */
    public function resolve(FormRuntime $rt, array $path, ?User $user = null): ?array
    {
        if ($path === [] || count($path) - 1 > $this->maxDepth()) {
            return null;
        }
        $hops = [];
        $current = $rt;
        $repeater = null;
        $branch = null;
        foreach (array_values($path) as $i => $step) {
            $step = (string) $step;
            $last = $i === count($path) - 1;
            if ($last) {
                if (str_starts_with($step, '@')) {
                    $system = substr($step, 1);
                    if ($repeater !== null || ! in_array($system, self::SYSTEM, true)) {
                        return null;
                    }

                    return ['hops' => $hops, 'rt' => $current, 'repeater' => null, 'field' => null, 'system' => $system, 'toMany' => $branch !== null, 'branch' => $branch];
                }
                $f = $this->fieldOf($current, $repeater, $step);
                if ($f === null || ! $this->codec->exportable($current, $f) || ($this->checking($user) && ! $this->visible($current, $user, $f['uuid']))) {
                    return null;
                }

                return ['hops' => $hops, 'rt' => $current, 'repeater' => $repeater, 'field' => $f, 'system' => null, 'toMany' => $branch !== null, 'branch' => $branch];
            }
            if ($repeater !== null) {
                return null; // a repeating section is the last hop
            }
            if (str_starts_with($step, '#')) {
                $uuid = $current->repeaterKeys[substr($step, 1)] ?? null;
                if ($uuid === null || ($this->checking($user) && ! $this->visibleGroup($current, $user, $uuid))) {
                    return null;
                }
                $hops[] = ['kind' => 'repeater', 'rt' => $current, 'field' => null, 'target' => $current, 'repeater' => $uuid];
                $repeater = $uuid;
                $branch ??= count($hops) - 1;

                continue;
            }
            if (str_starts_with($step, '~')) {
                [$formUuid, $key] = array_pad(explode('.', substr($step, 1), 2), 2, '');
                $source = $this->runtimes->forUuid(strtolower($formUuid));
                $f = $source === null ? null : $this->fieldOf($source, null, $key);
                if ($source === null || $f === null || $source->targetFormUuid($f) !== $current->form->uuid || ! $this->formVisible($source, $user)
                    || ($this->checking($user) && ! $this->visible($source, $user, $f['uuid']))) {
                    return null;
                }
                $hops[] = ['kind' => 'reverse', 'rt' => $current, 'field' => $f, 'target' => $source, 'repeater' => null];
                $branch ??= count($hops) - 1;
                $current = $source;

                continue;
            }
            $f = $this->fieldOf($current, null, $step);
            $targetUuid = $f === null ? null : $current->targetFormUuid($f);
            $target = $targetUuid === null ? null : $this->runtimes->forUuid($targetUuid);
            if ($f === null || $target === null || ! $this->formVisible($target, $user) || ($this->checking($user) && ! $this->visible($current, $user, $f['uuid']))) {
                return null;
            }
            $multi = $current->isMultiReference($f);
            $hops[] = ['kind' => $multi ? 'multi' : 'ref', 'rt' => $current, 'field' => $f, 'target' => $target, 'repeater' => null];
            if ($multi) {
                $branch ??= count($hops) - 1;
            }
            $current = $target;
        }

        return null;
    }

    /**
     * Items reached through some hops from records of the first hop's form:
     * source id => ids at the last level (record ids, or repeater row ids).
     * For a user, records outside their scope at any level are dropped.
     *
     * @param  list<array<string, mixed>>  $hops
     * @param  list<int>  $ids
     * @return array<int, list<int>>
     */
    public function reach(array $hops, array $ids, ?User $user): array
    {
        $map = [];
        foreach ($ids as $id) {
            $map[$id] = [$id];
        }
        foreach ($hops as $hop) {
            $level = array_values(array_unique(array_merge([], ...array_values($map))));
            $links = $this->links($hop, $level);
            $targets = array_values(array_unique(array_merge([], ...array_values($links))));
            $allowed = array_flip($this->allowed($hop, $targets, $user));
            foreach ($map as $source => $current) {
                $next = [];
                foreach ($current as $c) {
                    foreach ($links[$c] ?? [] as $t) {
                        if (isset($allowed[$t])) {
                            $next[] = $t;
                        }
                    }
                }
                $map[$source] = $next;
            }
        }

        return $map;
    }

    /**
     * Cell values of the path's last step for items at its last level.
     *
     * @param  array<string, mixed>  $r  a resolved path
     * @param  list<int>  $ids
     * @return array<int, bool|int|float|string|null>
     */
    public function cells(array $r, array $ids, ?User $user): array
    {
        $ids = array_values(array_unique($ids));
        if ($ids === []) {
            return [];
        }
        /** @var FormRuntime $rt */
        $rt = $r['rt'];
        if ($r['system'] !== null) {
            return $this->systemCells($rt, (string) $r['system'], $ids);
        }
        $f = $r['field'];
        $values = $r['repeater'] === null ? $this->recordValues($rt, $ids) : $this->rowValues($rt, (string) $r['repeater'], $ids);
        $masked = $this->masked($f, $user);
        $refs = [];
        $files = [];
        $raw = [];
        foreach ($values as $v) {
            $x = $v[$f['key']] ?? null;
            if ($x !== null) {
                $raw = [...$raw, ...array_values(array_filter((array) $x, 'is_string'))];
            }
        }
        if (! $masked && $raw !== []) {
            $storage = $rt->type($f)?->storage;
            if (in_array($storage, ['file', 'files'], true)) {
                $files = DB::table('files')->whereIn('uuid', array_values(array_unique($raw)))->pluck('original_name', 'uuid')
                    ->mapWithKeys(static fn ($n, $u) => [strtolower((string) $u) => (string) $n])->all();
            } elseif ($rt->targetTable($f) !== null) {
                $refs = $this->refs->titles($rt, $f, array_values(array_unique($raw)));
            }
        }
        $out = [];
        foreach ($values as $id => $v) {
            $x = $v[$f['key']] ?? null;
            $out[$id] = $masked ? ($x === null || $x === [] ? null : self::MASK) : $this->codec->toCell($rt, $f, $x, $refs, $files);
        }

        return $out;
    }

    /**
     * Stored values of the path's last step for items at its last level (reports
     * group and add these up); a value the user may only see masked is null.
     * System values are as in a cell.
     *
     * @param  array<string, mixed>  $r  a resolved path
     * @param  list<int>  $ids
     * @return array<int, mixed>
     */
    public function rawValues(array $r, array $ids, ?User $user): array
    {
        $ids = array_values(array_unique($ids));
        if ($ids === []) {
            return [];
        }
        /** @var FormRuntime $rt */
        $rt = $r['rt'];
        if ($r['system'] !== null) {
            return $this->systemCells($rt, (string) $r['system'], $ids);
        }
        if ($this->masked($r['field'], $user)) {
            return array_fill_keys($ids, null);
        }
        $values = $r['repeater'] === null ? $this->recordValues($rt, $ids) : $this->rowValues($rt, (string) $r['repeater'], $ids);
        $out = [];
        foreach ($ids as $id) {
            $out[$id] = $values[$id][$r['field']['key']] ?? null;
        }

        return $out;
    }

    /**
     * Applies a condition to the records of the source form through a path
     * of to-one and multi-reference hops (filters), limited to the user's
     * scope at every level. Returns false when the path is not usable.
     *
     * @param  list<string>  $path
     * @param  callable(Builder, string, array<string, mixed>, array<string, mixed>): void  $condition  query, column, column spec, field
     */
    public function where(Builder $q, FormRuntime $rt, array $path, ?User $user, callable $condition, string $sourceTable): bool
    {
        $r = $this->resolve($rt, $path, $user);
        if ($r === null || $r['field'] === null || $r['repeater'] !== null) {
            return false;
        }
        foreach ($r['hops'] as $hop) {
            if (! in_array($hop['kind'], ['ref', 'multi'], true)) {
                return false;
            }
        }
        $col = $r['rt']->column($r['field']['uuid']);
        if ($col === null) {
            return false;
        }
        if ($r['hops'] === []) {
            $condition($q, $sourceTable.'.'.$col['name'], $col, $r['field']);

            return true;
        }
        $build = function (int $i) use (&$build, $r, $user, $condition, $col): Builder {
            $hop = $r['hops'][$i];
            $target = $hop['target'];
            $sub = DB::table($target->table)->select('id')->whereNull('deleted_at');
            if ($user !== null) {
                $this->scope->apply($sub, $target, $user, 'view');
            }
            if ($i === count($r['hops']) - 1) {
                $condition($sub, $target->table.'.'.$col['name'], $col, $r['field']);
            } else {
                $this->linkIn($sub, $r['hops'][$i + 1], $build($i + 1), $target->table);
            }

            return $sub;
        };
        $this->linkIn($q, $r['hops'][0], $build(0), $sourceTable);

        return true;
    }

    /**
     * One level of the relation tree for the builder: the fields of the form
     * (or repeating section) at the end of `prefix`, the forms it links to,
     * the forms that link to it, its repeating sections and system values.
     *
     * @param  list<string>  $prefix
     * @return list<array<string, mixed>>
     */
    public function tree(FormRuntime $rt, array $prefix, ?User $user): array
    {
        $at = $rt;
        $repeater = null;
        $toMany = false;
        if ($prefix !== []) {
            $r = $this->resolve($rt, [...$prefix, '@record_number'], $user) ?? $this->resolveRepeater($rt, $prefix, $user);
            if ($r === null) {
                return [];
            }
            $at = $r['rt'];
            $repeater = $r['repeater'];
            $toMany = $r['toMany'];
        }
        $depth = count($prefix);
        $out = [];
        $fields = $repeater === null ? $at->mainFields() : $at->rowFields($repeater);
        foreach ($fields as $f) {
            if (! $this->codec->exportable($at, $f) || ($this->checking($user) && ! $this->visible($at, $user, $f['uuid']))) {
                continue;
            }
            $label = $this->codec->header($f);
            $out[] = ['kind' => 'field', 'path' => [...$prefix, $f['key']], 'label' => $label, 'type' => $f['type'], 'toMany' => $toMany, 'sensitive' => (bool) (($f['flags']['sensitive'] ?? false) || ($f['flags']['encrypted'] ?? false))];
            $targetUuid = $repeater === null ? $at->targetFormUuid($f) : null;
            $target = $targetUuid === null ? null : $this->runtimes->forUuid($targetUuid);
            if ($target !== null && $depth < $this->maxDepth() && $this->formVisible($target, $user)) {
                $out[] = ['kind' => 'link', 'path' => [...$prefix, $f['key']], 'label' => $label, 'form' => $this->formName($target), 'toMany' => $toMany || $at->isMultiReference($f)];
            }
        }
        if ($repeater === null) {
            foreach ($at->repeaters as $uuid => $rep) {
                if ($depth < $this->maxDepth() && (! $this->checking($user) || $this->visibleGroup($at, $user, $uuid))) {
                    $out[] = ['kind' => 'repeater', 'path' => [...$prefix, '#'.$rep['group']['key']], 'label' => $this->translator->labelOf($rep['group']['i18n']['label'] ?? [], (string) $rep['group']['key']), 'toMany' => true];
                }
            }
            if ($depth < $this->maxDepth()) {
                foreach ($this->incoming($at) as $in) {
                    if (! $this->formVisible($in['rt'], $user) || ($this->checking($user) && ! $this->visible($in['rt'], $user, $in['field']['uuid']))) {
                        continue;
                    }
                    $out[] = ['kind' => 'reverse', 'path' => [...$prefix, '~'.$in['rt']->form->uuid.'.'.$in['field']['key']], 'label' => $this->formName($in['rt']).' · '.$this->codec->header($in['field']), 'form' => $this->formName($in['rt']), 'toMany' => true];
                }
            }
            foreach (self::SYSTEM as $s) {
                $out[] = ['kind' => 'system', 'path' => [...$prefix, '@'.$s], 'label' => __('downloads.system.'.$s), 'toMany' => $toMany];
            }
        }

        return $out;
    }

    /**
     * Readable path for headers: `Request › Employee › Department › Manager`.
     *
     * @param  list<string>  $path
     */
    public function label(FormRuntime $rt, array $path): string
    {
        $parts = [$this->formName($rt)];
        $current = $rt;
        $repeater = null;
        foreach ($path as $step) {
            $step = (string) $step;
            if (str_starts_with($step, '@')) {
                $parts[] = __('downloads.system.'.substr($step, 1));
                break;
            }
            if (str_starts_with($step, '#')) {
                $repeater = $current->repeaterKeys[substr($step, 1)] ?? null;
                $parts[] = $repeater === null ? '?' : $this->translator->labelOf($current->repeaters[$repeater]['group']['i18n']['label'] ?? [], substr($step, 1));

                continue;
            }
            if (str_starts_with($step, '~')) {
                [$formUuid] = explode('.', substr($step, 1), 2);
                $source = $this->runtimes->forUuid(strtolower($formUuid));
                if ($source === null) {
                    $parts[] = '?';
                    break;
                }
                $parts[] = $this->formName($source);
                $current = $source;

                continue;
            }
            $f = $this->fieldOf($current, $repeater, $step);
            if ($f === null) {
                $parts[] = '?';
                break;
            }
            $parts[] = $this->codec->header($f);
            $targetUuid = $repeater === null ? $current->targetFormUuid($f) : null;
            $next = $targetUuid === null ? null : $this->runtimes->forUuid($targetUuid);
            if ($next !== null) {
                $current = $next;
            }
        }

        return implode(' › ', $parts);
    }

    public function formName(FormRuntime $rt): string
    {
        return $this->translator->labelOf($rt->form->translationsFor('name'), (string) $rt->form->key);
    }

    /** Whether the user (or the previewed role) sees a field (view mode). */
    public function visible(FormRuntime $rt, ?User $user, string $fieldUuid): bool
    {
        return ($this->levelsOf($rt, $user)[$fieldUuid] ?? 'hidden') !== 'hidden';
    }

    private function checking(?User $user): bool
    {
        return $user !== null || $this->roleId !== null;
    }

    /** Sensitive and encrypted values stay masked unless the user holds `field.{uuid}.view_sensitive`. */
    public function masked(array $field, ?User $user): bool
    {
        $sensitive = ($field['flags']['sensitive'] ?? false) || ($field['flags']['encrypted'] ?? false);

        return $sensitive && ($user === null || ! $this->access->allows($user, 'field.'.strtolower((string) $field['uuid']).'.view_sensitive'));
    }

    /** @return list<array{rt: FormRuntime, field: array<string, mixed>}> */
    public function incoming(FormRuntime $rt): array
    {
        if (isset($this->incoming[$rt->form->uuid])) {
            return $this->incoming[$rt->form->uuid];
        }
        $out = [];
        $sources = DB::table('relations')->where('target_form_id', $rt->form->id)->where('kind', 'reference')->distinct()->pluck('source_form_id');
        foreach (DB::table('forms')->whereIn('id', $sources)->whereNull('deleted_at')->pluck('uuid') as $uuid) {
            $source = $this->runtimes->forUuid(strtolower((string) $uuid));
            if ($source === null) {
                continue;
            }
            foreach ($source->mainFields() as $f) {
                if ($source->targetFormUuid($f) === $rt->form->uuid && $source->isStored($f)) {
                    $out[] = ['rt' => $source, 'field' => $f];
                }
            }
        }

        return $this->incoming[$rt->form->uuid] = $out;
    }

    /** @return array<string, mixed>|null */
    private function fieldOf(FormRuntime $rt, ?string $repeater, string $key): ?array
    {
        $uuid = $repeater === null ? ($rt->keys[$key] ?? null) : ($rt->repeaters[$repeater]['fields'][$key] ?? null);
        if ($uuid === null || ($repeater === null && isset($rt->fieldRepeater[$uuid]))) {
            return null;
        }

        return $rt->fields[$uuid] ?? null;
    }

    /**
     * @param  list<string>  $prefix
     * @return array{rt: FormRuntime, repeater: string|null, toMany: bool}|null
     */
    private function resolveRepeater(FormRuntime $rt, array $prefix, ?User $user): ?array
    {
        $last = (string) end($prefix);
        if (! str_starts_with($last, '#')) {
            return null;
        }
        $parent = array_slice($prefix, 0, -1);
        $base = $parent === [] ? ['rt' => $rt] : $this->resolve($rt, [...$parent, '@record_number'], $user);
        if ($base === null) {
            return null;
        }
        /** @var FormRuntime $at */
        $at = $base['rt'];
        $uuid = $at->repeaterKeys[substr($last, 1)] ?? null;
        if ($uuid === null || ($this->checking($user) && ! $this->visibleGroup($at, $user, $uuid))) {
            return null;
        }

        return ['rt' => $at, 'repeater' => $uuid, 'toMany' => true];
    }

    private function formVisible(FormRuntime $rt, ?User $user): bool
    {
        // A role's preview relies on field access, which already hides every field of a form the role may not view.
        return $user === null || $rt->form->kind === 'collection' || $this->access->allows($user, "form.{$rt->form->uuid}.view");
    }

    private function visibleGroup(FormRuntime $rt, ?User $user, string $groupUuid): bool
    {
        foreach ($rt->rowFields($groupUuid) as $f) {
            if ($this->visible($rt, $user, $f['uuid'])) {
                return true;
            }
        }

        return false;
    }

    /** @return array<string, string> */
    private function levelsOf(FormRuntime $rt, ?User $user): array
    {
        if ($this->roleId !== null) {
            return $this->levels[$rt->form->uuid.':role'] ??= $this->fieldAccess->resolveForRole($this->roleId, $rt->form->id, $rt->form->uuid, $rt->definition, 'view')['fields'];
        }
        if ($user === null) {
            return [];
        }

        return $this->levels[$rt->form->uuid.':'.$user->id] ??= $this->fieldAccess->resolve($user, $rt->form->id, $rt->form->uuid, $rt->definition, 'view')['fields'];
    }

    /**
     * Ids reached through one hop: current id => next-level ids.
     *
     * @param  array<string, mixed>  $hop
     * @param  list<int>  $ids
     * @return array<int, list<int>>
     */
    private function links(array $hop, array $ids): array
    {
        $out = [];
        if ($ids === []) {
            return $out;
        }
        /** @var FormRuntime $from */
        $from = $hop['rt'];
        /** @var FormRuntime $target */
        $target = $hop['target'];
        foreach (array_chunk($ids, 1000) as $chunk) {
            switch ($hop['kind']) {
                case 'repeater':
                    $table = $from->repeaters[$hop['repeater']]['table'];
                    foreach (DB::table($table)->whereIn('parent_id', $chunk)->orderBy('sort_order')->orderBy('id')->get(['id', 'parent_id']) as $row) {
                        $out[(int) $row->parent_id][] = (int) $row->id;
                    }
                    break;
                case 'multi':
                    $pivot = $from->pivots[$hop['field']['relation']] ?? null;
                    if ($pivot !== null) {
                        foreach (DB::table($pivot)->whereIn('source_id', $chunk)->orderBy('sort_order')->get(['source_id', 'target_id']) as $p) {
                            $out[(int) $p->source_id][] = (int) $p->target_id;
                        }
                    }
                    break;
                case 'reverse':
                    // Records of the other form whose field points at these.
                    if ($target->isMultiReference($hop['field'])) {
                        $pivot = $target->pivots[$hop['field']['relation']] ?? null;
                        if ($pivot !== null) {
                            foreach (DB::table($pivot)->whereIn('target_id', $chunk)->orderBy('source_id')->get(['source_id', 'target_id']) as $p) {
                                $out[(int) $p->target_id][] = (int) $p->source_id;
                            }
                        }
                    } else {
                        $col = $target->column($hop['field']['uuid']);
                        if ($col !== null) {
                            foreach (DB::table($target->table)->whereIn($col['name'], $chunk)->orderBy('id')->get(['id', $col['name'].' as fk']) as $row) {
                                $out[(int) $row->fk][] = (int) $row->id;
                            }
                        }
                    }
                    break;
                default:
                    $col = $from->column($hop['field']['uuid']);
                    if ($col !== null) {
                        foreach (DB::table($from->table)->whereIn('id', $chunk)->whereNotNull($col['name'])->get(['id', $col['name'].' as fk']) as $row) {
                            $out[(int) $row->id][] = (int) $row->fk;
                        }
                    }
            }
        }

        return $out;
    }

    /**
     * The next-level ids a user may reach: live records within their scope
     * (repeater rows follow their record, already checked).
     *
     * @param  array<string, mixed>  $hop
     * @param  list<int>  $ids
     * @return list<int>
     */
    private function allowed(array $hop, array $ids, ?User $user): array
    {
        if ($ids === [] || $hop['kind'] === 'repeater') {
            return $ids;
        }
        /** @var FormRuntime $target */
        $target = $hop['target'];
        $out = [];
        foreach (array_chunk($ids, 1000) as $chunk) {
            $q = DB::table($target->table)->whereIn('id', $chunk)->whereNull('deleted_at');
            if ($user !== null) {
                $this->scope->apply($q, $target, $user, 'view');
            }
            $out = [...$out, ...$q->pluck('id')->map(static fn ($v) => (int) $v)->all()];
        }

        return $out;
    }

    /** `source.fk IN (sub)` for a to-one hop, `source.id IN (pivot.source_id …)` for a multi-reference. */
    private function linkIn(Builder $q, array $hop, Builder $targets, string $sourceTable): void
    {
        /** @var FormRuntime $from */
        $from = $hop['rt'];
        if ($hop['kind'] === 'multi') {
            $pivot = $from->pivots[$hop['field']['relation']] ?? null;
            $pivot === null ? $q->whereRaw('1 = 0') : $q->whereIn($sourceTable.'.id', DB::table($pivot)->select('source_id')->whereIn('target_id', $targets));

            return;
        }
        $col = $from->column($hop['field']['uuid']);
        $col === null ? $q->whereRaw('1 = 0') : $q->whereIn($sourceTable.'.'.$col['name'], $targets);
    }

    /**
     * @param  list<int>  $ids
     * @return array<int, array<string, mixed>>
     */
    private function recordValues(FormRuntime $rt, array $ids): array
    {
        $out = [];
        foreach (array_chunk($ids, 500) as $chunk) {
            $rows = DB::table($rt->table)->whereIn('id', $chunk)->get()->map(static fn ($x) => (array) $x)->all();
            foreach ($this->store->hydrate($rt, $rows, false) as $rec) {
                $out[(int) $rec['id']] = $rec['values'];
            }
        }

        return $out;
    }

    /**
     * Values of repeater rows by row id.
     *
     * @param  list<int>  $ids
     * @return array<int, array<string, mixed>>
     */
    private function rowValues(FormRuntime $rt, string $repeater, array $ids): array
    {
        $table = $rt->repeaters[$repeater]['table'];
        $key = $rt->repeaters[$repeater]['group']['key'];
        $out = [];
        foreach (array_chunk($ids, 500) as $chunk) {
            $rows = DB::table($table)->whereIn('id', $chunk)->get(['id', 'uuid', 'parent_id']);
            $byUuid = [];
            foreach ($rows as $row) {
                $byUuid[strtolower((string) $row->uuid)] = (int) $row->id;
            }
            $parents = DB::table($rt->table)->whereIn('id', $rows->pluck('parent_id')->unique()->all())->get()->map(static fn ($x) => (array) $x)->all();
            foreach ($this->store->hydrate($rt, $parents, true) as $rec) {
                foreach ($rec['values'][$key] ?? [] as $values) {
                    $id = $byUuid[strtolower((string) ($values['uuid'] ?? ''))] ?? null;
                    if ($id !== null) {
                        $out[$id] = $values;
                    }
                }
            }
        }

        return $out;
    }

    /**
     * @param  list<int>  $ids
     * @return array<int, bool|int|float|string|null>
     */
    private function systemCells(FormRuntime $rt, string $system, array $ids): array
    {
        $out = [];
        foreach (array_chunk($ids, 1000) as $chunk) {
            $rows = DB::table($rt->table)->whereIn('id', $chunk)->get();
            $users = [];
            if (in_array($system, ['created_by', 'updated_by'], true)) {
                $users = DB::table('users')->whereIn('id', $rows->pluck($system)->filter()->unique()->all())->pluck('name', 'id')->all();
            }
            $transitions = [];
            if ($system === 'last_transition_at') {
                $transitions = DB::table('status_history')->where('form_id', $rt->form->id)->whereIn('record_id', $chunk)
                    ->groupBy('record_id')->selectRaw('record_id, max(acted_at) as at')->pluck('at', 'record_id')->all();
            }
            $wf = $system === 'status' ? WorkflowRuntime::for($rt) : null;
            foreach ($rows as $row) {
                $id = (int) $row->id;
                $out[$id] = match ($system) {
                    'status' => RecordPresenter::status($wf, isset($row->status_id) ? (int) $row->status_id : null)['name'] ?? null,
                    'created_by', 'updated_by' => $row->{$system} === null ? null : ($users[(int) $row->{$system}] ?? null),
                    'last_transition_at' => isset($transitions[$id]) ? self::isoDate((string) $transitions[$id]) : null,
                    'created_at', 'updated_at' => $row->{$system} === null ? null : self::isoDate((string) $row->{$system}),
                    default => $row->{$system} ?? null,
                };
            }
        }

        return $out;
    }

    private static function isoDate(string $value): string
    {
        return substr(str_replace(' ', 'T', $value), 0, 19).'Z';
    }
}
