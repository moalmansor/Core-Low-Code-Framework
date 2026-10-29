<?php

declare(strict_types=1);

use App\Modules\Core\Settings\SettingsService;
use App\Modules\Identity\Policies\AccessPolicies;
use App\Modules\Identity\Policies\TrustedDevices;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

require_once __DIR__.'/../../Engine/helpers.php';

beforeEach(fn () => $this->completeSetup());

function apPolicyDoc(string $key, array $roles, array $extra = []): array
{
    return array_replace([
        'key' => $key, 'i18n' => ['name' => ['en' => ucfirst($key), 'ar' => $key]], 'ip_allowlist' => [], 'time_windows' => [],
        'max_concurrent_sessions' => null, 'session_idle_minutes' => 30, 'session_absolute_minutes' => 720,
        'require_trusted_device' => false, 'require_2fa' => false, 'is_admin_policy' => false, 'roles' => $roles,
    ], $extra);
}

function apRoleUuid(string $key): string
{
    return strtolower((string) DB::table('roles')->where('key', $key)->value('uuid'));
}

/** Creates a policy directly, leaving no one signed in. */
function apMakePolicy($t, $admin, array $doc): array
{
    $policies = app(AccessPolicies::class);

    return $policies->present($policies->save(null, $policies->check($doc, null), $admin, request()));
}

it('manages policies with validation, optimistic concurrency, and refuses one that would shut out the administrator saving it', function () {
    $this->actingAs($this->superAdmin(), 'web');
    $this->postJson('/api/v1/access-policies', ['policy' => apPolicyDoc('Bad Key', [], ['ip_allowlist' => ['10.0.0.0/33', 'nope'], 'session_idle_minutes' => 1,
        'time_windows' => [['days' => [8], 'from' => '18:00', 'to' => '18:00', 'timezone' => 'Mars/Base']]])])
        ->assertUnprocessable()->assertJsonValidationErrors(['key', 'ip_allowlist.0', 'ip_allowlist.1', 'session_idle_minutes', 'time_windows.0.days', 'time_windows.0.from', 'time_windows.0.timezone']);

    // Requests in tests come from 127.0.0.1: a policy on the administrator's own role that excludes it is refused.
    $this->postJson('/api/v1/access-policies', ['policy' => apPolicyDoc('office', [apRoleUuid('super_admin')], ['ip_allowlist' => ['10.0.0.0/8']])])
        ->assertUnprocessable()->assertJsonValidationErrors('policy');
    expect(DB::table('access_policies')->count())->toBe(0);

    $made = $this->postJson('/api/v1/access-policies', ['policy' => apPolicyDoc('office', [apRoleUuid('user')], ['ip_allowlist' => ['10.0.0.0/8', '2001:db8::/32']])])->assertCreated()->json('data');
    expect($made['roles'])->toBe([apRoleUuid('user')]);
    expect(DB::table('roles')->where('key', 'user')->value('access_policy_id'))->not->toBeNull();
    $doc = apPolicyDoc('office', [], ['ip_allowlist' => ['10.0.0.0/8']]);
    $this->putJson("/api/v1/access-policies/{$made['uuid']}", ['policy' => $doc, 'base_hash' => str_repeat('0', 64)])->assertStatus(409);
    $this->putJson("/api/v1/access-policies/{$made['uuid']}", ['policy' => $doc, 'base_hash' => $made['hash']])->assertOk()->assertJsonPath('data.roles', []);
    expect(DB::table('roles')->where('key', 'user')->value('access_policy_id'))->toBeNull();
    $this->deleteJson("/api/v1/access-policies/{$made['uuid']}")->assertNoContent();
    expect(DB::table('audit_logs')->where('event', 'like', 'access_policy.%')->count())->toBe(3);

    // Only with "Manage access policies".
    $this->flushSession();
    $this->actingAs($this->makeUser(), 'web')->getJson('/api/v1/access-policies')->assertForbidden();
});

