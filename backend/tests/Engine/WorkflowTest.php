<?php

declare(strict_types=1);

use App\Modules\Workflow\Runtime\SlaTimers;
use Illuminate\Support\Facades\DB;

require_once __DIR__.'/helpers.php';

beforeEach(function () {
    $this->completeSetup();
    $this->admin = $this->superAdmin();
    $this->flushSession();
    $this->actingAs($this->admin, 'web');
});

it('reports workflow problems in the designer and blocks publishing until they are fixed', function () {
    [, $form] = createForm($this, 'wf_problems');
    saveDraft($this, $form, [], [fieldDoc('subject', 'text')])->assertOk();
    $a = statusDoc('a', true);
    $b = statusDoc('b', true, true);
    $c = statusDoc('c');
    $res = saveWorkflow($this, $form, [$a, $b, $c], [transitionDoc('back', $b, $a)])->assertOk();
    expect(array_column($res->json('data.problems'), 'code'))->toContain('initial_count', 'final_outgoing')
        ->and(array_column($res->json('data.warnings'), 'code'))->toContain('unreachable');
    $impact = $this->postJson("/api/v1/forms/{$form}/impact")->assertOk()->json('data.impact.blocking');
    expect(array_column($impact, 'detail'))->toContain('initial_count');

    // Structural errors are rejected outright.
    saveWorkflow($this, $form, [$a], [transitionDoc('x', $a, ['uuid' => uid()])])->assertStatus(422)->assertJsonValidationErrors(['transitions.0.to']);
    // A stale hash is a conflict, never a silent overwrite.
    $this->putJson("/api/v1/forms/{$form}/workflow", ['document' => ['statuses' => [], 'transitions' => [], 'sla' => []], 'base_hash' => str_repeat('0', 64)])->assertStatus(409)->assertJsonPath('code', 'workflow_changed');
});

it('starts records in the initial status, moves them through permitted transitions and keeps the history', function () {
    $wf = buildWorkflowForm($this);
    $form = $wf['form'];
    $rec = $this->postJson("/api/v1/r/{$form}", ['values' => ['subject' => 'Holiday']])->assertCreated()->json('data');
    expect($rec['system']['status']['key'])->toBe('draft');
    $state = $this->getJson("/api/v1/r/{$form}/{$rec['uuid']}/workflow")->assertOk()->json('data');
    expect(array_column($state['transitions'], 'key'))->toBe(['submit']);
    // Required fields reach the browser as key and label, never as an internal identifier.
    expect($state['transitions'][0]['required_fields'])->toBe([['key' => 'reason', 'label' => 'Reason', 'missing' => true]]);

    // Requirements: the reason field and a comment.
    $this->postJson("/api/v1/r/{$form}/{$rec['uuid']}/transitions/{$wf['submit']['uuid']}", ['row_version' => 1])
        ->assertStatus(422)->assertJsonValidationErrors(['reason', 'comment']);
    // The wrong transition for the status is refused.
    $this->postJson("/api/v1/r/{$form}/{$rec['uuid']}/transitions/{$wf['approve']['uuid']}", ['row_version' => 1])->assertStatus(409)->assertJsonPath('code', 'status_changed');

    $moved = $this->postJson("/api/v1/r/{$form}/{$rec['uuid']}/transitions/{$wf['submit']['uuid']}", [
        'row_version' => 1, 'values' => ['reason' => 'Family'], 'comment' => 'Please approve',
    ])->assertOk()->json('data');
    expect($moved['state'])->toBe('moved')->and($moved['status']['key'])->toBe('submitted')->and($moved['row_version'])->toBe(3);

    // Optimistic concurrency: the old version is a conflict.
    $this->postJson("/api/v1/r/{$form}/{$rec['uuid']}/transitions/{$wf['approve']['uuid']}", ['row_version' => 1])->assertStatus(409);

    $history = $this->getJson("/api/v1/r/{$form}/{$rec['uuid']}/status-history")->assertOk()->json('data');
    expect($history)->toHaveCount(2)
        ->and($history[0]['to']['key'])->toBe('submitted')->and($history[0]['comment'])->toBe('Please approve')->and($history[0]['transition']['key'])->toBe('submit')
        ->and($history[1]['from'])->toBeNull()->and($history[1]['to']['key'])->toBe('draft');
    expect(DB::table('audit_logs')->where('event', 'record.transitioned')->count())->toBe(1);
});

