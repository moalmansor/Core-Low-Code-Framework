<?php

declare(strict_types=1);

namespace App\Modules\Blueprints;

use App\Modules\Access\ObjectPermissions;
use App\Modules\Actions\ActionDocument;
use App\Modules\Audit\AuditWriter;
use App\Modules\Blueprints\Models\Blueprint;
use App\Modules\Blueprints\Models\BlueprintInstance;
use App\Modules\Blueprints\Models\BlueprintVersion;
use App\Modules\Core\I18n\Translator;
use App\Modules\Core\Tenancy\TenantContext;
use App\Modules\Forms\Definition\PublishedDefinitions;
use App\Modules\Forms\Draft\DraftInvalid;
use App\Modules\Forms\Draft\DraftRepository;
use App\Modules\Forms\FormService;
use App\Modules\Forms\Models\Application;
use App\Modules\Forms\Models\Form;
use App\Modules\Forms\Publishing\PublishService;
use App\Modules\Identity\Models\User;
use App\Modules\Notifications\NotificationRules;
use App\Modules\Workflow\WorkflowDocument;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Whole applications as blueprints, and cloning (specification §4.30,
 * ADR-0059).
 *
 * - A version captures every managed form and collection of the
 *   application: its draft design, workflow and, by the chosen level, its
 *   access rules and grants (structure plus permissions) and its
 *   notification rules and custom actions (everything).
 * - Instantiating makes a new application. Every form, element, status,
 *   transition, rule and action gets a uuid derived from the new
 *   application, so links between the application's own forms point at the
 *   new forms, and later versions can be matched to what was made without
 *   storing a map. Links to forms outside the application must exist here.
 * - The new forms are published in the order their links need; a form that
 *   cannot be published stays a draft and is reported. Notification rules
 *   and actions need a published form and are added after it.
 * - Propagating a newer version merges it into each form three ways (the
 *   draft design, the workflow, and — at the "everything" level — the
 *   rules and actions): what was changed locally is kept and listed as a
 *   conflict, design changes land in the draft, and forms the blueprint
 *   gained are created as drafts.
 * - Cloning an application is the same without keeping a blueprint.
 */
final class ApplicationBlueprints
{
    private const MODES = ['structure' => 0, 'structure_permissions' => 1, 'everything' => 2];

    private const WORKFLOW_LISTS = ['statuses', 'transitions', 'sla'];

    public function __construct(
        private readonly BlueprintService $blueprints,
        private readonly DraftRepository $drafts,
        private readonly WorkflowDocument $workflow,
        private readonly NotificationRules $notifications,
        private readonly ActionDocument $actions,
        private readonly FormService $forms,
        private readonly PublishService $publisher,
        private readonly ObjectPermissions $permissions,
        private readonly AuditWriter $audit,
        private readonly TenantContext $tenant,
        private readonly Translator $translator,
    ) {}

    // ── Capture ──────────────────────────────────────────────────────────

    /**
     * @param  array{name: array<string, string>, description?: array<string, string>, category?: string|null, tags?: list<string>, is_library?: bool, include_mode: string}  $data
     */
    public function create(Application $app, array $data): Blueprint
    {
        return DB::transaction(function () use ($app, $data): Blueprint {
            $bp = new Blueprint([
                'kind' => 'application', 'category' => $data['category'] ?? null, 'tags' => $data['tags'] ?? [],
                'is_library' => $data['is_library'] ?? false, 'source_type' => 'application', 'source_id' => $app->id,
            ]);
            $bp->organization_id = $this->tenant->organizationId();
            $bp->save();
            $bp->setTranslations('name', $data['name']);
            if (isset($data['description'])) {
                $bp->setTranslations('description', $data['description']);
            }
            $this->addVersion($bp, $app, $data['include_mode'], null);

            return $bp->refresh();
        });
    }

