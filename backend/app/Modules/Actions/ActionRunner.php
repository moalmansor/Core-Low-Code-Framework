<?php

declare(strict_types=1);

namespace App\Modules\Actions;

use App\Modules\Access\AccessResolver;
use App\Modules\Actions\Jobs\RunAction;
use App\Modules\Assignment\AssignmentService;
use App\Modules\Audit\AuditWriter;
use App\Modules\Core\Correlation\CorrelationId;
use App\Modules\Core\I18n\Translator;
use App\Modules\Core\Settings\SettingsService;
use App\Modules\Extensions\HookRunner;
use App\Modules\Forms\Conditions\OwnedConditions;
use App\Modules\Forms\Models\Form;
use App\Modules\Identity\Models\User;
use App\Modules\Justification\JustificationGate;
use App\Modules\Notifications\InAppNotifier;
use App\Modules\Records\Exchange\ExportService;
use App\Modules\Records\Runtime\FormRuntime;
use App\Modules\Records\Runtime\FormRuntimes;
use App\Modules\Records\Runtime\RecordException;
use App\Modules\Records\Runtime\RecordPipeline;
use App\Modules\Records\Runtime\RecordStore;
use App\Modules\Records\Runtime\RecycleBin;
use App\Modules\Records\Runtime\RuleRuntime;
use App\Modules\Workflow\Runtime\WorkflowRuntime;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Runs actions (specification §4.15 "Execution"). An action is offered only
 * to holders of its permission who also hold the form ability its work needs
 * (create to duplicate, edit to update, delete to delete, view otherwise),
 * where it is placed, and — for a record — when its condition holds. A run
 * asks one justification for everything it does, at the strictest level any
 * of its records needs (the action's own level, action-scope rules, the edit
 * rules of the fields it sets, the transition and delete rules it triggers),
 * saves it once with the count, and every record change links to it. Every
 * change goes through the record pipeline, so access, record scope, claims,
 * legal hold, validation and optimistic concurrency apply as in the form.
 * Runs above the action's threshold go to a background job; each run is a
 * bulk operation with progress and a per-record result.
 */
final class ActionRunner
{
    /** Built-in => form ability its work needs. */
    public const ABILITY = ['duplicate' => 'create', 'print' => 'print', 'bulk_update' => 'edit', 'bulk_status' => 'view', 'bulk_delete' => 'delete', 'bulk_reassign' => 'edit'];

    /** A dry run tries at most this many records (in a transaction rolled back after each). */
    public const DRY_RUN_LIMIT = 200;

    /** Built-ins whose dry run tries each record: the bulk operations on the form's own data. */
    public const BULK = ['bulk_update', 'bulk_status', 'bulk_delete', 'bulk_reassign'];

    public function __construct(
        private readonly FormRuntimes $runtimes,
        private readonly RecordStore $store,
        private readonly RecordPipeline $pipeline,
        private readonly RuleRuntime $rules,
        private readonly OwnedConditions $conditions,
        private readonly AccessResolver $access,
        private readonly JustificationGate $gate,
        private readonly Translator $translator,
        private readonly SettingsService $settings,
        private readonly ActionSteps $steps,
        private readonly InAppNotifier $inApp,
        private readonly AuditWriter $audit,
        private readonly CorrelationId $correlation,
    ) {}

    /**
     * The actions a user may run here: placement, permission, ability and,
     * for a record, the availability condition.
     *
     * @param  array<string, mixed>|null  $record  a found record (values, system)
     * @return list<array<string, mixed>>
     */
    public function available(FormRuntime $rt, User $user, ?string $placement, ?array $record): array
    {
        $rows = DB::table('actions')->where('form_id', $rt->form->id)->where('is_active', true)->orderBy('sort_order')->orderBy('id')->get();
        $asts = $this->conditions->many($rows->pluck('condition_id')->all());
        $tr = $this->translator->allMany('action', $rows->pluck('id')->map(static fn ($v) => (int) $v)->all());
        $out = [];
        foreach ($rows as $a) {
            $placements = json_decode((string) $a->placements, true) ?: [];
            if (($placement !== null && ! in_array($placement, $placements, true)) || ! $this->allowed($a, $rt, $user)) {
                continue;
            }
            $ast = $a->condition_id === null ? null : ($asts[(int) $a->condition_id] ?? null);
            if ($record !== null && $ast !== null && ! $this->rules->holds($rt, $ast, $record['values'], $user, 'view')) {
                continue;
            }
            $label = $tr[(int) $a->id]['label'] ?? [];
            $out[] = [
                'uuid' => strtolower((string) $a->uuid), 'kind' => $a->kind, 'builtin' => $a->builtin, 'placements' => $placements,
                'label' => $this->translator->labelOf($label, (string) $a->key),
                'confirm' => (bool) $a->requires_confirmation,
                'confirmation' => $this->translator->pick($tr[(int) $a->id]['confirmation_text'] ?? []),
                'icon' => $a->icon, 'color' => $a->color, 'conditional' => $ast !== null,
            ];
        }

        return $out;
    }

