<?php

declare(strict_types=1);

namespace App\Modules\Integrations\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Audit\AuditWriter;
use App\Modules\Core\I18n\Translator;
use App\Modules\Integrations\Models\ExternalDataSource;
use App\Modules\Integrations\Models\SyncJob;
use App\Modules\Integrations\SyncJobs;
use App\Modules\Records\Models\StoredFile;
use App\Support\Json\Canonical;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Sync jobs and their runs (specification §4.33, ADR-0057); everything needs "Manage integrations". */
final class SyncJobController extends Controller
{
    public function __construct(private readonly SyncJobs $sync, private readonly Translator $translator, private readonly AuditWriter $audit) {}

    public function index(): JsonResponse
    {
        Gate::authorize('system.manage_integrations');

        return response()->json(['data' => SyncJob::query()->orderBy('id')->get()->map(fn (SyncJob $j) => $this->summary($j))->values()->all()]);
    }

    public function show(string $job): JsonResponse
    {
        Gate::authorize('system.manage_integrations');

        return response()->json(['data' => $this->present($this->find($job))]);
    }

    public function store(Request $request): JsonResponse
    {
        Gate::authorize('system.manage_integrations');
        $data = $this->sync->normalize((array) $request->input('job', []));
        $job = new SyncJob;
        DB::transaction(function () use ($job, $data): void {
            $job->forceFill($data['row'])->save();
            $job->setTranslations('name', $data['name']);
            $this->sync->schedule($job, $data['schedule'], $this->translator->labelOf($data['name'], 'sync'));
        });
        $this->audit->record('integration.sync_created', 'config', null, 'sync_job', $job->id, ['direction' => $job->direction], null, null, $job->form_id);

        return response()->json(['data' => $this->present($job->refresh())], 201);
    }

    public function update(Request $request, string $job): JsonResponse
    {
        Gate::authorize('system.manage_integrations');
        $j = $this->find($job);
        $request->validate(['base_hash' => ['required', 'string', 'size:64']]);
        abort_unless(hash_equals($this->present($j)['hash'], (string) $request->input('base_hash')), 409, __('integrations.changed_meanwhile'));
        $data = $this->sync->normalize((array) $request->input('job', []));
        DB::transaction(function () use ($j, $data): void {
            $j->forceFill($data['row'])->save();
            $j->setTranslations('name', $data['name']);
            $this->sync->schedule($j, $data['schedule'], $this->translator->labelOf($data['name'], 'sync'));
        });
        $this->audit->record('integration.sync_updated', 'config', null, 'sync_job', $j->id, ['direction' => $j->direction, 'active' => $j->is_active], null, null, $j->form_id);

        return response()->json(['data' => $this->present($j->refresh())]);
    }

    public function destroy(string $job): JsonResponse
    {
        Gate::authorize('system.manage_integrations');
        $j = $this->find($job);
        abort_if(DB::table('sync_runs')->where('sync_job_id', $j->id)->whereIn('status', ['queued', 'running'])->exists(), 422, __('integrations.sync.already_running'));
        DB::transaction(function () use ($j): void {
            $task = $j->scheduled_task_id;
            DB::table('sync_runs')->where('sync_job_id', $j->id)->delete();
            $this->audit->record('integration.sync_deleted', 'config', null, 'sync_job', $j->id, ['direction' => $j->direction], null, null, $j->form_id);
            $j->delete();
            if ($task !== null) {
                DB::table('scheduled_tasks')->where('id', $task)->delete();
            }
        });

        return response()->json(null, 204);
    }

    /** Starts a run now (or a dry run). */
    public function run(Request $request, string $job): JsonResponse
    {
        Gate::authorize('system.manage_integrations');
        $j = $this->find($job);
        $data = $request->validate(['dry_run' => ['sometimes', 'boolean']]);
        $id = $this->sync->start($j, (bool) ($data['dry_run'] ?? false), (int) Auth::id());

        return response()->json(['data' => $this->runRow(DB::table('sync_runs')->where('id', $id)->first())], 202);
    }

    public function runs(string $job): JsonResponse
    {
        Gate::authorize('system.manage_integrations');
        $j = $this->find($job);

        return response()->json(['data' => DB::table('sync_runs')->where('sync_job_id', $j->id)->orderByDesc('id')->limit(50)->get()->map(fn ($r) => $this->runRow($r))->values()->all()]);
    }