    public function addVersion(Blueprint $bp, Application $app, string $includeMode, ?string $changelog): BlueprintVersion
    {
        $content = $this->capture($app, $includeMode);
        $hash = BlueprintService::hash($content);
        $current = $bp->current_version_id === null ? null : BlueprintVersion::query()->find($bp->current_version_id);
        if ($current !== null && hash_equals($current->content_hash, $hash)) {
            throw ValidationException::withMessages(['source' => __('blueprints.no_changes')]);
        }

        return DB::transaction(function () use ($bp, $content, $hash, $includeMode, $changelog): BlueprintVersion {
            $number = (int) BlueprintVersion::query()->where('blueprint_id', $bp->id)->max('version') + 1;
            $version = BlueprintVersion::query()->create([
                'blueprint_id' => $bp->id, 'version' => $number, 'content' => $content, 'content_hash' => $hash,
                'include_mode' => $includeMode, 'changelog' => $changelog,
            ]);
            $bp->forceFill(['current_version_id' => $version->id])->save();
            $this->audit->record('blueprint.version_created', 'config', null, 'blueprint', $bp->id, ['version' => $number, 'include_mode' => $includeMode, 'forms' => count($content['forms'])]);

            return $version;
        });
    }

    /** @return array<string, mixed> */
    public function capture(Application $app, string $includeMode): array
    {
        $forms = Form::query()->where('application_id', $app->id)->where('binding_mode', 'managed')->whereIn('kind', ['form', 'collection'])->orderBy('id')->get();
        if ($forms->isEmpty()) {
            throw ValidationException::withMessages(['source' => __('blueprints.application_empty')]);
        }
        $own = $forms->map(static fn (Form $f) => strtolower($f->uuid))->all();
        $entries = [];
        $dependencies = [];
        foreach ($forms as $form) {
            $entry = $this->blueprints->capture($form, $includeMode);
            $entry['workflow'] = self::plain($this->workflow->load($form));
            if (self::MODES[$includeMode] >= 2) {
                $entry['notifications'] = self::plain($this->notifications->load($form));
                $entry['actions'] = self::plain($this->actions->load($form));
            }
            foreach ($entry['dependencies'] as $d) {
                if (! in_array(strtolower((string) $d), $own, true)) {
                    $dependencies[strtolower((string) $d)] = true;
                }
            }
            $entries[] = $entry;
        }

        return [
            'format' => BlueprintService::FORMAT,
            'kind' => 'application',
            'source' => strtolower($app->uuid),
            'suggestedKey' => $app->key,
            'application' => [
                'name' => $app->translationsFor('name'), 'description' => $app->translationsFor('description'),
                'icon' => $app->icon, 'color' => $app->color, 'dataSharing' => $app->data_sharing_default,
            ],
            'forms' => $entries,
            'dependencies' => array_keys($dependencies),
        ];
    }

    // ── Instantiate and clone ────────────────────────────────────────────

    /**
     * Copies an application without keeping a blueprint.
     *
     * @param  array<string, string>  $name
     * @return array<string, mixed>
     */
    public function cloneApplication(Application $app, string $key, array $name, string $includeMode, User $user): array
    {
        $out = $this->instantiate(null, null, $this->capture($app, $includeMode), $key, $name, $includeMode, $user);
        $this->audit->record('application.cloned', 'config', null, 'application', $out['application']->id, ['from' => strtolower($app->uuid), 'include_mode' => $includeMode]);

        return $out;
    }

