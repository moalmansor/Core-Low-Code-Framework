<?php

declare(strict_types=1);

use App\Modules\Audit\AuditWriter;
use App\Modules\Audit\ChainVerifier;
use App\Modules\Audit\Models\AuditLog;
use Illuminate\Support\Facades\DB;

beforeEach(fn () => $this->completeSetup());

it('hash-chains entries and verifies the chains', function () {
    $writer = app(AuditWriter::class);
    foreach (range(1, 20) as $i) {
        $writer->record('test.event', 'config', [['field_key' => 'x', 'old' => $i - 1, 'new' => $i]], 'thing', $i, ['n' => $i, 'text' => 'نص عربي']);
    }
    $result = app(ChainVerifier::class)->verify(full: true);
    expect($result['breaks'])->toBe([])->and($result['verified'])->toBeGreaterThanOrEqual(20);
});

it('detects tampering at the database level', function () {
    $writer = app(AuditWriter::class);
    foreach (range(1, 5) as $i) {
        $writer->record('test.event', 'config', null, 'thing', 7, ['n' => $i]);
    }
    $victim = AuditLog::query()->where('event', 'test.event')->orderBy('id')->skip(2)->first();
    DB::table('audit_logs')->where('id', $victim->id)->update(['event' => 'test.forged']);
    $breaks = app(ChainVerifier::class)->verify(full: true)['breaks'];
    expect($breaks)->toHaveCount(1)->and($breaks[0]['id'])->toBe($victim->id)->and($breaks[0]['reason'])->toBe('content hash mismatch');

    $this->artisan('audit:verify --full')->assertExitCode(1);
    expect(AuditLog::query()->where('event', 'audit.chain_break_detected')->exists())->toBeTrue();
});

it('detects deleted entries as a sequence gap', function () {
    $writer = app(AuditWriter::class);
    foreach (range(1, 4) as $i) {
        $writer->record('test.event', 'config', null, 'thing', 9, ['n' => $i]);
    }
    $victim = AuditLog::query()->where('event', 'test.event')->orderBy('id')->skip(1)->first();
    DB::table('audit_logs')->where('id', $victim->id)->delete();
    expect(app(ChainVerifier::class)->verify(full: true)['breaks'][0]['reason'])->toBe('sequence gap or reorder');
});

it('makes audit entries immutable through the application', function () {
    app(AuditWriter::class)->record('test.event', 'config');
    $entry = AuditLog::query()->latest('id')->first();
    expect(fn () => $entry->forceFill(['event' => 'x'])->save())->toThrow(LogicException::class)
        ->and(fn () => $entry->delete())->toThrow(LogicException::class);
});

it('masks secrets in recorded changes', function () {
    app(AuditWriter::class)->record('test.event', 'config', [['field_key' => 'mail.password', 'old' => 'a', 'new' => 'hunter2'], ['field_key' => 'api_token', 'old' => null, 'new' => 'tok']]);
    $raw = DB::table('audit_logs')->latest('id')->value('changes');
    expect($raw)->not->toContain('hunter2')->not->toContain('"tok"');
});

it('lets auditors filter and export, CSV-injection safe, and nobody else', function () {
    // A cell that starts with a formula character must be neutralised in the CSV.
    app(AuditWriter::class)->record('test.event', 'config', null, '=HYPERLINK("http://evil")', 1);
    $this->actingAs($this->superAdmin(), 'web');
    $this->getJson('/api/v1/audit?event=test.event')->assertOk()->assertJsonPath('data.0.event', 'test.event');
    $csv = $this->get('/api/v1/audit/export?event=test.event')->assertOk()->streamedContent();
    expect($csv)->toStartWith("\xEF\xBB\xBF")->toContain("'=HYPERLINK");
    $this->postJson('/api/v1/audit/verify-chain')->assertOk()->assertJsonPath('data.breaks', []);

    $this->flushSession();
    $this->actingAs($this->makeUser(), 'web');
    $this->getJson('/api/v1/audit')->assertForbidden();
    $this->get('/api/v1/audit/export')->assertForbidden();
});