    /** A run's CSV log. */
    public function log(string $job, string $run): StreamedResponse
    {
        Gate::authorize('system.manage_integrations');
        $j = $this->find($job);
        $fileId = DB::table('sync_runs')->where('sync_job_id', $j->id)->where('uuid', strtolower($run))->value('log_file_id');
        $file = $fileId === null ? null : StoredFile::query()->find($fileId);
        abort_if($file === null, 404);

        return Storage::disk($file->disk)->download($file->path, $file->original_name, ['Content-Type' => 'text/csv; charset=utf-8', 'X-Content-Type-Options' => 'nosniff']);
    }

    /** @return array<string, mixed> */
    private function runRow(object $r): array
    {
        $at = static fn ($v) => $v === null ? null : Carbon::parse((string) $v, 'UTC')->toIso8601ZuluString();

        return ['uuid' => strtolower((string) $r->uuid), 'status' => $r->status, 'dry_run' => (bool) $r->dry_run, 'created' => (int) $r->created_count, 'updated' => (int) $r->updated_count,
            'skipped' => (int) $r->skipped_count, 'errors' => (int) $r->error_count, 'error' => $r->error, 'has_log' => $r->log_file_id !== null,
            'started_at' => $at($r->started_at), 'finished_at' => $at($r->finished_at), 'created_at' => $at($r->created_at),
            'triggered_by' => $r->triggered_by === null ? null : DB::table('users')->where('id', $r->triggered_by)->value('name')];
    }

    /** @return array<string, mixed> */
    private function present(SyncJob $j): array
    {
        $task = $j->scheduled_task_id === null ? null : DB::table('scheduled_tasks')->where('id', $j->scheduled_task_id)->first(['cron_expression', 'timezone', 'is_enabled', 'next_run_at']);
        $doc = [
            'uuid' => strtolower($j->uuid), 'name' => (object) $j->translationsFor('name'), 'direction' => $j->direction,
            'source' => strtolower((string) DB::table('external_data_sources')->where('id', $j->external_data_source_id)->value('uuid')),
            'form' => strtolower((string) DB::table('forms')->where('id', $j->form_id)->value('uuid')),
            'run_as' => strtolower((string) DB::table('users')->where('id', $j->run_as_user_id)->value('uuid')),
            'fields' => $j->field_mapping['fields'] ?? [], 'export' => $j->field_mapping['export'] ?? null,
            'match' => $j->match_keys['fields'] ?? [], 'updated_from' => $j->match_keys['updated_from'] ?? null,
            'conflict_rule' => $j->conflict_rule, 'active' => $j->is_active,
            'schedule' => $task === null || ! $task->is_enabled ? null : ['cron' => $task->cron_expression, 'timezone' => $task->timezone],
        ];
        $doc['hash'] = hash('sha256', (string) json_encode(Canonical::of($doc), JSON_UNESCAPED_UNICODE));

        return $doc + $this->summary($j) + ['run_as_name' => DB::table('users')->where('id', $j->run_as_user_id)->value('name'),
            'next_run_at' => $task === null || ! $task->is_enabled || $task->next_run_at === null ? null : Carbon::parse((string) $task->next_run_at, 'UTC')->toIso8601ZuluString()];
    }

    /** @return array<string, mixed> */
    private function summary(SyncJob $j): array
    {
        $source = ExternalDataSource::query()->find($j->external_data_source_id);
        $form = DB::table('forms')->where('id', $j->form_id)->first(['id', 'key']);
        $last = DB::table('sync_runs')->where('sync_job_id', $j->id)->orderByDesc('id')->first();

        return [
            'uuid' => strtolower($j->uuid), 'label' => $this->translator->labelOf($j->translationsFor('name'), 'sync'), 'direction' => $j->direction, 'active' => $j->is_active,
            'source_name' => $source === null ? null : $this->translator->labelOf($source->translationsFor('name'), $source->key),
            'form_name' => $form === null ? null : ($this->translator->many('form', [(int) $form->id], ['name'])[(int) $form->id]['name'] ?? Translator::humanize((string) $form->key)),
            'scheduled' => $j->scheduled_task_id !== null && (bool) DB::table('scheduled_tasks')->where('id', $j->scheduled_task_id)->value('is_enabled'),
            'last_run' => $last === null ? null : $this->runRow($last),
        ];
    }

    private function find(string $uuid): SyncJob
    {
        $j = Str::isUuid($uuid) ? SyncJob::query()->where('uuid', strtolower($uuid))->first() : null;
        abort_if($j === null, 404);

        return $j;
    }
}
