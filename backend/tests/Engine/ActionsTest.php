<?php

declare(strict_types=1);

use App\Infrastructure\Egress\AddressPolicy;
use App\Infrastructure\Egress\EgressGateway;
use App\Infrastructure\Egress\HostResolver;
use App\Modules\Actions\WebhookCaller;
use App\Modules\Core\Correlation\CorrelationId;
use App\Modules\Core\Models\EgressAllowlistEntry;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use Psr\Http\Message\RequestInterface;
use Tests\TestCase;

require_once __DIR__.'/helpers.php';

beforeEach(function () {
    $this->completeSetup();
    $this->admin = $this->superAdmin();
    $this->flushSession();
    $this->actingAs($this->admin, 'web');
});

/**
 * Tickets (subject, priority, notes; open → closed) and tasks that link to a ticket.
 *
 * @return array<string, mixed>
 */
function ticketForms(TestCase $t): array
{
    [, $tickets] = createForm($t, 'tickets');
    $f = [
        'subject' => fieldDoc('subject', 'text', null, ['validation' => ['required' => true]]),
        'priority' => fieldDoc('priority', 'select', null, ['options' => ['source' => 'static', 'static' => staticOptions(['low', 'high'])]]),
        'notes' => fieldDoc('notes', 'textarea'),
    ];
    saveDraft($t, $tickets, [], array_values($f))->assertOk();
    $open = statusDoc('open', true);
    $closed = statusDoc('closed', false, true);
    $close = transitionDoc('close', $open, $closed);
    saveWorkflow($t, $tickets, [$open, $closed], [$close])->assertOk();
    expect(publish($t, $tickets)['status'])->toBe('applied');

    [, $tasks] = createForm($t, 'tasks');
    $relation = ['uuid' => uid(), 'key' => 'ticket', 'type' => 'many_to_one', 'target' => $tickets, 'kind' => 'reference', 'onDelete' => 'restrict', 'display' => $f['subject']['uuid'], 'value' => null, 'inverse' => null];
    $title = fieldDoc('title', 'text');
    $ticket = fieldDoc('ticket', 'lookup', null, ['relation' => $relation['uuid']]);
    saveDraft($t, $tasks, [], [$title, $ticket], [$relation])->assertOk();
    expect(publish($t, $tasks)['status'])->toBe('applied');

    return ['tickets' => $tickets, 'tasks' => $tasks, 'f' => $f, 'close' => $close, 'closed' => $closed, 'title' => $title, 'ticket' => $ticket];
}

/** @param  list<array<string, mixed>>  $actions */
function saveActions(TestCase $t, string $form, array $actions): TestResponse
{
    $hash = $t->getJson("/api/v1/forms/{$form}/actions")->assertOk()->json('data.hash');

    return $t->putJson("/api/v1/forms/{$form}/actions", ['actions' => $actions, 'base_hash' => $hash]);
}

/** @return array<string, mixed> */
function actionDoc(string $key, array $extra = []): array
{
    return array_replace([
        'uuid' => uid(), 'key' => $key, 'kind' => 'custom', 'builtin' => null, 'i18n' => ['label' => ['en' => ucfirst($key)], 'confirmation' => [], 'success' => ['en' => 'Done']],
        'placements' => ['row', 'bulk'], 'condition' => null, 'confirm' => false, 'runMode' => 'sync', 'queueThreshold' => null, 'maxRecords' => null,
        'justification' => 'not_required', 'icon' => null, 'color' => null, 'active' => true, 'steps' => [],
    ], $extra);
}

/** @return array<string, mixed> */
function stepDoc(string $type, array $config, array $extra = []): array
{
    return array_replace(['uuid' => uid(), 'type' => $type, 'config' => $config, 'condition' => null, 'onFailure' => 'stop'], $extra);
}

/**
 * Routes outbound calls to scripted answers: the gateway's own checks
 * (allowlist, address policy) still run.
 *
 * @param  list<Response|Closure>  $answers
 * @param  list<RequestInterface>  $seen
 */
