<?php

declare(strict_types=1);

namespace App\Modules\Forms\Http\Controllers;

use App\Modules\Access\AccessCache;
use App\Modules\Access\AccessResolver;
use App\Modules\Access\ObjectPermissions;
use App\Modules\Access\RecordScope;
use App\Modules\Assignment\MyWork;
use App\Modules\Core\I18n\Translator;
use App\Modules\Experience\Maintenance;
use App\Modules\Experience\Models\HomeScreen;
use App\Modules\Experience\Models\Page;
use App\Modules\Experience\Pages;
use App\Modules\Experience\PersonRules;
use App\Modules\Forms\Conditions\OwnedConditions;
use App\Modules\Forms\Models\Application;
use App\Modules\Forms\Models\Form;
use App\Modules\Forms\Models\MenuItem;
use App\Modules\Identity\Models\User;
use App\Modules\Records\Runtime\FormRuntimes;
use App\Modules\Reports\Models\Dashboard;
use App\Modules\Reports\Models\Report;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * The navigation builder (specification §4.13, §4.29, ADR-0054): nested
 * menus with drag-and-drop ordering, icons, separators and section headers;
 * items for forms, collections, pages, reports, dashboards, My Work and
 * links; badges with live counts; and visibility by a rule on the person
 * (roles, department, attributes) on top of the permission of what the item
 * opens. Also the resolved sidebar of the signed-in user.
 */
final class MenuController extends Controller
{
    public const TYPES = ['form', 'collection', 'page', 'report', 'dashboard', 'my_work', 'link', 'separator', 'header'];

    /** Items that open an object; the object's kind is stored as the target type. */
    private const TARGETS = ['form' => 'form', 'collection' => 'form', 'page' => 'page', 'report' => 'report', 'dashboard' => 'dashboard'];

    /** What a badge may count. */
    public const BADGES = ['my_work', 'approvals', 'overdue', 'records'];

    /** Practically unlimited nesting, bounded so a tree cannot be made pathological. */
    public const MAX_DEPTH = 20;

    /** Counts above this are shown as "999+". */
    private const BADGE_CAP = 999;

    public function __construct(
        private readonly Translator $translator,
        private readonly ObjectPermissions $permissions,
        private readonly OwnedConditions $conditions,
        private readonly PersonRules $rules,
    ) {}

    public function show(Application $application): JsonResponse
    {
        Gate::authorize('system.manage_pages_menus');
        $items = MenuItem::query()->where('application_id', $application->id)->orderBy('sort_order')->get();

        return response()->json(['data' => $this->tree($items, true, $this->targets($items))]);
    }

