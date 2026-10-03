<?php

declare(strict_types=1);

namespace App\Modules\Records\Runtime;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Reads and writes the physical rows of one record: the main row, the rows
 * of each repeater's child table, and the pivot rows of many-to-many
 * references. Values are in API shape (ValueCodec); all writes happen inside
 * the caller's transaction.
 */
final class RecordStore
{
    public function __construct(private readonly ValueCodec $codec, private readonly References $refs) {}

    /**
     * @return array{id: int, uuid: string, row_version: int, values: array<string, mixed>, system: array<string, mixed>}|null
     */
    public function find(FormRuntime $rt, string $uuid, bool $withDeleted = false): ?array
    {
        $q = DB::table($rt->table)->where('uuid', $uuid);
        if (! $withDeleted) {
            $q->whereNull('deleted_at');
        }
        $row = $q->first();

        return $row === null ? null : $this->hydrate($rt, [(array) $row])[0];
    }

    /** @return array{id: int, uuid: string, row_version: int, values: array<string, mixed>, system: array<string, mixed>}|null */
    public function findById(FormRuntime $rt, int $id): ?array
    {
        $row = DB::table($rt->table)->where('id', $id)->first();

        return $row === null ? null : $this->hydrate($rt, [(array) $row])[0];
    }

    /**
     * Converts raw main rows (one query per reference table / child table / pivot for the whole page).
     *
     * @param  list<array<string, mixed>>  $rows
     * @return list<array{id: int, uuid: string, row_version: int, values: array<string, mixed>, system: array<string, mixed>}>
     */
    public function hydrate(FormRuntime $rt, array $rows, bool $withRows = true): array
    {
        if ($rows === []) {
            return [];
        }
        $ids = array_map(static fn ($r) => (int) $r['id'], $rows);
        $refMaps = $this->referenceMaps($rt, $rt->mainFields(), $rows);
        $pivots = $this->pivotValues($rt, $rt->mainFields(), $ids);
        $children = [];
        if ($withRows) {
            foreach ($rt->repeaters as $uuid => $rep) {
                $childRows = DB::table($rep['table'])->whereIn('parent_id', $ids)->orderBy('parent_id')->orderBy('sort_order')->orderBy('id')->get()->map(static fn ($r) => (array) $r)->all();
                $fields = $rt->rowFields($uuid);
                $childRefs = $this->referenceMaps($rt, $fields, $childRows);
                $childPivots = $this->pivotValues($rt, $fields, array_map(static fn ($r) => (int) $r['id'], $childRows));
                foreach ($childRows as $cr) {
                    $values = ['uuid' => strtolower((string) $cr['uuid'])];
                    foreach ($fields as $f) {
                        $values[$f['key']] = $rt->isMultiReference($f)
                            ? ($childPivots[$f['uuid']][(int) $cr['id']] ?? [])
                            : $this->codec->fromRow($rt, $f, $cr, $childRefs[$f['uuid']] ?? []);
                    }
                    $children[(int) $cr['parent_id']][$rep['group']['key']][] = $values;
                }
            }
        }
        $out = [];
        foreach ($rows as $r) {
            $values = [];
            foreach ($rt->mainFields() as $f) {
                if (! $rt->isStored($f)) {
                    continue;
                }
                $values[$f['key']] = $rt->isMultiReference($f)
                    ? ($pivots[$f['uuid']][(int) $r['id']] ?? [])
                    : $this->codec->fromRow($rt, $f, $r, $refMaps[$f['uuid']] ?? []);
            }
            foreach ($rt->repeaters as $rep) {
                $values[$rep['group']['key']] = $children[(int) $r['id']][$rep['group']['key']] ?? [];
            }
            $out[] = [
                'id' => (int) $r['id'],
                'uuid' => strtolower((string) $r['uuid']),
                'row_version' => (int) $r['row_version'],
                'values' => $values,
                'system' => [
                    'record_number' => $r['record_number'] ?? null,
                    'form_version_id' => (int) $r['form_version_id'],
                    'owner_user_id' => $r['owner_user_id'] ?? null,
                    'owner_department_id' => $r['owner_department_id'] ?? null,
                    'created_by' => $r['created_by'] ?? null,
                    'updated_by' => $r['updated_by'] ?? null,
                    'created_at' => isset($r['created_at']) ? Carbon::parse($r['created_at'], 'UTC')->toIso8601ZuluString() : null,
                    'updated_at' => isset($r['updated_at']) ? Carbon::parse($r['updated_at'], 'UTC')->toIso8601ZuluString() : null,
                    'deleted_at' => isset($r['deleted_at']) ? Carbon::parse($r['deleted_at'], 'UTC')->toIso8601ZuluString() : null,
                ],
            ];
        }

        return $out;
    }

