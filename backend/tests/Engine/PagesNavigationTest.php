<?php

declare(strict_types=1);

use App\Expressions\Evaluation\Ast;
use App\Expressions\Parsing\Parser;
use Illuminate\Support\Facades\DB;

require_once __DIR__.'/helpers.php';

beforeEach(function () {
    $this->completeSetup();
    $this->admin = $this->superAdmin();
    $this->flushSession();
    $this->flushSession();
    $this->actingAs($this->admin, 'web');
});

function pageDoc(string $app, string $type, array $extra = []): array
{
    return array_replace(['application' => $app, 'key' => 'p'.substr(str_replace('-', '', uid()), -10), 'type' => $type, 'published' => true,
        'i18n' => ['name' => ['en' => 'Guide', 'ar' => 'الدليل'], 'content' => []]], $extra);
}

function condition(string $source): array
{
    return Ast::sortKeys(Parser::parse($source));
}

it('keeps page content safe, checks the page and what it shows, and refuses stale saves', function () {
    [$app, $form] = createForm($this, 'tickets');
    saveDraft($this, $form, [], [fieldDoc('subject', 'text')])->assertOk();
    publish($this, $form);

    $page = $this->postJson('/api/v1/pages', ['page' => pageDoc($app, 'content', ['i18n' => ['name' => ['en' => 'Guide'],
        'content' => ['en' => '<p onclick="x()">Hello<script>alert(1)</script></p>', 'ar' => '<p>مرحبا</p>']]])])->assertCreated()->json('data');
    expect($page['i18n']['content']['en'])->toBe('<p>Hello</p>')
        ->and($page['permission'])->toBe("page.{$page['uuid']}.view");

    // A link page accepts only https:// addresses and paths inside the application.
    $this->postJson('/api/v1/pages', ['page' => pageDoc($app, 'link', ['url' => 'javascript:alert(1)'])])->assertUnprocessable()->assertJsonValidationErrors('url');
    $this->postJson('/api/v1/pages', ['page' => pageDoc($app, 'link', ['url' => '//evil.example'])])->assertUnprocessable()->assertJsonValidationErrors('url');
    $this->postJson('/api/v1/pages', ['page' => pageDoc($app, 'content', ['key' => $page['key'], 'i18n' => ['name' => ['en' => 'X'], 'content' => ['en' => 'x']]])])
        ->assertUnprocessable()->assertJsonValidationErrors('key');

    $viewer = $this->makeUser();
    grantPermission($viewer->id, "app.{$app}.access");
    $this->flushSession();
    $this->actingAs($viewer, 'web');
    $this->getJson("/api/v1/pages/{$page['uuid']}/view")->assertNotFound();
    $this->getJson('/api/v1/pages')->assertForbidden();
    grantPermission($viewer->id, "page.{$page['uuid']}.view");
    $view = $this->getJson("/api/v1/pages/{$page['uuid']}/view")->assertOk()->json('data');
    expect($view['content'])->toBe('<p>Hello</p>')->and($view['label'])->toBe('Guide');

    // A page that hosts a form needs the form's own permission too.
    $this->flushSession();
    $this->actingAs($this->admin, 'web');
    $host = $this->postJson('/api/v1/pages', ['page' => pageDoc($app, 'form_host', ['form' => $form])])->assertCreated()->json('data');
    $this->flushSession();
    $this->actingAs($viewer, 'web');
    grantPermission($viewer->id, "page.{$host['uuid']}.view");
    $this->getJson("/api/v1/pages/{$host['uuid']}/view")->assertNotFound();
    grantPermission($viewer->id, "form.{$form}.view");
    expect($this->getJson("/api/v1/pages/{$host['uuid']}/view")->assertOk()->json('data.form'))->toBe($form);

    // An unpublished page is not shown; a stale save is refused.
    $this->flushSession();
    $this->actingAs($this->admin, 'web');
    $doc = $this->getJson("/api/v1/pages/{$page['uuid']}")->assertOk()->json('data');
    $draft = array_replace($doc, ['published' => false]);
    $this->putJson("/api/v1/pages/{$page['uuid']}", ['page' => $draft, 'base_hash' => $doc['hash']])->assertOk();
    $this->putJson("/api/v1/pages/{$page['uuid']}", ['page' => $draft, 'base_hash' => $doc['hash']])->assertStatus(409);
    $this->flushSession();
    $this->actingAs($viewer, 'web');
    $this->getJson("/api/v1/pages/{$page['uuid']}/view")->assertNotFound();

    // Maintenance: the application is offline for the viewer, not for the admin.
    $this->flushSession();
    $this->actingAs($this->admin, 'web');
    $this->postJson("/api/v1/applications/{$app}/maintenance", ['enabled' => true, 'message' => ['en' => 'Back at noon']])->assertOk();
    $this->getJson("/api/v1/pages/{$host['uuid']}/view")->assertOk();
    $this->flushSession();
    $this->actingAs($viewer, 'web');
    $this->getJson("/api/v1/pages/{$host['uuid']}/view")->assertStatus(423)->assertJsonPath('message', 'Back at noon');

    // Deleting a page removes its permission; the audit trail keeps it.
    $this->flushSession();
    $this->actingAs($this->admin, 'web');
    $this->deleteJson("/api/v1/pages/{$host['uuid']}")->assertNoContent();
    expect(DB::table('permissions')->where('key', "page.{$host['uuid']}.view")->exists())->toBeFalse()
        ->and(DB::table('audit_logs')->where('event', 'page.deleted')->exists())->toBeTrue();
    $this->getJson("/api/v1/pages/{$host['uuid']}")->assertNotFound();
});

