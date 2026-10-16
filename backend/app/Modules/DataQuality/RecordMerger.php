<?php

declare(strict_types=1);

namespace App\Modules\DataQuality;

use App\Modules\Access\AccessResolver;
use App\Modules\Access\RecordScope;
use App\Modules\Audit\AuditWriter;
use App\Modules\Identity\Models\User;
use App\Modules\Justification\JustificationGate;
use App\Modules\Records\Runtime\FormRuntime;
use App\Modules\Records\Runtime\RecordException;
use App\Modules\Records\Runtime\RecordPipeline;
use App\Modules\Records\Runtime\RecordStore;
use App\Modules\Records\Runtime\RecycleBin;
use App\Modules\Records\Runtime\ReferentialIntegrity;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Merging duplicate records (specification §4.35, ADR-0044). The person
 * merging picks the surviving record and, field by field, which record's
 * value it keeps. Everything that pointed at the other records — references,
 * many-to-many links, repeating-section references, inline sub-forms — is
 * pointed at the survivor, and the others go to the recycle bin. One
 * justification covers the merge (always required), and an immutable
 * history keeps the full values of every merged record, with sensitive ones
 * encrypted. Needs "Merge records", editing the survivor and deleting the
 * others, under the user's record scope and field access.
 */
final class RecordMerger
{
    public const MAX_MERGED = 10;

    /** The baseline requirement: a merge always asks why. */
    private const REQUIRED = ['level' => 'mandatory', 'min' => null, 'max' => null, 'codes' => 'none', 'sets' => [], 'collections' => [], 'attachments' => 'none',
        'maxAttachments' => null, 'attachmentRules' => [], 'summary' => false, 'rules' => [], 'title' => null, 'help' => null];

    public function __construct(
        private readonly RecordStore $store,
        private readonly RecordPipeline $pipeline,
        private readonly ReferentialIntegrity $integrity,
        private readonly RecordScope $scope,
        private readonly AccessResolver $access,
        private readonly JustificationGate $gate,
        private readonly AuditWriter $audit,
        private readonly DuplicateGuard $guard,
        private readonly RecycleBin $bin,
    ) {}

    /**
     * What merging these records would do: their values side by side, the
     * fields that differ and the links that would move.
     *
     * @param  list<string>  $uuids
     * @return array<string, mixed>
     */
    public function preview(FormRuntime $rt, User $user, array $uuids): array
    {
        $records = $this->records($rt, $user, $uuids, null);
        $differ = [];
        foreach ($rt->mainFields() as $f) {
            if (! $rt->isStored($f) || $f['type'] === 'formula' || $f['type'] === 'autonumber' || in_array($rt->type($f)?->storage, ['file', 'files'], true)) {
                continue;
            }
            $vals = array_map(static fn ($r) => json_encode($r['values'][$f['key']] ?? null), $records);
            if (count(array_unique($vals)) > 1) {
                $differ[] = $f['key'];
            }
        }
        $links = [];
        foreach ($records as $r) {
            $links[$r['uuid']] = $this->linkCount($rt, $r['id']);
        }

        return ['records' => array_map(fn ($r) => ['uuid' => $r['uuid'], 'row_version' => $r['row_version'], 'number' => $r['system']['record_number'] ?? null,
            'title' => $this->bin->title($rt, DB::table($rt->table)->where('id', $r['id'])->first())], $records),
            'differ' => $differ, 'links' => $links,
            'justification' => $this->gate->prompt($rt, $this->requirement($rt, $user, $records[0]), ['fields' => $differ, 'values' => $records[0]['values'], 'old' => $records[0]['values']], $user)];
    }

