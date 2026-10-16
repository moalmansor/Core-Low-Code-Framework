<?php

declare(strict_types=1);

use App\Modules\Automation\AutomationEngine;
use App\Modules\Automation\WatchedFolders;
use App\Modules\Scheduling\ScheduledTasks;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
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

/**
 * Requests (subject, priority, notes, due date, attachment; open → closed)
 * and follow-ups that link to a request.
 *
 * @return array<string, mixed>
 */
function automationForms(TestCase $t): array
{
    [, $requests] = createForm($t, 'requests');
    $f = [
        'subject' => fieldDoc('subject', 'text', null, ['validation' => ['required' => true]]),
        'priority' => fieldDoc('priority', 'select', null, ['options' => ['source' => 'static', 'static' => staticOptions(['low', 'high'])]]),
        'notes' => fieldDoc('notes', 'textarea'),
        'counter' => fieldDoc('counter', 'number'),
        'due' => fieldDoc('due', 'date'),
        'attachment' => fieldDoc('attachment', 'file'),
    ];
    saveDraft($t, $requests, [], array_values($f))->assertOk();
    $open = statusDoc('open', true);
    $closed = statusDoc('closed', false, true);
    $close = transitionDoc('close', $open, $closed);
    saveWorkflow($t, $requests, [$open, $closed], [$close])->assertOk();
    expect(publish($t, $requests)['status'])->toBe('applied');

    [, $followups] = createForm($t, 'followups');
    $relation = ['uuid' => uid(), 'key' => 'request', 'type' => 'many_to_one', 'target' => $requests, 'kind' => 'reference', 'onDelete' => 'restrict', 'display' => $f['subject']['uuid'], 'value' => null, 'inverse' => null];
    $title = fieldDoc('title', 'text');
    $link = fieldDoc('request', 'lookup', null, ['relation' => $relation['uuid']]);
    saveDraft($t, $followups, [], [$title, $link], [$relation])->assertOk();
    expect(publish($t, $followups)['status'])->toBe('applied');

    return ['requests' => $requests, 'followups' => $followups, 'f' => $f, 'open' => $open, 'closed' => $closed, 'close' => $close, 'title' => $title, 'link' => $link];
}

/** @return array<string, mixed> */
function automationDoc(string $key, array $triggers, array $steps, array $extra = []): array
{
    return array_replace([
        'key' => $key, 'i18n' => ['name' => ['en' => ucfirst(str_replace('_', ' ', $key)), 'ar' => $key], 'description' => []], 'enabled' => true,
        'triggers' => $triggers, 'condition' => null, 'steps' => $steps, 'runAs' => 'triggering_user', 'runAsUser' => null,
        'concurrency' => 5, 'retry' => ['maxAttempts' => 3, 'backoffSeconds' => [60]], 'maxRecords' => 1000, 'confirmAbove' => null, 'maxChainDepth' => 3,
    ], $extra);
}

/** @return array<string, mixed> */
function autoTrigger(string $type, array $extra = []): array
{
    return ['uuid' => uid(), 'type' => $type] + $extra;
}

/** @return array<string, mixed> */
function autoStep(string $type, array $config, array $extra = []): array
{
    return array_replace(['uuid' => uid(), 'type' => $type, 'config' => $config, 'condition' => null, 'onFailure' => 'stop'], $extra);
}

/** @return array<string, mixed> */
function autoText(string $v): array
{
    return ['k' => 'lit', 't' => 'text', 'v' => $v];
}

/** @return array<string, mixed> */
function autoIs(string $field, string $value): array
{
    return ['k' => 'bin', 'op' => '=', 'a' => ['k' => 'ref', 'scope' => 'record', 'path' => [$field]], 'b' => autoText($value)];
}

function createAutomation(TestCase $t, string $form, array $doc): TestResponse
{
    return $t->postJson("/api/v1/forms/{$form}/automations", ['automation' => $doc]);
}

/** @return list<object> runs of an automation, oldest first */
function automationRuns(string $uuid): array
{
    $id = DB::table('automations')->where('uuid', $uuid)->value('id');

    return DB::table('automation_runs')->where('automation_id', $id)->orderBy('id')->get()->all();
}

/** Moves the clock; the session follows (idle sign-out would otherwise end it). */
function autoTravel(TestCase $t, string $at): void
{
    Carbon::setTestNow(Carbon::parse($at, 'UTC'));
    $t->flushSession();
    $t->actingAs($t->admin, 'web');
}

