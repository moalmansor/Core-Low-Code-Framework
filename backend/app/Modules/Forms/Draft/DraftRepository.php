<?php

declare(strict_types=1);

namespace App\Modules\Forms\Draft;

use App\Infrastructure\Database\Contracts\DatabaseDriver;
use App\Infrastructure\Database\Naming;
use App\Modules\Core\I18n\Translator;
use App\Modules\Forms\Definition\TargetSchemaBuilder;
use App\Modules\Forms\FieldTypes\FieldTypeRegistry;
use App\Modules\Forms\FormTables;
use App\Modules\Forms\Models\Collection;
use App\Modules\Forms\Models\Condition;
use App\Modules\Forms\Models\Field;
use App\Modules\Forms\Models\FieldGroup;
use App\Modules\Forms\Models\FieldOption;
use App\Modules\Forms\Models\Form;
use App\Modules\Forms\Models\Relation;
use App\Support\Html\HtmlSanitizer;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Reads and writes a form's draft (ADR-0018): the draft document of
 * architecture §14.1 is the API shape; the working tables (field_groups,
 * fields, field_options, conditions, relations, collections) are its storage.
 * Saves are whole-document and guarded by `forms.draft_updated_at` so two
 * builder sessions never overwrite each other silently.
 */
final class DraftRepository
{
    /** Draft i18n key => translation field, per object type. */
    private const FIELD_I18N = [
        'label' => 'label', 'placeholder' => 'placeholder', 'help' => 'help_text', 'tooltip' => 'tooltip',
        'description' => 'description', 'prefix' => 'prefix', 'suffix' => 'suffix', 'columnLabel' => 'column_label',
        'content' => 'content', 'consentTerms' => 'consent_terms',
    ];

    public function __construct(
        private readonly Translator $translator,
        private readonly DraftValidator $validator,
        private readonly TargetSchemaBuilder $schema,
        private readonly HtmlSanitizer $sanitizer,
        private readonly DatabaseDriver $driver,
    ) {}

