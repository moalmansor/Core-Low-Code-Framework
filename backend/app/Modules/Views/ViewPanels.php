<?php

declare(strict_types=1);

namespace App\Modules\Views;

use App\Expressions\Checking\TypeChecker;
use App\Expressions\StaticError;
use App\Modules\Access\AccessResolver;
use App\Modules\Access\RecordScope;
use App\Modules\Audit\AuditWriter;
use App\Modules\Core\I18n\Translator;
use App\Modules\Forms\Conditions\OwnedConditions;
use App\Modules\Forms\Definition\DefinitionCompiler;
use App\Modules\Forms\Draft\DraftRepository;
use App\Modules\Forms\Draft\DraftValidator;
use App\Modules\Forms\Models\Form;
use App\Modules\Identity\Models\User;
use App\Modules\Records\Runtime\FormRuntime;
use App\Modules\Records\Runtime\FormRuntimes;
use App\Modules\Records\Runtime\RecordPresenter;
use App\Modules\Records\Runtime\RecordStore;
use App\Modules\Records\Runtime\RuleRuntime;
use App\Support\Html\HtmlSanitizer;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * View Mode panels (specification §4.14 "View Mode"): tabs and sections
 * with per-role visibility (a condition on the record and `@user`), tables of
 * linked records (records of another form that reference this one, with
 * chosen columns), read-only derived fields from related forms, summary
 * widgets (count, sum, average, minimum, maximum of linked records), the
 * status timeline, comments, attachments, the form body and admin-written
 * HTML (sanitized). Panels take effect when saved.
 */
final class ViewPanels
{
    public const TYPES = ['tabs', 'tab', 'section', 'related_table', 'derived_fields', 'summary_widget', 'status_timeline', 'comments', 'attachments', 'form_body', 'html'];

    private const CONTAINERS = ['tabs', 'tab', 'section'];

    public function __construct(
        private readonly Translator $translator,
        private readonly OwnedConditions $conditions,
        private readonly DraftRepository $drafts,
        private readonly DraftValidator $draftValidator,
        private readonly HtmlSanitizer $sanitizer,
        private readonly FormRuntimes $runtimes,
        private readonly RelationPaths $paths,
        private readonly RuleRuntime $rules,
        private readonly RecordScope $scope,
        private readonly RecordStore $store,
        private readonly RecordPresenter $presenter,
        private readonly AccessResolver $access,
        private readonly AuditWriter $audit,
    ) {}

    /** @return list<array<string, mixed>> */
    public function load(Form $form): array
    {
        $rows = DB::table('view_panels')->where('form_id', $form->id)->orderBy('sort_order')->orderBy('id')->get();
        $uuids = $rows->pluck('uuid', 'id')->map(static fn ($u) => strtolower((string) $u))->all();
        $asts = $this->conditions->many($rows->pluck('visibility_condition_id')->all());
        $tr = $this->translator->allMany('view_panel', $rows->pluck('id')->map(static fn ($v) => (int) $v)->all());

        return $rows->map(static fn ($p) => [
            'uuid' => strtolower((string) $p->uuid),
            'parent' => $p->parent_panel_id === null ? null : ($uuids[$p->parent_panel_id] ?? null),
            'type' => $p->type,
            'i18n' => ['title' => (object) ($tr[(int) $p->id]['title'] ?? []), 'content' => (object) ($tr[(int) $p->id]['content'] ?? [])],
            'relationPath' => json_decode((string) ($p->relation_path ?? 'null'), true),
            'config' => json_decode((string) $p->config, true) ?: (object) [],
            'visibility' => $p->visibility_condition_id === null ? null : ($asts[(int) $p->visibility_condition_id] ?? null),
            'order' => (int) $p->sort_order,
        ])->values()->all();
    }

    public function hash(Form $form): string
    {
        return DefinitionCompiler::hash($this->load($form));
    }

