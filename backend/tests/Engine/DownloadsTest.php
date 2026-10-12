<?php

declare(strict_types=1);

use App\Modules\Core\Settings\SettingsService;
use App\Modules\Records\Models\StoredFile;
use App\Modules\Scheduling\ScheduledTasks;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use OpenSpout\Reader\XLSX\Reader as XlsxReader;
use Tests\TestCase;

require_once __DIR__.'/helpers.php';

beforeEach(function () {
    $this->completeSetup();
    $this->admin = $this->superAdmin();
    $this->flushSession();
    $this->actingAs($this->admin, 'web');
});

/**
 * Customers (name, tier, a sensitive note) ← tickets (subject, customer, lines repeater) ← tasks (title, ticket, hours).
 *
 * @return array<string, mixed>
 */
function downloadForms(TestCase $t): array
{
    [, $customers] = createForm($t, 'customers');
    $c = [
        'name' => fieldDoc('name', 'text'),
        'tier' => fieldDoc('tier', 'select', null, ['options' => ['source' => 'static', 'static' => staticOptions(['gold', 'silver'])]]),
        'note' => fieldDoc('note', 'text', null, ['flags' => ['sensitive' => true]]),
    ];
    saveDraft($t, $customers, [], array_values($c))->assertOk();
    expect(publish($t, $customers)['status'])->toBe('applied');

    [, $tickets] = createForm($t, 'tickets');
    $rel = ['uuid' => uid(), 'key' => 'customer', 'type' => 'many_to_one', 'target' => $customers, 'kind' => 'reference', 'onDelete' => 'restrict', 'display' => $c['name']['uuid'], 'value' => null, 'inverse' => null];
    $lines = groupDoc('lines', 'repeater');
    $tk = [
        'subject' => fieldDoc('subject', 'text', null, ['table' => ['filterable' => true]]),
        'opened' => fieldDoc('opened', 'date'),
        'customer' => fieldDoc('customer', 'lookup', null, ['relation' => $rel['uuid']]),
        'item' => fieldDoc('item', 'text', $lines['uuid']),
        'qty' => fieldDoc('qty', 'number', $lines['uuid']),
    ];
    saveDraft($t, $tickets, [$lines], array_values($tk), [$rel])->assertOk();
    expect(publish($t, $tickets)['status'])->toBe('applied');

    [, $tasks] = createForm($t, 'tasks');
    $rel2 = ['uuid' => uid(), 'key' => 'ticket', 'type' => 'many_to_one', 'target' => $tickets, 'kind' => 'reference', 'onDelete' => 'restrict', 'display' => $tk['subject']['uuid'], 'value' => null, 'inverse' => null];
    $ta = ['title' => fieldDoc('title', 'text'), 'hours' => fieldDoc('hours', 'number'), 'ticket' => fieldDoc('ticket', 'lookup', null, ['relation' => $rel2['uuid']])];
    saveDraft($t, $tasks, [], array_values($ta), [$rel2])->assertOk();
    expect(publish($t, $tasks)['status'])->toBe('applied');

    $acme = $t->postJson("/api/v1/r/{$customers}", ['values' => ['name' => 'Acme', 'tier' => 'gold', 'note' => 'Pays late']])->assertCreated()->json('data');
    $globex = $t->postJson("/api/v1/r/{$customers}", ['values' => ['name' => 'Globex', 'tier' => 'silver', 'note' => 'VIP']])->assertCreated()->json('data');
    $printer = $t->postJson("/api/v1/r/{$tickets}", ['values' => ['subject' => 'Printer', 'opened' => '2026-10-01', 'customer' => $acme['uuid'],
        'lines' => [['item' => 'Toner', 'qty' => 2], ['item' => 'Drum', 'qty' => 1]]]])->assertCreated()->json('data');
    $scanner = $t->postJson("/api/v1/r/{$tickets}", ['values' => ['subject' => 'Scanner', 'opened' => '2026-10-05', 'customer' => $globex['uuid']]])->assertCreated()->json('data');
    foreach ([['Replace toner', 2, $printer], ['Test print', 1, $printer], ['Clean glass', 3, $scanner]] as [$title, $hours, $ticket]) {
        $t->postJson("/api/v1/r/{$tasks}", ['values' => ['title' => $title, 'hours' => $hours, 'ticket' => $ticket['uuid']]])->assertCreated();
    }

    return ['customers' => $customers, 'tickets' => $tickets, 'tasks' => $tasks, 'c' => $c, 'tk' => $tk, 'printer' => $printer, 'scanner' => $scanner];
}

