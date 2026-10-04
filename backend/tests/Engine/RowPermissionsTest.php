<?php

declare(strict_types=1);

use App\Modules\Access\AccessCache;
use App\Modules\Access\Models\PermissionAssignment;
use Illuminate\Support\Facades\DB;

require_once __DIR__.'/helpers.php';

beforeEach(function () {
    $this->completeSetup();
    $this->admin = $this->superAdmin();
    $this->flushSession();
    $this->actingAs($this->admin, 'web');
});

it('enforces repeater row permissions and gives the client its user context', function () {
    $userRole = DB::table('roles')->where('key', 'user')->value('uuid');
    $adminRole = DB::table('roles')->where('key', 'super_admin')->value('uuid');
    [, $form] = createForm($this, 'expenses');
    // Only the "user" role may add rows; only super admins may remove them.
    $lines = groupDoc('lines', 'repeater', null, ['repeater' => ['rowPermissions' => ['add' => [$userRole], 'remove' => [$adminRole]]]]);
    saveDraft($this, $form, [$lines], [fieldDoc('subject', 'text'), fieldDoc('amount', 'decimal', $lines['uuid'])])->assertOk();
    expect(publish($this, $form)['status'])->toBe('applied');

    $definition = $this->getJson("/api/v1/r/{$form}/definition?mode=create")->assertOk()->json('data.user');
    expect($definition['roles'])->toContain('super_admin')->and($definition['role_uuids'])->toContain(strtolower((string) $adminRole));

    $this->postJson("/api/v1/r/{$form}", ['values' => ['subject' => 'Trip', 'lines' => [['amount' => '10']]]])
        ->assertStatus(422)->assertJsonPath('errors.lines.0', __('records.rows.add_forbidden'));
    $created = $this->postJson("/api/v1/r/{$form}", ['values' => ['subject' => 'Trip', 'lines' => []]])->assertCreated()->json('data');

    // Rows written by someone allowed to add them may be removed by a super admin.
    $id = DB::table('f_expenses')->where('uuid', $created['uuid'])->value('id');
    DB::table('f_expenses__lines')->insert(['uuid' => uid(), 'organization_id' => 1, 'parent_id' => $id, 'sort_order' => 0, 'amount' => '5', 'created_at' => now(), 'updated_at' => now()]);
    $this->patchJson("/api/v1/r/{$form}/{$created['uuid']}", ['values' => ['lines' => []], 'row_version' => 1])->assertOk();
    expect(DB::table('f_expenses__lines')->where('parent_id', $id)->count())->toBe(0);
});

it('lets form builders list roles for previews and row permissions', function () {
    $user = $this->makeUser();
    $permission = DB::table('permissions')->where('key', 'system.manage_forms')->value('id');
    PermissionAssignment::query()->create(['permission_id' => $permission, 'subject_type' => 'user', 'subject_id' => $user->id, 'effect' => 'allow', 'include_descendants' => false]);
    app(AccessCache::class)->bump();
    $this->flushSession();
    $this->actingAs($user, 'web');
    $this->getJson('/api/v1/role-options')->assertOk()->assertJsonFragment(['key' => 'user']);
});