    /** @return array<string, mixed> */
    public function load(Form $form): array
    {
        $groups = FieldGroup::query()->where('form_id', $form->id)->whereNull('archived_at')->orderBy('sort_order')->orderBy('id')->get();
        $fields = Field::query()->where('form_id', $form->id)->whereNull('archived_at')->orderBy('sort_order')->orderBy('id')->get();
        $relations = Relation::query()->where('source_form_id', $form->id)->orderBy('id')->get();
        $conditions = Condition::query()->where('form_id', $form->id)->orderBy('sort_order')->orderBy('id')->get();
        $options = FieldOption::query()->whereIn('field_id', $fields->pluck('id'))->orderBy('sort_order')->orderBy('id')->get()->groupBy('field_id');

        $groupUuid = $groups->pluck('uuid', 'id')->all();
        $fieldUuid = Field::query()->where('form_id', $form->id)->pluck('uuid', 'id')->all();
        $relationUuid = $relations->pluck('uuid', 'id')->all();
        $optionUuid = FieldOption::query()->whereIn('field_id', array_keys($fieldUuid))->pluck('uuid', 'id')->all();
        $conditionUuid = $conditions->pluck('uuid', 'id')->all();
        $formUuids = Form::query()->whereIn('id', [...$relations->pluck('target_form_id')->all(), ...$groups->pluck('subform_form_id')->filter()->all()])->pluck('uuid', 'id')->all();
        $targetFieldUuids = Field::query()->whereIn('id', [...$relations->pluck('display_field_id')->filter()->all(), ...$relations->pluck('value_field_id')->filter()->all()])->pluck('uuid', 'id')->all();
        $templateUuids = DB::table('field_templates')->whereIn('id', $fields->pluck('template_id')->filter()->all())->pluck('uuid', 'id')->all();

        $fieldTr = $this->translator->allMany('field', $fields->pluck('id')->map(fn ($v) => (int) $v)->all());
        $groupTr = $this->translator->allMany('field_group', $groups->pluck('id')->map(fn ($v) => (int) $v)->all());
        $optionTr = $this->translator->allMany('field_option', $options->flatten()->pluck('id')->map(fn ($v) => (int) $v)->all());
        $formTr = $this->translator->allMany('form', [$form->id])[$form->id] ?? [];

        $doc = [
            'form' => [
                'uuid' => $form->uuid,
                'key' => $form->key,
                'kind' => $form->kind,
                'bindingMode' => $form->binding_mode,
                'boundTable' => $form->binding_mode === 'bound' ? $form->table_name : null,
                'application' => DB::table('applications')->where('id', $form->application_id)->value('uuid'),
                'icon' => $form->icon,
                'dataSharing' => $form->data_sharing,
                'numbering' => $form->numbering_sequence_id === null ? null : DB::table('number_sequences')->where('id', $form->numbering_sequence_id)->value('uuid'),
                'calendar' => $form->business_calendar_id === null ? null : DB::table('business_calendars')->where('id', $form->business_calendar_id)->value('uuid'),
                'titleTemplate' => $form->title_template,
                'settings' => (object) ($form->settings ?? []),
                'version' => $form->current_version_id === null ? null : DB::table('form_versions')->where('id', $form->current_version_id)->value('version_number'),
                'state' => $form->state,
                'i18n' => $this->i18n($formTr, ['name' => 'name', 'description' => 'description', 'submitButtonLabel' => 'submit_button_label']),
            ],
            'collection' => null,
            'groups' => [],
            'fields' => [],
            'relations' => [],
            'conditions' => [],
        ];
        if ($form->kind === 'collection') {
            $c = Collection::query()->where('form_id', $form->id)->first();
            $doc['collection'] = $c === null ? null : [
                'type' => $c->collection_type,
                'sharedReference' => $c->is_shared_reference,
                'ownerApplication' => $c->owner_application_id === null ? null : DB::table('applications')->where('id', $c->owner_application_id)->value('uuid'),
                'valueField' => $fieldUuid[$c->value_field_id] ?? null,
                'labelField' => $fieldUuid[$c->label_field_id] ?? null,
                'parentField' => $fieldUuid[$c->parent_field_id] ?? null,
            ];
        }
        foreach ($groups as $g) {
            $doc['groups'][] = [
                'uuid' => $g->uuid,
                'key' => $g->key,
                'type' => $g->type,
                'parent' => $groupUuid[$g->parent_group_id] ?? null,
                'order' => $g->sort_order,
                'layout' => (object) ($g->layout ?? []),
                'collapsible' => $g->collapsible,
                'defaultState' => $g->default_state,
                'validation' => $g->validation,
                'repeater' => $g->repeater,
                'wizard' => $g->wizard,
                'subform' => $g->subform_form_id === null ? null : ['form' => $formUuids[$g->subform_form_id] ?? null, 'relation' => $relationUuid[$g->relation_id] ?? null],
                'justification' => $g->justification_level,
                'i18n' => $this->i18n($groupTr[$g->id] ?? [], ['title' => 'title', 'description' => 'description']),
            ];
        }
        foreach ($fields as $f) {
            $tr = $fieldTr[$f->id] ?? [];
            $i18n = $this->i18n($tr, self::FIELD_I18N);
            $messages = [];
            foreach ($tr as $trField => $locales) {
                if (str_starts_with($trField, 'validation.')) {
                    $messages[substr($trField, 11)] = $locales;
                }
            }
            if ($messages !== []) {
                $i18n['messages'] = $messages;
            }
            $opts = $f->options_source;
            if ($opts !== null && ($opts['source'] ?? null) === 'static') {
                $opts['static'] = ($options[$f->id] ?? collect())->map(fn (FieldOption $o): array => [
                    'uuid' => $o->uuid,
                    'value' => $o->value,
                    'group' => $o->group_key,
                    'parent' => $o->parent_value,
                    'color' => $o->color,
                    'icon' => $o->icon,
                    'default' => $o->is_default,
                    'active' => $o->is_active,
                    'order' => $o->sort_order,
                    'condition' => $conditionUuid[$o->condition_id] ?? null,
                    'i18n' => $this->i18n($optionTr[$o->id] ?? [], ['label' => 'label']),
                ])->values()->all();
            }
            $doc['fields'][] = [
                'uuid' => $f->uuid,
                'key' => $f->key,
                'type' => $f->type,
                'group' => $groupUuid[$f->group_id] ?? null,
                'order' => $f->sort_order,
                'storage' => [
                    'column' => $form->binding_mode === 'bound' && ($f->ui['_bound'] ?? false) ? null : ($f->column_name === $f->key ? null : $f->column_name),
                    'boundColumn' => ($f->ui['_bound'] ?? false) ? $f->column_name : null,
                    'dbType' => $f->db_type,
                    'length' => $f->length,
                    'precision' => $f->precision,
                    'scale' => $f->scale,
                    'nullable' => $f->is_nullable,
                    'default' => $f->db_default['v'] ?? null,
                    'index' => $f->index_type,
                    'uniqueScope' => array_values(array_filter(array_map(fn ($id) => $fieldUuid[$id] ?? null, $f->unique_scope ?? []))),
                    'multiCurrency' => (bool) ($f->db_default['multiCurrency'] ?? false),
                ],
                'options' => $opts,
                'validation' => (object) ($f->validation ?? []),
                'behavior' => (object) ($f->behavior ?? []),
                'ui' => (object) array_diff_key($f->ui ?? [], ['_bound' => true]),
                'table' => (object) ($f->table_settings ?? []),
                'export' => (object) ($f->export_settings ?? []),
                'events' => $f->events ?? [],
                'hook' => $f->hook_binding,
                'flags' => [
                    'encrypted' => $f->is_encrypted,
                    'blindIndex' => $f->blind_index,
                    'sensitive' => $f->is_sensitive,
                    'personal' => $f->is_personal_data,
                    'trackChanges' => $f->track_changes,
                ],
                'justification' => $f->justification_level,
                'relation' => $relationUuid[$f->relation_id] ?? null,
                'template' => $templateUuids[$f->template_id] ?? null,
                'i18n' => (object) $i18n,
            ];
        }
        foreach ($relations as $r) {
            $doc['relations'][] = [
                'uuid' => $r->uuid,
                'key' => $r->key,
                'type' => $r->type,
                'target' => $formUuids[$r->target_form_id] ?? null,
                'kind' => $r->kind,
                'onDelete' => $r->on_delete,
                'display' => $targetFieldUuids[$r->display_field_id] ?? null,
                'value' => $targetFieldUuids[$r->value_field_id] ?? null,
                'inverse' => $r->inverse_key,
            ];
        }
        foreach ($conditions as $c) {
            $doc['conditions'][] = [
                'uuid' => $c->uuid,
                'owner' => ['type' => $c->owner_type, 'uuid' => match ($c->owner_type) {
                    'form' => $form->uuid,
                    'field' => $fieldUuid[$c->owner_id] ?? null,
                    'group' => $groupUuid[$c->owner_id] ?? FieldGroup::query()->whereKey($c->owner_id)->value('uuid'),
                    'option' => $optionUuid[$c->owner_id] ?? null,
                    default => null,
                }],
                'name' => $c->name,
                'when' => $c->ast,
                'effects' => $c->effects,
                'else' => $c->else_effects ?? [],
                'evaluateOn' => $c->evaluate_on,
                'runtime' => $c->runtime,
                'order' => $c->sort_order,
                'active' => $c->is_active,
            ];
        }

        return $doc;
    }

