<?php

declare(strict_types=1);

namespace App\Modules\Actions;

use App\Modules\Access\AccessResolver;
use App\Modules\Access\RecordScope;
use App\Modules\Assignment\AssignmentService;
use App\Modules\Assignment\Membership;
use App\Modules\Documents\DocumentRenderer;
use App\Modules\Documents\GeneratedDocuments;
use App\Modules\Documents\Models\DocumentTemplate;
use App\Modules\Downloads\DownloadJobs;
use App\Modules\Identity\Models\User;
use App\Modules\Notifications\Notifier;
use App\Modules\Records\Runtime\FormRuntime;
use App\Modules\Records\Runtime\FormRuntimes;
use App\Modules\Records\Runtime\RecordException;
use App\Modules\Records\Runtime\RecordPipeline;
use App\Modules\Records\Runtime\RecordStore;
use App\Modules\Records\Runtime\RuleRuntime;
use App\Modules\Records\Runtime\ValueBridge;
use App\Modules\Records\Templating\TemplateValues;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * The steps of a custom action for one record (architecture §14.9), in
 * order. A step with a condition runs only when it holds for the record as
 * it is at that moment; each step sees the changes of the steps before it.
 * A failing step stops the chain or, when configured to continue, leaves a
 * warning and moves on. Steps are not undone when a later one fails: each
 * change is its own audited save, as if the user had made it.
 */
final class ActionSteps
{
    public function __construct(
        private readonly FormRuntimes $runtimes,
        private readonly RecordStore $store,
        private readonly RecordPipeline $pipeline,
        private readonly RuleRuntime $rules,
        private readonly Notifier $notifier,
        private readonly WebhookCaller $webhooks,
        private readonly TemplateValues $values,
        private readonly DocumentRenderer $documents,
        private readonly GeneratedDocuments $generated,
        private readonly AssignmentService $assignments,
        private readonly Membership $members,
        private readonly AccessResolver $access,
        private readonly RecordScope $scope,
        private readonly DownloadJobs $downloads,
    ) {}

    /**
     * @param  list<object>  $steps  action_steps rows
     * @param  array<int, array<string, mixed>>  $asts  step conditions by condition id
     * @param  array{uuid: string, row_version: int|null}  $target
     * @return list<array<string, mixed>> outputs (downloads, records to open, warnings)
     */
    public function runAll(array $steps, array $asts, FormRuntime $rt, User $user, array $target, string $key, ?int $justificationId, string $actionUuid, string $label): array
    {
        $origin = new StepOrigin('action', $actionUuid, $label);
        $found = $this->store->find($rt, $target['uuid']) ?? throw new RecordException(404, 'not_found', __('records.not_found'));
        if (! $this->scope->allows($rt, $user, 'view', $found['id'])) {
            throw new RecordException(404, 'not_found', __('records.not_found'));
        }
        // The user chose the record as it was; a change since then is a conflict, never run over silently.
        if ($target['row_version'] !== null && $target['row_version'] !== $found['row_version']) {
            throw new RecordException(409, 'conflict', __('actions.changed_since'));
        }
        $outputs = [];
        $recordId = $found['id'];
        foreach ($steps as $i => $step) {
            // Each step sees the record as the steps before it left it.
            $found = $this->store->findById($rt, $recordId) ?? throw new RecordException(404, 'not_found', __('records.not_found'));
            $ast = $step->condition_id === null ? null : ($asts[(int) $step->condition_id] ?? null);
            if ($ast !== null && ! $this->rules->holds($rt, $ast, $found['values'], $user, 'view')) {
                continue;
            }
            try {
                $outputs = [...$outputs, ...$this->one($step, $rt, $user, $found, "{$key}-{$i}", $justificationId, $origin)];
            } catch (RecordException|ValidationException|StepFailed $e) {
                $message = __('actions.step_failed', ['step' => __('actions.step.'.$step->type), 'reason' => ActionRunner::message($e)]);
                if ($step->on_failure === 'stop') {
                    throw new StepFailed($message, 0, $e);
                }
                $outputs[] = ['type' => 'warning', 'message' => $message];
            }
        }

        return $outputs;
    }

