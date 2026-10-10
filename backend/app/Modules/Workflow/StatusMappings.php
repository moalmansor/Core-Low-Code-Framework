<?php

declare(strict_types=1);

namespace App\Modules\Workflow;

use App\Infrastructure\Database\Contracts\DatabaseDriver;
use App\Modules\Audit\AuditWriter;
use App\Modules\Core\I18n\Translator;
use App\Modules\Forms\Models\Form;
use App\Modules\Workflow\Models\Status;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Ramsey\Uuid\Uuid;

/**
 * The status mapping screen (architecture §13.5): when a draft removes
 * statuses that records still hold, the admin chooses where those records go.
 * The choices are pending `status_mappings` rows until the next publish turns
 * them into `map_status` migration steps. Records without a status when a
 * workflow is first published move to the initial status the same way.
 */
final class StatusMappings
{
    public function __construct(
        private readonly DatabaseDriver $driver,
        private readonly Translator $translator,
        private readonly AuditWriter $audit,
    ) {}

    /**
     * Removed statuses of the published version with their record counts and
     * the pending choice for each, plus the statuses records can move to.
     *
     * @param  array<string, mixed>|null  $published
     * @param  array<string, mixed>  $draftWorkflow
     * @return array{removed: list<array<string, mixed>>, targets: list<array<string, mixed>>, unassigned: int}
     */
    public function overview(Form $form, ?array $published, array $draftWorkflow): array
    {
        $draftUuids = array_flip(array_column($draftWorkflow['statuses'] ?? [], 'uuid'));
        $pending = DB::table('status_mappings')->where('form_id', $form->id)->whereNull('applied_at')->whereNull('migration_plan_id')
            ->pluck('to_status_id', 'from_status_key')->all();
        $statusUuids = Status::query()->where('form_id', $form->id)->pluck('uuid', 'id')->map(static fn ($u) => strtolower((string) $u))->all();
        $removed = [];
        foreach ($published['workflow']['statuses'] ?? [] as $s) {
            if (isset($draftUuids[$s['uuid']])) {
                continue;
            }
            $id = Status::query()->where('uuid', $s['uuid'])->value('id');
            $removed[] = [
                'uuid' => $s['uuid'],
                'key' => $s['key'],
                'name' => $id === null ? $s['key'] : ($this->translator->get('status', (int) $id, 'name') ?? $s['key']),
                'records' => $id === null ? 0 : $this->count($form, (int) $id),
                'to' => isset($pending[$s['key']]) ? ($statusUuids[(int) $pending[$s['key']]] ?? null) : null,
            ];
        }
        $targets = array_map(fn (array $s): array => [
            'uuid' => $s['uuid'], 'key' => $s['key'],
            'name' => $this->translator->labelOf($s['i18n']['name'] ?? null, $s['key']),
        ], $draftWorkflow['statuses'] ?? []);
        $unassigned = ($draftWorkflow['statuses'] ?? []) !== [] && $this->tableExists($form)
            ? (int) DB::table($form->table_name)->whereNull('status_id')->count() : 0;

        return ['removed' => $removed, 'targets' => $targets, 'unassigned' => $unassigned];
    }

    /**
     * Replaces the pending choices.
     *
     * @param  list<array{from: string, to: string}>  $choices  removed status uuid => draft status uuid
     * @param  array<string, mixed>|null  $published
     * @param  array<string, mixed>  $draftWorkflow
     */
    public function choose(Form $form, array $choices, ?array $published, array $draftWorkflow, int $userId): void
    {
        $overview = $this->overview($form, $published, $draftWorkflow);
        $removed = array_column($overview['removed'], null, 'uuid');
        $targets = array_flip(array_column($overview['targets'], 'uuid'));
        $errors = [];
        $rows = [];
        foreach ($choices as $i => $c) {
            if (! isset($removed[$c['from'] ?? ''])) {
                $errors["mappings.{$i}.from"][] = __('workflow.mapping_not_removed');

                continue;
            }
            if (! isset($targets[$c['to'] ?? ''])) {
                $errors["mappings.{$i}.to"][] = __('workflow.unknown_status');

                continue;
            }
            $rows[$removed[$c['from']]['key']] = (int) Status::query()->where('form_id', $form->id)->where('uuid', $c['to'])->value('id');
        }
        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
        DB::transaction(function () use ($form, $rows, $userId): void {
            DB::table('status_mappings')->where('form_id', $form->id)->whereNull('applied_at')->whereNull('migration_plan_id')->delete();
            $now = Carbon::now('UTC')->format('Y-m-d H:i:s.u');
            foreach ($rows as $key => $to) {
                DB::table('status_mappings')->insert([
                    'organization_id' => $form->organization_id, 'created_at' => $now, 'updated_at' => $now, 'created_by' => $userId, 'updated_by' => $userId,
                    'form_id' => $form->id, 'form_version_id' => null, 'change_type' => 'merge', 'from_status_key' => $key,
                    'to_status_id' => $to, 'records_affected' => 0, 'migration_plan_id' => null, 'applied_at' => null,
                ]);
            }
            $this->audit->record('workflow.status_mapping_chosen', 'config', null, 'form', $form->id, ['mappings' => $rows], $userId);
        });
    }

