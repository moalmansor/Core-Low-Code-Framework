<?php

declare(strict_types=1);

namespace App\Modules\Downloads;

use App\Modules\Assignment\Membership;
use App\Modules\Audit\AuditWriter;
use App\Modules\Core\I18n\Translator;
use App\Modules\Core\Settings\SettingsService;
use App\Modules\Identity\Models\User;
use App\Modules\Notifications\EmailQueue;
use App\Modules\Notifications\Notifier;
use App\Modules\Scheduling\ScheduledTasks;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Scheduled downloads (specification §4.23, architecture §19.8 step 7): a
 * profile runs daily, weekly, monthly or on a cron expression in a chosen
 * time zone, and is emailed to chosen users and roles. It is produced for
 * each recipient separately, under that recipient's own permissions — never
 * a shared identity — so each receives only what they may see; a recipient
 * who may not use the profile is skipped. The file is attached when it is
 * small enough, otherwise the email links to it inside the application
 * (sign-in needed).
 */
final class DownloadSchedules
{
    public const FREQUENCIES = ['daily', 'weekly', 'monthly', 'cron'];

    public function __construct(
        private readonly ScheduledTasks $tasks,
        private readonly DownloadJobs $jobs,
        private readonly DownloadProfiles $profiles,
        private readonly DownloadEngine $engine,
        private readonly Membership $members,
        private readonly EmailQueue $mail,
        private readonly Notifier $notifier,
        private readonly Translator $translator,
        private readonly SettingsService $settings,
        private readonly AuditWriter $audit,
    ) {}

    /** @return list<array<string, mixed>> */
    public function list(object $profile): array
    {
        return DB::table('download_schedules')->where('download_profile_id', $profile->id)->orderBy('id')->get()
            ->map(fn (object $s) => $this->present($s))->values()->all();
    }

    /** @return array<string, mixed> */
    public function present(object $s): array
    {
        $task = $s->scheduled_task_id === null ? null : DB::table('scheduled_tasks')->where('id', $s->scheduled_task_id)->first();
        $cron = (string) $s->cron_expression;
        $parts = explode(' ', $cron);
        $recipients = json_decode((string) $s->recipients, true) ?: [];

        return [
            'uuid' => strtolower((string) $s->uuid), 'frequency' => $s->frequency, 'timezone' => $s->timezone, 'format' => $s->format,
            'time' => count($parts) === 5 && $s->frequency !== 'cron' ? sprintf('%02d:%02d', (int) $parts[1], (int) $parts[0]) : null,
            'weekday' => $s->frequency === 'weekly' ? (int) ($parts[4] ?? 1) : null,
            'day' => $s->frequency === 'monthly' ? (int) ($parts[2] ?? 1) : null,
            'cron' => $s->frequency === 'cron' ? $cron : null,
            'parameters' => (object) (json_decode((string) ($s->parameters ?? 'null'), true) ?: []),
            'recipients' => ['users' => array_values($recipients['users'] ?? []), 'roles' => array_values($recipients['roles'] ?? [])],
            'active' => (bool) $s->is_active,
            'next_run_at' => $task?->next_run_at === null ? null : Carbon::parse($task->next_run_at, 'UTC')->toIso8601ZuluString(),
            'last_run_at' => $task?->last_run_at === null ? null : Carbon::parse($task->last_run_at, 'UTC')->toIso8601ZuluString(),
            'last_status' => $task?->last_status,
        ];
    }

