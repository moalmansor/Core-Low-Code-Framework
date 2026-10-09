<?php

declare(strict_types=1);

namespace App\Modules\Records\Runtime;

use App\Modules\Core\I18n\Translator;
use App\Modules\Forms\Definition\PublishedDefinitions;
use Illuminate\Support\Facades\DB;

/**
 * Resolves references between record uuids (API) and ids (columns), and the
 * display title of referenced records: the relation's display field for
 * forms and collections, the name for users, roles and departments.
 */
final class References
{
    public function __construct(private readonly PublishedDefinitions $definitions, private readonly Translator $translator) {}

    /**
     * @param  list<string>  $uuids
     * @return array<string, int> uuid => id (only existing, non-deleted rows)
     */
    public function ids(string $table, array $uuids): array
    {
        if ($uuids === []) {
            return [];
        }
        $q = DB::table($table)->whereIn('uuid', $uuids);
        if (in_array($table, ['users', 'departments'], true) || str_starts_with($table, 'f') || str_starts_with($table, 'c')) {
            $q->whereNull('deleted_at');
        }

        return $q->pluck('id', 'uuid')->mapWithKeys(static fn ($id, $uuid) => [strtolower((string) $uuid) => (int) $id])->all();
    }

    /**
     * @param  list<int>  $ids
     * @return array<int, string> id => uuid
     */
    public function uuids(string $table, array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        return DB::table($table)->whereIn('id', array_values(array_unique($ids)))->pluck('uuid', 'id')
            ->mapWithKeys(static fn ($uuid, $id) => [(int) $id => strtolower((string) $uuid)])->all();
    }

    /**
     * Display titles of referenced records.
     *
     * @param  list<string>  $uuids
     * @return array<string, string> uuid => title
     */
    public function titles(FormRuntime $rt, array $field, array $uuids): array
    {
        $table = $rt->targetTable($field);
        if ($table === null || $uuids === []) {
            return [];
        }
        $rows = DB::table($table)->whereIn('uuid', $uuids)->get();
        $out = [];
        $storage = $rt->type($field)?->storage;
        if ($storage === 'user') {
            foreach ($rows as $r) {
                $out[strtolower((string) $r->uuid)] = (string) $r->name;
            }

            return $out;
        }
        if ($storage === 'role' || $storage === 'department') {
            $names = $this->translator->many($storage, $rows->pluck('id')->map(fn ($i) => (int) $i)->all(), ['name']);
            foreach ($rows as $r) {
                $out[strtolower((string) $r->uuid)] = $names[(int) $r->id]['name'] ?? (string) ($r->code ?? $r->key ?? $r->uuid);
            }

            return $out;
        }
        $column = $this->displayColumn($rt, $field);
        foreach ($rows as $r) {
            $title = $column !== null ? ($r->{$column} ?? null) : null;
            $out[strtolower((string) $r->uuid)] = (string) ($title ?? $r->record_number ?? $r->uuid);
        }

        return $out;
    }

    /**
     * The title of a record of a form (its label field for collections, else
     * its first text field, else its record number or uuid).
     *
     * @param  array<string, mixed>  $definition  the form's published definition
     */
    public function recordTitle(array $definition, object $row): string
    {
        $column = $this->titleColumn($definition);
        $title = $column !== null ? ($row->{$column} ?? null) : null;

        return (string) ($title ?? $row->record_number ?? $row->uuid);
    }

    /** @param  array<string, mixed>  $definition */
    public function titleColumn(array $definition, ?string $display = null): ?string
    {
        $display ??= $definition['collection']['labelField'] ?? null;
        if ($display === null) {
            foreach ($definition['fields'] as $f) {
                if (in_array($f['type'], ['text', 'search', 'email', 'tel'], true)) {
                    $display = $f['uuid'];
                    break;
                }
            }
        }
        foreach ($definition['schema']['tables'][0]['columns'] ?? [] as $c) {
            if (($c['field'] ?? null) === $display && ! isset($c['part']) && ! ($c['encrypted'] ?? false)) {
                return $c['name'];
            }
        }

        return null;
    }

    /** Physical column of the relation's display field in the target table. */
    public function displayColumn(FormRuntime $rt, array $field): ?string
    {
        $relation = $rt->relationOf($field);
        if ($relation === null) {
            return null;
        }
        $target = $this->definitions->current($relation['target']);
        if ($target === null) {
            return null;
        }

        // Collections declare a label field; otherwise the first text field.
        return $this->titleColumn($target, $relation['display'] ?? null);
    }
}