/** @return array<string, mixed> */
function profileDoc(string $key, array $columns, array $extra = []): array
{
    return array_replace([
        'key' => $key, 'i18n' => ['name' => ['en' => ucfirst($key), 'ar' => $key]], 'formats' => ['xlsx', 'csv', 'pdf'], 'fileName' => '{form} {date:yyyyMMdd}',
        'xlsx' => ['styledHeader' => true, 'freezeHeader' => true, 'titleRow' => true], 'csv' => ['delimiter' => ',', 'bom' => true], 'pdf' => ['orientation' => 'landscape', 'pageNumbers' => true],
        'applyUserFilters' => true, 'allowSelected' => true, 'availableIn' => ['table_toolbar', 'record_view'], 'parameters' => [], 'maxRows' => 0, 'active' => true,
        'columns' => $columns, 'filters' => [],
    ], $extra);
}

/** @return array<string, mixed> */
function col(array $path, array $extra = []): array
{
    return array_replace(['kind' => str_starts_with((string) end($path), '@') ? 'system' : 'field', 'path' => $path, 'i18n' => ['header' => []]], $extra);
}

function runDownload(TestCase $t, string $form, string $profile, string $format, array $extra = []): TestResponse
{
    return $t->postJson("/api/v1/r/{$form}/downloads/{$profile}", ['format' => $format] + $extra);
}

/** @return array<string, list<list<mixed>>> rows of each sheet by sheet name */
function readXlsx(string $fileUuid): array
{
    $file = StoredFile::query()->withoutGlobalScopes()->where('uuid', $fileUuid)->firstOrFail();
    $path = tempnam(sys_get_temp_dir(), 'xlsx');
    file_put_contents($path, Storage::disk($file->disk)->get($file->path));
    $reader = new XlsxReader;
    $reader->open($path);
    $out = [];
    foreach ($reader->getSheetIterator() as $sheet) {
        foreach ($sheet->getRowIterator() as $row) {
            $out[$sheet->getName()][] = $row->toArray();
        }
    }
    $reader->close();
    @unlink($path);

    return $out;
}

