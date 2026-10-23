<?php

declare(strict_types=1);

namespace App\Modules\Blueprints;

use App\Modules\Actions\ActionDocument;
use App\Modules\Audit\AuditWriter;
use App\Modules\Blueprints\Models\Blueprint;
use App\Modules\Blueprints\Models\BlueprintInstance;
use App\Modules\Blueprints\Models\BlueprintVersion;
use App\Modules\Core\I18n\Translator;
use App\Modules\Core\Tenancy\TenantContext;
use App\Modules\Forms\Draft\DraftRepository;
use App\Modules\Forms\Models\Form;
use App\Modules\Identity\Models\User;
use App\Modules\Notifications\NotificationRules;
use App\Modules\Reports\Dashboards;
use App\Modules\Reports\Models\Dashboard;
use App\Modules\Workflow\WorkflowDocument;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Blueprints of a workflow, notification rules, actions and dashboards
 * (specification §4.30, ADR-0059).
 *
 * - A workflow, the notification rules or the custom actions of a form are
 *   saved as a blueprint and applied to another form. References to the
 *   source form's fields, statuses and transitions are matched by key on
 *   the target; what has no match is reported. Statuses, transitions,
 *   rules, actions and steps get uuids derived from the target form.
 * - A workflow is applied only to a form without one. Rules and actions
 *   are added to the form's own; one whose key the form already uses is
 *   refused.
 * - A dashboard blueprint makes a new dashboard with its widgets.
 * - Propagating a newer version merges it three ways into each object made
 *   from the blueprint; what was changed locally is kept and listed.
 */
final class PartBlueprints
{
    public const KINDS = ['workflow', 'notification', 'action', 'dashboard'];

    private const LISTS = ['workflow' => ['statuses', 'transitions', 'sla'], 'notification' => ['items'], 'action' => ['items'], 'dashboard' => ['widgets']];

    public function __construct(
        private readonly WorkflowDocument $workflow,
        private readonly NotificationRules $notifications,
        private readonly ActionDocument $actions,
        private readonly Dashboards $dashboards,
        private readonly AuditWriter $audit,
        private readonly TenantContext $tenant,
        private readonly Translator $translator,
        private readonly DraftRepository $drafts,
    ) {}

    /**
     * @param  array{name: array<string, string>, description?: array<string, string>, category?: string|null, tags?: list<string>, is_library?: bool}  $data
     */
    public function create(string $kind, string $source, array $data): Blueprint
    {
        $object = $this->source($kind, $source, null);

        return DB::transaction(function () use ($kind, $object, $data): Blueprint {
            $bp = new Blueprint([
                'kind' => $kind, 'category' => $data['category'] ?? null, 'tags' => $data['tags'] ?? [], 'is_library' => $data['is_library'] ?? false,
                'source_type' => $kind === 'dashboard' ? 'dashboard' : 'form', 'source_id' => $object->id,
            ]);
            $bp->organization_id = $this->tenant->organizationId();
            $bp->save();
            $bp->setTranslations('name', $data['name']);
            if (isset($data['description'])) {
                $bp->setTranslations('description', $data['description']);
            }
            $this->addVersion($bp, null, null);

            return $bp->refresh();
        });
    }

    public function addVersion(Blueprint $bp, ?string $source, ?string $changelog): BlueprintVersion
    {
        $object = $this->source($bp->kind, $source, $bp);
        $content = $this->capture($bp->kind, $object);
        $hash = BlueprintService::hash($content);
        $current = $bp->current_version_id === null ? null : BlueprintVersion::query()->find($bp->current_version_id);
        if ($current !== null && hash_equals($current->content_hash, $hash)) {
            throw ValidationException::withMessages(['source' => __('blueprints.no_changes')]);
        }

        return DB::transaction(function () use ($bp, $content, $hash, $changelog): BlueprintVersion {
            $number = (int) BlueprintVersion::query()->where('blueprint_id', $bp->id)->max('version') + 1;
            $version = BlueprintVersion::query()->create([
                'blueprint_id' => $bp->id, 'version' => $number, 'content' => $content, 'content_hash' => $hash, 'include_mode' => 'structure', 'changelog' => $changelog,
            ]);
            $bp->forceFill(['current_version_id' => $version->id])->save();
            $this->audit->record('blueprint.version_created', 'config', null, 'blueprint', $bp->id, ['version' => $number, 'kind' => $bp->kind]);

            return $version;
        });
    }