    /**
     * Inserts the main row and its child and pivot rows.
     *
     * @param  array<string, mixed>  $values  API values by key (incl. repeater rows)
     * @param  array<string, mixed>  $system  system column values
     */
    public function insert(FormRuntime $rt, string $uuid, array $values, array $system): int
    {
        $now = Carbon::now('UTC')->format('Y-m-d H:i:s.u');
        $row = $system + ['uuid' => $uuid, 'row_version' => 1, 'created_at' => $now, 'updated_at' => $now, 'legal_hold' => 0];
        $row += $this->columns($rt, $rt->mainFields(), $values);
        $id = (int) DB::table($rt->table)->insertGetId($row);
        $this->writePivots($rt, $rt->mainFields(), $id, $values, $system['created_by'] ?? null);
        $this->writeChildren($rt, $id, $values, $system['organization_id'], $system['created_by'] ?? null);

        return $id;
    }

    /**
     * Optimistic update: only when the stored row_version equals `$expected`.
     *
     * @param  array<string, mixed>  $values  full API values (changed fields are written)
     * @param  list<string>  $changedKeys
     * @param  array<string, mixed>  $system
     * @return bool false when the row version no longer matches
     */
    public function update(FormRuntime $rt, int $id, int $expected, array $values, array $changedKeys, array $system, int $organizationId, ?int $userId): bool
    {
        $now = Carbon::now('UTC')->format('Y-m-d H:i:s.u');
        $fields = array_values(array_filter($rt->mainFields(), static fn ($f) => in_array($f['key'], $changedKeys, true)));
        $update = $system + ['row_version' => DB::raw('row_version + 1'), 'updated_at' => $now] + $this->columns($rt, $fields, $values);
        $affected = DB::table($rt->table)->where('id', $id)->where('row_version', $expected)->update($update);
        if ($affected === 0) {
            return false;
        }
        $this->writePivots($rt, $fields, $id, $values, $userId);
        $repeaterKeys = array_intersect(array_keys($rt->repeaterKeys), $changedKeys);
        if ($repeaterKeys !== []) {
            $this->writeChildren($rt, $id, array_intersect_key($values, array_flip($repeaterKeys)), $organizationId, $userId);
        }

        return true;
    }

    public function softDelete(FormRuntime $rt, int $id, int $expected, ?int $userId): bool
    {
        return DB::table($rt->table)->where('id', $id)->where('row_version', $expected)->whereNull('deleted_at')->update([
            'deleted_at' => Carbon::now('UTC')->format('Y-m-d H:i:s.u'), 'deleted_by' => $userId, 'row_version' => DB::raw('row_version + 1'),
        ]) === 1;
    }

    public function restore(FormRuntime $rt, int $id, ?int $userId): bool
    {
        return DB::table($rt->table)->where('id', $id)->whereNotNull('deleted_at')->update([
            'deleted_at' => null, 'deleted_by' => null, 'updated_by' => $userId, 'updated_at' => Carbon::now('UTC')->format('Y-m-d H:i:s.u'), 'row_version' => DB::raw('row_version + 1'),
        ]) === 1;
    }

    /**
     * @param  list<array<string, mixed>>  $fields
     * @param  array<string, mixed>  $values
     * @return array<string, mixed>
     */
    public function columns(FormRuntime $rt, array $fields, array $values): array
    {
        $out = [];
        foreach ($fields as $f) {
            if (! $rt->isStored($f) || $rt->isMultiReference($f) || ! array_key_exists($f['key'], $values)) {
                continue;
            }
            $value = $values[$f['key']];
            $refIds = [];
            $table = $rt->type($f)?->storage === 'file' ? 'files' : $rt->targetTable($f);
            if ($table !== null && is_string($value)) {
                $refIds = $this->refs->ids($table, [$value]);
            }
            $out += $this->codec->toColumns($rt, $f, $value, $refIds);
        }

        return $out;
    }

