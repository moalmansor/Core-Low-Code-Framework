<?php

declare(strict_types=1);

namespace App\Modules\Downloads;

use App\Modules\Access\AccessResolver;
use App\Modules\Audit\AuditWriter;
use App\Modules\Core\Correlation\CorrelationId;
use App\Modules\Core\I18n\Translator;
use App\Modules\Core\Settings\SettingsService;
use App\Modules\Downloads\Jobs\RunDownload;
use App\Modules\Forms\Models\Form;
use App\Modules\Identity\Models\User;
use App\Modules\Notifications\InAppNotifier;
use App\Modules\Records\FileStore;
use App\Modules\Records\Models\StoredFile;
use App\Modules\Records\Runtime\FormRuntime;
use App\Modules\Records\Runtime\FormRuntimes;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

/**
 * Download runs (architecture §19.8 steps 4–6): a `download_jobs` row per
 * run with the parameters, filters, selection and the columns the user was
 * entitled to. Up to `downloads.sync_rows` records it is produced in the
 * request; larger runs go to the `heavy` queue with progress and a
 * notification. Every run is planned again under the user's permissions as
 * they are when it runs. The file is the user's own and expires; every
 * download is audited with who, the profile, filters, parameters, row count
 * and duration.
 */
final class DownloadJobs
{
    public function __construct(
        private readonly DownloadProfiles $profiles,
        private readonly DownloadEngine $engine,
        private readonly DownloadWriter $writer,
        private readonly FormRuntimes $runtimes,
        private readonly AccessResolver $access,
        private readonly SettingsService $settings,
        private readonly FileStore $files,
        private readonly AuditWriter $audit,
        private readonly InAppNotifier $notifier,
        private readonly CorrelationId $correlation,
        private readonly Translator $translator,
    ) {}

    /** Whether a user may run a profile: their own personal one, or a shared one they were granted, on a form they may view. */
    public function mayUse(object $profile, User $user): bool
    {
        if ($profile->deleted_at !== null || ! $profile->is_active) {
            return false;
        }
        $form = DB::table('forms')->where('id', $profile->form_id)->value('uuid');
        if ($form === null || ! $this->access->allows($user, 'form.'.strtolower((string) $form).'.view')) {
            return false;
        }

        return $profile->is_personal ? (int) $profile->owner_user_id === $user->id : $this->access->allows($user, 'download.'.strtolower((string) $profile->uuid).'.use');
    }

    /**
     * Profiles a user may run from a place of a form, with what the
     * download dialog needs.
     *
     * @return list<array<string, mixed>>
     */
    public function available(Form $form, User $user, string $place): array
    {
        $out = [];
        $rows = DB::table('download_profiles')->where('form_id', $form->id)->whereNull('deleted_at')->where('is_active', true)
            ->where(static fn ($q) => $q->where('is_personal', false)->orWhere('owner_user_id', $user->id))->orderBy('is_personal')->orderBy('key')->get();
        foreach ($rows as $p) {
            if (! in_array($place, json_decode((string) $p->available_in, true) ?: [], true) || ! $this->mayUse($p, $user)) {
                continue;
            }
            $doc = $this->profiles->load($p);
            $out[] = [
                'uuid' => $doc['uuid'], 'personal' => $doc['personal'],
                'name' => $this->translator->labelOf((array) $doc['i18n']['name'], (string) $doc['key']),
                'description' => $this->translator->pick((array) $doc['i18n']['description']),
                'formats' => $doc['formats'], 'applyUserFilters' => $doc['applyUserFilters'], 'allowSelected' => $doc['allowSelected'],
                'parameters' => array_map(fn (array $x) => [
                    'key' => $x['key'], 'type' => $x['type'], 'required' => $x['required'], 'default' => $x['default'],
                    'label' => $this->translator->labelOf((array) $x['i18n']['label'], (string) $x['key']),
                    'options' => array_map(fn (array $o) => ['value' => $o['value'], 'label' => $this->translator->pick($o['label']) ?? $o['value']], $x['options']),
                ], $doc['parameters']),
            ];
        }

        return $out;
    }

