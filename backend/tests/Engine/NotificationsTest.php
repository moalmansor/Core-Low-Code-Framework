<?php

declare(strict_types=1);

use App\Modules\Notifications\EmailQueue;
use App\Modules\Notifications\InAppNotifier;
use App\Modules\Notifications\Notifier;
use App\Modules\Notifications\Recipients;
use App\Modules\Records\Runtime\FormRuntimes;
use App\Modules\Records\Templating\HtmlPreviews;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Testing\TestResponse;
use Symfony\Component\Mailer\Transport\TransportInterface;
use Symfony\Component\Mime\Email;
use Tests\TestCase;

require_once __DIR__.'/helpers.php';

beforeEach(function () {
    $this->completeSetup();
    $this->admin = $this->superAdmin();
    $this->flushSession();
    $this->actingAs($this->admin, 'web');
});

/** @return array<string, mixed> a template for the workflow form */
function leaveTemplate(TestCase $t, string $form, array $extra = []): array
{
    return $t->postJson('/api/v1/notifications/templates', array_replace_recursive([
        'key' => 'leave_moved', 'form' => $form,
        'subject' => ['en' => 'Request {{ @record_number }}: {{ subject }}', 'ar' => 'الطلب {{ @record_number }}'],
        'preheader' => ['en' => 'Now {{ @status }}'],
        'design' => ['style' => ['accent' => '#0f766e', 'logo' => false], 'blocks' => [
            ['type' => 'heading', 'text' => ['en' => 'Hello {{ @recipient }}']],
            ['type' => 'text', 'text' => ['en' => "The reason given:\n**{{ reason }}**"]],
            ['type' => 'fields', 'fields' => ['subject', 'reason', '@status']],
            ['type' => 'text', 'text' => ['en' => 'Urgent handling'], 'condition' => ['k' => 'call', 'fn' => 'contains', 'args' => [['k' => 'ref', 'scope' => 'record', 'path' => ['subject']], ['k' => 'lit', 't' => 'text', 'v' => 'Urgent']]]],
            ['type' => 'button', 'label' => ['en' => 'Open'], 'link' => 'record'],
        ]],
    ], $extra))->assertCreated()->json('data');
}

/** @return array<string, mixed> */
function ruleDoc(array $extra): array
{
    return array_replace([
        'uuid' => uid(), 'key' => 'r'.substr(str_replace('-', '', uid()), -8), 'name' => ['en' => 'Notify'], 'trigger' => 'record_created',
        'fromStatus' => null, 'toStatus' => null, 'field' => null, 'condition' => null, 'schedule' => null, 'template' => null,
        'channels' => ['in_app'], 'recipients' => ['to' => [['type' => 'creator', 'ref' => null]], 'cc' => [], 'bcc' => []],
        'attachments' => null, 'delayMinutes' => null, 'respectPreferences' => true, 'active' => true,
    ], $extra);
}

function saveRules(TestCase $t, string $form, array $rules): TestResponse
{
    $hash = $t->getJson("/api/v1/forms/{$form}/notification-rules")->assertOk()->json('data.hash');

    return $t->putJson("/api/v1/forms/{$form}/notification-rules", ['rules' => $rules, 'base_hash' => $hash]);
}

/** @return list<Email> */
function sentMails(): array
{
    /** @var TransportInterface $transport */
    $transport = Mail::mailer()->getSymfonyTransport();

    return array_map(static fn ($m) => $m->getOriginalMessage(), iterator_to_array($transport->messages()));
}

