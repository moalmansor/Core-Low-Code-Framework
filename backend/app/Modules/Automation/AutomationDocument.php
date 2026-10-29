<?php

declare(strict_types=1);

namespace App\Modules\Automation;

use App\Expressions\Checking\TypeChecker;
use App\Expressions\StaticError;
use App\Modules\Access\AccessResolver;
use App\Modules\Actions\ActionDocument;
use App\Modules\Audit\AuditWriter;
use App\Modules\Core\I18n\Translator;
use App\Modules\Forms\Conditions\OwnedConditions;
use App\Modules\Forms\Definition\DefinitionCompiler;
use App\Modules\Forms\Draft\DraftRepository;
use App\Modules\Forms\Draft\DraftValidator;
use App\Modules\Forms\Models\Form;
use App\Modules\Identity\Models\User;
use App\Modules\Records\Runtime\FormRuntime;
use App\Modules\Records\Runtime\FormRuntimes;
use App\Modules\Scheduling\ScheduledTasks;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * An automation of a form (specification §4.31, architecture §19.9,
 * ADR-0043): one or more triggers, an optional condition, a sequence of
 * steps from the action library plus waits, and its controls (run as,
 * concurrency, retry policy, record limits, loop protection). Saved whole
 * with a concurrency hash. Schedule triggers own a scheduled task.
 */
final class AutomationDocument
{
    /** Triggers an admin can choose. */
    public const TRIGGERS = ['record_created', 'record_updated', 'record_deleted', 'field_changed', 'status_changed', 'condition_true', 'schedule', 'date_reached', 'watched_folder', 'inbound_webhook', 'manual'];

    /** Triggers that start one run for one record, caused by someone's change. */
    public const EVENT_TRIGGERS = ['record_created', 'record_updated', 'record_deleted', 'field_changed', 'status_changed', 'condition_true'];

    /** Triggers whose run covers every record that meets the condition. */
    public const SET_TRIGGERS = ['schedule', 'manual'];

    public const STEPS = ['update_fields', 'change_status', 'assign', 'create_linked_record', 'send_email', 'send_notification', 'generate_document', 'run_download', 'call_webhook', 'wait_delay', 'wait_condition'];

    /** What can still run once the record is deleted. */
    public const AFTER_DELETE_STEPS = ['send_email', 'send_notification', 'call_webhook'];

    public const FREQUENCIES = ['hourly', 'daily', 'weekly', 'monthly', 'cron'];

    public const MAX_STEPS = 20;

    public const MAX_TRIGGERS = 10;

    public const MAX_MINUTES = 525600;

    public function __construct(
        private readonly Translator $translator,
        private readonly OwnedConditions $conditions,
        private readonly FormRuntimes $runtimes,
        private readonly DraftRepository $drafts,
        private readonly DraftValidator $draftValidator,
        private readonly ActionDocument $actions,
        private readonly ScheduledTasks $tasks,
        private readonly AuditWriter $audit,
        private readonly AccessResolver $access,
    ) {}

    public function row(string $uuid): ?object
    {
        return Str::isUuid($uuid) ? DB::table('automations')->where('uuid', strtolower($uuid))->first() : null;
    }

    /**
     * The automations of a form, or of every form, for lists.
     *
     * @return list<array<string, mixed>>
     */
    public function list(?Form $form): array
    {
        $q = DB::table('automations')->orderBy('key');
        if ($form !== null) {
            $q->where('form_id', $form->id);
        }
        $rows = $q->get();
        $ids = $rows->pluck('id')->map(static fn ($v) => (int) $v)->all();
        $names = $this->translator->many('automation', $ids, ['name']);
        $triggers = DB::table('automation_triggers')->whereIn('automation_id', $ids ?: [0])->get()->groupBy('automation_id');
        $steps = DB::table('automation_steps')->whereIn('automation_id', $ids ?: [0])->selectRaw('automation_id, count(*) as n')->groupBy('automation_id')->pluck('n', 'automation_id');
        $forms = DB::table('forms')->whereIn('id', $rows->pluck('form_id')->filter()->unique()->all() ?: [0])->get(['id', 'uuid', 'key'])->keyBy('id');
        $formNames = $this->translator->many('form', $forms->keys()->map(static fn ($v) => (int) $v)->all(), ['name']);
        $last = DB::table('automation_runs')->whereIn('automation_id', $ids ?: [0])->where('is_test', false)
            ->selectRaw('automation_id, max(id) as id')->groupBy('automation_id')->pluck('id', 'automation_id');
        $lastRuns = DB::table('automation_runs')->whereIn('id', $last->values()->all() ?: [0])->get(['id', 'automation_id', 'status', 'created_at'])->keyBy('automation_id');

        return $rows->map(function ($a) use ($names, $triggers, $steps, $forms, $formNames, $lastRuns) {
            $f = $a->form_id === null ? null : ($forms[$a->form_id] ?? null);
            $run = $lastRuns[$a->id] ?? null;

            return [
                'uuid' => strtolower((string) $a->uuid),
                'key' => $a->key,
                'name' => $names[(int) $a->id]['name'] ?? Translator::humanize((string) $a->key),
                'enabled' => (bool) $a->is_enabled,
                'form' => $f === null ? null : ['uuid' => strtolower((string) $f->uuid), 'name' => $formNames[(int) $f->id]['name'] ?? Translator::humanize((string) $f->key)],
                'triggers' => ($triggers[$a->id] ?? collect())->pluck('type')->values()->all(),
                'steps' => (int) ($steps[$a->id] ?? 0),
                'lastRun' => $run === null ? null : ['status' => $run->status, 'at' => Carbon::parse((string) $run->created_at, 'UTC')->toIso8601ZuluString()],
            ];
        })->values()->all();
    }

