<?php

declare(strict_types=1);

use App\Modules\Forms\Models\Form;
use App\Modules\Notifications\EmailQueue;
use App\Modules\Operations\AlertRules;
use App\Modules\Records\Runtime\FormRuntimes;
use App\Modules\Records\Runtime\SubmissionJournal;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

require_once __DIR__.'/helpers.php';

beforeEach(function () {
    $this->completeSetup();
    $this->admin = $this->superAdmin();
    $this->flushSession();
    $this->actingAs($this->admin, 'web');
});

afterEach(function () {
    Carbon::setTestNow();
});

/** A published form "visits" (visitor required, notes) and its runtime. */
function opsForm(TestCase $t): array
{
    [, $form] = createForm($t, 'visits');
    saveDraft($t, $form, [], [fieldDoc('visitor', 'text', null, ['validation' => ['required' => true]]), fieldDoc('notes', 'text')])->assertOk();
    expect(publish($t, $form)['status'])->toBe('applied');
    $rt = app(FormRuntimes::class)->forForm(Form::query()->where('uuid', $form)->firstOrFail());

    return ['form' => $form, 'rt' => $rt];
}

/** A submission the system failed to save (not one the person was told to fix). */
function failedSubmission(array $w, int $userId, array $payload): string
{
    $journal = app(SubmissionJournal::class);
    $entry = $journal->open((string) Str::uuid7(), $w['rt']->form->id, $w['rt']->versionId, 'create', 'ui', $userId, $payload, null, null);
    $journal->failed($entry['id'], new RuntimeException('Deadlock found when trying to get lock'));

    return strtolower($entry['uuid']);
}

it('is only for those who manage operations, and reports the health of every service', function () {
    $health = $this->getJson('/api/v1/ops/health')->assertOk()->json('data');
    expect($health['checks']['database'])->toBe('ok')->and($health['checks'])->toHaveKeys(['cache', 'redis', 'smtp', 'virus_scanner'])
        ->and($health['queues'])->toHaveKeys(['default', 'heavy', 'mail', 'schema'])->and($health['storage'])->toHaveKey('used_percent')
        ->and(collect($this->getJson('/api/v1/admin/console')->json('data.areas'))->pluck('key')->all())->toContain('operations_center');

    $this->flushSession();
    $this->actingAs($this->makeUser(['user']), 'web');
    foreach (['/ops/health', '/ops/emails', '/ops/submissions', '/ops/jobs', '/ops/alert-rules'] as $path) {
        $this->getJson('/api/v1'.$path)->assertForbidden();
    }
});

it('lists e-mails with stuck ones, resends to changed recipients, cancels and shows the HTML in a sandbox', function () {
    $queue = app(EmailQueue::class);
    Carbon::setTestNow(Carbon::parse('2026-10-13 08:00:00', 'UTC'));
    $stuck = $queue->enqueue(['source' => 'system', 'locale' => 'en', 'to' => ['a@example.test'], 'subject' => 'Weekly report', 'html' => '<p>Hello <script>x()</script></p>', 'delay_minutes' => 1]);
    $later = $queue->enqueue(['source' => 'system', 'locale' => 'en', 'to' => ['b@example.test'], 'subject' => 'Reminder', 'html' => '<p>Later</p>', 'delay_minutes' => 600]);
    DB::table('email_queue')->where('id', $stuck)->update(['last_error' => 'Connection refused']);
    Carbon::setTestNow(Carbon::parse('2026-10-13 09:00:00', 'UTC'));

    $listed = $this->getJson('/api/v1/ops/emails?status=stuck')->assertOk();
    expect($listed->json('meta.total'))->toBe(1)->and($listed->json('data.0.subject'))->toBe('Weekly report')->and($listed->json('data.0.error'))->toBe('Connection refused');
    $uuid = $listed->json('data.0.uuid');
    expect($this->getJson('/api/v1/ops/health')->json('data.stuck_emails'))->toBe(1);

    $url = $this->getJson("/api/v1/ops/emails/{$uuid}/html")->assertOk()->json('data.url');
    $html = $this->get($url)->assertOk();
    expect($html->getContent())->toContain('Hello')->and($html->headers->get('Content-Security-Policy'))->toContain("default-src 'none'");

    $this->postJson('/api/v1/ops/emails/resend', ['emails' => [$uuid], 'recipients' => ['to' => ['not an address']]])->assertStatus(422);
    $resent = $this->postJson('/api/v1/ops/emails/resend', ['emails' => [$uuid], 'recipients' => ['to' => ['c@example.test']]])->assertOk()->json('data.resent.0');
    expect(json_decode((string) DB::table('email_queue')->where('uuid', $resent)->value('to'), true))->toBe(['c@example.test']);
    $laterUuid = DB::table('email_queue')->where('id', $later)->value('uuid');
    expect($this->postJson('/api/v1/ops/emails/cancel', ['emails' => [$laterUuid]])->json('data.cancelled'))->toBe(1)
        ->and(DB::table('audit_logs')->whereIn('event', ['operations.email_resent', 'operations.email_cancelled'])->count())->toBe(2);
});