function fakeWebhooks(array $answers, array &$seen): void
{
    $resolver = new class implements HostResolver
    {
        public function resolve(string $host): array
        {
            return ['93.184.216.34'];
        }
    };
    $queue = [];
    foreach ($answers as $a) {
        $queue[] = static function (RequestInterface $r) use ($a, &$seen) {
            $seen[] = $r;

            return $a instanceof Closure ? $a($r) : $a;
        };
    }
    $mock = new MockHandler($queue);
    app()->instance(EgressGateway::class, new EgressGateway(new AddressPolicy, $resolver, app(CorrelationId::class), HandlerStack::create($mock)));
    app()->forgetInstance(WebhookCaller::class);
    app()->resolving(WebhookCaller::class, static fn (WebhookCaller $c) => $c->sleepWith(static fn (int $ms) => null));
}

it('runs a chain of steps on selected records, asking one justification for the whole run', function () {
    $w = ticketForms($this);
    EgressAllowlistEntry::query()->create(['host_pattern' => 'hooks.example.com', 'ports' => [443], 'allow_http' => false, 'is_active' => true]);
    $doc = $this->postJson('/api/v1/documents/templates', ['key' => 'ticket_sheet', 'type' => 'html', 'form' => $w['tickets'], 'name' => ['en' => 'Ticket sheet'], 'formats' => ['pdf'],
        'design' => ['blocks' => [['type' => 'heading', 'text' => ['en' => 'Ticket {{ subject }}']]]]])->assertCreated()->json('data');
    $lit = static fn (string $v) => ['k' => 'lit', 't' => 'text', 'v' => $v];
    $escalate = actionDoc('escalate', ['justification' => 'mandatory', 'steps' => [
        stepDoc('update_fields', ['set' => [['field' => $w['f']['priority']['uuid'], 'value' => $lit('high')], ['field' => $w['f']['notes']['uuid'], 'value' => $lit('')]]]),
        stepDoc('change_status', ['transition' => $w['close']['uuid']]),
        stepDoc('send_notification', ['template' => null, 'recipients' => ['to' => [['type' => 'creator', 'ref' => null]]]]),
        stepDoc('create_linked_record', ['form' => $w['tasks'], 'linkField' => $w['ticket']['uuid'], 'prefill' => [['field' => $w['title']['uuid'], 'value' => ['k' => 'ref', 'scope' => 'record', 'path' => ['subject']]]], 'open' => true]),
        stepDoc('generate_document', ['template' => $doc['uuid'], 'format' => 'pdf', 'attachToField' => null]),
        stepDoc('call_webhook', ['url' => 'https://hooks.example.com/tickets/{{ subject }}', 'method' => 'POST', 'format' => 'json',
            'headers' => [['name' => 'X-Token', 'value' => 'abc-header']], 'body' => '{"subject":"{{ subject }}"}', 'secret' => 's3cr3t']),
    ]]);
    // Invalid configuration is refused, naming the step.
    $bad = $escalate;
    $bad['steps'][1]['config']['transition'] = uid();
    saveActions($this, $w['tickets'], [$bad])->assertStatus(422)->assertJsonValidationErrors(['actions.0.steps.1.config.transition']);
    saveActions($this, $w['tickets'], [$escalate])->assertOk();

    // Secrets never come back; saving again without them keeps them.
    $loaded = $this->getJson("/api/v1/forms/{$w['tickets']}/actions")->json('data.actions.0.steps.5.config');
    expect($loaded['secret'])->toBeNull()->and($loaded['secretSet'])->toBeTrue()->and($loaded['headers'][0])->toBe(['name' => 'X-Token', 'value' => null, 'set' => true]);
    $again = $this->getJson("/api/v1/forms/{$w['tickets']}/actions")->json('data.actions');
    saveActions($this, $w['tickets'], $again)->assertOk();
    expect((string) DB::table('action_steps')->where('type', 'call_webhook')->value('config'))->not->toContain('s3cr3t')->not->toContain('abc-header');

    $a = $this->postJson("/api/v1/r/{$w['tickets']}", ['values' => ['subject' => 'Printer', 'notes' => 'x']])->assertCreated()->json('data');
    $b = $this->postJson("/api/v1/r/{$w['tickets']}", ['values' => ['subject' => 'Scanner']])->assertCreated()->json('data');
    $items = [['uuid' => $a['uuid'], 'row_version' => $a['row_version']], ['uuid' => $b['uuid'], 'row_version' => $b['row_version']]];
    $offered = collect($this->getJson("/api/v1/r/{$w['tickets']}/actions?placement=bulk")->json('data'))->pluck('label')->all();
    expect($offered)->toBe(['Escalate']);

    $prep = $this->postJson("/api/v1/r/{$w['tickets']}/actions/{$escalate['uuid']}/prepare", ['items' => $items])->assertOk()->json('data');
    expect($prep['count'])->toBe(2)->and($prep['justification']['level'] ?? null)->toBe('mandatory');
    $seen = [];
    fakeWebhooks([new Response(200), new Response(204)], $seen);
    $this->postJson("/api/v1/r/{$w['tickets']}/actions/{$escalate['uuid']}/run", ['items' => $items])->assertStatus(422)->assertJsonPath('code', 'justification_required');
    $run = $this->postJson("/api/v1/r/{$w['tickets']}/actions/{$escalate['uuid']}/run", ['items' => $items, 'justification' => ['reason_text' => 'Customer called twice']])
        ->assertCreated()->json('data');
    expect($run['status'])->toBe('completed')->and($run['affected'])->toBe(2)->and($run['failed'])->toBe(0)->and($run['message'])->toBe('Done');
    expect(collect($run['outputs'])->pluck('type')->all())->toContain('download', 'open');

    foreach ([$a, $b] as $r) {
        $now = $this->getJson("/api/v1/r/{$w['tickets']}/{$r['uuid']}")->json('data');
        expect($now['values']['priority'])->toBe('high')->and($now['values']['notes'])->toBeNull()->and($now['system']['status']['key'] ?? null)->toBe('closed');
    }
    // One justification for the run, linked to every change it made.
    $j = DB::table('justifications')->where('context', 'action')->get();
    expect($j)->toHaveCount(1)->and((int) $j[0]->affected_count)->toBe(2)->and($j[0]->bulk_operation_id)->not->toBeNull();
    expect(DB::table('audit_logs')->where('event', 'record.updated')->where('justification_id', $j[0]->id)->count())->toBe(2)
        ->and(DB::table('audit_logs')->where('event', 'record.transitioned')->where('justification_id', $j[0]->id)->count())->toBe(2);
    // Linked records, notifications, webhook calls.
    expect(DB::table('f_tasks')->pluck('title')->sort()->values()->all())->toBe(['Printer', 'Scanner']);
    expect(DB::table('in_app_notifications')->where('type', 'action')->where('user_id', $this->admin->id)->count())->toBe(2);
    expect($seen)->toHaveCount(2);
    $first = $seen[0];
    expect((string) $first->getUri())->toBe('https://hooks.example.com/tickets/Printer')
        ->and((string) $first->getBody())->toBe('{"subject":"Printer"}')
        ->and($first->getHeaderLine('X-Token'))->toBe('abc-header')
        ->and($first->getHeaderLine('X-LCF-Signature'))->toBe('sha256='.hash_hmac('sha256', $first->getHeaderLine('X-LCF-Timestamp').'.{"subject":"Printer"}', 's3cr3t'));
    $logged = DB::table('audit_logs')->where('event', 'action.webhook_called')->pluck('meta')->implode(' ');
    expect($logged)->toContain('hooks.example.com')->not->toContain('s3cr3t')->not->toContain('abc-header');
});