    /** Replaces the whole tree (order, nesting, labels, targets, badges, rules). Items missing from the tree are deleted. */
    public function update(Request $request, Application $application): JsonResponse
    {
        Gate::authorize('system.manage_pages_menus');
        $data = $request->validate([
            'items' => ['present', 'array', 'max:500'],
        ]);
        $flat = [];
        $this->flatten($data['items'], null, 0, $flat);
        if (count($flat) > 500) {
            throw ValidationException::withMessages(['items' => __('validation.max.array', ['attribute' => 'items', 'max' => 500])]);
        }
        foreach ($flat as $node) {
            validator($node, [
                'uuid' => ['sometimes', 'nullable', 'uuid'],
                'type' => ['required', Rule::in(self::TYPES)],
                'target' => ['required_if:type,form,collection,page,report,dashboard', 'nullable', 'uuid'],
                'url' => ['required_if:type,link', 'nullable', 'string', 'max:2048', 'regex:#^(https://|/)[^\s]*$#', 'not_regex:#^//#'],
                'open_in_new_tab' => ['sometimes', 'boolean'],
                'icon' => ['sometimes', 'nullable', 'string', 'regex:/^[a-z0-9 -]{0,64}$/'],
                'label' => ['required_unless:type,separator', 'array'],
                'label.*' => ['nullable', 'string', 'max:255'],
                'is_active' => ['sometimes', 'boolean'],
                'badge' => ['sometimes', 'nullable', 'array'],
                'badge.kind' => ['required_with:badge', Rule::in(self::BADGES)],
                'condition' => ['sometimes', 'nullable', 'array'],
            ])->validate();
            if (($node['badge']['kind'] ?? null) === 'records' && ! in_array($node['type'], ['form', 'collection'], true)) {
                throw ValidationException::withMessages(['items' => __('experience.bad_badge')]);
            }
            if (isset($node['condition'])) {
                // Only the person can be referred to: no record is open while the menu is built.
                $this->rules->check($node['condition'], 'items');
            }
        }
        DB::transaction(function () use ($application, $flat): void {
            $keep = [];
            $ids = [];
            foreach ($flat as $node) {
                $item = isset($node['uuid']) ? MenuItem::query()->where('application_id', $application->id)->where('uuid', strtolower($node['uuid']))->first() : null;
                $item ??= new MenuItem(['application_id' => $application->id]);
                $targetId = isset(self::TARGETS[$node['type']]) ? $this->targetId($node['type'], (string) $node['target'], $application) : null;
                $item->fill([
                    'application_id' => $application->id,
                    'parent_id' => $node['_parent'] === null ? null : $ids[$node['_parent']],
                    'type' => $node['type'],
                    'target_type' => self::TARGETS[$node['type']] ?? null,
                    'target_id' => $targetId,
                    'url' => $node['type'] === 'link' ? $node['url'] : null,
                    'open_in_new_tab' => $node['type'] === 'link' && ($node['open_in_new_tab'] ?? false),
                    'icon' => $node['icon'] ?? null,
                    'sort_order' => $node['_order'],
                    'is_active' => $node['is_active'] ?? true,
                    'badge' => in_array($node['type'], ['separator', 'header'], true) || ! isset($node['badge']['kind']) ? null : ['kind' => $node['badge']['kind']],
                ])->save();
                $condition = $this->conditions->put(null, 'menu_item', $item->id, in_array($node['type'], ['separator'], true) ? null : ($node['condition'] ?? null), $item->visibility_condition_id);
                if ($condition !== $item->visibility_condition_id) {
                    $item->forceFill(['visibility_condition_id' => $condition])->save();
                }
                $ids[$node['_index']] = $item->id;
                $keep[] = $item->id;
                $item->setTranslations('label', $node['label'] ?? []);
                $this->permissions->registerMenuItem($item->id, $item->uuid);
            }
            $removed = MenuItem::query()->where('application_id', $application->id)->whereNotIn('id', $keep ?: [0])->get();
            foreach ($removed->sortByDesc('id') as $r) {
                MenuItem::query()->where('parent_id', $r->id)->update(['parent_id' => null]);
                $this->permissions->forget('menu.'.$r->uuid);
                $condition = $r->visibility_condition_id;
                $r->delete();
                $this->conditions->forget($condition);
            }
        });
        Cache::increment('menu:epoch');

        return $this->show($application);
    }

    /** The sidebar of the signed-in user: active applications they may use and the menu items they may see. */
    public function navigation(AccessResolver $access, Pages $pages, Maintenance $maintenance): JsonResponse
    {
        $user = $this->user();
        $apps = Application::query()->where('status', 'active')->orderBy('sort_order')->orderBy('key')->get()
            ->filter(static fn (Application $a) => $access->allows($user, "app.{$a->uuid}.access"));
        $names = $this->translator->many('application', $apps->pluck('id')->map(fn ($i) => (int) $i)->all(), ['name']);
        $withHome = array_flip(HomeScreen::query()->where('is_active', true)->whereNotNull('application_id')->distinct()->pluck('application_id')->map(static fn ($v) => (int) $v)->all());
        $out = [];
        foreach ($apps as $app) {
            $items = MenuItem::query()->where('application_id', $app->id)->where('is_active', true)->orderBy('sort_order')->get();
            $targets = $this->targets($items);
            $asts = $this->conditions->many($items->pluck('visibility_condition_id')->all());
            $visible = $items->filter(function (MenuItem $i) use ($access, $user, $targets, $pages, $asts): bool {
                // A rule that has gone missing hides the item rather than showing it to everyone.
                if ($i->visibility_condition_id !== null && ! $this->rules->passes($asts[(int) $i->visibility_condition_id] ?? ['k' => 'lit', 't' => 'boolean', 'v' => false], $user)) {
                    return false;
                }
                $target = $targets[$i->target_type.':'.$i->target_id] ?? null;

                return match ($i->type) {
                    'form', 'collection' => $target !== null && $target['state'] === 'published'
                        && ($access->allows($user, "menu.{$i->uuid}.view") || $access->allows($user, "form.{$target['uuid']}.view")),
                    'page' => $target !== null && $pages->viewable($target['model'], $user),
                    'report' => $target !== null && $access->allows($user, "report.{$target['uuid']}.view"),
                    'dashboard' => $target !== null && $target['state'] === 'active' && $access->allows($user, "dashboard.{$target['uuid']}.view"),
                    'separator', 'header', 'my_work' => true,
                    default => $access->allows($user, "menu.{$i->uuid}.view"),
                };
            });
            $tree = $this->prune($this->tree($visible, false, $targets));
            $home = isset($withHome[(int) $app->id]);
            if ($tree !== [] || $home || $maintenance->applicationOffline($app)) {
                $out[] = [
                    'uuid' => $app->uuid, 'key' => $app->key, 'name' => $names[$app->id]['name'] ?? Translator::humanize((string) $app->key), 'icon' => $app->icon, 'color' => $app->color,
                    'maintenance' => $maintenance->applicationOffline($app), 'home' => $home, 'items' => $tree,
                ];
            }
        }

        return response()->json(['data' => $out]);
    }

