<?php

declare(strict_types=1);

use App\Infrastructure\Database\Contracts\DatabaseDriver;
use App\Modules\Audit\AuditWriter;
use App\Modules\Audit\ChainVerifier;
use App\Modules\Retention\Partitions;
use App\Modules\Retention\RetentionEngine;
use App\Modules\Retention\SubjectKeys;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

require_once __DIR__.'/helpers.php';

beforeEach(function () {
    // Engine tests truncate the audit log but keep the chain heads; start every chain afresh so it can be verified.
    DB::table('audit_logs')->delete();
    DB::table('audit_chain_heads')->update(['last_seq' => 0, 'last_hash' => str_repeat('0', 64), 'last_verified_seq' => 0]);
    $this->completeSetup();
    $this->admin = $this->superAdmin();
    $this->flushSession();
    $this->actingAs($this->admin, 'web');
    Storage::fake('archive');
});

afterEach(function () {
    Carbon::setTestNow();
});

/**
 * A "people" form: name, a personal-data e-mail, city, and a note that is not personal.
 *
 * @return array{form: string, table: string}
 */
function peopleForm(TestCase $t): array
{
    [, $form] = createForm($t, 'people');
    saveDraft($t, $form, [], [
        fieldDoc('name', 'text'),
        fieldDoc('email', 'email', null, ['flags' => ['personal' => true]]),
        fieldDoc('city', 'text'),
        fieldDoc('note', 'text'),
    ])->assertOk();
    expect(publish($t, $form)['status'])->toBe('applied');

    return ['form' => $form, 'table' => (string) DB::table('forms')->where('uuid', $form)->value('table_name')];
}

/** @param array<string, mixed> $values */
function person(TestCase $t, string $form, array $values): array
{
    return $t->postJson("/api/v1/r/{$form}", ['values' => $values])->assertCreated()->json('data');
}

/** Makes records two years old. @param list<string> $uuids */
function backdate(string $table, array $uuids): void
{
    DB::table($table)->whereIn('uuid', $uuids)->update(['created_at' => Carbon::now('UTC')->subYears(2)->format('Y-m-d H:i:s.u')]);
}

/** @param array<string, mixed> $policy */
function savePolicy(TestCase $t, array $policy): array
{
    return $t->postJson('/api/v1/retention/policies', ['policy' => array_replace([
        'name' => ['en' => 'Keep for a year', 'ar' => 'الاحتفاظ لسنة'], 'retention_days' => 365, 'date_basis' => 'created_at',
        'schedule' => ['cron' => '30 2 * * *', 'timezone' => 'UTC'], 'active' => true,
    ], $policy)])->assertCreated()->json('data');
}

it('partitions the audit and error logs by month and keeps twelve months ahead', function () {
    $partitions = app(Partitions::class);
    $driver = app(DatabaseDriver::class);
    foreach (Partitions::TABLES as $table) {
        expect($driver->timePartitionBounds($table))->toHaveCount(13);
    }
    Carbon::setTestNow(Carbon::now('UTC')->startOfMonth()->addMonths(2)->addDays(3));
    expect($partitions->maintain())->toBe(['audit_logs' => 2, 'error_logs' => 2])
        ->and($partitions->maintain())->toBe(['audit_logs' => 0, 'error_logs' => 0]);
    $bounds = $driver->timePartitionBounds('audit_logs');
    expect(end($bounds))->toBe(Carbon::now('UTC')->startOfMonth()->addMonths(13)->toDateString());
    Carbon::setTestNow();
    app(AuditWriter::class)->record('test.partitioned', 'operations');
    expect(array_sum(array_column($partitions->describe('audit_logs'), 'rows')))->toBe(DB::table('audit_logs')->count());
});