    /** @return array<string, mixed> */
    public function capture(string $kind, Form|Dashboard $object): array
    {
        if ($object instanceof Dashboard) {
            $doc = self::plain($this->dashboards->present($object));

            return ['format' => BlueprintService::FORMAT, 'kind' => 'dashboard', 'source' => strtolower($object->uuid), 'suggestedKey' => $object->key,
                'document' => array_diff_key($doc, array_flip(['uuid', 'key', 'hash', 'label']))];
        }
        $items = match ($kind) {
            'workflow' => self::plain($this->workflow->load($object)),
            'notification' => ['items' => self::plain($this->notifications->load($object))],
            default => ['items' => self::plain($this->actions->load($object))],
        };
        if ($kind === 'workflow' && ($items['statuses'] ?? []) === []) {
            throw ValidationException::withMessages(['source' => __('blueprints.workflow_empty')]);
        }
        if ($kind !== 'workflow' && $items['items'] === []) {
            throw ValidationException::withMessages(['source' => __('blueprints.nothing_to_capture')]);
        }

        return ['format' => BlueprintService::FORMAT, 'kind' => $kind, 'source' => strtolower($object->uuid), 'keys' => $this->keys($object), 'document' => $items];
    }

    /**
     * Applies a version to a form (workflow, rules, actions) or makes a new dashboard.
     *
     * @param  array<string, string>  $name  the new dashboard's name
     * @return array{uuid: string, notes: list<string>}
     */
    public function instantiate(Blueprint $bp, BlueprintVersion $version, ?Form $form, ?string $key, array $name, User $user): array
    {
        $content = $version->content;
        if ($bp->kind === 'dashboard') {
            if ($key === null || preg_match('/^[a-z][a-z0-9_]{1,47}$/', $key) !== 1 || Dashboard::query()->where('key', $key)->exists()) {
                throw ValidationException::withMessages(['key' => __('blueprints.dashboard_key_taken')]);
            }
            $uuid = (string) Str::uuid7();
            $doc = self::remap($content['document'], self::dashboardMap($content, $uuid));
            if ($name !== []) {
                $doc['i18n']['name'] = $name;
            }
            $p = $this->dashboards->check(['uuid' => $uuid, 'key' => $key] + $doc, null);
            $d = $this->dashboards->save(null, $p, $user);
            BlueprintInstance::query()->create(['blueprint_id' => $bp->id, 'blueprint_version_id' => $version->id, 'object_type' => 'dashboard', 'object_id' => $d->id, 'include_mode' => 'structure', 'is_detached' => false]);
            $this->audit->record('blueprint.instantiated', 'config', null, 'blueprint', $bp->id, ['version' => $version->version, 'dashboard' => $d->uuid]);

            return ['uuid' => strtolower($d->uuid), 'notes' => []];
        }
        if ($form === null) {
            throw ValidationException::withMessages(['form' => __('blueprints.form_required')]);
        }
        // A form follows one blueprint of each kind; a detached link no longer counts.
        $linked = BlueprintInstance::query()->where('object_type', $bp->kind)->where('object_id', $form->id)->first();
        if ($linked !== null && ! $linked->is_detached) {
            throw ValidationException::withMessages(['form' => __('blueprints.already_linked')]);
        }
        $map = $this->map($content, $form);
        $notes = $this->unmatched($content, $form);
        $missing = array_values(array_diff(array_keys((array) ($content['keys']['fields'] ?? [])), array_keys($map)));
        $doc = self::remap($content['document'], $map);
        DB::transaction(function () use ($bp, $version, $form, $doc, $user, $missing, $linked): void {
            $linked?->delete();
            if ($bp->kind === 'workflow') {
                if ($this->workflow->load($form)['statuses'] !== []) {
                    throw ValidationException::withMessages(['form' => __('blueprints.form_has_workflow')]);
                }
                $this->workflow->save($form, $doc, $user->id);
            } else {
                $own = $bp->kind === 'notification' ? self::plain($this->notifications->load($form)) : self::plain($this->actions->load($form));
                // A rule or action that uses a field the form does not have is left out, not saved half-working.
                $doc['items'] = array_values(array_filter($doc['items'], static fn (array $item) => ! self::uses($item, $missing)));
                $taken = array_intersect(array_column($own, 'key'), array_column($doc['items'], 'key'));
                if ($taken !== []) {
                    throw ValidationException::withMessages(['form' => __('blueprints.keys_taken', ['keys' => implode(', ', $taken)])]);
                }
                $this->saveItems($bp->kind, $form, [...$own, ...($bp->kind === 'action' ? ApplicationBlueprints::withoutSecrets($doc['items']) : $doc['items'])], $user);
            }
            BlueprintInstance::query()->create(['blueprint_id' => $bp->id, 'blueprint_version_id' => $version->id, 'object_type' => $bp->kind, 'object_id' => $form->id, 'include_mode' => 'structure', 'is_detached' => false]);
        });
        $this->audit->record('blueprint.instantiated', 'config', null, 'blueprint', $bp->id, ['version' => $version->version, 'form' => strtolower($form->uuid), 'kind' => $bp->kind]);

        return ['uuid' => strtolower($form->uuid), 'notes' => $notes];
    }

