<?php

declare(strict_types=1);

namespace App\Modules\DataQuality;

use App\Modules\Access\RecordScope;
use App\Modules\Audit\AuditWriter;
use App\Modules\Core\Correlation\CorrelationId;
use App\Modules\Core\I18n\Translator;
use App\Modules\DataQuality\Jobs\RunDataRepair;
use App\Modules\Forms\Models\Form;
use App\Modules\Identity\Models\User;
use App\Modules\Records\Runtime\FormRuntime;
use App\Modules\Records\Runtime\FormRuntimes;
use App\Modules\Records\Runtime\RecordException;
use App\Modules\Records\Runtime\RecordPipeline;
use App\Modules\Records\Runtime\RecordStore;
use App\Modules\Records\Runtime\RecycleBin;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Data repair for administrators (specification §4.35, ADR-0044), with
 * "Repair data". Two scans, run in the background as bulk operations:
 * broken links (records pointing at records that no longer exist or are in
 * the recycle bin) and records that fail the form's current validation (after
 * a rule changed). Findings are fixed in a guided screen: a broken link can
 * be cleared, through the normal save so that rules, access and the audit
 * apply; anything else opens the record to be corrected by hand.
 */
final class DataRepair
{
    public const SCANS = ['orphan_scan', 'validation_sweep'];

    private const MAX_FINDINGS = 1000;

    private const MAX_SCANNED = 50000;

    public function __construct(
        private readonly FormRuntimes $runtimes,
        private readonly RecordStore $store,
        private readonly RecordPipeline $pipeline,
        private readonly RecordScope $scope,
        private readonly Translator $translator,
        private readonly AuditWriter $audit,
        private readonly CorrelationId $correlation,
        private readonly RecycleBin $bin,
    ) {}

    public function start(Form $form, string $type, User $user): object
    {
        if ($this->runtimes->forForm($form) === null) {
            throw ValidationException::withMessages(['form' => __('data_quality.publish_first')]);
        }
        $now = Carbon::now('UTC')->format('Y-m-d H:i:s.u');
        $id = (int) DB::table('bulk_operations')->insertGetId([
            'uuid' => (string) Str::uuid7(), 'organization_id' => $form->organization_id, 'created_at' => $now, 'updated_at' => $now,
            'form_id' => $form->id, 'type' => $type, 'criteria' => json_encode(['all' => true]), 'payload' => null, 'dry_run' => false,
            'preview_count' => null, 'max_count' => 0, 'confirmed_above_max' => false, 'status' => 'queued', 'progress' => 0,
            'affected_count' => 0, 'failed_count' => 0, 'created_by' => $user->id, 'correlation_id' => $this->correlation->get(),
        ]);
        $this->audit->record('data_repair.scan_started', 'data', null, 'form', $form->id, ['scan' => $type], $user->id, null, $form->id);
        RunDataRepair::dispatch($id)->afterCommit();

        return DB::table('bulk_operations')->where('id', $id)->first() ?? throw new \LogicException('operation vanished');
    }

    /** Runs a scan (from RunDataRepair), as the person who started it. */
    public function run(int $operationId): void
    {
        $op = DB::table('bulk_operations')->where('id', $operationId)->first();
        if ($op === null || $op->status !== 'queued' || ! in_array($op->type, self::SCANS, true)) {
            return;
        }
        $start = Carbon::now('UTC')->format('Y-m-d H:i:s.u');
        DB::table('bulk_operations')->where('id', $op->id)->update(['status' => 'running', 'started_at' => $start, 'updated_at' => $start]);
        $form = Form::query()->find($op->form_id);
        $rt = $form === null ? null : $this->runtimes->forForm($form);
        $user = User::query()->find($op->created_by);
        $previous = Auth::user();
        try {
            if ($rt === null || $user === null) {
                throw new \RuntimeException((string) __('data_quality.publish_first'));
            }
            Auth::setUser($user);
            [$findings, $scanned] = $op->type === 'orphan_scan' ? $this->orphans($rt) : $this->invalid($rt, $user);
            $done = Carbon::now('UTC')->format('Y-m-d H:i:s.u');
            DB::table('bulk_operations')->where('id', $op->id)->update([
                'status' => 'completed', 'progress' => 100, 'preview_count' => $scanned, 'affected_count' => count($findings),
                'result' => json_encode(['findings' => array_slice($findings, 0, self::MAX_FINDINGS), 'more' => count($findings) > self::MAX_FINDINGS], JSON_UNESCAPED_UNICODE),
                'finished_at' => $done, 'updated_at' => $done,
            ]);
        } catch (Throwable $e) {
            report($e);
            DB::table('bulk_operations')->where('id', $op->id)->update(['status' => 'failed', 'error' => mb_substr($e->getMessage(), 0, 2000), 'finished_at' => Carbon::now('UTC')->format('Y-m-d H:i:s.u')]);
        } finally {
            $previous === null ? Auth::guard()->forgetUser() : Auth::setUser($previous);
        }
    }