    /**
     * Creates or updates a schedule of a profile.
     *
     * @param  array<string, mixed>  $d
     */
    public function save(object $profile, ?object $existing, array $d, User $actor): object
    {
        $errors = [];
        $frequency = $d['frequency'] ?? null;
        if (! in_array($frequency, self::FREQUENCIES, true)) {
            $errors['frequency'][] = __('validation.in', ['attribute' => 'frequency']);
        }
        $timezone = (string) ($d['timezone'] ?? config('app.timezone', 'UTC'));
        if (! in_array($timezone, timezone_identifiers_list(), true)) {
            $errors['timezone'][] = __('downloads.bad_timezone');
        }
        [$hour, $minute] = preg_match('/^([01]\d|2[0-3]):([0-5]\d)$/', (string) ($d['time'] ?? ''), $m) === 1 ? [(int) $m[1], (int) $m[2]] : [null, null];
        if ($frequency !== 'cron' && $hour === null) {
            $errors['time'][] = __('downloads.bad_time');
        }
        $cron = match ($frequency) {
            'daily' => "{$minute} {$hour} * * *",
            'weekly' => sprintf('%d %d * * %d', $minute, $hour, max(0, min(6, (int) ($d['weekday'] ?? 1)))),
            'monthly' => sprintf('%d %d %d * *', $minute, $hour, max(1, min(28, (int) ($d['day'] ?? 1)))),
            default => trim((string) ($d['cron'] ?? '')),
        };
        if (! isset($errors['frequency']) && ! isset($errors['time']) && ! isset($errors['timezone']) && ! ScheduledTasks::valid($cron, $timezone)) {
            $errors['cron'][] = __('downloads.bad_cron');
        }
        $doc = $this->profiles->load($profile);
        $format = $d['format'] ?? null;
        if (! in_array($format, $doc['formats'], true)) {
            $errors['format'][] = __('downloads.bad_formats');
        }
        // Nobody is there to fill the parameters: required ones need a value now.
        $parameters = [];
        try {
            $parameters = $this->engine->parameters($doc, (array) ($d['parameters'] ?? []));
        } catch (ValidationException $e) {
            $errors = array_merge($errors, $e->errors());
        }
        $users = array_values(array_unique(array_map('strtolower', array_filter((array) ($d['recipients']['users'] ?? []), 'is_string'))));
        $roles = array_values(array_unique(array_map('strtolower', array_filter((array) ($d['recipients']['roles'] ?? []), 'is_string'))));
        if ($profile->is_personal) {
            // A personal profile is only ever sent to its owner.
            $users = [strtolower((string) DB::table('users')->where('id', $profile->owner_user_id)->value('uuid'))];
            $roles = [];
        }
        if (count($users) !== DB::table('users')->whereIn('uuid', $users ?: ['-'])->whereNull('deleted_at')->count() || count($roles) !== DB::table('roles')->whereIn('uuid', $roles ?: ['-'])->count()) {
            $errors['recipients'][] = __('downloads.bad_recipients');
        } elseif ($users === [] && $roles === []) {
            $errors['recipients'][] = __('downloads.recipients_required');
        }
        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
        $active = (bool) ($d['active'] ?? true);

        return DB::transaction(function () use ($profile, $existing, $frequency, $cron, $timezone, $format, $parameters, $users, $roles, $active, $actor, $doc): object {
            $now = Carbon::now('UTC')->format('Y-m-d H:i:s.u');
            $row = [
                'updated_at' => $now, 'updated_by' => $actor->id, 'frequency' => $frequency, 'cron_expression' => $cron, 'timezone' => $timezone,
                'format' => $format, 'parameters' => $parameters === [] ? null : json_encode((object) $parameters, JSON_UNESCAPED_UNICODE),
                'recipients' => json_encode(['users' => $users, 'roles' => $roles]), 'run_as_policy' => 'per_recipient', 'is_active' => $active,
            ];
            if ($existing === null) {
                $id = (int) DB::table('download_schedules')->insertGetId($row + [
                    'uuid' => (string) Str::uuid7(), 'organization_id' => $profile->organization_id, 'created_at' => $now, 'created_by' => $actor->id, 'download_profile_id' => $profile->id,
                ]);
                $taskId = null;
            } else {
                $id = (int) $existing->id;
                DB::table('download_schedules')->where('id', $id)->update($row);
                $taskId = $existing->scheduled_task_id === null ? null : (int) $existing->scheduled_task_id;
            }
            $name = $this->translator->labelOf((array) $doc['i18n']['name'], (string) $doc['key']);
            $task = $this->tasks->put($taskId, 'download', 'download_schedule', $id, $name, $cron, $timezone, $active && (bool) $profile->is_active);
            DB::table('download_schedules')->where('id', $id)->update(['scheduled_task_id' => $task]);
            $this->audit->record($existing === null ? 'download_schedule.created' : 'download_schedule.updated', 'config', null, 'download_profile', (int) $profile->id,
                ['frequency' => $frequency, 'cron' => $cron, 'timezone' => $timezone, 'format' => $format, 'users' => count($users), 'roles' => count($roles)], $actor->id, null, (int) $profile->form_id);

            return DB::table('download_schedules')->where('id', $id)->first();
        });
    }