    /** @param  list<array<string, mixed>>  $panels */
    public function save(Form $form, array $panels, int $userId): void
    {
        $rt = $this->runtimes->forForm($form);
        if ($rt === null) {
            throw ValidationException::withMessages(['panels' => __('views.publish_first')]);
        }
        $panels = $this->validate($form, $rt, $panels);
        DB::transaction(function () use ($form, $panels, $userId): void {
            $now = Carbon::now('UTC')->format('Y-m-d H:i:s.u');
            $ids = [];
            $tr = [];
            // Parents first so children can point at them.
            foreach ($panels as $i => $p) {
                $existing = DB::table('view_panels')->where('form_id', $form->id)->where('uuid', $p['uuid'])->first();
                $row = [
                    'updated_at' => $now, 'updated_by' => $userId, 'parent_panel_id' => null, 'type' => $p['type'],
                    'relation_path' => isset($p['relationPath']) ? json_encode($p['relationPath']) : null,
                    'config' => json_encode((object) ($p['config'] ?? [])), 'sort_order' => (int) ($p['order'] ?? $i),
                ];
                if ($existing === null) {
                    $id = (int) DB::table('view_panels')->insertGetId($row + ['uuid' => $p['uuid'], 'organization_id' => $form->organization_id, 'created_at' => $now, 'created_by' => $userId, 'form_id' => $form->id, 'visibility_condition_id' => null]);
                    $conditionId = null;
                } else {
                    $id = (int) $existing->id;
                    DB::table('view_panels')->where('id', $id)->update($row);
                    $conditionId = $existing->visibility_condition_id === null ? null : (int) $existing->visibility_condition_id;
                }
                $new = $this->conditions->put($form->id, 'view_panel', $id, $p['visibility'] ?? null, $conditionId);
                if ($new !== $conditionId) {
                    DB::table('view_panels')->where('id', $id)->update(['visibility_condition_id' => $new]);
                }
                $ids[$p['uuid']] = $id;
                $content = [];
                foreach ((array) ($p['i18n']['content'] ?? []) as $locale => $html) {
                    $content[$locale] = is_string($html) ? $this->sanitizer->clean($html) : null;
                }
                $tr[$id] = ['title' => (array) ($p['i18n']['title'] ?? []), 'content' => $content];
            }
            foreach ($panels as $p) {
                if (($p['parent'] ?? null) !== null) {
                    DB::table('view_panels')->where('id', $ids[$p['uuid']])->update(['parent_panel_id' => $ids[$p['parent']]]);
                }
            }
            $this->translator->syncObjects('view_panel', $tr);
            $gone = DB::table('view_panels')->where('form_id', $form->id)->whereNotIn('id', array_values($ids) ?: [0])->get(['id', 'visibility_condition_id']);
            DB::table('view_panels')->whereIn('id', $gone->pluck('id')->all() ?: [0])->update(['parent_panel_id' => null]);
            foreach ($gone as $g) {
                DB::table('view_panels')->where('id', $g->id)->delete();
                $this->conditions->forget($g->visibility_condition_id === null ? null : (int) $g->visibility_condition_id);
                $this->translator->forget('view_panel', (int) $g->id);
            }
            $this->audit->record('view_panels.saved', 'config', null, 'form', $form->id, ['panels' => count($panels)], $userId);
        });
    }

    /**
     * The panels of a record as the user sees them: visible panels only, each
     * data panel with its data, as a tree.
     *
     * @param  array{id: int, uuid: string, row_version: int, values: array<string, mixed>, system: array<string, mixed>}  $record
     * @return list<array<string, mixed>>
     */
    public function render(FormRuntime $rt, array $record, User $user): array
    {
        $panels = $this->load($rt->form);
        $out = [];
        foreach ($panels as $p) {
            if (($p['visibility'] ?? null) !== null && ! $this->rules->holds($rt, $p['visibility'], $record['values'], $user, 'view')) {
                continue;
            }
            $out[$p['uuid']] = [
                'uuid' => $p['uuid'], 'parent' => $p['parent'], 'type' => $p['type'],
                'title' => $this->pick((array) $p['i18n']['title']),
                'content' => $p['type'] === 'html' ? $this->pick((array) $p['i18n']['content']) : null,
                'config' => $p['config'],
                'data' => $this->data($rt, $record, $user, $p),
            ];
        }
        // Children of hidden panels are hidden too.
        $visible = static function (array $p) use (&$visible, $out): bool {
            return $p['parent'] === null || (isset($out[$p['parent']]) && $visible($out[$p['parent']]));
        };

        return array_values(array_filter($out, $visible));
    }