    /**
     * Makes a new application from captured content.
     *
     * @param  array<string, mixed>  $content
     * @param  array<string, string>  $name
     * @return array{application: Application, forms: list<array<string, mixed>>, notes: list<string>}
     */
    public function instantiate(?Blueprint $bp, ?BlueprintVersion $version, array $content, string $key, array $name, string $includeMode, User $user): array
    {
        if (self::MODES[$includeMode] > self::MODES[$version->include_mode ?? $includeMode]) {
            throw ValidationException::withMessages(['include' => __('blueprints.include_unavailable')]);
        }
        if (preg_match('/^[a-z][a-z0-9_]{1,47}$/', $key) !== 1 || Application::withTrashed()->where('key', $key)->exists()) {
            throw ValidationException::withMessages(['key' => __('blueprints.application_key_taken')]);
        }
        $missing = array_values(array_filter((array) ($content['dependencies'] ?? []), static fn ($u) => ! Form::query()->where('uuid', $u)->exists()));
        if ($missing !== []) {
            throw ValidationException::withMessages(['dependencies' => __('blueprints.dependencies_missing', ['forms' => implode(', ', $missing)])]);
        }

        // The application and its forms, with their drafts and workflows.
        [$app, $made, $instance] = DB::transaction(function () use ($bp, $version, $content, $key, $name, $includeMode, $user): array {
            $c = (array) $content['application'];
            $app = Application::query()->create([
                'key' => $key, 'icon' => $c['icon'] ?? null, 'color' => $c['color'] ?? null, 'status' => 'active',
                'data_sharing_default' => $c['dataSharing'] ?? 'shared', 'maintenance_mode' => false, 'sort_order' => 0,
            ]);
            $app->setTranslations('name', $name === [] ? (array) ($c['name'] ?? []) : $name);
            $app->setTranslations('description', (array) ($c['description'] ?? []));
            $this->permissions->registerApplication($app->id, $app->uuid);
            $map = self::map($content, strtolower($app->uuid));
            $instance = $bp === null ? null : BlueprintInstance::query()->create([
                'blueprint_id' => $bp->id, 'blueprint_version_id' => $version?->id, 'object_type' => 'application',
                'object_id' => $app->id, 'include_mode' => $includeMode, 'is_detached' => false,
            ]);
            $made = [];
            foreach ($content['forms'] as $entry) {
                $made[] = $this->makeForm($app, $entry, $map, $key, $user, $bp, $version, $includeMode);
            }

            return [$app, $made, $instance];
        });

        // Publishing, in the order the forms' links need.
        $notes = [];
        $results = [];
        foreach ($this->publishOrder($made) as $m) {
            /** @var Form $form */
            $form = $m['form'];
            $state = 'draft';
            $problem = null;
            try {
                $this->publisher->publishNow($form, $user->id, __('blueprints.made_from_blueprint'));
                $state = 'published';
            } catch (Throwable $e) {
                $problem = $e->getMessage();
            }
            $form->refresh();
            if (self::MODES[$includeMode] >= 1 && is_array($m['entry']['access'] ?? null)) {
                foreach ($this->blueprints->applyAccess($form, self::remap($m['entry']['access'], $m['map']), strtolower($form->uuid)) as $skip) {
                    $notes[] = __('blueprints.access_skipped', ['form' => $this->label($form), 'what' => $skip]);
                }
            }
            if (self::MODES[$includeMode] >= 2) {
                if ($state === 'published') {
                    foreach (['notifications' => $this->notifications, 'actions' => $this->actions] as $part => $service) {
                        $list = self::remap((array) ($m['entry'][$part] ?? []), $m['map']);
                        if ($list === []) {
                            continue;
                        }
                        try {
                            $service->save($form, $part === 'actions' ? self::withoutSecrets($list) : $list, $user->id);
                        } catch (Throwable $e) {
                            $notes[] = __("blueprints.{$part}_not_copied", ['form' => $this->label($form), 'reason' => $e->getMessage()]);
                        }
                    }
                } elseif (($m['entry']['notifications'] ?? []) !== [] || ($m['entry']['actions'] ?? []) !== []) {
                    $notes[] = __('blueprints.rules_after_publish', ['form' => $this->label($form)]);
                }
            }
            $results[] = ['uuid' => strtolower($form->uuid), 'key' => $form->key, 'name' => $this->label($form), 'state' => $state, 'problem' => $problem];
        }
        $this->audit->record('blueprint.instantiated', 'config', null, 'application', $app->id, [
            'blueprint' => $bp?->uuid, 'version' => $version?->version, 'include_mode' => $includeMode, 'forms' => count($results), 'instance' => $instance?->uuid,
        ]);

        return ['application' => $app, 'forms' => $results, 'notes' => $notes];
    }