    public function find(FormRuntime $rt, string $uuid): ?object
    {
        return DB::table('actions')->where('form_id', $rt->form->id)->where('uuid', strtolower($uuid))->where('is_active', true)->first();
    }

    public function allowed(object $action, FormRuntime $rt, User $user): bool
    {
        $ability = $action->kind === 'builtin' ? (self::ABILITY[$action->builtin] ?? 'view') : 'view';

        return $this->access->allows($user, 'action.'.strtolower((string) $action->uuid).'.run')
            && $this->access->allows($user, "form.{$rt->form->uuid}.{$ability}");
    }

    /**
     * The records a run works on: the chosen ones (with the version the user
     * saw), or for the toolbar every record the current filters show.
     *
     * @param  array<string, mixed>  $input  {items: [{uuid, row_version}]} or {filters: {...}}
     * @return list<array{uuid: string, row_version: int|null}>
     */
    public function targets(object $action, FormRuntime $rt, User $user, array $input): array
    {
        $max = $this->maxRecords($action);
        if (isset($input['filters']) && is_array($input['filters'])) {
            $rows = app(ExportService::class)->plan($rt, $user, $input['filters'])['query']->limit($max + 1)
                ->get([$rt->table.'.uuid', $rt->table.'.row_version']);
            $targets = $rows->map(static fn ($r) => ['uuid' => strtolower((string) $r->uuid), 'row_version' => (int) $r->row_version])->all();
        } else {
            $targets = array_map(static fn ($i) => ['uuid' => strtolower((string) $i['uuid']), 'row_version' => isset($i['row_version']) ? (int) $i['row_version'] : null], (array) ($input['items'] ?? []));
        }
        if ($targets === []) {
            throw ValidationException::withMessages(['items' => __('actions.nothing_selected')]);
        }
        if (count($targets) > $max) {
            throw ValidationException::withMessages(['items' => __('actions.too_many_records', ['max' => $max])]);
        }

        return array_values($targets);
    }

    /**
     * What the run will ask: how many records, whether it runs in the
     * background, the justification prompt, and the inputs a built-in needs.
     *
     * @param  list<array{uuid: string, row_version: int|null}>  $targets
     * @return array<string, mixed>
     */
    public function prepare(object $action, FormRuntime $rt, User $user, array $targets, array $inputs = []): array
    {
        [$req, $change] = $this->requirement($action, $rt, $user, $targets, $inputs);

        return [
            'count' => count($targets),
            'queued' => $this->queued($action, count($targets)),
            'justification' => $req === null ? null : $this->gate->prompt($rt, $req, $change, $user) + ['count' => count($targets)],
            'inputs' => $this->inputs($action, $rt, $user),
            'preview' => $this->preview($rt, $targets),
            'dry_run' => in_array($action->builtin, self::BULK, true),
        ];
    }

    /**
     * The first records a run covers, named as in the recycle bin, so the
     * person sees what they are about to change.
     *
     * @param  list<array{uuid: string, row_version: int|null}>  $targets
     * @return list<array{uuid: string, title: string}>
     */
    private function preview(FormRuntime $rt, array $targets): array
    {
        $uuids = array_column(array_slice($targets, 0, 20), 'uuid');
        $rows = DB::table($rt->table)->whereIn('uuid', $uuids ?: ['00000000-0000-0000-0000-000000000000'])->get()->keyBy(static fn ($r) => strtolower((string) $r->uuid));
        $bin = app(RecycleBin::class);
        $out = [];
        foreach ($uuids as $u) {
            if (isset($rows[$u])) {
                $out[] = ['uuid' => $u, 'title' => $bin->title($rt, $rows[$u])];
            }
        }

        return $out;
    }

