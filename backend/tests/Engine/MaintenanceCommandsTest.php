<?php

declare(strict_types=1);

use App\Modules\Records\Models\StoredFile;
use App\Modules\Schema\Models\SchemaSnapshot;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

require_once __DIR__.'/helpers.php';

beforeEach(function () {
    $this->completeSetup();
    $this->admin = $this->superAdmin();
    $this->flushSession();
    $this->actingAs($this->admin, 'web');
});

it('purges temporary uploads that no record adopted', function () {
    Storage::fake('private');
    $old = $this->post('/api/v1/files', ['file' => UploadedFile::fake()->createWithContent('a.txt', 'hello')], ['Accept' => 'application/json'])->assertCreated()->json('data.uuid');
    $fresh = $this->post('/api/v1/files', ['file' => UploadedFile::fake()->createWithContent('b.txt', 'world')], ['Accept' => 'application/json'])->assertCreated()->json('data.uuid');
    DB::table('files')->where('uuid', $old)->update(['created_at' => now()->subHours(30)]);
    $path = StoredFile::query()->where('uuid', $old)->value('path');

    $this->artisan('files:purge-temporary')->assertSuccessful();

    expect(DB::table('files')->where('uuid', $old)->exists())->toBeFalse()
        ->and(DB::table('files')->where('uuid', $fresh)->exists())->toBeTrue();
    Storage::disk('private')->assertMissing($path);
});

it('reconciles metadata with the physical schema and purges expired snapshots', function () {
    [, $form] = createForm($this, 'assets');
    saveDraft($this, $form, [], [fieldDoc('serial', 'text'), fieldDoc('model', 'text')])->assertOk();
    expect(publish($this, $form)['status'])->toBe('applied');

    $this->artisan('schema:reconcile --scheduled')->assertSuccessful();
    expect(DB::table('schema_reconciliation_reports')->orderByDesc('id')->value('status'))->toBe('clean');

    // Drift: a column removed outside the framework is reported.
    Schema::table('f_assets', fn ($t) => $t->dropColumn('model'));
    $this->artisan('schema:reconcile --scheduled')->assertFailed();
    $report = DB::table('schema_reconciliation_reports')->orderByDesc('id')->first();
    expect($report->status)->toBe('drift')->and((int) $report->difference_count)->toBeGreaterThan(0)
        ->and(json_decode((string) $report->differences, true)[0]['form_key'])->toBe('assets');

    // Snapshots past retention are deleted along with their files.
    $count = DB::table('schema_snapshots')->count();
    expect($count)->toBeGreaterThan(0);
    SchemaSnapshot::query()->update(['expires_at' => now()->subDay()]);
    $this->artisan('schema:purge-snapshots')->assertSuccessful();
    expect(DB::table('schema_snapshots')->count())->toBe(0);
});

it('lists forms as picker options for menu and numbering editors', function () {
    [, $form] = createForm($this, 'tickets');
    $this->getJson('/api/v1/form-options')->assertOk()->assertJsonPath('data.0.uuid', $form)->assertJsonPath('data.0.key', 'tickets');
    $user = $this->makeUser();
    $this->flushSession();
    $this->actingAs($user, 'web');
    $this->getJson('/api/v1/form-options')->assertForbidden();
});