    /**
     * Creates the run; produces it at once when it is small.
     *
     * @param  array{filters?: array<string, mixed>|null, uuids?: list<string>|null, record?: string|null, parameters?: array<string, mixed>}  $input
     */
    public function start(object $profile, User $user, string $format, array $input, ?int $scheduleId = null, bool $inline = true): object
    {
        $form = Form::query()->findOrFail($profile->form_id);
        $rt = $this->runtimes->forForm($form) ?? abort(404);
        $doc = $this->profiles->load($profile);
        $plan = $this->engine->plan($rt, $doc, $user, $input);
        $count = min((clone $plan['query'])->count(), $plan['max']);
        $now = Carbon::now('UTC')->format('Y-m-d H:i:s.u');
        $selected = ($input['record'] ?? null) !== null ? [strtolower((string) $input['record'])] : (($input['uuids'] ?? []) === [] ? null : array_values((array) $input['uuids']));
        $id = (int) DB::table('download_jobs')->insertGetId([
            'uuid' => (string) Str::uuid7(), 'organization_id' => $form->organization_id, 'created_at' => $now, 'updated_at' => $now,
            'download_profile_id' => $profile->id, 'download_schedule_id' => $scheduleId, 'user_id' => $user->id, 'format' => $format,
            'parameters' => json_encode((object) $plan['parameters'], JSON_UNESCAPED_UNICODE),
            // A run from the record page is remembered as such; otherwise the table filters it followed.
            'filters_snapshot' => ($input['record'] ?? null) !== null ? json_encode(['@record' => strtolower((string) $input['record'])])
                : (is_array($input['filters'] ?? null) && $doc['applyUserFilters'] ? json_encode((object) $input['filters'], JSON_UNESCAPED_UNICODE) : null),
            'selected_ids' => $selected === null ? null : json_encode($selected),
            'effective_columns' => json_encode(['columns' => array_map(static fn ($c) => $c['header'], $plan['columns']), 'omitted' => $plan['omitted']], JSON_UNESCAPED_UNICODE),
            'status' => 'queued', 'progress' => 0, 'row_count' => $count, 'attempts' => 0, 'correlation_id' => $this->correlation->get(),
        ]);
        $queued = ! $inline || $count > (int) $this->settings->get('downloads', 'sync_rows');
        $queued ? RunDownload::dispatch($id) : $this->run($id, false);

        return DB::table('download_jobs')->where('id', $id)->first();
    }

    /** Produces a run; called by RunDownload (and inline for small runs). */
    public function run(int $jobId, bool $notify = true): void
    {
        $job = DB::table('download_jobs')->where('id', $jobId)->first();
        if ($job === null || ! in_array($job->status, ['queued', 'failed'], true)) {
            return;
        }
        $started = microtime(true);
        $user = User::query()->find($job->user_id);
        $profile = DB::table('download_profiles')->where('id', $job->download_profile_id)->first();
        $form = $profile === null ? null : Form::query()->find($profile->form_id);
        $rt = $form === null ? null : $this->runtimes->forForm($form);
        // Permissions are checked again: a grant removed since the request stops the run.
        if ($user === null || $user->status !== 'active' || $profile === null || $rt === null || ! $this->mayUse($profile, $user)) {
            $this->fail($job, __('downloads.unavailable'), $notify);

            return;
        }
        // The run acts as its user (files, audit); whoever was signed in before is restored afterwards.
        $previous = Auth::user();
        Auth::setUser($user);
        try {
            $this->produce($job, $jobId, $user, $profile, $form, $rt, $notify, $started);
        } finally {
            $previous === null ? Auth::guard()->forgetUser() : Auth::setUser($previous);
        }
    }

    private function produce(object $job, int $jobId, User $user, object $profile, Form $form, FormRuntime $rt, bool $notify, float $started): void
    {
        $this->update($jobId, ['status' => 'running', 'progress' => 0, 'error' => null, 'attempts' => (int) $job->attempts + 1, 'started_at' => Carbon::now('UTC')->format('Y-m-d H:i:s.u')]);
        $doc = $this->profiles->load($profile);
        $selected = json_decode((string) ($job->selected_ids ?? 'null'), true);
        $snapshot = json_decode((string) ($job->filters_snapshot ?? 'null'), true);
        $input = isset($snapshot['@record'])
            ? ['record' => (string) $snapshot['@record']]
            : ['filters' => $snapshot, 'uuids' => $selected];
        $input['parameters'] = (array) json_decode((string) ($job->parameters ?? '{}'), true);
        $path = (string) tempnam(sys_get_temp_dir(), 'lcf-download-');
        try {
            $plan = $this->engine->plan($rt, $doc, $user, $input);
            $total = max(1, (int) $job->row_count);
            $written = $this->writer->write($rt, $doc, $plan, $user, (string) $job->format, $path, function (int $rows) use ($jobId, $total): bool {
                $this->update($jobId, ['progress' => min(99, (int) floor(100 * $rows / $total))]);

                return DB::table('download_jobs')->where('id', $jobId)->value('status') !== 'cancelled';
            });
            if (DB::table('download_jobs')->where('id', $jobId)->value('status') === 'cancelled') {
                @unlink($path);

                return;
            }
            $name = $this->writer->fileName($rt, $doc, $user, $plan['parameters']);
            $stored = $this->files->storeGenerated($path, $name, (string) $job->format, DownloadWriter::MIME[$job->format], 'download_job', ['owner_id' => $jobId, 'user_id' => $user->id, 'form_id' => $form->id]);
        } catch (Throwable $e) {
            @unlink($path);
            report($e);
            $this->fail($job, __('downloads.failed', ['reference' => $this->correlation->get()]), $notify);

            return;
        }
        @unlink($path);
        $hours = (int) $this->settings->get('records', 'job_file_hours');
        $now = Carbon::now('UTC');
        $this->update($jobId, [
            'status' => 'completed', 'progress' => 100, 'row_count' => $written['rows'], 'file_id' => $stored->id,
            'expires_at' => $now->copy()->addHours($hours)->format('Y-m-d H:i:s.u'), 'finished_at' => $now->format('Y-m-d H:i:s.u'),
            'effective_columns' => json_encode(['columns' => array_map(static fn ($c) => $c['header'], $plan['columns']), 'omitted' => $plan['omitted']], JSON_UNESCAPED_UNICODE),
        ]);
        $this->audit->record('download.completed', 'data', null, 'download_profile', (int) $profile->id, [
            'job' => $job->uuid, 'profile' => $profile->key, 'format' => $job->format, 'rows' => $written['rows'], 'truncated' => $written['truncated'],
            'parameters' => $plan['parameters'], 'filters' => array_keys((array) ($input['filters'] ?? [])), 'selected' => is_array($selected) ? count($selected) : null,
            'schedule' => $job->download_schedule_id === null ? null : (int) $job->download_schedule_id, 'duration_ms' => (int) round((microtime(true) - $started) * 1000),
        ], $user->id, null, $form->id);
        if ($job->download_schedule_id !== null) {
            app(DownloadSchedules::class)->deliver(DB::table('download_jobs')->where('id', $jobId)->first());
        } elseif ($notify) {
            $this->notifier->notify($user->id, 'download.ready', 'downloads.notify.ready', ['name' => $name, 'rows' => $written['rows']],
                "/app/{$form->uuid}?download={$job->uuid}", ['download_job' => $job->uuid], ['form_id' => $form->id]);
        }
    }