    /**
     * Tries a bulk built-in on its records without keeping anything: each
     * record runs through the record pipeline (access, scope, validation,
     * concurrency, legal hold) inside a transaction that is rolled back. The
     * justification is asked when the run starts, so a record that only
     * lacks one counts as one that would succeed.
     *
     * @param  list<array{uuid: string, row_version: int|null}>  $targets
     * @param  array<string, mixed>  $inputs
     * @return array<string, mixed>
     */
    public function dryRun(object $action, FormRuntime $rt, User $user, array $targets, array $inputs): array
    {
        if (! in_array($action->builtin, self::BULK, true)) {
            throw ValidationException::withMessages(['action' => __('actions.no_dry_run')]);
        }
        $inputs = $this->checkInputs($action, $rt, $inputs);
        $tried = array_slice($targets, 0, self::DRY_RUN_LIMIT);
        $ok = 0;
        $errors = [];
        foreach ($tried as $target) {
            DB::beginTransaction();
            try {
                $this->builtin($action, $rt, $user, $target, $inputs, 'dry-'.Str::uuid7(), null);
                $ok++;
            } catch (RecordException $e) {
                in_array($e->reason, ['justification_required', 'justification_invalid'], true) ? $ok++ : $errors[] = ['uuid' => $target['uuid'], 'error' => self::message($e)];
            } catch (ValidationException|StepFailed $e) {
                $errors[] = ['uuid' => $target['uuid'], 'error' => self::message($e)];
            } finally {
                DB::rollBack();
            }
        }
        $now = Carbon::now('UTC')->format('Y-m-d H:i:s.u');
        DB::table('bulk_operations')->insert([
            'uuid' => (string) Str::uuid7(), 'organization_id' => $rt->form->organization_id, 'created_at' => $now, 'updated_at' => $now, 'started_at' => $now, 'finished_at' => $now,
            'form_id' => $rt->form->id, 'type' => match ($action->builtin) {
                'bulk_update' => 'update', 'bulk_status' => 'status_change', 'bulk_delete' => 'delete', default => 'reassign',
            }, 'action_id' => (int) $action->id,
            'criteria' => json_encode(['records' => $targets]), 'payload' => $inputs === [] ? null : json_encode($inputs),
            'dry_run' => true, 'preview_count' => count($targets), 'max_count' => $this->maxRecords($action), 'confirmed_above_max' => false,
            'status' => 'completed', 'progress' => 100, 'affected_count' => $ok, 'failed_count' => count($errors),
            'result' => json_encode(['records' => $errors, 'outputs' => []], JSON_UNESCAPED_UNICODE), 'created_by' => $user->id, 'correlation_id' => $this->correlation->get(),
        ]);
        $this->audit->record('action.dry_run', 'data', null, 'action', (int) $action->id, ['action' => $action->key, 'records' => count($targets), 'tried' => count($tried), 'would_fail' => count($errors)], $user->id, null, $rt->form->id);
        $bin = app(RecycleBin::class);
        $titles = DB::table($rt->table)->whereIn('uuid', array_column($errors, 'uuid') ?: ['00000000-0000-0000-0000-000000000000'])->get()->keyBy(static fn ($r) => strtolower((string) $r->uuid));

        return [
            'count' => count($targets), 'tried' => count($tried), 'would_succeed' => $ok, 'would_fail' => count($errors),
            'errors' => array_map(static fn ($e) => $e + ['title' => isset($titles[$e['uuid']]) ? $bin->title($rt, $titles[$e['uuid']]) : ''], array_slice($errors, 0, 50)),
        ];
    }