    /**
     * `map_status` operations for the next publish, and the blocking issues
     * of removed statuses that still hold records without a chosen target.
     *
     * @param  array<string, mixed>  $definition  compiled draft
     * @param  array<string, mixed>|null  $published
     * @return array{operations: list<array<string, mixed>>, blocking: list<array<string, mixed>>, summary: array<string, mixed>}
     */
    public function plan(Form $form, array $definition, ?array $published): array
    {
        $operations = [];
        $blocking = [];
        $overview = $this->overview($form, $published, $definition['workflow'] ?? []);
        $ids = Status::query()->where('form_id', $form->id)->pluck('id', 'uuid')->mapWithKeys(static fn ($id, $u) => [strtolower((string) $u) => (int) $id])->all();
        $version = (int) $definition['form']['version'];
        foreach ($overview['removed'] as $r) {
            if ($r['records'] === 0) {
                continue;
            }
            if ($r['to'] === null) {
                $blocking[] = ['code' => 'status_mapping_required', 'path' => 'workflow.statuses', 'message' => __('workflow.mapping_required', ['name' => $r['name'], 'count' => $r['records']])];

                continue;
            }
            $operations[] = $this->operation($form, $ids[$r['uuid']] ?? null, $ids[$r['to']], $r['key'], $version);
        }
        $initial = array_values(array_filter($definition['workflow']['statuses'] ?? [], static fn ($s) => $s['initial'] ?? false));
        if ($overview['unassigned'] > 0 && count($initial) === 1) {
            $operations[] = $this->operation($form, null, $ids[$initial[0]['uuid']], null, $version);
        }

        return ['operations' => $operations, 'blocking' => $blocking, 'summary' => $overview];
    }

    /**
     * Records the version's status changes once it is published: applied
     * mappings get the version, and added, renamed and removed statuses are
     * listed for the history.
     *
     * @param  array<string, mixed>  $definition
     * @param  array<string, mixed>|null  $published
     */
    public function finalize(Form $form, int $versionId, int $planId, array $definition, ?array $published, ?int $userId): void
    {
        DB::table('status_mappings')->where('form_id', $form->id)->where('migration_plan_id', $planId)->update(['form_version_id' => $versionId]);
        $before = array_column($published['workflow']['statuses'] ?? [], null, 'uuid');
        $after = array_column($definition['workflow']['statuses'] ?? [], null, 'uuid');
        $ids = Status::query()->where('form_id', $form->id)->pluck('id', 'uuid')->mapWithKeys(static fn ($id, $u) => [strtolower((string) $u) => (int) $id])->all();
        $now = Carbon::now('UTC')->format('Y-m-d H:i:s.u');
        $row = fn (string $type, string $key, ?int $to): array => [
            'organization_id' => $form->organization_id, 'created_at' => $now, 'updated_at' => $now, 'created_by' => $userId, 'updated_by' => $userId,
            'form_id' => $form->id, 'form_version_id' => $versionId, 'change_type' => $type, 'from_status_key' => $key, 'to_status_id' => $to,
            'records_affected' => 0, 'migration_plan_id' => $planId, 'applied_at' => $now,
        ];
        $mapped = DB::table('status_mappings')->where('migration_plan_id', $planId)->where('change_type', 'merge')->pluck('from_status_key')->all();
        foreach ($after as $uuid => $s) {
            if (! isset($before[$uuid])) {
                DB::table('status_mappings')->insert($row('add', $s['key'], $ids[$uuid] ?? null));
            } elseif ($before[$uuid]['key'] !== $s['key'] || (array) ($before[$uuid]['i18n']['name'] ?? []) != (array) ($s['i18n']['name'] ?? [])) {
                DB::table('status_mappings')->insert($row('rename', $before[$uuid]['key'], $ids[$uuid] ?? null));
            }
        }
        foreach ($before as $uuid => $s) {
            if (! isset($after[$uuid]) && ! in_array($s['key'], $mapped, true)) {
                DB::table('status_mappings')->insert($row('remove', $s['key'], null));
            }
        }
        // A pending choice for a status that held no records by publish time is no longer needed.
        DB::table('status_mappings')->where('form_id', $form->id)->whereNull('applied_at')->whereNull('migration_plan_id')->delete();
    }