    /**
     * Validates and stores a draft document.
     *
     * @param  array<string, mixed>  $doc
     * @param  array<string, mixed>|null  $published  current published definition (null before the first publish)
     * @return array{draft_updated_at: string, problems: list<array<string, mixed>>}
     *
     * @throws DraftInvalid|DraftConflict
     */
    public function save(Form $form, array $doc, ?string $expectedUpdatedAt, ?array $published): array
    {
        $doc = $this->normalize($doc);
        $result = $this->validator->validate($doc, $form, $published);
        if ($result['errors'] !== []) {
            throw new DraftInvalid($result['errors']);
        }

        $stamp = DB::transaction(function () use ($form, $doc, $expectedUpdatedAt, $published): string {
            $locked = $this->driver->lockForUpdate(Form::query()->whereKey($form->id)->getQuery())->first();
            $current = $locked?->draft_updated_at === null ? null : Carbon::parse($locked->draft_updated_at)->format('Y-m-d\TH:i:s.u\Z');
            if ($current !== null && $expectedUpdatedAt !== null && ! hash_equals($current, $expectedUpdatedAt)) {
                throw new DraftConflict($current, $locked->draft_updated_by === null ? null : (int) $locked->draft_updated_by);
            }
            $this->persist($form, $doc, $published);
            $now = Carbon::now('UTC');
            $form->forceFill(['draft_updated_at' => $now, 'draft_updated_by' => Auth::id()])->saveQuietly();

            return $now->format('Y-m-d\TH:i:s.u\Z');
        });

        return ['draft_updated_at' => $stamp, 'problems' => $result['problems']];
    }