it('requires the transition permission and applies field access per status', function () {
    $wf = buildWorkflowForm($this);
    $form = $wf['form'];
    $rec = $this->postJson("/api/v1/r/{$form}", ['values' => ['subject' => 'Trip', 'reason' => 'Work']])->assertCreated()->json('data');

    // Subject becomes read-only for everyone once submitted.
    $this->putJson("/api/v1/forms/{$form}/access-rules", ['changes' => [[
        'target' => ['type' => 'field', 'uuid' => $wf['subject']['uuid']], 'subject' => ['type' => 'everyone'], 'mode' => null,
        'access' => 'read_only', 'effect' => 'allow', 'status' => $wf['submitted']['uuid'],
    ]]])->assertOk();

    $clerk = $this->makeUser();
    foreach (['view', 'edit', 'create'] as $ability) {
        grantPermission($clerk->id, "form.{$form}.{$ability}");
    }
    $this->flushSession();
    $this->actingAs($clerk, 'web');
    $this->postJson("/api/v1/r/{$form}/{$rec['uuid']}/transitions/{$wf['submit']['uuid']}", ['row_version' => 1, 'comment' => 'go'])->assertStatus(403);
    $this->getJson("/api/v1/r/{$form}/{$rec['uuid']}/transitions")->assertOk()->assertJsonPath('data', []);

    grantPermission($clerk->id, 'transition.'.$wf['submit']['uuid'].'.perform');
    $this->postJson("/api/v1/r/{$form}/{$rec['uuid']}/transitions/{$wf['submit']['uuid']}", ['row_version' => 1, 'comment' => 'go'])->assertOk();
    // In "submitted" the subject is read-only; in "draft" it was editable.
    $this->patchJson("/api/v1/r/{$form}/{$rec['uuid']}", ['values' => ['subject' => 'Changed'], 'row_version' => 2])->assertStatus(422)->assertJsonValidationErrors(['subject']);
    $this->patchJson("/api/v1/r/{$form}/{$rec['uuid']}", ['values' => ['reason' => 'Changed'], 'row_version' => 2])->assertOk();
});

it('maps records of removed statuses on publish and records the move in the history', function () {
    $wf = buildWorkflowForm($this);
    $form = $wf['form'];
    $rec = $this->postJson("/api/v1/r/{$form}", ['values' => ['subject' => 'A', 'reason' => 'x']])->assertCreated()->json('data');
    $this->postJson("/api/v1/r/{$form}/{$rec['uuid']}/transitions/{$wf['submit']['uuid']}", ['row_version' => 1, 'comment' => 'c'])->assertOk();

    // Remove "submitted": its record must be mapped before publishing.
    $approve = transitionDoc('approve', $wf['draft'], $wf['approved']);
    saveWorkflow($this, $form, [$wf['draft'], $wf['approved']], [$approve])->assertOk();
    $impact = $this->postJson("/api/v1/forms/{$form}/impact")->assertOk()->json('data.impact');
    expect(array_column($impact['blocking'], 'code'))->toContain('status_mapping_required')
        ->and($impact['workflow']['removed_statuses'][0]['records'])->toBe(1);
    $this->postJson("/api/v1/forms/{$form}/workflow/status-mapping", ['mappings' => [['from' => $wf['submitted']['uuid'], 'to' => $wf['draft']['uuid']]]])
        ->assertOk()->assertJsonPath('data.removed.0.to', $wf['draft']['uuid']);
    $plan = publish($this, $form);
    expect($plan['status'])->toBe('applied')->and(array_column($plan['steps'], 'operation'))->toContain('map_status');

    $shown = $this->getJson("/api/v1/r/{$form}/{$rec['uuid']}")->assertOk()->json('data');
    expect($shown['system']['status']['key'])->toBe('draft')->and($shown['row_version'])->toBe(3);
    expect(DB::table('status_history')->where('source', 'status_mapping')->count())->toBe(1);
    $mapping = DB::table('status_mappings')->where('change_type', 'merge')->first();
    expect((int) $mapping->records_affected)->toBe(1)->and($mapping->form_version_id)->not->toBeNull();
    expect(DB::table('statuses')->where('uuid', $wf['submitted']['uuid'])->value('archived_at'))->not->toBeNull();
});

it('times SLAs and escalates by moving the record', function () {
    $wf = buildWorkflowForm($this);
    $form = $wf['form'];
    $sla = [['uuid' => uid(), 'status' => $wf['submitted']['uuid'], 'durationMinutes' => 60, 'workingTime' => false, 'calendar' => null, 'warnBeforeMinutes' => 10,
        'escalations' => [['afterMinutes' => 5, 'action' => 'transition', 'params' => ['transition' => $wf['approve']['uuid']]]], 'condition' => null, 'active' => true]];
    $doc = $this->getJson("/api/v1/forms/{$form}/workflow")->json('data');
    saveWorkflow($this, $form, $doc['document']['statuses'], $doc['document']['transitions'], $sla)->assertOk();
    expect(publish($this, $form)['status'])->toBe('applied');

    $rec = $this->postJson("/api/v1/r/{$form}", ['values' => ['subject' => 'A', 'reason' => 'x']])->assertCreated()->json('data');
    $this->postJson("/api/v1/r/{$form}/{$rec['uuid']}/transitions/{$wf['submit']['uuid']}", ['row_version' => 1, 'comment' => 'c'])->assertOk();
    $timer = DB::table('sla_timers')->first();
    expect($timer->state)->toBe('running');

    $timers = app(SlaTimers::class);
    $timers->tick(SlaTimers::nowUtc()->modify('+55 minutes'));
    expect(DB::table('sla_timers')->value('state'))->toBe('warned');
    $timers->tick(SlaTimers::nowUtc()->modify('+70 minutes'));
    $shown = $this->getJson("/api/v1/r/{$form}/{$rec['uuid']}")->json('data');
    expect($shown['system']['status']['key'])->toBe('approved');
    expect(DB::table('status_history')->where('source', 'sla_escalation')->count())->toBe(1);
    expect(DB::table('sla_timers')->where('id', $timer->id)->value('state'))->toBe('completed');
    expect(DB::table('audit_logs')->whereIn('event', ['sla.warning', 'sla.breached', 'sla.escalated'])->count())->toBe(3);
});