    /**
     * Starts a run: checks the inputs and the justification, saves the
     * justification once, then runs now or queues it.
     *
     * @param  list<array{uuid: string, row_version: int|null}>  $targets
     * @param  array<string, mixed>  $inputs
     * @param  array<string, mixed>|null  $justification
     * @return array<string, mixed>
     */
    public function start(object $action, FormRuntime $rt, User $user, array $targets, array $inputs, ?array $justification): array
    {
        $inputs = $this->checkInputs($action, $rt, $inputs);
        [$req, $change] = $this->requirement($action, $rt, $user, $targets, $inputs);
        $validated = $this->gate->check($rt, $user, $req, $change, $justification);
        $now = Carbon::now('UTC')->format('Y-m-d H:i:s.u');
        $queued = $this->queued($action, count($targets));
        $context = self::context($action);
        $opId = DB::transaction(function () use ($action, $rt, $user, $targets, $inputs, $validated, $now, $queued, $context): int {
            $justificationId = $validated === null ? null : $this->gate->record($rt, count($targets) === 1 ? $this->recordId($rt, $targets[0]['uuid']) : null, $user, $context, $validated, [], count($targets));
            $opId = (int) DB::table('bulk_operations')->insertGetId([
                'uuid' => (string) Str::uuid7(), 'organization_id' => $rt->form->organization_id, 'created_at' => $now, 'updated_at' => $now,
                'form_id' => $rt->form->id, 'type' => match ($action->builtin) {
                    'bulk_update' => 'update', 'bulk_status' => 'status_change', 'bulk_delete' => 'delete', 'bulk_reassign' => 'reassign', default => 'action',
                }, 'action_id' => (int) $action->id,
                'criteria' => json_encode(['records' => $targets]), 'payload' => $inputs === [] ? null : json_encode($inputs),
                'dry_run' => false, 'preview_count' => count($targets), 'max_count' => $this->maxRecords($action), 'confirmed_above_max' => false,
                'status' => $queued ? 'queued' : 'running', 'progress' => 0, 'affected_count' => 0, 'failed_count' => 0,
                'justification_id' => $justificationId, 'created_by' => $user->id, 'correlation_id' => $this->correlation->get(),
            ]);
            if ($justificationId !== null) {
                DB::table('justifications')->where('id', $justificationId)->update(['bulk_operation_id' => $opId]);
            }

            return $opId;
        });
        $this->audit->record('action.started', 'data', null, 'action', (int) $action->id, ['action' => $action->key, 'records' => count($targets), 'queued' => $queued], $user->id, null, $rt->form->id);
        if ($queued) {
            RunAction::dispatch($opId);
        } else {
            $this->execute($opId);
        }

        return $this->present($opId, $user);
    }

    /**
     * Works through a run's records (now, or in the background job). A job's
     * run that breaks goes back to the queue state, and its retry carries on
     * after the records already done.
     */
    public function execute(int $opId, bool $retryable = false): void
    {
        $op = DB::table('bulk_operations')->where('id', $opId)->first();
        if ($op === null || in_array($op->status, ['completed', 'completed_with_errors', 'failed', 'cancelled'], true)) {
            return;
        }
        $background = $op->status === 'queued';
        $action = DB::table('actions')->where('id', $op->action_id)->first();
        $rt = $this->runtimes->forForm(Form::query()->findOrFail($op->form_id));
        $user = User::query()->find($op->created_by);
        if ($action === null || $rt === null || $user === null) {
            DB::table('bulk_operations')->where('id', $opId)->update(['status' => 'failed', 'error' => __('actions.gone'), 'finished_at' => Carbon::now('UTC')->format('Y-m-d H:i:s.u')]);

            return;
        }
        $previousLocale = app()->getLocale();
        app()->setLocale($this->localeOf($user));
        DB::table('bulk_operations')->where('id', $opId)->update(['status' => 'running', 'started_at' => Carbon::now('UTC')->format('Y-m-d H:i:s.u')]);
        $targets = json_decode((string) $op->criteria, true)['records'] ?? [];
        $inputs = json_decode((string) ($op->payload ?? 'null'), true) ?? [];
        $result = json_decode((string) ($op->result ?? 'null'), true) ?? ['records' => [], 'outputs' => []];
        $done = array_column($result['records'], null, 'uuid');
        $affected = (int) $op->affected_count;
        $failed = (int) $op->failed_count;
        $steps = $action->kind === 'custom' ? DB::table('action_steps')->where('action_id', $action->id)->orderBy('sort_order')->orderBy('id')->get()->all() : [];
        $stepAsts = $this->conditions->many(array_map(static fn ($s) => $s->condition_id, $steps));
        $label = $this->translator->labelOf($this->translator->allMany('action', [(int) $action->id])[(int) $action->id]['label'] ?? [], (string) $action->key);
        try {
            foreach ($targets as $n => $target) {
                if (isset($done[$target['uuid']])) {
                    continue;
                }
                if (DB::table('bulk_operations')->where('id', $opId)->value('status') === 'cancelled') {
                    break;
                }
                // Journal key of this record's saves in this run: a retried run replays them instead of saving twice.
                $key = 'act-'.substr(hash('sha256', $op->uuid.':'.$target['uuid']), 0, 40);
                try {
                    $outputs = $action->kind === 'custom'
                        ? $this->steps->runAll($steps, $stepAsts, $rt, $user, $target, $key, (int) $op->justification_id ?: null, strtolower((string) $action->uuid), $label)
                        : $this->builtin($action, $rt, $user, $target, $inputs, $key, $op->justification_id === null ? null : (int) $op->justification_id);
                    $this->onAction($rt, $user, $target['uuid'], (string) $action->key);
                    $result['records'][] = ['uuid' => $target['uuid'], 'ok' => true, 'error' => null];
                    $result['outputs'] = array_slice([...$result['outputs'], ...$outputs], 0, 20);
                    $affected++;
                } catch (RecordException|ValidationException|StepFailed $e) {
                    $result['records'][] = ['uuid' => $target['uuid'], 'ok' => false, 'error' => self::message($e), 'title' => $this->titleOf($rt, $target['uuid'])];
                    $failed++;
                }
                DB::table('bulk_operations')->where('id', $opId)->update([
                    'progress' => (int) floor(100 * ($n + 1) / max(1, count($targets))), 'affected_count' => $affected, 'failed_count' => $failed,
                    'result' => json_encode($result, JSON_UNESCAPED_UNICODE), 'updated_at' => Carbon::now('UTC')->format('Y-m-d H:i:s.u'),
                ]);
            }
            $cancelled = DB::table('bulk_operations')->where('id', $opId)->value('status') === 'cancelled';
            DB::table('bulk_operations')->where('id', $opId)->update([
                'status' => $cancelled ? 'cancelled' : ($failed > 0 ? 'completed_with_errors' : 'completed'), 'progress' => 100,
                'finished_at' => Carbon::now('UTC')->format('Y-m-d H:i:s.u'),
            ]);
            $this->audit->record('action.finished', 'data', null, 'action', (int) $action->id, ['action' => $action->key, 'affected' => $affected, 'failed' => $failed], $user->id, null, $rt->form->id);
            if ($background) {
                $this->inApp->notify($user->id, 'action.finished', $failed > 0 ? 'actions.notify.finished_with_errors' : 'actions.notify.finished',
                    ['name' => $label, 'count' => $affected, 'failed' => $failed], '/app/'.$rt->form->uuid, ['run' => $op->uuid], ['form_id' => $rt->form->id]);
            }
        } catch (Throwable $e) {
            $retryable
                ? DB::table('bulk_operations')->where('id', $opId)->update(['status' => 'queued', 'error' => mb_substr($e->getMessage(), 0, 1000)])
                : $this->fail($opId, $e->getMessage());
            throw $e;
        } finally {
            app()->setLocale($previousLocale);
        }
    }

