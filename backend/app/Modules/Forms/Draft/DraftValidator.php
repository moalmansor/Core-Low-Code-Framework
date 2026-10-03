<?php

declare(strict_types=1);

namespace App\Modules\Forms\Draft;

use App\Expressions\Checking\TypeChecker;
use App\Expressions\StaticError;
use App\Expressions\Text\SafeRegex;
use App\Modules\Forms\Definition\PublishedDefinitions;
use App\Modules\Forms\Definition\SystemColumns;
use App\Modules\Forms\Definition\TargetSchemaBuilder;
use App\Modules\Forms\Definition\ValueTypes;
use App\Modules\Forms\FieldTypes\FieldTypeRegistry;
use App\Modules\Forms\Models\Form;
use App\Support\Json\SchemaValidator;

/**
 * Checks a draft document (architecture §14.1) beyond its JSON schema.
 *
 * - **errors** make the document unstorable (dangling references inside the
 *   document, duplicate uuids or keys, unknown types, group cycles) — the save
 *   is rejected with 422;
 * - **problems** are design faults that a draft may carry while the admin is
 *   still working (type errors in expressions, missing options, invalid
 *   nesting, column clashes) — the draft is stored and the problems are shown,
 *   but preview compilation and publishing refuse until none remain
 *   (ADR-0028).
 *
 * Each entry: {path, code, message, params}.
 */
final class DraftValidator
{
    public const SCHEMA_ID = 'https://schemas.core-lcf/form-draft/v1';

    /** @var list<array{path: string, code: string, message: string, params: array<string, mixed>}> */
    private array $errors = [];

    /** @var list<array{path: string, code: string, message: string, params: array<string, mixed>}> */
    private array $problems = [];

    /** @var array<string, array<string, mixed>> */
    private array $groups = [];

    /** @var array<string, array<string, mixed>> */
    private array $fields = [];

    /** @var array<string, array<string, mixed>> */
    private array $relations = [];

    public function __construct(
        private readonly SchemaValidator $schemas,
        private readonly PublishedDefinitions $definitions,
        private readonly TargetSchemaBuilder $schemaBuilder,
    ) {}

    /**
     * @param  array<string, mixed>  $doc
     * @param  array<string, mixed>|null  $published  current published definition of this form
     * @return array{errors: list<array<string, mixed>>, problems: list<array<string, mixed>>}
     */
    public function validate(array $doc, Form $form, ?array $published): array
    {
        $this->errors = [];
        $this->problems = [];
        foreach ($this->schemas->errors(self::SCHEMA_ID, $doc) as $pointer => $messages) {
            $this->error(str_replace('/', '.', ltrim($pointer, '/')), 'schema', implode(' ', $messages));
        }
        if ($this->errors !== []) {
            return ['errors' => $this->errors, 'problems' => []];
        }

        $this->index($doc);
        $this->checkForm($doc, $form, $published);
        $this->checkGroups($doc);
        $this->checkRelations($doc, $form);
        $this->checkFields($doc, $form);
        $this->checkConditions($doc);
        if ($this->errors === []) {
            $this->checkExpressions($doc);
            $this->checkColumns($doc);
        }

        return ['errors' => $this->errors, 'problems' => $this->problems];
    }