it('archives old records to cold storage, skips those under legal hold, and restores one under its own id', function () {
    $p = peopleForm($this);
    $old = person($this, $p['form'], ['name' => 'Old Person', 'email' => 'old@example.test', 'city' => 'Jeddah']);
    $held = person($this, $p['form'], ['name' => 'Held Person', 'email' => 'held@example.test', 'city' => 'Riyadh']);
    backdate($p['table'], [$old['uuid'], $held['uuid']]);
    $recent = person($this, $p['form'], ['name' => 'Recent', 'email' => 'new@example.test']);

    $this->postJson('/api/v1/legal-holds', ['form' => $p['form'], 'record' => $held['uuid'], 'reason' => 'Litigation 2026-17'])->assertCreated();
    expect((bool) DB::table($p['table'])->where('uuid', $held['uuid'])->value('legal_hold'))->toBeTrue();
    $this->deleteJson("/api/v1/r/{$p['form']}/{$held['uuid']}", ['row_version' => $held['row_version']])->assertStatus(423);

    $policy = savePolicy($this, ['data_class' => 'records', 'form' => $p['form'], 'end_action' => 'archive']);
    expect(DB::table('scheduled_tasks')->where('kind', 'retention')->where('owner_id', DB::table('retention_policies')->where('uuid', $policy['uuid'])->value('id'))->exists())->toBeTrue();
    $this->postJson("/api/v1/retention/policies/{$policy['uuid']}/run")->assertStatus(202);

    $run = $this->getJson('/api/v1/retention/runs')->assertOk()->json('data.0');
    expect($run)->toMatchArray(['status' => 'succeeded', 'archived' => 1, 'deleted' => 0, 'skipped_legal_hold' => 1]);
    expect(DB::table($p['table'])->pluck('uuid')->map(fn ($u) => strtolower((string) $u))->sort()->values()->all())
        ->toEqualCanonicalizing([$held['uuid'], $recent['uuid']]);
    $archived = DB::table('archived_records')->where('source_table', $p['table'])->first();
    expect($archived)->not->toBeNull();
    Storage::disk('archive')->assertExists($archived->path);
    expect(DB::table('audit_logs')->where('event', 'record.archived')->exists())->toBeTrue()
        ->and(DB::table('audit_logs')->where('event', 'retention.run')->exists())->toBeTrue();

    $list = $this->getJson('/api/v1/retention/archive')->assertOk()->json('data');
    expect($list[0]['restorable'])->toBeTrue();
    $this->postJson("/api/v1/retention/archive/{$archived->id}/restore")->assertOk();
    $back = $this->getJson("/api/v1/r/{$p['form']}/{$old['uuid']}")->assertOk()->json('data');
    expect($back['values'])->toMatchArray(['name' => 'Old Person', 'email' => 'old@example.test', 'city' => 'Jeddah'])
        ->and((int) DB::table($p['table'])->where('uuid', $old['uuid'])->value('id'))->toBe((int) $archived->record_id);
    $this->postJson("/api/v1/retention/archive/{$archived->id}/restore")->assertUnprocessable();
});

it('refuses to restore an archive that was altered in cold storage', function () {
    $p = peopleForm($this);
    $old = person($this, $p['form'], ['name' => 'Old Person']);
    backdate($p['table'], [$old['uuid']]);
    $policy = savePolicy($this, ['data_class' => 'records', 'form' => $p['form'], 'end_action' => 'archive']);
    $this->postJson("/api/v1/retention/policies/{$policy['uuid']}/run")->assertStatus(202);
    $archived = DB::table('archived_records')->first();
    Storage::disk('archive')->put($archived->path, str_replace('Old Person', 'Someone Else', (string) Storage::disk('archive')->get($archived->path)));
    $this->postJson("/api/v1/retention/archive/{$archived->id}/restore")->assertUnprocessable()->assertJsonValidationErrors('archive');
});

it('holds every record matching a condition until the hold is lifted, with the reason recorded both ways', function () {
    $p = peopleForm($this);
    $a = person($this, $p['form'], ['name' => 'A', 'city' => 'Riyadh']);
    $b = person($this, $p['form'], ['name' => 'B', 'city' => 'Dammam']);
    $condition = ['k' => 'bin', 'op' => '=', 'a' => ['k' => 'ref', 'scope' => 'record', 'path' => ['city']], 'b' => ['k' => 'lit', 't' => 'text', 'v' => 'Riyadh']];
    $hold = $this->postJson('/api/v1/legal-holds', ['form' => $p['form'], 'condition' => $condition, 'reason' => 'Audit of the Riyadh branch'])->assertCreated()->json('data');
    expect($hold['held'])->toBe(1);
    $flags = DB::table($p['table'])->pluck('legal_hold', 'uuid')->mapWithKeys(fn ($v, $k) => [strtolower((string) $k) => (bool) $v]);
    expect($flags[$a['uuid']])->toBeTrue()->and($flags[$b['uuid']])->toBeFalse();
    // A record that comes to match is covered by the nightly refresh.
    $c = person($this, $p['form'], ['name' => 'C', 'city' => 'Riyadh']);
    $this->artisan('retention:maintain')->assertSuccessful();
    expect((bool) DB::table($p['table'])->where('uuid', $c['uuid'])->value('legal_hold'))->toBeTrue();

    $this->postJson("/api/v1/legal-holds/{$hold['uuid']}/lift", ['reason' => 'Audit closed'])->assertOk();
    expect(DB::table($p['table'])->where('legal_hold', true)->count())->toBe(0);
    $listed = $this->getJson('/api/v1/legal-holds')->assertOk()->json('data.0');
    expect($listed)->toMatchArray(['reason' => 'Audit of the Riyadh branch', 'lift_reason' => 'Audit closed']);
    $this->postJson("/api/v1/legal-holds/{$hold['uuid']}/lift", ['reason' => 'Again'])->assertUnprocessable();
});

