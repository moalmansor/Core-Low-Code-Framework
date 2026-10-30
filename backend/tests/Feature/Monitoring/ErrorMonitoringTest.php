<?php

declare(strict_types=1);

use App\Modules\Core\Settings\SettingsService;
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
    app(SettingsService::class)->write('mail', 'host', 'smtp.example.test');
    app(SettingsService::class)->write('mail', 'from_address', 'noreply@example.test');
    $response = $this->postJson('/api/v1/__test/boom', ['password' => 'p@ss', 'note' => 'hello', 'api_key' => 'k-123'], ['X-Correlation-ID' => '01J8ZZZZZZZZZZZZZZZZZZZZZZ'])
        ->assertStatus(500)
        ->assertJsonStructure(['message', 'reference', 'correlation_id'])
        ->assertJsonMissingPath('exception')
        ->assertHeader('X-Correlation-ID', '01j8zzzzzzzzzzzzzzzzzzzzzz');
    $reference = $response->json('reference');
    expect($reference)->toMatch('/^E-[A-Z0-9]{10}$/')->and($response->getContent())->not->toContain('Exploded')
        ->and($response->json('message'))->toContain($reference); // shown wherever the message is shown

    $log = ErrorLog::query()->where('reference_code', $reference)->firstOrFail();
    expect($log->correlation_id)->toBe('01j8zzzzzzzzzzzzzzzzzzzzzz')
        ->and($log->message)->not->toContain('4111111111111111')->not->toContain('ali@example.test');
    $request = json_encode($log->request);
    expect($request)->not->toContain('p@ss')->not->toContain('k-123')->toContain('hello');
    Mail::assertQueued(ErrorAlertMail::class);
});

it('queues no alert while outgoing mail is not configured, and an alert never retries in a loop', function () {
    Mail::fake();
    $this->superAdmin();
    $this->postJson('/api/v1/__test/boom')->assertStatus(500);
    // The group is recorded for Error Monitoring, but a message that cannot be sent is not queued.
    expect(ErrorGroup::query()->count())->toBe(1);
    Mail::assertNothingQueued();
    // When one is queued, the mail server is asked again a bounded number of times, spaced out.
    $mail = new ErrorAlertMail(1, 'E-TEST');
    expect($mail->tries)->toBe(3)->and($mail->backoff)->toBe([60, 300]);
    expect(config('horizon.defaults.mail-1.tries'))->toBe(1);
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