    /** @param  array<string, mixed>  $doc */
    private function index(array $doc): void
    {
        $this->groups = $this->fields = $this->relations = [];
        $uuids = [];
        $seen = function (string $uuid, string $path) use (&$uuids): void {
            if (isset($uuids[$uuid])) {
                $this->error($path.'.uuid', 'duplicate_uuid', "The identifier {$uuid} is used twice.", ['uuid' => $uuid]);
            }
            $uuids[$uuid] = true;
        };
        $seen($doc['form']['uuid'], 'form');
        $keys = [];
        foreach ($doc['groups'] as $i => $g) {
            $seen($g['uuid'], "groups.{$i}");
            if (isset($keys['g'][$g['key']])) {
                $this->error("groups.{$i}.key", 'duplicate_key', "Group key {$g['key']} is used twice.", ['key' => $g['key']]);
            }
            $keys['g'][$g['key']] = true;
            $this->groups[$g['uuid']] = $g + ['_i' => $i];
        }
        foreach ($doc['fields'] as $i => $f) {
            $seen($f['uuid'], "fields.{$i}");
            if (isset($keys['f'][$f['key']]) || isset($keys['g'][$f['key']])) {
                $this->error("fields.{$i}.key", 'duplicate_key', "Field key {$f['key']} is already used by another field or group.", ['key' => $f['key']]);
            }
            $keys['f'][$f['key']] = true;
            $this->fields[$f['uuid']] = $f + ['_i' => $i];
            foreach ($f['options']['static'] ?? [] as $j => $o) {
                $seen($o['uuid'], "fields.{$i}.options.static.{$j}");
            }
        }
        foreach ($doc['relations'] as $i => $r) {
            $seen($r['uuid'], "relations.{$i}");
            if (isset($keys['r'][$r['key']])) {
                $this->error("relations.{$i}.key", 'duplicate_key', "Relation key {$r['key']} is used twice.", ['key' => $r['key']]);
            }
            $keys['r'][$r['key']] = true;
            $this->relations[$r['uuid']] = $r + ['_i' => $i];
        }
        foreach ($doc['conditions'] as $i => $c) {
            $seen($c['uuid'], "conditions.{$i}");
        }
    }

    /**
     * @param  array<string, mixed>  $doc
     * @param  array<string, mixed>|null  $published
     */
    private function checkForm(array $doc, Form $form, ?array $published): void
    {
        $f = $doc['form'];
        if ($f['uuid'] !== $form->uuid) {
            $this->error('form.uuid', 'form_mismatch', 'The document belongs to another form.');
        }
        if ($f['kind'] !== $form->kind) {
            $this->error('form.kind', 'immutable', 'The kind of a form cannot change.');
        }
        if ($published !== null && $f['key'] !== $form->key) {
            $this->error('form.key', 'immutable_after_publish', 'The form key cannot change after the first publish.');
        }
        if ($published !== null && ($f['bindingMode'] ?? $form->binding_mode) !== $form->binding_mode) {
            $this->error('form.bindingMode', 'immutable_after_publish', 'The binding mode cannot change after the first publish.');
        }
        if ($form->kind === 'collection' && empty($doc['collection'])) {
            $this->problem('collection', 'collection_settings_missing', 'Collection settings are missing.');
        }
        foreach (['valueField', 'labelField', 'parentField'] as $k) {
            $uuid = $doc['collection'][$k] ?? null;
            if ($uuid !== null && ! isset($this->fields[$uuid])) {
                $this->error("collection.{$k}", 'unknown_field', 'The collection setting references an unknown field.');
            }
        }
        foreach ($f['settings']['searchFields'] ?? [] as $i => $uuid) {
            if (! isset($this->fields[$uuid])) {
                $this->error("form.settings.searchFields.{$i}", 'unknown_field', 'Search references an unknown field.');
            }
        }
    }

