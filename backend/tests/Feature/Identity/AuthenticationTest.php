<?php

declare(strict_types=1);

use App\Modules\Audit\Models\AuditLog;
use App\Modules\Core\Settings\SettingsService;
use App\Modules\Identity\Models\LoginAttempt;
use Tests\TestCase;

beforeEach(fn () => $this->completeSetup());

it('refuses every login before setup is complete', function () {
    app(SettingsService::class)->write('setup', 'completed_at', null);
    $user = $this->makeUser();
    $this->postJson('/api/v1/auth/login', ['email' => $user->email, 'password' => TestCase::PASSWORD])
        ->assertUnprocessable();
    $this->assertGuest('web');
});

it('signs a user in with valid credentials and records the attempt', function () {
    $user = $this->makeUser();
    $this->postJson('/api/v1/auth/login', ['email' => $user->email, 'password' => TestCase::PASSWORD])
        ->assertOk()->assertJsonPath('two_factor', false);
    $this->assertAuthenticatedAs($user, 'web');
    expect(LoginAttempt::query()->where('user_id', $user->id)->where('successful', true)->exists())->toBeTrue()
        ->and(AuditLog::query()->where('event', 'auth.login')->where('object_id', $user->id)->exists())->toBeTrue();
    // The identifier is stored as an HMAC, never in clear text.
    expect(LoginAttempt::query()->where('user_id', $user->id)->value('identifier_hash'))->not->toContain('@');
});

it('rejects a wrong password with a generic message', function () {
    $user = $this->makeUser();
    $this->postJson('/api/v1/auth/login', ['email' => $user->email, 'password' => 'wrong-password'])
        ->assertUnprocessable()->assertJsonValidationErrors('email');
    $this->postJson('/api/v1/auth/login', ['email' => 'nobody@example.test', 'password' => 'wrong-password'])
        ->assertUnprocessable()->assertJsonValidationErrors('email');
    $this->assertGuest('web');
});

it('locks the account after the configured number of failures', function () {
    app(SettingsService::class)->write('security', 'lockout_max_attempts', 3);
    $user = $this->makeUser();
    foreach (range(1, 3) as $_) {
        $this->postJson('/api/v1/auth/login', ['email' => $user->email, 'password' => 'nope-nope'])->assertUnprocessable();
    }
    expect($user->fresh()->isLocked())->toBeTrue();
    // Even the right password is refused while locked.
    $this->postJson('/api/v1/auth/login', ['email' => $user->email, 'password' => TestCase::PASSWORD])->assertUnprocessable();
    $this->assertGuest('web');
    expect(AuditLog::query()->where('event', 'auth.locked_out')->exists())->toBeTrue();

    $this->travel(16)->minutes();
    $this->postJson('/api/v1/auth/login', ['email' => $user->email, 'password' => TestCase::PASSWORD])->assertOk();
});

it('refuses suspended accounts', function () {
    $user = $this->makeUser(attributes: ['status' => 'suspended']);
    $this->postJson('/api/v1/auth/login', ['email' => $user->email, 'password' => TestCase::PASSWORD])->assertUnprocessable();
    $this->assertGuest('web');
});

it('challenges enrolled users for a TOTP code before signing them in', function () {
    $admin = $this->superAdmin();
    $this->postJson('/api/v1/auth/login', ['email' => $admin->email, 'password' => TestCase::PASSWORD])
        ->assertOk()->assertJsonPath('two_factor', true);
    $this->assertGuest('web');

    $this->postJson('/api/v1/auth/two-factor-challenge', ['code' => '000000'])->assertUnprocessable();
    $this->assertGuest('web');

    $this->postJson('/api/v1/auth/two-factor-challenge', ['code' => $this->totp()])->assertNoContent();
    $this->assertAuthenticatedAs($admin, 'web');
});

it('accepts a one-time recovery code instead of the TOTP code', function () {
    $admin = $this->superAdmin();
    $this->postJson('/api/v1/auth/login', ['email' => $admin->email, 'password' => TestCase::PASSWORD])->assertOk();
    $this->postJson('/api/v1/auth/two-factor-challenge', ['recovery_code' => 'recovery-code-1'])->assertNoContent();
    $this->assertAuthenticatedAs($admin, 'web');
    expect($admin->fresh()->recoveryCodes())->not->toContain('recovery-code-1');
});

it('blocks administrators who have not enrolled in 2FA from everything but enrollment', function () {
    $admin = $this->makeUser(['admin'], twoFactor: false);
    $this->actingAs($admin, 'web');
    $this->getJson('/api/v1/users')->assertForbidden()->assertJsonPath('code', 'two_factor_enrollment_required');
    $this->getJson('/api/v1/me')->assertOk()
        ->assertJsonPath('data.two_factor.required', true)
        ->assertJsonPath('data.two_factor.enabled', false);
});

it('does not require 2FA for ordinary users', function () {
    $user = $this->makeUser();
    $this->actingAs($user, 'web');
    $this->getJson('/api/v1/admin/console')->assertOk()->assertJsonPath('data.areas', []);
});

it('signs the user out', function () {
    $user = $this->makeUser();
    $this->postJson('/api/v1/auth/login', ['email' => $user->email, 'password' => TestCase::PASSWORD])->assertOk();
    $this->postJson('/api/v1/auth/logout')->assertNoContent();
    $this->assertGuest('web');
    expect(AuditLog::query()->where('event', 'auth.logout')->exists())->toBeTrue();
});

it('answers unauthenticated API calls with 401 JSON', function () {
    $this->getJson('/api/v1/me')->assertUnauthorized()->assertJsonPath('code', 'unauthenticated');
});