    /**
     * Live counts for the badges of the items the person sees, cached for a
     * short while per person. Records are counted within the person's own
     * record scope.
     */
    public function badges(MyWork $work, FormRuntimes $runtimes, RecordScope $scope, AccessResolver $access, AccessCache $accessCache): JsonResponse
    {
        $user = $this->user();
        // A menu change or an access change starts afresh; otherwise counts may be up to 30 seconds old.
        $key = 'menu:badges:'.$user->id.':'.(int) Cache::get('menu:epoch', 0).':'.$accessCache->epoch();
        $counts = Cache::remember($key, 30, function () use ($user, $work, $runtimes, $scope, $access): array {
            $items = MenuItem::query()->where('is_active', true)->whereNotNull('badge')->get();
            $out = [];
            $memo = [];
            foreach ($items as $i) {
                $kind = (string) ($i->badge['kind'] ?? '');
                $n = match ($kind) {
                    'my_work' => $memo['work'] ??= $work->count($user, 'work'),
                    'approvals' => $memo['approval'] ??= $work->count($user, 'approval'),
                    'overdue' => $memo['overdue'] ??= $work->visible($user, ['overdue' => true])['items']->count(),
                    'records' => $this->recordCount($i, $user, $runtimes, $scope, $access),
                    default => null,
                };
                if ($n !== null) {
                    $out[strtolower($i->uuid)] = min($n, self::BADGE_CAP + 1);
                }
            }

            return $out;
        });

        return response()->json(['data' => (object) $counts, 'meta' => ['cap' => self::BADGE_CAP]]);
    }

    private function recordCount(MenuItem $i, User $user, FormRuntimes $runtimes, RecordScope $scope, AccessResolver $access): ?int
    {
        $form = $i->target_id === null ? null : Form::query()->find($i->target_id);
        if ($form === null || $form->state !== 'published' || ! $access->allows($user, "form.{$form->uuid}.view")) {
            return null;
        }
        $rt = $runtimes->forForm($form);
        if ($rt === null) {
            return null;
        }

        $q = $scope->apply(DB::table($rt->table)->whereNull('deleted_at'), $rt, $user, 'view')->select('id')->limit(self::BADGE_CAP + 1);

        return DB::query()->fromSub($q, 'badge_rows')->count();
    }

    private function targetId(string $type, string $uuid, Application $application): int
    {
        $uuid = strtolower($uuid);
        $id = match ($type) {
            'form', 'collection' => Form::query()->where('uuid', $uuid)->where('kind', $type)->value('id'),
            'page' => Pages::live()->where('uuid', $uuid)->where('application_id', $application->id)->value('id'),
            'report' => Report::query()->where('uuid', $uuid)->value('id'),
            'dashboard' => Dashboard::query()->where('uuid', $uuid)->value('id'),
            default => null,
        };
        if ($id === null) {
            throw ValidationException::withMessages(['items' => __('experience.menu_target_missing')]);
        }

        return (int) $id;
    }