    /** @param  array<string, mixed>  $doc */
    private function checkGroups(array $doc): void
    {
        foreach ($this->groups as $uuid => $g) {
            $path = "groups.{$g['_i']}";
            $parent = $g['parent'];
            if ($parent !== null && ! isset($this->groups[$parent])) {
                $this->error("{$path}.parent", 'unknown_group', 'The parent group does not exist.');

                continue;
            }
            // Cycles.
            $seen = [$uuid => true];
            $p = $parent;
            while ($p !== null && isset($this->groups[$p])) {
                if (isset($seen[$p])) {
                    $this->error("{$path}.parent", 'group_cycle', 'Groups cannot contain themselves.');
                    break;
                }
                $seen[$p] = true;
                $p = $this->groups[$p]['parent'];
            }
            $parentType = $parent === null ? null : $this->groups[$parent]['type'];
            if (isset(FieldTypeRegistry::GROUP_PARENTS[$g['type']]) && ! in_array($parentType, FieldTypeRegistry::GROUP_PARENTS[$g['type']], true)) {
                $this->problem("{$path}.parent", 'invalid_nesting', "A {$g['type']} must be placed inside a ".implode(' or ', FieldTypeRegistry::GROUP_PARENTS[$g['type']]).'.', ['type' => $g['type']]);
            }
            if ($parentType !== null && isset(FieldTypeRegistry::EXCLUSIVE_CHILDREN[$parentType]) && FieldTypeRegistry::EXCLUSIVE_CHILDREN[$parentType] !== $g['type']) {
                $this->problem("{$path}.parent", 'invalid_nesting', "A {$parentType} may only contain ".FieldTypeRegistry::EXCLUSIVE_CHILDREN[$parentType].' elements.', ['type' => $parentType]);
            }
            if (in_array($g['type'], FieldTypeRegistry::DATA_GROUPS, true) && $this->dataAncestor($parent) !== null) {
                $this->problem("{$path}.parent", 'nested_data_group', 'Repeaters and sub-forms cannot be nested inside another repeater or sub-form.');
            }
            if ($g['type'] === 'repeater' && (($g['repeater']['maxRows'] ?? null) !== null) && ($g['repeater']['minRows'] ?? 0) > $g['repeater']['maxRows']) {
                $this->problem("{$path}.repeater.minRows", 'min_greater_than_max', 'The minimum number of rows is greater than the maximum.');
            }
            if ($g['type'] === 'subform') {
                if (empty($g['subform']['form'])) {
                    $this->problem("{$path}.subform.form", 'subform_target_missing', 'Choose the linked form for this sub-form.');
                } elseif (empty($g['subform']['relation']) || ! isset($this->relations[$g['subform']['relation']])) {
                    $this->problem("{$path}.subform.relation", 'subform_relation_missing', 'The sub-form needs a one-to-many relation to the linked form.');
                } else {
                    $r = $this->relations[$g['subform']['relation']];
                    if ($r['type'] !== 'one_to_many' || $r['kind'] !== 'subform' || $r['target'] !== $g['subform']['form']) {
                        $this->problem("{$path}.subform.relation", 'subform_relation_invalid', 'The sub-form relation must be a one-to-many sub-form relation to the linked form.');
                    }
                }
            }
            foreach ($g['repeater']['aggregates'] ?? [] as $j => $a) {
                if (! isset($this->fields[$a['field']]) || $this->repeaterOf($this->fields[$a['field']]['group']) !== $uuid) {
                    $this->problem("{$path}.repeater.aggregates.{$j}.field", 'unknown_field', 'Row totals must use a field of this repeater.');
                }
            }
        }
    }

    /** @param  array<string, mixed>  $doc */
    private function checkRelations(array $doc, Form $form): void
    {
        foreach ($this->relations as $r) {
            $path = "relations.{$r['_i']}";
            $target = Form::query()->where('uuid', $r['target'])->first();
            if ($target === null) {
                $this->error("{$path}.target", 'unknown_form', 'The related form does not exist.');

                continue;
            }
            if ($target->id !== $form->id && $target->application_id !== $form->application_id && $target->data_sharing === 'isolated') {
                $this->problem("{$path}.target", 'target_isolated', 'The related form keeps its data isolated to its application.');
            }
            if ($target->current_version_id === null && $target->id !== $form->id) {
                $this->problem("{$path}.target", 'target_unpublished', 'Publish the related form before publishing this one.');
            }
            if ($r['kind'] === 'child_table') {
                $this->problem("{$path}.kind", 'child_table_relation', 'Repeaters create their child tables automatically; use a reference or sub-form relation.');
            }
            if ($r['kind'] === 'subform' && $r['type'] !== 'one_to_many') {
                $this->problem("{$path}.type", 'subform_relation_invalid', 'Sub-form relations are one-to-many.');
            }
            if ($r['kind'] === 'reference' && $r['type'] === 'one_to_many') {
                $this->problem("{$path}.type", 'reference_one_to_many', 'A reference field points at one record or many records; define the reverse (one-to-many) side through the inverse name.');
            }
            $targetDef = $this->definitions->current($r['target']);
            foreach (['display', 'value'] as $k) {
                if (($r[$k] ?? null) !== null && $targetDef !== null && ! in_array($r[$k], array_column($targetDef['fields'] ?? [], 'uuid'), true)) {
                    $this->problem("{$path}.{$k}", 'unknown_field', 'The related form has no such field.');
                }
            }
            if ($r['onDelete'] === 'set_null') {
                $using = array_filter($this->fields, static fn (array $f): bool => ($f['relation'] ?? null) === $r['uuid']);
                foreach ($using as $f) {
                    if (($f['storage']['nullable'] ?? true) === false) {
                        $this->problem("fields.{$f['_i']}.storage.nullable", 'set_null_requires_nullable', 'A relation that sets the value to empty on delete needs a column that allows empty values.');
                    }
                }
            }
        }
    }