    /**
     * A scan's state and its findings, as records the user may see, with the
     * field's label in the user's language.
     *
     * @return array<string, mixed>
     */
    public function present(object $op, User $user): array
    {
        $form = Form::query()->find($op->form_id);
        $rt = $form === null ? null : $this->runtimes->forForm($form);
        $result = json_decode((string) ($op->result ?? 'null'), true) ?: [];
        $rows = [];
        $hidden = 0;
        if ($rt !== null) {
            $ids = array_values(array_unique(array_map(static fn ($f) => (int) $f['record'], (array) ($result['findings'] ?? []))));
            $live = DB::table($rt->table)->whereIn('id', $ids ?: [0])->whereNull('deleted_at')->get()->keyBy('id');
            foreach ((array) ($result['findings'] ?? []) as $f) {
                $row = $live[(int) $f['record']] ?? null;
                if ($row === null) {
                    continue; // deleted since the scan
                }
                if (! $this->scope->allows($rt, $user, 'view', (int) $row->id)) {
                    $hidden++;

                    continue;
                }
                $field = isset($rt->keys[$f['field'] ?? '']) ? ($rt->fields[$rt->keys[$f['field']]] ?? null) : null;
                $rows[] = [
                    'record' => strtolower((string) $row->uuid), 'number' => $row->record_number, 'title' => $this->bin->title($rt, $row), 'field' => $f['field'] ?? null,
                    'label' => $field === null ? null : $this->translator->labelOf((array) ($field['i18n']['label'] ?? []), (string) $field['key']),
                    'problem' => $f['problem'], 'message' => $f['message'] ?? null, 'fixable' => (bool) ($f['fixable'] ?? false),
                ];
            }
        }

        return [
            'uuid' => strtolower((string) $op->uuid), 'type' => $op->type, 'status' => $op->status, 'scanned' => $op->preview_count === null ? null : (int) $op->preview_count,
            'findings' => $rows, 'hidden' => $hidden, 'more' => (bool) ($result['more'] ?? false), 'error' => $op->error,
            'finished_at' => $op->finished_at === null ? null : Carbon::parse((string) $op->finished_at, 'UTC')->toIso8601ZuluString(),
        ];
    }

    /**
     * Clears broken links, each through a normal save of its record (rules,
     * field access, validation and the audit apply; a required link that
     * cannot be emptied stays, with the reason).
     *
     * @param  list<array{record: string, field: string}>  $items
     * @return array{fixed: int, failed: list<array{record: string, field: string, message: string}>}
     */
    public function clearLinks(Form $form, array $items, User $user): array
    {
        $rt = $this->runtimes->forForm($form) ?? throw ValidationException::withMessages(['form' => __('data_quality.publish_first')]);
        $fixed = 0;
        $failed = [];
        foreach (array_slice($items, 0, 500) as $item) {
            $uuid = strtolower((string) ($item['record'] ?? ''));
            $key = (string) ($item['field'] ?? '');
            $field = isset($rt->keys[$key]) ? ($rt->fields[$rt->keys[$key]] ?? null) : null;
            $found = Str::isUuid($uuid) ? $this->store->find($rt, $uuid) : null;
            if ($field === null || $found === null || isset($rt->fieldRepeater[$field['uuid']]) || $rt->relationOf($field) === null) {
                $failed[] = ['record' => $uuid, 'field' => $key, 'message' => (string) __('data_quality.not_fixable')];

                continue;
            }
            try {
                $value = $rt->isMultiReference($field) ? $this->liveTargets($rt, $field, (array) ($found['values'][$key] ?? [])) : null;
                $this->pipeline->update($rt, $user, $uuid, $found['row_version'], [$key => $value], 'repair-'.Str::uuid7(), 'ui');
                $fixed++;
            } catch (RecordException $e) {
                $errors = (array) ($e->payload['errors'] ?? []);
                $failed[] = ['record' => $uuid, 'field' => $key, 'message' => (string) (is_array($errors[$key] ?? null) ? ($errors[$key][0] ?? $e->getMessage()) : ($errors[$key] ?? $e->getMessage()))];
            }
        }
        $this->audit->record('data_repair.links_cleared', 'data', null, 'form', $form->id, ['fixed' => $fixed, 'failed' => count($failed)], $user->id, null, $form->id);

        return ['fixed' => $fixed, 'failed' => $failed];
    }