    /** Marks a run failed (its job gave up, or a run in the request broke). */
    public function fail(int $opId, string $error): void
    {
        DB::table('bulk_operations')->where('id', $opId)->whereNotIn('status', ['completed', 'completed_with_errors', 'cancelled'])
            ->update(['status' => 'failed', 'error' => mb_substr($error, 0, 1000), 'finished_at' => Carbon::now('UTC')->format('Y-m-d H:i:s.u')]);
    }

    /** @return array<string, mixed> a run as its starter sees it */
    public function present(int $opId, User $user): array
    {
        $op = DB::table('bulk_operations')->where('id', $opId)->first();
        abort_if($op === null || (int) $op->created_by !== $user->id, 404);
        $result = json_decode((string) ($op->result ?? 'null'), true) ?? ['records' => [], 'outputs' => []];

        return [
            'uuid' => strtolower((string) $op->uuid), 'status' => $op->status, 'progress' => (int) $op->progress,
            'count' => (int) $op->preview_count, 'affected' => (int) $op->affected_count, 'failed' => (int) $op->failed_count,
            'errors' => array_values(array_filter($result['records'], static fn ($r) => ! $r['ok'])),
            'outputs' => $result['outputs'], 'error' => $op->status === 'failed' ? __('actions.run_failed') : null,
            'message' => $op->status === 'completed' && $op->action_id !== null ? $this->translator->get('action', (int) $op->action_id, 'success_message') : null,
        ];
    }

    public function cancel(string $uuid, User $user): void
    {
        DB::table('bulk_operations')->where('uuid', strtolower($uuid))->where('created_by', $user->id)->whereIn('status', ['queued', 'running'])->update(['status' => 'cancelled']);
    }