it('retries a failed submission as its submitter and only once, after an edit, and discards with a reason', function () {
    $w = opsForm($this);
    $person = $this->makeUser(['super_admin']);
    $first = failedSubmission($w, $person->id, ['visitor' => 'Nora', 'notes' => 'gate 2']);
    $second = failedSubmission($w, $person->id, ['visitor' => 'Sami']);
    // A save the person was told to fix is not a system failure and is not listed by default.
    $journal = app(SubmissionJournal::class);
    $rejected = $journal->open((string) Str::uuid7(), $w['rt']->form->id, $w['rt']->versionId, 'create', 'ui', $person->id, ['visitor' => null], null, null);
    $journal->rejected($rejected['id'], 'Visitor is required.');

    $listed = $this->getJson('/api/v1/ops/submissions')->assertOk();
    expect($listed->json('meta.total'))->toBe(2)->and($this->getJson('/api/v1/ops/submissions?status=rejected')->json('meta.total'))->toBe(1);
    $shown = $this->getJson("/api/v1/ops/submissions/{$first}")->assertOk()->json('data');
    expect($shown['payload'])->toBe(['visitor' => 'Nora', 'notes' => 'gate 2'])->and($shown['error'])->toContain('Deadlock')->and($shown['user']['name'])->toBe($person->name);

    $this->patchJson("/api/v1/ops/submissions/{$first}/payload", ['payload' => ['visitor' => 'Nora Saleh', 'notes' => 'gate 2', 'nope' => 1]])->assertStatus(422);
    $this->patchJson("/api/v1/ops/submissions/{$first}/payload", ['payload' => ['visitor' => 'Nora Saleh', 'notes' => 'gate 2']])->assertOk()->assertJsonPath('data.edited', true);
    $res = $this->postJson('/api/v1/ops/submissions/retry', ['submissions' => [$first]])->assertOk()->json('data');
    expect($res['succeeded'])->toBe(1);
    $row = DB::table('submission_journal')->where('uuid', $first)->first();
    expect($row->status)->toBe('processed')->and(DB::table($w['rt']->table)->where('visitor', 'Nora Saleh')->value('created_by'))->toBe($person->id);
    // Retrying again changes nothing: the record exists once.
    expect($this->postJson('/api/v1/ops/submissions/retry', ['submissions' => [$first]])->json('data.results.0.ok'))->toBeFalse()
        ->and(DB::table($w['rt']->table)->count())->toBe(1);

    $this->postJson("/api/v1/ops/submissions/{$second}/discard", ['reason' => ''])->assertStatus(422);
    $this->postJson("/api/v1/ops/submissions/{$second}/discard", ['reason' => 'Entered again by phone'])->assertOk()->assertJsonPath('data.status', 'discarded');
    expect($this->getJson('/api/v1/ops/submissions')->json('meta.total'))->toBe(0)
        ->and(DB::table('audit_logs')->whereIn('event', ['operations.submission_edited', 'operations.submission_retried', 'operations.submission_discarded'])->count())->toBe(4);

    // A submitter who left cannot be acted for.
    $third = failedSubmission($w, $person->id, ['visitor' => 'Huda']);
    DB::table('users')->where('id', $person->id)->update(['status' => 'disabled']);
    expect($this->postJson('/api/v1/ops/submissions/retry', ['submissions' => [$third]])->json('data.results.0.ok'))->toBeFalse();
});