it('validates templates, previews them with a real record and sends a test to the admin', function () {
    $wf = buildWorkflowForm($this);
    $form = $wf['form'];
    // A placeholder that names nothing is refused, naming the placeholder.
    $this->postJson('/api/v1/notifications/templates', [
        'key' => 'bad', 'form' => $form, 'subject' => ['en' => 'Hi {{ nothing_here }}'], 'design' => ['blocks' => []],
    ])->assertStatus(422)->assertJsonValidationErrors(['subject.en']);
    $tpl = leaveTemplate($this, $form);
    $rec = $this->postJson("/api/v1/r/{$form}", ['values' => ['subject' => 'Urgent leave', 'reason' => 'Family <b>event</b>']])->assertCreated()->json('data');

    $preview = $this->postJson('/api/v1/notifications/templates/preview', [
        'form' => $form, 'record' => $rec['uuid'], 'subject' => ['en' => 'Request {{ subject }}'], 'design' => $tpl['design'], 'locale' => 'en',
    ])->assertOk()->json('data');
    expect($preview['subject'])->toBe('Request Urgent leave')
        // Values are escaped, the bold markup is the template's own.
        ->and($preview['html'])->toContain('<strong>Family &lt;b&gt;event&lt;/b&gt;</strong>')
        ->and($preview['html'])->toContain('Urgent handling')
        ->and($preview['html'])->toContain('/app/'.$form.'/'.$rec['uuid'])
        ->and($preview['html'])->toContain('#0f766e');

    // Markup comes from the template only, and a bold span that fills to nothing leaves no markers.
    $marked = $this->postJson("/api/v1/r/{$form}", ['values' => ['subject' => '**not bold**']])->assertCreated()->json('data');
    $bare = $this->postJson('/api/v1/notifications/templates/preview', [
        'form' => $form, 'record' => $marked['uuid'], 'subject' => ['en' => 'Re **{{ subject }}**'],
        'design' => ['blocks' => [['type' => 'text', 'text' => ['en' => 'By **{{ reason }}**. Topic {{ subject }}']]]], 'locale' => 'en',
    ])->assertOk()->json('data');
    expect($bare['subject'])->toBe('Re **not bold**')
        ->and($bare['html'])->toContain('By . Topic **not bold**')
        ->and($bare['html'])->not->toContain('<strong>');

    // The designer shows the email through a signed link served under its own policy: inline styles, nothing else.
    $shown = $this->get($bare['url'])->assertOk();
    expect($shown->getContent())->toContain('By . Topic')
        ->and($shown->headers->get('Content-Security-Policy'))->toBe(HtmlPreviews::POLICY)
        ->and($shown->headers->has(HtmlPreviews::HEADER))->toBeFalse();
    $this->get(strtok($bare['url'], '?'))->assertForbidden();

    $this->postJson("/api/v1/notifications/templates/{$tpl['uuid']}/test", ['record' => $rec['uuid'], 'locale' => 'en'])->assertStatus(202);
    $row = DB::table('email_queue')->where('source', 'test')->first();
    expect($row->status)->toBe('sent')->and(json_decode($row->to, true))->toBe([$this->admin->email])
        ->and(sentMails()[0]->getSubject())->toStartWith('Request ');

    // Saving with an old version is a conflict, never an overwrite.
    $this->putJson("/api/v1/notifications/templates/{$tpl['uuid']}", ['key' => 'leave_moved', 'form' => $form, 'subject' => ['en' => 'X'], 'design' => ['blocks' => []], 'base_hash' => str_repeat('0', 64)])
        ->assertStatus(409)->assertJsonPath('code', 'template_changed');
});

it('notifies on a status change by email and in-app, with To, CC and BCC and only the fields each recipient may see', function () {
    $wf = buildWorkflowForm($this);
    $form = $wf['form'];
    $tpl = leaveTemplate($this, $form);
    $clerk = $this->makeUser(['user']);
    // The clerk may view the form, but not the reason.
    $role = DB::table('roles')->where('key', 'user')->value('id');
    grantPermission((int) $role, "form.{$form}.view", 'role');
    $this->putJson("/api/v1/forms/{$form}/access-rules", ['changes' => [[
        'target' => ['type' => 'field', 'uuid' => $wf['reason']['uuid']], 'subject' => ['type' => 'role', 'uuid' => DB::table('roles')->where('id', $role)->value('uuid')],
        'mode' => 'view', 'access' => 'hidden', 'effect' => 'allow',
    ]]])->assertOk();

    saveRules($this, $form, [ruleDoc([
        'key' => 'submitted', 'trigger' => 'status_changed', 'toStatus' => $wf['submitted']['uuid'], 'template' => $tpl['uuid'], 'channels' => ['email', 'in_app'],
        'recipients' => ['to' => [['type' => 'user', 'ref' => $clerk->uuid]], 'cc' => [['type' => 'creator', 'ref' => null]], 'bcc' => [['type' => 'static', 'ref' => 'audit@example.test']]],
    ])])->assertOk();

    $rec = $this->postJson("/api/v1/r/{$form}", ['values' => ['subject' => 'Holiday', 'reason' => 'Secret reason']])->assertCreated()->json('data');
    expect(DB::table('email_queue')->count())->toBe(0);
    $this->postJson("/api/v1/r/{$form}/{$rec['uuid']}/transitions/{$wf['submit']['uuid']}", ['row_version' => 1, 'comment' => 'Go'])->assertOk();

    $mail = DB::table('email_queue')->where('source', 'rule')->first();
    expect($mail->status)->toBe('sent')
        ->and(json_decode($mail->to, true))->toBe([$clerk->email])
        ->and(json_decode($mail->cc, true))->toBe([$this->admin->email])
        ->and(json_decode($mail->bcc, true))->toBe(['audit@example.test'])
        // The clerk receives this message, so a field hidden from the clerk is left out for everyone on it.
        ->and($mail->body_html)->not->toContain('Secret reason')
        ->and($mail->body_html)->toContain('Holiday');
    expect(DB::table('in_app_notifications')->where('user_id', $clerk->id)->where('type', 'rule.status_changed')->count())->toBe(1)
        ->and(DB::table('in_app_notifications')->where('user_id', $clerk->id)->value('title'))->toContain('Holiday');
});

