<?php

declare(strict_types=1);

use App\Modules\Access\Models\Permission;
use App\Modules\Access\Models\PermissionAssignment;
use App\Modules\Access\Models\Role;
use App\Modules\Audit\Models\AuditLog;
use App\Modules\Identity\Models\User;
use App\Modules\Organization\Models\Department;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Symfony\Component\Mailer\Exception\TransportException;

beforeEach(function () {
    $this->completeSetup();
    Notification::fake();
});

function roleUuid(string $key): string
{
    return Role::query()->where('key', $key)->value('uuid');
}

it('creates a user, sends a password set-up link, and audits the change', function () {
    $this->actingAs($this->superAdmin(), 'web');
    $uuid = $this->postJson('/api/v1/users', ['name' => 'Sara Ali', 'email' => 'Sara@Example.test', 'roles' => [roleUuid('user')]])
        ->assertCreated()->assertJsonPath('data.email', 'sara@example.test')->assertJsonPath('meta.password_link', 'sent')->json('data.uuid');
    $user = User::query()->where('uuid', $uuid)->firstOrFail();
    expect($user->password)->toBeNull()->and($user->roleKeys())->toBe(['user']);
    Notification::assertSentTo($user, ResetPassword::class);
    expect(AuditLog::query()->where('object_type', 'user')->where('object_id', $user->id)->where('event', 'like', '%created%')->exists())->toBeTrue();

    $this->postJson('/api/v1/users', ['name' => 'Dup', 'email' => 'sara@example.test'])->assertUnprocessable()->assertJsonValidationErrors('email');
    $this->getJson('/api/v1/users?search=sara')->assertOk()->assertJsonPath('total', 1);
});

it('creates the user even when outgoing e-mail is not configured yet', function () {
    // The setup wizard lets the administrator configure e-mail later.
    config(['mail.default' => 'lcf']);
    $this->actingAs($this->superAdmin(), 'web');
    $uuid = $this->postJson('/api/v1/users', ['name' => 'Omar Said', 'email' => 'omar@example.test'])
        ->assertCreated()->assertJsonPath('meta.password_link', 'mail_not_configured')->json('data.uuid');
    expect(User::query()->where('uuid', $uuid)->exists())->toBeTrue();
    Notification::assertNothingSent();

    $this->postJson("/api/v1/users/{$uuid}/password-link")
        ->assertUnprocessable()->assertJsonPath('message', __('ui.users.mail_not_configured'));
});

it('creates the user and reports the failure when the link cannot be e-mailed', function () {
    $broker = Mockery::mock();
    $broker->shouldReceive('sendResetLink')->andThrow(new TransportException('Connection refused'));
    Password::shouldReceive('broker')->andReturn($broker);
    $this->actingAs($this->superAdmin(), 'web');

    $uuid = $this->postJson('/api/v1/users', ['name' => 'Huda Nasser', 'email' => 'huda@example.test'])
        ->assertCreated()->assertJsonPath('meta.password_link', 'failed')->json('data.uuid');
    expect(User::query()->where('uuid', $uuid)->exists())->toBeTrue()
        ->and(DB::table('error_logs')->count())->toBeGreaterThan(0);

    $this->postJson("/api/v1/users/{$uuid}/password-link")
        ->assertUnprocessable()->assertJsonPath('message', __('ui.users.mail_failed'));
});

it('never exposes password hashes or 2FA secrets', function () {
    $this->actingAs($this->superAdmin(), 'web');
    $other = $this->makeUser(['admin']);
    $body = $this->getJson("/api/v1/users/{$other->uuid}")->assertOk()->getContent();
    expect($body)->not->toContain('password')->not->toContain('two_factor_secret')->not->toContain('recovery');
});

it('suspends a user, ends their sessions, and unlocks them', function () {
    $this->actingAs($this->superAdmin(), 'web');
    $user = $this->makeUser(attributes: ['locked_until' => now()->addHour()]);
    DB::table('sessions')->insert(['id' => str_repeat('s', 40), 'user_id' => $user->id, 'guard' => 'web', 'payload' => '', 'last_activity' => time(),
        'created_at' => now()->format('Y-m-d H:i:s.u'), 'absolute_expires_at' => now()->addDay()->format('Y-m-d H:i:s.u')]);
    $this->postJson("/api/v1/users/{$user->uuid}/status", ['status' => 'suspended'])->assertOk()->assertJsonPath('data.status', 'suspended');
    expect(DB::table('sessions')->where('user_id', $user->id)->exists())->toBeFalse();
    $this->postJson("/api/v1/users/{$user->uuid}/unlock")->assertSuccessful();
    expect($user->fresh()->isLocked())->toBeFalse();
});