    /** @return array<string, mixed> */
    public function load(object $a): array
    {
        $steps = DB::table('automation_steps')->where('automation_id', $a->id)->orderBy('sort_order')->orderBy('id')->get();
        $triggers = DB::table('automation_triggers')->where('automation_id', $a->id)->orderBy('id')->get();
        $asts = $this->conditions->many([$a->condition_id, ...$steps->pluck('condition_id')->all()]);
        $tr = $this->translator->allMany('automation', [(int) $a->id])[(int) $a->id] ?? [];
        $retry = json_decode((string) $a->retry_policy, true) ?: [];
        $fieldUuids = DB::table('fields')->whereIn('id', [...$triggers->pluck('field_id')->all(), ...$triggers->pluck('date_field_id')->all(), 0])->pluck('uuid', 'id');
        $statusUuids = DB::table('statuses')->whereIn('id', [...$triggers->pluck('from_status_id')->all(), ...$triggers->pluck('to_status_id')->all(), 0])->pluck('uuid', 'id');
        $uuidOf = static fn ($map, $id) => $id === null || ! isset($map[$id]) ? null : strtolower((string) $map[$id]);

        return [
            'uuid' => strtolower((string) $a->uuid),
            'key' => $a->key,
            'i18n' => ['name' => (object) ($tr['name'] ?? []), 'description' => (object) ($tr['description'] ?? [])],
            'enabled' => (bool) $a->is_enabled,
            'triggers' => $triggers->map(function ($t) use ($uuidOf, $fieldUuids, $statusUuids) {
                $c = json_decode((string) ($t->config ?? 'null'), true) ?: [];

                return [
                    'uuid' => strtolower((string) $t->uuid),
                    'type' => $t->type,
                    'field' => $uuidOf($fieldUuids, $t->field_id),
                    'fromStatus' => $uuidOf($statusUuids, $t->from_status_id),
                    'toStatus' => $uuidOf($statusUuids, $t->to_status_id),
                    'schedule' => $t->type === 'schedule' ? [
                        'frequency' => $c['frequency'] ?? 'cron', 'time' => $c['time'] ?? null, 'weekday' => $c['weekday'] ?? null, 'day' => $c['day'] ?? null,
                        'minute' => $c['minute'] ?? null, 'cron' => ($c['frequency'] ?? 'cron') === 'cron' ? $t->cron_expression : null, 'timezone' => $t->timezone,
                    ] : null,
                    'dateField' => $uuidOf($fieldUuids, $t->date_field_id),
                    'offsetMinutes' => $t->offset_minutes === null ? null : (int) $t->offset_minutes,
                    'folder' => $t->watched_path,
                    'fileField' => $c['fileField'] ?? null,
                    'nameField' => $c['nameField'] ?? null,
                    'inboundEndpoint' => $t->inbound_endpoint_id === null ? null : strtolower((string) DB::table('inbound_endpoints')->where('id', $t->inbound_endpoint_id)->value('uuid')),
                ];
            })->values()->all(),
            'condition' => $a->condition_id === null ? null : ($asts[(int) $a->condition_id] ?? null),
            'steps' => $steps->map(fn ($s) => [
                'uuid' => strtolower((string) $s->uuid),
                'type' => $s->type,
                'config' => $this->actions->presentConfig($s->type, json_decode((string) $s->config, true) ?: []),
                'condition' => $s->condition_id === null ? null : ($asts[(int) $s->condition_id] ?? null),
                'onFailure' => $s->on_failure,
            ])->values()->all(),
            'runAs' => $a->run_as,
            'runAsUser' => $a->run_as_user_id === null ? null : strtolower((string) DB::table('users')->where('id', $a->run_as_user_id)->value('uuid')),
            'concurrency' => (int) $a->concurrency_limit,
            'retry' => ['maxAttempts' => (int) ($retry['max_attempts'] ?? 1), 'backoffSeconds' => array_map('intval', (array) ($retry['backoff_seconds'] ?? []))],
            'maxRecords' => (int) $a->max_records_per_run,
            'confirmAbove' => $a->confirm_above === null ? null : (int) $a->confirm_above,
            'maxChainDepth' => (int) $a->max_chain_depth,
        ];
    }

    public function hash(object $a): string
    {
        return DefinitionCompiler::hash($this->load($a));
    }