it('fires on field changes and once when a condition becomes true, and respects channel preferences unless mandatory', function () {
    $wf = buildWorkflowForm($this);
    $form = $wf['form'];
    $urgent = ['k' => 'call', 'fn' => 'contains', 'args' => [['k' => 'ref', 'scope' => 'record', 'path' => ['subject']], ['k' => 'lit', 't' => 'text', 'v' => 'Urgent']]];
    saveRules($this, $form, [
        ruleDoc(['key' => 'reason_changed', 'trigger' => 'field_changed', 'field' => $wf['reason']['uuid']]),
        ruleDoc(['key' => 'became_urgent', 'trigger' => 'condition_met', 'condition' => $urgent]),
    ])->assertOk();
    $rec = $this->postJson("/api/v1/r/{$form}", ['values' => ['subject' => 'Holiday']])->assertCreated()->json('data');
    $count = fn (string $type) => DB::table('in_app_notifications')->where('user_id', $this->admin->id)->where('type', $type)->count();
    expect($count('rule.field_changed'))->toBe(0)->and($count('rule.condition_met'))->toBe(0);

    $this->patchJson("/api/v1/r/{$form}/{$rec['uuid']}", ['row_version' => 1, 'values' => ['subject' => 'Urgent: holiday']])->assertOk();
    expect($count('rule.condition_met'))->toBe(1)->and($count('rule.field_changed'))->toBe(0);
    // Still urgent: the condition did not become true again.
    $this->patchJson("/api/v1/r/{$form}/{$rec['uuid']}", ['row_version' => 2, 'values' => ['subject' => 'Urgent: holiday!', 'reason' => 'Family']])->assertOk();
    expect($count('rule.condition_met'))->toBe(1)->and($count('rule.field_changed'))->toBe(1);

    // In-app switched off: the optional rule is silent, a mandatory one still reaches the person.
    $this->patchJson('/api/v1/me/preferences', ['notification_channels' => ['in_app' => false, 'email' => true]])->assertOk()
        ->assertJsonPath('data.notification_channels', ['email' => true, 'in_app' => false]);
    $this->patchJson("/api/v1/r/{$form}/{$rec['uuid']}", ['row_version' => 3, 'values' => ['reason' => 'Trip']])->assertOk();
    expect($count('rule.field_changed'))->toBe(1);
    $rules = $this->getJson("/api/v1/forms/{$form}/notification-rules")->json('data.rules');
    $rules[0]['respectPreferences'] = false;
    saveRules($this, $form, $rules)->assertOk();
    $this->patchJson("/api/v1/r/{$form}/{$rec['uuid']}", ['row_version' => 4, 'values' => ['reason' => 'Rest']])->assertOk();
    expect($count('rule.field_changed'))->toBe(2);
});

