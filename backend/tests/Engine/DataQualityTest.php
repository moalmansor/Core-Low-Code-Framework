<?php

declare(strict_types=1);

use App\Modules\DataQuality\DuplicateFinder;
use App\Modules\Records\Runtime\RecycleBin;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

require_once __DIR__.'/helpers.php';

beforeEach(function () {
    $this->completeSetup();
    $this->admin = $this->superAdmin();
    $this->flushSession();
    $this->actingAs($this->admin, 'web');
});

afterEach(function () {
    Carbon::setTestNow();
});

/**
 * Contacts (name, email, code) and notes that link to a contact.
 *
 * @return array<string, mixed>
 */
function qualityForms(TestCase $t): array
{
    [, $contacts] = createForm($t, 'contacts');
    $f = [
        'name' => fieldDoc('name', 'text', null, ['validation' => ['required' => true]]),
        'email' => fieldDoc('email', 'text'),
        'code' => fieldDoc('code', 'text'),
        'city' => fieldDoc('city', 'text'),
    ];
    saveDraft($t, $contacts, [], array_values($f))->assertOk();
    expect(publish($t, $contacts)['status'])->toBe('applied');
    [, $notes] = createForm($t, 'notes');
    $relation = ['uuid' => uid(), 'key' => 'contact', 'type' => 'many_to_one', 'target' => $contacts, 'kind' => 'reference', 'onDelete' => 'restrict', 'display' => $f['name']['uuid'], 'value' => null, 'inverse' => null];
    $text = fieldDoc('text', 'text');
    $contact = fieldDoc('contact', 'lookup', null, ['relation' => $relation['uuid']]);
    saveDraft($t, $notes, [], [$text, $contact], [$relation])->assertOk();
    expect(publish($t, $notes)['status'])->toBe('applied');

    return ['contacts' => $contacts, 'notes' => $notes, 'f' => $f, 'contact' => $contact];
}

/** @return array<string, mixed> */
function dupRule(array $fields, array $extra = []): array
{
    return array_replace(['uuid' => uid(), 'i18n' => ['name' => ['en' => 'Same person'], 'message' => []], 'fields' => $fields, 'mode' => 'all', 'action' => 'warn', 'appliesOn' => 'both', 'active' => true], $extra);
}

function saveDupRules(TestCase $t, string $form, array $rules): TestResponse
{
    $hash = $t->getJson("/api/v1/forms/{$form}/duplicate-rules")->assertOk()->json('data.hash');

    return $t->putJson("/api/v1/forms/{$form}/duplicate-rules", ['rules' => $rules, 'base_hash' => $hash]);
}

function qualityTable(string $form): string
{
    return (string) DB::table('forms')->where('uuid', $form)->value('table_name');
}

it('compares text ignoring case, spacing, Arabic letter forms and diacritics, and fuzzily', function () {
    expect(DuplicateFinder::normalize('  Ahmed   AL-Saud '))->toBe('ahmed al saud')
        ->and(DuplicateFinder::normalize('أحْمَد'))->toBe(DuplicateFinder::normalize('احمد'))
        ->and(DuplicateFinder::normalize('مدرسة ١٢'))->toBe(DuplicateFinder::normalize('مدرسه 12'))
        ->and(DuplicateFinder::similarity('Jonathan Smith', 'Jonathon Smith'))->toBeGreaterThan(0.9)
        ->and(DuplicateFinder::similarity('Jonathan Smith', 'Maria Lopez'))->toBeLessThan(0.5);
});

it('warns about a likely duplicate while saving, lets the person save anyway, and blocks what a rule forbids', function () {
    $w = qualityForms($this);
    saveDupRules($this, $w['contacts'], [
        dupRule([['field' => $w['f']['name']['uuid'], 'method' => 'normalized'], ['field' => $w['f']['city']['uuid'], 'method' => 'fuzzy', 'threshold' => 0.8]]),
        dupRule([['field' => $w['f']['code']['uuid'], 'method' => 'exact']], ['i18n' => ['name' => ['en' => 'Same code'], 'message' => ['en' => 'This code is already used.']], 'action' => 'block']),
    ])->assertOk();
    $first = $this->postJson("/api/v1/r/{$w['contacts']}", ['values' => ['name' => 'أحمد علي', 'city' => 'Riyadh', 'code' => 'C-1']])->assertCreated()->json('data');

    $warned = $this->postJson("/api/v1/r/{$w['contacts']}", ['values' => ['name' => 'احمد  علي', 'city' => 'Riyad', 'code' => 'C-2']])->assertStatus(409);
    expect($warned->json('code'))->toBe('duplicate')->and($warned->json('blocking'))->toBeFalse()
        ->and($warned->json('duplicates.0.uuid'))->toBe($first['uuid'])->and($warned->json('rules'))->toBe(['Same person']);
    $this->postJson("/api/v1/r/{$w['contacts']}", ['values' => ['name' => 'احمد  علي', 'city' => 'Riyad', 'code' => 'C-2'], 'confirm_duplicates' => true])->assertCreated();

    // A blocking rule cannot be answered.
    $blocked = $this->postJson("/api/v1/r/{$w['contacts']}", ['values' => ['name' => 'Someone else', 'code' => 'C-1'], 'confirm_duplicates' => true])->assertStatus(409);
    expect($blocked->json('blocking'))->toBeTrue()->and($blocked->json('message'))->toBe('This code is already used.');
    // Editing into a duplicate is caught too; a different city is not similar enough.
    $other = $this->postJson("/api/v1/r/{$w['contacts']}", ['values' => ['name' => 'Ahmed Ali', 'city' => 'Jeddah', 'code' => 'C-3']])->assertCreated()->json('data');
    $this->patchJson("/api/v1/r/{$w['contacts']}/{$other['uuid']}", ['values' => ['code' => 'C-1'], 'row_version' => $other['row_version']])->assertStatus(409)->assertJsonPath('code', 'duplicate');
});