    /**
     * @param  array{uuid: string, row_version: int|null}  $target
     * @param  array<string, mixed>  $inputs
     * @return list<array<string, mixed>>
     */
    private function builtin(object $action, FormRuntime $rt, User $user, array $target, array $inputs, string $key, ?int $justificationId): array
    {
        $found = $this->store->find($rt, $target['uuid']) ?? throw new RecordException(404, 'not_found', __('records.not_found'));
        $version = $target['row_version'] ?? $found['row_version'];

        switch ($action->builtin) {
            case 'bulk_update':
                $this->pipeline->update($rt, $user, $target['uuid'], $version, (array) $inputs['values'], $key, 'action', null, $justificationId);

                return [];
            case 'bulk_status':
                $this->steps->movable($rt, $found, (string) $inputs['transition']);
                $this->pipeline->transition($rt, $user, $target['uuid'], (string) $inputs['transition'], $version, ($inputs['comment'] ?? null) === null ? [] : ['comment' => $inputs['comment']], $key, 'action', $justificationId);

                return [];
            case 'bulk_delete':
                $this->pipeline->delete($rt, $user, $target['uuid'], $version, $key, null, 'delete', $justificationId);

                return [];
            case 'bulk_reassign':
                if ($target['row_version'] !== null && $target['row_version'] !== $found['row_version']) {
                    throw new RecordException(409, 'conflict', __('records.conflict'));
                }
                $this->steps->assignRecord((array) $inputs['assignee'], $rt, $user, $found, $justificationId);

                return [];
            case 'duplicate':
                return $this->duplicate($rt, $user, $found, $key);
            default:
                throw new StepFailed(__('actions.not_runnable'));
        }
    }

    /** Developer extensions at onAction (ADR-0051): after the action's work on one record, in a transaction of their own. */
    private function onAction(FormRuntime $rt, User $user, string $uuid, string $key): void
    {
        $hooks = app(HookRunner::class);
        if (! $hooks->has($rt, 'onAction')) {
            return;
        }
        $record = $this->store->find($rt, $uuid);
        if ($record === null) {
            return;
        }
        try {
            DB::transaction(static fn () => $hooks->run('onAction', $rt, $user, $record['values'], null, $uuid, ['action' => $key]));
        } finally {
            $hooks->flush();
        }
    }

    /** A record's title for a run's report (its number, else its title, else "Untitled record"). */
    private function titleOf(FormRuntime $rt, string $uuid): string
    {
        $title = app(RecycleBin::class)->title($rt, DB::table($rt->table)->where('uuid', $uuid)->first());

        return $title !== '' ? $title : __('records.untitled');
    }

    /**
     * A copy of a record: the values the user may edit, without files,
     * numbers the system assigns, or values that must stay unique.
     *
     * @param  array<string, mixed>  $found
     * @return list<array<string, mixed>>
     */
    private function duplicate(FormRuntime $rt, User $user, array $found, string $key): array
    {
        $values = [];
        foreach ($found['values'] as $k => $v) {
            $uuid = $rt->keys[$k] ?? null;
            $f = $uuid === null ? null : $rt->fields[$uuid];
            if ($f !== null && (in_array($rt->type($f)?->storage, ['file', 'files'], true) || in_array($f['type'], ['autonumber', 'formula', 'signature'], true) || ($f['storage']['unique'] ?? false))) {
                continue;
            }
            if ($f === null && isset($rt->repeaterKeys[$k])) {
                $rows = [];
                foreach ((array) $v as $row) {
                    $row = (array) $row;
                    unset($row['_id'], $row['id'], $row['uuid']);
                    $rows[] = array_filter($row, fn ($cell, $ck) => ! in_array($rt->type($this->rowField($rt, (string) $k, (string) $ck) ?? [])?->storage, ['file', 'files'], true), ARRAY_FILTER_USE_BOTH);
                }
                $v = $rows;
            }
            $values[$k] = $v;
        }
        $created = $this->pipeline->create($rt, $user, $values, [], $key, 'action');

        return [['type' => 'open', 'url' => '/app/'.$rt->form->uuid.'/'.$created['uuid'].'/edit']];
    }

    /** @return array<string, mixed>|null */
    private function rowField(FormRuntime $rt, string $repeaterKey, string $key): ?array
    {
        foreach ($rt->rowFields((string) ($rt->repeaterKeys[$repeaterKey] ?? '')) as $f) {
            if ($f['key'] === $key) {
                return $f;
            }
        }

        return null;
    }

