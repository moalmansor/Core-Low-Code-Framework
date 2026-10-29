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

function ffFlag(string $key, string $type, ?string $target, array $audience = [], bool $enabled = true): array
{
    return ['key' => $key, 'type' => $type, 'target' => $target, 'enabled' => $enabled, 'application' => null,
        'audience' => $audience + ['roles' => [], 'departments' => [], 'users' => []], 'i18n' => ['description' => ['en' => 'Pilot']]];
}

it('releases a form to its pilot group only: hidden from navigation, 404 to everyone else', function () {
    [$app, $form] = createForm($this, 'pilot_requests');
    saveDraft($this, $form, [], [fieldDoc('title', 'text')])->assertOk();
    expect(publish($this, $form, ['placement' => ['application' => $app, 'label' => ['en' => 'Pilot requests']]])['status'])->toBe('applied');
    $record = $this->postJson("/api/v1/r/{$form}", ['values' => ['title' => 'First']])->assertCreated()->json('data.uuid');

    $pilot = $this->makeUser();
    $other = $this->makeUser();
    foreach ([$pilot, $other] as $u) {
        grantPermission($u->id, "app.{$app}.access");
        grantPermission($u->id, "form.{$form}.view");
    }
    $this->postJson('/api/v1/feature-flags', ['flag' => ffFlag('new_requests', 'form', $form, ['users' => [$pilot->uuid]])])->assertCreated();
    $this->postJson('/api/v1/feature-flags', ['flag' => ffFlag('again', 'form', $form)])->assertUnprocessable()->assertJsonValidationErrors('target');

    $this->flushSession();
    $this->actingAs($other, 'web');
    $this->getJson("/api/v1/r/{$form}")->assertNotFound();
    $this->getJson("/api/v1/r/{$form}/{$record}")->assertNotFound();
    expect(json_encode($this->getJson('/api/v1/navigation')->assertOk()->json('data')))->not->toContain('Pilot requests');

    $this->flushSession();
    $this->actingAs($pilot, 'web');
    $this->getJson("/api/v1/r/{$form}")->assertOk();
    $this->getJson("/api/v1/r/{$form}/{$record}")->assertOk();
    expect(json_encode($this->getJson('/api/v1/navigation')->assertOk()->json('data')))->toContain('Pilot requests');

    // Turned off: no one, the pilot included.
    $this->flushSession();
    $this->actingAs($this->admin, 'web');
    $flag = collect($this->getJson('/api/v1/feature-flags')->assertOk()->json('data'))->firstWhere('key', 'new_requests');
    $doc = ffFlag('new_requests', 'form', $form, ['users' => [$pilot->uuid]], false);
    $this->putJson("/api/v1/feature-flags/{$flag['uuid']}", ['flag' => $doc, 'base_hash' => $flag['hash']])->assertOk();
    $this->flushSession();
    $this->actingAs($pilot, 'web');
    $this->getJson("/api/v1/r/{$form}")->assertNotFound();
    expect(DB::table('audit_logs')->where('event', 'like', 'feature_flag.%')->count())->toBe(2);
});

it('gates a capability and tells the screens, and only "Manage feature flags" manages flags', function () {
    $user = $this->makeUser();
    $this->postJson('/api/v1/feature-flags', ['flag' => ffFlag('anything', 'capability', 'personal_api_tokens', ['roles' => [strtolower((string) DB::table('roles')->where('key', 'admin')->value('uuid'))]])])
        ->assertCreated()->assertJsonPath('data.key', 'personal_api_tokens');
    $this->postJson('/api/v1/feature-flags', ['flag' => ffFlag('nope', 'capability', 'teleport')])->assertUnprocessable()->assertJsonValidationErrors('target');

    $this->flushSession();
    $this->actingAs($user, 'web');
    expect($this->getJson('/api/v1/me')->json('data.capabilities_off'))->toBe(['personal_api_tokens']);
    $this->getJson('/api/v1/me/tokens')->assertForbidden()->assertJsonPath('code', 'feature_off');
    $this->getJson('/api/v1/feature-flags')->assertForbidden();
});
