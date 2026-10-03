<?php

declare(strict_types=1);

namespace App\Modules\Records\Runtime;

use App\Modules\Audit\AuditWriter;
use App\Modules\Core\Outbox\OutboxWriter;
use App\Modules\Forms\Models\Form;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * On-delete rules of relations (specification §4.9 "no orphaned records,
 * configurable on-delete rules"). Records are soft-deleted, so database
 * foreign keys never fire; the pipeline applies each relation's rule to the
 * records that reference the one being deleted:
 *
 * - restrict: the delete is refused while live records reference it;
 * - cascade: referencing records are deleted too (recursively, with the same
 *   rules), referencing repeater rows are removed from their records, and
 *   many-to-many links are removed;
 * - set_null: the reference is cleared (many-to-many links are removed).
 *
 * The whole cascade is planned before anything changes, so a restrict found
 * anywhere in it refuses the delete as a unit. Every change is audited.
 */
final class ReferentialIntegrity
{
    private const MAX_DEPTH = 8;

    /** @var array<string, list<array<string, mixed>>> target form uuid => referencing relations */
    private array $memo = [];

    public function __construct(private readonly FormRuntimes $runtimes, private readonly AuditWriter $audit, private readonly OutboxWriter $outbox) {}

    /**
     * Applies the rules for deleting record `$id` of `$rt`. Call inside the
     * delete transaction, before the record itself is deleted.
     *
     * @throws RecordException 409 `referenced` when a restrict rule blocks the delete
     */
    public function beforeDelete(FormRuntime $rt, int $id, int $userId): void
    {
        $visited = [$rt->table.':'.$id => true];
        $actions = [];
        $this->plan($rt, $id, 0, $visited, $actions);
        $now = Carbon::now('UTC')->format('Y-m-d H:i:s.u');
        foreach ($actions as $a) {
            match ($a['kind']) {
                'delete_record' => $this->deleteRecord($a['rt'], $a['id'], $userId, $now, $rt),
                'null_column' => $this->nullColumn($a['rt'], $a['table'], $a['column'], $a['ids'], $a['parents'], $userId, $now, $a['field']),
                'delete_rows' => $this->deleteRows($a['rt'], $a['table'], $a['ids'], $a['parents'], $userId, $now),
                'unlink' => DB::table($a['table'])->where('target_id', $a['target'])->delete(),
                default => null,
            };
        }
    }

    /**
     * @param  array<string, true>  $visited
     * @param  list<array<string, mixed>>  $actions
     */
    private function plan(FormRuntime $rt, int $id, int $depth, array &$visited, array &$actions): void
    {
        if ($depth > self::MAX_DEPTH) {
            throw new RecordException(409, 'referenced', __('records.cascade_too_deep'));
        }
        foreach ($this->referencesTo($rt->form->uuid) as $ref) {
            /** @var FormRuntime $src */
            $src = $ref['rt'];
            $rule = $ref['rule'];
            if ($ref['kind'] === 'pivot') {
                $sources = DB::table($ref['pivot'])->where('target_id', $id)->pluck('source_id')->map(static fn ($v) => (int) $v)->all();
                $live = $sources === [] ? 0 : $this->liveCount($src, $ref, $sources);
                if ($live === 0) {
                    continue;
                }
                if ($rule === 'restrict') {
                    throw $this->blocked($src, $live);
                }
                $actions[] = ['kind' => 'unlink', 'table' => $ref['pivot'], 'target' => $id];

                continue;
            }
            if ($ref['kind'] === 'row') {
                $rows = DB::table($ref['table'])->join($src->table, $src->table.'.id', '=', $ref['table'].'.parent_id')
                    ->whereNull($src->table.'.deleted_at')->where($ref['table'].'.'.$ref['column'], $id)
                    ->get([$ref['table'].'.id as row_id', $ref['table'].'.parent_id']);
                if ($rows->isEmpty()) {
                    continue;
                }
                if ($rule === 'restrict') {
                    throw $this->blocked($src, $rows->pluck('parent_id')->unique()->count());
                }
                $ids = $rows->pluck('row_id')->map(static fn ($v) => (int) $v)->all();
                $parents = $rows->pluck('parent_id')->map(static fn ($v) => (int) $v)->unique()->values()->all();
                $actions[] = $rule === 'cascade'
                    ? ['kind' => 'delete_rows', 'rt' => $src, 'table' => $ref['table'], 'ids' => $ids, 'parents' => $parents]
                    : ['kind' => 'null_column', 'rt' => $src, 'table' => $ref['table'], 'column' => $ref['column'], 'ids' => $ids, 'parents' => $parents, 'field' => $ref['field']];

                continue;
            }
            $ids = DB::table($src->table)->whereNull('deleted_at')->where($ref['column'], $id)->pluck('id')->map(static fn ($v) => (int) $v)->all();
            if ($ids === []) {
                continue;
            }
            if ($rule === 'restrict') {
                throw $this->blocked($src, count($ids));
            }
            if ($rule === 'set_null') {
                $actions[] = ['kind' => 'null_column', 'rt' => $src, 'table' => $src->table, 'column' => $ref['column'], 'ids' => $ids, 'parents' => $ids, 'field' => $ref['field']];

                continue;
            }
            foreach ($ids as $rid) {
                if (isset($visited[$src->table.':'.$rid])) {
                    continue;
                }
                $visited[$src->table.':'.$rid] = true;
                $this->plan($src, $rid, $depth + 1, $visited, $actions);
                $actions[] = ['kind' => 'delete_record', 'rt' => $src, 'id' => $rid];
            }
        }
    }

    /**
     * Every reference to records of the given form, from the published
     * definitions of all forms.
     *
     * @return list<array<string, mixed>>
     */
    private function referencesTo(string $formUuid): array
    {
        if (isset($this->memo[$formUuid])) {
            return $this->memo[$formUuid];
        }
        $out = [];
        foreach (Form::query()->whereNotNull('current_version_id')->orderBy('id')->get() as $form) {
            $src = $this->runtimes->forForm($form);
            if ($src === null) {
                continue;
            }
            foreach ($src->fields as $f) {
                $relation = $src->relationOf($f);
                if ($relation === null || $relation['target'] !== $formUuid || ($relation['kind'] ?? 'reference') !== 'reference') {
                    continue;
                }
                $base = ['rt' => $src, 'rule' => $relation['onDelete'] ?? 'restrict', 'field' => $f];
                $repeater = $src->fieldRepeater[$f['uuid']] ?? null;
                if ($src->isMultiReference($f)) {
                    if (isset($src->pivots[$relation['uuid']])) {
                        $out[] = $base + ['kind' => 'pivot', 'pivot' => $src->pivots[$relation['uuid']], 'row_table' => $repeater === null ? null : $src->repeaters[$repeater]['table']];
                    }
                } elseif (($column = $src->column($f['uuid'])) !== null) {
                    $out[] = $repeater === null
                        ? $base + ['kind' => 'column', 'column' => $column['name']]
                        : $base + ['kind' => 'row', 'table' => $src->repeaters[$repeater]['table'], 'column' => $column['name']];
                }
            }
        }

        return $this->memo[$formUuid] = $out;
    }

    /**
     * Live records among pivot sources (sources are rows of a child table when
     * the reference lives in a repeater).
     *
     * @param  array<string, mixed>  $ref
     * @param  list<int>  $sources
     */
    private function liveCount(FormRuntime $src, array $ref, array $sources): int
    {
        if ($ref['row_table'] !== null) {
            return DB::table($ref['row_table'])->join($src->table, $src->table.'.id', '=', $ref['row_table'].'.parent_id')
                ->whereIn($ref['row_table'].'.id', $sources)->whereNull($src->table.'.deleted_at')->distinct()->count($src->table.'.id');
        }

        return DB::table($src->table)->whereIn('id', $sources)->whereNull('deleted_at')->count();
    }

    private function blocked(FormRuntime $src, int $count): RecordException
    {
        return new RecordException(409, 'referenced', __('records.referenced', [
            'count' => $count, 'form' => $src->form->translate('name') ?? $src->form->key,
        ]), ['referenced_by' => ['form' => $src->form->uuid, 'count' => $count]]);
    }

    private function deleteRecord(FormRuntime $src, int $id, int $userId, string $now, FormRuntime $origin): void
    {
        $updated = DB::table($src->table)->where('id', $id)->whereNull('deleted_at')
            ->update(['deleted_at' => $now, 'deleted_by' => $userId, 'row_version' => DB::raw('row_version + 1')]);
        if ($updated === 1) {
            $this->audit->record('record.deleted', 'data', null, 'record', $id, ['form' => $src->form->key, 'cascade_from' => $origin->form->key], $userId, null, $src->form->id, $id);
            $this->outbox->publish('record.deleted', ['form' => $src->form->uuid, 'record' => strtolower((string) DB::table($src->table)->where('id', $id)->value('uuid'))]);
        }
    }

    /**
     * @param  list<int>  $ids  rows whose column is cleared
     * @param  list<int>  $parents  records that change (the rows themselves, or their parents)
     * @param  array<string, mixed>  $field
     */
    private function nullColumn(FormRuntime $src, string $table, string $column, array $ids, array $parents, int $userId, string $now, array $field): void
    {
        DB::table($table)->whereIn('id', $ids)->update([$column => null]);
        $this->touch($src, $parents, $userId, $now, ['field_key' => $field['key'], 'old' => 'reference', 'new' => null]);
    }

    /**
     * @param  list<int>  $ids
     * @param  list<int>  $parents
     */
    private function deleteRows(FormRuntime $src, string $table, array $ids, array $parents, int $userId, string $now): void
    {
        foreach ($src->pivots as $pivot) {
            DB::table($pivot)->whereIn('source_id', $ids)->delete();
        }
        DB::table($table)->whereIn('id', $ids)->delete();
        $this->touch($src, $parents, $userId, $now, ['field_key' => $table, 'old' => count($ids).' row(s)', 'new' => null]);
    }

    /**
     * New version of changed records, so editors holding the old version get a conflict.
     *
     * @param  list<int>  $ids
     * @param  array<string, mixed>  $change
     */
    private function touch(FormRuntime $src, array $ids, int $userId, string $now, array $change): void
    {
        DB::table($src->table)->whereIn('id', $ids)->update(['updated_at' => $now, 'updated_by' => $userId, 'row_version' => DB::raw('row_version + 1')]);
        foreach ($ids as $id) {
            $this->audit->record('record.updated', 'data', [$change], 'record', $id, ['form' => $src->form->key, 'reason' => 'on_delete_rule'], $userId, null, $src->form->id, $id);
        }
    }
}