    /**
     * @param  list<string>  $merged  records merged into the survivor
     * @param  array<string, string>  $choices  field key => uuid of the record whose value the survivor keeps
     * @param  array<string, int>  $versions  uuid => the row version the person saw
     * @param  array<string, mixed>|null  $justification
     * @return array{uuid: string, merged: int, repointed: array<string, int>}
     */
    public function merge(FormRuntime $rt, User $user, string $survivor, array $merged, array $choices, array $versions, ?array $justification): array
    {
        $uuids = array_values(array_unique(array_map('strtolower', [$survivor, ...$merged])));
        if (count($uuids) < 2 || count($uuids) > self::MAX_MERGED + 1) {
            throw new RecordException(422, 'invalid', __('data_quality.merge_count', ['max' => self::MAX_MERGED + 1]));
        }
        $records = $this->records($rt, $user, $uuids, $versions);
        $byUuid = [];
        foreach ($records as $r) {
            $byUuid[$r['uuid']] = $r;
        }
        $keep = $byUuid[strtolower($survivor)];
        $values = [];
        foreach ($choices as $key => $from) {
            $field = isset($rt->keys[$key]) ? ($rt->fields[$rt->keys[$key]] ?? null) : null;
            $source = $byUuid[strtolower((string) $from)] ?? null;
            // Files belong to the record they were uploaded to: the survivor keeps its own.
            if ($field === null || $source === null || isset($rt->fieldRepeater[$field['uuid']]) || in_array($rt->type($field)?->storage, ['file', 'files'], true)) {
                throw new RecordException(422, 'invalid', __('data_quality.bad_choice'));
            }
            if ($source['uuid'] !== $keep['uuid'] && json_encode($source['values'][$key] ?? null) !== json_encode($keep['values'][$key] ?? null)) {
                $values[$key] = $source['values'][$key] ?? null;
            }
        }
        $validated = $this->gate->check($rt, $user, $this->requirement($rt, $user, $keep), ['fields' => array_keys($values), 'values' => $keep['values'], 'old' => $keep['values']], $justification)
            ?? throw new RecordException(422, 'justification_required', __('data_quality.merge_needs_reason'));
        $others = array_values(array_filter($records, static fn ($r) => $r['uuid'] !== $keep['uuid']));

        return DB::transaction(function () use ($rt, $user, $keep, $others, $values, $validated, $choices): array {
            $justificationId = $this->gate->record($rt, $keep['id'], $user, 'merge', $validated, [], count($others) + 1);
            $repointed = [];
            $now = Carbon::now('UTC')->format('Y-m-d H:i:s.u');
            foreach ($others as $o) {
                foreach ($this->repoint($rt, $o['id'], $keep['id'], $user->id, $now) as $label => $n) {
                    $repointed[$label] = ($repointed[$label] ?? 0) + $n;
                }
            }
            foreach ($others as $o) {
                $fresh = $this->store->findById($rt, $o['id']) ?? $o;
                $this->pipeline->delete($rt, $user, $o['uuid'], $fresh['row_version'], 'merge-'.Str::uuid7(), null, 'merge', $justificationId);
            }
            // The survivor takes the chosen values once the others are gone, so they do not count as its duplicates;
            // a "block" rule still stops a merge that would duplicate a third record.
            if ($values !== []) {
                $this->guard->acknowledge(true);
                $current = $this->store->findById($rt, $keep['id']) ?? $keep;
                $this->pipeline->update($rt, $user, $keep['uuid'], $current['row_version'], $values, 'merge-'.Str::uuid7(), 'ui', null, $justificationId);
            }
            $snapshots = array_map(static fn ($o) => ['uuid' => $o['uuid'], 'values' => $o['values']], $others);
            $sensitive = array_filter($rt->mainFields(), static fn ($f) => (bool) (($f['flags']['sensitive'] ?? false) || ($f['flags']['encrypted'] ?? false)));
            DB::table('merge_history')->insert([
                'uuid' => (string) Str::uuid7(), 'organization_id' => $rt->form->organization_id, 'form_id' => $rt->form->id, 'survivor_record_id' => $keep['id'],
                'merged_record_ids' => json_encode(array_map(static fn ($o) => $o['id'], $others)),
                'field_choices' => json_encode((object) array_map('strtolower', $choices)),
                // Sensitive values are kept, but encrypted, like the record itself keeps them away from casual eyes.
                'merged_snapshots' => $sensitive === [] ? json_encode($snapshots, JSON_UNESCAPED_UNICODE) : json_encode(['encrypted' => Crypt::encryptString((string) json_encode($snapshots, JSON_UNESCAPED_UNICODE))]),
                'relations_repointed' => json_encode((object) $repointed), 'justification_id' => $justificationId, 'merged_by' => $user->id, 'merged_at' => $now,
            ]);
            $this->audit->record('record.merged', 'data', null, 'record', $keep['id'], [
                'form' => $rt->form->key, 'merged' => array_map(static fn ($o) => $o['uuid'], $others), 'fields' => array_keys($values), 'repointed' => $repointed,
            ], $user->id, null, $rt->form->id, $keep['id'], null, $justificationId);

            return ['uuid' => $keep['uuid'], 'merged' => count($others), 'repointed' => $repointed];
        });
    }

    /**
     * The records to merge, checked: live, visible, editable (survivor) and
     * deletable (the others) by the user, at the versions they saw.
     *
     * @param  list<string>  $uuids
     * @param  array<string, int>|null  $versions
     * @return list<array<string, mixed>>
     */
    private function records(FormRuntime $rt, User $user, array $uuids, ?array $versions): array
    {
        if (! $this->access->allows($user, 'system.merge_records') || ! $this->access->allows($user, "form.{$rt->form->uuid}.view")) {
            throw new RecordException(403, 'forbidden', __('data_quality.merge_forbidden'));
        }
        $out = [];
        foreach ($uuids as $i => $uuid) {
            $found = Str::isUuid($uuid) ? $this->store->find($rt, strtolower($uuid)) : null;
            if ($found === null || ! $this->scope->allows($rt, $user, 'view', $found['id'])) {
                throw new RecordException(404, 'not_found', __('records.not_found'));
            }
            if (! $this->scope->allows($rt, $user, $i === 0 ? 'edit' : 'delete', $found['id'])) {
                throw new RecordException(403, 'forbidden', __('data_quality.merge_forbidden'));
            }
            if ($versions !== null && (int) ($versions[$found['uuid']] ?? 0) !== $found['row_version']) {
                throw new RecordException(409, 'conflict', __('data_quality.changed_since'));
            }
            $out[] = $found;
        }

        return $out;
    }