    /** @return list<array<string, mixed>> */
    public function preview(Blueprint $bp): array
    {
        $out = [];
        foreach ($this->outdated($bp) as [$instance, $object]) {
            $plan = $this->plan($bp, $instance, $object);
            $out[] = [
                'instance' => $instance->uuid,
                'form' => ['uuid' => strtolower($object->uuid), 'key' => $object->key, 'name' => $this->translator->labelOf($object->translationsFor('name'), (string) $object->key), 'state' => $object instanceof Form ? $object->state : null],
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
    public function propagate(Blueprint $bp, ?array $instanceUuids, User $user): array
    {
        $results = [];
        foreach ($this->outdated($bp) as [$instance, $object]) {
            if ($instanceUuids !== null && ! in_array($instance->uuid, $instanceUuids, true)) {
                continue;
            }
            $plan = $this->plan($bp, $instance, $object);
            $applied = count(array_filter($plan['changes'], static fn ($c) => $c['status'] === 'apply'));
            $conflicts = array_values(array_filter($plan['changes'], static fn ($c) => $c['status'] === 'conflict'));
            try {
                if ($applied > 0) {
                    if ($object instanceof Dashboard) {
                        $this->dashboards->save($object, $this->dashboards->check(['uuid' => strtolower($object->uuid), 'key' => $object->key] + $plan['document'], $object), $user);
                    } elseif ($bp->kind === 'workflow') {
                        $this->workflow->save($object, $plan['document'], $user->id);
                    } else {
                        $this->saveItems($bp->kind, $object, $plan['document']['items'], $user);
                    }
                }
                $instance->forceFill(['last_propagated_version_id' => $bp->current_version_id])->save();
                $this->audit->record('blueprint.propagated', 'config', null, $object instanceof Dashboard ? 'dashboard' : 'form', $object->id, [
                    'blueprint' => $bp->uuid, 'version' => $bp->currentVersion?->version, 'applied' => $applied, 'conflicts' => count($conflicts),
                ]);
                $results[] = ['instance' => $instance->uuid, 'form' => strtolower($object->uuid), 'status' => 'applied', 'applied' => $applied,
                    'conflicts' => array_map(static fn ($c) => array_diff_key($c, ['value' => true]), $conflicts)];
            } catch (Throwable $e) {
                $results[] = ['instance' => $instance->uuid, 'form' => strtolower($object->uuid), 'status' => 'failed', 'errors' => [['message' => $e->getMessage()]]];
            }
        }

        return $results;
    }

    /** @return list<array{0: BlueprintInstance, 1: Form|Dashboard}> */
    private function outdated(Blueprint $bp): array
    {
        $out = [];
        foreach (BlueprintInstance::query()->where('blueprint_id', $bp->id)->where('object_type', $bp->kind)->where('is_detached', false)->orderBy('id')->get() as $instance) {
            if ($instance->baseVersionId() === $bp->current_version_id) {
                continue;
            }
            $object = $bp->kind === 'dashboard' ? Dashboard::query()->find($instance->object_id) : Form::query()->find($instance->object_id);
            if ($object !== null) {
                $out[] = [$instance, $object];
            }
        }

        return $out;
    }

    /** @return array{document: array<string, mixed>, changes: list<array<string, mixed>>} */
    private function plan(Blueprint $bp, BlueprintInstance $instance, Form|Dashboard $object): array
    {
        $base = BlueprintVersion::query()->findOrFail($instance->baseVersionId())->content;
        $target = BlueprintVersion::query()->findOrFail($bp->current_version_id)->content;
        if ($object instanceof Dashboard) {
            $uuid = strtolower($object->uuid);
            $local = array_diff_key(self::plain($this->dashboards->present($object)), array_flip(['uuid', 'key', 'hash', 'label']));

            return BlueprintService::mergeLists(self::remap($base['document'], self::dashboardMap($base, $uuid)), self::remap($target['document'], self::dashboardMap($target, $uuid)), $local, self::LISTS['dashboard']);
        }
        $local = match ($bp->kind) {
            'workflow' => self::plain($this->workflow->load($object)),
            'notification' => ['items' => self::plain($this->notifications->load($object))],
            default => ['items' => self::plain($this->actions->load($object))],
        };

        return BlueprintService::mergeLists(self::remap($base['document'], $this->map($base, $object)), self::remap($target['document'], $this->map($target, $object)), $local, self::LISTS[$bp->kind]);
    }

    /** @param  list<array<string, mixed>>  $items */
    private function saveItems(string $kind, Form $form, array $items, User $user): void
    {
        $kind === 'notification' ? $this->notifications->save($form, $items, $user->id) : $this->actions->save($form, $items, $user->id);
    }

    /**
     * The source form's fields, statuses and transitions by key, captured with
     * the content so they can be matched on another form.
     *
     * @return array{fields: array<string, string>, statuses: array<string, string>, transitions: array<string, string>}
     */
    private function keys(Form $form): array
    {
        $lower = static fn ($rows) => collect($rows)->mapWithKeys(static fn ($k, $u) => [strtolower((string) $u) => (string) $k])->all();

        return [
            // Fields come from the draft, so a field just added there is matched too.
            'fields' => $lower(collect((array) ($this->drafts->load($form)['fields'] ?? []))->mapWithKeys(static fn ($f) => [(string) ((array) $f)['uuid'] => (string) ((array) $f)['key']])),
            'statuses' => $lower(DB::table('statuses')->where('form_id', $form->id)->whereNull('archived_at')->pluck('key', 'uuid')),
            'transitions' => $lower(DB::table('transitions')->where('form_id', $form->id)->whereNull('archived_at')->pluck('key', 'uuid')),
        ];
    }

    /**
     * Old uuid → uuid on the target form: fields, statuses and transitions by
     * key; the blueprint's own elements derived from the target form.
     *
     * @param  array<string, mixed>  $content
     * @return array<string, string>
     */
    private function map(array $content, Form $form): array
    {
        $target = $this->keys($form);
        $map = [strtolower((string) $content['source']) => strtolower($form->uuid)];
        foreach (['fields', 'statuses', 'transitions'] as $kind) {
            $byKey = array_flip($target[$kind]);
            foreach ($content['keys'][$kind] ?? [] as $uuid => $key) {
                if (isset($byKey[$key])) {
                    $map[strtolower((string) $uuid)] = $byKey[$key];
                }
            }
        }
        $doc = (array) $content['document'];
        $own = [];
        foreach (['statuses', 'transitions', 'sla', 'items'] as $list) {
            foreach ($doc[$list] ?? [] as $o) {
                $own[] = $o['uuid'] ?? null;
                foreach ($o['steps'] ?? [] as $s) {
                    $own[] = $s['uuid'] ?? null;
                }
            }
        }
        foreach ($own as $uuid) {
            if (is_string($uuid)) {
                $map[strtolower($uuid)] = BlueprintService::derive(strtolower($form->uuid), $uuid);
            }
        }

        return $map;
    }

    /**
     * Fields the content refers to that the target form does not have, by key.
     *
     * @param  array<string, mixed>  $content
     * @return list<string>
     */
    private function unmatched(array $content, Form $form): array
    {
        $have = array_flip($this->keys($form)['fields']);
        $used = [];
        $walk = static function (mixed $v) use (&$walk, &$used, $content): void {
            if (is_string($v) && isset($content['keys']['fields'][strtolower($v)])) {
                $used[$content['keys']['fields'][strtolower($v)]] = true;
            } elseif (is_array($v)) {
                array_walk($v, $walk);
            }
        };
        $walk($content['document']);
        $missing = array_values(array_diff(array_keys($used), array_keys($have)));

        return $missing === [] ? [] : [__('blueprints.fields_unmatched', ['fields' => implode(', ', $missing)])];
    }

    /**
     * Whether the value refers to any of the given uuids.
     *
     * @param  list<string>  $uuids
     */
    private static function uses(mixed $value, array $uuids): bool
    {
        if (is_string($value)) {
            return in_array(strtolower($value), $uuids, true);
        }
        if (is_array($value)) {
            foreach ($value as $v) {
                if (self::uses($v, $uuids)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * @param  array<string, mixed>  $content
     * @return array<string, string>
     */
    private static function dashboardMap(array $content, string $dashboardUuid): array
    {
        $map = [strtolower((string) $content['source']) => $dashboardUuid];
        foreach ($content['document']['widgets'] ?? [] as $w) {
            $map[strtolower((string) $w['uuid'])] = BlueprintService::derive($dashboardUuid, (string) $w['uuid']);
        }

        return $map;
    }

    private function source(string $kind, ?string $uuid, ?Blueprint $bp): Form|Dashboard
    {
        if ($kind === 'dashboard') {
            $d = $uuid !== null ? Dashboard::query()->where('uuid', strtolower($uuid))->first() : ($bp?->source_type === 'dashboard' ? Dashboard::query()->find($bp->source_id) : null);

            return $d ?? throw ValidationException::withMessages(['source' => __('blueprints.source_missing_dashboard')]);
        }
        $f = $uuid !== null ? Form::query()->where('uuid', strtolower($uuid))->first() : ($bp?->source_type === 'form' ? Form::query()->find($bp->source_id) : null);

        return $f ?? throw ValidationException::withMessages(['source' => __('blueprints.source_missing')]);
    }

    /** @return array<mixed> */
    private static function plain(mixed $value): array
    {
        return (array) json_decode((string) json_encode($value, JSON_UNESCAPED_UNICODE), true);
    }

    /**
     * @param  array<string, string>  $map
     */
    private static function remap(mixed $value, array $map): mixed
    {
        return ApplicationBlueprints::remap($value, $map);
    }
}
