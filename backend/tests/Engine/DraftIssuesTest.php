<?php

declare(strict_types=1);

use App\Modules\Monitoring\Models\ErrorLog;

require_once __DIR__.'/helpers.php';

beforeEach(function () {
    $this->completeSetup();
    $this->admin = $this->superAdmin();
    $this->flushSession();
    $this->actingAs($this->admin, 'web');
});

it('reports a schema-invalid draft as a sentence with a reference, never as schema detail', function () {
    [, $form] = createForm($this, 'issue_probe');
    $code = fieldDoc('code', 'text');
    // An incomplete condition: the date value is empty.
    $rule = ['uuid' => uid(), 'owner' => ['type' => 'field', 'uuid' => $code['uuid']], 'name' => null,
        'when' => ['k' => 'bin', 'op' => '=', 'a' => ['k' => 'ref', 'scope' => 'record', 'path' => ['code']], 'b' => ['k' => 'lit', 't' => 'date', 'v' => '']],
        'effects' => [], 'else' => [], 'evaluateOn' => 'always', 'runtime' => 'client_and_server', 'order' => 0, 'active' => true];

    $response = saveDraft($this, $form, [], [$code], [], [$rule])->assertStatus(422)->assertJsonPath('code', 'draft_invalid');
    $errors = $response->json('errors');
    expect($errors)->toHaveCount(1)
        ->and($errors[0]['path'])->toBe('conditions.0.when')
        ->and($errors[0]['code'])->toBe('invalid_value');
    $reference = $errors[0]['params']['reference'];
    expect($errors[0]['message'])->toContain($reference);

    // Nothing technical reaches the client; Error Monitoring has the detail under the reference.
    expect($response->getContent())->not->toContain('required properties')->not->toContain('/conditions')->not->toContain('const value');
    $log = ErrorLog::query()->where('reference_code', $reference)->firstOrFail();
    expect($log->severity)->toBe('warning')->and($log->message)->toContain('/conditions/0/when/b');
});

it('does not expose schema detail when a field template is invalid', function () {
    $response = $this->postJson('/api/v1/field-templates', [
        'kind' => 'field', 'name' => ['en' => 'Bad'], 'definition' => ['fields' => [['uuid' => 'not-a-uuid']]],
    ])->assertStatus(422);
    expect($response->getContent())->not->toContain('required properties')->toContain('E-');
});

it('keeps empty and space-padded text literals in conditions exactly as sent', function () {
    [, $form] = createForm($this, 'empty_literal');
    $code = fieldDoc('code', 'text');
    $when = ['k' => 'bin', 'op' => 'or',
        'a' => ['k' => 'bin', 'op' => '=', 'a' => ['k' => 'ref', 'scope' => 'record', 'path' => ['code']], 'b' => ['k' => 'lit', 't' => 'text', 'v' => '']],
        'b' => ['k' => 'bin', 'op' => '=', 'a' => ['k' => 'ref', 'scope' => 'record', 'path' => ['code']], 'b' => ['k' => 'lit', 't' => 'text', 'v' => ' A ']]];
    // An empty rule name outside the expression is still read as "no name".
    $rule = ['uuid' => uid(), 'owner' => ['type' => 'field', 'uuid' => $code['uuid']], 'name' => '', 'when' => $when,
        'effects' => [], 'else' => [], 'evaluateOn' => 'always', 'runtime' => 'client_and_server', 'order' => 0, 'active' => true];

    saveDraft($this, $form, [], [$code], [], [$rule])->assertOk();
    $saved = $this->getJson("/api/v1/forms/{$form}/draft")->assertOk()->json('data.document.conditions.0');
    expect($saved['when'])->toEqual($when)->and($saved['name'])->toBeNull();

    $this->postJson('/api/v1/expressions/check', ['ast' => $when['a'], 'form' => '', 'expected' => 'boolean'])
        ->assertOk()->assertJsonPath('data.ok', true);
});
