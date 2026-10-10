<?php

declare(strict_types=1);

use App\Modules\Justification\Models\Justification;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

require_once __DIR__.'/helpers.php';

beforeEach(function () {
    $this->completeSetup();
    $this->admin = $this->superAdmin();
    $this->flushSession();
    $this->actingAs($this->admin, 'web');
});

function saveRecordRules(TestCase $t, string $form, array $rules): TestResponse
{
    $hash = $t->getJson("/api/v1/forms/{$form}/record-access-rules")->assertOk()->json('data.hash');

    return $t->putJson("/api/v1/forms/{$form}/record-access-rules", ['rules' => $rules, 'base_hash' => $hash]);
}

/** @return array<string, mixed> */
function scopeRule(string $scope, string $operation = 'all', array $subject = ['type' => 'everyone'], string $effect = 'allow', ?array $condition = null): array
{
    return ['uuid' => uid(), 'subject' => $subject, 'operation' => $operation, 'scope' => $scope, 'effect' => $effect, 'condition' => $condition];
}

it('limits every record query to the user scope and answers 404 outside it', function () {
    [, $form] = createForm($this, 'tickets');
    saveDraft($this, $form, [], [fieldDoc('subject', 'text', null, ['table' => ['filterable' => true]]), fieldDoc('secret', 'checkbox')])->assertOk();
    expect(publish($this, $form)['status'])->toBe('applied');
    $alice = $this->makeUser();
    $bob = $this->makeUser();
    foreach ([$alice, $bob] as $u) {
        foreach (['view', 'create', 'edit', 'delete', 'export'] as $a) {
            grantPermission($u->id, "form.{$form}.{$a}");
        }
    }
    // Everyone sees only their own records; secret ones are excluded even for their owners.
    saveRecordRules($this, $form, [
        scopeRule('own'),
        scopeRule('custom', 'view', ['type' => 'everyone'], 'deny', ['k' => 'bin', 'op' => '=', 'a' => ['k' => 'ref', 'scope' => 'record', 'path' => ['secret']], 'b' => ['k' => 'lit', 't' => 'boolean', 'v' => true]]),
    ])->assertOk();

    $this->flushSession();
    $this->actingAs($alice, 'web');
    $mine = $this->postJson("/api/v1/r/{$form}", ['values' => ['subject' => 'Alice 1']])->assertCreated()->json('data.uuid');
    $hidden = $this->postJson("/api/v1/r/{$form}", ['values' => ['subject' => 'Alice secret', 'secret' => true]])->assertCreated()->json('data.uuid');
    expect(array_column($this->getJson("/api/v1/r/{$form}")->assertOk()->json('data'), 'uuid'))->toBe([$mine]);
    $this->getJson("/api/v1/r/{$form}/{$hidden}")->assertNotFound();

    $this->flushSession();
    $this->actingAs($bob, 'web');
    expect($this->getJson("/api/v1/r/{$form}")->assertOk()->json('meta.total'))->toBe(0);
    $this->getJson("/api/v1/r/{$form}/{$mine}")->assertNotFound();
    $this->patchJson("/api/v1/r/{$form}/{$mine}", ['values' => ['subject' => 'x'], 'row_version' => 1])->assertNotFound();
    $this->deleteJson("/api/v1/r/{$form}/{$mine}", ['row_version' => 1])->assertNotFound();
    expect($this->get($this->postJson("/api/v1/r/{$form}/exports", ['format' => 'csv'])->assertOk()->json('data.url'))->streamedContent())->not->toContain('Alice 1');

    // A more specific tier overrides: Bob alone sees all records.
    $this->flushSession();
    $this->actingAs($this->admin, 'web');
    $rules = $this->getJson("/api/v1/forms/{$form}/record-access-rules")->json('data.rules');
    saveRecordRules($this, $form, [...$rules, scopeRule('all', 'view', ['type' => 'user', 'uuid' => $bob->uuid])])->assertOk();
    $explain = $this->getJson("/api/v1/forms/{$form}/record-access-explain?user={$bob->uuid}")->assertOk()->json('data.view');
    expect($explain['scopes'])->toBe(['all']);
    $this->flushSession();
    $this->actingAs($bob, 'web');
    // The hidden record stays out: the "secret" exclusion is a deny of the everyone tier... overridden by Bob's allow.
    expect($this->getJson("/api/v1/r/{$form}")->json('meta.total'))->toBe(2);
    $this->patchJson("/api/v1/r/{$form}/{$mine}", ['values' => ['subject' => 'x'], 'row_version' => 1])->assertNotFound();
});