it('offers an action only with its permission and the form ability, and only where its condition holds', function () {
    $w = ticketForms($this);
    $high = ['k' => 'bin', 'op' => '=', 'a' => ['k' => 'ref', 'scope' => 'record', 'path' => ['priority']], 'b' => ['k' => 'lit', 't' => 'text', 'v' => 'high']];
    $note = actionDoc('note', ['placements' => ['row'], 'condition' => $high, 'steps' => [stepDoc('update_fields', ['set' => [['field' => $w['f']['notes']['uuid'], 'value' => ['k' => 'lit', 't' => 'text', 'v' => 'Seen']]]])]]);
    saveActions($this, $w['tickets'], [$note])->assertOk();
    $low = $this->postJson("/api/v1/r/{$w['tickets']}", ['values' => ['subject' => 'A', 'priority' => 'low']])->assertCreated()->json('data');
    $hi = $this->postJson("/api/v1/r/{$w['tickets']}", ['values' => ['subject' => 'B', 'priority' => 'high']])->assertCreated()->json('data');
    expect($this->getJson("/api/v1/r/{$w['tickets']}/actions?placement=row&record={$low['uuid']}")->json('data'))->toBe([]);
    expect($this->getJson("/api/v1/r/{$w['tickets']}/actions?placement=row&record={$hi['uuid']}")->json('data'))->toHaveCount(1);
    $this->postJson("/api/v1/r/{$w['tickets']}/actions/{$note['uuid']}/run", ['items' => [['uuid' => $low['uuid']]]])->assertStatus(422);

    // Someone who may view and edit tickets but holds no grant for the action never sees or runs it.
    $clerk = $this->makeUser();
    foreach (['view', 'edit'] as $ability) {
        grantPermission($clerk->id, "form.{$w['tickets']}.{$ability}");
    }
    $this->flushSession();
    $this->actingAs($clerk, 'web');
    expect($this->getJson("/api/v1/r/{$w['tickets']}/actions?placement=row&record={$hi['uuid']}")->json('data'))->toBe([]);
    $this->postJson("/api/v1/r/{$w['tickets']}/actions/{$note['uuid']}/run", ['items' => [['uuid' => $hi['uuid']]]])->assertNotFound();
    grantPermission($clerk->id, 'action.'.$note['uuid'].'.run');
    $run = $this->postJson("/api/v1/r/{$w['tickets']}/actions/{$note['uuid']}/run", ['items' => [['uuid' => $hi['uuid'], 'row_version' => $hi['row_version']]]])->assertCreated()->json('data');
    expect($run['status'])->toBe('completed');
    // A record changed since it was selected is reported, never run over.
    $stale = $this->postJson("/api/v1/r/{$w['tickets']}/actions/{$note['uuid']}/run", ['items' => [['uuid' => $hi['uuid'], 'row_version' => $hi['row_version']]]])->assertCreated()->json('data');
    expect($stale['status'])->toBe('completed_with_errors')->and($stale['errors'][0]['error'])->toBe(__('actions.changed_since'));
});