    /**
     * @param  array<string, mixed>  $record
     * @param  array<string, mixed>  $p
     */
    private function data(FormRuntime $rt, array $record, User $user, array $p): mixed
    {
        $config = (array) $p['config'];
        switch ($p['type']) {
            case 'derived_fields':
                $items = [];
                foreach ($config['paths'] ?? [] as $path) {
                    $r = $this->paths->resolve($rt, $path, $user);
                    if ($r === null) {
                        continue;
                    }
                    $items[] = ['path' => $path, 'label' => $this->pathLabel($r), 'value' => $this->paths->values($rt, $path, [$record['id']], $user)[$record['id']] ?? null];
                }

                return $items;
            case 'related_table':
            case 'summary_widget':
                $source = $this->runtimes->forUuid((string) ($config['source'] ?? ''));
                $via = $source === null ? null : ($source->keys[$config['via'] ?? ''] ?? null);
                $col = $via === null ? null : $source->column($via);
                if ($source === null || $col === null || ! $this->access->allows($user, "form.{$source->form->uuid}.view")) {
                    return null;
                }
                $q = DB::table($source->table)->whereNull('deleted_at')->where($col['name'], $record['id']);
                $this->scope->apply($q, $source, $user, 'view');
                if ($p['type'] === 'summary_widget') {
                    $aggregate = $config['aggregate'] ?? 'count';
                    if ($aggregate === 'count') {
                        return ['value' => $q->count()];
                    }
                    $fieldUuid = $source->keys[$config['field'] ?? ''] ?? null;
                    $valueCol = $fieldUuid === null ? null : $source->column($fieldUuid);

                    return ['value' => $valueCol === null ? null : (($v = $q->{$aggregate}($valueCol['name'])) === null ? null : (string) $v)];
                }
                $total = (clone $q)->count();
                $rows = $q->orderByDesc('updated_at')->orderByDesc('id')->limit(min(100, max(1, (int) ($config['limit'] ?? 20))))->get()->map(static fn ($x) => (array) $x)->all();
                $records = $this->store->hydrate($source, $rows, false);
                $ids = array_map(static fn ($r) => $r['id'], $records);
                $columns = [];
                foreach ($config['columns'] ?? [] as $path) {
                    $r = $this->paths->resolve($source, $path, $user);
                    if ($r !== null) {
                        $columns[] = ['key' => implode('.', $path), 'label' => $this->pathLabel($r), 'values' => $this->paths->values($source, $path, $ids, $user)];
                    }
                }
                $presented = $this->presenter->many($source, $records, [], false);

                return [
                    'form' => ['uuid' => $source->form->uuid, 'key' => $source->form->key],
                    'total' => $total,
                    'columns' => array_map(static fn ($c) => ['key' => $c['key'], 'label' => $c['label']], $columns),
                    'rows' => array_map(static fn ($rec, $pres) => [
                        'uuid' => $rec['uuid'], 'title' => $pres['title'], 'status' => $pres['system']['status'],
                        'cells' => array_combine(array_column($columns, 'key'), array_map(static fn ($c) => $c['values'][$rec['id']] ?? null, $columns)) ?: (object) [],
                    ], $records, $presented),
                    'can_create' => $this->access->allows($user, "form.{$source->form->uuid}.create"),
                ];
            default:
                return null;
        }
    }