it('rejects custom record rules the query planner cannot compile', function () {
    [, $form] = createForm($this, 'notes');
    saveDraft($this, $form, [], [fieldDoc('title', 'text')])->assertOk();
    saveRecordRules($this, $form, [scopeRule('custom', 'view', ['type' => 'everyone'], 'allow', ['k' => 'call', 'fn' => 'today', 'args' => []])])
        ->assertStatus(422)->assertJsonValidationErrors(['rules.0.condition']);
    saveRecordRules($this, $form, [scopeRule('custom', 'view', ['type' => 'everyone'], 'allow', ['k' => 'bin', 'op' => '=', 'a' => ['k' => 'ref', 'scope' => 'record', 'path' => ['nope']], 'b' => ['k' => 'lit', 't' => 'text', 'v' => 'x']])])
        ->assertStatus(422);
});

it('asks for a justification after validation, enforces it on the server and keeps it immutable', function () {
    [, $form] = createForm($this, 'salaries');
    $amount = fieldDoc('amount', 'decimal', null, ['validation' => ['required' => true]]);
    $phone = fieldDoc('phone', 'text');
    saveDraft($this, $form, [], [$amount, $phone])->assertOk();
    expect(publish($this, $form)['status'])->toBe('applied');
    $this->postJson('/api/v1/justification-reason-codes', ['set_key' => 'pay', 'code' => 'RAISE', 'label' => ['en' => 'Raise', 'ar' => 'زيادة']])->assertCreated();
    $otherCode = $this->postJson('/api/v1/justification-reason-codes', ['set_key' => 'pay', 'code' => 'OTHER', 'label' => ['en' => 'Other'], 'requires_note' => true])->assertCreated()->json('data');
    $other = $otherCode['uuid'];
    $this->patchJson("/api/v1/justification-reason-codes/{$other}", ['sort_order' => 2, 'base_updated_at' => $otherCode['updated_at']])->assertOk();
    $this->patchJson("/api/v1/justification-reason-codes/{$other}", ['sort_order' => 3, 'base_updated_at' => $otherCode['updated_at']])->assertStatus(409);
    $hash = $this->getJson("/api/v1/forms/{$form}/justification-rules")->assertOk()->json('data.hash');
    $this->putJson("/api/v1/forms/{$form}/justification-rules", ['base_hash' => $hash, 'rules' => [[
        'uuid' => uid(), 'scope' => 'field', 'target' => $amount['uuid'], 'subject' => ['type' => 'everyone'], 'level' => 'mandatory',
        'condition' => null, 'levelWhen' => null, 'text' => ['min' => 5, 'max' => 200], 'reasonCodes' => ['mode' => 'required', 'source' => 'codes', 'set' => 'pay'],
        'attachments' => ['mode' => 'none'], 'showSummary' => true, 'active' => true, 'i18n' => ['title' => ['en' => 'Why?'], 'help' => []],
    ]]])->assertOk();

    $rec = $this->postJson("/api/v1/r/{$form}", ['values' => ['amount' => '100', 'phone' => '1']])->assertCreated()->json('data.uuid');
    // A phone change needs nothing; a failing save returns validation errors only.
    $this->patchJson("/api/v1/r/{$form}/{$rec}", ['values' => ['phone' => '2'], 'row_version' => 1])->assertOk();
    $this->patchJson("/api/v1/r/{$form}/{$rec}", ['values' => ['amount' => null], 'row_version' => 2])->assertStatus(422)->assertJsonPath('code', 'invalid');
    $prompt = $this->patchJson("/api/v1/r/{$form}/{$rec}", ['values' => ['amount' => '150'], 'row_version' => 2])->assertStatus(422)->assertJsonPath('code', 'justification_required')->json('justification');
    expect($prompt['title'])->toBe('Why?')->and($prompt['reason_codes']['mode'])->toBe('required')->and($prompt['changes'][0]['field'])->toBe('amount');
    $this->patchJson("/api/v1/r/{$form}/{$rec}", ['values' => ['amount' => '150'], 'row_version' => 2, 'justification' => ['reason_text' => 'ok', 'reason_code' => $other]])
        ->assertStatus(422)->assertJsonPath('code', 'justification_invalid')->assertJsonStructure(['errors' => ['reason_text', 'note']]);
    $this->patchJson("/api/v1/r/{$form}/{$rec}", ['values' => ['amount' => '150'], 'row_version' => 2, 'justification' => ['reason_text' => 'Annual review', 'reason_code' => $other, 'note' => 'Board decision']])->assertOk();

    $list = $this->getJson("/api/v1/r/{$form}/{$rec}/justifications")->assertOk()->json('data');
    expect($list)->toHaveCount(1)->and($list[0]['reason_text'])->toBe('Annual review')->and($list[0]['reason_code']['label'])->toBe('Other')
        ->and($list[0]['changed_fields'][0]['field'])->toBe('amount');
    $entry = DB::table('audit_logs')->where('event', 'record.updated')->orderByDesc('id')->first();
    expect($entry->justification_id)->not->toBeNull();
    $history = $this->getJson("/api/v1/r/{$form}/{$rec}/history")->json('data');
    expect($history[0]['justification']['reason_text'])->toBe('Annual review');

    $saved = Justification::query()->firstOrFail();
    expect(fn () => $saved->forceFill(['reason_text' => 'tampered'])->save())->toThrow(LogicException::class);
    expect(fn () => $saved->delete())->toThrow(LogicException::class);

    // Without View Justifications the text is not readable.
    $clerk = $this->makeUser();
    grantPermission($clerk->id, "form.{$form}.view");
    $this->flushSession();
    $this->actingAs($clerk, 'web');
    $this->getJson("/api/v1/r/{$form}/{$rec}/justifications")->assertForbidden();
});

