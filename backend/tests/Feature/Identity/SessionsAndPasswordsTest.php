<?php

declare(strict_types=1);

use App\Modules\Core\Settings\SettingsService;
use App\Modules\Identity\Models\PasswordHistory;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

beforeEach(fn () => $this->completeSetup());

function login($test, $user): void
{
    $test->postJson('/api/v1/auth/login', ['email' => $user->email, 'password' => TestCase::PASSWORD])->assertOk();
}

it('ends a session after the idle timeout', function () {
    app(SettingsService::class)->write('security', 'session_idle_minutes', 10);
    $user = $this->makeUser();
    login($this, $user);
    $this->getJson('/api/v1/me')->assertOk();
    $this->travel(11)->minutes();
    $this->getJson('/api/v1/me')->assertUnauthorized()->assertJsonPath('code', 'session_expired');
});

it('ends a session at the absolute timeout even when active', function () {
    app(SettingsService::class)->write('security', 'session_idle_minutes', 30);
    app(SettingsService::class)->write('security', 'session_absolute_minutes', 60);
    $user = $this->makeUser();
    login($this, $user);
    $this->getJson('/api/v1/me')->assertOk();
    foreach (range(1, 3) as $_) {
        $this->travel(20)->minutes();
        $this->getJson('/api/v1/me')->assertOk();
    }
    $this->travel(5)->minutes();
    $this->getJson('/api/v1/me')->assertUnauthorized()->assertJsonPath('code', 'session_expired');
});

it('lists the user\'s sessions with opaque handles and revokes others', function () {
    $user = $this->makeUser();
    login($this, $user);
    $this->getJson('/api/v1/me')->assertOk(); // persist the session row
    DB::table('sessions')->insert([
        'id' => str_repeat('a', 40), 'user_id' => $user->id, 'guard' => 'web', 'ip_address' => '10.0.0.9',
        'user_agent' => 'Other device', 'payload' => base64_encode(serialize([])), 'last_activity' => time(),
        'created_at' => now()->format('Y-m-d H:i:s.u'), 'absolute_expires_at' => now()->addDay()->format('Y-m-d H:i:s.u'),
    ]);
    $sessions = $this->getJson('/api/v1/me/sessions')->assertOk()->json('data');
    $other = collect($sessions)->firstWhere('user_agent', 'Other device');
    expect($other['handle'])->toMatch('/^[a-f0-9]{64}$/')->and(json_encode($sessions))->not->toContain(str_repeat('a', 40));

    $this->deleteJson('/api/v1/me/sessions/'.$other['handle'])->assertNoContent();
    expect(DB::table('sessions')->where('id', str_repeat('a', 40))->exists())->toBeFalse();
    $this->deleteJson('/api/v1/me/sessions/'.str_repeat('b', 64))->assertNotFound();
});

it('lets an administrator revoke all sessions of a user', function () {
    $admin = $this->superAdmin();
    $user = $this->makeUser();
    DB::table('sessions')->insert([
        'id' => str_repeat('c', 40), 'user_id' => $user->id, 'guard' => 'web', 'ip_address' => '10.0.0.9',
        'user_agent' => 'x', 'payload' => base64_encode(serialize([])), 'last_activity' => time(),
        'created_at' => now()->format('Y-m-d H:i:s.u'), 'absolute_expires_at' => now()->addDay()->format('Y-m-d H:i:s.u'),
    ]);
    $this->actingAs($admin, 'web')->deleteJson("/api/v1/users/{$user->uuid}/sessions")->assertSuccessful();
    expect(DB::table('sessions')->where('user_id', $user->id)->count())->toBe(0);
});

it('sends a reset link and resets the password under the policy', function () {
    Notification::fake();
    $user = $this->makeUser();
    $this->postJson('/api/v1/auth/forgot-password', ['email' => $user->email])->assertOk();
    // The same answer for unknown addresses: no account enumeration.
    $this->postJson('/api/v1/auth/forgot-password', ['email' => 'ghost@example.test'])->assertOk();
    $token = null;
    Notification::assertSentTo($user, ResetPassword::class, function (ResetPassword $n) use (&$token, $user) {
        $token = $n->token;
        expect($n->toMail($user)->actionUrl)->toStartWith(config('app.url').'/reset-password/');

        return true;
    });

    $this->postJson('/api/v1/auth/reset-password', ['token' => $token, 'email' => $user->email, 'password' => 'short', 'password_confirmation' => 'short'])
        ->assertUnprocessable()->assertJsonValidationErrors('password');
    $this->postJson('/api/v1/auth/reset-password', ['token' => $token, 'email' => $user->email, 'password' => 'Nw#8kPz!4rTq2Lx@', 'password_confirmation' => 'Nw#8kPz!4rTq2Lx@'])
        ->assertOk();
    expect(Hash::check('Nw#8kPz!4rTq2Lx@', $user->fresh()->password))->toBeTrue()
        ->and(PasswordHistory::query()->where('user_id', $user->id)->count())->toBe(1);
});

it('rejects common passwords and reuse of recent passwords', function () {
    // A relaxed policy still refuses passwords from the common-password list.
    foreach (['password_require_uppercase', 'password_require_symbol'] as $key) {
        app(SettingsService::class)->write('security', $key, false);
    }
    app(SettingsService::class)->write('security', 'password_min_length', 10);
    $user = $this->makeUser();
    $this->actingAs($user, 'web');
    $this->putJson('/api/v1/auth/user/password', ['current_password' => TestCase::PASSWORD, 'password' => 'charlie123', 'password_confirmation' => 'charlie123'])
        ->assertUnprocessable()->assertJsonValidationErrors('password');
    $this->putJson('/api/v1/auth/user/password', ['current_password' => TestCase::PASSWORD, 'password' => TestCase::PASSWORD, 'password_confirmation' => TestCase::PASSWORD])
        ->assertUnprocessable();
    $this->putJson('/api/v1/auth/user/password', ['current_password' => TestCase::PASSWORD, 'password' => 'Qz8#vN2!pL5$wR7m', 'password_confirmation' => 'Qz8#vN2!pL5$wR7m'])
        ->assertOk();
    $this->putJson('/api/v1/auth/user/password', ['current_password' => 'Qz8#vN2!pL5$wR7m', 'password' => TestCase::PASSWORD, 'password_confirmation' => TestCase::PASSWORD])
        ->assertUnprocessable();
});

it('blocks API use once the password has expired', function () {
    app(SettingsService::class)->write('security', 'password_expiry_days', 30);
    $user = $this->makeUser(attributes: ['password_changed_at' => now()->subDays(31)]);
    $this->actingAs($user, 'web')->getJson('/api/v1/admin/console')->assertForbidden()->assertJsonPath('code', 'password_expired');
    $this->getJson('/api/v1/me')->assertOk()->assertJsonPath('data.password_expired', true);
});

it('ends the session of a user who is suspended meanwhile', function () {
    $user = $this->makeUser();
    $this->actingAs($user, 'web');
    $user->forceFill(['status' => 'suspended'])->save();
    $this->getJson('/api/v1/me')->assertUnauthorized()->assertJsonPath('code', 'account_inactive');
});