it('removes old log rows, exporting them first when the policy says so', function () {
    Carbon::setTestNow(Carbon::parse('2024-03-01 08:00:00', 'UTC'));
    $id = DB::table('email_queue')->insertGetId([
        'uuid' => uid(), 'organization_id' => $this->admin->organization_id, 'created_at' => now(), 'updated_at' => now(), 'source' => 'rule', 'locale' => 'en',
        'to' => json_encode(['a@example.test']), 'subject' => 'Old', 'body_html' => '<p>x</p>', 'status' => 'sent', 'attempts' => 1, 'max_attempts' => 5, 'correlation_id' => uid(),
    ]);
    DB::table('email_queue')->insert([
        'uuid' => uid(), 'organization_id' => $this->admin->organization_id, 'created_at' => now(), 'updated_at' => now(), 'source' => 'rule', 'locale' => 'en',
        'to' => json_encode(['a@example.test']), 'subject' => 'Resent', 'body_html' => '<p>x</p>', 'status' => 'pending', 'attempts' => 0, 'max_attempts' => 5, 'correlation_id' => uid(), 'resent_from_id' => $id,
    ]);
    Carbon::setTestNow();
    $policy = savePolicy($this, ['data_class' => 'email_logs', 'end_action' => 'export_then_delete', 'retention_days' => 90]);
    $this->postJson("/api/v1/retention/policies/{$policy['uuid']}/run")->assertStatus(202);
    $run = $this->getJson('/api/v1/retention/runs')->json('data.0');
    expect($run)->toMatchArray(['status' => 'succeeded', 'deleted' => 1, 'has_export' => true]);
    expect(DB::table('email_queue')->where('id', $id)->exists())->toBeFalse()
        ->and(DB::table('email_queue')->where('subject', 'Resent')->value('resent_from_id'))->toBeNull();
    $export = $this->get("/api/v1/retention/runs/{$run['uuid']}/export")->assertOk()->streamedContent();
    expect($export)->toContain('"subject":"Old"');
});

it('only archives audit logs, as a verified monthly copy, and leaves every entry and the chain in place', function () {
    $this->postJson('/api/v1/retention/policies', ['policy' => [
        'name' => ['en' => 'Audit'], 'data_class' => 'audit_logs', 'end_action' => 'delete', 'retention_days' => 30, 'schedule' => ['cron' => '0 3 * * *'],
    ]])->assertUnprocessable()->assertJsonValidationErrors('end_action');
    Carbon::setTestNow(Carbon::parse('2025-01-15 10:00:00', 'UTC'));
    app(AuditWriter::class)->record('test.old_entry', 'operations', meta: ['n' => 1]);
    app(AuditWriter::class)->record('test.old_entry', 'operations', meta: ['n' => 2]);
    Carbon::setTestNow();
    $before = DB::table('audit_logs')->count();
    $policy = savePolicy($this, ['data_class' => 'audit_logs', 'end_action' => 'archive', 'retention_days' => 180]);
    $this->postJson("/api/v1/retention/policies/{$policy['uuid']}/run")->assertStatus(202);
    $copy = DB::table('archived_records')->where('source_table', 'audit_logs')->where('record_id', 20250201)->first();
    expect($copy)->not->toBeNull();
    $lines = array_filter(explode("\n", (string) Storage::disk('archive')->get($copy->path)));
    expect($lines)->toHaveCount(2)->and(hash('sha256', (string) Storage::disk('archive')->get($copy->path)))->toBe($copy->payload_hash);
    $proof = json_decode((string) Storage::disk('archive')->get(substr($copy->path, 0, -6).'.proof.json'), true);
    expect($proof['entries'])->toBe(2)->and($proof['sha256'])->toBe($copy->payload_hash);
    expect(DB::table('audit_logs')->count())->toBeGreaterThanOrEqual($before)
        ->and(app(ChainVerifier::class)->verify(true)['breaks'])->toBe([]);
    // A month is copied once.
    $this->postJson("/api/v1/retention/policies/{$policy['uuid']}/run")->assertStatus(202);
    expect(DB::table('archived_records')->where('source_table', 'audit_logs')->where('record_id', 20250201)->count())->toBe(1);
});

