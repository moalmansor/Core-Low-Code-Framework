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

it('posts, lists and deletes comments on a record', function () {
    [, $form] = createForm($this, 'comment_visits');
    saveDraft($this, $form, [], [fieldDoc('visitor', 'text')])->assertOk();
    expect(publish($this, $form)['status'])->toBe('applied');
    $record = $this->postJson("/api/v1/r/{$form}", ['values' => ['visitor' => 'Nora']])->assertCreated()->json('data.uuid');

    $this->getJson("/api/v1/r/{$form}/{$record}/comments")->assertOk()->assertJsonPath('data', []);
    $posted = $this->postJson("/api/v1/r/{$form}/{$record}/comments", ['body' => 'a'])->assertCreated()->json('data.uuid');
    $this->postJson("/api/v1/r/{$form}/{$record}/comments", ['body' => 'Reply <script>x</script>', 'parent' => $posted])->assertCreated();

    $list = $this->getJson("/api/v1/r/{$form}/{$record}/comments")->assertOk()->json('data');
    expect($list)->toHaveCount(2)
        ->and($list[0])->toMatchArray(['uuid' => $posted, 'parent' => null, 'body' => 'a', 'mine' => true])
        ->and($list[0]['author']['name'])->toBe($this->admin->name)
        ->and($list[1]['parent'])->toBe($posted)
        ->and($list[1]['body'])->not->toContain('<script>');

    $this->deleteJson("/api/v1/r/{$form}/{$record}/comments/{$posted}")->assertNoContent();
    expect($this->getJson("/api/v1/r/{$form}/{$record}/comments")->json('data'))->toHaveCount(1);
    expect(DB::table('audit_logs')->whereIn('event', ['record.comment_added', 'record.comment_deleted'])->count())->toBe(3);
});

it('sanitises rich-text values on the server', function () {
    [, $form] = createForm($this, 'rich_notes');
    saveDraft($this, $form, [], [fieldDoc('notes', 'rich_text')])->assertOk();
    expect(publish($this, $form)['status'])->toBe('applied');
    $html = '<p onclick="x()">Hi <a href="https://example.test">link</a><script>alert(1)</script><a href="javascript:alert(1)">bad</a></p>';
    $record = $this->postJson("/api/v1/r/{$form}", ['values' => ['notes' => $html]])->assertCreated()->json('data.uuid');

    $stored = $this->getJson("/api/v1/r/{$form}/{$record}")->assertOk()->json('data.values.notes');
    expect($stored)->toContain('<a href="https://example.test"')->toContain('noopener')
        ->not->toContain('script')->not->toContain('onclick')->not->toContain('javascript:');
});