it('runs built-in bulk update, status change, delete and duplicate through the record pipeline', function () {
    $w = ticketForms($this);
    $update = actionDoc('set_priority', ['kind' => 'builtin', 'builtin' => 'bulk_update', 'placements' => ['bulk'], 'steps' => []]);
    $status = actionDoc('move', ['kind' => 'builtin', 'builtin' => 'bulk_status', 'placements' => ['bulk'], 'steps' => []]);
    $delete = actionDoc('remove', ['kind' => 'builtin', 'builtin' => 'bulk_delete', 'placements' => ['bulk'], 'steps' => [], 'justification' => 'mandatory']);
    $copy = actionDoc('copy', ['kind' => 'builtin', 'builtin' => 'duplicate', 'placements' => ['row'], 'steps' => []]);
    // A built-in only goes where it makes sense.
    saveActions($this, $w['tickets'], [array_replace($copy, ['placements' => ['bulk']])])->assertStatus(422)->assertJsonValidationErrors(['actions.0.placements']);
    saveActions($this, $w['tickets'], [$update, $status, $delete, $copy])->assertOk();
    $records = collect(['A', 'B', 'C'])->map(fn ($s) => $this->postJson("/api/v1/r/{$w['tickets']}", ['values' => ['subject' => $s, 'priority' => 'low']])->assertCreated()->json('data'))->all();
    $items = array_map(static fn ($r) => ['uuid' => $r['uuid']], $records);

    $inputs = $this->postJson("/api/v1/r/{$w['tickets']}/actions/{$update['uuid']}/prepare", ['items' => $items])->json('data.inputs.fields');
    expect(collect($inputs)->pluck('key')->all())->toContain('priority', 'notes');
    $this->postJson("/api/v1/r/{$w['tickets']}/actions/{$update['uuid']}/run", ['items' => $items, 'inputs' => ['values' => ['nope' => 1]]])->assertStatus(422);
    $res = $this->postJson("/api/v1/r/{$w['tickets']}/actions/{$update['uuid']}/run", ['items' => $items, 'inputs' => ['values' => ['priority' => 'high']]]);
    expect($res->json('data.affected'))->toBe(3);
    expect(DB::table('f_tickets')->pluck('priority')->unique()->values()->all())->toBe(['high']);

    $transitions = $this->postJson("/api/v1/r/{$w['tickets']}/actions/{$status['uuid']}/prepare", ['items' => $items])->json('data.inputs.transitions');
    expect($transitions)->toBe([['uuid' => $w['close']['uuid'], 'label' => 'Close']]);
    $this->postJson("/api/v1/r/{$w['tickets']}/actions/{$status['uuid']}/run", ['items' => array_slice($items, 0, 2), 'inputs' => ['transition' => $w['close']['uuid']]])->assertCreated();
    expect(DB::table('f_tickets')->whereNotNull('status_id')->where('status_id', DB::table('statuses')->where('uuid', $w['closed']['uuid'])->value('id'))->count())->toBe(2);

    $this->postJson("/api/v1/r/{$w['tickets']}/actions/{$delete['uuid']}/run", ['items' => array_slice($items, 1)])->assertStatus(422)->assertJsonPath('code', 'justification_required');
    $this->postJson("/api/v1/r/{$w['tickets']}/actions/{$delete['uuid']}/run", ['items' => array_slice($items, 1), 'justification' => ['reason_text' => 'Duplicates']])->assertCreated();
    expect(DB::table('f_tickets')->whereNull('deleted_at')->count())->toBe(1);
    expect(DB::table('justifications')->where('context', 'bulk')->value('affected_count'))->toBe(2);

    $copied = $this->postJson("/api/v1/r/{$w['tickets']}/actions/{$copy['uuid']}/run", ['items' => [$items[0]]])->assertCreated()->json('data');
    expect($copied['outputs'][0]['type'])->toBe('open');
    expect(DB::table('f_tickets')->whereNull('deleted_at')->where('subject', 'A')->count())->toBe(2);
});