function autoTable(string $form): string
{
    return (string) DB::table('forms')->where('uuid', $form)->value('table_name');
}

/** @return array<string, mixed> */
function requestValues(TestCase $t, string $form, string $uuid): array
{
    return $t->getJson("/api/v1/r/{$form}/{$uuid}")->assertOk()->json('data');
}

it('runs steps when a record is created, as the person who created it, only when the condition holds', function () {
    $w = automationForms($this);
    $doc = automationDoc('escalate_high', [autoTrigger('record_created')], [
        autoStep('update_fields', ['set' => [['field' => $w['f']['notes']['uuid'], 'value' => autoText('Escalated')]]]),
        autoStep('change_status', ['transition' => $w['close']['uuid']]),
        autoStep('create_linked_record', ['form' => $w['followups'], 'linkField' => $w['link']['uuid'],
            'prefill' => [['field' => $w['title']['uuid'], 'value' => ['k' => 'ref', 'scope' => 'record', 'path' => ['subject']]]]]),
    ], ['condition' => autoIs('priority', 'high')]);
    $a = createAutomation($this, $w['requests'], $doc)->assertCreated()->json('data');
    expect($a['automation']['steps'])->toHaveCount(3)->and($a['automation']['runAs'])->toBe('triggering_user');

    $high = $this->postJson("/api/v1/r/{$w['requests']}", ['values' => ['subject' => 'Broken lift', 'priority' => 'high']])->assertCreated()->json('data');
    $low = $this->postJson("/api/v1/r/{$w['requests']}", ['values' => ['subject' => 'Paint', 'priority' => 'low']])->assertCreated()->json('data');

    $record = requestValues($this, $w['requests'], $high['uuid']);
    expect($record['values']['notes'])->toBe('Escalated')
        ->and($record['system']['status']['key'] ?? null)->toBe('closed')
        ->and(DB::table('status_history')->where('source', 'automation')->count())->toBe(1);
    $followup = DB::table(autoTable($w['followups']))->first();
    expect($followup)->not->toBeNull();
    expect(requestValues($this, $w['requests'], $low['uuid'])['values']['notes'])->toBeNull();

    $runs = automationRuns($a['automation']['uuid']);
    expect(array_map(static fn ($r) => [$r->status, (int) $r->records_affected, $r->trigger_type], $runs))
        ->toBe([['succeeded', 1, 'record_created'], ['succeeded', 0, 'record_created']]);
    // The history: each step with its outcome; the second run only notes that the condition did not hold.
    $list = $this->getJson("/api/v1/automations/{$a['automation']['uuid']}/runs")->assertOk()->json('data');
    expect($list['total'])->toBe(2);
    $detail = $this->getJson('/api/v1/automation-runs/'.$runs[0]->uuid)->assertOk()->json('data');
    expect(array_column($detail['log'], 'status'))->toBe(['succeeded', 'succeeded', 'succeeded'])
        ->and($detail['record_list'][0]['uuid'])->toBe($high['uuid']);
    $skipped = $this->getJson('/api/v1/automation-runs/'.$runs[1]->uuid)->assertOk()->json('data');
    expect($skipped['log'][0]['status'])->toBe('skipped');
});