it('assigns by rule, lets queue members claim, guards claimed records and lists My Work', function () {
    $wf = buildWorkflowForm($this);
    $form = $wf['form'];
    $role = DB::table('roles')->where('key', 'user')->first();
    $a = $this->makeUser();
    $b = $this->makeUser();
    foreach ([$a, $b] as $u) {
        foreach (['view', 'edit'] as $ab) {
            grantPermission($u->id, "form.{$form}.{$ab}");
        }
    }
    $hash = $this->getJson("/api/v1/forms/{$form}/assignment-rules")->assertOk()->json('data.hash');
    $this->putJson("/api/v1/forms/{$form}/assignment-rules", ['base_hash' => $hash, 'rules' => [
        ['uuid' => uid(), 'transition' => $wf['submit']['uuid'], 'strategy' => 'role', 'target' => ['type' => 'role', 'uuid' => $role->uuid], 'field' => null, 'condition' => null, 'dueInMinutes' => 60, 'workingTime' => false, 'priority' => 5],
    ]])->assertOk();
    $queue = $this->postJson('/api/v1/queues', ['key' => 'users', 'name' => ['en' => 'Users'], 'type' => 'role', 'subject' => $role->uuid, 'forms' => [['form' => $form, 'columns' => [['subject']]]]])->assertCreated()->json('data');
    // A queue edited elsewhere since it was loaded is never overwritten.
    $saved = $this->patchJson("/api/v1/queues/{$queue['uuid']}", ['claim_timeout_minutes' => 30, 'base_updated_at' => $queue['updated_at']])->assertOk()->json('data');
    $this->patchJson("/api/v1/queues/{$queue['uuid']}", ['claim_timeout_minutes' => 45, 'base_updated_at' => $queue['updated_at']])->assertStatus(409)->assertJsonPath('code', 'queue_changed');
    expect($saved['claim_timeout_minutes'])->toBe(30);

    $rec = $this->postJson("/api/v1/r/{$form}", ['values' => ['subject' => 'Help', 'reason' => 'x']])->assertCreated()->json('data.uuid');
    $this->postJson("/api/v1/r/{$form}/{$rec}/transitions/{$wf['submit']['uuid']}", ['row_version' => 1, 'comment' => 'c'])->assertOk();
    expect(DB::table('assignments')->where('status', 'active')->where('assignee_type', 'role')->count())->toBe(1);

    $this->flushSession();
    $this->actingAs($a, 'web');
    $work = $this->getJson('/api/v1/my-work')->assertOk()->json('data');
    expect($work)->toHaveCount(1)->and($work[0]['columns'][0]['value'])->toBe('Help')->and($work[0]['priority'])->toBe(5)->and($work[0]['claimable'])->toBeTrue();
    $this->postJson("/api/v1/r/{$form}/{$rec}/claim")->assertOk()->assertJsonPath('data.by.uuid', strtolower($a->uuid));

    $this->flushSession();
    $this->actingAs($b, 'web');
    $this->postJson("/api/v1/r/{$form}/{$rec}/claim")->assertStatus(409)->assertJsonPath('code', 'already_claimed');
    $this->patchJson("/api/v1/r/{$form}/{$rec}", ['values' => ['reason' => 'mine'], 'row_version' => 2])->assertStatus(423);
    $this->postJson("/api/v1/r/{$form}/{$rec}/release")->assertForbidden();

    $this->flushSession();
    $this->actingAs($a, 'web');
    $this->patchJson("/api/v1/r/{$form}/{$rec}", ['values' => ['reason' => 'mine'], 'row_version' => 2])->assertOk();
    $this->postJson("/api/v1/r/{$form}/{$rec}/release")->assertNoContent();
    expect(DB::table('queue_claims')->whereNotNull('active_key')->count())->toBe(0);

    // Manual reassignment needs Reassign Records and is audited.
    $this->postJson("/api/v1/r/{$form}/{$rec}/assign", ['assignee' => ['type' => 'user', 'uuid' => $b->uuid]])->assertForbidden();
    $this->flushSession();
    $this->actingAs($this->admin, 'web');
    $this->postJson("/api/v1/r/{$form}/{$rec}/assign", ['assignee' => ['type' => 'user', 'uuid' => $b->uuid]])->assertCreated()->assertJsonPath('data.reassigned', true);
    expect(DB::table('assignments')->where('status', 'reassigned')->count())->toBe(1)
        ->and(DB::table('audit_logs')->where('event', 'record.reassigned')->count())->toBe(1);
});