    /** @param  array<string, mixed>  $doc */
    private function checkFields(array $doc, Form $form): void
    {
        $bound = ($doc['form']['bindingMode'] ?? 'managed') === 'bound';
        foreach ($this->fields as $uuid => $f) {
            $path = "fields.{$f['_i']}";
            if (! FieldTypeRegistry::has($f['type'])) {
                $this->error("{$path}.type", 'unknown_type', "Unknown field type {$f['type']}.", ['type' => $f['type']]);

                continue;
            }
            $type = FieldTypeRegistry::get($f['type']);
            if ($f['group'] !== null && ! isset($this->groups[$f['group']])) {
                $this->error("{$path}.group", 'unknown_group', 'The group does not exist.');

                continue;
            }
            if ($f['group'] !== null && isset(FieldTypeRegistry::EXCLUSIVE_CHILDREN[$this->groups[$f['group']]['type']])) {
                $this->problem("{$path}.group", 'invalid_nesting', 'Fields go inside a tab, step or column, not directly in its container.');
            }
            if ($f['group'] !== null && $this->groups[$f['group']]['type'] === 'subform') {
                $this->problem("{$path}.group", 'invalid_nesting', 'A sub-form shows the linked form\'s own fields; add fields to that form instead.');
            }
            if (($f['relation'] ?? null) !== null && ! isset($this->relations[$f['relation']])) {
                $this->error("{$path}.relation", 'unknown_relation', 'The relation does not exist.');
            }
            $storageKind = $type->storage;
            $needsRelation = in_array($storageKind, ['lookup'], true)
                || (in_array($storageKind, ['choice', 'multi_choice'], true) && in_array($f['options']['source'] ?? 'static', ['collection', 'form'], true));
            if ($needsRelation && ($f['relation'] ?? null) === null) {
                $this->problem("{$path}.relation", 'relation_required', 'Choose the collection or form this field refers to.');
            }
            if ($type->options && empty($f['options'])) {
                $this->problem("{$path}.options", 'options_required', 'This field needs a list of options.');
            }
            $opts = $f['options'] ?? null;
            if ($opts !== null) {
                $values = [];
                foreach ($opts['static'] ?? [] as $j => $o) {
                    if (isset($values[$o['value']])) {
                        $this->problem("{$path}.options.static.{$j}.value", 'duplicate_option', 'Two options have the same value.');
                    }
                    $values[$o['value']] = true;
                    if (($o['condition'] ?? null) !== null && ! in_array($o['condition'], array_column($doc['conditions'], 'uuid'), true)) {
                        $this->error("{$path}.options.static.{$j}.condition", 'unknown_condition', 'The option condition does not exist.');
                    }
                }
                if (($opts['source'] ?? '') === 'static' && ($opts['static'] ?? []) === [] && $type->options) {
                    $this->problem("{$path}.options.static", 'options_required', 'Add at least one option.');
                }
                if (($opts['dependsOn'] ?? null) !== null && ! isset($this->fields[$opts['dependsOn']])) {
                    $this->error("{$path}.options.dependsOn", 'unknown_field', 'The field this list depends on does not exist.');
                }
                if (($opts['min'] ?? null) !== null && ($opts['max'] ?? null) !== null && $opts['min'] > $opts['max']) {
                    $this->problem("{$path}.options.min", 'min_greater_than_max', 'The minimum selection is greater than the maximum.');
                }
                if (in_array($opts['source'], ['collection', 'form'], true) && ($opts['collection'] ?? null) === null && ($f['relation'] ?? null) === null) {
                    $this->problem("{$path}.options.collection", 'options_source_missing', 'Choose where the options come from.');
                }
                if ($opts['source'] === 'query' && empty($opts['query'])) {
                    $this->problem("{$path}.options.query", 'options_source_missing', 'Build the options query.');
                }
            }
            $v = $f['validation'] ?? [];
            foreach (array_keys($v) as $rule) {
                if ($rule !== 'messages' && ! in_array($rule, $type->validation, true) && ! in_array($rule, ['required'], true) && ($v[$rule] ?? null) !== null && $v[$rule] !== [] && $v[$rule] !== false) {
                    $this->problem("{$path}.validation.{$rule}", 'rule_not_applicable', "The rule {$rule} does not apply to this field type.", ['rule' => $rule]);
                }
            }
            if (($v['pattern'] ?? null) !== null && $v['pattern'] !== '' && ! SafeRegex::isSafe($v['pattern'])) {
                $this->problem("{$path}.validation.pattern", 'unsafe_pattern', 'The pattern is not valid or uses features outside the safe subset.');
            }
            if (($v['length']['min'] ?? null) !== null && ($v['length']['max'] ?? null) !== null && $v['length']['min'] > $v['length']['max']) {
                $this->problem("{$path}.validation.length", 'min_greater_than_max', 'The minimum length is greater than the maximum.');
            }
            foreach (array_merge($v['unique']['scope'] ?? [], array_column($v['compare'] ?? [], 'field'), $f['storage']['uniqueScope'] ?? []) as $ref) {
                if (! isset($this->fields[$ref])) {
                    $this->error("{$path}.validation", 'unknown_field', 'A validation rule references an unknown field.');
                }
            }
            foreach ($f['behavior']['autofill'] ?? [] as $j => $a) {
                if (! isset($this->fields[$a['to']])) {
                    $this->error("{$path}.behavior.autofill.{$j}.to", 'unknown_field', 'Auto-fill targets an unknown field.');
                }
            }
            if (($f['behavior']['default']['field'] ?? null) !== null && ! isset($this->fields[$f['behavior']['default']['field']])) {
                $this->error("{$path}.behavior.default.field", 'unknown_field', 'The default value references an unknown field.');
            }
            foreach ($f['events'] ?? [] as $j => $e) {
                foreach ($e['do'] as $k => $d) {
                    if (in_array($d['type'], ['set_field', 'reload_options'], true) && (($d['target'] ?? null) === null || ! isset($this->fields[$d['target']]))) {
                        $this->problem("{$path}.events.{$j}.do.{$k}.target", 'unknown_field', 'The event targets an unknown field.');
                    }
                    if ($d['type'] === 'run_action' || $d['type'] === 'call_webhook') {
                        $this->problem("{$path}.events.{$j}.do.{$k}.type", 'not_available', 'Actions and webhooks become available in a later release of the platform.');
                    }
                }
            }
            if (($f['flags']['encrypted'] ?? false) && ! in_array($storageKind, ['string', 'text', 'longtext', 'json', 'phone'], true)) {
                $this->problem("{$path}.flags.encrypted", 'encryption_unsupported', 'Only text values can be stored encrypted.');
            }
            if (($f['flags']['encrypted'] ?? false) && in_array($f['storage']['index'] ?? 'none', ['index', 'unique'], true)) {
                $this->problem("{$path}.storage.index", 'encrypted_index', 'Encrypted values cannot be indexed; use the blind index for exact search.');
            }
            if ($type->calculated && in_array($f['type'], ['formula'], true) && ($f['behavior']['formula'] ?? null) === null) {
                $this->problem("{$path}.behavior.formula", 'formula_required', 'Write the formula of this calculated field.');
            }
            if ($storageKind === 'auto_number' && ($f['behavior']['autoNumber'] ?? null) === null) {
                $this->problem("{$path}.behavior.autoNumber", 'sequence_required', 'Choose the numbering sequence.');
            }
            if ($bound && $type->isStored() && $this->repeaterOf($f['group']) === null && empty($f['storage']['boundColumn']) && $f['storage']['column'] === null) {
                // Bound forms may add new columns too (managed by the framework); nothing to report.
            }
            if (in_array($f['type'], ['submit', 'reset', 'button', 'image_button'], true) && $this->repeaterOf($f['group']) !== null) {
                $this->problem("{$path}.group", 'invalid_nesting', 'Buttons cannot be placed inside a repeater.');
            }
        }
    }