it('prevents an administrator from managing a more privileged account', function () {
    $super = $this->superAdmin();
    $admin = $this->makeUser(['admin']);
    $this->actingAs($admin, 'web');
    // The admin role lacks manage_code etc., so the super admin is out of reach.
    $this->patchJson("/api/v1/users/{$super->uuid}", ['email' => 'attacker@example.test'])->assertForbidden();
    $this->postJson("/api/v1/users/{$super->uuid}/password-link")->assertForbidden();
    $this->postJson("/api/v1/users/{$super->uuid}/reset-2fa")->assertForbidden();
    $this->postJson("/api/v1/users/{$super->uuid}/status", ['status' => 'disabled'])->assertForbidden();
    $this->deleteJson("/api/v1/users/{$super->uuid}")->assertForbidden();
    expect($super->fresh()->email)->not->toBe('attacker@example.test');
});

it('prevents giving a role with permissions the administrator does not hold', function () {
    $admin = $this->makeUser(['admin']);
    $this->actingAs($admin, 'web');
    $target = $this->makeUser();
    $this->patchJson("/api/v1/users/{$target->uuid}", ['roles' => [roleUuid('super_admin')]])->assertForbidden();
    // Nobody changes their own roles.
    $this->patchJson("/api/v1/users/{$admin->uuid}", ['roles' => [roleUuid('admin'), roleUuid('user')]])->assertForbidden();
    expect($target->fresh()->roleKeys())->toBe(['user']);
});

it('requires step-up confirmation to give an administrative role', function () {
    $this->actingAs($this->superAdmin(), 'web');
    $target = $this->makeUser();
    $this->patchJson("/api/v1/users/{$target->uuid}", ['roles' => [roleUuid('admin')]])->assertUnprocessable()->assertJsonValidationErrors('confirmation_code');
    Cache::flush();
    $this->patchJson("/api/v1/users/{$target->uuid}", ['roles' => [roleUuid('admin')], 'confirmation_code' => $this->totp()])->assertOk();
    expect($target->fresh()->roleKeys())->toBe(['admin']);
});

it('builds, moves and archives the department tree', function () {
    $this->actingAs($this->superAdmin(), 'web');
    $hq = $this->postJson('/api/v1/departments', ['code' => 'HQ', 'name' => ['en' => 'Headquarters', 'ar' => 'المقر الرئيسي']])->assertCreated()->json('data.uuid');
    $fin = $this->postJson('/api/v1/departments', ['code' => 'FIN', 'name' => ['en' => 'Finance'], 'parent' => $hq])->assertCreated()->json('data.uuid');
    $ap = $this->postJson('/api/v1/departments', ['code' => 'AP', 'name' => ['en' => 'Payables'], 'parent' => $fin])->assertCreated()->json('data.uuid');

    $tree = $this->getJson('/api/v1/departments/tree')->assertOk()->json('data');
    expect($tree)->toHaveCount(1)->and($tree[0]['children'][0]['children'][0]['code'])->toBe('AP');
    $this->withHeader('X-Locale', 'ar')->getJson('/api/v1/departments/tree')->assertJsonPath('data.0.name', 'المقر الرئيسي');

    // No cycles: a department cannot move under its own descendant.
    $this->patchJson("/api/v1/departments/{$hq}", ['parent' => $ap])->assertUnprocessable();
    // Moving re-parents the whole subtree.
    $this->patchJson("/api/v1/departments/{$ap}", ['parent' => $hq])->assertOk();
    $apId = Department::query()->where('uuid', $ap)->value('id');
    expect(Department::query()->find($apId)->depth)->toBe(1);
    expect(DB::table('department_closure')->where('descendant_id', $apId)->count())->toBe(2);

    // A department with sub-departments cannot be archived.
    $this->deleteJson("/api/v1/departments/{$hq}")->assertUnprocessable();
    $this->deleteJson("/api/v1/departments/{$ap}")->assertSuccessful();
});

it('applies department grants to members and checks them on assignment', function () {
    $super = $this->superAdmin();
    $this->actingAs($super, 'web');
    $dept = $this->postJson('/api/v1/departments', ['code' => 'OPS', 'name' => ['en' => 'Operations']])->json('data.uuid');
    $deptId = Department::query()->where('uuid', $dept)->value('id');
    PermissionAssignment::query()->create(['permission_id' => Permission::query()->where('key', 'system.manage_code')->value('id'),
        'subject_type' => 'department', 'subject_id' => $deptId, 'effect' => 'allow']);

    $admin = $this->makeUser(['admin']);
    $target = $this->makeUser();
    $this->flushSession();
    $this->actingAs($admin, 'web');
    // The department allows manage_code, which the admin does not hold.
    $this->patchJson("/api/v1/users/{$target->uuid}", ['department' => $dept])->assertForbidden();
});

it('forbids user administration without the permission', function () {
    $this->actingAs($this->makeUser(), 'web');
    $this->getJson('/api/v1/users')->assertForbidden();
    $this->getJson('/api/v1/departments/tree')->assertForbidden();
    $this->postJson('/api/v1/users', ['name' => 'X', 'email' => 'x@example.test'])->assertForbidden();
});