it('downloads linked data at several levels, in both directions, with one row per related record, aggregates and separate sheets', function () {
    $w = downloadForms($this);
    $tasksDir = '~'.$w['tasks'].'.ticket';
    $doc = profileDoc('full', [
        col(['@record_number']),
        col(['subject'], ['width' => 30]),
        col(['customer', 'name']),
        col(['customer', 'tier']),
        col([$tasksDir, 'title'], ['toMany' => 'flatten']),
        col([$tasksDir, 'hours'], ['toMany' => 'aggregate', 'aggregate' => 'sum', 'i18n' => ['header' => ['en' => 'Total hours']]]),
        col(['#lines', 'item'], ['toMany' => 'separate_sheet', 'sheet' => 'lines']),
        col(['#lines', 'qty'], ['toMany' => 'separate_sheet', 'sheet' => 'lines']),
        ['kind' => 'static', 'staticValue' => 'Q4', 'i18n' => ['header' => ['en' => 'Quarter']]],
        ['kind' => 'calculated', 'expression' => ['k' => 'ref', 'scope' => 'record', 'path' => ['subject']], 'i18n' => ['header' => ['en' => 'Again']]],
    ]);

    // A path that reaches many records needs a choice; an unknown path is refused.
    $bad = $doc;
    unset($bad['columns'][4]['toMany']);
    $bad['columns'][2]['path'] = ['customer', 'nope'];
    $this->postJson("/api/v1/forms/{$w['tickets']}/download-profiles", ['profile' => $bad])->assertStatus(422)
        ->assertJsonValidationErrors(['columns.4.toMany', 'columns.2.path']);

    // The tree offers forward links, child records and repeating sections.
    $tree = collect($this->getJson("/api/v1/forms/{$w['tickets']}/download-tree")->assertOk()->json('data'));
    expect($tree->where('kind', 'link')->pluck('path')->all())->toContain(['customer'])
        ->and($tree->where('kind', 'reverse')->pluck('path')->all())->toContain([$tasksDir])
        ->and($tree->where('kind', 'repeater')->pluck('path')->all())->toContain(['#lines']);
    $deeper = collect($this->getJson("/api/v1/forms/{$w['tickets']}/download-tree?path=".urlencode((string) json_encode(['customer'])))->json('data'));
    expect($deeper->where('kind', 'field')->pluck('label')->all())->toContain('Name', 'Tier');

    // Live preview before saving.
    $preview = $this->postJson("/api/v1/forms/{$w['tickets']}/download-preview", ['profile' => $doc])->assertOk()->json('data');
    expect($preview['headers'][''])->toBe(['Record number', 'Subject', 'Customer › Name', 'Customer › Tier', 'Tasks › Title', 'Total hours', 'Quarter', 'Again'])
        ->and($preview['rows'][''])->toHaveCount(3)
        ->and(collect($preview['rows'][''])->pluck(1)->sort()->values()->all())->toBe(['Printer', 'Printer', 'Scanner'])
        ->and($preview['rows']['lines'])->toHaveCount(2);

    $saved = $this->postJson("/api/v1/forms/{$w['tickets']}/download-profiles", ['profile' => $doc])->assertCreated()->json('data');
    $uuid = $saved['profile']['uuid'];
    expect($saved['profile']['sheetMode'])->toBe('per_related_form')
        ->and(DB::table('permissions')->where('key', "download.{$uuid}.use")->exists())->toBeTrue();

    $job = runDownload($this, $w['tickets'], $uuid, 'xlsx')->assertCreated()->json('data');
    expect($job['status'])->toBe('completed')->and($job['rows'])->toBe(3);
    $sheets = readXlsx($job['file']);
    [$main, $lines] = array_values($sheets);
    // The repeating section's rows get a sheet named after the section.
    expect(array_keys($sheets))->toBe(['Full', 'Lines']);
    // Title row, then the header, then one row per task (the ticket repeated).
    expect($main[1])->toBe(['Record number', 'Subject', 'Customer › Name', 'Customer › Tier', 'Tasks › Title', 'Total hours', 'Quarter', 'Again']);
    $byTask = collect(array_slice($main, 2))->keyBy(4);
    expect($byTask['Replace toner'][1])->toBe('Printer')->and($byTask['Replace toner'][2])->toBe('Acme')->and($byTask['Replace toner'][3])->toBe('Gold')
        ->and($byTask['Replace toner'][5])->toEqual(3)->and($byTask['Clean glass'][5])->toEqual(3)->and($byTask['Test print'][6])->toBe('Q4');
    expect(collect(array_slice($lines, 1))->map(static fn ($r) => [$r[1], (int) $r[2]])->all())->toBe([['Toner', 2], ['Drum', 1]]);

    // CSV has the byte-order mark and only the main sheet; PDF is a PDF.
    $csv = runDownload($this, $w['tickets'], $uuid, 'csv')->assertCreated()->json('data');
    $file = StoredFile::query()->withoutGlobalScopes()->where('uuid', $csv['file'])->first();
    $text = (string) Storage::disk($file->disk)->get($file->path);
    expect(str_starts_with($text, "\xEF\xBB\xBF"))->toBeTrue()->and($text)->toContain('Replace toner')->not->toContain('Toner,2')
        ->and($file->original_name)->toBe('Tickets '.Carbon::now(config('app.timezone'))->format('Ymd').'.csv');
    $pdf = runDownload($this, $w['tickets'], $uuid, 'pdf')->assertCreated()->json('data');
    $pdfFile = StoredFile::query()->withoutGlobalScopes()->where('uuid', $pdf['file'])->first();
    expect(str_starts_with((string) Storage::disk($pdfFile->disk)->get($pdfFile->path), '%PDF'))->toBeTrue();

    // From the record page: that record and its related data only.
    $one = runDownload($this, $w['tickets'], $uuid, 'xlsx', ['record' => $w['scanner']['uuid']])->assertCreated()->json('data');
    expect($one['rows'])->toBe(1);

    // Every download is audited.
    expect(DB::table('audit_logs')->where('event', 'download.completed')->count())->toBe(4);
});