it('fires on a changed field, a status change and a condition that becomes true', function () {
    $w = automationForms($this);
    $note = static fn (string $text) => autoStep('update_fields', ['set' => [['field' => $w['f']['subject']['uuid'], 'value' => autoText($text)]]]);
    $changed = createAutomation($this, $w['requests'], automationDoc('on_priority', [autoTrigger('field_changed', ['field' => $w['f']['priority']['uuid']])], [$note('Priority changed')]))->assertCreated()->json('data.automation.uuid');
    $closed = createAutomation($this, $w['requests'], automationDoc('on_close', [autoTrigger('status_changed', ['fromStatus' => $w['open']['uuid'], 'toStatus' => $w['closed']['uuid']])], [
        autoStep('send_notification', ['template' => null, 'recipients' => ['to' => [['type' => 'creator', 'ref' => null]]]]),
    ]))->assertCreated()->json('data.automation.uuid');
    $became = createAutomation($this, $w['requests'], automationDoc('became_high', [autoTrigger('condition_true')], [
        autoStep('update_fields', ['set' => [['field' => $w['f']['notes']['uuid'], 'value' => autoText('Now high')]]]),
    ], ['condition' => autoIs('priority', 'high')]))->assertCreated()->json('data.automation.uuid');

    $r = $this->postJson("/api/v1/r/{$w['requests']}", ['values' => ['subject' => 'Leak', 'priority' => 'low']])->assertCreated()->json('data');
    // Notes changes: neither the priority nor the condition moved.
    $v = requestValues($this, $w['requests'], $r['uuid']);
    $this->patchJson("/api/v1/r/{$w['requests']}/{$r['uuid']}", ['values' => ['notes' => 'x'], 'row_version' => $v['row_version']])->assertOk();
    expect(automationRuns($changed))->toHaveCount(0)->and(automationRuns($became))->toHaveCount(0);

    $v = requestValues($this, $w['requests'], $r['uuid']);
    $this->patchJson("/api/v1/r/{$w['requests']}/{$r['uuid']}", ['values' => ['priority' => 'high'], 'row_version' => $v['row_version']])->assertOk();
    $v = requestValues($this, $w['requests'], $r['uuid']);
    expect($v['values']['subject'])->toBe('Priority changed')->and($v['values']['notes'])->toBe('Now high')
        ->and(automationRuns($changed))->toHaveCount(1)->and(automationRuns($became))->toHaveCount(1);
    // Saving again while it stays high does not fire the condition again.
    $this->patchJson("/api/v1/r/{$w['requests']}/{$r['uuid']}", ['values' => ['notes' => 'still'], 'row_version' => $v['row_version']])->assertOk();
    expect(automationRuns($became))->toHaveCount(1);

    $v = requestValues($this, $w['requests'], $r['uuid']);
    $this->postJson("/api/v1/r/{$w['requests']}/{$r['uuid']}/transitions/{$w['close']['uuid']}", ['row_version' => $v['row_version']])->assertOk();
    expect(automationRuns($closed)[0]->status ?? null)->toBe('succeeded')
        ->and(DB::table('in_app_notifications')->where('type', 'automation')->where('user_id', $this->admin->id)->count())->toBe(1);
});

it('stops a chain of automations that keeps triggering itself', function () {
    $w = automationForms($this);
    $bump = ['k' => 'bin', 'op' => '+', 'a' => ['k' => 'call', 'fn' => 'coalesce', 'args' => [['k' => 'ref', 'scope' => 'record', 'path' => ['counter']], ['k' => 'lit', 't' => 'number', 'v' => '0']]], 'b' => ['k' => 'lit', 't' => 'number', 'v' => '1']];
    $loop = createAutomation($this, $w['requests'], automationDoc('loop', [autoTrigger('record_updated')], [
        autoStep('update_fields', ['set' => [['field' => $w['f']['counter']['uuid'], 'value' => $bump]]]),
    ], ['maxChainDepth' => 2]))->assertCreated()->json('data.automation.uuid');
    $r = $this->postJson("/api/v1/r/{$w['requests']}", ['values' => ['subject' => 'Loop']])->assertCreated()->json('data');
    $v = requestValues($this, $w['requests'], $r['uuid']);
    $this->patchJson("/api/v1/r/{$w['requests']}/{$r['uuid']}", ['values' => ['notes' => 'go'], 'row_version' => $v['row_version']])->assertOk();

    $runs = automationRuns($loop);
    expect(array_map(static fn ($r) => [$r->status, (int) $r->chain_depth], $runs))
        ->toBe([['succeeded', 0], ['succeeded', 1], ['succeeded', 2], ['skipped_loop', 3]])
        ->and((int) requestValues($this, $w['requests'], $r['uuid'])['values']['counter'])->toBe(3)
        ->and((int) $runs[3]->parent_run_id)->toBe((int) $runs[2]->id)
        ->and(DB::table('audit_logs')->where('event', 'automation.loop_stopped')->count())->toBe(1);
});