it('previews a bulk run, tries it without keeping anything, and reassigns records in bulk', function () {
    $w = ticketForms($this);
    $update = actionDoc('set_subject', ['kind' => 'builtin', 'builtin' => 'bulk_update', 'placements' => ['bulk'], 'steps' => []]);
    $delete = actionDoc('remove', ['kind' => 'builtin', 'builtin' => 'bulk_delete', 'placements' => ['bulk'], 'steps' => [], 'justification' => 'mandatory']);
    $reassign = actionDoc('hand_over', ['kind' => 'builtin', 'builtin' => 'bulk_reassign', 'placements' => ['bulk', 'toolbar'], 'steps' => []]);
    $custom = actionDoc('note', ['steps' => [stepDoc('update_fields', ['set' => [['field' => $w['f']['notes']['uuid'], 'value' => ['k' => 'lit', 't' => 'text', 'v' => 'x']]]])]]);
    saveActions($this, $w['tickets'], [$update, $delete, $reassign, $custom])->assertOk();
    $records = collect(['A', 'B', 'C'])->map(fn ($s) => $this->postJson("/api/v1/r/{$w['tickets']}", ['values' => ['subject' => $s]])->assertCreated()->json('data'))->all();
    $items = array_map(static fn ($r) => ['uuid' => $r['uuid'], 'row_version' => $r['row_version']], $records);

    // The preview names the records; a dry run of an emptied required field fails every record and keeps nothing.
    $prepared = $this->postJson("/api/v1/r/{$w['tickets']}/actions/{$update['uuid']}/prepare", ['items' => $items])->assertOk()->json('data');
    expect(array_column($prepared['preview'], 'title'))->toBe(['A', 'B', 'C'])->and($prepared['dry_run'])->toBeTrue();
    $dry = $this->postJson("/api/v1/r/{$w['tickets']}/actions/{$update['uuid']}/dry-run", ['items' => $items, 'inputs' => ['values' => ['subject' => null]]])->assertOk()->json('data');
    expect($dry)->toMatchArray(['count' => 3, 'tried' => 3, 'would_succeed' => 0, 'would_fail' => 3])->and($dry['errors'][0]['title'])->toBe('A');
    $dry = $this->postJson("/api/v1/r/{$w['tickets']}/actions/{$update['uuid']}/dry-run", ['items' => $items, 'inputs' => ['values' => ['notes' => 'checked']]])->assertOk()->json('data');
    expect($dry['would_succeed'])->toBe(3)->and(DB::table('f_tickets')->whereNotNull('notes')->count())->toBe(0)
        ->and(DB::table('bulk_operations')->where('dry_run', true)->count())->toBe(2);
    // A delete that needs a justification counts as one that would succeed; nothing is deleted or justified.
    expect($this->postJson("/api/v1/r/{$w['tickets']}/actions/{$delete['uuid']}/dry-run", ['items' => $items])->json('data.would_succeed'))->toBe(3)
        ->and(DB::table('f_tickets')->whereNull('deleted_at')->count())->toBe(3)->and(DB::table('justifications')->count())->toBe(0);
    // Only the bulk built-ins have a dry run.
    $this->postJson("/api/v1/r/{$w['tickets']}/actions/{$custom['uuid']}/dry-run", ['items' => $items])->assertStatus(422);

    // Bulk reassign: an unknown assignee is refused, a user is assigned every record.
    expect($this->postJson("/api/v1/r/{$w['tickets']}/actions/{$reassign['uuid']}/prepare", ['items' => $items])->json('data.inputs.assignees'))->toBe(['user', 'role', 'department']);
    $this->postJson("/api/v1/r/{$w['tickets']}/actions/{$reassign['uuid']}/run", ['items' => $items, 'inputs' => ['assignee' => ['type' => 'user', 'uuid' => uid()]]])->assertStatus(422);
    $to = ['type' => 'user', 'uuid' => $this->admin->uuid];
    $done = $this->postJson("/api/v1/r/{$w['tickets']}/actions/{$reassign['uuid']}/run", ['items' => $items, 'inputs' => ['assignee' => $to]])->assertCreated()->json('data');
    expect($done['affected'])->toBe(3)
        ->and(DB::table('assignments')->where('status', 'active')->where('assignee_type', 'user')->where('assignee_id', $this->admin->id)->count())->toBe(3)
        ->and(DB::table('bulk_operations')->where('type', 'reassign')->where('dry_run', false)->count())->toBe(1);
});

