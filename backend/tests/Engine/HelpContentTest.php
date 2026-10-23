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

function hcHelp(string $type, string $target, string $display, string $body, bool $published = true): array
{
    return ['target_type' => $type, 'target' => $target, 'display' => $display, 'published' => $published, 'sort_order' => 0,
        'i18n' => ['title' => ['en' => 'About this', 'ar' => 'حول هذا'], 'body' => ['en' => $body]]];
}

it('shows published help for a form, its fields and groups, as tooltips, a panel and pages, to people who may open the form', function () {
    [$app, $form] = createForm($this, 'leave_requests');
    $days = fieldDoc('days', 'number');
    saveDraft($this, $form, [], [$days])->assertOk();
    expect(publish($this, $form)['status'])->toBe('applied');

    // Tooltips are plain text and only beside a field or group.
    $tip = $this->postJson('/api/v1/help-content', ['help' => hcHelp('field', $days['uuid'], 'tooltip', '<b>Working</b> days only')])->assertCreated()->json('data');
    expect($tip['i18n']['body']['en'])->toBe('Working days only');
    $this->postJson('/api/v1/help-content', ['help' => hcHelp('form', $form, 'tooltip', 'x')])->assertUnprocessable()->assertJsonValidationErrors('display');
    $this->postJson('/api/v1/help-content', ['help' => hcHelp('field', uid(), 'tooltip', 'x')])->assertUnprocessable()->assertJsonPath('errors.target.0', __('experience.help_publish_first'));

    $panel = $this->postJson('/api/v1/help-content', ['help' => hcHelp('form', $form, 'side_panel', '<p onclick="x()">Ask your manager first<script>alert(1)</script></p>')])->assertCreated()->json('data');
    expect($panel['i18n']['body']['en'])->toBe('<p>Ask your manager first</p>');
    $page = $this->postJson('/api/v1/help-content', ['help' => hcHelp('form', $form, 'help_page', '<h2>The policy</h2>')])->assertCreated()->json('data');
    $this->postJson('/api/v1/help-content', ['help' => hcHelp('form', $form, 'side_panel', 'Draft', false)])->assertCreated();
    // The editor's pickers: published forms, then a form's fields.
    expect(array_column($this->getJson('/api/v1/help-content/targets')->assertOk()->json('data.forms'), 'uuid'))->toContain($form);
    $this->getJson("/api/v1/help-content/targets?form={$form}")->assertOk()->assertJsonPath('data.fields.0.uuid', $days['uuid']);

    $user = $this->makeUser();
    grantPermission($user->id, "form.{$form}.view");
    $stranger = $this->makeUser();
    $this->flushSession();
    $this->actingAs($user, 'web');
    $help = $this->getJson("/api/v1/help?type=form&target={$form}")->assertOk()->json('data');
    expect($help['tooltips'])->toBe([$days['uuid'] => 'Working days only']);
    // The unpublished entry is not shown; the help page is listed without its body.
    expect(array_column($help['panel'], 'display'))->toBe(['side_panel', 'help_page']);
    expect($help['panel'][1]['body'])->toBeNull();
    $this->getJson("/api/v1/help/{$page['uuid']}")->assertOk()->assertJsonPath('data.body', '<h2>The policy</h2>');
    $this->getJson("/api/v1/help/{$panel['uuid']}")->assertNotFound();
    $this->getJson('/api/v1/help-content')->assertForbidden();

    // Someone who cannot open the form sees no help for it, not even the page.
    $this->flushSession();
    $this->actingAs($stranger, 'web');
    expect($this->getJson("/api/v1/help?type=form&target={$form}")->assertOk()->json('data'))->toBe(['tooltips' => [], 'panel' => []]);
    $this->getJson("/api/v1/help/{$page['uuid']}")->assertNotFound();

    // Saving over someone else's change is refused.
    $this->flushSession();
    $this->actingAs($this->admin, 'web');
    $doc = hcHelp('form', $form, 'side_panel', 'Ask HR');
    $this->putJson("/api/v1/help-content/{$panel['uuid']}", ['help' => $doc, 'base_hash' => $panel['hash']])->assertOk();
    $this->putJson("/api/v1/help-content/{$panel['uuid']}", ['help' => $doc, 'base_hash' => $panel['hash']])->assertStatus(409);
    $this->deleteJson("/api/v1/help-content/{$tip['uuid']}")->assertNoContent();
    expect(DB::table('audit_logs')->where('event', 'like', 'help.%')->count())->toBe(6);
});

it('gives admin areas their help, only to those who may open the area', function () {
    $this->postJson('/api/v1/help-content', ['help' => hcHelp('admin_area', 'users', 'side_panel', 'How to invite people')])->assertCreated()->assertJsonPath('data.target', 'users');
    $this->postJson('/api/v1/help-content', ['help' => hcHelp('admin_area', 'nowhere', 'side_panel', 'x')])->assertUnprocessable()->assertJsonValidationErrors('target');
    expect($this->getJson('/api/v1/help?type=admin_area&target=users')->json('data.panel.0.body'))->toBe('How to invite people');

    $this->flushSession();
    $this->actingAs($this->makeUser(), 'web');
    expect($this->getJson('/api/v1/help?type=admin_area&target=users')->json('data.panel'))->toBe([]);
});

it('keeps a knowledge page and a changelog per application, and tells people about the changelog in their bell', function () {
    [$app] = createForm($this, 'assets');
    $doc = static fn (string $type, string $key) => ['application' => $app, 'key' => $key, 'type' => $type, 'published' => true,
        'i18n' => ['name' => ['en' => 'What changed', 'ar' => 'ما الذي تغيّر'], 'content' => ['en' => '<p>New field: serial number</p>']]];
    $knowledge = $this->postJson('/api/v1/pages', ['page' => $doc('knowledge', 'handbook')])->assertCreated()->json('data.uuid');
    $changelog = $this->postJson('/api/v1/pages', ['page' => $doc('changelog', 'changes')])->assertCreated()->json('data.uuid');
    $this->postJson('/api/v1/pages', ['page' => array_replace($doc('changelog', 'empty'), ['i18n' => ['name' => ['en' => 'x'], 'content' => []]])])
        ->assertUnprocessable()->assertJsonValidationErrors('i18n.content');

    $this->postJson("/api/v1/pages/{$knowledge}/announce")->assertUnprocessable();
    $this->postJson("/api/v1/pages/{$changelog}/announce")->assertCreated();

    $user = $this->makeUser();
    grantPermission($user->id, "app.{$app}.access");
    $this->flushSession();
    $this->actingAs($user, 'web');
    $bell = $this->getJson('/api/v1/announcements/active?placement=bell')->assertOk()->json('data');
    expect($bell)->toHaveCount(1);
    expect($bell[0]['title'])->toBe('What changed');
    expect($bell[0]['body'])->toContain("/pages/{$changelog}")->toContain('Read what changed');
    // Announcing needs both "Manage pages and menus" and "Publish announcements".
    $this->postJson("/api/v1/pages/{$changelog}/announce")->assertForbidden();
});