it('seals personal-data values in the audit trail and shreds them on anonymization, keeping the chain intact', function () {
    $p = peopleForm($this);
    $r = person($this, $p['form'], ['name' => 'Sara', 'email' => 'sara@example.test', 'note' => 'VIP']);
    $this->patchJson("/api/v1/r/{$p['form']}/{$r['uuid']}", ['row_version' => $r['row_version'], 'values' => ['email' => 'sara.k@example.test']])->assertOk();

    $raw = (string) DB::table('audit_logs')->where('event', 'record.updated')->where('form_id', DB::table('forms')->where('uuid', $p['form'])->value('id'))->value('changes');
    expect($raw)->not->toContain('sara')->and($raw)->toContain(SubjectKeys::PREFIX);
    $history = $this->getJson("/api/v1/r/{$p['form']}/{$r['uuid']}/history")->assertOk()->json('data');
    expect(collect($history)->firstWhere('event', 'record.updated')['changes'][0])->toMatchArray(['field_key' => 'email', 'old' => 'sara@example.test', 'new' => 'sara.k@example.test']);
    $viewer = $this->getJson('/api/v1/audit?event=record.updated')->assertOk()->json('data.0.changes.0');
    expect($viewer['new'])->toBe(SubjectKeys::PERSONAL);

    $req = $this->postJson('/api/v1/personal-data-requests', ['type' => 'anonymize', 'subject' => ['email' => 'sara.k@example.test']])->assertCreated()->json('data');
    $found = $this->getJson("/api/v1/personal-data-requests/{$req['uuid']}")->assertOk()->json('data');
    expect($found['status'])->toBe('awaiting_review')->and($found['findings']['forms'][0])->toMatchArray(['key' => 'people', 'fields' => ['email']]);
    $this->postJson("/api/v1/personal-data-requests/{$req['uuid']}/execute", ['reason' => 'Erasure request received by e-mail'])->assertUnprocessable()->assertJsonValidationErrors('confirmation_code');
    $this->postJson("/api/v1/personal-data-requests/{$req['uuid']}/execute", ['reason' => 'Erasure request received by e-mail', 'confirmation_code' => $this->totp()])->assertOk()->assertJsonPath('data.status', 'completed');

    $after = $this->getJson("/api/v1/r/{$p['form']}/{$r['uuid']}")->assertOk()->json('data.values');
    expect($after['email'])->toStartWith('anonymized-')->and($after['name'])->toBe('Sara')->and($after['note'])->toBe('VIP');
    $history = $this->getJson("/api/v1/r/{$p['form']}/{$r['uuid']}/history")->assertOk()->json('data');
    expect(collect($history)->firstWhere('event', 'record.updated')['changes'][0])->toMatchArray(['old' => SubjectKeys::ERASED, 'new' => SubjectKeys::ERASED]);
    expect(app(ChainVerifier::class)->verify(true)['breaks'])->toBe([])
        ->and(DB::table('justifications')->where('context', 'personal_data')->exists())->toBeTrue();
});