it('builds menus with pages, reports, dashboards and My Work, hides items by rule and counts badges', function () {
    [$app, $form] = createForm($this, 'tickets');
    saveDraft($this, $form, [], [fieldDoc('subject', 'text')])->assertOk();
    publish($this, $form);
    foreach (['Printer', 'Scanner', 'Phone'] as $s) {
        $this->postJson("/api/v1/r/{$form}", ['values' => ['subject' => $s]])->assertCreated();
    }
    $page = $this->postJson('/api/v1/pages', ['page' => pageDoc($app, 'content', ['i18n' => ['name' => ['en' => 'Guide'], 'content' => ['en' => '<p>Hi</p>']]])])->assertCreated()->json('data');
    $report = $this->postJson('/api/v1/reports', ['report' => ['key' => 'count_all', 'form' => $form, 'visualization' => 'kpi', 'i18n' => ['name' => ['en' => 'Count']],
        'dimensions' => [], 'measures' => [['uuid' => uid(), 'aggregate' => 'count', 'path' => null, 'i18n' => ['label' => []]]], 'columns' => [], 'filters' => [], 'sort' => null, 'limit' => null, 'options' => []]])
        ->assertCreated()->json('data');

    $label = static fn (string $en) => ['en' => $en, 'ar' => $en];
    $items = [
        ['type' => 'header', 'label' => $label('Work'), 'children' => [
            ['type' => 'my_work', 'label' => $label('My work'), 'badge' => ['kind' => 'my_work']],
            ['type' => 'form', 'target' => $form, 'label' => $label('Tickets'), 'badge' => ['kind' => 'records']],
        ]],
        ['type' => 'page', 'target' => $page['uuid'], 'label' => $label('Guide')],
        ['type' => 'report', 'target' => $report['uuid'], 'label' => $label('Count'), 'condition' => condition('has_role("super_admin")')],
        ['type' => 'link', 'url' => 'https://example.org/help', 'label' => $label('Help'), 'open_in_new_tab' => true],
    ];
    $tree = $this->putJson("/api/v1/applications/{$app}/menu", ['items' => $items])->assertOk()->json('data');
    expect($tree[0]['children'][1]['badge'])->toBe(['kind' => 'records'])
        ->and($tree[2]['condition'])->toEqual(condition('has_role("super_admin")'))
        ->and(DB::table('conditions')->where('owner_type', 'menu_item')->count())->toBe(1);

    // Rules may refer only to the person; a badge counting records needs a form.
    $bad = $items;
    $bad[2]['condition'] = condition('subject = "x"');
    $this->putJson("/api/v1/applications/{$app}/menu", ['items' => $bad])->assertUnprocessable();
    $bad = $items;
    $bad[1]['badge'] = ['kind' => 'records'];
    $this->putJson("/api/v1/applications/{$app}/menu", ['items' => $bad])->assertUnprocessable();
    $deep = ['type' => 'header', 'label' => $label('L'), 'children' => []];
    for ($i = 0; $i < 21; $i++) {
        $deep = ['type' => 'header', 'label' => $label('L'.$i), 'children' => [$deep]];
    }
    $this->putJson("/api/v1/applications/{$app}/menu", ['items' => [$deep]])->assertUnprocessable();

    // The admin sees everything, with badge counts.
    $nav = collect($this->getJson('/api/v1/navigation')->assertOk()->json('data'))->firstWhere('uuid', $app);
    expect(array_column($nav['items'], 'type'))->toBe(['header', 'page', 'report', 'link']);
    $badges = $this->getJson('/api/v1/navigation/badges')->assertOk()->json('data');
    expect($badges[$tree[0]['children'][1]['uuid']])->toBe(3)->and($badges[$tree[0]['children'][0]['uuid']])->toBe(0);

    // A viewer with the form only: the page, the report (by rule) and nothing else they may not open.
    $viewer = $this->makeUser();
    grantPermission($viewer->id, "app.{$app}.access");
    grantPermission($viewer->id, "form.{$form}.view");
    grantPermission($viewer->id, "report.{$report['uuid']}.view");
    $this->flushSession();
    $this->actingAs($viewer, 'web');
    $nav = collect($this->getJson('/api/v1/navigation')->assertOk()->json('data'))->firstWhere('uuid', $app);
    expect(array_column($nav['items'], 'type'))->toBe(['header'])
        ->and(array_column($nav['items'][0]['children'], 'type'))->toBe(['my_work', 'form'])
        ->and($nav['items'][0]['children'][1])->not->toHaveKey('condition');
    // A link opens nothing the framework guards, so it is shown only where its own menu permission is granted.
    grantPermission($viewer->id, "menu.{$tree[3]['uuid']}.view");
    $nav = collect($this->getJson('/api/v1/navigation')->assertOk()->json('data'))->firstWhere('uuid', $app);
    expect(array_column($nav['items'], 'type'))->toBe(['header', 'link']);

    // Removing an item removes its rule.
    $this->flushSession();
    $this->actingAs($this->admin, 'web');
    $this->putJson("/api/v1/applications/{$app}/menu", ['items' => array_slice($items, 0, 2)])->assertOk();
    expect(DB::table('conditions')->where('owner_type', 'menu_item')->count())->toBe(0);
});