    /**
     * @param  array<string, mixed>  $entry
     * @param  array<string, string>  $map
     * @return array{form: Form, entry: array<string, mixed>, map: array<string, string>, targets: list<string>}
     */
    private function makeForm(Application $app, array $entry, array $map, string $appKey, User $user, ?Blueprint $bp, ?BlueprintVersion $version, string $includeMode): array
    {
        $uuid = $map[strtolower((string) $entry['source'])];
        $doc = self::remap($entry['document'], $map);
        $name = (array) ($doc['form']['i18n']['name'] ?? []);
        $form = $this->forms->create([
            'uuid' => $uuid, 'kind' => (string) $entry['kind'], 'key' => $this->freeKey($appKey.'_'.$entry['suggestedKey']),
            'application' => $app, 'name' => $name, 'collection_type' => $entry['collectionType'] ?? 'table',
        ]);
        $doc['form']['key'] = $form->key;
        $doc['form']['application'] = strtolower($app->uuid);
        $doc['form']['bindingMode'] = 'managed';
        foreach (['numbering' => 'number_sequences', 'calendar' => 'business_calendars'] as $prop => $table) {
            if (isset($doc['form'][$prop]) && ! DB::table($table)->where('uuid', $doc['form'][$prop])->exists()) {
                $doc['form'][$prop] = null;
            }
        }
        try {
            $this->drafts->save($form, $doc, null, null);
        } catch (DraftInvalid $e) {
            throw ValidationException::withMessages(['blueprint' => __('blueprints.content_invalid', ['detail' => $e->errors[0]['message'] ?? ''])]);
        }
        $workflow = self::remap((array) ($entry['workflow'] ?? []), $map);
        if (($workflow['statuses'] ?? []) !== []) {
            $this->workflow->save($form->refresh(), $workflow, $user->id);
        }
        if ($bp !== null) {
            $instance = BlueprintInstance::query()->create([
                'blueprint_id' => $bp->id, 'blueprint_version_id' => $version?->id, 'object_type' => 'form',
                'object_id' => $form->id, 'include_mode' => $includeMode, 'is_detached' => false,
            ]);
            $form->forceFill(['blueprint_instance_id' => $instance->id])->save();
        }
        $targets = [];
        foreach ($doc['relations'] ?? [] as $r) {
            if (is_string($r['target'] ?? null) && $r['target'] !== $uuid) {
                $targets[] = $r['target'];
            }
        }

        return ['form' => $form->refresh(), 'entry' => $entry, 'map' => $map, 'targets' => $targets];
    }

    /**
     * Forms whose links point at other new forms come after them; a cycle
     * keeps the remaining order.
     *
     * @param  list<array{form: Form, entry: array<string, mixed>, map: array<string, string>, targets: list<string>}>  $made
     * @return list<array{form: Form, entry: array<string, mixed>, map: array<string, string>, targets: list<string>}>
     */
    private function publishOrder(array $made): array
    {
        $pending = $made;
        $done = [];
        $ordered = [];
        while ($pending !== []) {
            $progress = false;
            foreach ($pending as $i => $m) {
                $own = array_map(static fn (array $x) => strtolower($x['form']->uuid), $pending);
                if (array_diff(array_intersect($m['targets'], $own), [strtolower($m['form']->uuid)]) === []) {
                    $ordered[] = $m;
                    $done[] = strtolower($m['form']->uuid);
                    unset($pending[$i]);
                    $progress = true;
                }
            }
            if (! $progress) {
                return [...$ordered, ...array_values($pending)];
            }
        }

        return $ordered;
    }

    // ── Propagation ──────────────────────────────────────────────────────

    /**
     * What propagating the current version would change, per form of each
     * application made from the blueprint.
     *
     * @return list<array<string, mixed>>
     */
    public function preview(Blueprint $bp): array
    {
        $out = [];
        foreach ($this->outdated($bp) as [$instance, $app]) {
            foreach ($this->plan($bp, $instance, $app) as $p) {
                $out[] = [
                    'instance' => $instance->uuid,
                    'application' => ['uuid' => strtolower($app->uuid), 'key' => $app->key, 'name' => $this->translator->labelOf($app->translationsFor('name'), $app->key)],
                    'form' => $p['form'] === null ? ['uuid' => $p['uuid'], 'key' => $p['entry']['suggestedKey'], 'name' => null, 'state' => 'new']
                        : ['uuid' => $p['form']->uuid, 'key' => $p['form']->key, 'name' => $p['form']->translate('name'), 'state' => $p['form']->state],
                    'from_version' => (int) BlueprintVersion::query()->whereKey($instance->baseVersionId())->value('version'),
                    'changes' => array_map(static fn ($c) => array_diff_key($c, ['value' => true]), $p['changes']),
                ];
            }
        }

        return $out;
    }