    /** @param  array<string, mixed>  $doc */
    private function checkConditions(array $doc): void
    {
        $optionUuids = [];
        foreach ($this->fields as $f) {
            foreach ($f['options']['static'] ?? [] as $o) {
                $optionUuids[$o['uuid']] = true;
            }
        }
        foreach ($doc['conditions'] as $i => $c) {
            $owner = $c['owner'];
            $exists = match ($owner['type']) {
                'form' => $owner['uuid'] === $doc['form']['uuid'],
                'field' => isset($this->fields[$owner['uuid']]),
                'group' => isset($this->groups[$owner['uuid']]),
                default => isset($optionUuids[$owner['uuid']]), // option
            };
            if (! $exists) {
                $this->error("conditions.{$i}.owner", 'unknown_owner', 'The rule belongs to an element that does not exist.');
            }
            foreach (['effects' => $c['effects'], 'else' => $c['else'] ?? []] as $kind => $effects) {
                foreach ($effects as $j => $e) {
                    $target = $e['target'] ?? null;
                    if ($target === null) {
                        if ($e['effect'] === 'trigger_action') {
                            $this->problem("conditions.{$i}.{$kind}.{$j}", 'not_available', 'Actions become available in a later release of the platform.');
                        }

                        continue;
                    }
                    $ok = match ($target['type']) {
                        'field' => isset($this->fields[$target['uuid']]),
                        'group' => isset($this->groups[$target['uuid']]),
                        'option' => isset($optionUuids[$target['uuid']]),
                        default => false,
                    };
                    if (! $ok) {
                        $this->problem("conditions.{$i}.{$kind}.{$j}.target", 'unknown_target', 'The effect targets an element that does not exist.');
                    }
                    if ($e['effect'] === 'reload_options' && $target['type'] === 'field' && isset($this->fields[$target['uuid']]) && ! FieldTypeRegistry::get($this->fields[$target['uuid']]['type'])->options) {
                        $this->problem("conditions.{$i}.{$kind}.{$j}", 'effect_not_applicable', 'Only option lists can be reloaded.');
                    }
                }
            }
        }
    }