    /**
     * Moves the records of one status (or without a status) to another in
     * batches, recording each move in the status history (`source =
     * status_mapping`) as the before-value that reversing uses.
     *
     * @param  array<string, mixed>  $op
     */
    public function apply(array $op, ?int $actorId, ?int $planId): int
    {
        $moved = 0;
        $now = Carbon::now('UTC')->format('Y-m-d H:i:s.u');
        $q = DB::table($op['table'])->select(['id', 'status_changed_at'])->orderBy('id');
        $op['from'] === null ? $q->whereNull('status_id') : $q->where('status_id', $op['from']);
        $q->chunkById(500, function ($rows) use ($op, $actorId, $now, &$moved): void {
            DB::transaction(function () use ($rows, $op, $actorId, $now, &$moved): void {
                $ids = $rows->pluck('id')->all();
                $updated = DB::table($op['table'])->whereIn('id', $ids)->when($op['from'] === null, fn ($q) => $q->whereNull('status_id'), fn ($q) => $q->where('status_id', $op['from']))
                    ->update(['status_id' => $op['to'], 'status_changed_at' => $now, 'row_version' => DB::raw('row_version + 1')]);
                $history = [];
                foreach ($rows as $r) {
                    $history[] = $this->history($op, (int) $r->id, $op['from'], $op['to'], $actorId, $now, $r->status_changed_at);
                }
                if ($history !== []) {
                    DB::table('status_history')->insert($history);
                }
                $moved += $updated;
            });
        });
        if ($op['key'] !== null) {
            DB::table('status_mappings')->where('form_id', $op['form_id'])->where('from_status_key', $op['key'])->whereNull('applied_at')
                ->where(fn ($q) => $q->whereNull('migration_plan_id')->orWhere('migration_plan_id', $planId))
                ->update(['records_affected' => $moved, 'applied_at' => $now, 'migration_plan_id' => $planId, 'updated_at' => $now]);
        }

        return $moved;
    }

    /**
     * Reverses `apply()`: records the mapping moved and that still hold the
     * target status go back, each with a compensating history entry.
     *
     * @param  array<string, mixed>  $op
     */
    public function revert(array $op, ?int $actorId): void
    {
        $now = Carbon::now('UTC')->format('Y-m-d H:i:s.u');
        DB::table('status_history')->where('correlation_id', $op['batch'])->where('source', 'status_mapping')->where('to_status_id', $op['to'])
            ->select(['id', 'record_id'])->orderBy('id')
            ->chunkById(500, function ($rows) use ($op, $actorId, $now): void {
                DB::transaction(function () use ($rows, $op, $actorId, $now): void {
                    $ids = $rows->pluck('record_id')->all();
                    $back = DB::table($op['table'])->whereIn('id', $ids)->where('status_id', $op['to'])->pluck('id')->all();
                    if ($back === []) {
                        return;
                    }
                    DB::table($op['table'])->whereIn('id', $back)->where('status_id', $op['to'])
                        ->update(['status_id' => $op['from'], 'status_changed_at' => $now, 'row_version' => DB::raw('row_version + 1')]);
                    if ($op['from'] !== null) {
                        DB::table('status_history')->insert(array_map(fn ($id) => $this->history(['batch' => $op['batch'].'r'] + $op, (int) $id, $op['to'], $op['from'], $actorId, $now, null), $back));
                    }
                });
            });
        if ($op['key'] !== null) {
            DB::table('status_mappings')->where('form_id', $op['form_id'])->where('from_status_key', $op['key'])->whereNotNull('applied_at')->whereNull('form_version_id')
                ->update(['records_affected' => 0, 'applied_at' => null, 'migration_plan_id' => null, 'updated_at' => $now]);
        }
    }

    /** @return array<string, mixed> */
    private function operation(Form $form, ?int $from, int $to, ?string $key, int $version): array
    {
        // Deterministic per form, version and source status, so the reviewed and the executed plan hash alike.
        $batch = Uuid::uuid5(Uuid::NAMESPACE_URL, "lcf:status-mapping:{$form->uuid}:{$version}:".($from ?? 'none'))->toString();

        return ['op' => 'map_status', 'table' => $form->table_name, 'form_id' => $form->id, 'organization_id' => $form->organization_id, 'from' => $from, 'to' => $to, 'key' => $key, 'batch' => substr($batch, 0, 35)];
    }

    /** @return array<string, mixed> */
    private function history(array $op, int $recordId, ?int $from, ?int $to, ?int $actorId, string $now, mixed $since): array
    {
        $seconds = $since === null ? null : max(0, Carbon::parse($now, 'UTC')->getTimestamp() - Carbon::parse((string) $since, 'UTC')->getTimestamp());

        return [
            'organization_id' => $op['organization_id'], 'form_id' => $op['form_id'], 'record_id' => $recordId,
            'from_status_id' => $from, 'to_status_id' => $to, 'transition_id' => null, 'source' => 'status_mapping',
            'comment' => null, 'attachment_file_ids' => null, 'acted_by' => $actorId, 'on_behalf_of_user_id' => null,
            'justification_id' => null, 'approval_request_id' => null, 'seconds_in_previous' => $seconds, 'working_seconds_in_previous' => null,
            'correlation_id' => $op['batch'], 'acted_at' => $now,
        ];
    }

    private function count(Form $form, int $statusId): int
    {
        return $this->tableExists($form) ? (int) DB::table($form->table_name)->where('status_id', $statusId)->count() : 0;
    }

    private function tableExists(Form $form): bool
    {
        return $form->current_version_id !== null && in_array(strtolower($form->table_name), array_map('strtolower', $this->driver->tables()), true);
    }
}