it('sweeps the existing records for groups of duplicates', function () {
    $w = qualityForms($this);
    foreach (['Sara Khan', 'sara  khan', 'Omar Said', 'SARA-KHAN', 'Omar Saeed', 'Lina'] as $name) {
        $this->postJson("/api/v1/r/{$w['contacts']}", ['values' => ['name' => $name]])->assertCreated();
    }
    saveDupRules($this, $w['contacts'], [dupRule([['field' => $w['f']['name']['uuid'], 'method' => 'normalized']])])->assertOk();
    $rule = $this->getJson("/api/v1/forms/{$w['contacts']}/duplicate-rules")->json('data.rules.0.uuid');
    $sweep = $this->postJson("/api/v1/duplicate-rules/{$rule}/sweep")->assertStatus(202)->json('data');
    $done = $this->getJson("/api/v1/data-operations/{$sweep['uuid']}")->assertOk()->json('data');
    expect($done['status'])->toBe('completed')->and($done['groups'])->toHaveCount(1)->and($done['groups'][0])->toHaveCount(3);

    // Fuzzily, the two Omars are one group too.
    saveDupRules($this, $w['contacts'], [dupRule([['field' => $w['f']['name']['uuid'], 'method' => 'fuzzy', 'threshold' => 0.75]])])->assertOk();
    $rule = $this->getJson("/api/v1/forms/{$w['contacts']}/duplicate-rules")->json('data.rules.0.uuid');
    $sweep = $this->postJson("/api/v1/duplicate-rules/{$rule}/sweep")->assertStatus(202)->json('data');
    expect(count($this->getJson("/api/v1/data-operations/{$sweep['uuid']}")->json('data.groups')))->toBe(2);
});

it('merges duplicates: chosen values, links moved to the survivor, the others in the recycle bin, a justified immutable history', function () {
    $w = qualityForms($this);
    $keep = $this->postJson("/api/v1/r/{$w['contacts']}", ['values' => ['name' => 'Huda', 'email' => null, 'city' => 'Dammam']])->assertCreated()->json('data');
    $dup = $this->postJson("/api/v1/r/{$w['contacts']}", ['values' => ['name' => 'Huda A.', 'email' => 'huda@example.test', 'city' => 'Khobar']])->assertCreated()->json('data');
    $note = $this->postJson("/api/v1/r/{$w['notes']}", ['values' => ['text' => 'Called', 'contact' => $dup['uuid']]])->assertCreated()->json('data');

    $preview = $this->postJson("/api/v1/r/{$w['contacts']}/merge/preview", ['records' => [$keep['uuid'], $dup['uuid']]])->assertOk()->json('data');
    expect($preview['differ'])->toBe(['name', 'email', 'city'])->and($preview['links'][$dup['uuid']])->toBe(1);
    $body = ['survivor' => $keep['uuid'], 'merged' => [$dup['uuid']], 'choices' => ['email' => $dup['uuid'], 'city' => $keep['uuid']],
        'versions' => [$keep['uuid'] => $keep['row_version'], $dup['uuid'] => $dup['row_version']]];
    $this->postJson("/api/v1/r/{$w['contacts']}/merge", $body)->assertStatus(422)->assertJsonPath('code', 'justification_required');
    $done = $this->postJson("/api/v1/r/{$w['contacts']}/merge", $body + ['justification' => ['reason_text' => 'Same customer registered twice']])->assertOk()->json('data');
    expect($done['merged'])->toBe(1)->and($done['repointed'])->toBe(['notes.contact' => 1]);

    $survivor = $this->getJson("/api/v1/r/{$w['contacts']}/{$keep['uuid']}")->json('data.values');
    expect($survivor['email'])->toBe('huda@example.test')->and($survivor['city'])->toBe('Dammam')
        ->and($this->getJson("/api/v1/r/{$w['notes']}/{$note['uuid']}")->json('data.values.contact'))->toBe($keep['uuid'])
        ->and(DB::table(qualityTable($w['contacts']))->where('uuid', $dup['uuid'])->value('deleted_at'))->not->toBeNull()
        ->and(DB::table('recycle_bin')->where('form_id', DB::table('forms')->where('uuid', $w['contacts'])->value('id'))->count())->toBe(1)
        ->and(DB::table('merge_history')->count())->toBe(1)
        ->and(DB::table('justifications')->where('context', 'merge')->count())->toBe(1);

    // A stale view of the records is refused, and merging needs its permission.
    $this->postJson("/api/v1/r/{$w['contacts']}/merge", $body + ['justification' => ['reason_text' => 'again']])->assertStatus(409);
    $this->flushSession();
    $this->actingAs($this->makeUser(['user']), 'web');
    $this->postJson("/api/v1/r/{$w['contacts']}/merge/preview", ['records' => [$keep['uuid'], $note['uuid']]])->assertStatus(403);
});