it('sends date reminders once per date and validates rules', function () {
    [, $form] = createForm($this, 'contracts');
    $title = fieldDoc('title', 'text');
    $ends = fieldDoc('ends_on', 'date');
    saveDraft($this, $form, [], [$title, $ends])->assertOk();
    expect(publish($this, $form)['status'])->toBe('applied');
    // A reminder needs a date field; email needs a template; To needs someone.
    saveRules($this, $form, [ruleDoc(['trigger' => 'scheduled_reminder', 'channels' => ['email'], 'recipients' => ['to' => [], 'cc' => [], 'bcc' => []]])])
        ->assertStatus(422)->assertJsonValidationErrors(['rules.0.schedule.dateField', 'rules.0.template', 'rules.0.recipients.to']);
    saveRules($this, $form, [ruleDoc(['key' => 'expiry', 'trigger' => 'scheduled_reminder', 'schedule' => ['dateField' => $ends['uuid'], 'offsetMinutes' => -3 * 1440]])])->assertOk();

    $this->postJson("/api/v1/r/{$form}", ['values' => ['title' => 'Soon', 'ends_on' => now('UTC')->addDays(2)->format('Y-m-d')]])->assertCreated();
    $this->postJson("/api/v1/r/{$form}", ['values' => ['title' => 'Later', 'ends_on' => now('UTC')->addDays(30)->format('Y-m-d')]])->assertCreated();
    expect(app(Notifier::class)->reminders())->toBe(1)
        ->and(app(Notifier::class)->reminders())->toBe(0)
        ->and(DB::table('in_app_notifications')->where('type', 'rule.scheduled_reminder')->count())->toBe(1);
});

it('retries a failing email with backoff, then marks it failed with the reason; resend and cancel', function () {
    $queue = app(EmailQueue::class);
    Mail::shouldReceive('html')->andThrow(new RuntimeException('550 Mailbox unavailable'));
    $id = $queue->enqueue(['source' => 'system', 'locale' => 'en', 'to' => ['someone@example.test'], 'subject' => 'Hi', 'html' => '<p>Hi</p>']);
    $row = DB::table('email_queue')->where('id', $id)->first();
    expect($row->status)->toBe('pending')->and((int) $row->attempts)->toBe(1)->and($row->last_error)->toBe('550 Mailbox unavailable')->and($row->next_attempt_at)->not->toBeNull();
    DB::table('email_queue')->where('id', $id)->update(['attempts' => (int) $row->max_attempts - 1, 'next_attempt_at' => now('UTC')->subMinute()->format('Y-m-d H:i:s.u')]);
    $queue->send($id);
    expect(DB::table('email_queue')->where('id', $id)->value('status'))->toBe('failed');
    // The mail service is restored for the rest of the test.
    app()->forgetInstance('mail.manager');
    Mail::clearResolvedInstances();

    $copy = $queue->resend(DB::table('email_queue')->where('id', $id)->first(), ['to' => ['other@example.test']], $this->admin->id);
    expect(DB::table('email_queue')->where('id', $copy)->value('resent_from_id'))->toBe($id)
        ->and(DB::table('email_queue')->where('id', $copy)->value('status'))->toBe('sent');
    $later = $queue->enqueue(['source' => 'system', 'locale' => 'en', 'to' => ['x@example.test'], 'subject' => 'Later', 'html' => '<p>x</p>', 'delay_minutes' => 30]);
    expect($queue->cancel(DB::table('email_queue')->where('id', $later)->first(), $this->admin->id))->toBeTrue()
        ->and(DB::table('email_queue')->where('id', $later)->value('status'))->toBe('cancelled');
});

it('lists a person\'s own notifications and marks them read', function () {
    $other = $this->makeUser(['user']);
    $notifier = app(InAppNotifier::class);
    $mine = $notifier->notifyText($this->admin->id, 'test', 'For me', null, '/app');
    $notifier->notifyText($other->id, 'test', 'For someone else', null, null);
    $list = $this->getJson('/api/v1/me/notifications')->assertOk()->json();
    expect(array_column($list['data'], 'title'))->toBe(['For me'])->and($list['meta']['unread'])->toBe(1);
    $theirs = DB::table('in_app_notifications')->where('user_id', $other->id)->value('uuid');
    $this->postJson("/api/v1/me/notifications/{$theirs}/read")->assertOk()->assertJsonPath('data.unread', 1);
    expect(DB::table('in_app_notifications')->where('user_id', $other->id)->value('read_at'))->toBeNull();
    $uuid = DB::table('in_app_notifications')->where('id', $mine)->value('uuid');
    $this->postJson("/api/v1/me/notifications/{$uuid}/read")->assertOk()->assertJsonPath('data.unread', 0);
});

