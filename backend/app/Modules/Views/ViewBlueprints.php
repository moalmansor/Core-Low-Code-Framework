<?php

declare(strict_types=1);

namespace App\Modules\Views;

use App\Modules\Audit\AuditWriter;
use App\Modules\Blueprints\BlueprintService;
use App\Modules\Blueprints\Models\Blueprint;
use App\Modules\Blueprints\Models\BlueprintInstance;
use App\Modules\Blueprints\Models\BlueprintVersion;
use App\Modules\Core\Tenancy\TenantContext;
use App\Modules\Forms\Models\Form;
use App\Modules\Records\Runtime\FormRuntime;
use App\Modules\Records\Runtime\FormRuntimes;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Blueprints of table views (specification §4.30, Phase 3): a view saved as a
 * blueprint holds its settings, columns and filters addressed by relation
 * path, so it applies to any form with the same field keys. Creating a view
 * from it skips columns and filters whose paths the target form lacks (and
 * reports them). New versions propagate with the same three-way merge as
 * forms: per column, filter and setting, local changes are kept and listed as
 * conflicts. Views have no draft, so propagated changes take effect when the
 * admin applies them after the preview (ADR-0034).
 */
final class ViewBlueprints
{
    private const PROPS = ['i18n', 'pageSize', 'defaultSort', 'showTotals', 'columnChooser', 'globalSearch', 'rowOptions', 'includeInQueues'];

    public function __construct(
        private readonly ViewDocument $views,
        private readonly FormRuntimes $runtimes,
        private readonly RelationPaths $paths,
        private readonly AuditWriter $audit,
        private readonly TenantContext $tenant,
    ) {}

    /** @param  array{name: array<string, string>, description?: array<string, string>, category?: string|null, tags?: list<string>, is_library?: bool}  $meta */
    public function create(string $viewUuid, array $meta): Blueprint
    {
        [$form, $view] = $this->view($viewUuid);

        return DB::transaction(function () use ($view, $meta): Blueprint {
            $bp = new Blueprint([
                'kind' => 'view', 'category' => $meta['category'] ?? null, 'tags' => $meta['tags'] ?? [],
                'is_library' => $meta['is_library'] ?? false, 'source_type' => 'view', 'source_id' => (int) DB::table('views')->where('uuid', $view['uuid'])->value('id'),
            ]);
            $bp->organization_id = $this->tenant->organizationId();
            $bp->save();
            $bp->setTranslations('name', $meta['name']);
            if (isset($meta['description'])) {
                $bp->setTranslations('description', $meta['description']);
            }
            $this->addVersion($bp, $view['uuid'], null);

            return $bp->refresh();
        });
    }

    public function addVersion(Blueprint $bp, ?string $viewUuid, ?string $changelog): BlueprintVersion
    {
        $viewUuid ??= DB::table('views')->where('id', $bp->source_id)->value('uuid');
        if ($viewUuid === null || $bp->kind !== 'view') {
            throw ValidationException::withMessages(['source' => __('blueprints.source_missing')]);
        }
        [$form, $view] = $this->view(strtolower((string) $viewUuid));
        $content = $this->capture($form, $view);
        $hash = BlueprintService::hash($content);
        $current = $bp->current_version_id === null ? null : BlueprintVersion::query()->find($bp->current_version_id);
        if ($current !== null && hash_equals($current->content_hash, $hash)) {
            throw ValidationException::withMessages(['source' => __('blueprints.no_changes')]);
        }

        return DB::transaction(function () use ($bp, $content, $hash, $changelog): BlueprintVersion {
            $number = (int) BlueprintVersion::query()->where('blueprint_id', $bp->id)->max('version') + 1;
            $version = BlueprintVersion::query()->create(['blueprint_id' => $bp->id, 'version' => $number, 'content' => $content, 'content_hash' => $hash, 'include_mode' => 'structure', 'changelog' => $changelog]);
            $bp->forceFill(['current_version_id' => $version->id])->save();
            $this->audit->record('blueprint.version_created', 'config', null, 'blueprint', $bp->id, ['version' => $number, 'kind' => 'view']);

            return $version;
        });
    }

    /**
     * Creates a view in a form from a blueprint version.
     *
     * @param  array<string, string>  $name
     * @return array{view: string, skipped: list<string>}
     */
    public function instantiate(Blueprint $bp, BlueprintVersion $version, Form $form, string $key, array $name): array
    {
        $rt = $this->runtimes->forForm($form);
        if ($rt === null) {
            throw ValidationException::withMessages(['form' => __('views.publish_first')]);
        }
        $views = $this->views->load($form);
        if (in_array($key, array_column($views, 'key'), true)) {
            throw ValidationException::withMessages(['key' => __('validation.unique', ['attribute' => 'key'])]);
        }
        $uuid = BlueprintService::derive($form->uuid, $bp->uuid);
        [$view, $skipped] = $this->fit($rt, $version->content['view'], $uuid);
        $view['key'] = $key;
        $view['i18n']['name'] = $name;
        $view['default'] = $views === [];
        $view['priority'] = count($views);

        return DB::transaction(function () use ($bp, $version, $form, $views, $view, $skipped): array {
            $this->views->save($form, [...$views, $view], (int) Auth::id());
            $viewId = (int) DB::table('views')->where('uuid', $view['uuid'])->value('id');
            BlueprintInstance::query()->create(['blueprint_id' => $bp->id, 'blueprint_version_id' => $version->id, 'object_type' => 'view', 'object_id' => $viewId, 'include_mode' => 'structure', 'is_detached' => false]);
            $this->audit->record('blueprint.instantiated', 'config', null, 'blueprint', $bp->id, ['version' => $version->version, 'view' => $view['uuid'], 'form' => $form->uuid, 'skipped' => count($skipped)]);

            return ['view' => $view['uuid'], 'skipped' => $skipped];
        });
    }

