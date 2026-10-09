<?php

declare(strict_types=1);

namespace App\Modules\Blueprints;

use App\Modules\Access\AccessCache;
use App\Modules\Access\FieldAccessRules;
use App\Modules\Access\Models\PermissionAssignment;
use App\Modules\Audit\AuditWriter;
use App\Modules\Blueprints\Models\Blueprint;
use App\Modules\Blueprints\Models\BlueprintInstance;
use App\Modules\Blueprints\Models\BlueprintVersion;
use App\Modules\Core\Tenancy\TenantContext;
use App\Modules\Forms\Definition\PublishedDefinitions;
use App\Modules\Forms\Draft\DraftInvalid;
use App\Modules\Forms\Draft\DraftRepository;
use App\Modules\Forms\FormService;
use App\Modules\Forms\Models\Application;
use App\Modules\Forms\Models\Form;
use App\Support\Json\SchemaValidator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Blueprints of forms and collections (specification §4.30, architecture
 * §10.17, §14.12): a blueprint version holds the form's draft document
 * (§14.1) and, by include mode, its access rules and grants. Instantiating
 * creates a new draft form whose element uuids are derived from the new
 * form's uuid and the blueprint's uuids, so later versions can be mapped onto
 * it without storing a mapping. Propagation is a three-way merge per element
 * (blueprint base → blueprint current, against the object's draft): elements
 * changed locally are kept and listed as conflicts; nothing is applied
 * without a preview, and results land in the object's draft for the admin to
 * publish.
 */
final class BlueprintService
{
    public const FORMAT = 1;

    private const MODES = ['structure' => 0, 'structure_permissions' => 1, 'everything' => 2];

    private const LISTS = ['groups', 'fields', 'relations', 'conditions'];

    /** Form-level properties an instance owns; propagation never touches them. */
    private const LOCAL_FORM_PROPS = ['key', 'application', 'bindingMode', 'boundTable', 'version', 'table'];

    private const SUBJECT_TABLES = ['user' => 'users', 'role' => 'roles', 'department' => 'departments'];

    public function __construct(
        private readonly DraftRepository $drafts,
        private readonly FormService $forms,
        private readonly PublishedDefinitions $definitions,
        private readonly FieldAccessRules $accessRules,
        private readonly AccessCache $accessCache,
        private readonly AuditWriter $audit,
        private readonly SchemaValidator $schemas,
        private readonly TenantContext $tenant,
    ) {}

    // ── Capture ──────────────────────────────────────────────────────────

    /**
     * @param  array{name: array<string, string>, description?: array<string, string>, category?: string|null, tags?: list<string>, is_library?: bool, include_mode: string}  $data
     */
    public function createFromForm(Form $form, array $data): Blueprint
    {
        return DB::transaction(function () use ($form, $data): Blueprint {
            $bp = new Blueprint([
                'kind' => $form->kind, 'category' => $data['category'] ?? null, 'tags' => $data['tags'] ?? [],
                'is_library' => $data['is_library'] ?? false, 'source_type' => 'form', 'source_id' => $form->id,
            ]);
            $bp->organization_id = $this->tenant->organizationId();
            $bp->save();
            $bp->setTranslations('name', $data['name']);
            if (isset($data['description'])) {
                $bp->setTranslations('description', $data['description']);
            }
            $this->addVersion($bp, $form, $data['include_mode'], null);

            return $bp->refresh();
        });
    }

    /** A new version from the blueprint's source form (or the given form). Refused when nothing changed. */
    public function addVersion(Blueprint $bp, Form $form, string $includeMode, ?string $changelog): BlueprintVersion
    {
        if ($form->kind !== $bp->kind) {
            throw ValidationException::withMessages(['source' => __('blueprints.kind_mismatch')]);
        }
        $content = $this->capture($form, $includeMode);
        $hash = self::hash($content);
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
            $this->audit->record('blueprint.version_created', 'config', null, 'blueprint', $bp->id, ['version' => $number, 'include_mode' => $includeMode]);

            return $version;
        });
    }

    /** @return array<string, mixed> */
    public function capture(Form $form, string $includeMode): array
    {
        $doc = $this->drafts->normalize($this->drafts->load($form));
        foreach (self::LOCAL_FORM_PROPS as $prop) {
            unset($doc['form'][$prop]);
        }
        $dependencies = [];
        foreach ($doc['relations'] as $r) {
            if ($r['target'] !== $form->uuid) {
                $dependencies[$r['target']] = true;
            }
        }
        $content = [
            'format' => self::FORMAT,
            'kind' => $form->kind,
            'source' => $form->uuid,
            'suggestedKey' => $form->key,
            'collectionType' => $form->collection->collection_type ?? null,
            'document' => $doc,
            'dependencies' => array_keys($dependencies),
            'access' => null,
        ];
        if (self::MODES[$includeMode] >= 1) {
            $content['access'] = $this->captureAccess($form);
        }

        return $content;
    }

    /** @return array{rules: list<array<string, mixed>>, grants: list<array<string, mixed>>} */
    private function captureAccess(Form $form): array
    {
        $groups = DB::table('field_groups')->where('form_id', $form->id)->pluck('uuid', 'id')->all();
        $fields = DB::table('fields')->where('form_id', $form->id)->pluck('uuid', 'id')->all();
        $rules = [];
        foreach (DB::table('field_access_rules')->where('form_id', $form->id)->orderBy('id')->get() as $r) {
            $rules[] = [
                'target' => $r->target_type,
                'group' => $r->group_id === null ? null : strtolower((string) ($groups[$r->group_id] ?? '')),
                'field' => $r->field_id === null ? null : strtolower((string) ($fields[$r->field_id] ?? '')),
                'subjectType' => $r->subject_type,
                'subject' => $r->subject_id === null ? null : $this->subjectUuid($r->subject_type, (int) $r->subject_id),
                'mode' => $r->mode, 'access' => $r->access, 'effect' => $r->effect,
            ];
        }
        $grants = [];
        foreach (DB::table('permission_assignments')->join('permissions', 'permissions.id', '=', 'permission_assignments.permission_id')
            ->where('permissions.key', 'like', "form.{$form->uuid}.%")->orderBy('permission_assignments.id')
            ->get(['permissions.key', 'subject_type', 'subject_id', 'effect', 'include_descendants']) as $a) {
            $grants[] = [
                'ability' => substr((string) $a->key, strlen("form.{$form->uuid}.")),
                'subjectType' => $a->subject_type,
                'subject' => $this->subjectUuid($a->subject_type, (int) $a->subject_id),
                'effect' => $a->effect, 'includeDescendants' => (bool) $a->include_descendants,
            ];
        }

        return ['rules' => $rules, 'grants' => $grants];
    }

    private function subjectUuid(string $type, int $id): ?string
    {
        $table = self::SUBJECT_TABLES[$type] ?? null;
        $uuid = $table === null ? null : DB::table($table)->where('id', $id)->value('uuid');

        return $uuid === null ? null : strtolower((string) $uuid);
    }

    // ── Instantiate ──────────────────────────────────────────────────────

    /**
     * Creates a draft form from a blueprint version.
     *
     * @param  array<string, string>  $name
     * @return array{form: Form, skipped: list<string>}
     */
    public function instantiate(Blueprint $bp, BlueprintVersion $version, Application $application, string $key, array $name, string $includeMode): array
    {
        if (! in_array($bp->kind, ['form', 'collection'], true)) {
            throw ValidationException::withMessages(['blueprint' => __('blueprints.kind_unavailable')]);
        }
        if (self::MODES[$includeMode] > self::MODES[$version->include_mode]) {
            throw ValidationException::withMessages(['include' => __('blueprints.include_unavailable')]);
        }
        $content = $version->content;
        $missing = [];
        foreach ($content['dependencies'] ?? [] as $target) {
            if (! Form::query()->where('uuid', $target)->exists()) {
                $missing[] = $target;
            }
        }
        if ($missing !== []) {
            throw ValidationException::withMessages(['dependencies' => __('blueprints.dependencies_missing', ['forms' => implode(', ', $missing)])]);
        }

        return DB::transaction(function () use ($bp, $version, $application, $key, $name, $includeMode, $content): array {
            $form = $this->forms->create([
                'kind' => $bp->kind, 'key' => $key, 'application' => $application, 'name' => $name,
                'collection_type' => $content['collectionType'] ?? 'table',
            ]);
            $doc = $this->mapped($content, $form->uuid);
            $doc['form']['key'] = $key;
            $doc['form']['application'] = $application->uuid;
            $doc['form']['bindingMode'] = 'managed';
            $doc['form']['i18n']['name'] = $name;
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
            $instance = BlueprintInstance::query()->create([
                'blueprint_id' => $bp->id, 'blueprint_version_id' => $version->id, 'object_type' => 'form',
                'object_id' => $form->id, 'include_mode' => $includeMode, 'is_detached' => false,
            ]);
            $form->forceFill(['blueprint_instance_id' => $instance->id])->save();
            $skipped = self::MODES[$includeMode] >= 1 && is_array($content['access'] ?? null)
                ? $this->applyAccess($form, $content['access'], $form->uuid)
                : [];
            $this->audit->record('blueprint.instantiated', 'config', null, 'blueprint', $bp->id, [
                'version' => $version->version, 'form' => $form->uuid, 'include_mode' => $includeMode, 'skipped' => count($skipped),
            ]);

            return ['form' => $form, 'skipped' => $skipped];
        });
    }

    /**
     * The version's draft document with every element uuid mapped into the
     * instance's namespace.
     *
     * @param  array<string, mixed>  $content
     * @return array<string, mixed>
     */
    private function mapped(array $content, string $formUuid): array
    {
        return $this->forms->remapUuids(
            $content['document'],
            static fn (string $old): string => self::derive($formUuid, $old),
            [(string) $content['source'] => $formUuid],
        );
    }

    /**
     * @param  array{rules: list<array<string, mixed>>, grants: list<array<string, mixed>>}  $access
     * @return list<string> descriptions of rules and grants whose subject does not exist here
     */
    private function applyAccess(Form $form, array $access, string $formUuid): array
    {
        $groupIds = DB::table('field_groups')->where('form_id', $form->id)->pluck('id', 'uuid')->mapWithKeys(static fn ($id, $u) => [strtolower((string) $u) => (int) $id])->all();
        $fieldIds = DB::table('fields')->where('form_id', $form->id)->pluck('id', 'uuid')->mapWithKeys(static fn ($id, $u) => [strtolower((string) $u) => (int) $id])->all();
        $skipped = [];
        foreach ($access['rules'] as $r) {
            $subjectId = null;
            if ($r['subjectType'] !== 'everyone') {
                $subjectId = $this->subjectId((string) $r['subjectType'], $r['subject'] ?? null);
                if ($subjectId === null) {
                    $skipped[] = "rule:{$r['subjectType']}:{$r['subject']}";

                    continue;
                }
            }
            $groupId = $r['group'] === null ? null : ($groupIds[self::derive($formUuid, (string) $r['group'])] ?? null);
            $fieldId = $r['field'] === null ? null : ($fieldIds[self::derive($formUuid, (string) $r['field'])] ?? null);
            if (($r['group'] !== null && $groupId === null) || ($r['field'] !== null && $fieldId === null)) {
                continue;
            }
            $this->accessRules->put($form, (string) $r['target'], $groupId, $fieldId, (string) $r['subjectType'], $subjectId, $r['mode'], $r['access'], $r['effect']);
        }
        foreach ($access['grants'] as $g) {
            $subjectId = $this->subjectId((string) $g['subjectType'], $g['subject'] ?? null);
            $permissionId = DB::table('permissions')->where('key', "form.{$formUuid}.{$g['ability']}")->value('id');
            if ($subjectId === null || $permissionId === null) {
                $skipped[] = "grant:{$g['ability']}:{$g['subjectType']}:{$g['subject']}";

                continue;
            }
            PermissionAssignment::query()->updateOrCreate(
                ['permission_id' => $permissionId, 'subject_type' => $g['subjectType'], 'subject_id' => $subjectId],
                ['effect' => $g['effect'], 'include_descendants' => (bool) ($g['includeDescendants'] ?? false)],
            );
        }
        $this->accessCache->bump();

        return $skipped;
    }

    private function subjectId(string $type, ?string $uuid): ?int
    {
        $table = self::SUBJECT_TABLES[$type] ?? null;
        if ($table === null || $uuid === null) {
            return null;
        }
        $q = DB::table($table)->where('uuid', $uuid);
        if ($table !== 'roles') {
            $q->whereNull('deleted_at');
        }
        $id = $q->value('id');

        return $id === null ? null : (int) $id;
    }

    // ── Propagation ──────────────────────────────────────────────────────

    /**
     * What propagating the current version would change on each linked object.
     *
     * @return list<array<string, mixed>>
     */
    public function preview(Blueprint $bp): array
    {
        $out = [];
        foreach ($this->outdated($bp) as [$instance, $form]) {
            $plan = $this->plan($bp, $instance, $form);
            $out[] = [
                'instance' => $instance->uuid,
                'form' => ['uuid' => $form->uuid, 'key' => $form->key, 'name' => $form->translate('name'), 'state' => $form->state],
                'from_version' => (int) BlueprintVersion::query()->whereKey($instance->baseVersionId())->value('version'),
                'changes' => array_map(static fn ($c) => array_diff_key($c, ['value' => true]), $plan['changes']),
            ];
        }

        return $out;
    }

    /**
     * Applies the current version to the given instances (all outdated ones
     * when null). Each object's draft is saved on its own; one that would
     * become invalid is left untouched and reported.
     *
     * @param  list<string>|null  $instanceUuids
     * @return list<array<string, mixed>>
     */
    public function propagate(Blueprint $bp, ?array $instanceUuids): array
    {
        $results = [];
        foreach ($this->outdated($bp) as [$instance, $form]) {
            if ($instanceUuids !== null && ! in_array($instance->uuid, $instanceUuids, true)) {
                continue;
            }
            $plan = $this->plan($bp, $instance, $form);
            $applied = array_values(array_filter($plan['changes'], static fn ($c) => $c['status'] === 'apply'));
            $conflicts = array_values(array_filter($plan['changes'], static fn ($c) => $c['status'] === 'conflict'));
            try {
                $published = $form->current_version_id === null ? null : $this->definitions->version($form->id, (int) $form->current_version_id);
                if ($applied !== []) {
                    $this->drafts->save($form, $plan['document'], null, $published);
                }
                $instance->forceFill(['last_propagated_version_id' => $bp->current_version_id])->save();
                $this->audit->record('blueprint.propagated', 'config', null, 'form', $form->id, [
                    'blueprint' => $bp->uuid, 'version' => $bp->currentVersion?->version, 'applied' => count($applied), 'conflicts' => count($conflicts),
                ]);
                $results[] = ['instance' => $instance->uuid, 'form' => $form->uuid, 'status' => 'applied', 'applied' => count($applied), 'conflicts' => array_map(static fn ($c) => array_diff_key($c, ['value' => true]), $conflicts)];
            } catch (DraftInvalid $e) {
                $results[] = ['instance' => $instance->uuid, 'form' => $form->uuid, 'status' => 'failed', 'errors' => $e->errors];
            }
        }

        return $results;
    }

    public function detach(BlueprintInstance $instance): void
    {
        $instance->forceFill(['is_detached' => true])->save();
        Form::query()->whereKey($instance->object_id)->update(['blueprint_instance_id' => null]);
        $this->audit->record('blueprint.detached', 'config', null, 'blueprint', $instance->blueprint_id, ['instance' => $instance->uuid]);
    }

    /** @return list<array{0: BlueprintInstance, 1: Form}> */
    private function outdated(Blueprint $bp): array
    {
        $out = [];
        foreach (BlueprintInstance::query()->where('blueprint_id', $bp->id)->where('is_detached', false)->where('object_type', 'form')->orderBy('id')->get() as $instance) {
            if ($instance->baseVersionId() === $bp->current_version_id) {
                continue;
            }
            $form = Form::query()->find($instance->object_id);
            if ($form !== null && $form->state !== 'schema_inconsistent') {
                $out[] = [$instance, $form];
            }
        }

        return $out;
    }

    /**
     * Three-way merge of base → current blueprint content onto the object's draft.
     *
     * @return array{document: array<string, mixed>, changes: list<array<string, mixed>>}
     */
    private function plan(Blueprint $bp, BlueprintInstance $instance, Form $form): array
    {
        $baseVersion = BlueprintVersion::query()->findOrFail($instance->baseVersionId());
        $currentVersion = BlueprintVersion::query()->findOrFail($bp->current_version_id);
        $base = $this->mapped($baseVersion->content, $form->uuid);
        $target = $this->mapped($currentVersion->content, $form->uuid);
        $local = $this->drafts->normalize($this->drafts->load($form));
        $changes = [];

        foreach (self::LISTS as $list) {
            $b = array_column($base[$list] ?? [], null, 'uuid');
            $t = array_column($target[$list] ?? [], null, 'uuid');
            $l = array_column($local[$list] ?? [], null, 'uuid');
            $localKeys = $list === 'conditions' ? [] : array_column($local[$list] ?? [], 'uuid', 'key');
            foreach (array_unique([...array_keys($b), ...array_keys($t)]) as $uuid) {
                $bo = $b[$uuid] ?? null;
                $to = $t[$uuid] ?? null;
                $lo = $l[$uuid] ?? null;
                if (self::same($bo, $to)) {
                    continue;
                }
                $kind = rtrim($list, 's');
                $label = $to['key'] ?? $bo['key'] ?? $to['name'] ?? $bo['name'] ?? $uuid;
                [$action, $status, $reason] = match (true) {
                    $bo === null && $lo === null && isset($to['key'], $localKeys[$to['key']]) => ['add', 'conflict', 'key_taken'],
                    $bo === null && $lo === null => ['add', 'apply', null],
                    $bo === null && self::same($lo, $to) => ['add', 'skip', 'already_present'],
                    $bo === null => ['add', 'conflict', 'modified_locally'],
                    $to === null && $lo === null => ['remove', 'skip', 'already_removed'],
                    $to === null && self::same($lo, $bo) => ['remove', 'apply', null],
                    $to === null => ['remove', 'conflict', 'modified_locally'],
                    $lo === null => ['update', 'conflict', 'removed_locally'],
                    self::same($lo, $bo) => ['update', 'apply', null],
                    self::same($lo, $to) => ['update', 'skip', 'already_present'],
                    default => ['update', 'conflict', 'modified_locally'],
                };
                $changes[] = ['kind' => $kind, 'uuid' => $uuid, 'label' => (string) $label, 'action' => $action, 'status' => $status, 'reason' => $reason, 'value' => $to];
            }
        }
        // Form-level and collection settings, property by property.
        foreach (['form', 'collection'] as $section) {
            $b = $base[$section] ?? [];
            $t = $target[$section] ?? [];
            $l = $local[$section] ?? [];
            if (! is_array($b) || ! is_array($t) || ! is_array($l)) {
                continue;
            }
            foreach (array_unique([...array_keys($b), ...array_keys($t)]) as $prop) {
                if (in_array($prop, self::LOCAL_FORM_PROPS, true) || ($section === 'form' && $prop === 'i18n')) {
                    continue;
                }
                $bo = $b[$prop] ?? null;
                $to = $t[$prop] ?? null;
                if (self::same($bo, $to)) {
                    continue;
                }
                $lo = $l[$prop] ?? null;
                $status = self::same($lo, $bo) ? 'apply' : (self::same($lo, $to) ? 'skip' : 'conflict');
                $changes[] = ['kind' => $section, 'uuid' => null, 'label' => (string) $prop, 'action' => 'update', 'status' => $status, 'reason' => $status === 'conflict' ? 'modified_locally' : null, 'value' => $to];
            }
        }

        $doc = $local;
        foreach ($changes as $c) {
            if ($c['status'] !== 'apply') {
                continue;
            }
            if ($c['kind'] === 'form' || $c['kind'] === 'collection') {
                $doc[$c['kind']][$c['label']] = $c['value'];

                continue;
            }
            $list = $c['kind'].'s';
            $doc[$list] = match ($c['action']) {
                'add' => [...$doc[$list], $c['value']],
                'remove' => array_values(array_filter($doc[$list], static fn ($o) => $o['uuid'] !== $c['uuid'])),
                default => array_map(static fn ($o) => $o['uuid'] === $c['uuid'] ? $c['value'] : $o, $doc[$list]),
            };
        }

        return ['document' => $doc, 'changes' => $changes];
    }

    // ── Export / import ──────────────────────────────────────────────────

    /** @return array<string, mixed> */
    public function export(Blueprint $bp): array
    {
        return [
            'format' => 'lcf-blueprint',
            'formatVersion' => self::FORMAT,
            'exportedAt' => now('UTC')->format('Y-m-d\TH:i:s\Z'),
            'blueprint' => [
                'uuid' => $bp->uuid, 'kind' => $bp->kind, 'category' => $bp->category, 'tags' => $bp->tags ?? [],
                'i18n' => ['name' => $bp->translationsFor('name'), 'description' => $bp->translationsFor('description')],
            ],
            'versions' => BlueprintVersion::query()->where('blueprint_id', $bp->id)->orderBy('version')->get()
                ->map(static fn (BlueprintVersion $v) => [
                    'uuid' => $v->uuid, 'version' => $v->version, 'includeMode' => $v->include_mode, 'changelog' => $v->changelog,
                    'contentHash' => $v->content_hash, 'content' => $v->content,
                ])->all(),
        ];
    }

    /**
     * Imports an exported blueprint. An existing blueprint with the same uuid
     * in this organization gains the versions it does not have yet (matched by
     * content hash); otherwise a new blueprint is created.
     *
     * @param  array<string, mixed>  $payload
     * @return array{blueprint: Blueprint, added: int}
     */
    public function import(array $payload): array
    {
        if (($payload['format'] ?? null) !== 'lcf-blueprint' || ($payload['formatVersion'] ?? null) !== self::FORMAT
            || ! is_array($payload['blueprint'] ?? null) || ! is_array($payload['versions'] ?? null) || $payload['versions'] === []) {
            throw ValidationException::withMessages(['file' => __('blueprints.import_format')]);
        }
        $meta = $payload['blueprint'];
        if (! in_array($meta['kind'] ?? null, ['form', 'collection'], true)) {
            throw ValidationException::withMessages(['file' => __('blueprints.kind_unavailable')]);
        }
        $versions = $payload['versions'];
        usort($versions, static fn ($a, $b) => ($a['version'] ?? 0) <=> ($b['version'] ?? 0));
        foreach ($versions as $i => $v) {
            $content = $v['content'] ?? null;
            if (! is_array($content) || ! hash_equals((string) ($v['contentHash'] ?? ''), self::hash($content))) {
                throw ValidationException::withMessages(['file' => __('blueprints.import_tampered', ['version' => $v['version'] ?? $i + 1])]);
            }
            if (($content['kind'] ?? null) !== $meta['kind'] || ! isset(self::MODES[$v['includeMode'] ?? ''])) {
                throw ValidationException::withMessages(['file' => __('blueprints.import_format')]);
            }
            $doc = $content['document'] ?? null;
            $doc = is_array($doc) ? $doc + ['form' => []] : null;
            if ($doc === null || $this->schemas->errors('https://schemas.core-lcf/form-draft/v1', $this->drafts->normalize($this->withLocalProps($doc))) !== []) {
                throw ValidationException::withMessages(['file' => __('blueprints.import_invalid', ['version' => $v['version'] ?? $i + 1])]);
            }
        }

        return DB::transaction(function () use ($meta, $versions): array {
            $uuid = is_string($meta['uuid'] ?? null) && Str::isUuid($meta['uuid']) ? strtolower($meta['uuid']) : (string) Str::uuid7();
            $bp = Blueprint::query()->where('uuid', $uuid)->first();
            if ($bp === null) {
                $taken = Blueprint::query()->withoutGlobalScopes()->where('uuid', $uuid)->exists();
                $bp = new Blueprint([
                    'kind' => $meta['kind'], 'category' => is_string($meta['category'] ?? null) ? mb_substr($meta['category'], 0, 64) : null,
                    'tags' => array_values(array_filter((array) ($meta['tags'] ?? []), 'is_string')), 'is_library' => false,
                ]);
                $bp->uuid = $taken ? (string) Str::uuid7() : $uuid;
                $bp->organization_id = $this->tenant->organizationId();
                $bp->save();
                foreach (['name', 'description'] as $field) {
                    $values = array_filter((array) ($meta['i18n'][$field] ?? []), 'is_string');
                    if ($values !== []) {
                        $bp->setTranslations($field, $values);
                    }
                }
            } elseif ($bp->kind !== $meta['kind']) {
                throw ValidationException::withMessages(['file' => __('blueprints.kind_mismatch')]);
            }
            $known = BlueprintVersion::query()->where('blueprint_id', $bp->id)->pluck('content_hash')->all();
            $added = 0;
            foreach ($versions as $v) {
                if (in_array($v['contentHash'], $known, true)) {
                    continue;
                }
                $number = (int) BlueprintVersion::query()->where('blueprint_id', $bp->id)->max('version') + 1;
                $version = BlueprintVersion::query()->create([
                    'blueprint_id' => $bp->id, 'version' => $number, 'content' => $v['content'], 'content_hash' => $v['contentHash'],
                    'include_mode' => $v['includeMode'], 'changelog' => is_string($v['changelog'] ?? null) ? $v['changelog'] : null,
                ]);
                $bp->forceFill(['current_version_id' => $version->id])->save();
                $known[] = $v['contentHash'];
                $added++;
            }
            $this->audit->record('blueprint.imported', 'config', null, 'blueprint', $bp->id, ['versions_added' => $added]);

            return ['blueprint' => $bp, 'added' => $added];
        });
    }

    /**
     * A captured document plus placeholder instance properties, so it can be
     * checked against the draft schema.
     *
     * @param  array<string, mixed>  $doc
     * @return array<string, mixed>
     */
    private function withLocalProps(array $doc): array
    {
        $doc['form'] += ['key' => 'blueprint_check', 'bindingMode' => 'managed'];

        return $doc;
    }

    // ── Helpers ──────────────────────────────────────────────────────────

    /** @param  array<string, mixed>  $content */
    public static function hash(array $content): string
    {
        return hash('sha256', (string) json_encode(self::canonical($content), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION));
    }

    /**
     * A stable uuid for an element of an instance: version 8 (custom) built
     * from SHA-256 of the instance's form uuid and the blueprint element uuid.
     */
    public static function derive(string $formUuid, string $uuid): string
    {
        $h = hash('sha256', strtolower($formUuid).'|'.strtolower($uuid));
        $h[12] = '8';
        $h[16] = dechex((hexdec($h[16]) & 0x3) | 0x8);

        return substr($h, 0, 8).'-'.substr($h, 8, 4).'-'.substr($h, 12, 4).'-'.substr($h, 16, 4).'-'.substr($h, 20, 12);
    }

    private static function same(mixed $a, mixed $b): bool
    {
        return json_encode(self::canonical($a)) === json_encode(self::canonical($b));
    }

    private static function canonical(mixed $v): mixed
    {
        if (! is_array($v)) {
            return $v;
        }
        if (! array_is_list($v)) {
            ksort($v);
        }

        return array_map(self::canonical(...), $v);
    }
}