it('waits for multi-party approval and moves when the rule is met', function () {
    [, $form] = createForm($this, 'purchase_wf');
    saveDraft($this, $form, [], [fieldDoc('item', 'text')])->assertOk();
    $a = $this->makeUser();
    $b = $this->makeUser();
    foreach ([$a, $b] as $u) {
        grantPermission($u->id, "form.{$form}.view");
    }
    $new = statusDoc('new', true);
    $ok = statusDoc('ok', false, true);
    $rejected = statusDoc('rejected', false, true);
    $approve = transitionDoc('approve', $new, $ok, ['approval' => ['mode' => 'all', 'approvers' => [['type' => 'user', 'uuid' => $a->uuid, 'weight' => 1], ['type' => 'user', 'uuid' => $b->uuid, 'weight' => 1]], 'rejection' => 'immediate', 'rejectionStatus' => $rejected['uuid']]]);
    saveWorkflow($this, $form, [$new, $ok, $rejected], [$approve])->assertOk();
    expect(publish($this, $form)['status'])->toBe('applied');

    $rec = $this->postJson("/api/v1/r/{$form}", ['values' => ['item' => 'Laptop']])->assertCreated()->json('data');
    $res = $this->postJson("/api/v1/r/{$form}/{$rec['uuid']}/transitions/{$approve['uuid']}", ['row_version' => 1])->assertOk()->json('data');
    expect($res['state'])->toBe('approval_pending')->and($res['status']['key'])->toBe('new');
    $approval = $res['approval'];
    // No further transitions while approval is pending.
    $this->postJson("/api/v1/r/{$form}/{$rec['uuid']}/transitions/{$approve['uuid']}", ['row_version' => 1])->assertStatus(409)->assertJsonPath('code', 'approval_pending');

    $this->flushSession();
    $this->actingAs($a, 'web');
    expect(collect($this->getJson('/api/v1/my-work?kind=approval')->assertOk()->json('data'))->pluck('approval')->all())->toBe([$approval]);
    $this->postJson("/api/v1/approvals/{$approval}/decide", ['decision' => 'approved', 'comment' => 'fine'])->assertOk()->assertJsonPath('data.status', 'pending');
    $this->postJson("/api/v1/approvals/{$approval}/decide", ['decision' => 'approved'])->assertStatus(409)->assertJsonPath('code', 'already_decided');

    $this->flushSession();
    $this->actingAs($b, 'web');
    $this->postJson("/api/v1/approvals/{$approval}/decide", ['decision' => 'approved'])->assertOk()->assertJsonPath('data.status', 'approved');
    $this->flushSession();
    $this->actingAs($this->admin, 'web');
    expect($this->getJson("/api/v1/r/{$form}/{$rec['uuid']}")->json('data.system.status.key'))->toBe('ok');
    expect(DB::table('approval_decisions')->where('decision', 'approved')->count())->toBe(2);

    // A rejection returns the next request immediately to the rejection status.
    $rec2 = $this->postJson("/api/v1/r/{$form}", ['values' => ['item' => 'Phone']])->assertCreated()->json('data');
    $approval2 = $this->postJson("/api/v1/r/{$form}/{$rec2['uuid']}/transitions/{$approve['uuid']}", ['row_version' => 1])->json('data.approval');
    $this->flushSession();
    $this->actingAs($b, 'web');
    $this->postJson("/api/v1/approvals/{$approval2}/decide", ['decision' => 'rejected'])->assertStatus(422);
    $this->postJson("/api/v1/approvals/{$approval2}/decide", ['decision' => 'rejected', 'comment' => 'No budget'])->assertOk()->assertJsonPath('data.status', 'rejected');
    $this->flushSession();
    $this->actingAs($this->admin, 'web');
    expect($this->getJson("/api/v1/r/{$form}/{$rec2['uuid']}")->json('data.system.status.key'))->toBe('rejected');
});
