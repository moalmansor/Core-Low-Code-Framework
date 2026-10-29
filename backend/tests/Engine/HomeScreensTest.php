<?php

declare(strict_types=1);

use App\Expressions\Evaluation\Ast;
use App\Expressions\Parsing\Parser;
use App\Modules\Access\AccessCache;
use Illuminate\Support\Facades\DB;

require_once __DIR__.'/helpers.php';

beforeEach(function () {
    $this->completeSetup();
    $this->admin = $this->superAdmin();
    $this->flushSession();
    $this->actingAs($this->admin, 'web');
});

function screenDoc(string $target = 'default', ?string $targetUuid = null, array $widgets = [], array $extra = []): array
{
    return array_replace(['application' => null, 'target_type' => $target, 'target' => $targetUuid, 'priority' => 0, 'active' => true,
        'i18n' => ['name' => ['en' => ucfirst($target).' home']], 'widgets' => $widgets], $extra);
}

function widget(string $type, array $extra = []): array
{
    return array_replace(['type' => $type, 'i18n' => ['title' => ['en' => ucfirst($type)], 'text' => []], 'layout' => ['w' => 6, 'h' => 2]], $extra);
}

it('gives each person the screen meant for them, with only the widgets they may see', function () {
    [, $form] = createForm($this, 'tickets');
    saveDraft($this, $form, [], [fieldDoc('subject', 'text')])->assertOk();
    publish($this, $form);
    $report = $this->postJson('/api/v1/reports', ['report' => ['key' => 'all_tickets', 'form' => $form, 'visualization' => 'kpi', 'i18n' => ['name' => ['en' => 'Tickets']],
        'dimensions' => [], 'measures' => [['uuid' => uid(), 'aggregate' => 'count', 'path' => null, 'i18n' => ['label' => []]]], 'columns' => [], 'filters' => [], 'sort' => null, 'limit' => null, 'options' => []]])
        ->assertCreated()->json('data.uuid');
    $dept = $this->postJson('/api/v1/departments', ['code' => 'OPS', 'name' => ['en' => 'Operations']])->assertCreated()->json('data.uuid');
    $userRole = strtolower((string) DB::table('roles')->where('key', 'user')->value('uuid'));

    $default = $this->postJson('/api/v1/home-screens', ['screen' => screenDoc('default', null, [
        widget('html', ['i18n' => ['title' => [], 'text' => ['en' => '<p onmouseover="x()">Welcome<script>bad()</script></p>']]]),
        widget('report', ['report' => $report]),
        widget('my_records', ['form' => $form]),
        widget('shortcuts', ['links' => [['form' => $form, 'action' => 'new', 'i18n' => ['label' => ['en' => 'New ticket']]]]]),
        widget('pinned_links', ['links' => [['url' => 'https://example.org/policy', 'i18n' => ['label' => ['en' => 'Policy']]]]]),
        widget('my_work', ['condition' => Ast::sortKeys(Parser::parse('has_role("super_admin")'))]),
    ])])->assertCreated()->json('data');
    expect($default['widgets'][0]['i18n']['text']['en'])->toBe('<p>Welcome</p>')
        ->and(DB::table('conditions')->where('owner_type', 'page_widget')->count())->toBe(1);

    // Bad links, missing targets and rules that read a record are refused.
    $this->postJson('/api/v1/home-screens', ['screen' => screenDoc('default', null, [widget('pinned_links', ['links' => [['url' => 'javascript:x()', 'i18n' => ['label' => ['en' => 'X']]]]])])])
        ->assertUnprocessable()->assertJsonValidationErrors('widgets.0.links.0');
    $this->postJson('/api/v1/home-screens', ['screen' => screenDoc('role')])->assertUnprocessable()->assertJsonValidationErrors('target');
    $this->postJson('/api/v1/home-screens', ['screen' => screenDoc('default', null, [widget('my_work', ['condition' => Ast::sortKeys(Parser::parse('subject = "x"'))])])])
        ->assertUnprocessable()->assertJsonValidationErrors('widgets.0.condition');

    // The default screen, filtered: no report, no form widgets, no rule-limited widget for a plain viewer.
    $viewer = $this->makeUser();
    $this->flushSession();
    $this->actingAs($viewer, 'web');
    $home = $this->getJson('/api/v1/home')->assertOk()->json('data');
    expect($home['uuid'])->toBe($default['uuid'])
        ->and(array_column($home['widgets'], 'type'))->toBe(['html', 'pinned_links'])
        ->and($home['widgets'][0]['text'])->toBe('<p>Welcome</p>')
        ->and($home['widgets'][1]['links'][0])->toBe(['url' => 'https://example.org/policy', 'label' => 'Policy']);
    $this->getJson('/api/v1/home-screens')->assertForbidden();

    // With the form and report: their widgets appear; a shortcut to create needs the create permission.
    grantPermission($viewer->id, "form.{$form}.view");
    grantPermission($viewer->id, "report.{$report}.view");
    expect(array_column($this->getJson('/api/v1/home')->json('data.widgets'), 'type'))->toBe(['html', 'report', 'my_records', 'pinned_links']);
    grantPermission($viewer->id, "form.{$form}.create");
    expect(array_column($this->getJson('/api/v1/home')->json('data.widgets'), 'type'))->toBe(['html', 'report', 'my_records', 'shortcuts', 'pinned_links']);

    // Precedence: the person's own screen, then a role's, then a department's, then the default.
    $this->flushSession();
    $this->actingAs($this->admin, 'web');
    $deptScreen = $this->postJson('/api/v1/home-screens', ['screen' => screenDoc('department', $dept)])->assertCreated()->json('data.uuid');
    $roleScreen = $this->postJson('/api/v1/home-screens', ['screen' => screenDoc('role', $userRole)])->assertCreated()->json('data.uuid');
    $viewerUuid = strtolower((string) DB::table('users')->where('id', $viewer->id)->value('uuid'));
    $ownScreen = $this->postJson('/api/v1/home-screens', ['screen' => screenDoc('user', $viewerUuid)])->assertCreated()->json('data');
    DB::table('users')->where('id', $viewer->id)->update(['department_id' => DB::table('departments')->where('uuid', $dept)->value('id')]);
    $this->flushSession();
    $this->actingAs($viewer->fresh(), 'web');
    expect($this->getJson('/api/v1/home')->json('data.uuid'))->toBe($ownScreen['uuid']);
    $this->flushSession();
    $this->actingAs($this->admin, 'web');
    $this->putJson("/api/v1/home-screens/{$ownScreen['uuid']}", ['screen' => array_replace($ownScreen, ['active' => false]), 'base_hash' => $ownScreen['hash']])->assertOk();
    $this->putJson("/api/v1/home-screens/{$ownScreen['uuid']}", ['screen' => $ownScreen, 'base_hash' => $ownScreen['hash']])->assertStatus(409);
    $this->flushSession();
    $this->actingAs($viewer->fresh(), 'web');
    expect($this->getJson('/api/v1/home')->json('data.uuid'))->toBe($roleScreen);
    $this->flushSession();
    $this->actingAs($this->admin, 'web');
    $this->deleteJson("/api/v1/home-screens/{$roleScreen}")->assertNoContent();
    $this->flushSession();
    $this->actingAs($viewer->fresh(), 'web');
    expect($this->getJson('/api/v1/home')->json('data.uuid'))->toBe($deptScreen);
});

