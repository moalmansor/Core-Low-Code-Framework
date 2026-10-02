<?php

declare(strict_types=1);

use App\Modules\Access\Models\PermissionAssignment;
use App\Modules\Access\Models\Role;
use App\Modules\Audit\Models\AuditLog;
use Illuminate\Support\Facades\Cache;

beforeEach(function () {
    $this->completeSetup();
    $this->admin = $this->superAdmin();
    $this->actingAs($this->admin, 'web');
});

/** A TOTP code that has not been used yet in this test (Fortify rejects replays). */
function freshCode(): string
{
    Cache::flush();

    return test()->totp();
}

it('lists roles and the permission catalog with localized labels', function () {
    $this->getJson('/api/v1/roles')->assertOk()->assertJsonFragment(['key' => 'super_admin']);
    $this->getJson('/api/v1/permissions')->assertJsonFragment(['key' => 'system.manage_users', 'label' => 'Manage users']);
    $this->withHeader('X-Locale', 'ar')->getJson('/api/v1/permissions')->assertOk()
        ->assertJsonFragment(['key' => 'system.manage_users', 'label' => 'إدارة المستخدمين']);
});

it('forbids users without the permission on every access endpoint', function () {
    $user = $this->makeUser();
    $this->actingAs($user, 'web');
    $role = Role::query()->where('key', 'user')->first();
    $this->getJson('/api/v1/roles')->assertForbidden();
    $this->getJson('/api/v1/permissions')->assertForbidden();
    $this->putJson('/api/v1/permission-assignments', ['subject_type' => 'user', 'subject' => $user->uuid, 'grants' => [['permission' => 'system.manage_permissions', 'effect' => 'allow']]])->assertForbidden();
    $this->getJson("/api/v1/access/view-as/{$user->uuid}")->assertForbidden();
    $this->postJson("/api/v1/roles/{$role->uuid}/copy-permissions", ['from_role' => $role->uuid])->assertForbidden();
    expect(PermissionAssignment::query()->where('subject_type', 'user')->exists())->toBeFalse();
});

it('creates, updates and deletes a custom role, and protects core roles', function () {
    $uuid = $this->postJson('/api/v1/roles', ['key' => 'auditor', 'name' => ['en' => 'Auditor', 'ar' => 'مدقق'], 'requires_2fa' => true])
        ->assertCreated()->json('data.uuid');
    $this->patchJson("/api/v1/roles/{$uuid}", ['name' => ['en' => 'Internal auditor']])->assertOk();
    $this->getJson("/api/v1/roles/{$uuid}")->assertJsonPath('data.names.en', 'Internal auditor');
    $this->deleteJson("/api/v1/roles/{$uuid}")->assertSuccessful();

    $core = Role::query()->where('key', 'admin')->first();
    $this->deleteJson("/api/v1/roles/{$core->uuid}")->assertUnprocessable();
    $this->postJson('/api/v1/roles', ['key' => 'Bad Key!', 'name' => ['en' => 'x']])->assertUnprocessable();
});

it('sets grants on a subject and audits the change', function () {
    $user = $this->makeUser();
    $this->putJson('/api/v1/permission-assignments', [
        'subject_type' => 'user', 'subject' => $user->uuid,
        'grants' => [['permission' => 'system.manage_reports', 'effect' => 'allow'], ['permission' => 'system.view_errors', 'effect' => 'deny']],
    ])->assertOk()->assertJsonCount(2, 'data');
    expect(AuditLog::query()->where('event', 'access.grants_changed')->exists())->toBeTrue();

    $this->getJson("/api/v1/access/explain?user={$user->uuid}&permission=system.manage_reports")
        ->assertOk()->assertJsonPath('data.granted', true)->assertJsonPath('data.decided_by', 'user_allow');

    // effect null removes the grant.
    $this->putJson('/api/v1/permission-assignments', ['subject_type' => 'user', 'subject' => $user->uuid,
        'grants' => [['permission' => 'system.manage_reports', 'effect' => null]]])->assertOk()->assertJsonCount(1, 'data');
});