    /**
     * Creates (`$existing` null) or replaces an automation of `$form`.
     *
     * @param  array<string, mixed>  $doc
     */
    public function save(Form $form, ?object $existing, array $doc, User $actor): object
    {
        $userId = $actor->id;
        $rt = $this->runtimes->forForm($form) ?? throw ValidationException::withMessages(['automation' => __('automations.publish_first')]);
        $d = $this->validate($form, $rt, $existing, $doc, $actor);

        return DB::transaction(function () use ($form, $existing, $d, $userId): object {
            $now = Carbon::now('UTC')->format('Y-m-d H:i:s.u');
            $runAsUser = $d['runAsUser'] === null ? null : (int) DB::table('users')->where('uuid', $d['runAsUser'])->value('id');
            $row = [
                'updated_at' => $now, 'updated_by' => $userId, 'key' => $d['key'], 'is_enabled' => $d['enabled'], 'run_as' => $d['runAs'], 'run_as_user_id' => $runAsUser,
                'concurrency_limit' => $d['concurrency'], 'retry_policy' => json_encode(['max_attempts' => $d['retry']['maxAttempts'], 'backoff_seconds' => $d['retry']['backoffSeconds']]),
                'max_records_per_run' => $d['maxRecords'], 'confirm_above' => $d['confirmAbove'], 'max_chain_depth' => $d['maxChainDepth'],
            ];
            if ($existing === null) {
                $id = (int) DB::table('automations')->insertGetId($row + [
                    'uuid' => (string) Str::uuid7(), 'organization_id' => $form->organization_id, 'created_at' => $now, 'created_by' => $userId,
                    'form_id' => $form->id, 'application_id' => $form->application_id,
                ]);
                $conditionId = null;
            } else {
                $id = (int) $existing->id;
                DB::table('automations')->where('id', $id)->update($row);
                $conditionId = $existing->condition_id === null ? null : (int) $existing->condition_id;
            }
            $newCondition = $this->conditions->put($form->id, 'automation', $id, $d['condition'], $conditionId);
            if ($newCondition !== $conditionId) {
                DB::table('automations')->where('id', $id)->update(['condition_id' => $newCondition]);
            }
            $this->translator->syncObjects('automation', [$id => ['name' => $d['i18n']['name'], 'description' => $d['i18n']['description']]]);
            $this->saveTriggers($form, $id, $d, $now);
            $this->saveSteps($form, $id, $d['steps'], $now);
            $this->audit->record($existing === null ? 'automation.created' : 'automation.updated', 'config', null, 'automation', $id, [
                'key' => $d['key'], 'enabled' => $d['enabled'], 'triggers' => array_column($d['triggers'], 'type'), 'steps' => array_column($d['steps'], 'type'),
            ], $userId, null, $form->id);

            return DB::table('automations')->where('id', $id)->first() ?? throw new \LogicException('automation vanished');
        });
    }

    /** Turns an automation on or off without touching the rest. */
    public function enable(object $a, bool $enabled, int $userId): void
    {
        DB::transaction(function () use ($a, $enabled, $userId): void {
            DB::table('automations')->where('id', $a->id)->update(['is_enabled' => $enabled, 'updated_at' => Carbon::now('UTC')->format('Y-m-d H:i:s.u'), 'updated_by' => $userId]);
            foreach (DB::table('automation_triggers')->where('automation_id', $a->id)->whereNotNull('scheduled_task_id')->pluck('scheduled_task_id') as $task) {
                DB::table('scheduled_tasks')->where('id', $task)->update(['is_enabled' => $enabled]);
            }
            $this->audit->record($enabled ? 'automation.enabled' : 'automation.disabled', 'config', null, 'automation', (int) $a->id, ['key' => $a->key], $userId, null, $a->form_id === null ? null : (int) $a->form_id);
        });
    }

    /** Removes an automation with its triggers, steps and run history (the audit log keeps that it existed). */
    public function delete(object $a, int $userId): void
    {
        DB::transaction(function () use ($a, $userId): void {
            foreach (DB::table('automation_triggers')->where('automation_id', $a->id)->get(['id', 'scheduled_task_id']) as $t) {
                DB::table('automation_triggers')->where('id', $t->id)->delete();
                if ($t->scheduled_task_id !== null) {
                    DB::table('scheduled_tasks')->where('id', $t->scheduled_task_id)->delete();
                }
            }
            foreach (DB::table('automation_steps')->where('automation_id', $a->id)->get(['id', 'condition_id']) as $s) {
                DB::table('automation_steps')->where('id', $s->id)->delete();
                $this->conditions->forget($s->condition_id === null ? null : (int) $s->condition_id);
            }
            $runs = DB::table('automation_runs')->where('automation_id', $a->id)->pluck('id')->all();
            foreach (array_chunk($runs, 500) as $chunk) {
                DB::table('download_jobs')->whereIn('automation_run_id', $chunk)->update(['automation_run_id' => null]);
                // Runs of other automations that this one started keep their place in history, without the link.
                DB::table('automation_runs')->whereIn('parent_run_id', $chunk)->update(['parent_run_id' => null]);
            }
            foreach (array_chunk($runs, 500) as $chunk) {
                DB::table('automation_runs')->whereIn('id', $chunk)->delete();
            }
            DB::table('automations')->where('id', $a->id)->update(['condition_id' => null]);
            $this->conditions->forget($a->condition_id === null ? null : (int) $a->condition_id);
            $this->translator->forget('automation', (int) $a->id);
            DB::table('automations')->where('id', $a->id)->delete();
            $this->audit->record('automation.deleted', 'config', null, 'automation', (int) $a->id, ['key' => $a->key, 'runs' => count($runs)], $userId, null, $a->form_id === null ? null : (int) $a->form_id);
        });
    }