    /**
     * @param  list<string>|null  $instanceUuids
     * @return list<array<string, mixed>>
     */
    public function propagate(Blueprint $bp, ?array $instanceUuids, User $user): array
    {
        $results = [];
        foreach ($this->outdated($bp) as [$instance, $app]) {
            if ($instanceUuids !== null && ! in_array($instance->uuid, $instanceUuids, true)) {
                continue;
            }
            $current = BlueprintVersion::query()->findOrFail($bp->current_version_id);
            foreach ($this->plan($bp, $instance, $app) as $p) {
                $conflicts = array_values(array_filter($p['changes'], static fn ($c) => $c['status'] === 'conflict'));
                $applied = count(array_filter($p['changes'], static fn ($c) => $c['status'] === 'apply'));
                try {
                    if ($p['form'] === null) {
                        $made = $this->makeForm($app, $p['entry'], $p['map'], $app->key, $user, $bp, $current, $instance->include_mode);
                        $results[] = ['instance' => $instance->uuid, 'form' => strtolower($made['form']->uuid), 'status' => 'created', 'applied' => 1, 'conflicts' => []];

                        continue;
                    }
                    $form = $p['form'];
                    if ($p['document'] !== null) {
                        $published = $form->current_version_id === null ? null : app(PublishedDefinitions::class)->version($form->id, (int) $form->current_version_id);
                        $this->drafts->save($form, $p['document'], null, $published);
                    }
                    if ($p['workflow'] !== null) {
                        $this->workflow->save($form->refresh(), $p['workflow'], $user->id);
                    }
                    if ($p['notifications'] !== null) {
                        $this->notifications->save($form->refresh(), $p['notifications'], $user->id);
                    }
                    if ($p['actions'] !== null) {
                        $this->actions->save($form->refresh(), self::withoutSecrets($p['actions']), $user->id);
                    }
                    $this->audit->record('blueprint.propagated', 'config', null, 'form', $form->id, [
                        'blueprint' => $bp->uuid, 'version' => $current->version, 'applied' => $applied, 'conflicts' => count($conflicts),
                    ]);
                    $results[] = ['instance' => $instance->uuid, 'form' => strtolower($form->uuid), 'status' => 'applied', 'applied' => $applied,
                        'conflicts' => array_map(static fn ($c) => array_diff_key($c, ['value' => true]), $conflicts)];
                } catch (DraftInvalid $e) {
                    $results[] = ['instance' => $instance->uuid, 'form' => $p['uuid'], 'status' => 'failed', 'errors' => $e->errors];
                } catch (Throwable $e) {
                    $results[] = ['instance' => $instance->uuid, 'form' => $p['uuid'], 'status' => 'failed', 'errors' => [['message' => $e->getMessage()]]];
                }
            }
            $instance->forceFill(['last_propagated_version_id' => $bp->current_version_id])->save();
            BlueprintInstance::query()->where('blueprint_id', $bp->id)->where('object_type', 'form')->where('is_detached', false)
                ->whereIn('object_id', Form::query()->where('application_id', $app->id)->pluck('id'))
                ->update(['last_propagated_version_id' => $bp->current_version_id]);
        }

        return $results;
    }

    /** Detaching an application detaches every form made with it. */
    public function detach(BlueprintInstance $instance): void
    {
        $formIds = Form::query()->where('application_id', $instance->object_id)->pluck('id');
        BlueprintInstance::query()->where('blueprint_id', $instance->blueprint_id)->where('object_type', 'form')->whereIn('object_id', $formIds)->update(['is_detached' => true]);
        Form::query()->whereIn('id', $formIds)->whereIn('blueprint_instance_id', BlueprintInstance::query()->where('blueprint_id', $instance->blueprint_id)->select('id'))->update(['blueprint_instance_id' => null]);
    }

    /** @return list<array{0: BlueprintInstance, 1: Application}> */
    private function outdated(Blueprint $bp): array
    {
        $out = [];
        foreach (BlueprintInstance::query()->where('blueprint_id', $bp->id)->where('object_type', 'application')->where('is_detached', false)->orderBy('id')->get() as $instance) {
            if ($instance->baseVersionId() === $bp->current_version_id) {
                continue;
            }
            $app = Application::query()->find($instance->object_id);
            if ($app !== null) {
                $out[] = [$instance, $app];
            }
        }

        return $out;
    }

