<?php

declare(strict_types=1);

namespace App\Modules\Forms\Http\Controllers;

use App\Modules\Access\AccessResolver;
use App\Modules\Access\ObjectPermissions;
use App\Modules\Core\I18n\Translator;
use App\Modules\Forms\Models\Application;
use App\Modules\Forms\Models\Form;
use App\Modules\Forms\Models\MenuItem;
use App\Modules\Identity\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * The menu editor (specification §4.13: drag-and-drop ordering and nesting)
 * and the resolved sidebar for the signed-in user.
 */
final class MenuController extends Controller
{
    /** Menu targets available in this release; pages, reports, dashboards and My Work arrive with their modules. */
    public const TYPES = ['form', 'collection', 'link', 'separator', 'header'];

    public function __construct(private readonly Translator $translator, private readonly ObjectPermissions $permissions) {}

    public function show(Application $application): JsonResponse
    {
        Gate::authorize('system.manage_pages_menus');
        $items = MenuItem::query()->where('application_id', $application->id)->orderBy('sort_order')->get();

        return response()->json(['data' => $this->tree($items, null, true)]);
    }

    /** Replaces the whole tree (order, nesting, labels, targets). Items missing from the tree are deleted. */
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
        foreach ($flat as $i => $node) {
            validator($node, [
                'uuid' => ['sometimes', 'nullable', 'uuid'],
                'type' => ['required', Rule::in(self::TYPES)],
                'target' => ['required_if:type,form,collection', 'nullable', 'uuid'],
                'url' => ['required_if:type,link', 'nullable', 'string', 'max:2048', 'regex:#^(https://|/)[^\s]*$#'],
                'open_in_new_tab' => ['sometimes', 'boolean'],
                'icon' => ['sometimes', 'nullable', 'string', 'regex:/^[a-z0-9 -]{0,64}$/'],
                'label' => ['required_unless:type,separator', 'array'],
                'label.*' => ['nullable', 'string', 'max:255'],
                'is_active' => ['sometimes', 'boolean'],
            ])->validate();
        }
        DB::transaction(function () use ($application, $flat): void {
            $keep = [];
            $ids = [];
            foreach ($flat as $node) {
                $item = isset($node['uuid']) ? MenuItem::query()->where('application_id', $application->id)->where('uuid', $node['uuid'])->first() : null;
                $item ??= new MenuItem(['application_id' => $application->id]);
                $targetId = null;
                if (in_array($node['type'], ['form', 'collection'], true)) {
                    $targetId = Form::query()->where('uuid', $node['target'])->where('kind', $node['type'])->value('id');
                    if ($targetId === null) {
                        throw ValidationException::withMessages(['items' => __('forms.menu_target_missing')]);
                    }
                }
                $item->fill([
                    'application_id' => $application->id,
                    'parent_id' => $node['_parent'] === null ? null : $ids[$node['_parent']],
                    'type' => $node['type'],
                    'target_type' => $targetId === null ? null : 'form',
                    'target_id' => $targetId,
                    'url' => $node['type'] === 'link' ? $node['url'] : null,
                    'open_in_new_tab' => $node['open_in_new_tab'] ?? false,
                    'icon' => $node['icon'] ?? null,
                    'sort_order' => $node['_order'],
                    'is_active' => $node['is_active'] ?? true,
                ])->save();
                $ids[$node['_index']] = $item->id;
                $keep[] = $item->id;
                $item->setTranslations('label', $node['label'] ?? []);
                $this->permissions->registerMenuItem($item->id, $item->uuid);
            }
            $removed = MenuItem::query()->where('application_id', $application->id)->whereNotIn('id', $keep ?: [0])->get();
            foreach ($removed->sortByDesc('id') as $r) {
                MenuItem::query()->where('parent_id', $r->id)->update(['parent_id' => null]);
                $this->permissions->forget('menu.'.$r->uuid);
                $r->delete();
            }
        });