    public function delete(object $schedule, User $actor): void
    {
        DB::transaction(function () use ($schedule, $actor): void {
            $this->tasks->disable($schedule->scheduled_task_id === null ? null : (int) $schedule->scheduled_task_id);
            // Past runs keep their history; they no longer point at the schedule.
            DB::table('download_jobs')->where('download_schedule_id', $schedule->id)->update(['download_schedule_id' => null]);
            DB::table('download_schedules')->where('id', $schedule->id)->delete();
            if ($schedule->scheduled_task_id !== null) {
                DB::table('scheduled_tasks')->where('id', $schedule->scheduled_task_id)->delete();
            }
            $this->audit->record('download_schedule.deleted', 'config', null, 'download_profile', (int) $schedule->download_profile_id, [], $actor->id);
        });
    }

    /** The scheduler's handler: one run per recipient, under their own permissions. */
    public function run(object $task): void
    {
        $schedule = DB::table('download_schedules')->where('id', $task->owner_id)->first();
        $profile = $schedule === null ? null : DB::table('download_profiles')->where('id', $schedule->download_profile_id)->whereNull('deleted_at')->first();
        if ($schedule === null || $profile === null || ! $schedule->is_active || ! $profile->is_active) {
            return;
        }
        $recipients = json_decode((string) $schedule->recipients, true) ?: [];
        $ids = [];
        foreach (DB::table('users')->whereIn('uuid', $recipients['users'] ?? [])->pluck('id') as $id) {
            $ids = [...$ids, ...$this->members->userIds('user', (int) $id)];
        }
        foreach (DB::table('roles')->whereIn('uuid', $recipients['roles'] ?? [])->pluck('id') as $id) {
            $ids = [...$ids, ...$this->members->userIds('role', (int) $id)];
        }
        $parameters = json_decode((string) ($schedule->parameters ?? 'null'), true) ?: [];
        $skipped = 0;
        foreach (array_values(array_unique($ids)) as $userId) {
            $user = User::query()->find($userId);
            if ($user === null || ! $this->jobs->mayUse($profile, $user)) {
                $skipped++;

                continue;
            }
            $this->jobs->start($profile, $user, (string) $schedule->format, ['parameters' => $parameters], (int) $schedule->id, false);
        }
        if ($skipped > 0) {
            $this->audit->record('download_schedule.skipped', 'operations', null, 'download_profile', (int) $profile->id, ['schedule' => $schedule->uuid, 'recipients' => $skipped]);
        }
    }

    /** Sends a finished scheduled run to its recipient. */
    public function deliver(object $job): void
    {
        $user = User::query()->find($job->user_id);
        $file = $job->file_id === null ? null : DB::table('files')->where('id', $job->file_id)->first();
        $profile = DB::table('download_profiles')->where('id', $job->download_profile_id)->first();
        if ($user === null || $file === null || $profile === null || ! is_string($user->email) || $user->email === '') {
            return;
        }
        $locale = $this->notifier->localeOf($user->id);
        $previous = App::getLocale();
        App::setLocale($locale);
        try {
            $doc = $this->profiles->load($profile);
            $name = $this->translator->labelOf((array) $doc['i18n']['name'], (string) $doc['key']);
            $form = DB::table('forms')->where('id', $profile->form_id)->value('uuid');
            $attach = (int) $file->size_bytes <= (int) $this->settings->get('downloads', 'email_attachment_mb') * 1024 * 1024;
            $link = rtrim((string) config('app.url'), '/').'/app/'.strtolower((string) $form).'?download='.strtolower((string) $job->uuid);
            $body = '<p>'.e(__('downloads.email.intro', ['name' => $name, 'rows' => (int) $job->row_count])).'</p>'
                .($attach ? '<p>'.e(__('downloads.email.attached')).'</p>' : '<p><a href="'.e($link).'">'.e(__('downloads.email.open')).'</a></p><p>'.e(__('downloads.email.sign_in')).'</p>');
            $this->mail->enqueue([
                'source' => 'download', 'locale' => $locale, 'to' => [$user->email], 'subject' => __('downloads.email.subject', ['name' => $name]),
                'html' => '<!doctype html><html lang="'.e($locale).'" dir="'.($locale === 'ar' ? 'rtl' : 'ltr').'"><body style="font-family:sans-serif">'.$body.'</body></html>',
                'form_id' => (int) $profile->form_id, 'attachments' => $attach ? [(int) $file->id] : [],
            ]);
        } finally {
            App::setLocale($previous);
        }
    }
}