it('acts with the run-as user\'s permissions, and only those who manage permissions may make it act as someone else', function () {
    $w = automationForms($this);
    $role = DB::table('roles')->where('key', 'user')->first();
    grantPermission((int) $role->id, "form.{$w['requests']}.view", 'role');
    grantPermission((int) $role->id, "form.{$w['requests']}.create", 'role');
    $clerk = $this->makeUser(['user']);
    $a = createAutomation($this, $w['requests'], automationDoc('clerk_edits', [autoTrigger('record_created')], [
        autoStep('update_fields', ['set' => [['field' => $w['f']['notes']['uuid'], 'value' => autoText('changed')]]]),
    ]))->assertCreated()->json('data.automation.uuid');

    // The clerk may create but not edit: the step fails as the clerk would.
    $this->flushSession();
    $this->actingAs($clerk, 'web');
    $this->postJson("/api/v1/r/{$w['requests']}", ['values' => ['subject' => 'Mine']])->assertCreated();
    $run = automationRuns($a)[0];
    expect($run->status)->toBe('failed')->and(json_decode((string) $run->steps_log, true)[0]['status'])->toBe('failed');

    // An automation manager without "Manage permissions" cannot make it run as someone else.
    $manager = $this->makeUser(['user']);
    grantPermission($manager->id, 'system.manage_automations');
    $this->flushSession();
    $this->actingAs($manager, 'web');
    createAutomation($this, $w['requests'], automationDoc('as_admin', [autoTrigger('manual')], [
        autoStep('update_fields', ['set' => [['field' => $w['f']['notes']['uuid'], 'value' => autoText('x')]]]),
    ], ['runAs' => 'specific_user', 'runAsUser' => $this->admin->uuid]))->assertStatus(422)->assertJsonValidationErrors(['runAsUser']);
    createAutomation($this, $w['requests'], automationDoc('as_me', [autoTrigger('manual')], [
        autoStep('update_fields', ['set' => [['field' => $w['f']['notes']['uuid'], 'value' => autoText('x')]]]),
    ], ['runAs' => 'specific_user', 'runAsUser' => $manager->uuid]))->assertCreated();
    // Schedules have nobody who caused them: they need a run-as user.
    createAutomation($this, $w['requests'], automationDoc('nightly', [autoTrigger('schedule', ['schedule' => ['frequency' => 'daily', 'time' => '02:00', 'timezone' => 'Asia/Riyadh']])], [
        autoStep('update_fields', ['set' => [['field' => $w['f']['notes']['uuid'], 'value' => autoText('x')]]]),
    ]))->assertStatus(422)->assertJsonValidationErrors(['runAs']);
    // Without the permission, nothing is listed.
    $this->flushSession();
    $this->actingAs($clerk, 'web');
    $this->getJson('/api/v1/automations')->assertForbidden();
});

it('runs by hand and on a schedule over the records that meet the condition, asking for confirmation above the threshold', function () {
    $w = automationForms($this);
    foreach (['A' => 'high', 'B' => 'high', 'C' => 'low'] as $subject => $priority) {
        $this->postJson("/api/v1/r/{$w['requests']}", ['values' => ['subject' => $subject, 'priority' => $priority]])->assertCreated();
    }
    $doc = automationDoc('close_high', [autoTrigger('manual'), autoTrigger('schedule', ['schedule' => ['frequency' => 'weekly', 'time' => '06:30', 'weekday' => 0, 'timezone' => 'Asia/Riyadh']])], [
        autoStep('update_fields', ['set' => [['field' => $w['f']['notes']['uuid'], 'value' => autoText('Reviewed')]]]),
    ], ['condition' => autoIs('priority', 'high'), 'runAs' => 'specific_user', 'runAsUser' => $this->admin->uuid, 'confirmAbove' => 1, 'maxRecords' => 5]);
    $a = createAutomation($this, $w['requests'], $doc)->assertCreated()->json('data.automation');

    $run = $this->postJson("/api/v1/automations/{$a['uuid']}/run")->assertStatus(202)->json('data');
    expect($run['status'])->toBe('awaiting_confirmation')->and($run['records'])->toBe(2);
    expect(DB::table(autoTable($w['requests']))->where('notes', 'Reviewed')->count())->toBe(0);
    $done = $this->postJson("/api/v1/automation-runs/{$run['uuid']}/confirm")->assertOk()->json('data');
    expect($done['status'])->toBe('succeeded')->and($done['affected'])->toBe(2)
        ->and(DB::table(autoTable($w['requests']))->where('notes', 'Reviewed')->count())->toBe(2);

    // Above the maximum the run does not start at all.
    $doc['maxRecords'] = 1;
    $doc['confirmAbove'] = null;
    $hash = $this->getJson("/api/v1/automations/{$a['uuid']}")->json('data.hash');
    $this->putJson("/api/v1/automations/{$a['uuid']}", ['automation' => $doc, 'base_hash' => $hash])->assertOk();
    $over = $this->postJson("/api/v1/automations/{$a['uuid']}/run")->assertStatus(202)->json('data');
    expect($over['status'])->toBe('failed')->and($over['error'])->toContain('1');

    // The schedule owns a scheduled task, listed in the manager; when due it starts a run.
    $task = DB::table('scheduled_tasks')->where('kind', 'automation')->first();
    expect($task->cron_expression)->toBe('30 6 * * 0')->and($task->timezone)->toBe('Asia/Riyadh');
    $listed = collect($this->getJson('/api/v1/scheduled-tasks')->assertOk()->json('data'))->firstWhere('kind', 'automation');
    expect($listed['name'])->toBe('Close high')->and($listed['owner']['uuid'])->toBe($a['uuid']);
    app(ScheduledTasks::class)->runDue(Carbon::parse((string) $task->next_run_at, 'UTC')->addMinute());
    expect(collect(automationRuns($a['uuid']))->where('trigger_type', 'schedule')->first()->status ?? null)->toBe('failed');

    // Turned off, its schedule stops too.
    $this->postJson("/api/v1/automations/{$a['uuid']}/enable", ['enabled' => false])->assertOk();
    expect((bool) DB::table('scheduled_tasks')->where('id', $task->id)->value('is_enabled'))->toBeFalse();
    $this->postJson("/api/v1/automations/{$a['uuid']}/run")->assertStatus(422);
});