    /**
     * The merge of base → current content onto each form of the application.
     *
     * @return list<array{uuid: string, form: Form|null, entry: array<string, mixed>, map: array<string, string>, changes: list<array<string, mixed>>, document: array<string, mixed>|null, workflow: array<string, mixed>|null, notifications: list<array<string, mixed>>|null, actions: list<array<string, mixed>>|null}>
     */
    private function plan(Blueprint $bp, BlueprintInstance $instance, Application $app): array
    {
        $baseContent = BlueprintVersion::query()->findOrFail($instance->baseVersionId())->content;
        $targetContent = BlueprintVersion::query()->findOrFail($bp->current_version_id)->content;
        $appUuid = strtolower($app->uuid);
        $baseMap = self::map($baseContent, $appUuid);
        $targetMap = self::map($targetContent, $appUuid);
        $bases = [];
        foreach ($baseContent['forms'] as $e) {
            $bases[strtolower((string) $e['source'])] = $e;
        }
        $out = [];
        foreach ($targetContent['forms'] as $entry) {
            $source = strtolower((string) $entry['source']);
            $uuid = $targetMap[$source];
            $form = Form::query()->where('uuid', $uuid)->where('application_id', $app->id)->first();
            $instanceRow = $form === null ? null : BlueprintInstance::query()->where('blueprint_id', $bp->id)->where('object_type', 'form')->where('object_id', $form->id)->first();
            if ($form !== null && ($instanceRow === null || $instanceRow->is_detached || $form->state === 'schema_inconsistent')) {
                continue;
            }
            if ($form === null) {
                $out[] = ['uuid' => $uuid, 'form' => null, 'entry' => $entry, 'map' => $targetMap, 'document' => null, 'workflow' => null, 'notifications' => null, 'actions' => null,
                    'changes' => [['kind' => 'new_form', 'uuid' => $uuid, 'label' => (string) $entry['suggestedKey'], 'action' => 'add', 'status' => 'apply', 'reason' => null, 'value' => null]]];

                continue;
            }
            $base = $bases[$source] ?? ['document' => ['groups' => [], 'fields' => [], 'relations' => [], 'conditions' => [], 'form' => [], 'collection' => []], 'workflow' => [], 'notifications' => [], 'actions' => []];
            $doc = $this->blueprints->mergeDocuments(self::remap($base['document'], $baseMap), self::remap($entry['document'], $targetMap), $this->drafts->normalize($this->drafts->load($form)));
            $changes = $doc['changes'];
            $wf = BlueprintService::mergeLists(self::remap((array) ($base['workflow'] ?? []), $baseMap), self::remap((array) ($entry['workflow'] ?? []), $targetMap), self::plain($this->workflow->load($form)), self::WORKFLOW_LISTS);
            $changes = [...$changes, ...$wf['changes']];
            $rules = null;
            $acts = null;
            if (self::MODES[$instance->include_mode] >= 2 && $form->current_version_id !== null) {
                $r = BlueprintService::mergeLists(['rules' => self::remap((array) ($base['notifications'] ?? []), $baseMap)], ['rules' => self::remap((array) ($entry['notifications'] ?? []), $targetMap)], ['rules' => self::plain($this->notifications->load($form))], ['rules']);
                $a = BlueprintService::mergeLists(['actions' => self::remap((array) ($base['actions'] ?? []), $baseMap)], ['actions' => self::remap((array) ($entry['actions'] ?? []), $targetMap)], ['actions' => self::plain($this->actions->load($form))], ['actions']);
                $changes = [...$changes, ...$r['changes'], ...$a['changes']];
                $rules = self::anyApplied($r['changes']) ? $r['document']['rules'] : null;
                $acts = self::anyApplied($a['changes']) ? $a['document']['actions'] : null;
            }
            if ($changes === []) {
                continue;
            }
            $out[] = [
                'uuid' => $uuid, 'form' => $form, 'entry' => $entry, 'map' => $targetMap, 'changes' => $changes,
                'document' => self::anyApplied($doc['changes']) ? $doc['document'] : null,
                'workflow' => self::anyApplied($wf['changes']) ? $wf['document'] : null,
                'notifications' => $rules, 'actions' => $acts,
            ];
        }

        return $out;
    }

    // ── Identity ─────────────────────────────────────────────────────────

