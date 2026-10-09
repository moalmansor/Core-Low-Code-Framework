<?php

declare(strict_types=1);

namespace App\Modules\Records\Runtime;

use App\Modules\Forms\FieldTypes\FieldType;
use App\Modules\Forms\FieldTypes\FieldTypeRegistry;
use App\Modules\Forms\Models\Form;

/**
 * A published definition indexed for the record runtime: fields by key and
 * uuid, the physical columns of each field, repeaters with their child
 * tables, relations, and the reference targets.
 */
final class FormRuntime
{
    /** @var array<string, array<string, mixed>> by uuid */
    public array $fields = [];

    /** @var array<string, string> key => uuid (top-level fields and repeater keys) */
    public array $keys = [];

    /** @var array<string, array<string, mixed>> by uuid */
    public array $groups = [];

    /** @var array<string, array{group: array<string, mixed>, table: string, fields: array<string, string>}> repeater uuid => child table and its field keys */
    public array $repeaters = [];

    /** @var array<string, string> repeater key => uuid */
    public array $repeaterKeys = [];

    /** @var array<string, array<string, mixed>> */
    public array $relations = [];

    /** @var array<string, list<array<string, mixed>>> field uuid => columns (main or child table) */
    public array $columns = [];

    /** @var array<string, string> field uuid => repeater uuid for fields inside repeaters */
    public array $fieldRepeater = [];

    /** @var array<string, string> pivot table by relation uuid */
    public array $pivots = [];

    public string $table;

    /** @param  array<string, mixed>  $definition */
    public function __construct(public readonly Form $form, public readonly array $definition, public readonly int $versionId)
    {
        $this->table = $definition['schema']['tables'][0]['name'];
        foreach ($definition['groups'] as $g) {
            $this->groups[$g['uuid']] = $g;
        }
        foreach ($definition['relations'] as $r) {
            $this->relations[$r['uuid']] = $r;
        }
        foreach ($definition['schema']['tables'] as $t) {
            if ($t['role'] === 'child') {
                $this->repeaters[$t['group']] = ['group' => $this->groups[$t['group']], 'table' => $t['name'], 'fields' => []];
                $this->repeaterKeys[$this->groups[$t['group']]['key']] = $t['group'];
            }
            if ($t['role'] === 'pivot') {
                $this->pivots[$t['relation']] = $t['name'];
            }
            foreach ($t['columns'] as $c) {
                if (isset($c['field'])) {
                    $this->columns[$c['field']][] = $c;
                }
            }
        }
        foreach ($definition['fields'] as $f) {
            $this->fields[$f['uuid']] = $f;
            $repeater = $this->repeaterOf($f['group']);
            if ($repeater !== null && isset($this->repeaters[$repeater])) {
                $this->fieldRepeater[$f['uuid']] = $repeater;
                $this->repeaters[$repeater]['fields'][$f['key']] = $f['uuid'];
            } else {
                $this->keys[$f['key']] = $f['uuid'];
            }
        }
    }

    public function type(array $field): ?FieldType
    {
        return FieldTypeRegistry::has($field['type']) ? FieldTypeRegistry::get($field['type']) : null;
    }

    public function isStored(array $field): bool
    {
        return $this->type($field)?->isStored() ?? false;
    }

    /** @return array<string, mixed>|null */
    public function relationOf(array $field): ?array
    {
        return isset($field['relation']) ? ($this->relations[$field['relation']] ?? null) : null;
    }

    /** Physical table holding a reference field's target records. */
    public function targetTable(array $field): ?string
    {
        $storage = $this->type($field)?->storage;

        return match ($storage) {
            'user' => 'users',
            'role' => 'roles',
            'department' => 'departments',
            default => ($r = $this->relationOf($field)) !== null ? ($this->definition['targets'][$r['target']]['table'] ?? null) : null,
        };
    }

    public function targetFormUuid(array $field): ?string
    {
        return $this->relationOf($field)['target'] ?? null;
    }

    public function isMultiReference(array $field): bool
    {
        return ($this->relationOf($field)['type'] ?? null) === 'many_to_many';
    }

    /** @return array<string, mixed>|null */
    public function column(string $fieldUuid, ?string $part = null): ?array
    {
        foreach ($this->columns[$fieldUuid] ?? [] as $c) {
            if (($c['part'] ?? null) === $part) {
                return $c;
            }
        }

        return null;
    }

    /** Fields of the main table (not inside repeaters), in definition order. @return list<array<string, mixed>> */
    public function mainFields(): array
    {
        return array_values(array_filter($this->fields, fn (array $f) => ! isset($this->fieldRepeater[$f['uuid']])));
    }

    /** @return list<array<string, mixed>> */
    public function rowFields(string $repeaterUuid): array
    {
        return array_values(array_map(fn (string $u) => $this->fields[$u], $this->repeaters[$repeaterUuid]['fields'] ?? []));
    }

    private function repeaterOf(?string $groupUuid): ?string
    {
        $seen = [];
        while ($groupUuid !== null && isset($this->groups[$groupUuid]) && ! isset($seen[$groupUuid])) {
            $seen[$groupUuid] = true;
            if ($this->groups[$groupUuid]['type'] === 'repeater') {
                return $groupUuid;
            }
            $groupUuid = $this->groups[$groupUuid]['parent'];
        }

        return null;
    }
}
