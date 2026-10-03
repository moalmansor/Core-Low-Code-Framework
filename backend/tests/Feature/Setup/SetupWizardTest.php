<?php

declare(strict_types=1);

use App\Modules\Audit\Models\AuditLog;
use App\Modules\Core\Models\Locale;
use App\Modules\Core\Settings\SettingsService;
use App\Modules\Identity\Models\User;
use App\Modules\Setup\SetupState;
use PragmaRX\Google2FA\Google2FA;

function setupPayload(array $overrides = []): array
{
    return array_replace_recursive([
        'system_name' => ['en' => 'Acme Platform', 'ar' => 'منصة أكمي'],
        'default_locale' => 'ar',
        'enabled_locales' => ['ar', 'en'],
        'formats' => ['timezone' => 'Asia/Riyadh', 'date_format' => 'dd/MM/yyyy', 'time_format' => '12h', 'first_day_of_week' => 0],
        'calendar' => ['system' => 'both'],
        'tenancy' => ['mode' => 'single'],
        'mail' => ['host' => 'smtp.example.test', 'port' => 587, 'encryption' => 'tls', 'username' => 'mailer', 'password' => 's3cret-smtp', 'from_address' => 'noreply@example.test', 'from_name' => 'Acme'],
        'admin' => ['name' => 'Root Admin', 'email' => 'root@example.test', 'password' => 'Vq7!mR2#tL9$wZ4p', 'password_confirmation' => 'Vq7!mR2#tL9$wZ4p'],
    ], $overrides);
}

function startTwoFactor($test, string $token): string
{
    $response = $test->withHeader('X-Setup-Token', $token)->postJson('/api/v1/setup/two-factor', ['email' => 'root@example.test'])->assertOk();

    return $response->json('data.secret');
}

it('reports the wizard as open before setup and exposes no secrets', function () {
    $this->getJson('/api/v1/setup/status')->assertOk()
        ->assertJsonPath('data.completed', false)
        ->assertJsonMissingPath('data.defaults.mail.password');
    $this->getJson('/api/v1/bootstrap')->assertOk()->assertJsonPath('data.setup_completed', false);
});

it('requires the console-issued setup token for every step', function () {
    $token = app(SetupState::class)->issueToken();
    $this->postJson('/api/v1/setup/token', ['token' => 'wrong'])->assertStatus(422);
    $this->postJson('/api/v1/setup/token', ['token' => $token])->assertNoContent();
    $this->postJson('/api/v1/setup/complete', setupPayload())->assertForbidden()->assertJsonPath('code', 'invalid_setup_token');
    $this->withHeader('X-Setup-Token', 'nope')->postJson('/api/v1/setup/two-factor', ['email' => 'a@b.test'])->assertForbidden();
});

it('completes setup, creates an enrolled super admin, applies settings, and locks the wizard', function () {
    $token = app(SetupState::class)->issueToken();
    $secret = startTwoFactor($this, $token);
    $code = (new Google2FA)->getCurrentOtp($secret);

    $response = $this->withHeader('X-Setup-Token', $token)
        ->postJson('/api/v1/setup/complete', setupPayload(['two_factor_code' => $code]))
        ->assertCreated();
    expect($response->json('data.recovery_codes'))->toHaveCount(8);

    $admin = User::query()->where('email', 'root@example.test')->firstOrFail();
    expect($admin->hasEnabledTwoFactorAuthentication())->toBeTrue()
        ->and($admin->roleKeys())->toBe(['super_admin'])
        ->and($admin->preference->locale)->toBe('ar');
    $this->assertAuthenticatedAs($admin, 'web');

    $settings = app(SettingsService::class);
    expect($settings->get('formats', 'timezone'))->toBe('Asia/Riyadh')
        ->and($settings->get('calendar', 'system'))->toBe('both')
        ->and($settings->get('mail', 'password'))->toBe('s3cret-smtp');
    // The SMTP password is encrypted at rest.
    $row = DB::table('settings')->where('group', 'mail')->where('key', 'password')->first();
    expect($row->is_encrypted)->toBeTruthy()->and((string) $row->encrypted_value)->not->toContain('s3cret-smtp');

    expect(Locale::query()->where('is_default', true)->value('code'))->toBe('ar');
    expect(AuditLog::query()->where('event', 'setup.completed')->exists())->toBeTrue();

    // Locked for good: every setup endpoint answers 404, the token is gone.
    $this->getJson('/api/v1/setup/status')->assertNotFound();
    $this->withHeader('X-Setup-Token', $token)->postJson('/api/v1/setup/complete', setupPayload())->assertNotFound();
    expect(app(SetupState::class)->hasToken())->toBeFalse();
    $this->getJson('/api/v1/bootstrap')->assertJsonPath('data.setup_completed', true);
    $this->artisan('setup:token')->assertExitCode(1);
});

it('enforces the password policy and a valid authenticator code', function () {
    $token = app(SetupState::class)->issueToken();
    $secret = startTwoFactor($this, $token);
    $code = (new Google2FA)->getCurrentOtp($secret);

    $this->withHeader('X-Setup-Token', $token)
        ->postJson('/api/v1/setup/complete', setupPayload(['two_factor_code' => $code, 'admin' => ['password' => 'password123', 'password_confirmation' => 'password123']]))
        ->assertUnprocessable()->assertJsonValidationErrors(['password']);

    $this->withHeader('X-Setup-Token', $token)
        ->postJson('/api/v1/setup/complete', setupPayload(['two_factor_code' => '000000']))
        ->assertUnprocessable()->assertJsonValidationErrors(['two_factor_code']);

    expect(User::query()->count())->toBe(0);
});

it('rejects a default locale that is not enabled and unknown settings keys', function () {
    $token = app(SetupState::class)->issueToken();
    $secret = startTwoFactor($this, $token);
    $code = (new Google2FA)->getCurrentOtp($secret);
    $payload = setupPayload(['two_factor_code' => $code]);
    $payload['enabled_locales'] = ['en'];
    $this->withHeader('X-Setup-Token', $token)->postJson('/api/v1/setup/complete', $payload)
        ->assertUnprocessable()->assertJsonValidationErrors(['enabled_locales']);

    $payload = setupPayload(['two_factor_code' => $code, 'formats' => ['evil' => 1]]);
    $this->withHeader('X-Setup-Token', $token)->postJson('/api/v1/setup/complete', $payload)
        ->assertUnprocessable()->assertJsonValidationErrors(['formats']);
});
