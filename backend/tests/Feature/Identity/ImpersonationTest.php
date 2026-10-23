<?php

declare(strict_types=1);

use App\Modules\Core\Settings\SettingsService;
use Illuminate\Support\Facades\DB;
use PragmaRX\Google2FA\Google2FA;
use Tests\TestCase;

require_once __DIR__.'/../../Engine/helpers.php';

beforeEach(fn () => $this->completeSetup());

/** Signs a Super Admin in for real (password and second factor), so the session can change hands. */
function signInAdmin($t, $admin): void
{
    $t->postJson('/api/v1/auth/login', ['email' => $admin->email, 'password' => TestCase::PASSWORD])->assertOk();
    $t->postJson('/api/v1/auth/two-factor-challenge', ['code' => (new Google2FA)->getCurrentOtp(TestCase::TOTP_SECRET)])->assertNoContent();
}

it('lets an administrator act as a user for a reason and a time, recorded as themselves, and stop', function () {
    $admin = $this->superAdmin();
    $user = $this->makeUser();
    signInAdmin($this, $admin);

    $this->postJson("/api/v1/users/{$user->uuid}/impersonate", ['reason' => 'short', 'minutes' => 30])->assertUnprocessable()->assertJsonValidationErrors('reason');
    $this->postJson("/api/v1/users/{$user->uuid}/impersonate", ['reason' => 'Reproduce the missing button', 'minutes' => 600])->assertUnprocessable()->assertJsonValidationErrors('minutes');
    $this->postJson("/api/v1/users/{$admin->uuid}/impersonate", ['reason' => 'Reproduce the missing button', 'minutes' => 30])->assertUnprocessable();
    $this->postJson("/api/v1/users/{$user->uuid}/impersonate", ['reason' => 'Reproduce the missing button', 'minutes' => 30])->assertCreated();

    // Now the session is the user's, with a banner's worth of facts about who is behind it.
    $me = $this->getJson('/api/v1/me')->assertOk()->json('data');
    expect($me['uuid'])->toBe($user->uuid);
    expect($me['impersonation']['by'])->toBe($admin->name);

    // Their account security is out of reach, and so are the blocked actions; another impersonation too.
    $this->patchJson('/api/v1/me', ['name' => 'Changed'])->assertForbidden()->assertJsonPath('code', 'impersonation_blocked');
    $this->postJson('/api/v1/me/tokens', ['name' => 'x'])->assertForbidden()->assertJsonPath('action', 'account_security');
    $this->deleteJson('/api/v1/me/sessions')->assertForbidden();

    $this->patchJson('/api/v1/me/preferences', ['theme_mode' => 'dark'])->assertOk();

    $this->postJson('/api/v1/impersonation/stop')->assertNoContent();
    expect($this->getJson('/api/v1/me')->json('data.uuid'))->toBe($admin->uuid);
    // What is audited during it names the administrator as actor, the user as subject, and the impersonation.
    $session = DB::table('impersonation_sessions')->first();
    $entry = DB::table('audit_logs')->where('impersonation_session_id', $session->id)->orderByDesc('id')->first();
    expect($entry->event)->toBe('impersonation.ended');
    expect((int) $entry->actor_user_id)->toBe($admin->id);
    expect((int) $entry->subject_user_id)->toBe($user->id);
    expect(DB::table('impersonation_sessions')->value('end_reason'))->toBe('manual');
    expect(DB::table('audit_logs')->whereIn('event', ['impersonation.started', 'impersonation.ended'])->count())->toBe(2);
});

it('never escalates: no one holding more, no Super Admin unless one, and only with the permission', function () {
    $admin = $this->makeUser(['admin']);
    $super = $this->superAdmin();
    $developer = $this->makeUser(['developer']);
    grantPermission($admin->id, 'system.impersonate_users');
    $this->actingAs($admin, 'web');
    $this->postJson("/api/v1/users/{$super->uuid}/impersonate", ['reason' => 'Reproduce the missing button', 'minutes' => 30])->assertForbidden();
    // The developer role holds "Manage code", which the admin role does not.
    $this->postJson("/api/v1/users/{$developer->uuid}/impersonate", ['reason' => 'Reproduce the missing button', 'minutes' => 30])->assertForbidden();

    $this->flushSession();
    $this->actingAs($this->makeUser(), 'web');
    $this->postJson("/api/v1/users/{$developer->uuid}/impersonate", ['reason' => 'Reproduce the missing button', 'minutes' => 30])->assertForbidden();
    expect(DB::table('impersonation_sessions')->count())->toBe(0);
});

it('ends at its time limit and refuses the actions the security settings block', function () {
    app(SettingsService::class)->write('security', 'impersonation_blocked_actions', ['change_access']);
    $admin = $this->superAdmin();
    $user = $this->makeUser();
    signInAdmin($this, $admin);
    $this->postJson("/api/v1/users/{$user->uuid}/impersonate", ['reason' => 'Reproduce the missing button', 'minutes' => 10])->assertCreated();
    $this->getJson('/api/v1/roles')->assertForbidden(); // the user's own lack of permission
    $this->postJson('/api/v1/roles', [])->assertForbidden()->assertJsonPath('action', 'change_access');

    $this->travel(11)->minutes();
    $this->getJson('/api/v1/me')->assertStatus(409)->assertJsonPath('code', 'impersonation_ended');
    expect($this->getJson('/api/v1/me')->json('data.uuid'))->toBe($admin->uuid);
    expect(DB::table('impersonation_sessions')->value('end_reason'))->toBe('expired');
});