it('gives each user only the columns, records and values they may see, at every level', function () {
    $w = downloadForms($this);
    $doc = profileDoc('secure', [col(['subject']), col(['customer', 'name']), col(['customer', 'tier']), col(['customer', 'note']),
        // A formula reading the tier must not reveal it to someone who cannot see the tier.
        ['kind' => 'calculated', 'expression' => ['k' => 'ref', 'scope' => 'record', 'path' => ['customer', 'tier']], 'i18n' => ['header' => ['en' => 'Tier again']]],
    ], ['availableIn' => ['table_toolbar']]);
    $uuid = $this->postJson("/api/v1/forms/{$w['tickets']}/download-profiles", ['profile' => $doc])->assertCreated()->json('data.profile.uuid');

    $role = DB::table('roles')->where('key', 'user')->first();
    foreach ([$w['tickets'], $w['customers']] as $form) {
        grantPermission((int) $role->id, "form.{$form}.view", 'role');
    }
    // The tier is hidden from the role; the note is sensitive.
    $this->putJson("/api/v1/forms/{$w['customers']}/access-rules", ['changes' => [
        ['target' => ['type' => 'field', 'uuid' => $w['c']['tier']['uuid']], 'subject' => ['type' => 'role', 'uuid' => $role->uuid], 'mode' => null, 'access' => 'hidden'],
    ]])->assertOk();
    // The builder previews what the role receives.
    $effective = $this->postJson("/api/v1/forms/{$w['tickets']}/download-effective", ['profile' => $doc, 'role' => $role->uuid])->assertOk()->json('data');
    expect($effective['columns'])->toBe(['Subject', 'Customer › Name', 'Customer › Note'])->and($effective['omitted'])->toBe(['Customer › Tier', 'Tier again']);
    // Only Globex is in scope for the role at the customer level.
    $hash = $this->getJson("/api/v1/forms/{$w['customers']}/record-access-rules")->json('data.hash');
    $this->putJson("/api/v1/forms/{$w['customers']}/record-access-rules", ['base_hash' => $hash, 'rules' => [
        ['uuid' => uid(), 'subject' => ['type' => 'everyone'], 'operation' => 'all', 'scope' => 'custom', 'effect' => 'allow',
            'condition' => ['k' => 'bin', 'op' => '=', 'a' => ['k' => 'ref', 'scope' => 'record', 'path' => ['name']], 'b' => ['k' => 'lit', 't' => 'text', 'v' => 'Globex']]],
    ]])->assertOk();

    $clerk = $this->makeUser(['user']);
    $this->flushSession();
    $this->actingAs($clerk, 'web');
    // Not granted: not offered, not runnable.
    expect($this->getJson("/api/v1/r/{$w['tickets']}/downloads?place=table_toolbar")->assertOk()->json('data'))->toBe([]);
    runDownload($this, $w['tickets'], $uuid, 'xlsx')->assertNotFound();

    grantPermission($clerk->id, "download.{$uuid}.use");
    expect(collect($this->getJson("/api/v1/r/{$w['tickets']}/downloads?place=table_toolbar")->json('data'))->pluck('name')->all())->toBe(['Secure']);
    expect($this->getJson("/api/v1/r/{$w['tickets']}/downloads?place=record_view")->json('data'))->toBe([]);
    $job = runDownload($this, $w['tickets'], $uuid, 'xlsx')->assertCreated()->json('data');
    expect($job['omitted'])->toBe(['Customer › Tier', 'Tier again']);
    $rows = array_values(readXlsx($job['file']))[0];
    $byTicket = collect(array_slice($rows, 2))->keyBy(0);
    // The hidden column is gone, the out-of-scope customer reads empty, the sensitive note is masked.
    expect($rows[1])->toBe(['Subject', 'Customer › Name', 'Customer › Note'])
        ->and($byTicket['Printer'][1])->toBe('')->and($byTicket['Scanner'][1])->toBe('Globex')->and($byTicket['Scanner'][2])->toBe('••••••');

    // An explicit grant shows sensitive values.
    grantPermission($clerk->id, 'field.'.$w['c']['note']['uuid'].'.view_sensitive');
    $job = runDownload($this, $w['tickets'], $uuid, 'xlsx')->assertCreated()->json('data');
    expect(collect(array_slice(array_values(readXlsx($job['file']))[0], 2))->keyBy(0)['Scanner'][2])->toBe('VIP');
});