    /** Type-checks every expression of the draft against the draft's own fields. */
    private function checkExpressions(array $doc): void
    {
        $resolver = $this->resolver($doc);
        $check = function (?array $ast, string $path, ?string $expected = null, ?array $rowsPath = null) use ($resolver): void {
            if ($ast === null) {
                return;
            }
            try {
                $checker = $rowsPath === null ? $resolver : static fn (string $scope, array $p, ?array $rows) => $resolver($scope, $p, $rows ?? $rowsPath);
                TypeChecker::check($ast, $checker, $expected);
            } catch (StaticError $e) {
                $this->problem($path, 'expression_'.strtolower($e->errorCode), $e->getMessage(), ['node' => $e->node]);
            }
        };
        $check($doc['form']['titleTemplate'] ?? null, 'form.titleTemplate');
        foreach ($doc['conditions'] as $i => $c) {
            $rows = $this->rowsPathFor($c['owner']);
            $check($c['when'], "conditions.{$i}.when", 'boolean', $rows);
            foreach (['effects', 'else'] as $kind) {
                foreach ($c[$kind] ?? [] as $j => $e) {
                    if (isset($e['value'])) {
                        $check($e['value'], "conditions.{$i}.{$kind}.{$j}.value", null, $rows);
                    }
                }
            }
        }
        foreach ($this->fields as $f) {
            $path = "fields.{$f['_i']}";
            $rows = $this->rowsPathFor(['type' => 'field', 'uuid' => $f['uuid']]);
            $check($f['behavior']['formula'] ?? null, "{$path}.behavior.formula", null, $rows);
            $check($f['behavior']['default']['expr'] ?? null, "{$path}.behavior.default.expr", null, $rows);
            $check($f['validation']['date']['min'] ?? null, "{$path}.validation.date.min", null, $rows);
            $check($f['validation']['date']['max'] ?? null, "{$path}.validation.date.max", null, $rows);
            foreach ($f['validation']['custom'] ?? [] as $j => $c) {
                $check($c['when'], "{$path}.validation.custom.{$j}.when", 'boolean', $rows);
            }
            foreach ($f['events'] ?? [] as $j => $e) {
                $check($e['when'] ?? null, "{$path}.events.{$j}.when", 'boolean', $rows);
                foreach ($e['do'] as $k => $d) {
                    $check($d['value'] ?? null, "{$path}.events.{$j}.do.{$k}.value", null, $rows);
                }
            }
            $check($f['options']['query']['where'] ?? null, "{$path}.options.query.where", 'boolean');
        }
        foreach ($this->groups as $g) {
            foreach ($g['validation']['rules'] ?? [] as $j => $r) {
                $check($r['when'], "groups.{$g['_i']}.validation.rules.{$j}.when", 'boolean', $this->rowsPathFor(['type' => 'group', 'uuid' => $g['uuid']]));
            }
        }
    }