    /**
     * Runs one step for one record, as `$user`. Shared by actions and
     * automations; when the origin simulates (an automation's test run),
     * steps that reach outside the database say what they would do instead.
     *
     * @param  array<string, mixed>  $found
     * @return list<array<string, mixed>>
     */
    public function one(object $step, FormRuntime $rt, User $user, array $found, string $key, ?int $justificationId, StepOrigin $origin): array
    {
        $c = ActionDocument::runtimeConfig($step);
        if ($origin->simulate && in_array($step->type, StepOrigin::OUTSIDE, true)) {
            return [['type' => 'simulated', 'message' => __('automations.simulated.'.$step->type)]];
        }

        return match ($step->type) {
            'update_fields' => $this->updateFields($c, $rt, $user, $found, $key, $justificationId, $origin),
            'change_status' => $this->changeStatus($c, $rt, $user, $found, $key, $justificationId, $origin),
            'send_email', 'send_notification' => $this->send($step->type, $c, $rt, $user, $found, $origin),
            'call_webhook' => $this->webhook($c, $rt, $user, $found, $origin),
            'generate_document' => $this->document($c, $rt, $user, $found, $key, $justificationId, $origin),
            'create_linked_record' => $this->linked($c, $rt, $user, $found, $key, $origin),
            'assign' => $this->assign($c, $rt, $user, $found, $justificationId),
            'run_download' => $this->download($c, $user, $found),
            default => throw new StepFailed(__('actions.not_runnable')),
        };
    }

    /**
     * @param  array<string, mixed>  $c
     * @param  array<string, mixed>  $found
     * @return list<array<string, mixed>>
     */
    private function updateFields(array $c, FormRuntime $rt, User $user, array $found, string $key, ?int $justificationId, StepOrigin $origin): array
    {
        $values = [];
        foreach ((array) $c['set'] as $item) {
            $field = $rt->fields[$item['field']] ?? null;
            if ($field === null) {
                throw new StepFailed(__('actions.field_gone'));
            }
            $values[$field['key']] = ValueBridge::toApi($rt, $field, $this->rules->evaluate($rt, (array) $item['value'], $found['values'], $user));
        }
        $this->pipeline->update($rt, $user, $found['uuid'], $found['row_version'], $values, $key, $origin->source, null, $justificationId);

        return [];
    }

    /**
     * @param  array<string, mixed>  $c
     * @param  array<string, mixed>  $found
     * @return list<array<string, mixed>>
     */
    private function changeStatus(array $c, FormRuntime $rt, User $user, array $found, string $key, ?int $justificationId, StepOrigin $origin): array
    {
        $this->pipeline->transition($rt, $user, $found['uuid'], (string) $c['transition'], $found['row_version'], [], $key, $origin->source, $justificationId);

        return [];
    }

    /**
     * @param  array<string, mixed>  $c
     * @param  array<string, mixed>  $found
     * @return list<array<string, mixed>>
     */
    private function send(string $type, array $c, FormRuntime $rt, User $user, array $found, StepOrigin $origin): array
    {
        // "The person running the action" is a recipient the notifier knows as a user.
        $recipients = [];
        foreach (['to', 'cc', 'bcc'] as $slot) {
            $recipients[$slot] = array_map(static fn (array $r) => ($r['type'] ?? null) === 'actor' ? ['type' => 'user', 'ref' => strtolower((string) $user->uuid)] : $r, (array) ($c['recipients'][$slot] ?? []));
        }
        $this->notifier->sendForAction([
            'channels' => [$type === 'send_email' ? 'email' : 'in_app'], 'template' => $c['template'] ?? null, 'recipients' => $recipients,
            'attachments' => ($c['documents'] ?? []) === [] ? null : ['fields' => [], 'documents' => $c['documents']],
        ], $rt, $found['id'], $user, $origin->uuid, $origin->label, $origin->source);

        return [];
    }

    /**
     * @param  array<string, mixed>  $c
     * @param  array<string, mixed>  $found
     * @return list<array<string, mixed>>
     */
    private function webhook(array $c, FormRuntime $rt, User $user, array $found, StepOrigin $origin): array
    {
        $ctx = $this->values->context($rt, $found['id'], [$user], app()->getLocale(), $user->name);
        $this->webhooks->call($c, $ctx, ['form_id' => $rt->form->id, 'record_id' => $found['id'], 'user_id' => $user->id, $origin->source => $origin->uuid]);

        return [];
    }

