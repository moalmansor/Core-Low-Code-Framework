<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;

require_once __DIR__.'/helpers.php';

beforeEach(function () {
    $this->completeSetup();
    $this->admin = $this->superAdmin();
    $this->flushSession();
    $this->actingAs($this->admin, 'web');
});

/** A published form with an "amount" field (and, unless told otherwise, a "notes" field). */
function partForm($t, string $key, bool $notes = true): array
{
    [, $form] = createForm($t, $key);
    $amount = fieldDoc('amount', 'number');
    saveDraft($t, $form, [], $notes ? [$amount, fieldDoc('notes', 'text')] : [$amount])->assertOk();
    expect(publish($t, $form)['status'])->toBe('applied');

    return [$form, $amount];
}

function ruleDoc(string $key, ?string $field = null): array
{
    return [
        'uuid' => uid(), 'key' => $key, 'name' => ['en' => ucfirst($key)], 'trigger' => $field === null ? 'record_created' : 'field_changed',
        'fromStatus' => null, 'toStatus' => null, 'field' => $field, 'condition' => null, 'schedule' => null, 'template' => null,
        'channels' => ['in_app'], 'recipients' => ['to' => [['type' => 'creator', 'ref' => null]], 'cc' => [], 'bcc' => []],
        'attachments' => null, 'delayMinutes' => null, 'respectPreferences' => true, 'active' => true,
    ];
}

function putRules($t, string $form, array $rules): void
{
    $hash = $t->getJson("/api/v1/forms/{$form}/notification-rules")->assertOk()->json('data.hash');
    $t->putJson("/api/v1/forms/{$form}/notification-rules", ['base_hash' => $hash, 'rules' => $rules])->assertOk();
}

it('applies a workflow blueprint to a form without one, refuses a form that has one, and propagates a new status', function () {
    [$source] = partForm($this, 'purchases');
    $new = statusDoc('new', true);
    $done = statusDoc('done', false, true);
    saveWorkflow($this, $source, [$new, $done], [transitionDoc('finish', $new, $done)])->assertOk();
    $bp = $this->postJson('/api/v1/blueprints', ['source_type' => 'workflow', 'source' => $source, 'name' => ['en' => 'Two-step']])->assertCreated()->json('data.uuid');

    [$target] = partForm($this, 'expenses');
    $made = $this->postJson("/api/v1/blueprints/{$bp}/instantiate", ['form' => $target])->assertCreated()->json('data');
    expect($made['uuid'])->toBe($target);
    $statuses = $this->getJson("/api/v1/forms/{$target}/workflow")->json('data.document.statuses');
    expect(array_column($statuses, 'key'))->toBe(['new', 'done']);
    // The copy's statuses are its own, not the source's.
    expect(array_column($statuses, 'uuid'))->not->toContain($new['uuid']);
    $this->postJson("/api/v1/blueprints/{$bp}/instantiate", ['form' => $target])->assertUnprocessable()->assertJsonValidationErrors('form');
    expect($this->getJson("/api/v1/blueprints/{$bp}")->json('data.instances.0.form.key'))->toBe('expenses');

    // The source gains a status; the target keeps its local rename of "done".
    $local = $this->getJson("/api/v1/forms/{$target}/workflow")->json('data');
    $local['document']['statuses'][1]['i18n']['name']['en'] = 'Closed';
    $this->putJson("/api/v1/forms/{$target}/workflow", ['document' => $local['document'], 'base_hash' => $local['hash']])->assertOk();
    $done['i18n']['name']['en'] = 'Finished';
    saveWorkflow($this, $source, [$new, $done, statusDoc('cancelled', false, true)], [transitionDoc('finish', $new, $done)])->assertOk();
    $this->postJson("/api/v1/blueprints/{$bp}/versions", [])->assertCreated()->assertJsonPath('data.version', 2);

    $changes = collect($this->getJson("/api/v1/blueprints/{$bp}/propagation-preview")->assertOk()->json('data.0.changes'));
    expect($changes->firstWhere('label', 'cancelled')['status'])->toBe('apply');
    expect($changes->firstWhere('label', 'done')['status'])->toBe('conflict');
    $this->postJson("/api/v1/blueprints/{$bp}/propagate")->assertOk()->assertJsonPath('data.0.status', 'applied');
    $after = collect($this->getJson("/api/v1/forms/{$target}/workflow")->json('data.document.statuses'));
    expect($after->pluck('key')->all())->toBe(['new', 'done', 'cancelled']);
    expect($after->firstWhere('key', 'done')['i18n']['name']['en'])->toBe('Closed');
});