it('reaches the people a record holds — pickers and email fields here or in linked forms, its assignee and whoever last moved it — read when it is sent', function () {
    // A team form whose lead a ticket reaches through its "team" link.
    [, $team] = createForm($this, 'team');
    $teamName = fieldDoc('name', 'text');
    saveDraft($this, $team, [], [$teamName, fieldDoc('lead', 'user_picker')])->assertOk();
    expect(publish($this, $team)['status'])->toBe('applied');
    [, $ticket] = createForm($this, 'ticket');
    $rel = ['uuid' => uid(), 'key' => 'team', 'type' => 'many_to_one', 'target' => $team, 'kind' => 'reference', 'onDelete' => 'restrict', 'display' => $teamName['uuid'], 'value' => null, 'inverse' => null];
    saveDraft($this, $ticket, [], [
        fieldDoc('title', 'text'), fieldDoc('owner', 'user_picker'), fieldDoc('crew', 'role_picker'), fieldDoc('unit', 'department_picker'),
        fieldDoc('contact', 'email'), fieldDoc('team', 'lookup', null, ['relation' => $rel['uuid']]),
    ], [$rel])->assertOk();
    $open = statusDoc('open', true);
    $closed = statusDoc('closed', false, true);
    $close = transitionDoc('close', $open, $closed);
    saveWorkflow($this, $ticket, [$open, $closed], [$close])->assertOk();
    expect(publish($this, $ticket)['status'])->toBe('applied');

    // The pickers' choices are offered by the field tree, linked ones by their path.
    $tree = $this->getJson("/api/v1/forms/{$ticket}/field-tree")->assertOk()->json('data.fields');
    $kinds = collect($tree)->mapWithKeys(fn ($n) => [$n['key'] => $n['kind']])->all();
    expect($kinds)->toMatchArray(['owner' => 'user', 'crew' => 'role', 'unit' => 'department', 'contact' => 'email', 'title' => null])
        ->and(collect(collect($tree)->firstWhere('key', 'team')['children'])->firstWhere('key', 'lead')['path'])->toBe(['team', 'lead']);

    $owner = $this->makeUser();
    $newOwner = $this->makeUser();
    $lead = $this->makeUser();
    $crewMember = $this->makeUser(['developer']);
    $unitMember = $this->makeUser();
    $known = $this->makeUser(['user'], ['email' => 'known.person@example.test']);
    $assignee = $this->makeUser();
    $closer = $this->makeUser();
    $dept = $this->postJson('/api/v1/departments', ['code' => 'OPS', 'name' => ['en' => 'Operations']])->assertCreated()->json('data.uuid');
    DB::table('users')->where('id', $unitMember->id)->update(['department_id' => DB::table('departments')->where('uuid', $dept)->value('id')]);
    $auditor = DB::table('roles')->where('key', 'developer')->value('uuid');
    grantPermission($closer->id, "form.{$ticket}.view");
    grantPermission($closer->id, "transition.{$close['uuid']}.perform");

    // A text field names nobody; "the person running it" belongs to actions.
    saveRules($this, $ticket, [ruleDoc(['recipients' => ['to' => [['type' => 'field', 'ref' => 'title']], 'cc' => [], 'bcc' => []]])])
        ->assertStatus(422)->assertJsonValidationErrors(['rules.0.recipients.to.0']);
    saveRules($this, $ticket, [ruleDoc(['recipients' => ['to' => [['type' => 'actor', 'ref' => null]], 'cc' => [], 'bcc' => []]])])
        ->assertStatus(422)->assertJsonValidationErrors(['rules.0.recipients.to.0']);

    $tpl = $this->postJson('/api/v1/notifications/templates', [
        'key' => 'ticket_closed', 'form' => $ticket, 'subject' => ['en' => 'Closed {{ title }}'], 'design' => ['blocks' => [['type' => 'text', 'text' => ['en' => 'Closed.']]]],
    ])->assertCreated()->json('data.uuid');
    saveRules($this, $ticket, [ruleDoc([
        'key' => 'closed', 'trigger' => 'status_changed', 'toStatus' => $closed['uuid'], 'template' => $tpl, 'channels' => ['email', 'in_app'],
        'recipients' => [
            'to' => [['type' => 'field', 'ref' => 'owner'], ['type' => 'field', 'ref' => 'team.lead']],
            'cc' => [['type' => 'field', 'ref' => 'crew'], ['type' => 'field', 'ref' => 'unit'], ['type' => 'assignee', 'ref' => null]],
            'bcc' => [['type' => 'field', 'ref' => 'contact'], ['type' => 'status_changer', 'ref' => null]],
        ],
    ])])->assertOk();

    $teamRec = $this->postJson("/api/v1/r/{$team}", ['values' => ['name' => 'Blue', 'lead' => $lead->uuid]])->assertCreated()->json('data.uuid');
    $rec = $this->postJson("/api/v1/r/{$ticket}", ['values' => [
        'title' => 'Printer', 'owner' => $owner->uuid, 'crew' => $auditor, 'unit' => $dept, 'contact' => 'Known.Person@example.test', 'team' => $teamRec,
    ]])->assertCreated()->json('data');
    $recId = (int) DB::table(DB::table('forms')->where('uuid', $ticket)->value('table_name'))->where('uuid', $rec['uuid'])->value('id');
    DB::table('assignments')->insert([
        'uuid' => uid(), 'organization_id' => DB::table('organizations')->value('id'), 'created_at' => now(), 'updated_at' => now(), 'form_id' => DB::table('forms')->where('uuid', $ticket)->value('id'),
        'record_id' => $recId, 'assignee_type' => 'user', 'assignee_id' => $assignee->id, 'status' => 'active', 'priority' => 0,
    ]);
    $recipients = app(Recipients::class);
    $rt = app(FormRuntimes::class)->forUuid($ticket);
    expect($recipients->resolve($rt, $recId, ['to' => [['type' => 'assignee', 'ref' => null]]])['to'])->toBe([$assignee->id]);
    // Recipients follow the record as it is when the message goes out, not when the rule was saved.
    $this->patchJson("/api/v1/r/{$ticket}/{$rec['uuid']}", ['values' => ['owner' => $newOwner->uuid], 'row_version' => 1])->assertOk();
    $this->flushSession();
    $this->actingAs($closer, 'web');
    $this->postJson("/api/v1/r/{$ticket}/{$rec['uuid']}/transitions/{$close['uuid']}", ['row_version' => 2])->assertOk();

    $notified = DB::table('in_app_notifications')->where('type', 'rule.status_changed')->pluck('user_id')->map(fn ($v) => (int) $v)->sort()->values()->all();
    // Closing the ticket completed its assignment, so by the time the message goes out it has no assignee.
    $expected = collect([$newOwner, $lead, $crewMember, $unitMember, $known, $closer])->map(fn ($u) => $u->id)->sort()->values()->all();
    expect($notified)->toBe($expected);
    $mail = DB::table('email_queue')->where('source', 'rule')->first();
    expect(json_decode($mail->to, true))->toEqualCanonicalizing([$newOwner->email, $lead->email])
        ->and(json_decode($mail->cc, true))->toEqualCanonicalizing([$crewMember->email, $unitMember->email])
        // An address that belongs to a user reaches that user; the person who closed it is told too.
        ->and(json_decode($mail->bcc, true))->toEqualCanonicalizing([$known->email, $closer->email]);

    // An address nobody in the system has is an outside address.
    $this->flushSession();
    $this->actingAs($this->admin, 'web');
    $other = $this->postJson("/api/v1/r/{$ticket}", ['values' => ['title' => 'Desk', 'contact' => 'visitor@outside.test']])->assertCreated()->json('data.uuid');
    $otherId = (int) DB::table(DB::table('forms')->where('uuid', $ticket)->value('table_name'))->where('uuid', $other)->value('id');
    $who = $recipients->resolve($rt, $otherId, ['to' => [['type' => 'field', 'ref' => 'contact'], ['type' => 'status_changer', 'ref' => null]]]);
    // A record not moved since it was created was last moved by whoever created it.
    expect($who['to'])->toBe([$this->admin->id])->and($who['static']['to'])->toBe(['visitor@outside.test']);
});