    /**
     * The strictest justification over the run's records, and the change used
     * to word the prompt.
     *
     * @param  list<array{uuid: string, row_version: int|null}>  $targets
     * @param  array<string, mixed>  $inputs
     * @return array{0: array<string, mixed>|null, 1: array<string, mixed>}
     */
    private function requirement(object $action, FormRuntime $rt, User $user, array $targets, array $inputs): array
    {
        $fields = [];
        $transitions = [];
        $reassigns = false;
        if ($action->kind === 'custom') {
            foreach (DB::table('action_steps')->where('action_id', $action->id)->get(['type', 'config']) as $s) {
                $c = json_decode((string) $s->config, true) ?: [];
                if ($s->type === 'update_fields') {
                    foreach ((array) ($c['set'] ?? []) as $item) {
                        $k = $rt->fields[$item['field']]['key'] ?? null;
                        if ($k !== null) {
                            $fields[] = $k;
                        }
                    }
                }
                if ($s->type === 'change_status' && ($id = DB::table('transitions')->where('uuid', $c['transition'] ?? '')->value('id')) !== null) {
                    $transitions[] = (int) $id;
                }
                $reassigns = $reassigns || $s->type === 'assign';
            }
        } elseif ($action->builtin === 'bulk_update') {
            $fields = array_keys((array) ($inputs['values'] ?? []));
        } elseif ($action->builtin === 'bulk_reassign') {
            $reassigns = true;
        } elseif ($action->builtin === 'bulk_status' && isset($inputs['transition'])) {
            $id = DB::table('transitions')->where('uuid', strtolower((string) $inputs['transition']))->value('id');
            $transitions = $id === null ? [] : [(int) $id];
        }
        $req = null;
        $change = [];
        foreach ($targets as $target) {
            $found = $this->store->find($rt, $target['uuid'], $action->builtin === 'bulk_delete');
            if ($found === null) {
                continue;
            }
            $base = ['status' => $found['system']['status_id'] ?? null, 'values' => $found['values'], 'old' => $found['values']];
            $change = $base + ['fields' => $fields, 'action' => (int) $action->id, 'actionUuid' => strtolower((string) $action->uuid), 'actionLevel' => $action->justification_level];
            $req = $this->gate->strictest($req, $this->gate->requirement($rt, $user, self::context($action), $change));
            // A bulk built-in also answers to its own level and to rules aimed at the action.
            if (self::context($action) === 'bulk') {
                $req = $this->gate->strictest($req, $this->gate->requirement($rt, $user, 'action', ['fields' => []] + $change));
            }
            if ($fields !== [] && $action->kind === 'builtin') {
                $req = $this->gate->strictest($req, $this->gate->requirement($rt, $user, 'edit', $base + ['fields' => $fields]));
            }
            foreach ($transitions as $t) {
                $req = $this->gate->strictest($req, $this->gate->requirement($rt, $user, 'transition', $base + ['fields' => [], 'transition' => $t]));
            }
            if ($reassigns && app(AssignmentService::class)->hasActive($rt->form->id, $found['id'])) {
                $req = $this->gate->strictest($req, $this->gate->requirement($rt, $user, 'reassign', $base));
            }
            if ($action->builtin === 'bulk_delete') {
                $req = $this->gate->strictest($req, $this->gate->requirement($rt, $user, 'delete', $base));
            }
        }

        return [$req, $change];
    }