it('applies fixed filters and parameters, and follows the table filters and selection when allowed', function () {
    $w = downloadForms($this);
    $doc = profileDoc('filtered', [col(['subject'])], [
        'fileName' => '{profile} {param.from}',
        'parameters' => [['key' => 'from', 'type' => 'date', 'required' => true, 'i18n' => ['label' => ['en' => 'From']]]],
        'filters' => [
            ['path' => ['opened'], 'operator' => 'gte', 'parameter' => 'from'],
            ['path' => ['customer', 'tier'], 'operator' => 'in', 'value' => ['gold', 'silver']],
        ],
    ]);
    $uuid = $this->postJson("/api/v1/forms/{$w['tickets']}/download-profiles", ['profile' => $doc])->assertCreated()->json('data.profile.uuid');

    runDownload($this, $w['tickets'], $uuid, 'csv')->assertStatus(422)->assertJsonValidationErrors(['parameters.from']);
    $job = runDownload($this, $w['tickets'], $uuid, 'csv', ['parameters' => ['from' => '2026-10-03']])->assertCreated()->json('data');
    expect($job['rows'])->toBe(1);
    $file = StoredFile::query()->withoutGlobalScopes()->where('uuid', $job['file'])->first();
    expect($file->original_name)->toBe('Filtered 2026-10-03.csv')->and((string) Storage::disk($file->disk)->get($file->path))->toContain('Scanner')->not->toContain('Printer');

    // The user's search and a selection narrow it further.
    expect(runDownload($this, $w['tickets'], $uuid, 'csv', ['parameters' => ['from' => '2026-09-01'], 'filters' => ['filter' => ['subject' => ['op' => 'equals', 'value' => 'Printer']]]])->json('data.rows'))->toBe(1)
        ->and(runDownload($this, $w['tickets'], $uuid, 'csv', ['parameters' => ['from' => '2026-09-01'], 'uuids' => [$w['scanner']['uuid']]])->json('data.rows'))->toBe(1);
});

it('runs large downloads in the background, notifies the user, and expires the file', function () {
    $w = downloadForms($this);
    app(SettingsService::class)->write('downloads', 'sync_rows', 1);
    $uuid = $this->postJson("/api/v1/forms/{$w['tickets']}/download-profiles", ['profile' => profileDoc('big', [col(['subject'])])])->assertCreated()->json('data.profile.uuid');
    $job = runDownload($this, $w['tickets'], $uuid, 'xlsx')->assertCreated()->json('data');
    // The test queue runs the job at once; the run still went through the queue and notified.
    $status = $this->getJson("/api/v1/download-jobs/{$job['uuid']}")->assertOk()->json('data');
    expect($status['status'])->toBe('completed')->and($status['file'])->not->toBeNull();
    expect(DB::table('in_app_notifications')->where('user_id', $this->admin->id)->where('type', 'download.ready')->count())->toBe(1);

    // Someone else cannot follow it.
    $this->flushSession();
    $this->actingAs($this->makeUser(['user']), 'web');
    $this->getJson("/api/v1/download-jobs/{$job['uuid']}")->assertNotFound();

    DB::table('download_jobs')->update(['expires_at' => Carbon::now('UTC')->subMinute()->format('Y-m-d H:i:s.u')]);
    $this->artisan('downloads:expire')->assertSuccessful();
    expect(DB::table('download_jobs')->value('status'))->toBe('expired')
        ->and(StoredFile::query()->withoutGlobalScopes()->where('uuid', $status['file'])->whereNull('deleted_at')->exists())->toBeFalse();
});