it('lets a delegate act for the delegator and records it on their behalf', function () {
    $wf = buildWorkflowForm($this);
    $form = $wf['form'];
    $boss = $this->makeUser();
    $deputy = $this->makeUser();
    foreach (['view', 'edit'] as $ab) {
        grantPermission($boss->id, "form.{$form}.{$ab}");
    }
    grantPermission($boss->id, 'transition.'.$wf['approve']['uuid'].'.perform');
    grantPermission($boss->id, 'system.delegate_own_work');
    $rec = $this->postJson("/api/v1/r/{$form}", ['values' => ['subject' => 'Budget', 'reason' => 'x']])->assertCreated()->json('data.uuid');
    $this->postJson("/api/v1/r/{$form}/{$rec}/transitions/{$wf['submit']['uuid']}", ['row_version' => 1, 'comment' => 'c'])->assertOk();

    $this->flushSession();
    $this->actingAs($deputy, 'web');
    $this->getJson("/api/v1/r/{$form}/{$rec}")->assertNotFound();

    $this->flushSession();
    $this->actingAs($boss, 'web');
    $this->postJson('/api/v1/delegations', ['delegate' => $deputy->uuid, 'type' => 'delegation', 'starts_at' => now()->subMinute()->toIso8601String(), 'ends_at' => now()->addDay()->toIso8601String(), 'reason' => 'Leave', 'forms' => [$form]])->assertCreated();
    // Out-of-office cover for someone else needs Manage Delegation.
    $this->postJson('/api/v1/delegations', ['delegator' => $deputy->uuid, 'delegate' => $boss->uuid, 'type' => 'out_of_office', 'starts_at' => now()->toIso8601String(), 'ends_at' => now()->addDay()->toIso8601String(), 'reason' => 'Cover'])->assertForbidden();

    $this->flushSession();
    $this->actingAs($deputy, 'web');
    $this->getJson("/api/v1/r/{$form}/{$rec}")->assertOk();
    $transitions = $this->getJson("/api/v1/r/{$form}/{$rec}/transitions")->assertOk()->json('data');
    expect(array_column($transitions, 'key'))->toBe(['approve']);
    $this->postJson("/api/v1/r/{$form}/{$rec}/transitions/{$wf['approve']['uuid']}", ['row_version' => 2])->assertOk();
    $h = DB::table('status_history')->orderByDesc('id')->first();
    expect((int) $h->acted_by)->toBe($deputy->id)->and((int) $h->on_behalf_of_user_id)->toBe($boss->id);
    expect((int) DB::table('audit_logs')->where('event', 'record.transitioned')->orderByDesc('id')->value('on_behalf_of_user_id'))->toBe($boss->id);
});