    /** Physical column names: unique per table, not reserved. */
    private function checkColumns(array $doc): void
    {
        $byTable = [];
        foreach ($this->fields as $f) {
            if (! FieldTypeRegistry::get($f['type'])->isStored() || ! empty($f['storage']['boundColumn'])) {
                continue;
            }
            $column = $this->schemaBuilder->columnName($f);
            $table = $this->repeaterOf($f['group']) ?? 'main';
            if (SystemColumns::isReserved($column)) {
                $this->problem("fields.{$f['_i']}.storage.column", 'reserved_column', "The column name {$column} is reserved.", ['column' => $column]);
            }
            if (isset($byTable[$table][$column])) {
                $this->problem("fields.{$f['_i']}.storage.column", 'duplicate_column', "Two fields use the column {$column}.", ['column' => $column]);
            }
            $byTable[$table][$column] = true;
        }
    }

    /**
     * Static type resolver over the draft: field keys and repeater group keys of
     * this form, relation paths into published target forms.
     *
     * @param  array<string, mixed>  $doc
     * @return callable(string, list<string>, ?list<string>): ?string
     */
    public function resolver(array $doc): callable
    {
        $byKey = [];
        $rowFields = [];
        $relations = [];
        foreach ($doc['relations'] as $r) {
            $relations[$r['uuid']] = $r;
        }
        foreach ($doc['groups'] as $g) {
            if ($g['type'] === 'repeater') {
                $byKey[$g['key']] = ['repeater' => $g['uuid']];
            }
        }
        $groups = [];
        foreach ($doc['groups'] as $g) {
            $groups[$g['uuid']] = $g;
        }
        foreach ($doc['fields'] as $f) {
            $repeater = $this->repeaterOfIn($f['group'], $groups);
            $entry = ['field' => $f, 'relation' => isset($f['relation']) ? ($relations[$f['relation']] ?? null) : null];
            if ($repeater !== null) {
                $rowFields[$repeater][$f['key']] = $entry;
            } else {
                $byKey[$f['key']] = $entry;
            }
        }
        $repeaterByKey = [];
        foreach ($doc['groups'] as $g) {
            if ($g['type'] === 'repeater') {
                $repeaterByKey[$g['key']] = $g['uuid'];
            }
        }

        $walk = function (array $entry, array $rest, int $hops) use (&$walk): ?string {
            $type = ValueTypes::of($entry['field'], $entry['relation']);
            if ($rest === []) {
                return $type;
            }
            $relation = $entry['relation'];
            if ($relation === null || $hops >= 4) {
                return null;
            }
            $target = $this->definitions->current($relation['target']);
            if ($target === null) {
                return null;
            }
            $next = null;
            $targetRelations = [];
            foreach ($target['relations'] ?? [] as $r) {
                $targetRelations[$r['uuid']] = $r;
            }
            foreach ($target['fields'] ?? [] as $tf) {
                if ($tf['key'] === $rest[0]) {
                    $next = ['field' => $tf, 'relation' => isset($tf['relation']) ? ($targetRelations[$tf['relation']] ?? null) : null];
                }
            }
            if ($next === null) {
                return null;
            }
            $inner = $walk($next, array_slice($rest, 1), $hops + 1);
            if ($inner === null) {
                return null;
            }
            $toMany = str_starts_with($type, 'list<');

            return $toMany && ! str_starts_with($inner, 'list<') ? 'list<'.$inner.'>' : $inner;
        };

        return function (string $scope, array $path, ?array $rowsPath) use ($byKey, $rowFields, $repeaterByKey, $walk): ?string {
            $first = $path[0];
            $rest = array_slice($path, 1);
            $rowRepeater = $rowsPath !== null ? ($repeaterByKey[$rowsPath[0]] ?? null) : null;
            if (($scope === 'row' || $scope === 'record') && $rowRepeater !== null && isset($rowFields[$rowRepeater][$first])) {
                return $walk($rowFields[$rowRepeater][$first], $rest, 0);
            }
            if ($scope === 'row') {
                return null;
            }
            $entry = $byKey[$first] ?? null;
            if ($entry === null) {
                return null;
            }
            if (isset($entry['repeater'])) {
                if ($rest === []) {
                    return 'list<record>';
                }
                $rf = $rowFields[$entry['repeater']][$rest[0]] ?? null;
                if ($rf === null) {
                    return null;
                }
                $inner = $walk($rf, array_slice($rest, 1), 1);

                return $inner === null ? null : (str_starts_with($inner, 'list<') ? $inner : 'list<'.$inner.'>');
            }

            return $walk($entry, $rest, 0);
        };
    }

