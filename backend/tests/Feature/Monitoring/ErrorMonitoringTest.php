<?php

declare(strict_types=1);

use App\Modules\Monitoring\Mail\ErrorAlertMail;
use App\Modules\Monitoring\Models\ErrorGroup;
use App\Modules\Monitoring\Models\ErrorLog;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Route;

beforeEach(function () {
    $this->completeSetup();
    config(['app.debug' => false]);
    Route::middleware('api')->post('/api/v1/__test/boom', static function () {
        throw new RuntimeException('Exploded while handling card 4111111111111111 for ali@example.test');
    });
});

it('shows users a friendly message with a reference and records the error', function () {
    Mail::fake();
    $this->superAdmin();
    $response = $this->postJson('/api/v1/__test/boom', ['password' => 'p@ss', 'note' => 'hello', 'api_key' => 'k-123'], ['X-Correlation-ID' => '01J8ZZZZZZZZZZZZZZZZZZZZZZ'])
        ->assertStatus(500)
        ->assertJsonStructure(['message', 'reference', 'correlation_id'])
        ->assertJsonMissingPath('exception')
        ->assertHeader('X-Correlation-ID', '01j8zzzzzzzzzzzzzzzzzzzzzz');
    $reference = $response->json('reference');
    expect($reference)->toMatch('/^E-[A-Z0-9]{10}$/')->and($response->getContent())->not->toContain('Exploded');

    $log = ErrorLog::query()->where('reference_code', $reference)->firstOrFail();
    expect($log->correlation_id)->toBe('01j8zzzzzzzzzzzzzzzzzzzzzz')
        ->and($log->message)->not->toContain('4111111111111111')->not->toContain('ali@example.test');
    $request = json_encode($log->request);
    expect($request)->not->toContain('p@ss')->not->toContain('k-123')->toContain('hello');
    Mail::assertQueued(ErrorAlertMail::class);
});

it('groups duplicates by fingerprint and counts occurrences', function () {
    Mail::fake();
    $this->postJson('/api/v1/__test/boom')->assertStatus(500);
    $this->postJson('/api/v1/__test/boom')->assertStatus(500);
    $this->postJson('/api/v1/__test/boom')->assertStatus(500);
    expect(ErrorGroup::query()->count())->toBe(1)->and(ErrorGroup::query()->first()->occurrences)->toBe(3);
});

it('writes every error to the secondary sink first', function () {
    $path = storage_path('logs/testing');
    $before = collect(glob($path.'/errors-*.log'))->sum(fn ($f) => count(file($f)));
    $reference = $this->postJson('/api/v1/__test/boom')->json('reference');
    $lines = collect(glob($path.'/errors-*.log'))->flatMap(fn ($f) => file($f));
    expect($lines->count())->toBeGreaterThan($before)
        ->and($lines->contains(fn ($l) => str_contains($l, $reference)))->toBeTrue();
});

it('lets error viewers triage groups and look up references', function () {
    $reference = $this->postJson('/api/v1/__test/boom')->json('reference');
    $admin = $this->superAdmin();
    $this->actingAs($admin, 'web');
    $group = $this->getJson('/api/v1/errors/groups?status=new')->assertOk()->json('data.0');
    $this->patchJson("/api/v1/errors/groups/{$group['id']}", ['status' => 'in_progress', 'assignee_user_id' => $admin->id, 'notes' => 'Looking'])->assertOk();
    $this->getJson("/api/v1/errors/reference/{$reference}")->assertOk();

    $this->flushSession();
    $this->actingAs($this->makeUser(), 'web');
    $this->getJson('/api/v1/errors/groups')->assertForbidden();
});