    /** @return list<array<string, mixed>> */
    public function preview(Blueprint $bp): array
    {
        $out = [];
        foreach ($this->outdated($bp) as [$instance, $form, $current]) {
            $plan = $this->plan($bp, $instance, $form, $current);
            $out[] = [
                'instance' => $instance->uuid,
                'form' => ['uuid' => $form->uuid, 'key' => $form->key, 'name' => $form->translate('name'), 'state' => $form->state],
                'view' => ['uuid' => $current['uuid'], 'key' => $current['key']],
                'from_version' => (int) BlueprintVersion::query()->whereKey($instance->baseVersionId())->value('version'),
                'changes' => array_map(static fn ($c) => array_diff_key($c, ['value' => true]), $plan['changes']),
            ];
        }

        return $out;
    }

    /**
     * @param  list<string>|null  $instanceUuids
     * @return list<array<string, mixed>>
     */
    public function propagate(Blueprint $bp, ?array $instanceUuids): array
    {
        $results = [];
        foreach ($this->outdated($bp) as [$instance, $form, $current]) {
            if ($instanceUuids !== null && ! in_array($instance->uuid, $instanceUuids, true)) {
                continue;
            }
            $plan = $this->plan($bp, $instance, $form, $current);
            $conflicts = array_values(array_filter($plan['changes'], static fn ($c) => $c['status'] === 'conflict'));
            $applied = array_values(array_filter($plan['changes'], static fn ($c) => $c['status'] === 'apply'));
            try {
                if ($applied !== []) {
                    $views = array_map(static fn ($v) => $v['uuid'] === $current['uuid'] ? $plan['view'] : $v, $this->views->load($form));
                    $this->views->save($form, $views, (int) Auth::id());
                }
                $instance->forceFill(['last_propagated_version_id' => $bp->current_version_id])->save();
                $this->audit->record('blueprint.propagated', 'config', null, 'form', $form->id, ['blueprint' => $bp->uuid, 'view' => $current['uuid'], 'applied' => count($applied), 'conflicts' => count($conflicts)]);
                $results[] = ['instance' => $instance->uuid, 'form' => $form->uuid, 'status' => 'applied', 'applied' => count($applied), 'conflicts' => array_map(static fn ($c) => array_diff_key($c, ['value' => true]), $conflicts)];
            } catch (ValidationException $e) {
                $results[] = ['instance' => $instance->uuid, 'form' => $form->uuid, 'status' => 'failed', 'errors' => $e->errors()];
            }
        }

        return $results;
    }

    /**
     * @param  array<string, mixed>  $content
     * @return array{0: array<string, mixed>, 1: list<string>}
     */
    private function fit(FormRuntime $rt, array $content, string $uuid): array
    {
        $skipped = [];
        $view = ['uuid' => $uuid, 'key' => '', 'default' => false, 'priority' => 0] + array_intersect_key($content, array_flip(self::PROPS)) + ['columns' => [], 'filters' => []];
        foreach (['columns', 'filters'] as $list) {
            $view[$list] = [];
            foreach ($content[$list] ?? [] as $item) {
                $path = $item['path'];
                if (($path[0] ?? '') !== '@justification' && $this->paths->resolve($rt, $path) === null) {
                    $skipped[] = implode('.', $path);

                    continue;
                }
                $view[$list][] = $item + ['uuid' => BlueprintService::derive($uuid, ViewDocument::pathHash($path))];
            }
        }
        $view['defaultSort'] = array_values(array_filter($view['defaultSort'] ?? [], fn ($s) => $this->paths->resolve($rt, $s['path'] ?? []) !== null));

        return [$view, $skipped];
    }

    /** @return array<string, mixed> */
    private function capture(Form $form, array $view): array
    {
        $strip = static fn (array $items) => array_map(static fn ($i) => array_diff_key($i, ['uuid' => true]), $items);

        return [
            'format' => BlueprintService::FORMAT, 'kind' => 'view', 'source' => $view['uuid'], 'sourceForm' => $form->uuid, 'suggestedKey' => $view['key'],
            'view' => array_intersect_key($view, array_flip(self::PROPS)) + ['columns' => $strip($view['columns']), 'filters' => $strip($view['filters'])],
        ];
    }

