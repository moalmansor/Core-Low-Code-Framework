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

function trTour(?string $form, ?string $place, array $steps, string $trigger = 'first_use', array $roles = []): array
{
    return ['form' => $form, 'place' => $place, 'trigger' => $trigger, 'roles' => $roles, 'active' => true,
        'i18n' => ['name' => ['en' => 'Getting started', 'ar' => 'البداية']], 'steps' => $steps];
}

function trStep(string $key, ?string $target, string $body = 'Start here'): array
{
    return ['key' => $key, 'target' => $target, 'placement' => 'auto', 'i18n' => ['title' => ['en' => ucfirst($key)], 'body' => ['en' => $body, 'ar' => 'ابدأ هنا']]];
}

it('runs a first-use tour on a form once per version, and only for people who may open the form', function () {
    [, $form] = createForm($this, 'expenses');
    saveDraft($this, $form, [], [fieldDoc('amount', 'number')])->assertOk();
    expect(publish($this, $form)['status'])->toBe('applied');

    // Steps point at fields of the form, named parts of the screen, or nothing.
    $this->postJson('/api/v1/tours', ['tour' => trTour($form, null, [trStep('one', '[data-field="nope"]')])])->assertUnprocessable()->assertJsonValidationErrors('steps.0.target');
    $this->postJson('/api/v1/tours', ['tour' => trTour($form, null, [trStep('one', 'body script')])])->assertUnprocessable()->assertJsonValidationErrors('steps.0.target');
    $this->postJson('/api/v1/tours', ['tour' => trTour($form, null, [trStep('one', null), trStep('one', null)])])->assertUnprocessable()->assertJsonValidationErrors('steps.1.key');
    $tour = $this->postJson('/api/v1/tours', ['tour' => trTour($form, null, [
        trStep('welcome', null, '<b>Welcome</b>'), trStep('amount', '[data-field="amount"]'), trStep('save', '[data-tour="save_record"]'),
    ])])->assertCreated()->json('data');
    expect($tour['version'])->toBe(1);
    expect($tour['steps'][0]['i18n']['body']['en'])->toBe('Welcome');

    $user = $this->makeUser();
    grantPermission($user->id, "form.{$form}.view");
    $stranger = $this->makeUser();
    $this->flushSession();
    $this->actingAs($user, 'web');
    $mine = $this->getJson("/api/v1/tours/mine?form={$form}")->assertOk()->json('data');
    expect($mine)->toHaveCount(1);
    expect($mine[0]['due'])->toBeTrue();
    expect(array_column($mine[0]['steps'], 'target'))->toBe([null, '[data-field="amount"]', '[data-tour="save_record"]']);
    $this->postJson("/api/v1/tours/{$tour['uuid']}/progress", ['outcome' => 'completed'])->assertNoContent();
    expect($this->getJson("/api/v1/tours/mine?form={$form}")->json('data.0.due'))->toBeFalse();
    // "Show tips again".
    $this->deleteJson('/api/v1/me/tours')->assertNoContent();
    expect($this->getJson("/api/v1/tours/mine?form={$form}")->json('data.0.due'))->toBeTrue();
    $this->postJson("/api/v1/tours/{$tour['uuid']}/progress", ['outcome' => 'dismissed'])->assertNoContent();
    $this->getJson('/api/v1/tours')->assertForbidden();

    $this->flushSession();
    $this->actingAs($stranger, 'web');
    expect($this->getJson("/api/v1/tours/mine?form={$form}")->json('data'))->toBe([]);
    $this->postJson("/api/v1/tours/{$tour['uuid']}/progress", ['outcome' => 'completed'])->assertNotFound();

    // Changing the steps makes a new version, which shows again; a stale save is refused.
    $this->flushSession();
    $this->actingAs($this->admin, 'web');
    $doc = trTour($form, null, [trStep('welcome', null, 'Welcome back')]);
    $this->putJson("/api/v1/tours/{$tour['uuid']}", ['tour' => $doc, 'base_hash' => $tour['hash']])->assertOk()->assertJsonPath('data.version', 2);
    $this->putJson("/api/v1/tours/{$tour['uuid']}", ['tour' => $doc, 'base_hash' => $tour['hash']])->assertStatus(409);
    $this->flushSession();
    $this->actingAs($user, 'web');
    expect($this->getJson("/api/v1/tours/mine?form={$form}")->json('data.0.due'))->toBeTrue();
    // The removed steps' texts are gone.
    expect(DB::table('translations')->where('object_type', 'tour')->where('field', 'like', 'steps.amount.%')->count())->toBe(0);
});

it('runs tours in places for the roles chosen, and an administrator can show one again', function () {
    $role = strtolower((string) DB::table('roles')->where('key', 'admin')->value('uuid'));
    $tour = $this->postJson('/api/v1/tours', ['tour' => trTour(null, 'home', [trStep('search', '[data-tour="search"]')], 'first_use', [$role])])->assertCreated()->json('data.uuid');
    $this->postJson('/api/v1/tours', ['tour' => trTour(null, 'nowhere', [trStep('x', null)])])->assertUnprocessable()->assertJsonValidationErrors('place');
    $this->postJson('/api/v1/tours', ['tour' => trTour(null, 'area.users', [trStep('x', '[data-field="amount"]')])])->assertUnprocessable()->assertJsonValidationErrors('steps.0.target');
    $this->postJson('/api/v1/tours', ['tour' => trTour(null, 'area.users', [trStep('x', null)], 'manual')])->assertCreated();

    $admin = $this->makeUser(['admin']);
    $plain = $this->makeUser();
    $this->flushSession();
    $this->actingAs($plain, 'web');
    expect($this->getJson('/api/v1/tours/mine?place=home')->json('data'))->toBe([]);
    expect($this->getJson('/api/v1/tours/mine?place=area.users')->json('data'))->toBe([]);

    $this->flushSession();
    $this->actingAs($admin, 'web');
    expect($this->getJson('/api/v1/tours/mine?place=home')->json('data.0.due'))->toBeTrue();
    $this->postJson("/api/v1/tours/{$tour}/progress", ['outcome' => 'completed'])->assertNoContent();
    expect($this->getJson('/api/v1/tours/mine?place=home')->json('data.0.due'))->toBeFalse();

    $this->flushSession();
    $this->actingAs($this->admin, 'web');
    $this->postJson("/api/v1/tours/{$tour}/reset", ['roles' => [$role]])->assertOk()->assertJsonPath('data.people', 1);
    expect(DB::table('audit_logs')->where('event', 'tour.reset')->count())->toBe(1);
    $this->flushSession();
    $this->actingAs($admin, 'web');
    expect($this->getJson('/api/v1/tours/mine?place=home')->json('data.0.due'))->toBeTrue();
});
