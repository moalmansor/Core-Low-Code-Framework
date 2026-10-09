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

it('lists and creates the linked records of an inline sub-form', function () {
    [, $tasks] = createForm($this, 'tasks');
    saveDraft($this, $tasks, [], [fieldDoc('title', 'text', null, ['validation' => ['required' => true]])])->assertOk();
    expect(publish($this, $tasks)['status'])->toBe('applied');

    [, $projects] = createForm($this, 'projects');
    $relation = ['uuid' => uid(), 'key' => 'tasks', 'type' => 'one_to_many', 'target' => $tasks, 'kind' => 'subform', 'onDelete' => 'cascade', 'display' => null, 'value' => null, 'inverse' => null];
    $sub = groupDoc('task_list', 'subform', null, ['subform' => ['form' => $tasks, 'relation' => $relation['uuid']]]);
    $saved = saveDraft($this, $projects, [$sub], [fieldDoc('name', 'text')], [$relation])->assertOk();
    expect($saved->json('data.problems'))->toBe([]);
    expect(publish($this, $projects)['status'])->toBe('applied');

    $project = $this->postJson("/api/v1/r/{$projects}", ['values' => ['name' => 'Launch']])->assertCreated()->json('data.uuid');
    $this->postJson("/api/v1/r/{$projects}/{$project}/subforms/task_list", ['values' => []])->assertStatus(422)->assertJsonStructure(['errors' => ['title']]);
    $task = $this->postJson("/api/v1/r/{$projects}/{$project}/subforms/task_list", ['values' => ['title' => 'Book venue']])->assertCreated()->json('data.uuid');
    $this->postJson("/api/v1/r/{$tasks}", ['values' => ['title' => 'Unrelated']])->assertCreated();

    $list = $this->getJson("/api/v1/r/{$projects}/{$project}/subforms/task_list")->assertOk();
    expect($list->json('data'))->toHaveCount(1)
        ->and($list->json('data.0.uuid'))->toBe($task)
        ->and($list->json('meta.can_add'))->toBeTrue();
    expect(DB::table('f_tasks')->where('uuid', $task)->value('projects_tasks_id'))->toBe(DB::table('f_projects')->where('uuid', $project)->value('id'));
    $this->getJson("/api/v1/r/{$projects}/{$project}/subforms/nope")->assertNotFound();

    // The relation cascades: deleting the project deletes its tasks, not the unrelated one.
    $this->deleteJson("/api/v1/r/{$projects}/{$project}", ['row_version' => 1])->assertNoContent();
    expect(DB::table('f_tasks')->where('uuid', $task)->value('deleted_at'))->not->toBeNull()
        ->and(DB::table('f_tasks')->whereNull('deleted_at')->count())->toBe(1);
});