it('locates, exports and deletes a person\'s data, and never touches records under legal hold', function () {
    $p = peopleForm($this);
    $mine = person($this, $p['form'], ['name' => 'Omar', 'email' => 'omar@example.test']);
    $held = person($this, $p['form'], ['name' => 'Omar again', 'email' => 'OMAR@example.test']);
    person($this, $p['form'], ['name' => 'Someone', 'email' => 'other@example.test']);
    $this->postJson('/api/v1/legal-holds', ['form' => $p['form'], 'record' => $held['uuid'], 'reason' => 'Open claim'])->assertCreated();

    $export = $this->postJson('/api/v1/personal-data-requests', ['type' => 'export', 'subject' => ['email' => 'omar@example.test']])->assertCreated()->json('data');
    $found = $this->getJson("/api/v1/personal-data-requests/{$export['uuid']}")->json('data.findings.forms.0');
    expect($found['records'])->toHaveCount(2)->and($found['held'])->toBe(1);
    $this->postJson("/api/v1/personal-data-requests/{$export['uuid']}/execute", ['reason' => 'Subject access request'])->assertOk();
    $bundle = json_decode($this->get("/api/v1/personal-data-requests/{$export['uuid']}/file")->assertOk()->streamedContent(), true);
    expect(collect($bundle['forms'][0]['records'])->pluck('uuid')->all())->toEqualCanonicalizing([$mine['uuid'], $held['uuid']]);

    $del = $this->postJson('/api/v1/personal-data-requests', ['type' => 'delete', 'subject' => ['email' => 'omar@example.test']])->assertCreated()->json('data');
    $done = $this->postJson("/api/v1/personal-data-requests/{$del['uuid']}/execute", ['reason' => 'Erasure request, verified', 'confirmation_code' => $this->totp()])->assertOk()->json('data');
    expect($done['findings']['result'])->toEqual(['skipped_legal_hold' => 1, 'records' => 1]);
    expect(DB::table($p['table'])->where('uuid', $mine['uuid'])->exists())->toBeFalse()
        ->and(DB::table($p['table'])->where('uuid', $held['uuid'])->exists())->toBeTrue()
        ->and(DB::table('subject_keys')->whereNotNull('destroyed_at')->exists())->toBeTrue();

    $reject = $this->postJson('/api/v1/personal-data-requests', ['type' => 'delete', 'subject' => ['identifier' => 'X-1']])->assertCreated()->json('data');
    $this->postJson("/api/v1/personal-data-requests/{$reject['uuid']}/reject", ['reason' => 'Identity could not be verified'])->assertOk()->assertJsonPath('data.status', 'rejected');
});

it('refuses files past a storage quota\'s hard limit and reports usage by form and by user', function () {
    $p = peopleForm($this);
    $hash = $this->getJson('/api/v1/retention/quotas')->assertOk()->json('data.hash');
    $this->putJson('/api/v1/retention/quotas', ['base_hash' => $hash, 'quotas' => [['scope' => 'form', 'target' => $p['form'], 'warn_mb' => 2, 'limit_mb' => 1]]])
        ->assertUnprocessable()->assertJsonValidationErrors('quotas.0.warn_mb');
    $this->putJson('/api/v1/retention/quotas', ['base_hash' => $hash, 'quotas' => [['scope' => 'organization', 'warn_mb' => 1, 'limit_mb' => 1]]])->assertOk();

    $this->post('/api/v1/files', ['file' => UploadedFile::fake()->create('big.pdf', 1500, 'application/pdf')], ['Accept' => 'application/json'])
        ->assertUnprocessable()->assertJsonValidationErrors('file');
    $quota = $this->getJson('/api/v1/retention/quotas')->json('data.quotas.0');
    expect($quota)->toMatchArray(['scope' => 'organization', 'limit_mb' => 1, 'state' => 'ok']);
    $usage = $this->getJson('/api/v1/retention/usage')->assertOk()->json('data');
    expect($usage)->toHaveKeys(['forms', 'users', 'total']);
    $storage = $this->getJson('/api/v1/retention/storage')->assertOk()->json('data');
    expect(collect($storage['partitions'])->pluck('table')->all())->toBe(['audit_logs', 'error_logs'])->and($storage['tables'])->not->toBeEmpty();
});

it('lets nobody without the compliance permissions near retention, holds or personal data', function () {
    $user = $this->makeUser(['user']);
    $this->flushSession();
    $this->actingAs($user, 'web');
    $this->getJson('/api/v1/retention/policies')->assertForbidden();
    $this->getJson('/api/v1/retention/archive')->assertForbidden();
    $this->getJson('/api/v1/legal-holds')->assertForbidden();
    $this->postJson('/api/v1/personal-data-requests', ['type' => 'locate', 'subject' => ['email' => 'a@example.test']])->assertForbidden();
    expect(collect($this->getJson('/api/v1/admin/console')->json('data.areas'))->pluck('key')->all())->not->toContain('retention');
});

it('lists the data classes and actions a policy may use', function () {
    $opts = $this->getJson('/api/v1/retention/options')->assertOk()->json('data');
    expect($opts['data_classes'])->toBe(RetentionEngine::DATA_CLASSES)->and($opts['archive_disks'])->toBe(['archive']);
});