    /**
     * @param  array<string, mixed>  $c
     * @param  array<string, mixed>  $found
     * @return list<array<string, mixed>>
     */
    private function document(array $c, FormRuntime $rt, User $user, array $found, string $key, ?int $justificationId, StepOrigin $origin): array
    {
        $template = DocumentTemplate::query()->where('uuid', (string) $c['template'])->where('is_active', true)->first() ?? throw new StepFailed(__('actions.document_gone'));
        try {
            $out = $this->documents->render($template, $rt, $found['id'], [$user], app()->getLocale(), (string) $c['format']);
        } catch (Throwable $e) {
            report($e);
            throw new StepFailed(__('documents.generate_failed'));
        }
        $field = $c['attachToField'] === null ? null : ($rt->fields[$c['attachToField']] ?? null);
        if ($field === null) {
            $file = $this->generated->store($out, GeneratedDocuments::OWNER_DOWNLOAD, $rt->form->id, $found['id'], $user->id);

            return [['type' => 'download', 'file' => strtolower($file->uuid), 'name' => $file->original_name]];
        }
        // Into a file field: a fresh upload of the user's, claimed by the save like any other.
        $file = $this->generated->store($out, 'upload', null, null, $user->id);
        $file->forceFill(['is_temporary' => true])->save();
        $current = $found['values'][$field['key']] ?? null;
        $value = $rt->type($field)?->storage === 'files' ? [...array_values((array) $current), strtolower($file->uuid)] : strtolower($file->uuid);
        $this->pipeline->update($rt, $user, $found['uuid'], $found['row_version'], [$field['key'] => $value], $key, $origin->source, null, $justificationId);

        return [];
    }

    /**
     * @param  array<string, mixed>  $c
     * @param  array<string, mixed>  $found
     * @return list<array<string, mixed>>
     */
    private function linked(array $c, FormRuntime $rt, User $user, array $found, string $key, StepOrigin $origin): array
    {
        $target = $this->runtimes->forUuid((string) $c['form']) ?? throw new StepFailed(__('actions.form_gone'));
        $link = $target->fields[(string) $c['linkField']] ?? throw new StepFailed(__('actions.field_gone'));
        $values = [$link['key'] => $target->isMultiReference($link) ? [$found['uuid']] : $found['uuid']];
        foreach ((array) $c['prefill'] as $item) {
            $field = $target->fields[$item['field']] ?? null;
            if ($field !== null) {
                $values[$field['key']] = ValueBridge::toApi($target, $field, $this->rules->evaluate($rt, (array) $item['value'], $found['values'], $user));
            }
        }
        $created = $this->pipeline->create($target, $user, $values, [], $key, $origin->source);

        return ($c['open'] ?? false) ? [['type' => 'open', 'url' => '/app/'.$target->form->uuid.'/'.$created['uuid']]] : [];
    }

    /**
     * The record's download through a profile, under the runner's own
     * permissions (the profile's grant, field access and record scope).
     *
     * @param  array<string, mixed>  $c
     * @param  array<string, mixed>  $found
     * @return list<array<string, mixed>>
     */
    private function download(array $c, User $user, array $found): array
    {
        $profile = DB::table('download_profiles')->where('uuid', (string) $c['profile'])->whereNull('deleted_at')->first();
        if ($profile === null || ! $this->downloads->mayUse($profile, $user)) {
            throw new StepFailed(__('actions.download_unavailable'));
        }
        $job = $this->downloads->start($profile, $user, (string) $c['format'], ['record' => $found['uuid']]);
        $run = $this->downloads->present($job);
        if ($run['status'] !== 'completed' || $run['file'] === null) {
            throw new StepFailed((string) ($run['error'] ?? __('actions.download_unavailable')));
        }

        return [['type' => 'download', 'file' => $run['file'], 'name' => (string) DB::table('files')->where('uuid', $run['file'])->value('original_name')]];
    }

    /**
     * @param  array<string, mixed>  $c
     * @param  array<string, mixed>  $found
     * @return list<array<string, mixed>>
     */
    private function assign(array $c, FormRuntime $rt, User $user, array $found, ?int $justificationId): array
    {
        $this->assignRecord((array) ($c['to'] ?? []), $rt, $user, $found, $justificationId);

        return [];
    }

    /**
     * Assigns a record (or reassigns it) to a user, role or department, as the
     * assign step and the bulk reassign built-in do: with the person's
     * permission to assign or reassign and to edit the record.
     *
     * @param  array<string, mixed>  $to  {type, uuid}
     * @param  array<string, mixed>  $found
     */
    public function assignRecord(array $to, FormRuntime $rt, User $user, array $found, ?int $justificationId): void
    {
        $reassign = $this->assignments->hasActive($rt->form->id, $found['id']);
        if (! $this->access->allows($user, $reassign ? 'system.reassign_records' : 'system.assign_records') || ! $this->scope->allows($rt, $user, 'edit', $found['id'])) {
            throw new StepFailed(__('actions.no_assign_permission'));
        }
        $type = (string) ($to['type'] ?? '');
        $id = $this->members->idOf($type, (string) ($to['uuid'] ?? ''));
        if ($id === null || ($type === 'user' && ! DB::table('users')->where('id', $id)->where('status', 'active')->whereNull('deleted_at')->exists())) {
            throw new StepFailed(__('actions.bad_assignee'));
        }
        DB::transaction(fn () => $this->assignments->assign($rt, $found['id'], $type, $id, $user->id, ['justification' => $justificationId]));
    }
}