    /** @param  array<string, mixed>  $record */
    private function requirement(FormRuntime $rt, User $user, array $record): array
    {
        $rule = $this->gate->requirement($rt, $user, 'merge', ['fields' => [], 'status' => $record['system']['status_id'] ?? null, 'values' => $record['values'], 'old' => $record['values']]);

        return $this->gate->strictest(self::REQUIRED, $rule) ?? self::REQUIRED;
    }

    /** @return int how many live links point at the record */
    private function linkCount(FormRuntime $rt, int $id): int
    {
        $n = 0;
        foreach ($this->integrity->referencesTo($rt->form->uuid) as $ref) {
            $n += match ($ref['kind']) {
                'pivot' => DB::table($ref['pivot'])->where('target_id', $id)->count(),
                'row' => DB::table($ref['table'])->where($ref['column'], $id)->count(),
                default => DB::table($ref['rt']->table)->whereNull('deleted_at')->where($ref['column'], $id)->count(),
            };
        }

        return $n;
    }

    /**
     * Points everything that referenced `$from` at `$to`; each changed record
     * gets a new version (editors holding the old one get a conflict) and an
     * audit entry.
     *
     * @return array<string, int> links moved by referencing form and field
     */
    private function repoint(FormRuntime $rt, int $from, int $to, int $userId, string $now): array
    {
        $moved = [];
        foreach ($this->integrity->referencesTo($rt->form->uuid) as $ref) {
            /** @var FormRuntime $src */
            $src = $ref['rt'];
            $label = $src->form->key.'.'.($ref['field']['key'] ?? 'link');
            if ($ref['kind'] === 'pivot') {
                $sources = DB::table($ref['pivot'])->where('target_id', $from)->pluck('source_id')->map(static fn ($v) => (int) $v)->all();
                if ($sources === []) {
                    continue;
                }
                // A source already linked to both keeps one link.
                $both = DB::table($ref['pivot'])->where('target_id', $to)->whereIn('source_id', $sources)->pluck('source_id')->all();
                DB::table($ref['pivot'])->where('target_id', $from)->whereIn('source_id', $both ?: [0])->delete();
                DB::table($ref['pivot'])->where('target_id', $from)->update(['target_id' => $to]);
                $parents = $ref['row_table'] === null ? $sources : DB::table($ref['row_table'])->whereIn('id', $sources)->pluck('parent_id')->map(static fn ($v) => (int) $v)->all();
                $this->touch($src, array_values(array_unique($parents)), $userId, $now, $ref['field']['key'] ?? 'link');
                $moved[$label] = count($sources);

                continue;
            }
            if ($ref['kind'] === 'row') {
                $rows = DB::table($ref['table'])->where($ref['column'], $from)->get(['id', 'parent_id']);
                if ($rows->isEmpty()) {
                    continue;
                }
                DB::table($ref['table'])->whereIn('id', $rows->pluck('id')->all())->update([$ref['column'] => $to]);
                $this->touch($src, $rows->pluck('parent_id')->map(static fn ($v) => (int) $v)->unique()->values()->all(), $userId, $now, $ref['field']['key'] ?? 'link');
                $moved[$label] = $rows->count();

                continue;
            }
            $ids = DB::table($src->table)->where($ref['column'], $from)->pluck('id')->map(static fn ($v) => (int) $v)->all();
            if ($ids === []) {
                continue;
            }
            DB::table($src->table)->whereIn('id', $ids)->update([$ref['column'] => $to]);
            $this->touch($src, $ids, $userId, $now, $ref['field']['key'] ?? 'link');
            $moved[$label] = count($ids);
        }

        return $moved;
    }

    /** @param  list<int>  $ids */
    private function touch(FormRuntime $src, array $ids, int $userId, string $now, string $fieldKey): void
    {
        if ($ids === []) {
            return;
        }
        DB::table($src->table)->whereIn('id', $ids)->update(['row_version' => DB::raw('row_version + 1'), 'updated_at' => $now, 'updated_by' => $userId]);
        foreach ($ids as $id) {
            $this->audit->record('record.updated', 'data', [['field_key' => $fieldKey, 'old' => 'merged record', 'new' => 'surviving record']], 'record', $id,
                ['form' => $src->form->key, 'merge' => true], $userId, null, $src->form->id, $id);
        }
    }
}