it('waits before continuing, and retries a failing step on the retry policy before giving up', function () {
    $w = automationForms($this);
    Carbon::setTestNow(Carbon::parse('2026-10-12 08:00:00', 'UTC'));
    $wait = createAutomation($this, $w['requests'], automationDoc('remind_later', [autoTrigger('record_created')], [
        autoStep('wait_delay', ['minutes' => 30]),
        autoStep('update_fields', ['set' => [['field' => $w['f']['notes']['uuid'], 'value' => autoText('Reminded')]]]),
    ]))->assertCreated()->json('data.automation.uuid');
    $r = $this->postJson("/api/v1/r/{$w['requests']}", ['values' => ['subject' => 'Later']])->assertCreated()->json('data');
    $run = automationRuns($wait)[0];
    expect($run->status)->toBe('waiting')->and(Carbon::parse((string) $run->resume_at, 'UTC')->format('H:i'))->toBe('08:30');
    app(AutomationEngine::class)->resumeDue();
    expect(automationRuns($wait)[0]->status)->toBe('waiting');
    autoTravel($this, '2026-10-12 08:31:00');
    app(AutomationEngine::class)->resumeDue();
    expect(automationRuns($wait)[0]->status)->toBe('succeeded')
        ->and(requestValues($this, $w['requests'], $r['uuid'])['values']['notes'])->toBe('Reminded');

    // Emptying a required field fails every time: two attempts, ten seconds apart, then the run fails.
    $retry = createAutomation($this, $w['requests'], automationDoc('always_fails', [autoTrigger('record_created')], [
        autoStep('update_fields', ['set' => [['field' => $w['f']['subject']['uuid'], 'value' => autoText('')]]], ['onFailure' => 'retry']),
    ], ['retry' => ['maxAttempts' => 2, 'backoffSeconds' => [10]]]))->assertCreated()->json('data.automation.uuid');
    $this->postJson("/api/v1/r/{$w['requests']}", ['values' => ['subject' => 'Again']])->assertCreated();
    $run = automationRuns($retry)[0];
    expect($run->status)->toBe('queued')->and((int) $run->attempts)->toBe(1);
    autoTravel($this, '2026-10-12 08:31:11');
    app(AutomationEngine::class)->resumeDue();
    $run = automationRuns($retry)[0];
    expect($run->status)->toBe('failed')->and(array_column(json_decode((string) $run->steps_log, true), 'status'))->toBe(['retrying', 'failed']);
    // A failed run can be retried by hand: the record whose step failed runs again.
    $this->postJson("/api/v1/automation-runs/{$run->uuid}/retry")->assertOk();
    $run = automationRuns($retry)[0];
    expect($run->status)->toBe('queued')->and((int) $run->attempts)->toBe(1)
        ->and(json_decode((string) $run->trigger_ref, true)['records'])->toHaveCount(1);
});