    /**
     * Old uuid → new uuid for an application made from the content: each
     * form from the application's uuid, each of its elements, statuses,
     * transitions, rules and actions from the form's new uuid.
     *
     * @param  array<string, mixed>  $content
     * @return array<string, string>
     */
    public static function map(array $content, string $appUuid): array
    {
        $map = [strtolower((string) $content['source']) => $appUuid];
        foreach ($content['forms'] as $entry) {
            $form = BlueprintService::derive($appUuid, (string) $entry['source']);
            $map[strtolower((string) $entry['source'])] = $form;
            $doc = (array) $entry['document'];
            $own = [];
            foreach (['groups', 'fields', 'relations', 'conditions'] as $list) {
                foreach ($doc[$list] ?? [] as $o) {
                    $own[] = $o['uuid'];
                    foreach ($o['options']['static'] ?? [] as $opt) {
                        $own[] = $opt['uuid'];
                    }
                }
            }
            foreach (self::WORKFLOW_LISTS as $list) {
                foreach ($entry['workflow'][$list] ?? [] as $o) {
                    $own[] = $o['uuid'];
                }
            }
            foreach ($entry['notifications'] ?? [] as $o) {
                $own[] = $o['uuid'];
            }
            foreach ($entry['actions'] ?? [] as $o) {
                $own[] = $o['uuid'];
                foreach ($o['steps'] ?? [] as $step) {
                    $own[] = $step['uuid'];
                }
            }
            foreach ($own as $uuid) {
                if (is_string($uuid)) {
                    $map[strtolower($uuid)] = BlueprintService::derive($form, $uuid);
                }
            }
        }

        return $map;
    }

    /**
     * Every string that is an old uuid becomes its new one, anywhere in the value.
     *
     * @template T
     *
     * @param  T  $value
     * @param  array<string, string>  $map
     * @return T
     */
    public static function remap(mixed $value, array $map): mixed
    {
        if (is_string($value)) {
            return $map[strtolower($value)] ?? $value;
        }
        if (is_array($value)) {
            $out = [];
            foreach ($value as $k => $v) {
                $out[$k] = self::remap($v, $map);
            }

            return $out;
        }

        return $value;
    }

    /**
     * Webhook secrets and header values never travel: they are left empty
     * and must be entered again on the copy.
     *
     * @param  list<array<string, mixed>>  $actions
     * @return list<array<string, mixed>>
     */
    public static function withoutSecrets(array $actions): array
    {
        foreach ($actions as $i => $a) {
            foreach ($a['steps'] ?? [] as $j => $s) {
                if (($s['type'] ?? null) === 'call_webhook') {
                    $c = (array) $s['config'];
                    $c['secret'] = null;
                    $c['secretSet'] = false;
                    $c['headers'] = array_values(array_map(static fn ($h) => ['name' => $h['name'] ?? '', 'value' => null], (array) ($c['headers'] ?? [])));
                    $actions[$i]['steps'][$j]['config'] = $c;
                }
            }
        }

        return $actions;
    }

    /**
     * The value as plain arrays (documents loaded for screens hold objects for empty maps).
     *
     * @return array<mixed>
     */
    private static function plain(mixed $value): array
    {
        return (array) json_decode((string) json_encode($value, JSON_UNESCAPED_UNICODE), true);
    }

    /** @param  list<array<string, mixed>>  $changes */
    private static function anyApplied(array $changes): bool
    {
        return array_filter($changes, static fn ($c) => $c['status'] === 'apply') !== [];
    }

    private function freeKey(string $wanted): string
    {
        $base = mb_substr((string) preg_replace('/[^a-z0-9_]/', '_', strtolower($wanted)), 0, 44);
        $key = $base;
        for ($n = 2; Form::query()->where('key', $key)->exists(); $n++) {
            $key = $base.'_'.$n;
        }

        return $key;
    }

    private function label(Form $form): string
    {
        return $this->translator->labelOf($form->translationsFor('name'), Translator::humanize((string) $form->key));
    }

    /** A fresh application key from a wanted one, for the clone dialog's suggestion. */
    public static function suggestKey(string $wanted): string
    {
        $base = mb_substr((string) preg_replace('/[^a-z0-9_]/', '_', strtolower($wanted)), 0, 40);
        $key = $base.'_copy';
        for ($n = 2; Application::withTrashed()->where('key', $key)->exists(); $n++) {
            $key = $base.'_copy_'.$n;
        }

        return Str::limit($key, 48, '');
    }
}
