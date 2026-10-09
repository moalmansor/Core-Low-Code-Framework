<?php

declare(strict_types=1);

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