    /** @param  array<string, mixed>  $d */
    private function saveTriggers(Form $form, int $automationId, array $d, string $now): void
    {
        $kept = [];
        $name = $this->translator->labelOf($d['i18n']['name'], (string) $d['key']);
        foreach ($d['triggers'] as $t) {
            $existing = DB::table('automation_triggers')->where('automation_id', $automationId)->where('uuid', $t['uuid'])->first();
            $row = [
                'updated_at' => $now, 'type' => $t['type'], 'form_id' => $form->id,
                'field_id' => $t['fieldId'], 'from_status_id' => $t['fromStatusId'], 'to_status_id' => $t['toStatusId'],
                'cron_expression' => $t['cron'], 'timezone' => $t['timezone'], 'date_field_id' => $t['dateFieldId'], 'offset_minutes' => $t['offsetMinutes'],
                'watched_disk' => $t['folder'] === null ? null : 'watched', 'watched_path' => $t['folder'],
                'config' => $t['config'] === [] ? null : json_encode($t['config']),
                'inbound_endpoint_id' => $t['inboundEndpointId'],
            ];
            if ($existing === null) {
                $id = (int) DB::table('automation_triggers')->insertGetId($row + ['uuid' => $t['uuid'], 'automation_id' => $automationId, 'created_at' => $now]);
                $taskId = null;
                // A date or folder trigger starts looking from now, never at the past.
                DB::table('automation_triggers')->where('id', $id)->update(['last_fired_at' => $now]);
            } else {
                $id = (int) $existing->id;
                DB::table('automation_triggers')->where('id', $id)->update($row);
                $taskId = $existing->scheduled_task_id === null ? null : (int) $existing->scheduled_task_id;
            }
            if ($t['type'] === 'schedule') {
                $task = $this->tasks->put($taskId, 'automation', 'automation_trigger', $id, $name, (string) $t['cron'], (string) $t['timezone'], $d['enabled']);
                if ($task !== $taskId) {
                    DB::table('automation_triggers')->where('id', $id)->update(['scheduled_task_id' => $task]);
                }
            } elseif ($taskId !== null) {
                DB::table('automation_triggers')->where('id', $id)->update(['scheduled_task_id' => null]);
                DB::table('scheduled_tasks')->where('id', $taskId)->delete();
            }
            $kept[] = $id;
        }
        foreach (DB::table('automation_triggers')->where('automation_id', $automationId)->whereNotIn('id', $kept ?: [0])->get(['id', 'scheduled_task_id']) as $gone) {
            DB::table('automation_triggers')->where('id', $gone->id)->delete();
            if ($gone->scheduled_task_id !== null) {
                DB::table('scheduled_tasks')->where('id', $gone->scheduled_task_id)->delete();
            }
        }
    }

    /** @param  list<array<string, mixed>>  $steps */
    private function saveSteps(Form $form, int $automationId, array $steps, string $now): void
    {
        $kept = [];
        foreach ($steps as $j => $s) {
            $existing = DB::table('automation_steps')->where('automation_id', $automationId)->where('uuid', $s['uuid'])->first();
            $config = $s['type'] === 'call_webhook' ? $this->actions->sealWebhook($s['config'], $existing) : $s['config'];
            $row = ['updated_at' => $now, 'sort_order' => $j, 'type' => $s['type'], 'config' => json_encode((object) $config, JSON_UNESCAPED_UNICODE), 'on_failure' => $s['onFailure']];
            if ($existing === null) {
                $id = (int) DB::table('automation_steps')->insertGetId($row + ['uuid' => $s['uuid'], 'automation_id' => $automationId, 'created_at' => $now, 'condition_id' => null]);
                $conditionId = null;
            } else {
                $id = (int) $existing->id;
                DB::table('automation_steps')->where('id', $id)->update($row);
                $conditionId = $existing->condition_id === null ? null : (int) $existing->condition_id;
            }
            $newCondition = $this->conditions->put($form->id, 'automation_step', $id, $s['condition'], $conditionId);
            if ($newCondition !== $conditionId) {
                DB::table('automation_steps')->where('id', $id)->update(['condition_id' => $newCondition]);
            }
            $kept[] = $id;
        }
        foreach (DB::table('automation_steps')->where('automation_id', $automationId)->whereNotIn('id', $kept ?: [0])->get(['id', 'condition_id']) as $gone) {
            DB::table('automation_steps')->where('id', $gone->id)->delete();
            $this->conditions->forget($gone->condition_id === null ? null : (int) $gone->condition_id);
        }
    }