    /** @param  array<string, mixed>  $values */
    private function writeChildren(FormRuntime $rt, int $parentId, array $values, int $organizationId, ?int $userId): void
    {
        $now = Carbon::now('UTC')->format('Y-m-d H:i:s.u');
        foreach ($rt->repeaters as $repUuid => $rep) {
            $key = $rep['group']['key'];
            if (! array_key_exists($key, $values)) {
                continue;
            }
            $rows = is_array($values[$key]) ? array_values($values[$key]) : [];
            $fields = $rt->rowFields($repUuid);
            $existing = DB::table($rep['table'])->where('parent_id', $parentId)->pluck('id', 'uuid')
                ->mapWithKeys(static fn ($id, $u) => [strtolower((string) $u) => (int) $id])->all();
            $kept = [];
            foreach ($rows as $order => $row) {
                $rowUuid = isset($row['uuid']) && isset($existing[strtolower((string) $row['uuid'])]) ? strtolower((string) $row['uuid']) : null;
                $cols = $this->columns($rt, $fields, $row) + ['sort_order' => $order, 'updated_at' => $now, 'updated_by' => $userId];
                if ($rowUuid !== null) {
                    DB::table($rep['table'])->where('id', $existing[$rowUuid])->update($cols);
                    $childId = $existing[$rowUuid];
                } else {
                    $childId = (int) DB::table($rep['table'])->insertGetId($cols + [
                        'uuid' => (string) Str::uuid7(), 'organization_id' => $organizationId, 'parent_id' => $parentId,
                        'created_at' => $now, 'created_by' => $userId,
                    ]);
                }
                $kept[] = $childId;
                $this->writePivots($rt, $fields, $childId, $row, $userId);
            }
            $stale = array_values(array_diff(array_values($existing), $kept));
            if ($stale !== []) {
                foreach ($fields as $f) {
                    if ($rt->isMultiReference($f) && isset($rt->pivots[$f['relation']])) {
                        DB::table($rt->pivots[$f['relation']])->whereIn('source_id', $stale)->delete();
                    }
                }
                DB::table($rep['table'])->whereIn('id', $stale)->delete();
            }
        }
    }

    /**
     * @param  list<array<string, mixed>>  $fields
     * @param  array<string, mixed>  $values
     */
    private function writePivots(FormRuntime $rt, array $fields, int $sourceId, array $values, ?int $userId): void
    {
        foreach ($fields as $f) {
            if (! $rt->isMultiReference($f) || ! array_key_exists($f['key'], $values) || ! isset($rt->pivots[$f['relation']])) {
                continue;
            }
            $pivot = $rt->pivots[$f['relation']];
            $uuids = is_array($values[$f['key']]) ? $values[$f['key']] : [];
            $ids = $this->refs->ids((string) $rt->targetTable($f), $uuids);
            DB::table($pivot)->where('source_id', $sourceId)->delete();
            $now = Carbon::now('UTC')->format('Y-m-d H:i:s.u');
            $order = 0;
            foreach ($uuids as $u) {
                if (isset($ids[$u])) {
                    DB::table($pivot)->insert(['source_id' => $sourceId, 'target_id' => $ids[$u], 'sort_order' => $order++, 'created_at' => $now, 'created_by' => $userId]);
                }
            }
        }
    }

    /**
     * id => uuid maps of single-reference columns, per field.
     *
     * @param  list<array<string, mixed>>  $fields
     * @param  list<array<string, mixed>>  $rows
     * @return array<string, array<int, string>>
     */
    private function referenceMaps(FormRuntime $rt, array $fields, array $rows): array
    {
        $out = [];
        foreach ($fields as $f) {
            $col = $rt->column($f['uuid']);
            $table = $rt->targetTable($f);
            $storage = $rt->type($f)?->storage;
            if ($storage === 'file') {
                $table = 'files';
            }
            if ($col === null || $col['type'] !== 'bigint' || $table === null || ($col['encrypted'] ?? false)) {
                continue;
            }
            $ids = array_values(array_filter(array_map(static fn ($r) => $r[$col['name']] ?? null, $rows), static fn ($v) => $v !== null));
            $out[$f['uuid']] = $this->refs->uuids($table, array_map('intval', $ids));
        }

        return $out;
    }

    /**
     * Many-to-many values from pivot tables: field uuid => source id => list of target uuids.
     *
     * @param  list<array<string, mixed>>  $fields
     * @param  list<int>  $sourceIds
     * @return array<string, array<int, list<string>>>
     */
    private function pivotValues(FormRuntime $rt, array $fields, array $sourceIds): array
    {
        $out = [];
        if ($sourceIds === []) {
            return $out;
        }
        foreach ($fields as $f) {
            if (! $rt->isMultiReference($f) || ! isset($rt->pivots[$f['relation']])) {
                continue;
            }
            $table = (string) $rt->targetTable($f);
            $rows = DB::table($rt->pivots[$f['relation']])->whereIn('source_id', $sourceIds)->orderBy('sort_order')->get(['source_id', 'target_id']);
            $uuids = $this->refs->uuids($table, $rows->pluck('target_id')->map(fn ($i) => (int) $i)->all());
            foreach ($rows as $r) {
                if (isset($uuids[(int) $r->target_id])) {
                    $out[$f['uuid']][(int) $r->source_id][] = $uuids[(int) $r->target_id];
                }
            }
        }

        return $out;
    }
}