    /**
     * Links to records that are missing or in the recycle bin: in the
     * record's own columns, its repeating sections and its multi-links.
     *
     * @return array{0: list<array<string, mixed>>, 1: int}
     */
    private function orphans(FormRuntime $rt): array
    {
        $findings = [];
        foreach ($rt->fields as $f) {
            $target = $rt->targetTable($f);
            $relation = $rt->relationOf($f);
            if ($target === null || $relation === null || ! Schema::hasTable($target)) {
                continue;
            }
            $soft = Schema::hasColumn($target, 'deleted_at');
            $repeater = $rt->fieldRepeater[$f['uuid']] ?? null;
            if ($rt->isMultiReference($f)) {
                $pivot = $rt->pivots[$relation['uuid']] ?? null;
                if ($pivot === null) {
                    continue;
                }
                $q = DB::table($pivot.' as p')->leftJoin($target.' as x', 'x.id', '=', 'p.target_id')
                    ->where(static fn ($w) => $soft ? $w->whereNull('x.id')->orWhereNotNull('x.deleted_at') : $w->whereNull('x.id'));
                $sources = $q->distinct()->limit(self::MAX_FINDINGS)->pluck('p.source_id')->map(static fn ($v) => (int) $v)->all();
                $parents = $repeater === null ? $sources : DB::table($rt->repeaters[$repeater]['table'])->whereIn('id', $sources ?: [0])->pluck('parent_id')->map(static fn ($v) => (int) $v)->all();
                foreach (DB::table($rt->table)->whereIn('id', $parents ?: [0])->whereNull('deleted_at')->pluck('id') as $id) {
                    $findings[] = ['record' => (int) $id, 'field' => $repeater === null ? $f['key'] : $rt->repeaters[$repeater]['group']['key'], 'problem' => 'broken_link', 'fixable' => $repeater === null];
                }

                continue;
            }
            $col = $rt->column($f['uuid'])['name'] ?? null;
            if ($col === null) {
                continue;
            }
            $table = $repeater === null ? $rt->table : $rt->repeaters[$repeater]['table'];
            $q = DB::table($table.' as t')->leftJoin($target.' as x', 'x.id', '=', 't.'.$col)->whereNotNull('t.'.$col)
                ->where(static fn ($w) => $soft ? $w->whereNull('x.id')->orWhereNotNull('x.deleted_at') : $w->whereNull('x.id'));
            if ($repeater === null) {
                $q->whereNull('t.deleted_at');
                foreach ($q->limit(self::MAX_FINDINGS)->pluck('t.id') as $id) {
                    $findings[] = ['record' => (int) $id, 'field' => $f['key'], 'problem' => 'broken_link', 'fixable' => true];
                }
            } else {
                foreach ($q->join($rt->table.' as r', 'r.id', '=', 't.parent_id')->whereNull('r.deleted_at')->distinct()->limit(self::MAX_FINDINGS)->pluck('t.parent_id') as $id) {
                    $findings[] = ['record' => (int) $id, 'field' => $rt->repeaters[$repeater]['group']['key'], 'problem' => 'broken_link', 'fixable' => false];
                }
            }
        }

        return [$findings, DB::table($rt->table)->whereNull('deleted_at')->count()];
    }

    /**
     * Records that would not save as they are under the form's current rules.
     *
     * @return array{0: list<array<string, mixed>>, 1: int}
     */
    private function invalid(FormRuntime $rt, User $user): array
    {
        $findings = [];
        $scanned = 0;
        foreach (DB::table($rt->table)->whereNull('deleted_at')->orderBy('id')->lazyById(200, 'id') as $row) {
            if (++$scanned > self::MAX_SCANNED || count($findings) >= self::MAX_FINDINGS) {
                break;
            }
            $found = $this->store->findById($rt, (int) $row->id);
            if ($found === null) {
                continue;
            }
            try {
                $errors = $this->pipeline->check($rt, $user, [], $found);
            } catch (RecordException) {
                continue; // a record the person may not edit is not theirs to repair
            }
            foreach ($errors as $key => $messages) {
                $findings[] = ['record' => (int) $row->id, 'field' => (string) $key, 'problem' => 'invalid', 'message' => (string) (is_array($messages) ? ($messages[0] ?? '') : $messages), 'fixable' => false];
            }
        }

        return [$findings, min($scanned, self::MAX_SCANNED)];
    }

    /**
     * The links of a multi-link field that still point at live records.
     *
     * @param  array<string, mixed>  $field
     * @param  list<mixed>  $uuids
     * @return list<string>
     */
    private function liveTargets(FormRuntime $rt, array $field, array $uuids): array
    {
        $table = $rt->targetTable($field);
        $uuids = array_values(array_filter($uuids, static fn ($u) => is_string($u) && Str::isUuid($u)));
        if ($table === null || $uuids === []) {
            return [];
        }
        $q = DB::table($table)->whereIn('uuid', array_map('strtolower', $uuids));
        if (Schema::hasColumn($table, 'deleted_at')) {
            $q->whereNull('deleted_at');
        }

        return $q->pluck('uuid')->map(static fn ($u) => strtolower((string) $u))->all();
    }
}