it('refuses a policy that would leave no administrator able to sign in, whoever saves it', function () {
    $super = $this->superAdmin();
    // Someone who manages access policies but holds no administrative role, so no policy check touches them.
    $officer = $this->makeUser();
    grantPermission($officer->id, 'system.manage_access_policies');
    $this->actingAs($officer, 'web');

    // An administrative policy that keeps every Super Admin to one network: nobody can show an administrator still gets in.
    $this->postJson('/api/v1/access-policies', ['policy' => apPolicyDoc('admins_office', [], ['is_admin_policy' => true, 'ip_allowlist' => ['10.0.0.0/8']])])
        ->assertUnprocessable()->assertJsonPath('errors.policy.0', __('identity.locks_out_administrators'));
    // The same through the Super Admin role, and the same with hours instead of addresses.
    $this->postJson('/api/v1/access-policies', ['policy' => apPolicyDoc('supers', [apRoleUuid('super_admin')], ['ip_allowlist' => ['127.0.0.0/8']])])
        ->assertUnprocessable()->assertJsonPath('errors.policy.0', __('identity.locks_out_administrators'));
    $this->postJson('/api/v1/access-policies', ['policy' => apPolicyDoc('supers', [apRoleUuid('super_admin')], ['time_windows' => [['days' => [1, 2, 3, 4, 5, 6, 7], 'from' => '00:00', 'to' => '23:59', 'timezone' => 'UTC']]])])
        ->assertUnprocessable();
    expect(DB::table('access_policies')->count())->toBe(0);
    // Policies that leave an administrator free are fine.
    $this->postJson('/api/v1/access-policies', ['policy' => apPolicyDoc('developers', [apRoleUuid('developer')], ['ip_allowlist' => ['10.0.0.0/8']])])->assertCreated();

    // A Super Admin it governs, saving it from inside its limits, shows an administrator can still sign in.
    $this->flushSession();
    $this->actingAs($super, 'web');
    $this->postJson('/api/v1/access-policies', ['policy' => apPolicyDoc('supers', [apRoleUuid('super_admin')], ['ip_allowlist' => ['127.0.0.0/8']])])->assertCreated();
});

it('allows sign-in only from the allowed networks and ends a session that moves outside them', function () {
    apMakePolicy($this, $this->superAdmin(), apPolicyDoc('office', [apRoleUuid('user')], ['ip_allowlist' => ['10.0.0.0/8']]));
    $user = $this->makeUser();
    $this->postJson('/api/v1/auth/login', ['email' => $user->email, 'password' => TestCase::PASSWORD])
        ->assertUnprocessable()->assertJsonPath('errors.email.0', __('identity.denied_ip'));
    $this->assertGuest('web');

    $this->withServerVariables(['REMOTE_ADDR' => '10.1.2.3'])->postJson('/api/v1/auth/login', ['email' => $user->email, 'password' => TestCase::PASSWORD])->assertOk();
    $this->withServerVariables(['REMOTE_ADDR' => '10.1.2.3'])->getJson('/api/v1/me')->assertOk();
    $this->withServerVariables(['REMOTE_ADDR' => '192.168.1.5'])->getJson('/api/v1/me')
        ->assertUnauthorized()->assertJsonPath('code', 'access_policy')->assertJsonPath('reason', 'ip');
    expect(DB::table('audit_logs')->where('event', 'auth.policy_denied')->count())->toBe(1);
});

it('allows work only within the time windows, including windows past midnight', function () {
    $windows = [['days' => [1, 2, 3, 4, 5], 'from' => '08:00', 'to' => '17:00', 'timezone' => 'Asia/Riyadh']];
    // Monday 10:00 in Riyadh is 07:00 UTC; Saturday is outside.
    expect(AccessPolicies::within($windows, Carbon::parse('2026-10-19 07:00:00', 'UTC')))->toBeTrue();
    expect(AccessPolicies::within($windows, Carbon::parse('2026-10-24 07:00:00', 'UTC')))->toBeFalse();
    $night = [['days' => [5], 'from' => '22:00', 'to' => '06:00', 'timezone' => 'UTC']];
    expect(AccessPolicies::within($night, Carbon::parse('2026-10-24 03:00:00', 'UTC')))->toBeTrue(); // Saturday early, after Friday's start
    expect(AccessPolicies::within($night, Carbon::parse('2026-10-23 03:00:00', 'UTC')))->toBeFalse(); // Friday early belongs to Thursday

    Carbon::setTestNow(Carbon::parse('2026-10-24 07:00:00', 'UTC'));
    apMakePolicy($this, $this->superAdmin(), apPolicyDoc('weekdays', [apRoleUuid('user')], ['time_windows' => $windows]));
    $user = $this->makeUser();
    $this->postJson('/api/v1/auth/login', ['email' => $user->email, 'password' => TestCase::PASSWORD])
        ->assertUnprocessable()->assertJsonPath('errors.email.0', __('identity.denied_time'));
    Carbon::setTestNow(Carbon::parse('2026-10-19 07:00:00', 'UTC'));
    $this->postJson('/api/v1/auth/login', ['email' => $user->email, 'password' => TestCase::PASSWORD])->assertOk();
    Carbon::setTestNow();
});