it('requires step-up confirmation for dangerous grants and hard denies', function () {
    $user = $this->makeUser();
    $payload = ['subject_type' => 'user', 'subject' => $user->uuid, 'grants' => [['permission' => 'system.manage_settings', 'effect' => 'allow']]];
    $this->putJson('/api/v1/permission-assignments', $payload)->assertUnprocessable()->assertJsonValidationErrors('confirmation_code');
    $this->putJson('/api/v1/permission-assignments', $payload + ['confirmation_code' => '123456'])->assertUnprocessable();
    $this->putJson('/api/v1/permission-assignments', $payload + ['confirmation_code' => freshCode()])->assertOk();

    $hard = ['subject_type' => 'user', 'subject' => $user->uuid, 'grants' => [['permission' => 'system.manage_reports', 'effect' => 'hard_deny']]];
    $this->putJson('/api/v1/permission-assignments', $hard)->assertUnprocessable();
    $this->putJson('/api/v1/permission-assignments', $hard + ['confirmation_code' => 'recovery-code-1'])->assertOk();
});

it('refuses a change that would leave no super admin able to manage permissions', function () {
    $role = Role::query()->where('key', 'super_admin')->first();
    $this->putJson('/api/v1/permission-assignments', [
        'subject_type' => 'role', 'subject' => $role->uuid,
        'grants' => [['permission' => 'system.manage_permissions', 'effect' => 'deny']],
    ])->assertUnprocessable()->assertJsonValidationErrors('access');
    expect(PermissionAssignment::query()->where('subject_type', 'role')->where('subject_id', $role->id)->where('effect', 'deny')->exists())->toBeFalse();

    // Removing the super admin role from the only super admin is refused as well.
    $this->deleteJson("/api/v1/roles/{$role->uuid}")->assertUnprocessable();
    // A super admin cannot suspend themselves, and less privileged admins cannot reach them.
    $this->postJson("/api/v1/users/{$this->admin->uuid}/status", ['status' => 'suspended'])->assertUnprocessable();
    $other = $this->makeUser(['admin']);
    $this->flushSession();
    $this->actingAs($other, 'web');
    $this->postJson("/api/v1/users/{$this->admin->uuid}/status", ['status' => 'suspended'])->assertForbidden();
    expect($this->admin->fresh()->status)->toBe('active');
});

it('shows a user\'s effective permissions with the deciding tier ("view as user")', function () {
    $user = $this->makeUser(['admin']);
    $response = $this->getJson("/api/v1/access/view-as/{$user->uuid}")->assertOk();
    $rows = collect($response->json('data.permissions'))->keyBy('key');
    expect($rows['system.manage_users']['granted'])->toBeTrue()
        ->and($rows['system.manage_users']['decided_by'])->toBe('role_allow')
        ->and($rows['system.manage_code']['granted'])->toBeFalse()
        ->and($rows['system.manage_code']['decided_by'])->toBe('default_deny');
});

it('exports, imports and copies permission sets', function () {
    $source = Role::query()->where('key', 'developer')->first();
    $set = $this->getJson("/api/v1/access/export?subject_type=role&subject={$source->uuid}")->assertOk()->json('data');
    expect($set['format'])->toBe('lcf.permission-set/v1');

    $target = Role::query()->create(['key' => 'builder', 'audience' => 'internal']);
    // The developer set contains a dangerous permission (manage_code): step-up required.
    $this->postJson('/api/v1/access/import', ['subject_type' => 'role', 'subject' => $target->uuid, 'set' => $set])->assertUnprocessable();
    $this->postJson('/api/v1/access/import', ['subject_type' => 'role', 'subject' => $target->uuid, 'set' => $set, 'confirmation_code' => freshCode()])
        ->assertOk()->assertJsonPath('data.imported', count($set['grants']));
    expect(PermissionAssignment::query()->where('subject_type', 'role')->where('subject_id', $target->id)->count())->toBe(count($set['grants']));

    $copy = Role::query()->create(['key' => 'copycat', 'audience' => 'internal']);
    $this->postJson("/api/v1/roles/{$copy->uuid}/copy-permissions", ['from_role' => Role::query()->where('key', 'user')->value('uuid')])->assertOk();
    expect(PermissionAssignment::query()->where('subject_type', 'role')->where('subject_id', $copy->id)->count())->toBe(1);
});