    /** Problems of the stored draft (design faults that block publishing). */
    public function problems(Form $form, ?array $published): array
    {
        return $this->validator->validate($this->normalize($this->load($form)), $form, $published)['problems'];
    }

    public function draftStamp(Form $form): ?string
    {
        return $form->draft_updated_at?->clone()->utc()->format('Y-m-d\TH:i:s.u\Z');
    }

    /**
     * JSON round trip so empty objects and arrays compare and validate the same
     * whether the document came from the API or from load().
     *
     * @param  array<string, mixed>  $doc
     * @return array<string, mixed>
     */
    public function normalize(array $doc): array
    {
        return json_decode((string) json_encode($doc, JSON_THROW_ON_ERROR | JSON_PRESERVE_ZERO_FRACTION), true, 512, JSON_THROW_ON_ERROR);
    }

    /**
     * @param  array<string, mixed>  $doc
     * @param  array<string, mixed>|null  $published
     */
    private function persist(Form $form, array $doc, ?array $published): void
    {
        $f = $doc['form'];
        $publishedFields = array_column($published['fields'] ?? [], null, 'uuid');
        $publishedGroups = array_column($published['groups'] ?? [], null, 'uuid');
        $now = Carbon::now('UTC');

        // Form row.
        $updates = [
            'icon' => $f['icon'] ?? null,
            'data_sharing' => $f['dataSharing'] ?? $form->data_sharing,
            'title_template' => $f['titleTemplate'] ?? null,
            'settings' => $f['settings'] ?? [],
            'numbering_sequence_id' => isset($f['numbering']) ? DB::table('number_sequences')->where('uuid', $f['numbering'])->value('id') : null,
            'business_calendar_id' => isset($f['calendar']) ? DB::table('business_calendars')->where('uuid', $f['calendar'])->value('id') : null,
        ];
        if (isset($f['application'])) {
            $appId = DB::table('applications')->where('organization_id', $form->organization_id)->where('uuid', $f['application'])->whereNull('deleted_at')->value('id');
            if ($appId !== null) {
                $updates['application_id'] = $appId;
            }
        }
        if (! $form->isPublished()) {
            $updates['key'] = $f['key'];
            $updates['binding_mode'] = $f['bindingMode'] ?? 'managed';
            $updates['table_name'] = $updates['binding_mode'] === 'bound' && ! empty($f['boundTable'])
                ? $f['boundTable']
                : app(FormTables::class)->tableFor($form, $f['key']);
        }
        $form->fill($updates)->save();
        $this->translator->syncObjects('form', [$form->id => $this->fromI18n($f['i18n'] ?? [], ['name' => 'name', 'description' => 'description', 'submitButtonLabel' => 'submit_button_label'])]);

        // Removed elements first (their keys are freed), and keys that change are
        // parked under temporary names so swaps never collide.
        $docFieldUuids = array_column($doc['fields'], 'uuid');
        $docGroupUuids = array_column($doc['groups'], 'uuid');
        foreach (Field::query()->where('form_id', $form->id)->whereNotIn('uuid', $docFieldUuids ?: ['-'])->whereNull('archived_at')->get() as $removed) {
            if (isset($publishedFields[$removed->uuid])) {
                // The key is freed for new fields; the published definition keeps the original.
                $removed->forceFill(['archived_at' => $now, 'key' => substr('zz_'.$removed->id.'_'.$removed->key, 0, 48)])->save();
            } else {
                DB::table('field_access_rules')->where('field_id', $removed->id)->delete();
                DB::table('relations')->where('display_field_id', $removed->id)->update(['display_field_id' => null]);
                DB::table('relations')->where('value_field_id', $removed->id)->update(['value_field_id' => null]);
                DB::table('number_sequences')->where('field_id', $removed->id)->update(['field_id' => null]);
                DB::table('files')->where('field_id', $removed->id)->update(['field_id' => null]);
                DB::table('collections')->where('value_field_id', $removed->id)->update(['value_field_id' => null]);
                DB::table('collections')->where('label_field_id', $removed->id)->update(['label_field_id' => null]);
                DB::table('collections')->where('parent_field_id', $removed->id)->update(['parent_field_id' => null]);
                FieldOption::query()->where('field_id', $removed->id)->delete();
                $this->translator->forget('field', $removed->id);
                $removed->delete();
            }
        }
        foreach (FieldGroup::query()->where('form_id', $form->id)->whereNotIn('uuid', $docGroupUuids ?: ['-'])->whereNull('archived_at')->orderByDesc('id')->get() as $removed) {
            if (isset($publishedGroups[$removed->uuid])) {
                $removed->forceFill(['archived_at' => $now, 'key' => substr('zz_'.$removed->id.'_'.$removed->key, 0, 48)])->save();
            } else {
                FieldGroup::query()->where('parent_group_id', $removed->id)->update(['parent_group_id' => null]);
                Field::query()->where('group_id', $removed->id)->update(['group_id' => null]);
                DB::table('field_access_rules')->where('group_id', $removed->id)->delete();
                $this->translator->forget('field_group', $removed->id);
                $removed->delete();
            }
        }
        foreach ([[Field::class, $doc['fields']], [FieldGroup::class, $doc['groups']]] as [$model, $items]) {
            $keys = array_column($items, 'key', 'uuid');
            foreach ($model::query()->where('form_id', $form->id)->whereIn('uuid', array_keys($keys) ?: ['-'])->get(['id', 'uuid', 'key']) as $row) {
                if ($row->key !== $keys[$row->uuid]) {
                    $model::query()->whereKey($row->id)->update(['key' => 'zz_tmp_'.$row->id]);
                }
            }
        }

        // Groups (two passes: rows, then parents).
        $groupIds = FieldGroup::query()->where('form_id', $form->id)->pluck('id', 'uuid')->all();
        $groupTr = [];
        foreach ($doc['groups'] as $g) {
            $row = FieldGroup::query()->firstOrNew(['uuid' => $g['uuid']], ['form_id' => $form->id]);
            $row->fill([
                'form_id' => $form->id,
                'key' => $g['key'],
                'type' => $g['type'],
                'sort_order' => $g['order'],
                'layout' => $g['layout'] ?? [],
                'collapsible' => $g['collapsible'] ?? false,
                'default_state' => $g['defaultState'] ?? 'open',
                'validation' => $g['validation'] ?? null,
                'repeater' => $g['type'] === 'repeater' ? ($g['repeater'] ?? []) : null,
                'wizard' => $g['type'] === 'wizard' ? ($g['wizard'] ?? []) : null,
                'justification_level' => $g['justification'] ?? 'inherit',
                'subform_form_id' => isset($g['subform']['form']) ? Form::query()->where('uuid', $g['subform']['form'])->value('id') : null,
                'archived_at' => null,
            ]);
            $row->uuid = $g['uuid'];
            $row->save();
            $groupIds[$g['uuid']] = $row->id;
            $groupTr[$row->id] = $this->fromI18n($g['i18n'] ?? [], ['title' => 'title', 'description' => 'description']);
        }
        foreach ($doc['groups'] as $g) {
            FieldGroup::query()->whereKey($groupIds[$g['uuid']])->update(['parent_group_id' => $g['parent'] === null ? null : $groupIds[$g['parent']]]);
        }
        $this->translator->syncObjects('field_group', $groupTr);

        // Relations.
        $relationIds = [];
        $targetTables = [];
        foreach ($doc['relations'] as $r) {
            $target = Form::query()->where('uuid', $r['target'])->firstOrFail();
            $targetTables[$r['uuid']] = $target->table_name;
            $row = Relation::query()->firstOrNew(['uuid' => $r['uuid']]);
            $usingField = collect($doc['fields'])->first(fn (array $fd): bool => ($fd['relation'] ?? null) === $r['uuid']);
            [$fkTable, $fkColumn, $pivot] = match (true) {
                $r['type'] === 'many_to_many' => [$form->table_name, null, Naming::fit('p_'.$f['key'].'__'.$r['key'])],
                $r['kind'] === 'subform' => [$target->table_name, Naming::fit($f['key'].'_'.$r['key'].'_id'), null],
                default => [$form->table_name, $usingField === null ? null : $this->schema->columnName($usingField), null],
            };
            $row->fill([
                'key' => $r['key'],
                'source_form_id' => $form->id,
                'target_form_id' => $target->id,
                'type' => $r['type'],
                'kind' => $r['kind'],
                'fk_table' => $fkTable,
                'fk_column' => $fkColumn,
                'pivot_table' => $pivot,
                'display_field_id' => isset($r['display']) ? Field::query()->where('form_id', $target->id)->where('uuid', $r['display'])->value('id') : null,
                'value_field_id' => isset($r['value']) ? Field::query()->where('form_id', $target->id)->where('uuid', $r['value'])->value('id') : null,
                'on_delete' => $r['onDelete'],
                'inverse_key' => $r['inverse'] ?? null,
                'is_cross_application' => $target->application_id !== $form->application_id,
            ]);
            $row->uuid = $r['uuid'];
            $row->save();
            $relationIds[$r['uuid']] = $row->id;
        }
        foreach ($doc['groups'] as $g) {
            FieldGroup::query()->whereKey($groupIds[$g['uuid']])->update([
                'relation_id' => isset($g['subform']['relation']) ? ($relationIds[$g['subform']['relation']] ?? null) : null,
                'child_table_name' => $g['type'] === 'repeater' ? Naming::fit($form->table_name.'__'.$g['key']) : null,
            ]);
        }

        // Fields.
        $fieldIds = Field::query()->where('form_id', $form->id)->pluck('id', 'uuid')->all();
        $templates = DB::table('field_templates')->whereIn('uuid', array_filter(array_column($doc['fields'], 'template')))->pluck('id', 'uuid')->all();
        $fieldTr = [];
        foreach ($doc['fields'] as $fd) {
            $type = FieldTypeRegistry::get($fd['type']);
            $storage = $fd['storage'] ?? [];
            $row = Field::query()->firstOrNew(['uuid' => $fd['uuid']]);
            $bound = ! empty($storage['boundColumn']);
            $opts = $fd['options'] ?? null;
            if ($opts !== null) {
                unset($opts['static']);
            }
            $row->fill([
                'form_id' => $form->id,
                'group_id' => $fd['group'] === null ? null : $groupIds[$fd['group']],
                'key' => $fd['key'],
                'type' => $fd['type'],
                'sort_order' => $fd['order'],
                'is_stored' => $type->isStored(),
                'column_name' => $type->isStored() ? ($bound ? $storage['boundColumn'] : $this->schema->columnName($fd)) : null,
                'db_type' => $storage['dbType'] ?? null,
                'length' => $storage['length'] ?? null,
                'precision' => $storage['precision'] ?? null,
                'scale' => $storage['scale'] ?? null,
                'is_nullable' => $storage['nullable'] ?? true,
                'db_default' => ['v' => $storage['default'] ?? null, 'multiCurrency' => (bool) ($storage['multiCurrency'] ?? false)],
                'index_type' => $storage['index'] ?? 'none',
                'unique_scope' => [],
                'is_encrypted' => $fd['flags']['encrypted'] ?? false,
                'blind_index' => $fd['flags']['blindIndex'] ?? false,
                'is_sensitive' => $fd['flags']['sensitive'] ?? false,
                'is_personal_data' => $fd['flags']['personal'] ?? false,
                'track_changes' => $fd['flags']['trackChanges'] ?? true,
                'relation_id' => isset($fd['relation']) ? ($relationIds[$fd['relation']] ?? null) : null,
                'options_source' => $opts,
                'validation' => $fd['validation'] ?? [],
                'behavior' => $fd['behavior'] ?? [],
                'ui' => ($fd['ui'] ?? []) + ($bound ? ['_bound' => true] : []),
                'table_settings' => $fd['table'] ?? [],
                'export_settings' => $fd['export'] ?? [],
                'events' => $fd['events'] ?? [],
                'hook_binding' => $fd['hook'] ?? null,
                'justification_level' => $fd['justification'] ?? 'inherit',
                'template_id' => isset($fd['template']) ? ($templates[$fd['template']] ?? null) : null,
                'archived_at' => null,
                'archived_column_name' => null,
            ]);
            $row->uuid = $fd['uuid'];
            $row->save();
            $fieldIds[$fd['uuid']] = $row->id;
            $tr = $this->fromI18n($fd['i18n'] ?? [], self::FIELD_I18N);
            if (isset($tr['content'])) {
                $tr['content'] = array_map(fn (?string $html): ?string => $html === null ? null : $this->sanitizer->clean($html), $tr['content']);
            }
            foreach ($fd['i18n']['messages'] ?? [] as $rule => $locales) {
                $tr['validation.'.$rule] = $locales;
            }
            $fieldTr[$row->id] = $tr;
        }
        foreach ($doc['fields'] as $fd) {
            $scope = array_values(array_filter(array_map(fn (string $u) => $fieldIds[$u] ?? null, $fd['storage']['uniqueScope'] ?? [])));
            Field::query()->whereKey($fieldIds[$fd['uuid']])->update(['unique_scope' => json_encode($scope)]);
        }
        $this->translator->syncObjects('field', $fieldTr);

        // Collection settings.
        if ($form->kind === 'collection' && ! empty($doc['collection'])) {
            $c = $doc['collection'];
            Collection::query()->updateOrCreate(['form_id' => $form->id], [
                'collection_type' => $c['type'],
                'is_shared_reference' => $c['sharedReference'] ?? false,
                'owner_application_id' => isset($c['ownerApplication']) ? DB::table('applications')->where('uuid', $c['ownerApplication'])->value('id') : null,
                'value_field_id' => isset($c['valueField']) ? $fieldIds[$c['valueField']] : null,
                'label_field_id' => isset($c['labelField']) ? $fieldIds[$c['labelField']] : null,
                'parent_field_id' => isset($c['parentField']) ? $fieldIds[$c['parentField']] : null,
            ]);
        }

        // Conditions (before options, which may reference them).
        $optionIds = FieldOption::query()->whereIn('field_id', array_values($fieldIds))->pluck('id', 'uuid')->all();
        $conditionIds = [];
        $keptConditions = [];
        foreach ($doc['conditions'] as $c) {
            $row = Condition::query()->firstOrNew(['uuid' => $c['uuid']]);
            $row->fill([
                'form_id' => $form->id,
                'owner_type' => $c['owner']['type'],
                'owner_id' => match ($c['owner']['type']) {
                    'form' => $form->id,
                    'field' => $fieldIds[$c['owner']['uuid']],
                    'group' => $groupIds[$c['owner']['uuid']],
                    default => $optionIds[$c['owner']['uuid']] ?? 0, // option
                },
                'name' => $c['name'] ?? null,
                'ast' => $c['when'],
                'effects' => $c['effects'],
                'else_effects' => $c['else'] ?? [],
                'evaluate_on' => $c['evaluateOn'] ?? 'always',
                'runtime' => $c['runtime'] ?? 'client_and_server',
                'sort_order' => $c['order'] ?? 0,
                'is_active' => $c['active'] ?? true,
            ]);
            $row->uuid = $c['uuid'];
            $row->save();
            $conditionIds[$c['uuid']] = $row->id;
            $keptConditions[] = $row->id;
        }

        // Static options.
        $optionTr = [];
        $keptOptions = [];
        foreach ($doc['fields'] as $fd) {
            if (($fd['options']['source'] ?? null) !== 'static') {
                continue;
            }
            foreach ($fd['options']['static'] ?? [] as $i => $o) {
                $row = FieldOption::query()->firstOrNew(['uuid' => $o['uuid']]);
                $row->fill([
                    'field_id' => $fieldIds[$fd['uuid']],
                    'value' => $o['value'],
                    'group_key' => $o['group'] ?? null,
                    'parent_value' => $o['parent'] ?? null,
                    'color' => $o['color'] ?? null,
                    'icon' => $o['icon'] ?? null,
                    'is_default' => $o['default'] ?? false,
                    'is_active' => $o['active'] ?? true,
                    'sort_order' => $o['order'] ?? $i,
                    'condition_id' => isset($o['condition']) ? ($conditionIds[$o['condition']] ?? null) : null,
                ]);
                $row->uuid = $o['uuid'];
                $row->save();
                $keptOptions[] = $row->id;
                $optionTr[$row->id] = $this->fromI18n($o['i18n'] ?? [], ['label' => 'label']);
            }
        }
        // Option-owned conditions saved before their option existed.
        foreach ($doc['conditions'] as $c) {
            if ($c['owner']['type'] === 'option') {
                $id = FieldOption::query()->where('uuid', $c['owner']['uuid'])->value('id');
                Condition::query()->whereKey($conditionIds[$c['uuid']])->update(['owner_id' => $id]);
            }
        }
        $this->translator->syncObjects('field_option', $optionTr);

        // Removals: published elements are archived (history, restorable); unpublished ones are deleted.
        $staleOptionIds = FieldOption::query()->whereIn('field_id', array_values($fieldIds))->whereNotIn('id', $keptOptions ?: [0])->pluck('id')->all();
        if ($staleOptionIds !== []) {
            FieldOption::query()->whereIn('id', $staleOptionIds)->delete();
            foreach ($staleOptionIds as $id) {
                $this->translator->forget('field_option', (int) $id);
            }
        }
        Condition::query()->where('form_id', $form->id)->whereNotIn('id', $keptConditions ?: [0])->delete();
        $relationUuids = array_column($doc['relations'], 'uuid');
        foreach (Relation::query()->where('source_form_id', $form->id)->whereNotIn('uuid', $relationUuids ?: ['-'])->get() as $removed) {
            Field::query()->where('relation_id', $removed->id)->update(['relation_id' => null]);
            FieldGroup::query()->where('relation_id', $removed->id)->update(['relation_id' => null]);
            $removed->delete();
        }
    }

    /**
     * @param  array<string, array<string, string>>  $stored  translation field => locale => value
     * @param  array<string, string>  $map  draft key => translation field
     * @return array<string, array<string, string>>|object
     */
    private function i18n(array $stored, array $map): array|object
    {
        $out = [];
        foreach ($map as $docKey => $field) {
            if (isset($stored[$field])) {
                $out[$docKey] = $stored[$field];
            }
        }

        return $out === [] ? (object) [] : $out;
    }

    /**
     * @param  array<string, mixed>  $i18n
     * @param  array<string, string>  $map
     * @return array<string, array<string, string|null>>
     */
    private function fromI18n(array $i18n, array $map): array
    {
        $out = [];
        foreach ($map as $docKey => $field) {
            $out[$field] = $i18n[$docKey] ?? [];
        }

        return $out;
    }
}