    /** @return array{0: Form, 1: array<string, mixed>} */
    private function view(string $uuid): array
    {
        $row = DB::table('views')->where('uuid', $uuid)->first(['form_id']);
        $form = $row === null ? null : Form::query()->find($row->form_id);
        $view = $form === null ? null : collect($this->views->load($form))->firstWhere('uuid', $uuid);
        if ($form === null || $view === null) {
            throw ValidationException::withMessages(['source' => __('blueprints.source_missing')]);
        }

        return [$form, json_decode((string) json_encode($view), true)];
    }

    /** @return list<array{0: BlueprintInstance, 1: Form, 2: array<string, mixed>}> */
    private function outdated(Blueprint $bp): array
    {
        $out = [];
        foreach (BlueprintInstance::query()->where('blueprint_id', $bp->id)->where('is_detached', false)->where('object_type', 'view')->orderBy('id')->get() as $instance) {
            if ($instance->baseVersionId() === $bp->current_version_id) {
                continue;
            }
            $row = DB::table('views')->where('id', $instance->object_id)->first(['uuid', 'form_id']);
            $form = $row === null ? null : Form::query()->find($row->form_id);
            $current = $form === null ? null : collect($this->views->load($form))->firstWhere('uuid', strtolower((string) $row->uuid));
            if ($form !== null && $current !== null && $this->runtimes->forForm($form) !== null) {
                $out[] = [$instance, $form, json_decode((string) json_encode($current), true)];
            }
        }

        return $out;
    }

    /**
     * Three-way merge of the blueprint's base → current view onto the view.
     *
     * @param  array<string, mixed>  $local
     * @return array{view: array<string, mixed>, changes: list<array<string, mixed>>}
     */
    private function plan(Blueprint $bp, BlueprintInstance $instance, Form $form, array $local): array
    {
        $rt = $this->runtimes->forForm($form);
        $base = $this->fit($rt, BlueprintVersion::query()->findOrFail($instance->baseVersionId())->content['view'], $local['uuid'])[0];
        $target = $this->fit($rt, BlueprintVersion::query()->findOrFail($bp->current_version_id)->content['view'], $local['uuid'])[0];
        $changes = [];
        $view = $local;
        foreach (['columns', 'filters'] as $list) {
            $key = static fn (array $items) => array_column(array_map(static fn ($i) => $i + ['_k' => implode('.', $i['path'])], $items), null, '_k');
            $b = $key($base[$list]);
            $t = $key($target[$list]);
            $l = $key($local[$list]);
            $strip = static fn (?array $i) => $i === null ? null : array_diff_key($i, ['uuid' => true, '_k' => true]);
            foreach (array_unique([...array_keys($b), ...array_keys($t)]) as $k) {
                [$bo, $to, $lo] = [$strip($b[$k] ?? null), $strip($t[$k] ?? null), $strip($l[$k] ?? null)];
                if (self::same($bo, $to)) {
                    continue;
                }
                $status = match (true) {
                    $bo === null && $lo === null, $to === null && self::same($lo, $bo), $bo !== null && $to !== null && self::same($lo, $bo) => 'apply',
                    self::same($lo, $to) => 'skip',
                    default => 'conflict',
                };
                $action = $bo === null ? 'add' : ($to === null ? 'remove' : 'update');
                $changes[] = ['kind' => rtrim($list, 's'), 'uuid' => null, 'label' => (string) $k, 'action' => $action, 'status' => $status, 'reason' => $status === 'conflict' ? 'modified_locally' : null, 'value' => $t[$k] ?? null];
                if ($status === 'apply') {
                    $items = array_values(array_filter($view[$list], static fn ($i) => implode('.', $i['path']) !== $k));
                    if ($action !== 'remove') {
                        $items[] = array_diff_key($t[$k], ['_k' => true]);
                    }
                    $view[$list] = $items;
                }
            }
        }
        foreach (self::PROPS as $prop) {
            if ($prop === 'i18n' || self::same($base[$prop] ?? null, $target[$prop] ?? null)) {
                continue;
            }
            $status = self::same($local[$prop] ?? null, $base[$prop] ?? null) ? 'apply' : (self::same($local[$prop] ?? null, $target[$prop] ?? null) ? 'skip' : 'conflict');
            $changes[] = ['kind' => 'view', 'uuid' => null, 'label' => $prop, 'action' => 'update', 'status' => $status, 'reason' => $status === 'conflict' ? 'modified_locally' : null, 'value' => $target[$prop] ?? null];
            if ($status === 'apply') {
                $view[$prop] = $target[$prop] ?? null;
            }
        }

        return ['view' => $view, 'changes' => $changes];
    }

    private static function same(mixed $a, mixed $b): bool
    {
        $canon = static function (mixed $v) use (&$canon): mixed {
            if (! is_array($v)) {
                return $v;
            }
            if (! array_is_list($v)) {
                ksort($v);
            }

            return array_map($canon, $v);
        };

        return json_encode($canon($a)) === json_encode($canon($b));
    }
}
