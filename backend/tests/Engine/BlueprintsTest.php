<?php

declare(strict_types=1);

use App\Modules\Access\Models\PermissionAssignment;
use App\Modules\Blueprints\BlueprintService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

require_once __DIR__.'/helpers.php';

beforeEach(function () {
    $this->completeSetup();
    $this->admin = $this->superAdmin();
    $this->flushSession();
    $this->actingAs($this->admin, 'web');
});

/** @return array<string, mixed> the draft document of a form */
function draftOf(TestCase $t, string $form): array
{
    return $t->getJson("/api/v1/forms/{$form}/draft")->assertOk()->json('data');
}

function putDraft(TestCase $t, string $form, array $doc): void
{
    $t->putJson("/api/v1/forms/{$form}/draft", ['document' => $doc, 'draft_updated_at' => draftOf($t, $form)['draft_updated_at']])->assertOk();
}

it('saves a form as a blueprint, instantiates it with permissions, and propagates later versions with a three-way merge', function () {
    [$app, $source] = createForm($this, 'leave_request');
    $subject = fieldDoc('subject', 'text', null, ['validation' => ['required' => true]]);
    $priority = fieldDoc('priority', 'select', null, ['options' => ['source' => 'static', 'static' => staticOptions(['low', 'high'])]]);
    $secret = fieldDoc('secret', 'text');
    saveDraft($this, $source, [], [$subject, $priority, $secret])->assertOk();

    // Access: "secret" hidden from the user role, and the user role may view and create.
    $role = DB::table('roles')->where('key', 'user')->first();
    $this->putJson("/api/v1/forms/{$source}/access-rules", ['changes' => [
        ['target' => ['type' => 'field', 'uuid' => $secret['uuid']], 'subject' => ['type' => 'role', 'uuid' => $role->uuid], 'mode' => null, 'access' => 'hidden'],
    ]])->assertOk();
    $viewPermission = DB::table('permissions')->where('key', "form.{$source}.view")->value('id');
    PermissionAssignment::query()->create(['permission_id' => $viewPermission, 'subject_type' => 'role', 'subject_id' => $role->id, 'effect' => 'allow', 'include_descendants' => false]);

    $bp = $this->postJson('/api/v1/blueprints', [
        'source' => $source, 'include_mode' => 'structure_permissions', 'name' => ['en' => 'Leave request', 'ar' => 'طلب إجازة'],
        'category' => 'requests', 'tags' => ['hr'],
    ])->assertCreated()->json('data.uuid');
    $this->getJson('/api/v1/blueprints?search=leave')->assertOk()->assertJsonPath('data.0.uuid', $bp)->assertJsonPath('data.0.version', 1);

    // Unchanged source: no new version.
    $this->postJson("/api/v1/blueprints/{$bp}/versions", ['include_mode' => 'structure_permissions'])->assertStatus(422);

    // Instantiate: new draft form, derived uuids, same keys, access carried over.
    $made = $this->postJson("/api/v1/blueprints/{$bp}/instantiate", [
        'application' => $app, 'key' => 'leave_copy', 'name' => ['en' => 'Leave copy'], 'include_mode' => 'structure_permissions',
    ])->assertCreated()->json('data');
    expect($made['skipped'])->toBe([]);
    $copy = $made['uuid'];
    $doc = draftOf($this, $copy)['document'];
    expect(array_column($doc['fields'], 'key'))->toBe(['subject', 'priority', 'secret'])
        ->and($doc['fields'][0]['uuid'])->toBe(BlueprintService::derive($copy, $subject['uuid']))
        ->and($doc['form']['i18n']['name']['en'])->toBe('Leave copy');
    $copyId = DB::table('forms')->where('uuid', $copy)->value('id');
    expect(DB::table('field_access_rules')->where('form_id', $copyId)->where('access', 'hidden')->count())->toBe(1)
        ->and(DB::table('permission_assignments')->join('permissions', 'permissions.id', '=', 'permission_id')->where('permissions.key', "form.{$copy}.view")->where('subject_id', $role->id)->exists())->toBeTrue()
        ->and(DB::table('forms')->where('id', $copyId)->value('blueprint_instance_id'))->not->toBeNull();
    expect(publish($this, $copy)['status'])->toBe('applied');

    // The instance changes "priority" locally; the blueprint changes "subject" and "priority" and adds "notes".
    $local = draftOf($this, $copy)['document'];
    $local['fields'][1]['i18n']['label']['en'] = 'Urgency';
    putDraft($this, $copy, $local);
    $src = draftOf($this, $source)['document'];
    $src['fields'][0]['i18n']['label']['en'] = 'Title';
    $src['fields'][1]['i18n']['label']['en'] = 'Importance';
    $src['fields'][] = fieldDoc('notes', 'textarea', null, ['order' => 5]);
    putDraft($this, $source, $src);
    $this->postJson("/api/v1/blueprints/{$bp}/versions", ['include_mode' => 'structure_permissions', 'changelog' => 'Notes'])->assertCreated()->assertJsonPath('data.version', 2);

    $preview = $this->getJson("/api/v1/blueprints/{$bp}/propagation-preview")->assertOk()->json('data');
    expect($preview)->toHaveCount(1)->and($preview[0]['from_version'])->toBe(1);
    $byLabel = collect($preview[0]['changes'])->keyBy('label');
    expect($byLabel['subject']['status'])->toBe('apply')
        ->and($byLabel['notes']['action'])->toBe('add')
        ->and($byLabel['notes']['status'])->toBe('apply')
        ->and($byLabel['priority']['status'])->toBe('conflict')
        ->and($byLabel['priority']['reason'])->toBe('modified_locally');

    $result = $this->postJson("/api/v1/blueprints/{$bp}/propagate", [])->assertOk()->json('data');
    expect($result[0]['status'])->toBe('applied')->and($result[0]['applied'])->toBe(2)->and($result[0]['conflicts'])->toHaveCount(1);
    $merged = collect(draftOf($this, $copy)['document']['fields'])->keyBy('key');
    expect($merged['subject']['i18n']['label']['en'])->toBe('Title')
        ->and($merged['priority']['i18n']['label']['en'])->toBe('Urgency')
        ->and($merged->has('notes'))->toBeTrue();
    expect($this->getJson("/api/v1/blueprints/{$bp}/propagation-preview")->json('data'))->toBe([]);

    // Export, then import: the same blueprint gains nothing; a modified file is refused.
    $export = $this->getJson("/api/v1/blueprints/{$bp}/export")->assertOk()->json();
    expect($export['versions'])->toHaveCount(2);
    $file = fn (array $payload) => UploadedFile::fake()->createWithContent('bp.blueprint.json', (string) json_encode($payload));
    $this->post('/api/v1/blueprints/import', ['file' => $file($export)], ['Accept' => 'application/json'])->assertCreated()->assertJsonPath('data.versions_added', 0);
    $tampered = $export;
    $tampered['versions'][0]['content']['document']['fields'][0]['key'] = 'hacked';
    $this->post('/api/v1/blueprints/import', ['file' => $file($tampered)], ['Accept' => 'application/json'])->assertStatus(422);

    // A blueprint whose related form does not exist here cannot be instantiated.
    $foreign = $export;
    $foreign['blueprint']['uuid'] = uid();
    $foreign['versions'] = [$export['versions'][1]];
    $foreign['versions'][0]['content']['dependencies'] = [uid()];
    $foreign['versions'][0]['contentHash'] = BlueprintService::hash($foreign['versions'][0]['content']);
    $imported = $this->post('/api/v1/blueprints/import', ['file' => $file($foreign)], ['Accept' => 'application/json'])->assertCreated()->json('data.uuid');
    $this->postJson("/api/v1/blueprints/{$imported}/instantiate", ['application' => $app, 'key' => 'leave_three', 'name' => ['en' => 'Three']])
        ->assertStatus(422)->assertJsonValidationErrors('dependencies');

    // Detaching stops propagation; deleting the blueprint keeps the form.
    $instance = $this->getJson("/api/v1/blueprints/{$bp}")->json('data.instances.0.uuid');
    $this->postJson("/api/v1/blueprint-instances/{$instance}/detach")->assertNoContent();
    $this->deleteJson("/api/v1/blueprints/{$bp}")->assertNoContent();
    expect(DB::table('forms')->where('uuid', $copy)->whereNull('deleted_at')->exists())->toBeTrue();
});

it('requires the manage blueprints permission', function () {
    $user = $this->makeUser();
    $this->flushSession();
    $this->actingAs($user, 'web');
    $this->getJson('/api/v1/blueprints')->assertForbidden();
    $this->postJson('/api/v1/blueprints', [])->assertForbidden();
});