        return $this->show($application);
    }

    /** The sidebar of the signed-in user: active applications they may use and the menu items they may see. */
    public function navigation(AccessResolver $access): JsonResponse
    {
        /** @var User $user */
        $user = Auth::user();
        $apps = Application::query()->where('status', 'active')->orderBy('sort_order')->orderBy('key')->get()
            ->filter(static fn (Application $a) => $access->allows($user, "app.{$a->uuid}.access"));
        $names = $this->translator->many('application', $apps->pluck('id')->map(fn ($i) => (int) $i)->all(), ['name']);
        $out = [];
        foreach ($apps as $app) {
            $items = MenuItem::query()->where('application_id', $app->id)->where('is_active', true)->orderBy('sort_order')->get();
            $formUuids = Form::query()->whereIn('id', $items->pluck('target_id')->filter())->whereIn('state', ['published'])->pluck('uuid', 'id')->all();
            $visible = $items->filter(function (MenuItem $i) use ($access, $user, $formUuids): bool {
                if (in_array($i->type, ['form', 'collection'], true)) {
                    $uuid = $formUuids[$i->target_id] ?? null;

                    return $uuid !== null && ($access->allows($user, "menu.{$i->uuid}.view") || $access->allows($user, "form.{$uuid}.view"));
                }

                return $access->allows($user, "menu.{$i->uuid}.view") || in_array($i->type, ['separator', 'header'], true);
            });
            $tree = $this->tree($visible, null, false, $formUuids);
            $tree = $this->prune($tree);
            if ($tree !== [] || $app->maintenance_mode) {
                $out[] = ['uuid' => $app->uuid, 'key' => $app->key, 'name' => $names[$app->id]['name'] ?? $app->key, 'icon' => $app->icon, 'color' => $app->color, 'maintenance' => $app->maintenance_mode, 'items' => $tree];
            }
        }

        return response()->json(['data' => $out]);
    }

    /**
     * @param  Collection<int, MenuItem>  $items
     * @param  array<int, string>  $formUuids
     * @return list<array<string, mixed>>
     */
    private function tree($items, ?int $parent, bool $admin, array $formUuids = []): array
    {
        $labels = $this->translator->many('menu_item', $items->pluck('id')->map(fn ($i) => (int) $i)->all(), ['label']);
        if ($admin) {
            $formUuids = Form::query()->whereIn('id', $items->pluck('target_id')->filter())->pluck('uuid', 'id')->all();
        }
        $build = function (?int $parent) use (&$build, $items, $labels, $admin, $formUuids): array {
            return $items->filter(static fn (MenuItem $i) => $i->parent_id === $parent)->map(fn (MenuItem $i): array => array_filter([
                'uuid' => $i->uuid,
                'type' => $i->type,
                'target' => $i->target_id === null ? null : ($formUuids[$i->target_id] ?? null),
                'url' => $i->url,
                'open_in_new_tab' => $i->open_in_new_tab,
                'icon' => $i->icon,
                'label' => $labels[$i->id]['label'] ?? null,
                'labels' => $admin ? $i->translationsFor('label') : null,
                'is_active' => $admin ? $i->is_active : null,
                'permission' => $admin ? "menu.{$i->uuid}.view" : null,
                'children' => $build($i->id),
            ], static fn ($v) => $v !== null))->values()->all();
        };

        return $build($parent);
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
        if ($depth > 5) {
            throw ValidationException::withMessages(['items' => __('forms.menu_too_deep')]);
        }
        foreach (array_values($nodes) as $order => $node) {
            $index = count($flat);
            $clean = array_diff_key(is_array($node) ? $node : [], ['_parent' => true, '_order' => true, '_index' => true, 'children' => true]);
            $flat[] = ['_parent' => $parent, '_order' => $order, '_index' => $index] + $clean;
            $this->flatten(is_array($node['children'] ?? null) ? $node['children'] : [], $index, $depth + 1, $flat);
        }
    }
}