    /**
     * @param  array<string, mixed>  $a
     * @return array<string, mixed> normalized
     */
    private function validate(Form $form, FormRuntime $rt, ?object $existing, array $a, User $actor): array
    {
        $errors = [];
        $resolver = $this->draftValidator->resolver($this->drafts->normalize($this->drafts->load($form)));
        $default = $this->translator->defaultLocale();
        $boolean = function (mixed $ast, string $path) use ($resolver, &$errors): ?array {
            if ($ast === null) {
                return null;
            }
            if (! is_array($ast)) {
                $errors[$path][] = __('automations.bad_condition');

                return null;
            }
            try {
                TypeChecker::check($ast, $resolver, 'boolean');
            } catch (StaticError $e) {
                $errors[$path][] = $e->getMessage();
            }

            return $ast;
        };

        $key = (string) ($a['key'] ?? '');
        if (preg_match('/^[a-z][a-z0-9_]{0,47}$/', $key) !== 1) {
            $errors['key'][] = __('automations.bad_key');
        } elseif (DB::table('automations')->where('organization_id', $form->organization_id)->where('key', $key)->when($existing !== null, static fn ($q) => $q->where('id', '!=', $existing->id))->exists()) {
            $errors['key'][] = __('automations.key_taken');
        }
        $name = $this->texts($a['i18n']['name'] ?? [], 120);
        if (($name[$default] ?? '') === '') {
            $errors["i18n.name.{$default}"][] = __('automations.name_required');
        }
        $condition = $boolean($a['condition'] ?? null, 'condition');

        // Triggers.
        $rawTriggers = array_values((array) ($a['triggers'] ?? []));
        if ($rawTriggers === []) {
            $errors['triggers'][] = __('automations.triggers_required');
        }
        if (count($rawTriggers) > self::MAX_TRIGGERS) {
            $errors['triggers'][] = __('automations.too_many_triggers', ['max' => self::MAX_TRIGGERS]);
        }
        $statuses = DB::table('statuses')->where('form_id', $form->id)->whereNull('archived_at')->pluck('id', 'uuid')->mapWithKeys(static fn ($id, $u) => [strtolower((string) $u) => (int) $id])->all();
        $triggers = [];
        $seen = [];
        foreach ($rawTriggers as $i => $t) {
            $p = "triggers.{$i}";
            if (! is_array($t) || ! is_string($t['uuid'] ?? null) || ! Str::isUuid($t['uuid']) || isset($seen[strtolower($t['uuid'])])) {
                $errors["{$p}.uuid"][] = __('validation.uuid', ['attribute' => 'uuid']);

                continue;
            }
            $uuid = strtolower($t['uuid']);
            $seen[$uuid] = true;
            if (DB::table('automation_triggers')->where('uuid', $uuid)->when($existing !== null, static fn ($q) => $q->where('automation_id', '!=', $existing->id))->exists()) {
                $errors["{$p}.uuid"][] = __('validation.unique', ['attribute' => 'uuid']);
            }
            $type = $t['type'] ?? null;
            if (! in_array($type, self::TRIGGERS, true)) {
                $errors["{$p}.type"][] = __('automations.bad_trigger');

                continue;
            }
            $out = ['uuid' => $uuid, 'type' => $type, 'fieldId' => null, 'fromStatusId' => null, 'toStatusId' => null, 'cron' => null, 'timezone' => null,
                'dateFieldId' => null, 'offsetMinutes' => null, 'folder' => null, 'config' => [], 'inboundEndpointId' => null];
            switch ($type) {
                case 'field_changed':
                    $field = $this->mainField($rt, $t['field'] ?? null);
                    $field === null || ! $rt->isStored($field) ? $errors["{$p}.field"][] = __('automations.bad_field') : $out['fieldId'] = $this->fieldId($field['uuid']);
                    break;
                case 'status_changed':
                    foreach (['fromStatus' => 'fromStatusId', 'toStatus' => 'toStatusId'] as $in => $col) {
                        $s = $t[$in] ?? null;
                        if ($s !== null && ! isset($statuses[strtolower((string) $s)])) {
                            $errors["{$p}.{$in}"][] = __('automations.bad_status');
                        } elseif ($s !== null) {
                            $out[$col] = $statuses[strtolower((string) $s)];
                        }
                    }
                    if ($statuses === []) {
                        $errors["{$p}.type"][] = __('automations.no_workflow');
                    }
                    break;
                case 'condition_true':
                    if ($a['condition'] ?? null) {
                        break;
                    }
                    $errors["{$p}.type"][] = __('automations.condition_needed');
                    break;
                case 'schedule':
                    [$out['cron'], $out['timezone'], $out['config']] = $this->schedule((array) ($t['schedule'] ?? []), $p, $errors);
                    break;
                case 'date_reached':
                    $field = $this->mainField($rt, $t['dateField'] ?? null);
                    if ($field === null || ! in_array($rt->type($field)?->storage, ['date', 'datetime'], true) || $rt->column($field['uuid']) === null) {
                        $errors["{$p}.dateField"][] = __('automations.bad_date_field');
                    } else {
                        $out['dateFieldId'] = $this->fieldId($field['uuid']);
                    }
                    $offset = $t['offsetMinutes'] ?? 0;
                    if (! is_int($offset) || abs($offset) > self::MAX_MINUTES) {
                        $errors["{$p}.offsetMinutes"][] = __('automations.bad_number', ['min' => -self::MAX_MINUTES, 'max' => self::MAX_MINUTES]);
                    } else {
                        $out['offsetMinutes'] = $offset;
                    }
                    break;
                case 'inbound_webhook':
                    // An incoming webhook of this form: its run starts for the record the call created or updated.
                    $endpoint = is_string($t['inboundEndpoint'] ?? null) && Str::isUuid($t['inboundEndpoint'])
                        ? DB::table('inbound_endpoints')->where('uuid', strtolower($t['inboundEndpoint']))->where('form_id', $form->id)->value('id') : null;
                    $endpoint === null ? $errors["{$p}.inboundEndpoint"][] = __('automations.bad_inbound_endpoint') : $out['inboundEndpointId'] = (int) $endpoint;
                    break;
                case 'watched_folder':
                    $folder = trim((string) ($t['folder'] ?? ''), " \t/");
                    if (preg_match('#^[A-Za-z0-9_\-]+(/[A-Za-z0-9_\-]+){0,7}$#', $folder) !== 1 || mb_strlen($folder) > 200) {
                        $errors["{$p}.folder"][] = __('automations.bad_folder');
                    } else {
                        $out['folder'] = $folder;
                    }
                    $field = $this->mainField($rt, $t['fileField'] ?? null);
                    if ($field === null || ! in_array($rt->type($field)?->storage, ['file', 'files'], true)) {
                        $errors["{$p}.fileField"][] = __('automations.bad_file_field');
                    } else {
                        $out['config'] = ['fileField' => $field['uuid']];
                    }
                    // Optionally, a text field that receives the file's name (forms often require a title).
                    if (($t['nameField'] ?? null) !== null) {
                        $nameField = $this->mainField($rt, $t['nameField']);
                        if ($nameField === null || ! in_array($rt->type($nameField)?->storage, ['string', 'text'], true)) {
                            $errors["{$p}.nameField"][] = __('automations.bad_name_field');
                        } else {
                            $out['config']['nameField'] = $nameField['uuid'];
                        }
                    }
                    break;
            }
            $triggers[] = $out;
        }
        $types = array_column($triggers, 'type');
        $onlyEvents = $types !== [] && array_diff($types, [...self::EVENT_TRIGGERS, 'manual']) === [];

        // Running as.
        $runAs = $a['runAs'] ?? 'specific_user';
        $runAsUser = is_string($a['runAsUser'] ?? null) && Str::isUuid($a['runAsUser']) ? strtolower($a['runAsUser']) : null;
        if (! in_array($runAs, ['triggering_user', 'specific_user'], true)) {
            $errors['runAs'][] = __('validation.in', ['attribute' => 'runAs']);
        } elseif ($runAs === 'triggering_user' && ! $onlyEvents) {
            $errors['runAs'][] = __('automations.run_as_needs_user');
        } elseif ($runAs === 'specific_user' && ($runAsUser === null || ! DB::table('users')->where('uuid', $runAsUser)->where('status', 'active')->whereNull('deleted_at')->exists())) {
            $errors['runAsUser'][] = __('automations.bad_run_as_user');
        } elseif ($runAs === 'specific_user' && $runAsUser !== strtolower((string) $actor->uuid) && ! $this->access->allows($actor, 'system.manage_permissions')) {
            // Acting as someone else is acting with their permissions: only those who manage permissions may set it up.
            $errors['runAsUser'][] = __('automations.run_as_other_needs_permissions');
        }
        // Changing what an automation does while it runs as someone else needs the same right.
        $storedAs = $existing?->run_as_user_id;
        if ($existing !== null && $existing->run_as === 'specific_user' && $storedAs !== null && (int) $storedAs !== $actor->id && ! $this->access->allows($actor, 'system.manage_permissions')) {
            $errors['runAsUser'][] = __('automations.run_as_other_needs_permissions');
        }

        // Steps.
        $rawSteps = array_values((array) ($a['steps'] ?? []));
        if ($rawSteps === []) {
            $errors['steps'][] = __('automations.steps_required');
        }
        if (count($rawSteps) > self::MAX_STEPS) {
            $errors['steps'][] = __('automations.too_many_steps', ['max' => self::MAX_STEPS]);
        }
        $steps = [];
        $stepSeen = [];
        foreach ($rawSteps as $j => $s) {
            $p = "steps.{$j}";
            if (! is_array($s) || ! is_string($s['uuid'] ?? null) || ! Str::isUuid($s['uuid']) || isset($stepSeen[strtolower($s['uuid'])])) {
                $errors["{$p}.uuid"][] = __('validation.uuid', ['attribute' => 'uuid']);

                continue;
            }
            $uuid = strtolower($s['uuid']);
            $stepSeen[$uuid] = true;
            if (DB::table('automation_steps')->where('uuid', $uuid)->when($existing !== null, static fn ($q) => $q->where('automation_id', '!=', $existing->id))->exists()) {
                $errors["{$p}.uuid"][] = __('validation.unique', ['attribute' => 'uuid']);
            }
            $type = $s['type'] ?? null;
            if (! in_array($type, self::STEPS, true)) {
                $errors["{$p}.type"][] = __('validation.in', ['attribute' => 'type']);

                continue;
            }
            if (in_array('record_deleted', $types, true) && ! in_array($type, self::AFTER_DELETE_STEPS, true)) {
                $errors["{$p}.type"][] = __('automations.not_after_delete');
            }
            if (in_array($type, ['wait_delay', 'wait_condition'], true) && array_intersect($types, self::SET_TRIGGERS) !== []) {
                $errors["{$p}.type"][] = __('automations.no_wait_for_sets');
            }
            $c = (array) ($s['config'] ?? []);
            $config = match ($type) {
                'wait_delay' => ['minutes' => $this->minutes($c['minutes'] ?? null, "{$p}.config.minutes", $errors)],
                'wait_condition' => [
                    'condition' => $boolean($c['condition'] ?? null, "{$p}.config.condition") ?? (function () use ($p, &$errors) {
                        $errors["{$p}.config.condition"][] = __('automations.condition_required');

                        return null;
                    })(),
                    'timeoutMinutes' => $this->minutes($c['timeoutMinutes'] ?? null, "{$p}.config.timeoutMinutes", $errors),
                    'onTimeout' => ($c['onTimeout'] ?? 'stop') === 'continue' ? 'continue' : 'stop',
                ],
                'run_download' => $this->download($rt, $c, "{$p}.config", $errors),
                default => $this->actions->stepConfig($form, $rt, $resolver, $type, $c, "{$p}.config", $errors),
            };
            if ($type === 'create_linked_record') {
                unset($config['open']);
            }
            // Nobody is there to download a generated document: it goes into a file field of the record.
            if ($type === 'generate_document' && ($config['attachToField'] ?? null) === null) {
                $errors["{$p}.config.attachToField"][] = __('automations.document_needs_field');
            }
            $onFailure = in_array($s['onFailure'] ?? 'stop', ['stop', 'continue', 'retry'], true) ? (string) ($s['onFailure'] ?? 'stop') : 'stop';
            $steps[] = ['uuid' => $uuid, 'type' => $type, 'config' => $config, 'condition' => $boolean($s['condition'] ?? null, "{$p}.condition"), 'onFailure' => $onFailure];
        }

        // Controls.
        $int = function (string $field, int $min, int $max, int $fallback) use ($a, &$errors): int {
            $v = $a[$field] ?? $fallback;
            if (! is_int($v) || $v < $min || $v > $max) {
                $errors[$field][] = __('automations.bad_number', ['min' => $min, 'max' => $max]);

                return $fallback;
            }

            return $v;
        };
        $concurrency = $int('concurrency', 1, 50, 5);
        $maxRecords = $int('maxRecords', 1, 100000, 1000);
        $depth = $int('maxChainDepth', 1, 10, 3);
        $confirm = $a['confirmAbove'] ?? null;
        if ($confirm !== null && (! is_int($confirm) || $confirm < 1 || $confirm >= $maxRecords)) {
            $errors['confirmAbove'][] = __('automations.bad_confirm_above', ['max' => $maxRecords - 1]);
            $confirm = null;
        }
        $retry = (array) ($a['retry'] ?? []);
        $attempts = $retry['maxAttempts'] ?? 3;
        if (! is_int($attempts) || $attempts < 1 || $attempts > 10) {
            $errors['retry.maxAttempts'][] = __('automations.bad_number', ['min' => 1, 'max' => 10]);
            $attempts = 3;
        }
        $backoff = array_values((array) ($retry['backoffSeconds'] ?? [60, 300, 900]));
        if (count($backoff) > 10 || array_filter($backoff, static fn ($v) => ! is_int($v) || $v < 10 || $v > 86400) !== []) {
            $errors['retry.backoffSeconds'][] = __('automations.bad_backoff');
            $backoff = [60, 300, 900];
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        return [
            'key' => $key, 'i18n' => ['name' => $name, 'description' => $this->texts($a['i18n']['description'] ?? [], 500)], 'enabled' => (bool) ($a['enabled'] ?? true),
            'triggers' => $triggers, 'condition' => $condition, 'steps' => $steps, 'runAs' => $runAs, 'runAsUser' => $runAs === 'specific_user' ? $runAsUser : null,
            'concurrency' => $concurrency, 'retry' => ['maxAttempts' => $attempts, 'backoffSeconds' => $backoff === [] ? [60] : $backoff],
            'maxRecords' => $maxRecords, 'confirmAbove' => $confirm, 'maxChainDepth' => $depth,
        ];
    }

    /**
     * A schedule as cron, its time zone and the choices it was built from.
     *
     * @param  array<string, mixed>  $s
     * @param  array<string, list<array<mixed>|string>>  $errors
     * @return array{0: string|null, 1: string|null, 2: array<string, mixed>}
     */
    private function schedule(array $s, string $p, array &$errors): array
    {
        $frequency = $s['frequency'] ?? null;
        if (! in_array($frequency, self::FREQUENCIES, true)) {
            $errors["{$p}.schedule.frequency"][] = __('validation.in', ['attribute' => 'frequency']);

            return [null, null, []];
        }
        $timezone = (string) ($s['timezone'] ?? 'UTC');
        if (! in_array($timezone, timezone_identifiers_list(), true)) {
            $errors["{$p}.schedule.timezone"][] = __('automations.bad_timezone');
        }
        [$hour, $minute] = preg_match('/^([01]\d|2[0-3]):([0-5]\d)$/', (string) ($s['time'] ?? ''), $m) === 1 ? [(int) $m[1], (int) $m[2]] : [null, null];
        if (in_array($frequency, ['daily', 'weekly', 'monthly'], true) && $hour === null) {
            $errors["{$p}.schedule.time"][] = __('automations.bad_time');
        }
        $atMinute = max(0, min(59, (int) ($s['minute'] ?? 0)));
        $weekday = max(0, min(6, (int) ($s['weekday'] ?? 1)));
        $day = max(1, min(28, (int) ($s['day'] ?? 1)));
        $cron = match ($frequency) {
            'hourly' => "{$atMinute} * * * *",
            'daily' => "{$minute} {$hour} * * *",
            'weekly' => "{$minute} {$hour} * * {$weekday}",
            'monthly' => "{$minute} {$hour} {$day} * *",
            default => trim((string) ($s['cron'] ?? '')),
        };
        if (! isset($errors["{$p}.schedule.time"]) && ! isset($errors["{$p}.schedule.timezone"]) && ! ScheduledTasks::valid($cron, $timezone)) {
            $errors["{$p}.schedule.cron"][] = __('automations.bad_cron');
        }

        return [$cron, $timezone, array_filter([
            'frequency' => $frequency, 'time' => $hour === null ? null : sprintf('%02d:%02d', $hour, $minute), 'minute' => $frequency === 'hourly' ? $atMinute : null,
            'weekday' => $frequency === 'weekly' ? $weekday : null, 'day' => $frequency === 'monthly' ? $day : null,
        ], static fn ($v) => $v !== null)];
    }

    /**
     * A download delivered by email: each recipient receives their own run,
     * with what they may see; for the record only, or the profile's records.
     *
     * @param  array<string, mixed>  $c
     * @param  array<string, list<array<mixed>|string>>  $errors
     * @return array<string, mixed>
     */
    private function download(FormRuntime $rt, array $c, string $p, array &$errors): array
    {
        $profile = is_string($c['profile'] ?? null) && Str::isUuid($c['profile']) ? DB::table('download_profiles')->where('uuid', strtolower($c['profile']))
            ->where('form_id', $rt->form->id)->where('is_personal', false)->whereNull('deleted_at')->first() : null;
        if ($profile === null) {
            $errors["{$p}.profile"][] = __('actions.unknown_download');
        } elseif (! in_array($c['format'] ?? null, json_decode((string) $profile->formats, true) ?: [], true)) {
            $errors["{$p}.format"][] = __('notifications.bad_attachment_format');
        }
        $scope = ($c['scope'] ?? 'profile') === 'record' ? 'record' : 'profile';
        if ($profile !== null && $scope === 'record' && ! in_array('record_view', json_decode((string) $profile->available_in, true) ?: [], true)) {
            $errors["{$p}.scope"][] = __('automations.download_not_for_record');
        }
        $users = array_values(array_unique(array_map('strtolower', array_filter((array) ($c['deliverTo']['users'] ?? []), static fn ($u) => is_string($u) && Str::isUuid($u)))));
        $roles = array_values(array_unique(array_map('strtolower', array_filter((array) ($c['deliverTo']['roles'] ?? []), static fn ($u) => is_string($u) && Str::isUuid($u)))));
        $given = count((array) ($c['deliverTo']['users'] ?? [])) + count((array) ($c['deliverTo']['roles'] ?? []));
        if ($given !== count($users) + count($roles)
            || ($users !== [] && DB::table('users')->whereIn('uuid', $users)->whereNull('deleted_at')->count() !== count($users))
            || ($roles !== [] && DB::table('roles')->whereIn('uuid', $roles)->count() !== count($roles))) {
            $errors["{$p}.deliverTo"][] = __('downloads.bad_recipients');
        } elseif ($users === [] && $roles === []) {
            $errors["{$p}.deliverTo"][] = __('downloads.recipients_required');
        }

        return ['profile' => $profile === null ? null : strtolower((string) $profile->uuid), 'format' => (string) ($c['format'] ?? 'xlsx'), 'scope' => $scope,
            'deliverTo' => ['users' => $users, 'roles' => $roles]];
    }

    /** @param  array<string, list<array<mixed>|string>>  $errors */
    private function minutes(mixed $v, string $path, array &$errors): int
    {
        if (! is_int($v) || $v < 1 || $v > self::MAX_MINUTES) {
            $errors[$path][] = __('automations.bad_number', ['min' => 1, 'max' => self::MAX_MINUTES]);

            return 1;
        }

        return $v;
    }

    /** @return array<string, mixed>|null a top-level field of the form */
    private function mainField(FormRuntime $rt, mixed $uuid): ?array
    {
        $field = is_string($uuid) ? ($rt->fields[strtolower($uuid)] ?? null) : null;

        return $field === null || isset($rt->fieldRepeater[$field['uuid']]) ? null : $field;
    }

    private function fieldId(string $uuid): ?int
    {
        $id = DB::table('fields')->where('uuid', $uuid)->value('id');

        return $id === null ? null : (int) $id;
    }

    /** @return array<string, string> */
    private function texts(mixed $map, int $max): array
    {
        $out = [];
        foreach ((array) $map as $locale => $text) {
            if (is_string($text) && trim($text) !== '' && in_array($locale, $this->translator->enabledLocales(), true)) {
                $out[(string) $locale] = mb_substr($text, 0, $max);
            }
        }

        return $out;
    }
}
