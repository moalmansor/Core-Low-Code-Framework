<?php

declare(strict_types=1);

namespace App\Modules\Forms\Definition;

use App\Infrastructure\Database\Contracts\DatabaseDriver;
use App\Modules\Forms\Draft\DraftRepository;
use App\Modules\Forms\Models\Form;
use App\Modules\Workflow\WorkflowDocument;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Compiles a form's draft into the definition document of architecture §14.1
 * (architecture §3.1): the draft plus the resolved targets of its relations,
 * the access rules in force, and the target physical schema. A draft with
 * problems does not compile.
 */
final class DefinitionCompiler
{
    public function __construct(
        private readonly DraftRepository $drafts,
        private readonly TargetSchemaBuilder $schemaBuilder,
        private readonly PublishedDefinitions $definitions,
        private readonly DatabaseDriver $driver,
        private readonly WorkflowDocument $workflow,
    ) {}

    /**
     * @return array{definition: array<string, mixed>, problems: list<array<string, mixed>>}
     */
    public function compile(Form $form, int $versionNumber): array
    {
        $published = $form->current_version_id === null ? null : $this->definitions->version($form->id, (int) $form->current_version_id);
        $doc = $this->drafts->normalize($this->drafts->load($form));
        $problems = $this->drafts->problems($form, $published);
        $targets = $this->targets($doc, $form);
        $doc['form']['version'] = $versionNumber;
        $doc['form']['table'] = $form->table_name;
        unset($doc['form']['state']);
        $doc['targets'] = $targets;
        $doc['access'] = $this->accessRules($form);
        $doc['workflow'] = $this->workflow->compile($form);
        foreach ($this->workflow->problems($doc['workflow'])['problems'] as $p) {
            $problems[] = ['path' => 'workflow.'.$p['path'], 'code' => $p['code'], 'message' => $p['message']];
        }
        $doc['schema'] = $this->schemaBuilder->build($doc, $form->table_name, $targets, $this->boundColumns($form));

        return ['definition' => ['$schema' => 'https://schemas.core-lcf/form-definition/v1'] + $doc, 'problems' => $problems];
    }

    /** SHA-256 of the canonical JSON of a document. */
    public static function hash(mixed $document): string
    {
        return hash('sha256', (string) json_encode(self::canonical($document), JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION));
    }

    public static function canonical(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }
        if (array_is_list($value)) {
            return array_map(self::canonical(...), $value);
        }
        ksort($value, SORT_STRING);

        return array_map(self::canonical(...), $value);
    }

    /**
     * Physical tables and keys of every form this draft references.
     *
     * @param  array<string, mixed>  $doc
     * @return array<string, array{table: string, kind: string, key: string}>
     */
    private function targets(array $doc, Form $form): array
    {
        $uuids = array_values(array_unique(array_filter([
            ...array_column($doc['relations'], 'target'),
            ...array_map(static fn (array $g) => $g['subform']['form'] ?? null, $doc['groups']),
            ...array_map(static fn (array $f) => $f['options']['collection'] ?? null, $doc['fields']),
            ...array_map(static fn (array $f) => $f['options']['query']['from'] ?? null, $doc['fields']),
            ...array_map(static fn (array $f) => $f['validation']['async']['collection'] ?? null, $doc['fields']),
        ])));
        $out = [];
        foreach (Form::query()->whereIn('uuid', $uuids)->get(['uuid', 'table_name', 'kind', 'key']) as $t) {
            $out[$t->uuid] = ['table' => $t->table_name, 'kind' => $t->kind, 'key' => $t->key];
        }
        $out[$form->uuid] = ['table' => $form->table_name, 'kind' => $form->kind, 'key' => $form->key];

        return $out;
    }

    /**
     * Access rules in force, frozen into the version for history and diff
     * (resolution reads the live rules, architecture §16.4).
     *
     * @return list<array<string, mixed>>
     */
    private function accessRules(Form $form): array
    {
        $groups = DB::table('field_groups')->where('form_id', $form->id)->pluck('uuid', 'id')->all();
        $fields = DB::table('fields')->where('form_id', $form->id)->pluck('uuid', 'id')->all();
        $rows = DB::table('field_access_rules')->where('form_id', $form->id)->orderBy('id')->get();
        $statuses = DB::table('statuses')->where('form_id', $form->id)->pluck('uuid', 'id')->all();
        $subjects = [
            'role' => DB::table('roles')->whereIn('id', $rows->where('subject_type', 'role')->pluck('subject_id'))->pluck('uuid', 'id')->all(),
            'user' => DB::table('users')->whereIn('id', $rows->where('subject_type', 'user')->pluck('subject_id'))->pluck('uuid', 'id')->all(),
            'department' => DB::table('departments')->whereIn('id', $rows->where('subject_type', 'department')->pluck('subject_id'))->pluck('uuid', 'id')->all(),
        ];

        return $rows->map(static fn ($r): array => [
            'uuid' => strtolower((string) $r->uuid),
            'target' => ['type' => $r->target_type, 'uuid' => match ($r->target_type) {
                'form' => $form->uuid,
                'group' => $groups[$r->group_id] ?? null,
                default => $fields[$r->field_id] ?? null,
            }],
            'subject' => ['type' => $r->subject_type, 'uuid' => $r->subject_type === 'everyone' ? null : strtolower((string) ($subjects[$r->subject_type][$r->subject_id] ?? ''))],
            'status' => $r->status_id === null ? null : strtolower((string) ($statuses[$r->status_id] ?? '')),
            'mode' => $r->mode,
            'access' => $r->access,
            'effect' => $r->effect,
        ])->values()->all();
    }

    /** @return array<string, array<string, mixed>> */
    private function boundColumns(Form $form): array
    {
        if ($form->binding_mode !== 'bound') {
            return [];
        }
        if (! in_array($form->table_name, $this->driver->tables(), true)) {
            throw new RuntimeException("The bound table {$form->table_name} does not exist.");
        }
        $out = [];
        foreach ($this->driver->columns($form->table_name) as $c) {
            $out[$c['name']] = ['nullable' => $c['nullable'], 'native' => $c['type'], 'logical' => Introspection::logicalType($c['type'])];
        }

        return $out;
    }
}