it('runs large runs in the background, retries a failing webhook, and continues past a step set to continue', function () {
    $w = ticketForms($this);
    EgressAllowlistEntry::query()->create(['host_pattern' => 'hooks.example.com', 'ports' => [443], 'allow_http' => false, 'is_active' => true]);
    $ping = actionDoc('ping', ['runMode' => 'queued', 'steps' => [
        stepDoc('call_webhook', ['url' => 'https://hooks.example.com/ping', 'method' => 'POST', 'format' => 'json', 'headers' => [], 'body' => '{}', 'secret' => null]),
        stepDoc('call_webhook', ['url' => 'https://elsewhere.example.org/x', 'method' => 'GET', 'format' => 'json', 'headers' => [], 'body' => '', 'secret' => null], ['onFailure' => 'continue']),
        stepDoc('update_fields', ['set' => [['field' => $w['f']['notes']['uuid'], 'value' => ['k' => 'lit', 't' => 'text', 'v' => 'Pinged']]]]),
    ]]);
    saveActions($this, $w['tickets'], [$ping])->assertOk();
    $r = $this->postJson("/api/v1/r/{$w['tickets']}", ['values' => ['subject' => 'A']])->assertCreated()->json('data');
    $seen = [];
    fakeWebhooks([new Response(500), new Response(503), new Response(200)], $seen);
    $run = $this->postJson("/api/v1/r/{$w['tickets']}/actions/{$ping['uuid']}/run", ['items' => [['uuid' => $r['uuid']]]])->assertCreated()->json('data');
    // The queue runs at once in tests; the run reports through its status and the notifications center.
    $done = $this->getJson("/api/v1/action-runs/{$run['uuid']}")->assertOk()->json('data');
    expect($done['status'])->toBe('completed')->and($seen)->toHaveCount(3)
        ->and(collect($done['outputs'])->firstWhere('type', 'warning')['message'] ?? '')->toContain('elsewhere.example.org');
    expect(DB::table('f_tickets')->where('uuid', $r['uuid'])->value('notes'))->toBe('Pinged');
    expect(DB::table('in_app_notifications')->where('type', 'action.finished')->where('user_id', $this->admin->id)->exists())->toBeTrue();
    expect(DB::table('audit_logs')->where('event', 'action.webhook_called')->count())->toBe(4);
    // Another user cannot read someone else's run.
    $this->flushSession();
    $this->actingAs($this->makeUser(), 'web');
    $this->getJson("/api/v1/action-runs/{$run['uuid']}")->assertNotFound();
});