    /**
     * The objects the items open, keyed "type:id".
     *
     * @param  Collection<int, MenuItem>  $items
     * @return array<string, array{uuid: string, state: string, model: mixed}>
     */
    private function targets(Collection $items): array
    {
        $out = [];
        foreach ($items->whereNotNull('target_id')->groupBy('target_type') as $type => $group) {
            $ids = $group->pluck('target_id')->unique()->values()->all();
            $rows = match ($type) {
                'form' => Form::query()->whereIn('id', $ids)->get()->map(static fn (Form $f) => [$f->id, ['uuid' => strtolower((string) $f->uuid), 'state' => (string) $f->state, 'model' => $f]]),
                'page' => Page::query()->whereIn('id', $ids)->whereNull('deleted_at')->get()->map(static fn (Page $p) => [$p->id, ['uuid' => strtolower($p->uuid), 'state' => $p->is_published ? 'published' : 'draft', 'model' => $p]]),
                'report' => Report::query()->whereIn('id', $ids)->get()->map(static fn (Report $r) => [$r->id, ['uuid' => strtolower($r->uuid), 'state' => 'active', 'model' => $r]]),
                'dashboard' => Dashboard::query()->whereIn('id', $ids)->get()->map(static fn (Dashboard $d) => [$d->id, ['uuid' => strtolower($d->uuid), 'state' => $d->is_active ? 'active' : 'inactive', 'model' => $d]]),
                default => collect(),
            };
            foreach ($rows as [$id, $row]) {
                $out[$type.':'.$id] = $row;
            }
        }

        return $out;
    }

    /**
     * @param  Collection<int, MenuItem>  $items
     * @param  array<string, array{uuid: string, state: string, model: mixed}>  $targets
     * @return list<array<string, mixed>>
     */
    private function tree(Collection $items, bool $admin, array $targets): array
    {
        $labels = $this->translator->many('menu_item', $items->pluck('id')->map(fn ($i) => (int) $i)->all(), ['label']);
        $asts = $admin ? $this->conditions->many($items->pluck('visibility_condition_id')->all()) : [];
        $build = function (?int $parent) use (&$build, $items, $labels, $admin, $targets, $asts): array {
            return $items->filter(static fn (MenuItem $i) => $i->parent_id === $parent)->map(fn (MenuItem $i): array => array_filter([
                'uuid' => $i->uuid,
                'type' => $i->type,
                'target' => $i->target_id === null ? null : ($targets[$i->target_type.':'.$i->target_id]['uuid'] ?? null),
                'url' => $i->url,
                'open_in_new_tab' => $i->open_in_new_tab,
                'icon' => $i->icon,
                'label' => $labels[$i->id]['label'] ?? null,
                'labels' => $admin ? $i->translationsFor('label') : null,
                'is_active' => $admin ? $i->is_active : null,
                'permission' => $admin ? "menu.{$i->uuid}.view" : null,
                'badge' => $i->badge === null ? null : ($admin ? $i->badge : true),
                'condition' => $admin && $i->visibility_condition_id !== null ? ($asts[(int) $i->visibility_condition_id] ?? null) : null,
                'children' => $build($i->id),
            ], static fn ($v) => $v !== null))->values()->all();
        };

        return $build(null);
    }

    /** Drops headers left without visible items below them. */
    private function prune(array $nodes): array
    {
        $out = [];
        foreach ($nodes as $n) {
            $n['children'] = $this->prune($n['children'] ?? []);
            if ($n['type'] === 'header' && $n['children'] === []) {
                continue;
            }
            $out[] = $n;
        }

        return $out;
    }

    /**
     * @param  list<array<string, mixed>>  $nodes
     * @param  list<array<string, mixed>>  $flat
     */
    private function flatten(array $nodes, ?int $parent, int $depth, array &$flat): void
    {
        if ($depth >= self::MAX_DEPTH) {
            throw ValidationException::withMessages(['items' => __('forms.menu_too_deep', ['max' => self::MAX_DEPTH])]);
        }
        foreach (array_values($nodes) as $order => $node) {
            $index = count($flat);
            $clean = array_diff_key(is_array($node) ? $node : [], ['_parent' => true, '_order' => true, '_index' => true, 'children' => true]);
            $flat[] = ['_parent' => $parent, '_order' => $order, '_index' => $index] + $clean;
            $this->flatten(is_array($node['children'] ?? null) ? $node['children'] : [], $index, $depth + 1, $flat);
        }
    }

    private function user(): User
    {
        /** @var User $u */
        $u = Auth::user();

        return $u;
    }
}
