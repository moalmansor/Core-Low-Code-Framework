<?php

declare(strict_types=1);

namespace App\Modules\Views;

use App\Modules\Access\ObjectPermissions;
use App\Modules\Audit\AuditWriter;
use App\Modules\Core\I18n\Translator;
use App\Modules\Forms\Definition\DefinitionCompiler;
use App\Modules\Forms\Models\Form;
use App\Modules\Records\Runtime\FormRuntime;
use App\Modules\Records\Runtime\FormRuntimes;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * The table views of a form (specification §4.14 "Table view", architecture
 * §10.9): per view its columns (relation paths, including linked forms'
 * fields), order, width, pinning, formatting and totals; its filters; default
 * sort and page size; and who uses it (permission `view.{uuid}.use`, matrix-
 * managed; the default view applies when none of a user's views matches).
 * Views take effect when saved.
 */
final class ViewDocument
{
    public const PINNED = ['none', 'start', 'end'];

    public const AGGREGATES = ['none', 'count', 'sum', 'avg', 'min', 'max'];

    /** Virtual columns of the latest justification (View Justifications only). */
    public const JUSTIFICATION_COLUMNS = ['last_reason', 'last_code', 'last_at', 'last_by'];

    public function __construct(
        private readonly Translator $translator,
        private readonly ObjectPermissions $permissions,
        private readonly AuditWriter $audit,
        private readonly FormRuntimes $runtimes,
        private readonly RelationPaths $paths,
    ) {}

    /** @return list<array<string, mixed>> */
    public function load(Form $form): array
    {
        $views = DB::table('views')->where('form_id', $form->id)->orderBy('priority')->orderBy('id')->get();
        $ids = $views->pluck('id')->map(static fn ($v) => (int) $v)->all();
        $columns = DB::table('view_columns')->whereIn('view_id', $ids ?: [0])->orderBy('sort_order')->orderBy('id')->get()->groupBy('view_id');
        $filters = DB::table('filters')->whereIn('view_id', $ids ?: [0])->orderBy('sort_order')->orderBy('id')->get()->groupBy('view_id');
        $viewTr = $this->translator->allMany('view', $ids);
        $colTr = $this->translator->allMany('view_column', $columns->flatten()->pluck('id')->map(static fn ($v) => (int) $v)->all());
        $filTr = $this->translator->allMany('filter', $filters->flatten()->pluck('id')->map(static fn ($v) => (int) $v)->all());
        $json = static fn ($v) => $v === null ? null : json_decode((string) $v, true);

        return $views->map(fn ($v) => [
            'uuid' => strtolower((string) $v->uuid),
            'key' => $v->key,
            'i18n' => ['name' => (object) ($viewTr[(int) $v->id]['name'] ?? [])],
            'default' => (bool) $v->is_default,
            'priority' => (int) $v->priority,
            'pageSize' => (int) $v->page_size,
            'defaultSort' => $json($v->default_sort) ?? [],
            'showTotals' => (bool) $v->show_totals,
            'columnChooser' => (bool) $v->allow_column_chooser,
            'globalSearch' => (bool) $v->allow_global_search,
            'rowOptions' => $json($v->row_options) ?? ['view' => true, 'edit' => true, 'log' => true],
            'includeInQueues' => (bool) $v->include_in_queues,
            'columns' => ($columns[$v->id] ?? collect())->map(static fn ($c) => [
                'uuid' => strtolower((string) $c->uuid), 'path' => $json($c->path), 'i18n' => ['label' => (object) ($colTr[(int) $c->id]['label'] ?? [])],
                'width' => $c->width === null ? null : (int) $c->width, 'pinned' => $c->pinned, 'visible' => (bool) $c->is_visible,
                'sortable' => (bool) $c->is_sortable, 'format' => $json($c->format), 'aggregate' => $c->aggregate,
            ])->values()->all(),
            'filters' => ($filters[$v->id] ?? collect())->map(static fn ($f) => [
                'uuid' => strtolower((string) $f->uuid), 'path' => $json($f->path), 'i18n' => ['label' => (object) ($filTr[(int) $f->id]['label'] ?? [])],
                'type' => $f->filter_type, 'operators' => $json($f->operators) ?? [], 'quick' => (bool) $f->is_quick, 'default' => $json($f->default_value),
            ])->values()->all(),
        ])->values()->all();
    }

    public function hash(Form $form): string
    {
        return DefinitionCompiler::hash($this->load($form));
    }

    /** @param  list<array<string, mixed>>  $views */
    public function save(Form $form, array $views, int $userId): void
    {
        $rt = $this->runtimes->forForm($form);
        if ($rt === null) {
            throw ValidationException::withMessages(['views' => __('views.publish_first')]);
        }
        $views = $this->validate($form, $rt, $views);
        DB::transaction(function () use ($form, $views, $userId): void {
            $now = Carbon::now('UTC')->format('Y-m-d H:i:s.u');
            $kept = [];
            $viewTr = [];
            $colTr = [];
            $filTr = [];
            foreach ($views as $i => $v) {
                $row = [
                    'updated_at' => $now, 'updated_by' => $userId, 'key' => $v['key'], 'is_default' => (bool) ($v['default'] ?? false),
                    'priority' => (int) ($v['priority'] ?? $i), 'page_size' => (int) ($v['pageSize'] ?? 25), 'default_sort' => json_encode(array_values($v['defaultSort'] ?? [])),
                    'show_totals' => (bool) ($v['showTotals'] ?? false), 'allow_column_chooser' => (bool) ($v['columnChooser'] ?? true),
                    'allow_global_search' => (bool) ($v['globalSearch'] ?? true), 'row_options' => json_encode($v['rowOptions'] ?? ['view' => true, 'edit' => true, 'log' => true]),
                    'include_in_queues' => (bool) ($v['includeInQueues'] ?? false),
                ];
                $existing = DB::table('views')->where('form_id', $form->id)->where('uuid', $v['uuid'])->first();
                if ($existing === null) {
                    $id = (int) DB::table('views')->insertGetId($row + ['uuid' => $v['uuid'], 'organization_id' => $form->organization_id, 'created_at' => $now, 'created_by' => $userId, 'form_id' => $form->id]);
                } else {
                    $id = (int) $existing->id;
                    DB::table('views')->where('id', $id)->update($row);
                }
                $this->permissions->registerView($id, $v['uuid']);
                $viewTr[$id] = ['name' => (array) ($v['i18n']['name'] ?? [])];
                $keptColumns = [];
                foreach ($v['columns'] as $j => $c) {
                    $hash = self::pathHash($c['path']);
                    $data = [
                        'updated_at' => $now, 'path' => json_encode($c['path']), 'path_hash' => $hash, 'sort_order' => $j, 'width' => $c['width'] ?? null,
                        'pinned' => $c['pinned'] ?? 'none', 'is_visible' => (bool) ($c['visible'] ?? true), 'is_sortable' => (bool) ($c['sortable'] ?? true),
                        'format' => isset($c['format']) ? json_encode($c['format']) : null, 'aggregate' => $c['aggregate'] ?? 'none',
                    ];
                    $cid = DB::table('view_columns')->where('view_id', $id)->where('path_hash', $hash)->value('id');
                    if ($cid === null) {
                        $cid = DB::table('view_columns')->insertGetId($data + ['uuid' => $c['uuid'], 'view_id' => $id, 'created_at' => $now]);
                    } else {
                        DB::table('view_columns')->where('id', $cid)->update($data);
                    }
                    $colTr[(int) $cid] = ['label' => (array) ($c['i18n']['label'] ?? [])];
                    $keptColumns[] = (int) $cid;
                }
                $this->removeChildren('view_columns', 'view_column', $id, $keptColumns);
                $keptFilters = [];
                foreach ($v['filters'] as $j => $f) {
                    $hash = self::pathHash($f['path']);
                    $data = [
                        'updated_at' => $now, 'path' => json_encode($f['path']), 'path_hash' => $hash, 'filter_type' => $f['type'],
                        'operators' => json_encode(array_values($f['operators'])), 'is_quick' => (bool) ($f['quick'] ?? false),
                        'default_value' => isset($f['default']) ? json_encode($f['default']) : null, 'sort_order' => $j,
                    ];
                    $fid = DB::table('filters')->where('view_id', $id)->where('path_hash', $hash)->value('id');
                    if ($fid === null) {
                        $fid = DB::table('filters')->insertGetId($data + ['uuid' => $f['uuid'], 'view_id' => $id, 'created_at' => $now]);
                    } else {
                        DB::table('filters')->where('id', $fid)->update($data);
                    }
                    $filTr[(int) $fid] = ['label' => (array) ($f['i18n']['label'] ?? [])];
                    $keptFilters[] = (int) $fid;
                }
                $this->removeChildren('filters', 'filter', $id, $keptFilters);
                $kept[] = $id;
            }
            $this->translator->syncObjects('view', $viewTr);
            $this->translator->syncObjects('view_column', $colTr);
            $this->translator->syncObjects('filter', $filTr);
            foreach (DB::table('views')->where('form_id', $form->id)->whereNotIn('id', $kept ?: [0])->get(['id', 'uuid']) as $gone) {
                $this->delete((int) $gone->id, strtolower((string) $gone->uuid));
            }
            $this->audit->record('views.saved', 'config', null, 'form', $form->id, ['views' => count($views)], $userId);
        });
    }

    /** Removes a view, its columns, filters, saved views and permission. */
    public function delete(int $id, string $uuid): void
    {
        $saved = DB::table('saved_views')->where('view_id', $id)->pluck('id')->all();
        DB::table('saved_view_shares')->whereIn('saved_view_id', $saved ?: [0])->delete();
        DB::table('saved_views')->whereIn('id', $saved ?: [0])->delete();
        $this->removeChildren('view_columns', 'view_column', $id, []);
        $this->removeChildren('filters', 'filter', $id, []);
        DB::table('blueprint_instances')->where('object_type', 'view')->where('object_id', $id)->update(['is_detached' => true]);
        DB::table('views')->where('id', $id)->delete();
        $this->translator->forget('view', $id);
        $this->permissions->forget('view.'.$uuid);
    }

    /** SHA-256 of a path's canonical JSON. @param  list<string>  $path */
    public static function pathHash(array $path): string
    {
        return hash('sha256', (string) json_encode(array_values($path)));
    }

    /**
     * The filter type a path's final field implies.
     *
     * @param  array<string, mixed>|null  $field
     */
    public static function filterType(FormRuntime $rt, ?array $field, ?string $system): string
    {
        if ($system !== null) {
            return match ($system) {
                'status' => 'status', 'created_at', 'updated_at' => 'date_range', default => 'text',
            };
        }
        $storage = $field === null ? null : $rt->type($field)?->storage;

        return match (true) {
            $field === null => 'text',
            in_array($storage, ['user'], true) => 'user',
            $rt->targetTable($field) !== null => 'reference',
            ($field['options']['source'] ?? null) === 'static' => 'options',
            in_array($storage, ['bool'], true) || $field['type'] === 'checkbox' => 'boolean',
            in_array($field['type'], ['number', 'decimal', 'currency', 'percent', 'rating', 'slider'], true) => 'number_range',
            in_array($field['type'], ['date', 'datetime'], true) => 'date_range',
            default => 'text',
        };
    }

    /** @return list<string> */
    public static function operatorsFor(string $type): array
    {
        return match ($type) {
            'text' => ['contains', 'starts_with', 'equals', 'is_empty'],
            'number_range', 'date_range' => ['between', 'equals', 'is_empty'],
            'options', 'reference', 'user', 'status' => ['in', 'is_empty'],
            'boolean' => ['equals'],
            default => ['equals'],
        };
    }

    /**
     * @param  list<array<string, mixed>>  $views
     * @return list<array<string, mixed>>
     */
    private function validate(Form $form, FormRuntime $rt, array $views): array
    {
        $errors = [];
        $keys = [];
        $defaults = 0;
        foreach ($views as $i => &$v) {
            $p = "views.{$i}";
            if (! is_array($v) || ! is_string($v['uuid'] ?? null) || ! Str::isUuid($v['uuid'])) {
                $errors["{$p}.uuid"][] = __('validation.uuid', ['attribute' => 'uuid']);

                continue;
            }
            $v['uuid'] = strtolower($v['uuid']);
            if (DB::table('views')->where('uuid', $v['uuid'])->where('form_id', '!=', $form->id)->exists()) {
                $errors["{$p}.uuid"][] = __('validation.unique', ['attribute' => 'uuid']);
            }
            if (! is_string($v['key'] ?? null) || preg_match('/^[a-z][a-z0-9_]{0,47}$/', $v['key']) !== 1 || isset($keys[$v['key']])) {
                $errors["{$p}.key"][] = __('views.invalid_key');
            }
            $keys[$v['key'] ?? ''] = true;
            $defaults += ($v['default'] ?? false) ? 1 : 0;
            $name = (array) ($v['i18n']['name'] ?? []);
            if (trim((string) ($name[$this->translator->defaultLocale()] ?? '')) === '') {
                $errors["{$p}.i18n.name"][] = __('workflow.name_required');
            }
            if (! is_int($v['pageSize'] ?? 25) || ($v['pageSize'] ?? 25) < 5 || ($v['pageSize'] ?? 25) > 100) {
                $errors["{$p}.pageSize"][] = __('views.invalid_page_size');
            }
            $v['columns'] = array_values(is_array($v['columns'] ?? null) ? $v['columns'] : []);
            $v['filters'] = array_values(is_array($v['filters'] ?? null) ? $v['filters'] : []);
            if (count($v['columns']) > 60 || count($v['filters']) > 30) {
                $errors["{$p}.columns"][] = __('views.too_many');
            }
            $seen = [];
            foreach ($v['columns'] as $j => &$c) {
                $cp = "{$p}.columns.{$j}";
                $c['uuid'] = is_string($c['uuid'] ?? null) && Str::isUuid($c['uuid']) ? strtolower($c['uuid']) : (string) Str::uuid7();
                $path = $c['path'] ?? null;
                if (! $this->validPath($rt, $path)) {
                    $errors["{$cp}.path"][] = __('views.unknown_path');

                    continue;
                }
                $hash = self::pathHash($path);
                if (isset($seen[$hash])) {
                    $errors["{$cp}.path"][] = __('views.duplicate_column');
                }
                $seen[$hash] = true;
                if (! in_array($c['pinned'] ?? 'none', self::PINNED, true) || ! in_array($c['aggregate'] ?? 'none', self::AGGREGATES, true)) {
                    $errors["{$cp}"][] = __('validation.in', ['attribute' => 'column']);
                }
                if (($c['aggregate'] ?? 'none') !== 'none' && ! $this->aggregatable($rt, $path, $c['aggregate'])) {
                    $errors["{$cp}.aggregate"][] = __('views.aggregate_unsupported');
                }
                if (isset($c['width']) && (! is_int($c['width']) || $c['width'] < 40 || $c['width'] > 1200)) {
                    $errors["{$cp}.width"][] = __('views.invalid_width');
                }
            }
            unset($c);
            $seen = [];
            foreach ($v['filters'] as $j => &$f) {
                $fp = "{$p}.filters.{$j}";
                $f['uuid'] = is_string($f['uuid'] ?? null) && Str::isUuid($f['uuid']) ? strtolower($f['uuid']) : (string) Str::uuid7();
                $path = $f['path'] ?? null;
                if (! $this->validPath($rt, $path) || ($path[0] ?? '') === '@justification') {
                    $errors["{$fp}.path"][] = __('views.unknown_path');

                    continue;
                }
                $hash = self::pathHash($path);
                if (isset($seen[$hash])) {
                    $errors["{$fp}.path"][] = __('views.duplicate_filter');
                }
                $seen[$hash] = true;
                $r = $this->paths->resolve($rt, $path);
                if (($r['field'] !== null && $r['rt']->isMultiReference($r['field'])) || ($r['system'] !== null && $r['hops'] !== [])) {
                    $errors["{$fp}.path"][] = __('views.filter_unsupported');

                    continue;
                }
                $type = self::filterType($r['rt'], $r['field'], $r['system']);
                $f['type'] = $type;
                $allowed = self::operatorsFor($type);
                $ops = array_values(array_intersect((array) ($f['operators'] ?? $allowed), $allowed));
                $f['operators'] = $ops === [] ? $allowed : $ops;
            }
            unset($f);
            foreach ($v['defaultSort'] ?? [] as $j => $s) {
                if (! is_array($s) || ! $this->validPath($rt, $s['path'] ?? null) || ! in_array($s['dir'] ?? 'asc', ['asc', 'desc'], true)) {
                    $errors["{$p}.defaultSort.{$j}"][] = __('views.unknown_path');
                }
            }
        }
        unset($v);
        if ($views !== [] && $defaults !== 1) {
            $errors['views'][] = __('views.one_default');
        }
        if (count($views) > 50) {
            $errors['views'][] = __('views.too_many');
        }
        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        return $views;
    }

    private function validPath(FormRuntime $rt, mixed $path): bool
    {
        if (! is_array($path) || ! array_is_list($path) || $path === []) {
            return false;
        }
        foreach ($path as $k) {
            if (! is_string($k) || preg_match('/^@?[a-z][a-z0-9_]{0,47}$/', $k) !== 1) {
                return false;
            }
        }
        if ($path[0] === '@justification') {
            return count($path) === 2 && in_array($path[1], self::JUSTIFICATION_COLUMNS, true);
        }

        return $this->paths->resolve($rt, $path) !== null;
    }

    /** @param  list<string>  $path */
    private function aggregatable(FormRuntime $rt, array $path, string $aggregate): bool
    {
        if ($aggregate === 'count') {
            return true;
        }
        $r = $this->paths->resolve($rt, $path);

        return $r !== null && $r['hops'] === [] && $r['field'] !== null
            && in_array($r['field']['type'], ['number', 'decimal', 'currency', 'percent', 'rating', 'slider'], true)
            && ($rt->column($r['field']['uuid'])['type'] ?? null) !== null;
    }

    private function removeChildren(string $table, string $type, int $viewId, array $kept): void
    {
        foreach (DB::table($table)->where('view_id', $viewId)->whereNotIn('id', $kept ?: [0])->pluck('id') as $id) {
            $this->translator->forget($type, (int) $id);
        }
        DB::table($table)->where('view_id', $viewId)->whereNotIn('id', $kept ?: [0])->delete();
    }
}