it('tests against a sample record without keeping any change or sending anything', function () {
    $w = automationForms($this);
    $tpl = $this->postJson('/api/v1/notifications/templates', ['key' => 'heads_up', 'form' => $w['requests'], 'subject' => ['en' => 'Heads up'],
        'design' => ['blocks' => [['type' => 'text', 'text' => ['en' => 'Look at {{ subject }}']]]]])->assertCreated()->json('data.uuid');
    $a = createAutomation($this, $w['requests'], automationDoc('try_me', [autoTrigger('record_created')], [
        autoStep('update_fields', ['set' => [['field' => $w['f']['notes']['uuid'], 'value' => autoText('Tested')]]]),
        autoStep('send_email', ['template' => $tpl, 'recipients' => ['to' => [['type' => 'static', 'ref' => 'boss@example.test']], 'cc' => [], 'bcc' => []], 'documents' => []]),
        autoStep('wait_delay', ['minutes' => 60]),
    ], ['runAs' => 'specific_user', 'runAsUser' => $this->admin->uuid, 'enabled' => false]))->assertCreated()->json('data.automation.uuid');
    $r = $this->postJson("/api/v1/r/{$w['requests']}", ['values' => ['subject' => 'Sample']])->assertCreated()->json('data');
    $before = DB::table('email_queue')->count();

    $test = $this->postJson("/api/v1/automations/{$a}/test", ['record' => $r['uuid']])->assertOk()->json('data');
    expect($test['test'])->toBeTrue()->and($test['status'])->toBe('succeeded')
        ->and(array_column($test['log'], 'status'))->toBe(['succeeded', 'simulated', 'simulated'])
        ->and(requestValues($this, $w['requests'], $r['uuid'])['values']['notes'])->toBeNull()
        ->and(DB::table('email_queue')->count())->toBe($before);
});

it('starts a run when a date is reached, once per date', function () {
    $w = automationForms($this);
    Carbon::setTestNow(Carbon::parse('2026-10-12 09:00:00', 'UTC'));
    $a = createAutomation($this, $w['requests'], automationDoc('before_due', [autoTrigger('date_reached', ['dateField' => $w['f']['due']['uuid'], 'offsetMinutes' => -1440])], [
        autoStep('update_fields', ['set' => [['field' => $w['f']['notes']['uuid'], 'value' => autoText('Due tomorrow')]]]),
    ], ['runAs' => 'specific_user', 'runAsUser' => $this->admin->uuid]))->assertCreated()->json('data.automation.uuid');
    $soon = $this->postJson("/api/v1/r/{$w['requests']}", ['values' => ['subject' => 'Soon', 'due' => '2026-10-14']])->assertCreated()->json('data');
    $this->postJson("/api/v1/r/{$w['requests']}", ['values' => ['subject' => 'Later', 'due' => '2026-11-30']])->assertCreated();
    $engine = app(AutomationEngine::class);
    expect($engine->datesReached())->toBe(0);
    autoTravel($this, '2026-10-13 00:05:00');
    expect($engine->datesReached())->toBe(1)->and($engine->datesReached())->toBe(0);
    expect(requestValues($this, $w['requests'], $soon['uuid'])['values']['notes'])->toBe('Due tomorrow')
        ->and(automationRuns($a)[0]->trigger_type)->toBe('date_reached');
});