it('adds notification rules from a blueprint, matches fields by key, reports missing ones and refuses taken keys', function () {
    [$source, $amount] = partForm($this, 'invoices');
    $notes = collect($this->getJson("/api/v1/forms/{$source}/draft")->json('data.document.fields'))->firstWhere('key', 'notes');
    putRules($this, $source, [ruleDoc('created'), ruleDoc('amount_changed', $amount['uuid']), ruleDoc('notes_changed', $notes['uuid'])]);
    $bp = $this->postJson('/api/v1/blueprints', ['source_type' => 'notification', 'source' => $source, 'name' => ['en' => 'Invoice alerts']])->assertCreated()->json('data.uuid');

    [$target, $targetAmount] = partForm($this, 'bills', false);
    $made = $this->postJson("/api/v1/blueprints/{$bp}/instantiate", ['form' => $target])->assertCreated()->json('data');
    expect($made['notes'])->toHaveCount(1);
    expect($made['notes'][0])->toContain('notes');
    $rules = collect($this->getJson("/api/v1/forms/{$target}/notification-rules")->json('data.rules'));
    // The rule on "notes", which the target does not have, is left out.
    expect($rules->pluck('key')->all())->toBe(['created', 'amount_changed']);
    // The amount rule watches the target's own amount field.
    expect($rules->firstWhere('key', 'amount_changed')['field'])->toBe($targetAmount['uuid']);

    $this->postJson("/api/v1/blueprints/{$bp}/instantiate", ['form' => $target])->assertUnprocessable()->assertJsonValidationErrors('form');
    // A second rules blueprint on the same form is refused until the first is detached.
    $other = $this->postJson('/api/v1/blueprints', ['source_type' => 'notification', 'source' => $source, 'name' => ['en' => 'Other alerts']])->assertCreated()->json('data.uuid');
    $this->postJson("/api/v1/blueprints/{$other}/instantiate", ['form' => $target])->assertUnprocessable()->assertJsonPath('errors.form.0', __('blueprints.already_linked'));
    $link = $this->getJson("/api/v1/blueprints/{$bp}")->json('data.instances.0.uuid');
    $this->postJson("/api/v1/blueprint-instances/{$link}/detach")->assertNoContent();
    // Detached, the form takes the other blueprint; its rules with the same keys are still refused.
    $this->postJson("/api/v1/blueprints/{$other}/instantiate", ['form' => $target])->assertUnprocessable()->assertJsonValidationErrors('form');
    expect(DB::table('blueprint_instances')->where('object_type', 'notification')->count())->toBe(1);
    // An empty form cannot be captured.
    [$empty] = partForm($this, 'empty_rules');
    $this->postJson('/api/v1/blueprints', ['source_type' => 'notification', 'source' => $empty, 'name' => ['en' => 'Nothing']])->assertUnprocessable()->assertJsonValidationErrors('source');
});

it('makes dashboards from a dashboard blueprint and needs Manage Reports to do it', function () {
    $source = $this->postJson('/api/v1/dashboards', ['dashboard' => [
        'key' => 'overview', 'i18n' => ['name' => ['en' => 'Overview']],
        'widgets' => [['type' => 'text', 'i18n' => ['title' => ['en' => 'Welcome'], 'text' => ['en' => 'Hello']], 'roles' => [], 'layout' => ['w' => 6, 'h' => 2]]],
    ]])->assertCreated()->json('data.uuid');
    $bp = $this->postJson('/api/v1/blueprints', ['source_type' => 'dashboard', 'source' => $source, 'name' => ['en' => 'Overview starter']])->assertCreated()->json('data.uuid');

    $made = $this->postJson("/api/v1/blueprints/{$bp}/instantiate", ['key' => 'overview_west', 'name' => ['en' => 'West overview']])->assertCreated()->json('data');
    $copy = $this->getJson("/api/v1/dashboards/{$made['uuid']}")->assertOk()->json('data');
    expect($copy['label'])->toBe('West overview');
    expect($copy['widgets'])->toHaveCount(1);
    expect($copy['widgets'][0]['i18n']['text']['en'])->toBe('Hello');
    $this->postJson("/api/v1/blueprints/{$bp}/instantiate", ['key' => 'overview_west'])->assertUnprocessable()->assertJsonValidationErrors('key');
    expect(DB::table('blueprint_instances')->where('object_type', 'dashboard')->count())->toBe(1);
    $shown = $this->getJson("/api/v1/blueprints/{$bp}")->assertOk()->json('data');
    expect($shown['source']['name'])->toBe('Overview');
    expect($shown['instances'][0]['dashboard']['name'])->toBe('West overview');
    expect($shown['instances'][0]['form'])->toBeNull();

    // Manage Blueprints alone is not enough.
    $user = $this->makeUser();
    grantPermission($user->id, 'system.manage_blueprints');
    $this->flushSession();
    $this->actingAs($user, 'web');
    $this->postJson("/api/v1/blueprints/{$bp}/instantiate", ['key' => 'overview_east'])->assertForbidden();
});
