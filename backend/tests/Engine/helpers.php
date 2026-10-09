<?php

declare(strict_types=1);

use App\Modules\Access\AccessCache;
use App\Modules\Access\Models\PermissionAssignment;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/*
 * Builders for draft documents used by the engine tests. They create
 * documents exactly as the builder UI sends them.
 */

function uid(): string
{
    return (string) Str::uuid7();
}

/** @return array<string, mixed> */
function fieldDoc(string $key, string $type, ?string $group = null, array $extra = []): array
{
    return array_replace_recursive([
        'uuid' => uid(), 'key' => $key, 'type' => $type, 'group' => $group, 'order' => 0,
        'storage' => ['nullable' => true, 'index' => 'none'],
        'options' => null, 'validation' => [], 'behavior' => [], 'ui' => [], 'table' => [], 'export' => [], 'events' => [],
        'hook' => null, 'flags' => ['trackChanges' => true], 'justification' => 'inherit', 'relation' => null, 'template' => null,
        'i18n' => ['label' => ['en' => ucfirst(str_replace('_', ' ', $key)), 'ar' => $key]],
    ], $extra);
}

/** @return array<string, mixed> */
function groupDoc(string $key, string $type, ?string $parent = null, array $extra = []): array
{
    return array_replace_recursive([
        'uuid' => uid(), 'key' => $key, 'type' => $type, 'parent' => $parent, 'order' => 0, 'layout' => [],
        'collapsible' => false, 'defaultState' => 'open', 'validation' => null,
        'repeater' => $type === 'repeater' ? ['minRows' => 0, 'display' => 'table'] : null,
        'wizard' => null, 'subform' => null, 'justification' => 'inherit',
        'i18n' => ['title' => ['en' => ucfirst($key)]],
    ], $extra);
}

/** @return list<array<string, mixed>> */
function staticOptions(array $values): array
{
    return array_map(static fn (string $v, int $i) => ['uuid' => uid(), 'value' => $v, 'order' => $i, 'i18n' => ['label' => ['en' => ucfirst($v)]]], $values, array_keys($values));
}

/** Creates an application and a form through the API; returns [appUuid, formUuid]. */
function createForm(TestCase $test, string $key, string $kind = 'form'): array
{
    $app = $test->postJson('/api/v1/applications', ['key' => 'app_'.$key, 'name' => ['en' => 'App '.$key]])->assertCreated()->json('data.uuid');
    $form = $test->postJson('/api/v1/forms', ['kind' => $kind, 'key' => $key, 'application' => $app, 'name' => ['en' => ucfirst($key), 'ar' => $key]])->assertCreated()->json('data.uuid');

    return [$app, $form];
}

/** Saves a draft document built from the current one plus the given parts. */
function saveDraft(TestCase $test, string $form, array $groups, array $fields, array $relations = [], array $conditions = [], array $formPatch = []): TestResponse
{
    $current = $test->getJson("/api/v1/forms/{$form}/draft")->assertOk()->json('data');
    $doc = $current['document'];
    $doc['form'] = array_replace_recursive($doc['form'], $formPatch);
    $doc['groups'] = $groups;
    $doc['fields'] = $fields;
    $doc['relations'] = $relations;
    $doc['conditions'] = $conditions;

    return $test->putJson("/api/v1/forms/{$form}/draft", ['document' => $doc, 'draft_updated_at' => $current['draft_updated_at']]);
}

/** Runs impact + publish and returns the plan payload after the (synchronous) queue ran it. */
function publish(TestCase $test, string $form, array $options = []): array
{
    $impact = $test->postJson("/api/v1/forms/{$form}/impact")->assertOk()->json('data');
    expect($impact['impact']['blocking'])->toBe([]);
    $plan = $test->postJson("/api/v1/forms/{$form}/publish", ['impact_hash' => $impact['impact_hash']] + $options)->assertStatus(202)->json('data.plan');

    $data = $test->getJson("/api/v1/migration-plans/{$plan}")->assertOk()->json('data');
    if ($data['status'] !== 'applied' && getenv('LCF_DEBUG')) {
        fwrite(STDERR, json_encode(array_values(array_filter($data['steps'], static fn ($s) => $s['error'] !== null)), JSON_PRETTY_PRINT));
    }

    return $data;
}

/** @return array<string, mixed> */
function statusDoc(string $key, bool $initial = false, bool $final = false): array
{
    return ['uuid' => uid(), 'key' => $key, 'i18n' => ['name' => ['en' => ucfirst($key), 'ar' => $key]], 'color' => '#336699', 'icon' => null, 'initial' => $initial, 'final' => $final, 'order' => 0, 'position' => ['x' => 0, 'y' => 0]];
}

/** @return array<string, mixed> */
function transitionDoc(string $key, ?array $from, array $to, array $extra = []): array
{
    return array_replace_recursive([
        'uuid' => uid(), 'key' => $key, 'from' => $from['uuid'] ?? null, 'to' => $to['uuid'], 'i18n' => ['name' => ['en' => ucfirst($key)]],
        'condition' => null, 'requiredFields' => [], 'comment' => 'none', 'attachments' => 'none',
        'approval' => ['mode' => 'none', 'approvers' => [], 'n' => null, 'quorumWeight' => null, 'rejection' => 'immediate', 'rejectionStatus' => null],
        'confirmation' => false, 'style' => null, 'order' => 0, 'edge' => null,
    ], $extra);
}

function saveWorkflow(TestCase $t, string $form, array $statuses, array $transitions, array $sla = []): TestResponse
{
    $hash = $t->getJson("/api/v1/forms/{$form}/workflow")->assertOk()->json('data.hash');

    return $t->putJson("/api/v1/forms/{$form}/workflow", ['document' => ['statuses' => $statuses, 'transitions' => $transitions, 'sla' => $sla], 'base_hash' => $hash]);
}

function grantPermission(int $userId, string $key, string $type = 'user'): void
{
    $permission = DB::table('permissions')->where('key', $key)->value('id');
    PermissionAssignment::query()->create(['permission_id' => $permission, 'subject_type' => $type, 'subject_id' => $userId, 'effect' => 'allow', 'include_descendants' => false]);
    app(AccessCache::class)->bump();
}

/** A request form with draft → submitted → approved, a mandatory comment on submit and a required "reason". */
function buildWorkflowForm(TestCase $t): array
{
    [, $form] = createForm($t, 'leave');
    $subject = fieldDoc('subject', 'text', null, ['validation' => ['required' => true], 'table' => ['filterable' => true]]);
    $reason = fieldDoc('reason', 'textarea');
    saveDraft($t, $form, [], [$subject, $reason])->assertOk();
    $draft = statusDoc('draft', true);
    $submitted = statusDoc('submitted');
    $approved = statusDoc('approved', false, true);
    $submit = transitionDoc('submit', $draft, $submitted, ['comment' => 'mandatory', 'requiredFields' => [$reason['uuid']]]);
    $approve = transitionDoc('approve', $submitted, $approved);
    saveWorkflow($t, $form, [$draft, $submitted, $approved], [$submit, $approve])->assertOk()->assertJsonPath('data.problems', []);
    expect(publish($t, $form)['status'])->toBe('applied');

    return compact('form', 'subject', 'reason', 'draft', 'submitted', 'approved', 'submit', 'approve');
}