it('turns files dropped in a watched folder into records, setting aside the ones it cannot accept', function () {
    $w = automationForms($this);
    Storage::fake(WatchedFolders::DISK);
    $disk = Storage::disk(WatchedFolders::DISK);
    $a = createAutomation($this, $w['requests'], automationDoc('inbox', [autoTrigger('watched_folder', ['folder' => 'inbox/requests', 'fileField' => $w['f']['attachment']['uuid'], 'nameField' => $w['f']['subject']['uuid']])], [
        autoStep('update_fields', ['set' => [['field' => $w['f']['notes']['uuid'], 'value' => ['k' => 'ref', 'scope' => 'record', 'path' => ['subject']]]]]),
    ], ['runAs' => 'specific_user', 'runAsUser' => $this->admin->uuid]))->assertCreated()->json('data.automation.uuid');
    createAutomation($this, $w['requests'], automationDoc('outside', [autoTrigger('watched_folder', ['folder' => '../etc', 'fileField' => $w['f']['attachment']['uuid']])], [
        autoStep('update_fields', ['set' => [['field' => $w['f']['subject']['uuid'], 'value' => autoText('x')]]]),
    ], ['runAs' => 'specific_user', 'runAsUser' => $this->admin->uuid]))->assertStatus(422)->assertJsonValidationErrors(['triggers.0.folder']);

    $disk->put('inbox/requests/order.txt', "Order 1\n");
    $disk->put('inbox/requests/tool.exe', 'MZ');
    $disk->put('inbox/requests/fresh.txt', "still copying\n");
    touch($disk->path('inbox/requests/order.txt'), time() - 120);
    touch($disk->path('inbox/requests/tool.exe'), time() - 120);

    expect(app(WatchedFolders::class)->scan())->toBe(2);
    $record = DB::table(autoTable($w['requests']))->where('subject', 'order.txt')->where('notes', 'order.txt')->first();
    expect($record)->not->toBeNull()
        ->and(DB::table('files')->where('original_name', 'order.txt')->where('record_id', $record->id)->exists())->toBeTrue()
        ->and(automationRuns($a)[0]->trigger_type)->toBe('watched_folder')
        ->and($disk->files('inbox/requests'))->toBe(['inbox/requests/fresh.txt'])
        ->and(count($disk->files('inbox/requests/processed')))->toBe(1)
        ->and(collect($disk->files('inbox/requests/failed'))->filter(static fn ($f) => str_ends_with($f, '.error.txt'))->count())->toBe(1);
});

it('rejects automations that cannot work, and deletes one with its history', function () {
    $w = automationForms($this);
    createAutomation($this, $w['requests'], automationDoc('after_delete', [autoTrigger('record_deleted')], [
        autoStep('update_fields', ['set' => [['field' => $w['f']['notes']['uuid'], 'value' => autoText('x')]]]),
    ]))->assertStatus(422)->assertJsonValidationErrors(['steps.0.type']);
    createAutomation($this, $w['requests'], automationDoc('wait_all', [autoTrigger('manual')], [autoStep('wait_delay', ['minutes' => 5])],
        ['runAs' => 'specific_user', 'runAsUser' => $this->admin->uuid]))->assertStatus(422)->assertJsonValidationErrors(['steps.0.type']);
    createAutomation($this, $w['requests'], automationDoc('bad', [autoTrigger('inbound_webhook')], []))->assertStatus(422)->assertJsonValidationErrors(['triggers.0.inboundEndpoint', 'steps']);

    $a = createAutomation($this, $w['requests'], automationDoc('notify_delete', [autoTrigger('record_deleted')], [
        autoStep('send_notification', ['template' => null, 'recipients' => ['to' => [['type' => 'creator', 'ref' => null]]]]),
    ]))->assertCreated()->json('data');
    $r = $this->postJson("/api/v1/r/{$w['requests']}", ['values' => ['subject' => 'Gone']])->assertCreated()->json('data');
    $v = requestValues($this, $w['requests'], $r['uuid']);
    $this->deleteJson("/api/v1/r/{$w['requests']}/{$r['uuid']}", ['row_version' => $v['row_version']])->assertSuccessful();
    expect(automationRuns($a['automation']['uuid'])[0]->status ?? null)->toBe('succeeded');

    // Someone else saved in between: the stale copy is refused.
    $stale = $a['hash'];
    $this->postJson("/api/v1/automations/{$a['automation']['uuid']}/enable", ['enabled' => false])->assertOk();
    $this->putJson("/api/v1/automations/{$a['automation']['uuid']}", ['automation' => $a['automation'], 'base_hash' => $stale])->assertStatus(409);

    $this->deleteJson("/api/v1/automations/{$a['automation']['uuid']}")->assertNoContent();
    expect(DB::table('automations')->count())->toBe(0)->and(DB::table('automation_runs')->count())->toBe(0)
        ->and(DB::table('audit_logs')->where('event', 'automation.deleted')->exists())->toBeTrue();
});