it('gathers failed jobs, retries a scheduled task and dismisses a queue failure with a reason', function () {
    $now = Carbon::now('UTC')->format('Y-m-d H:i:s.u');
    $task = DB::table('scheduled_tasks')->insertGetId([
        'uuid' => (string) Str::uuid7(), 'organization_id' => (int) DB::table('organizations')->value('id'), 'created_at' => $now, 'updated_at' => $now, 'kind' => 'download', 'owner_type' => 'download_schedule', 'owner_id' => 1,
        'name' => 'Monthly visits', 'cron_expression' => '0 6 1 * *', 'timezone' => 'UTC', 'is_enabled' => true,
        'next_run_at' => Carbon::now('UTC')->addMonth()->format('Y-m-d H:i:s.u'), 'last_run_at' => $now, 'last_status' => 'failed', 'last_error' => 'Disk full',
    ]);
    $failed = DB::table('failed_jobs')->insertGetId(['uuid' => (string) Str::uuid(), 'connection' => 'redis', 'queue' => 'heavy',
        'payload' => json_encode(['displayName' => 'App\\Modules\\Records\\Jobs\\RunExportJob']), 'exception' => "RuntimeException: token=abc123 broke\n#0 trace", 'failed_at' => now()]);

    $jobs = $this->getJson('/api/v1/ops/jobs')->assertOk();
    expect($jobs->json('meta.total'))->toBe(2)->and(collect($jobs->json('data'))->pluck('kind')->sort()->values()->all())->toBe(['queue_job', 'scheduled_task']);
    $queued = collect($jobs->json('data'))->firstWhere('kind', 'queue_job');
    expect($queued['subject'])->toBe('RunExportJob · heavy')->and($queued['error'])->toContain('token=«masked»');

    $this->postJson("/api/v1/ops/jobs/scheduled_task/{$task}/retry")->assertStatus(202);
    expect(Carbon::parse((string) DB::table('scheduled_tasks')->where('id', $task)->value('next_run_at'))->isFuture())->toBeFalse();
    $this->postJson("/api/v1/ops/jobs/queue_job/{$failed}/discard", ['reason' => ''])->assertStatus(422);
    $this->postJson("/api/v1/ops/jobs/queue_job/{$failed}/discard", ['reason' => 'Export re-run by its owner'])->assertOk();
    expect($this->getJson('/api/v1/ops/jobs?kind=queue_job')->json('meta.total'))->toBe(0)
        ->and(DB::table('failed_jobs')->where('id', $failed)->exists())->toBeTrue()
        ->and(DB::table('audit_logs')->whereIn('event', ['operations.job_retried', 'operations.job_dismissed'])->count())->toBe(2);
});

it('alerts the chosen roles when a measure reaches its threshold, once per cooldown', function () {
    $w = opsForm($this);
    $role = DB::table('roles')->where('key', 'super_admin')->value('uuid');
    $hash = $this->getJson('/api/v1/ops/alert-rules')->assertOk()->json('data.hash');
    $rule = ['uuid' => (string) Str::uuid(), 'metric' => 'failed_submissions', 'threshold' => 2, 'window' => 60, 'roles' => [$role], 'cooldown' => 120, 'active' => true];
    $this->putJson('/api/v1/ops/alert-rules', ['rules' => [array_replace($rule, ['metric' => 'failed_integrations'])], 'base_hash' => $hash])->assertStatus(422);
    $this->putJson('/api/v1/ops/alert-rules', ['rules' => [array_replace($rule, ['roles' => []])], 'base_hash' => $hash])->assertStatus(422);
    $saved = $this->putJson('/api/v1/ops/alert-rules', ['rules' => [$rule], 'base_hash' => $hash])->assertOk()->json('data');
    $this->putJson('/api/v1/ops/alert-rules', ['rules' => [], 'base_hash' => $hash])->assertStatus(409);

    failedSubmission($w, $this->admin->id, ['visitor' => 'One']);
    expect(app(AlertRules::class)->evaluate())->toBe(0);
    failedSubmission($w, $this->admin->id, ['visitor' => 'Two']);
    expect($this->getJson('/api/v1/ops/alert-rules')->json("data.values.{$rule['uuid']}"))->toBe(2)
        ->and(app(AlertRules::class)->evaluate())->toBe(1)->and(app(AlertRules::class)->evaluate())->toBe(0);
    $mail = DB::table('email_queue')->where('source', 'alert')->first();
    expect($mail->subject)->toBe('Alert: Failed submissions reached 2')->and(json_decode((string) $mail->to, true))->toContain($this->admin->email)
        ->and(DB::table('audit_logs')->where('event', 'operations.alert_triggered')->count())->toBe(1)
        ->and($saved['rules'][0]['roles'])->toBe([strtolower((string) $role)]);
});