it('lists the person own records and recent changes, only where they may still see them', function () {
    [, $form] = createForm($this, 'tickets');
    saveDraft($this, $form, [], [fieldDoc('subject', 'text')])->assertOk();
    publish($this, $form);
    $viewer = $this->makeUser();
    foreach (["form.{$form}.view", "form.{$form}.create", "form.{$form}.edit"] as $p) {
        grantPermission($viewer->id, $p);
    }
    $this->postJson("/api/v1/r/{$form}", ['values' => ['subject' => 'Admin ticket']])->assertCreated();
    $this->flushSession();
    $this->actingAs($viewer, 'web');
    $mine = $this->postJson("/api/v1/r/{$form}", ['values' => ['subject' => 'My ticket']])->assertCreated()->json('data');

    $list = $this->getJson("/api/v1/r/{$form}?mine=1")->assertOk()->json('data');
    expect(array_column($list, 'uuid'))->toBe([$mine['uuid']]);
    expect($this->getJson("/api/v1/r/{$form}")->json('meta.total'))->toBe(2);

    $activity = $this->getJson('/api/v1/home/activity')->assertOk()->json('data');
    expect($activity)->toHaveCount(1)
        ->and($activity[0]['record']['uuid'])->toBe($mine['uuid'])
        ->and($activity[0]['operation'])->toBe('create');

    // Once the form is no longer theirs to see, its changes leave the list.
    DB::table('permission_assignments')->where('subject_type', 'user')->where('subject_id', $viewer->id)->delete();
    app(AccessCache::class)->bump();
    expect($this->getJson('/api/v1/home/activity')->assertOk()->json('data'))->toBe([]);
});