    /** @return array<string, mixed>|null what a built-in asks before it runs (transitions only those the user may perform) */
    private function inputs(object $action, FormRuntime $rt, ?User $user): ?array
    {
        if ($action->builtin === 'bulk_update') {
            $fields = [];
            foreach ($rt->mainFields() as $f) {
                if (! $rt->isStored($f) || in_array($f['type'], ['autonumber', 'formula', 'signature', 'file', 'files', 'image'], true) || ($f['storage']['unique'] ?? false)) {
                    continue;
                }
                $fields[] = [
                    'key' => $f['key'], 'type' => $f['type'], 'label' => $this->translator->labelOf($f['i18n']['label'] ?? null, (string) $f['key']),
                    'options' => array_map(fn ($o) => ['value' => $o['value'], 'label' => $this->translator->labelOf($o['i18n']['label'] ?? null, (string) $o['value'])], (array) ($f['options']['static'] ?? [])),
                ];
            }

            return ['fields' => $fields];
        }
        if ($action->builtin === 'bulk_reassign') {
            return ['assignees' => ['user', 'role', 'department']];
        }
        if ($action->builtin === 'bulk_status') {
            $wf = WorkflowRuntime::for($rt);
            $fixed = ActionDocument::settingsOf($action)['transition'] ?? null;
            if ($fixed !== null) {
                $t = $wf->transitions[$fixed] ?? null;

                return ['fixed' => $t === null ? null : [
                    'uuid' => $fixed, 'label' => WorkflowRuntime::label($t), 'comment' => $t['comment'] ?? 'none',
                    'from' => $t['fromId'] === null ? null : WorkflowRuntime::label($wf->status($t['fromId']) ?? []), 'to' => WorkflowRuntime::label($wf->status($t['toId']) ?? []),
                ]];
            }

            return ['transitions' => array_values(array_map(static fn (array $t) => [
                'uuid' => strtolower((string) $t['uuid']), 'label' => WorkflowRuntime::label($t), 'comment' => $t['comment'] ?? 'none',
            ], array_filter($wf->transitions, fn (array $t) => $user === null || $this->access->allows($user, 'transition.'.strtolower((string) $t['uuid']).'.perform'))))];
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $inputs
     * @return array<string, mixed>
     */
    private function checkInputs(object $action, FormRuntime $rt, array $inputs): array
    {
        if ($action->builtin === 'bulk_update') {
            $values = (array) ($inputs['values'] ?? []);
            $allowed = array_column($this->inputs($action, $rt, null)['fields'] ?? [], 'key');
            if ($values === [] || array_diff(array_keys($values), $allowed) !== []) {
                throw ValidationException::withMessages(['inputs.values' => __('actions.bad_bulk_values')]);
            }

            return ['values' => $values];
        }
        if ($action->builtin === 'bulk_reassign') {
            $type = $inputs['assignee']['type'] ?? null;
            $ref = $inputs['assignee']['uuid'] ?? null;
            $table = match ($type) {
                'user' => 'users', 'role' => 'roles', 'department' => 'departments', default => null,
            };
            if ($table === null || ! is_string($ref) || ! Str::isUuid($ref) || ! DB::table($table)->where('uuid', strtolower($ref))->exists()) {
                throw ValidationException::withMessages(['inputs.assignee' => __('actions.bad_assignee')]);
            }

            return ['assignee' => ['type' => $type, 'uuid' => strtolower($ref)]];
        }
        if ($action->builtin === 'bulk_status') {
            $t = ActionDocument::settingsOf($action)['transition'] ?? strtolower((string) ($inputs['transition'] ?? ''));
            if (! DB::table('transitions')->where('form_id', $rt->form->id)->where('uuid', $t)->whereNull('archived_at')->exists()) {
                throw ValidationException::withMessages(['inputs.transition' => __('actions.unknown_transition')]);
            }
            // One comment for every record, when the transition takes one.
            $mode = WorkflowRuntime::for($rt)->transitions[$t]['comment'] ?? 'none';
            $comment = is_string($inputs['comment'] ?? null) ? trim(mb_substr($inputs['comment'], 0, 5000)) : '';
            if ($mode === 'mandatory' && $comment === '') {
                throw ValidationException::withMessages(['inputs.comment' => __('workflow.comment_required')]);
            }

            return ['transition' => $t, 'comment' => $mode === 'none' || $comment === '' ? null : $comment];
        }

        return [];
    }

    /** The justification context of a run: bulk operations are `bulk`, everything else `action`. */
    private static function context(object $action): string
    {
        return in_array($action->builtin, self::BULK, true) ? 'bulk' : 'action';
    }

    private function queued(object $action, int $count): bool
    {
        return match ($action->run_mode) {
            'queued' => true,
            'sync' => false,
            default => $count > ($action->queue_threshold ?? (int) $this->settings->get('actions', 'queue_threshold')),
        };
    }

    private function maxRecords(object $action): int
    {
        return $action->max_records === null ? (int) $this->settings->get('actions', 'max_records') : (int) $action->max_records;
    }

    private function recordId(FormRuntime $rt, string $uuid): ?int
    {
        $id = DB::table($rt->table)->where('uuid', $uuid)->value('id');

        return $id === null ? null : (int) $id;
    }

    private function localeOf(User $user): string
    {
        $locale = DB::table('user_preferences')->where('user_id', $user->id)->value('locale');

        return is_string($locale) && in_array($locale, $this->translator->enabledLocales(), true) ? $locale : $this->translator->defaultLocale();
    }

    /** A failure's message for the result list (validation errors joined). */
    public static function message(Throwable $e): string
    {
        if ($e instanceof ValidationException) {
            return implode(' ', array_map(static fn ($m) => is_array($m) ? implode(' ', $m) : (string) $m, $e->errors()));
        }
        if ($e instanceof RecordException && isset($e->payload['errors']) && is_array($e->payload['errors'])) {
            $first = [];
            foreach ($e->payload['errors'] as $msgs) {
                foreach ((array) $msgs as $m) {
                    $first[] = is_array($m) ? (string) ($m['message'] ?? '') : (string) $m;
                }
            }

            return trim($e->getMessage().' '.implode(' ', array_slice(array_filter($first), 0, 3)));
        }

        return $e->getMessage();
    }
}