it('lets a justification rule target one action, built-in or custom', function () {
    $w = ticketForms($this);
    $copy = actionDoc('copy', ['kind' => 'builtin', 'builtin' => 'duplicate', 'placements' => ['row'], 'steps' => []]);
    saveActions($this, $w['tickets'], [$copy])->assertOk();
    $r = $this->postJson("/api/v1/r/{$w['tickets']}", ['values' => ['subject' => 'A']])->assertCreated()->json('data');
    $hash = $this->getJson("/api/v1/forms/{$w['tickets']}/justification-rules")->json('data.hash');
    $this->putJson("/api/v1/forms/{$w['tickets']}/justification-rules", ['base_hash' => $hash, 'rules' => [[
        'uuid' => uid(), 'scope' => 'action', 'target' => $copy['uuid'], 'subject' => ['type' => 'everyone'], 'level' => 'mandatory', 'condition' => null, 'levelWhen' => null,
        'text' => ['min' => 3], 'reasonCodes' => ['mode' => 'none', 'source' => 'codes'], 'attachments' => ['mode' => 'none'], 'showSummary' => false, 'active' => true, 'i18n' => ['title' => [], 'help' => []],
    ]]])->assertOk();
    expect($this->getJson("/api/v1/forms/{$w['tickets']}/justification-rules")->json('data.rules.0.target'))->toBe($copy['uuid']);
    $this->postJson("/api/v1/r/{$w['tickets']}/actions/{$copy['uuid']}/run", ['items' => [['uuid' => $r['uuid']]]])->assertStatus(422)->assertJsonPath('code', 'justification_required');
    $this->postJson("/api/v1/r/{$w['tickets']}/actions/{$copy['uuid']}/run", ['items' => [['uuid' => $r['uuid']]], 'justification' => ['reason_text' => 'No']])->assertStatus(422)->assertJsonPath('code', 'justification_invalid');
    $this->postJson("/api/v1/r/{$w['tickets']}/actions/{$copy['uuid']}/run", ['items' => [['uuid' => $r['uuid']]], 'justification' => ['reason_text' => 'Same again']])->assertCreated();
    expect(DB::table('justifications')->where('context', 'action')->value('reason_text'))->toBe('Same again');
});