it('tightens session lifetimes, limits concurrent sessions and requires a second factor', function () {
    app(SettingsService::class)->write('security', 'session_idle_minutes', 30);
    // Made before the policy, so no second factor is set up for them yet.
    $user = $this->makeUser(twoFactor: false);
    apMakePolicy($this, $this->superAdmin(), apPolicyDoc('strict', [apRoleUuid('user')], ['max_concurrent_sessions' => 2, 'session_idle_minutes' => 10, 'session_absolute_minutes' => 60, 'require_2fa' => true]));
    foreach (['a', 'b', 'c'] as $i => $c) {
        DB::table('sessions')->insert([
            'id' => str_repeat($c, 40), 'user_id' => $user->id, 'guard' => 'web', 'ip_address' => '10.0.0.9', 'user_agent' => 'Other',
            'payload' => base64_encode(serialize([])), 'last_activity' => time() - 100 * $i,
            'created_at' => now()->format('Y-m-d H:i:s.u'), 'absolute_expires_at' => now()->addDay()->format('Y-m-d H:i:s.u'),
        ]);
    }
    $this->postJson('/api/v1/auth/login', ['email' => $user->email, 'password' => TestCase::PASSWORD])->assertOk()->assertJsonPath('two_factor', false);
    // The newest other session is kept, the older ones end: with this one, two.
    $left = DB::table('sessions')->where('user_id', $user->id)->pluck('id')->all();
    expect($left)->toHaveCount(2)->toContain(str_repeat('a', 40))->not->toContain(str_repeat('b', 40))->not->toContain(str_repeat('c', 40));
    expect(DB::table('audit_logs')->where('event', 'auth.sessions_limited')->count())->toBe(1);

    // The policy's second factor applies like a role's: the profile works, the rest asks for enrolment.
    $this->getJson('/api/v1/me')->assertOk()->assertJsonPath('data.two_factor.required', true);
    $this->getJson('/api/v1/forms')->assertForbidden()->assertJsonPath('code', 'two_factor_enrollment_required');
    $this->travel(11)->minutes();
    $this->getJson('/api/v1/me')->assertUnauthorized()->assertJsonPath('code', 'session_expired');
});

it('trusts a device once a second factor is verified on it, and only that device', function () {
    $admin = $this->superAdmin();
    apMakePolicy($this, $admin, apPolicyDoc('admins', [], ['is_admin_policy' => true, 'require_trusted_device' => true]));

    $this->postJson('/api/v1/auth/login', ['email' => $admin->email, 'password' => TestCase::PASSWORD])->assertOk()->assertJsonPath('two_factor', true);
    $challenge = $this->postJson('/api/v1/auth/two-factor-challenge', ['code' => $this->totp()])->assertNoContent();
    $plain = $challenge->getCookie(TrustedDevices::COOKIE)?->getValue();
    expect($plain)->toBeString();
    // Only an HMAC of the cookie is stored.
    expect(DB::table('trusted_devices')->where('user_id', $admin->id)->value('device_hash'))->toBe(TrustedDevices::hash((string) $plain));

    // JSON requests in tests send cookies only "with credentials", as the browser does.
    $this->withCredentials()->withCookie(TrustedDevices::COOKIE, (string) $plain)->getJson('/api/v1/me')->assertOk();
    expect($this->withCookie(TrustedDevices::COOKIE, (string) $plain)->getJson('/api/v1/me/devices')->json('data.0.current'))->toBeTrue();

    // Another browser without the device cookie is turned away.
    $this->withCookie(TrustedDevices::COOKIE, str_repeat('x', 64))->getJson('/api/v1/me')
        ->assertUnauthorized()->assertJsonPath('code', 'access_policy')->assertJsonPath('reason', 'device');
});