it('emails scheduled downloads to each recipient with their own data, and keeps personal profiles to their owner', function () {
    $w = downloadForms($this);
    $uuid = $this->postJson("/api/v1/forms/{$w['tickets']}/download-profiles", ['profile' => profileDoc('weekly', [col(['subject']), col(['customer', 'tier'])])])->assertCreated()->json('data.profile.uuid');
    $role = DB::table('roles')->where('key', 'user')->first();
    grantPermission((int) $role->id, "form.{$w['tickets']}.view", 'role');
    grantPermission((int) $role->id, "form.{$w['customers']}.view", 'role');
    $member = $this->makeUser(['user']);
    $outsider = $this->makeUser(['manager'], ['email' => 'outsider@example.test']);
    grantPermission($member->id, "download.{$uuid}.use");

    $this->postJson("/api/v1/download-profiles/{$uuid}/schedules", ['schedule' => ['frequency' => 'weekly', 'time' => '25:00', 'timezone' => 'Asia/Riyadh', 'format' => 'xlsx', 'recipients' => ['users' => []]]])
        ->assertStatus(422)->assertJsonValidationErrors(['time', 'recipients']);
    $s = $this->postJson("/api/v1/download-profiles/{$uuid}/schedules", ['schedule' => [
        'frequency' => 'weekly', 'time' => '07:30', 'weekday' => 0, 'timezone' => 'Asia/Riyadh', 'format' => 'xlsx',
        'recipients' => ['users' => [$outsider->uuid], 'roles' => [$role->uuid]],
    ]])->assertCreated()->json('data');
    expect($s['weekday'])->toBe(0)->and($s['time'])->toBe('07:30')->and($s['next_run_at'])->not->toBeNull();
    $task = DB::table('scheduled_tasks')->where('kind', 'download')->first();
    expect($task->cron_expression)->toBe('30 7 * * 0')->and($task->timezone)->toBe('Asia/Riyadh');

    // Due now: one run per recipient who may use the profile; the outsider is skipped.
    DB::table('scheduled_tasks')->update(['next_run_at' => Carbon::now('UTC')->subMinute()->format('Y-m-d H:i:s.u')]);
    expect(app(ScheduledTasks::class)->runDue())->toBe(1);
    $jobs = DB::table('download_jobs')->whereNotNull('download_schedule_id')->get();
    expect($jobs->pluck('user_id')->map(static fn ($v) => (int) $v)->all())->toBe([$member->id])
        ->and($jobs[0]->status)->toBe('completed');
    $mail = DB::table('email_queue')->where('source', 'download')->get();
    expect($mail)->toHaveCount(1)->and(json_decode((string) $mail[0]->to, true))->toBe([$member->email])
        ->and(json_decode((string) $mail[0]->attachment_file_ids, true))->toBe([(int) $jobs[0]->file_id]);
    $after = DB::table('scheduled_tasks')->first();
    expect($after->last_status)->toBe('succeeded')->and($after->next_run_at > Carbon::now('UTC')->format('Y-m-d H:i:s.u'))->toBeTrue();

    // A personal profile: only the owner's fields, only the owner receives it, invisible to others.
    $maker = $this->makeUser(['user']);
    grantPermission($maker->id, 'system.create_personal_download_profiles');
    $this->putJson("/api/v1/forms/{$w['customers']}/access-rules", ['changes' => [
        ['target' => ['type' => 'field', 'uuid' => $w['c']['tier']['uuid']], 'subject' => ['type' => 'role', 'uuid' => $role->uuid], 'mode' => null, 'access' => 'hidden'],
    ]])->assertOk();
    $this->flushSession();
    $this->actingAs($maker, 'web');
    $this->postJson("/api/v1/forms/{$w['tickets']}/download-profiles", ['profile' => profileDoc('mine', [col(['customer', 'tier'])]), 'personal' => true])
        ->assertStatus(422)->assertJsonValidationErrors(['columns.0.path']);
    $mine = $this->postJson("/api/v1/forms/{$w['tickets']}/download-profiles", ['profile' => profileDoc('mine', [col(['subject'])]), 'personal' => true])->assertCreated()->json('data.profile.uuid');
    $this->postJson("/api/v1/forms/{$w['tickets']}/download-profiles", ['profile' => profileDoc('shared', [col(['subject'])])])->assertForbidden();
    $ps = $this->postJson("/api/v1/download-profiles/{$mine}/schedules", ['schedule' => ['frequency' => 'daily', 'time' => '06:00', 'timezone' => 'UTC', 'format' => 'xlsx',
        'recipients' => ['users' => [$this->admin->uuid], 'roles' => [$role->uuid]]]])->assertCreated()->json('data');
    expect($ps['recipients'])->toBe(['users' => [strtolower($maker->uuid)], 'roles' => []]);
    expect(collect($this->getJson("/api/v1/r/{$w['tickets']}/downloads?place=table_toolbar")->json('data'))->pluck('name')->all())->toBe(['Mine']);
    $this->flushSession();
    $this->actingAs($member, 'web');
    expect(collect($this->getJson("/api/v1/r/{$w['tickets']}/downloads?place=table_toolbar")->json('data'))->pluck('name')->all())->toBe(['Weekly']);
    // Without any right to build profiles, the builder refuses before looking anything up.
    $this->getJson("/api/v1/download-profiles/{$mine}")->assertForbidden();
});