    /** Rows path used for row scope when an element lives inside a repeater. */
    private function rowsPathFor(array $owner): ?array
    {
        $groupUuid = match ($owner['type']) {
            'field' => $this->fields[$owner['uuid']]['group'] ?? null,
            'group' => $owner['uuid'],
            default => null,
        };
        $repeater = $this->repeaterOf($groupUuid);

        return $repeater === null ? null : [$this->groups[$repeater]['key']];
    }

    private function repeaterOf(?string $groupUuid): ?string
    {
        return $this->repeaterOfIn($groupUuid, $this->groups);
    }

    /** @param  array<string, array<string, mixed>>  $groups */
    private function repeaterOfIn(?string $groupUuid, array $groups): ?string
    {
        $seen = [];
        while ($groupUuid !== null && isset($groups[$groupUuid]) && ! isset($seen[$groupUuid])) {
            $seen[$groupUuid] = true;
            if ($groups[$groupUuid]['type'] === 'repeater') {
                return $groupUuid;
            }
            $groupUuid = $groups[$groupUuid]['parent'] ?? null;
        }

        return null;
    }

    private function dataAncestor(?string $groupUuid): ?string
    {
        $seen = [];
        while ($groupUuid !== null && isset($this->groups[$groupUuid]) && ! isset($seen[$groupUuid])) {
            $seen[$groupUuid] = true;
            if (in_array($this->groups[$groupUuid]['type'], FieldTypeRegistry::DATA_GROUPS, true)) {
                return $groupUuid;
            }
            $groupUuid = $this->groups[$groupUuid]['parent'];
        }

        return null;
    }

    /** @param  array<string, mixed>  $params */
    private function error(string $path, string $code, string $message, array $params = []): void
    {
        $this->errors[] = ['path' => $path, 'code' => $code, 'message' => $message, 'params' => $params];
    }

    /** @param  array<string, mixed>  $params */
    private function problem(string $path, string $code, string $message, array $params = []): void
    {
        $this->problems[] = ['path' => $path, 'code' => $code, 'message' => $message, 'params' => $params];
    }
}