    public function crashed(int $jobId): void
    {
        $job = DB::table('download_jobs')->where('id', $jobId)->first();
        if ($job !== null && $job->status !== 'cancelled') {
            $this->fail($job, __('downloads.failed', ['reference' => $this->correlation->get()]), true);
        }
    }

    public function cancel(object $job): void
    {
        DB::table('download_jobs')->where('id', $job->id)->whereIn('status', ['queued', 'running'])
            ->update(['status' => 'cancelled', 'updated_at' => Carbon::now('UTC')->format('Y-m-d H:i:s.u')]);
    }

    /** Runs a failed download again (its owner, or the Operations Center). */
    public function retry(object $job): void
    {
        if ($job->status !== 'failed') {
            return;
        }
        $this->update((int) $job->id, ['status' => 'queued', 'error' => null, 'progress' => 0]);
        RunDownload::dispatch((int) $job->id);
    }

    /** @return array<string, mixed> a run as its owner sees it */
    public function present(object $job): array
    {
        $columns = json_decode((string) $job->effective_columns, true) ?: [];

        return [
            'uuid' => strtolower((string) $job->uuid), 'status' => $job->status, 'progress' => (int) $job->progress, 'format' => $job->format,
            'rows' => $job->row_count === null ? null : (int) $job->row_count, 'error' => $job->status === 'failed' ? $job->error : null,
            'file' => $job->file_id === null || $job->status !== 'completed' ? null : strtolower((string) DB::table('files')->where('id', $job->file_id)->value('uuid')),
            'expires_at' => $job->expires_at === null ? null : Carbon::parse($job->expires_at, 'UTC')->toIso8601ZuluString(),
            'omitted' => $columns['omitted'] ?? [],
        ];
    }

    /** Marks finished downloads whose file is past its time as expired and deletes the file. */
    public function expire(): int
    {
        $count = 0;
        DB::table('download_jobs')->where('status', 'completed')->where('expires_at', '<', Carbon::now('UTC')->format('Y-m-d H:i:s.u'))
            ->orderBy('id')->limit(500)->get()->each(function (object $job) use (&$count): void {
                $file = $job->file_id === null ? null : StoredFile::query()->withoutGlobalScopes()->find($job->file_id);
                if ($file !== null) {
                    Storage::disk($file->disk)->delete($file->path);
                    $file->forceFill(['deleted_at' => now()])->save();
                }
                $this->update((int) $job->id, ['status' => 'expired']);
                $count++;
            });

        return $count;
    }

    public function formOf(object $profile): ?FormRuntime
    {
        $form = Form::query()->find($profile->form_id);

        return $form === null ? null : $this->runtimes->forForm($form);
    }

    private function fail(object $job, string $error, bool $notify): void
    {
        $this->update((int) $job->id, ['status' => 'failed', 'error' => mb_substr($error, 0, 2000), 'finished_at' => Carbon::now('UTC')->format('Y-m-d H:i:s.u')]);
        $this->audit->record('download.failed', 'operations', null, 'download_profile', (int) $job->download_profile_id, ['job' => $job->uuid, 'error' => $error], (int) $job->user_id);
        if ($notify) {
            $this->notifier->notify((int) $job->user_id, 'download.failed', 'downloads.notify.failed', ['error' => $error], null, ['download_job' => $job->uuid]);
        }
    }

    /** @param  array<string, mixed>  $values */
    private function update(int $id, array $values): void
    {
        DB::table('download_jobs')->where('id', $id)->update($values + ['updated_at' => Carbon::now('UTC')->format('Y-m-d H:i:s.u')]);
    }
}