it('runs a profile from an action step for the record, under the runner\'s permissions', function () {
    $w = downloadForms($this);
    $uuid = $this->postJson("/api/v1/forms/{$w['tickets']}/download-profiles", ['profile' => profileDoc('sheet', [col(['subject']), col(['customer', 'name'])], ['formats' => ['xlsx']])])
        ->assertCreated()->json('data.profile.uuid');
    $action = ['uuid' => uid(), 'key' => 'export_one', 'kind' => 'custom', 'builtin' => null, 'i18n' => ['label' => ['en' => 'Export one'], 'confirmation' => [], 'success' => []],
        'placements' => ['view_page'], 'condition' => null, 'confirm' => false, 'runMode' => 'sync', 'queueThreshold' => null, 'maxRecords' => null,
        'justification' => 'not_required', 'icon' => null, 'color' => null, 'active' => true,
        'steps' => [['uuid' => uid(), 'type' => 'run_download', 'config' => ['profile' => $uuid, 'format' => 'pdf'], 'condition' => null, 'onFailure' => 'stop']]];
    $hash = $this->getJson("/api/v1/forms/{$w['tickets']}/actions")->json('data.hash');
    $this->putJson("/api/v1/forms/{$w['tickets']}/actions", ['actions' => [$action], 'base_hash' => $hash])->assertStatus(422)->assertJsonValidationErrors(['actions.0.steps.0.config.format']);
    $action['steps'][0]['config']['format'] = 'xlsx';
    $this->putJson("/api/v1/forms/{$w['tickets']}/actions", ['actions' => [$action], 'base_hash' => $hash])->assertOk();

    $run = $this->postJson("/api/v1/r/{$w['tickets']}/actions/{$action['uuid']}/run", ['items' => [['uuid' => $w['scanner']['uuid']]]])->assertCreated()->json('data');
    expect($run['status'])->toBe('completed')->and($run['outputs'][0]['type'])->toBe('download');
    $rows = array_values(readXlsx($run['outputs'][0]['file']))[0];
    expect(array_slice($rows, 2))->toBe([['Scanner', 'Globex']]);
});