    /**
     * @param  list<array<string, mixed>>  $panels
     * @return list<array<string, mixed>>
     */
    private function validate(Form $form, FormRuntime $rt, array $panels): array
    {
        $errors = [];
        $uuids = [];
        $types = [];
        foreach ($panels as $i => &$p) {
            if (! is_array($p) || ! is_string($p['uuid'] ?? null) || ! Str::isUuid($p['uuid'])) {
                $errors["panels.{$i}.uuid"][] = __('validation.uuid', ['attribute' => 'uuid']);

                continue;
            }
            $p['uuid'] = strtolower($p['uuid']);
            if (isset($uuids[$p['uuid']]) || DB::table('view_panels')->where('uuid', $p['uuid'])->where('form_id', '!=', $form->id)->exists()) {
                $errors["panels.{$i}.uuid"][] = __('validation.unique', ['attribute' => 'uuid']);
            }
            $uuids[$p['uuid']] = true;
            $types[$p['uuid']] = $p['type'] ?? null;
        }
        unset($p);
        $draft = $this->drafts->normalize($this->drafts->load($form));
        $resolver = $this->draftValidator->resolver($draft);
        foreach ($panels as $i => &$p) {
            $pp = "panels.{$i}";
            $type = $p['type'] ?? null;
            if (! in_array($type, self::TYPES, true)) {
                $errors["{$pp}.type"][] = __('validation.in', ['attribute' => 'type']);

                continue;
            }
            $parent = isset($p['parent']) && is_string($p['parent']) ? strtolower($p['parent']) : null;
            $p['parent'] = $parent;
            if ($parent !== null && (! isset($types[$parent]) || ! in_array($types[$parent], self::CONTAINERS, true) || $parent === $p['uuid'])) {
                $errors["{$pp}.parent"][] = __('panels.invalid_parent');
            }
            if ($type === 'tab' && ($parent === null || $types[$parent] !== 'tabs')) {
                $errors["{$pp}.parent"][] = __('panels.tab_outside_tabs');
            }
            $config = (array) ($p['config'] ?? []);
            switch ($type) {
                case 'derived_fields':
                    $paths = array_values((array) ($config['paths'] ?? []));
                    if ($paths === [] || count($paths) > 30) {
                        $errors["{$pp}.config.paths"][] = __('panels.paths_required');
                    }
                    foreach ($paths as $j => $path) {
                        if (! is_array($path) || $this->paths->resolve($rt, $path) === null) {
                            $errors["{$pp}.config.paths.{$j}"][] = __('views.unknown_path');
                        }
                    }
                    $config = ['paths' => $paths];
                    break;
                case 'related_table':
                case 'summary_widget':
                    $source = is_string($config['source'] ?? null) ? $this->runtimes->forUuid(strtolower($config['source'])) : null;
                    $via = $source === null ? null : ($source->fields[$source->keys[$config['via'] ?? ''] ?? ''] ?? null);
                    if ($source === null || $via === null || $source->targetFormUuid($via) !== $form->uuid || $source->isMultiReference($via)) {
                        $errors["{$pp}.config.via"][] = __('panels.unknown_link');
                        break;
                    }
                    if ($type === 'related_table') {
                        foreach ((array) ($config['columns'] ?? []) as $j => $path) {
                            if (! is_array($path) || $this->paths->resolve($source, $path) === null) {
                                $errors["{$pp}.config.columns.{$j}"][] = __('views.unknown_path');
                            }
                        }
                        $config = ['source' => strtolower($config['source']), 'via' => $config['via'], 'columns' => array_values((array) ($config['columns'] ?? [])), 'limit' => min(100, max(1, (int) ($config['limit'] ?? 20)))];
                    } else {
                        $aggregate = $config['aggregate'] ?? 'count';
                        $field = $source->fields[$source->keys[$config['field'] ?? ''] ?? ''] ?? null;
                        if (! in_array($aggregate, ['count', 'sum', 'avg', 'min', 'max'], true) || ($aggregate !== 'count' && ($field === null || ! in_array($field['type'], ['number', 'decimal', 'currency', 'percent', 'rating', 'slider'], true)))) {
                            $errors["{$pp}.config.aggregate"][] = __('views.aggregate_unsupported');
                        }
                        $config = ['source' => strtolower($config['source']), 'via' => $config['via'], 'aggregate' => $aggregate, 'field' => $config['field'] ?? null, 'format' => $config['format'] ?? null];
                    }
                    break;
                case 'tabs':
                case 'tab':
                case 'section':
                    $config = ['collapsible' => (bool) ($config['collapsible'] ?? false), 'columns' => in_array($config['columns'] ?? 1, [1, 2, 3], true) ? ($config['columns'] ?? 1) : 1];
                    break;
                case 'form_body':
                    $groups = array_values(array_filter((array) ($config['groups'] ?? []), 'is_string'));
                    $config = ['groups' => $groups];
                    break;
                default:
                    $config = [];
            }
            $p['config'] = $config;
            if (($p['visibility'] ?? null) !== null) {
                try {
                    TypeChecker::check($p['visibility'], $resolver, 'boolean');
                } catch (StaticError $e) {
                    $errors["{$pp}.visibility"][] = $e->getMessage();
                }
            }
            foreach (['title' => 255, 'content' => 50000] as $k => $max) {
                foreach ((array) ($p['i18n'][$k] ?? []) as $locale => $v) {
                    if ($v !== null && (! is_string($v) || mb_strlen($v) > $max)) {
                        $errors["{$pp}.i18n.{$k}.{$locale}"][] = __('validation.max.string', ['attribute' => $k, 'max' => $max]);
                    }
                }
            }
        }
        unset($p);
        // No cycles: every chain of parents ends at a root.
        $parents = array_column(array_filter($panels, 'is_array'), 'parent', 'uuid');
        foreach (array_keys($parents) as $start) {
            $seen = [];
            for ($u = $start; $u !== null; $u = $parents[$u] ?? null) {
                if (isset($seen[$u])) {
                    $errors['panels'][] = __('panels.cycle');
                    break 2;
                }
                $seen[$u] = true;
            }
        }
        if (count($panels) > 100) {
            $errors['panels'][] = __('panels.too_many');
        }
        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        return $panels;
    }

    /** @param  array<string, mixed>  $r */
    private function pathLabel(array $r): string
    {
        if ($r['system'] !== null) {
            return __('views.system_'.$r['system']);
        }
        $parts = [];
        foreach ($r['hops'] as $hop) {
            $parts[] = $this->translator->labelOf($hop['field']['i18n']['label'] ?? null, $hop['field']['key']);
        }
        $parts[] = $this->translator->labelOf($r['field']['i18n']['label'] ?? null, $r['field']['key']);

        return implode(' › ', $parts);
    }

    /** @param  array<string, string|null>  $values */
    private function pick(array $values): ?string
    {
        return $this->translator->pick($values);
    }
}