it('finds broken links and invalid records, and clears links through a normal save', function () {
    $w = qualityForms($this);
    $gone = $this->postJson("/api/v1/r/{$w['contacts']}", ['values' => ['name' => 'Gone']])->assertCreated()->json('data');
    $note = $this->postJson("/api/v1/r/{$w['notes']}", ['values' => ['text' => 'Orphan', 'contact' => $gone['uuid']]])->assertCreated()->json('data');
    $bad = $this->postJson("/api/v1/r/{$w['contacts']}", ['values' => ['name' => 'Valid']])->assertCreated()->json('data');
    // Changed behind the application's back: a linked contact deleted, a required name emptied.
    DB::table(qualityTable($w['contacts']))->where('uuid', $gone['uuid'])->update(['deleted_at' => now()]);
    DB::table(qualityTable($w['contacts']))->where('uuid', $bad['uuid'])->update(['name' => null]);

    $scan = $this->postJson("/api/v1/forms/{$w['notes']}/repair", ['type' => 'orphan_scan'])->assertStatus(202)->json('data');
    $found = $this->getJson("/api/v1/data-operations/{$scan['uuid']}")->assertOk()->json('data');
    expect($found['status'])->toBe('completed')->and($found['findings'])->toHaveCount(1)
        ->and($found['findings'][0])->toMatchArray(['record' => $note['uuid'], 'field' => 'contact', 'problem' => 'broken_link', 'fixable' => true]);
    $fix = $this->postJson("/api/v1/forms/{$w['notes']}/repair/clear-links", ['items' => [['record' => $note['uuid'], 'field' => 'contact']]])->assertOk()->json('data');
    expect($fix['fixed'])->toBe(1)->and($this->getJson("/api/v1/r/{$w['notes']}/{$note['uuid']}")->json('data.values.contact'))->toBeNull();

    $scan = $this->postJson("/api/v1/forms/{$w['contacts']}/repair", ['type' => 'validation_sweep'])->assertStatus(202)->json('data');
    $invalid = $this->getJson("/api/v1/data-operations/{$scan['uuid']}")->json('data');
    expect($invalid['findings'])->toHaveCount(1)->and($invalid['findings'][0])->toMatchArray(['record' => $bad['uuid'], 'field' => 'name', 'problem' => 'invalid', 'label' => 'Name']);
    expect($this->getJson("/api/v1/forms/{$w['contacts']}/repair")->json('data.validation_sweep.uuid'))->toBe($scan['uuid']);
});

it('keeps deleted records restorable for the retention period, then purges them', function () {
    $w = qualityForms($this);
    Carbon::setTestNow(Carbon::parse('2026-10-01 10:00:00', 'UTC'));
    $a = $this->postJson("/api/v1/r/{$w['contacts']}", ['values' => ['name' => 'Old']])->assertCreated()->json('data');
    $b = $this->postJson("/api/v1/r/{$w['contacts']}", ['values' => ['name' => 'Back']])->assertCreated()->json('data');
    foreach ([$a, $b] as $r) {
        $this->deleteJson("/api/v1/r/{$w['contacts']}/{$r['uuid']}", ['row_version' => $r['row_version']])->assertSuccessful();
    }
    $entry = DB::table('recycle_bin')->where('record_id', DB::table(qualityTable($w['contacts']))->where('uuid', $a['uuid'])->value('id'))->first();
    expect(Carbon::parse((string) $entry->purge_after)->toDateString())->toBe('2026-12-30')->and($entry->title_snapshot)->toBe('Old');
    $this->postJson("/api/v1/r/{$w['contacts']}/{$b['uuid']}/restore")->assertSuccessful();

    expect(app(RecycleBin::class)->purgeDue(Carbon::parse('2026-12-29', 'UTC')))->toBe(0)
        ->and(app(RecycleBin::class)->purgeDue(Carbon::parse('2026-12-31', 'UTC')))->toBe(1)
        ->and(DB::table(qualityTable($w['contacts']))->where('uuid', $a['uuid'])->exists())->toBeFalse()
        ->and(DB::table(qualityTable($w['contacts']))->where('uuid', $b['uuid'])->whereNull('deleted_at')->exists())->toBeTrue()
        ->and(DB::table('audit_logs')->where('event', 'record.purged')->count())->toBe(1);
});