it('e-mails a download of the record to each recipient, run under their own permissions', function () {
    $w = automationForms($this);
    $profile = $this->postJson("/api/v1/forms/{$w['requests']}/download-profiles", ['profile' => [
        'key' => 'sheet', 'i18n' => ['name' => ['en' => 'Sheet', 'ar' => 'ورقة']], 'formats' => ['csv'], 'fileName' => '{form}',
        'xlsx' => ['styledHeader' => true, 'freezeHeader' => true, 'titleRow' => false], 'csv' => ['delimiter' => ',', 'bom' => true], 'pdf' => ['orientation' => 'landscape', 'pageNumbers' => true],
        'applyUserFilters' => false, 'allowSelected' => false, 'availableIn' => ['record_view'], 'parameters' => [], 'maxRows' => 0, 'active' => true,
        'columns' => [['kind' => 'field', 'path' => ['subject'], 'i18n' => ['header' => []]]], 'filters' => [],
    ]])->assertCreated()->json('data.profile.uuid');
    $outsider = $this->makeUser(['user']);
    $a = createAutomation($this, $w['requests'], automationDoc('mail_sheet', [autoTrigger('record_created')], [
        autoStep('run_download', ['profile' => $profile, 'format' => 'csv', 'scope' => 'record', 'deliverTo' => ['users' => [$this->admin->uuid, $outsider->uuid], 'roles' => []]]),
    ]))->assertCreated()->json('data.automation.uuid');

    $this->postJson("/api/v1/r/{$w['requests']}", ['values' => ['subject' => 'Report me']])->assertCreated();
    $run = automationRuns($a)[0];
    // Only the admin may use the profile: one run, theirs, e-mailed when ready; the outsider is skipped.
    $jobs = DB::table('download_jobs')->where('automation_run_id', $run->id)->get();
    expect($run->status)->toBe('succeeded')->and($jobs)->toHaveCount(1)
        ->and((int) $jobs[0]->user_id)->toBe($this->admin->id)->and((bool) $jobs[0]->deliver_by_email)->toBeTrue()
        ->and($jobs[0]->status)->toBe('completed')
        ->and(DB::table('email_queue')->where('source', 'download')->where('to', 'like', '%'.$this->admin->email.'%')->exists())->toBeTrue();
});

it('starts a run for the record an incoming webhook created, from the inbound webhook trigger', function () {
    $w = automationForms($this);
    $endpoint = $this->postJson('/api/v1/inbound-endpoints', ['form' => $w['requests'], 'kind' => 'webhook', 'slug' => 'requests-in', 'operation' => 'create',
        'mapping' => ['subject' => 'title'], 'signature_header' => 'X-Sig', 'signature_algorithm' => 'hmac_sha256', 'run_as_user' => $this->admin->uuid, 'is_active' => true])->assertCreated();
    // An endpoint of another form is refused.
    $other = $this->postJson('/api/v1/inbound-endpoints', ['form' => $w['followups'], 'kind' => 'webhook', 'slug' => 'followups-in', 'operation' => 'create',
        'mapping' => ['title' => 'title'], 'signature_header' => 'X-Sig', 'signature_algorithm' => 'hmac_sha256', 'run_as_user' => $this->admin->uuid, 'is_active' => true])->assertCreated();
    $doc = automationDoc('mark_inbound', [autoTrigger('inbound_webhook', ['inboundEndpoint' => $other->json('data.uuid')])],
        [autoStep('update_fields', ['set' => [['field' => $w['f']['notes']['uuid'], 'value' => autoText('from the shop')]]])], ['runAs' => 'specific_user', 'runAsUser' => $this->admin->uuid]);
    createAutomation($this, $w['requests'], $doc)->assertStatus(422)->assertJsonStructure(['errors' => ['triggers.0.inboundEndpoint']]);
    $doc['triggers'][0]['inboundEndpoint'] = $endpoint->json('data.uuid');
    $a = createAutomation($this, $w['requests'], $doc)->assertCreated()->json('data.automation.uuid');
    expect($this->getJson("/api/v1/automations/{$a}")->json('data.automation.triggers.0.inboundEndpoint'))->toBe($endpoint->json('data.uuid'));
    // The endpoint cannot be removed while an automation listens to it.
    $this->deleteJson('/api/v1/inbound-endpoints/'.$endpoint->json('data.uuid'))->assertStatus(422);

    $raw = json_encode(['title' => 'Broken printer']);
    $this->flushSession();
    $made = $this->call('POST', '/api/inbound/requests-in', [], [], [], ['CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json',
        'HTTP_X_SIG' => hash_hmac('sha256', $raw, $endpoint->json('data.secret'))], $raw)->assertCreated();
    $this->actingAs($this->admin, 'web');
    $runs = automationRuns($a);
    expect($runs)->toHaveCount(1)->and($runs[0]->status)->toBe('succeeded')->and($runs[0]->trigger_type)->toBe('inbound_webhook')
        ->and(requestValues($this, $w['requests'], $made->json('data.record'))['values']['notes'])->toBe('from the shop');
});
