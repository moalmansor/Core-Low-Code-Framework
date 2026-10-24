<?php

declare(strict_types=1);

use App\Modules\Platform\Packages\PackageService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

require_once __DIR__.'/helpers.php';

beforeEach(function () {
    $this->completeSetup();
    $this->admin = $this->superAdmin();
    $this->flushSession();
    $this->actingAs($this->admin, 'web');
});

/**
 * A request form with a workflow, a role grant, an e-mail template used by a
 * notification rule, and a shared download profile.
 *
 * @return array<string, mixed>
 */
function packagedSetup(TestCase $t): array
{
    $wf = buildWorkflowForm($t);
    $form = $wf['form'];
    $app = DB::table('applications')->where('id', DB::table('forms')->where('uuid', $form)->value('application_id'))->value('uuid');
    $role = $t->postJson('/api/v1/roles', ['key' => 'reviewers', 'name' => ['en' => 'Reviewers', 'ar' => 'المراجعون']])->assertCreated()->json('data.uuid');
    grantPermission((int) DB::table('roles')->where('uuid', $role)->value('id'), "form.{$form}.view", 'role');
    $template = $t->postJson('/api/v1/notifications/templates', [
        'key' => 'leave_moved', 'form' => $form, 'subject' => ['en' => 'Request {{ subject }}', 'ar' => 'الطلب {{ subject }}'],
        'design' => ['blocks' => [['type' => 'text', 'text' => ['en' => 'Moved']]]],
    ])->assertCreated()->json('data.uuid');
    $hash = $t->getJson("/api/v1/forms/{$form}/notification-rules")->assertOk()->json('data.hash');
    $t->putJson("/api/v1/forms/{$form}/notification-rules", ['base_hash' => $hash, 'rules' => [[
        'uuid' => uid(), 'key' => 'on_create', 'name' => ['en' => 'Notify'], 'trigger' => 'record_created',
        'fromStatus' => null, 'toStatus' => null, 'field' => null, 'condition' => null, 'schedule' => null, 'template' => $template,
        'channels' => ['in_app', 'email'], 'recipients' => ['to' => [['type' => 'creator', 'ref' => null]], 'cc' => [], 'bcc' => []],
        'attachments' => null, 'delayMinutes' => null, 'respectPreferences' => true, 'active' => true,
    ]]])->assertOk();
    $profile = $t->postJson("/api/v1/forms/{$form}/download-profiles", ['profile' => [
        'key' => 'leave_list', 'i18n' => ['name' => ['en' => 'Leave list', 'ar' => 'قائمة الإجازات']], 'formats' => ['xlsx', 'csv'], 'fileName' => '{form} {date:yyyyMMdd}',
        'xlsx' => ['styledHeader' => true, 'freezeHeader' => true, 'titleRow' => true], 'csv' => ['delimiter' => ',', 'bom' => true], 'pdf' => ['orientation' => 'landscape', 'pageNumbers' => true],
        'applyUserFilters' => true, 'allowSelected' => true, 'availableIn' => ['table_toolbar'], 'parameters' => [], 'maxRows' => 0, 'active' => true,
        'columns' => [['kind' => 'field', 'path' => ['subject'], 'i18n' => ['header' => []]]], 'filters' => [],
    ]])->assertCreated()->json('data.profile.uuid');

    return $wf + compact('app', 'role', 'template', 'profile');
}

/** @return array<string, mixed> the exported package, downloaded as a file */
function exportPackage(TestCase $t, array $objects): array
{
    $made = $t->postJson('/api/v1/packages/export', ['name' => 'Leave', 'objects' => $objects])->assertCreated()->json('data');
    $json = $t->get("/api/v1/packages/{$made['uuid']}/download")->assertOk()->streamedContent();

    return json_decode($json, true);
}

function uploadPackage(TestCase $t, array $package, string $path = '/api/v1/packages/import'): TestResponse
{
    return $t->post($path, ['file' => UploadedFile::fake()->createWithContent('package.json', (string) json_encode($package))], ['Accept' => 'application/json']);
}

/** Sets the package's checksum (and object hashes) as an export would. */
function sealPackage(array $package): array
{
    foreach ($package['objects'] as &$o) {
        $o['hash'] = PackageService::hash($o['content']);
    }
    unset($o);
    $package['checksum'] = PackageService::checksum($package);

    return $package;
}

/** The same package as another environment would have it: every object and element has its own uuids. */
function asOtherEnvironment(array $package): array
{
    $subjects = array_keys($package['subjects']);
    $map = [];
    $walk = static function (mixed $v) use (&$walk, &$map, $subjects): mixed {
        if (is_string($v) && Str::isUuid($v) && ! in_array(strtolower($v), $subjects, true)) {
            return $map[strtolower($v)] ??= (string) Str::uuid7();
        }

        return is_array($v) ? array_combine(array_map(static fn ($k) => is_string($k) && Str::isUuid($k) && ! in_array($k, $subjects, true) ? ($map[strtolower($k)] ??= (string) Str::uuid7()) : $k, array_keys($v)), array_map($walk, $v)) : $v;
    };
    $package['objects'] = $walk($package['objects']);

    return sealPackage($package);
}

function waitApplied(TestCase $t, string $uuid): array
{
    return $t->getJson("/api/v1/packages/{$uuid}")->assertOk()->json('data');
}

it('exports the chosen objects with what they depend on, and refuses a file that was changed', function () {
    $s = packagedSetup($this);
    $package = exportPackage($this, [['type' => 'form', 'uuid' => $s['form']], ['type' => 'download_profile', 'uuid' => $s['profile']]]);

    expect($package['format'])->toBe('lcf-package/1')
        ->and(collect($package['objects'])->pluck('type')->all())->toBe(['application', 'form', 'email_template', 'download_profile'])
        ->and($package['subjects'][$s['role']])->toBe(['type' => 'role', 'key' => 'reviewers']);
    $form = collect($package['objects'])->firstWhere('type', 'form');
    expect($form['content']['workflow']['statuses'])->toHaveCount(3)
        ->and($form['content']['notifications'][0]['template'])->toBe($s['template'])
        ->and(collect($form['content']['access']['grants'])->where('ability', 'view')->pluck('subject')->all())->toContain($s['role'])
        ->and($form['content']['document']['form'])->not->toHaveKey('table');

    // Compared with the environment it came from, everything is the same.
    $report = uploadPackage($this, $package, '/api/v1/packages/compare')->assertCreated()->json('data');
    expect($report['ambiguous'])->toBeFalse()->and($report['counts'])->toBe(['same' => 4]);

    // A changed file no longer matches its checksum.
    $tampered = $package;
    $tampered['objects'][0]['content']['icon'] = 'skull';
    uploadPackage($this, $tampered)->assertStatus(422)->assertJsonValidationErrors(['file']);
    uploadPackage($this, ['format' => 'something else'])->assertStatus(422)->assertJsonValidationErrors(['file']);
    expect(DB::table('audit_logs')->where('event', 'package.exported')->count())->toBe(1);
});

it('imports another environment\'s package under new keys, publishes the forms and keeps grants by role key', function () {
    $s = packagedSetup($this);
    $other = asOtherEnvironment(exportPackage($this, [['type' => 'form', 'uuid' => $s['form']], ['type' => 'download_profile', 'uuid' => $s['profile']]]));

    $imported = uploadPackage($this, $other)->assertCreated()->json('data');
    expect($imported['status'])->toBe('conflicts')
        ->and(collect($imported['report']['objects'])->pluck('state')->unique()->all())->toBe(['key_taken']);

    // Taking an object whose key is used here is refused; renaming it is not.
    $byType = collect($other['objects'])->keyBy('type');
    $this->postJson("/api/v1/packages/{$imported['uuid']}/apply", ['choices' => [$byType['application']['uuid'] => ['action' => 'take']]])
        ->assertStatus(422);
    $choices = [];
    foreach ($other['objects'] as $o) {
        $choices[$o['uuid']] = ['action' => 'rename', 'key' => 'copy_'.$o['key']];
    }
    $this->postJson("/api/v1/packages/{$imported['uuid']}/apply", ['choices' => $choices])->assertStatus(202);

    $done = waitApplied($this, $imported['uuid']);
    expect($done['status'])->toBe('applied', json_encode($done['results']))
        ->and(collect($done['results'])->pluck('outcome')->unique()->all())->toBe(['applied']);

    $form = $byType['form']['uuid'];
    expect(DB::table('forms')->where('uuid', $form)->value('key'))->toBe('copy_leave')
        ->and(DB::table('forms')->where('uuid', $form)->value('current_version_id'))->not->toBeNull();
    $this->postJson("/api/v1/r/{$form}", ['values' => ['subject' => 'Imported']])->assertCreated();
    $permission = DB::table('permissions')->where('key', "form.{$form}.view")->value('id');
    expect(DB::table('permission_assignments')->where('permission_id', $permission)->where('subject_type', 'role')->pluck('subject_id')->map(fn ($v) => (int) $v)->all())
        ->toContain((int) DB::table('roles')->where('key', 'reviewers')->value('id'));
    $rules = $this->getJson("/api/v1/forms/{$form}/notification-rules")->assertOk()->json('data.rules');
    expect($rules[0]['template'])->toBe($byType['email_template']['uuid'])
        ->and(DB::table('download_profiles')->where('uuid', $byType['download_profile']['uuid'])->value('key'))->toBe('copy_leave_list');

    // The same package again: nothing to do.
    $again = uploadPackage($this, $other)->assertCreated()->json('data');
    expect($again['status'])->toBe('validated')
        ->and(collect($again['report']['objects'])->pluck('state')->unique()->all())->toBe(['same']);
});

it('tells which side changed since the last import, and refuses to choose when both did', function () {
    $s = packagedSetup($this);
    $original = exportPackage($this, [['type' => 'email_template', 'uuid' => $s['template']]]);
    $template = collect($original['objects'])->firstWhere('type', 'email_template');

    // Changed in the package only: a plain "changed", resolved by taking it.
    $v2 = $original;
    foreach ($v2['objects'] as &$o) {
        if ($o['type'] === 'email_template') {
            $o['content']['subject']['en'] = 'Request moved: {{ subject }}';
        }
    }
    unset($o);
    $v2 = sealPackage($v2);
    $imported = uploadPackage($this, $v2)->assertCreated()->json('data');
    $row = collect($imported['report']['objects'])->firstWhere('uuid', $template['uuid']);
    expect($row['state'])->toBe('changed')->and($row['differences'])->toBe(['subject']);
    $this->postJson("/api/v1/packages/{$imported['uuid']}/apply", [])->assertStatus(422)->assertJsonValidationErrors(["choices.{$template['uuid']}"]); // a change needs a choice
    $choices = collect($imported['report']['objects'])->mapWithKeys(fn ($r) => [$r['uuid'] => ['action' => $r['uuid'] === $template['uuid'] ? 'take' : 'keep']])->all();
    $this->postJson("/api/v1/packages/{$imported['uuid']}/apply", ['choices' => $choices])->assertStatus(202);
    expect(waitApplied($this, $imported['uuid'])['status'])->toBe('applied')
        ->and($this->getJson("/api/v1/notifications/templates/{$template['uuid']}")->assertOk()->json('data.subject.en'))->toBe('Request moved: {{ subject }}');

    // Then changed here only: "changed here", kept by default.
    $tpl = $this->getJson("/api/v1/notifications/templates/{$template['uuid']}")->assertOk()->json('data');
    $this->putJson("/api/v1/notifications/templates/{$template['uuid']}", ['key' => $tpl['key'], 'form' => $tpl['form'], 'design' => $tpl['design'], 'base_hash' => $tpl['hash'],
        'subject' => ['en' => 'Changed here {{ subject }}', 'ar' => 'تغير هنا']])->assertOk();
    $report = uploadPackage($this, $v2, '/api/v1/packages/compare')->assertCreated()->json('data');
    expect(collect($report['objects'])->firstWhere('uuid', $template['uuid'])['state'])->toBe('changed_here');

    // And changed in a new package as well: ambiguous, so it cannot be applied.
    $v3 = $v2;
    foreach ($v3['objects'] as &$o) {
        if ($o['type'] === 'email_template') {
            $o['content']['subject']['en'] = 'Third wording {{ subject }}';
        }
    }
    unset($o);
    $v3 = sealPackage($v3);
    $both = uploadPackage($this, $v3)->assertCreated()->json('data');
    expect($both['report']['ambiguous'])->toBeTrue()
        ->and(collect($both['report']['objects'])->firstWhere('uuid', $template['uuid'])['state'])->toBe('changed_in_both');
    $this->postJson("/api/v1/packages/{$both['uuid']}/apply", ['choices' => [$template['uuid'] => ['action' => 'take']]])
        ->assertStatus(422)->assertJsonValidationErrors(['package']);
    expect($this->getJson('/api/v1/drift-reports')->assertOk()->json('data'))->toHaveCount(3);
});

it('reports tables that drift from their metadata and will not import over them', function () {
    $s = packagedSetup($this);
    $package = exportPackage($this, [['type' => 'form', 'uuid' => $s['form']]]);
    $table = DB::table('forms')->where('uuid', $s['form'])->value('table_name');
    DB::statement("ALTER TABLE {$table} ADD stray_column INT NULL");

    $report = uploadPackage($this, $package, '/api/v1/packages/compare')->assertCreated()->json('data');
    expect($report['ambiguous'])->toBeTrue()
        ->and(collect($report['objects'])->firstWhere('uuid', $s['form'])['state'])->toBe('schema_drift')
        ->and($report['schema'][0]['table'])->toBe($table);
    $this->getJson("/api/v1/drift-reports/{$report['uuid']}")->assertOk()->assertJsonPath('data.ambiguous', true);
});

it('drops a removed field only with the destructive confirmation', function () {
    $s = packagedSetup($this);
    $before = exportPackage($this, [['type' => 'form', 'uuid' => $s['form']]]);
    // Here the form loses its "reason" field ...
    $form = collect($before['objects'])->firstWhere('type', 'form');
    $pkg = $before;
    foreach ($pkg['objects'] as &$o) {
        if ($o['type'] === 'form') {
            $o['content']['document']['fields'] = array_values(array_filter($o['content']['document']['fields'], static fn ($f) => $f['key'] !== 'reason'));
            foreach ($o['content']['workflow']['transitions'] as &$tr) {
                $tr['requiredFields'] = [];
            }
            unset($tr);
        }
    }
    unset($o);
    $pkg = sealPackage($pkg);
    $choices = fn (array $imported) => collect($imported['report']['objects'])->mapWithKeys(fn ($r) => [$r['uuid'] => ['action' => $r['uuid'] === $form['uuid'] ? 'take' : 'keep']])->all();

    $first = uploadPackage($this, $pkg)->assertCreated()->json('data');
    expect(collect($first['report']['objects'])->firstWhere('uuid', $form['uuid'])['differences'])->toContain('fields-reason');
    $this->postJson("/api/v1/packages/{$first['uuid']}/apply", ['choices' => $choices($first)])->assertStatus(202);
    $failed = waitApplied($this, $first['uuid']);
    expect($failed['status'])->toBe('failed')
        ->and(collect($failed['results'])->firstWhere('uuid', $form['uuid'])['outcome'])->toBe('failed');

    // ... applied again, now confirmed.
    $this->postJson("/api/v1/packages/{$first['uuid']}/apply", ['choices' => $choices($first), 'confirm_destructive' => true])->assertStatus(202);
    expect(waitApplied($this, $first['uuid'])['status'])->toBe('applied')
        ->and(DB::table('fields')->where('form_id', DB::table('forms')->where('uuid', $form['uuid'])->value('id'))->where('key', 'reason')->exists())->toBeFalse();
});

it('is only for holders of "Manage configuration packages"', function () {
    $user = $this->makeUser();
    $this->flushSession();
    $this->actingAs($user, 'web');
    $this->getJson('/api/v1/packages')->assertForbidden();
    $this->getJson('/api/v1/packages/catalog')->assertForbidden();
    $this->getJson('/api/v1/drift-reports')->assertForbidden();
    grantPermission($user->id, 'system.manage_packages');
    $this->getJson('/api/v1/packages/catalog')->assertOk()->assertJsonStructure(['data' => ['application', 'form', 'email_template', 'document_template', 'download_profile']]);
});

it('carries reports and dashboards with the forms they read', function () {
    $s = packagedSetup($this);
    $report = $this->postJson('/api/v1/reports', ['report' => ['key' => 'leave_count', 'form' => $s['form'], 'visualization' => 'kpi', 'i18n' => ['name' => ['en' => 'Leave count']],
        'measures' => [['uuid' => uid(), 'aggregate' => 'count', 'path' => null, 'i18n' => ['label' => []]]]]])->assertCreated()->json('data');
    $dashboard = $this->postJson('/api/v1/dashboards', ['dashboard' => ['key' => 'leave_board', 'i18n' => ['name' => ['en' => 'Leave board']], 'widgets' => [
        ['type' => 'report', 'report' => $report['uuid'], 'roles' => [$s['role']], 'layout' => ['w' => 6, 'h' => 2]],
    ]]])->assertCreated()->json('data');

    $package = exportPackage($this, [['type' => 'dashboard', 'uuid' => $dashboard['uuid']]]);
    expect(collect($package['objects'])->pluck('type')->all())->toBe(['application', 'form', 'email_template', 'report', 'dashboard']);

    $other = asOtherEnvironment($package);
    $imported = uploadPackage($this, $other)->assertCreated()->json('data');
    $choices = [];
    foreach ($other['objects'] as $o) {
        $choices[$o['uuid']] = ['action' => 'rename', 'key' => 'copy_'.$o['key']];
    }
    $this->postJson("/api/v1/packages/{$imported['uuid']}/apply", ['choices' => $choices])->assertStatus(202);
    $done = waitApplied($this, $imported['uuid']);
    expect($done['status'])->toBe('applied', json_encode($done['results']));

    $byType = collect($other['objects'])->keyBy('type');
    $copy = $this->getJson("/api/v1/dashboards/{$byType['dashboard']['uuid']}")->assertOk()->json('data');
    expect($copy['key'])->toBe('copy_leave_board')
        ->and($copy['widgets'][0]['report'])->toBe($byType['report']['uuid'])
        ->and($copy['widgets'][0]['roles'])->toBe([$s['role']]);
    $data = $this->getJson("/api/v1/reports/{$byType['report']['uuid']}/data")->assertOk()->json('data');
    expect($data['result']['totals'])->toBe([0]);
});

it('re-imports an application after its form changed here without losing anything silently: keep, take and rename', function () {
    $s = packagedSetup($this);
    $package = exportPackage($this, [['type' => 'application', 'uuid' => $s['app']], ['type' => 'form', 'uuid' => $s['form']]]);
    $before = $this->postJson("/api/v1/r/{$s['form']}", ['values' => ['subject' => 'Before the change', 'reason' => 'kept']])->assertCreated()->json('data.uuid');

    // Here the form gains a "notes" field, which a record fills in.
    $draft = $this->getJson("/api/v1/forms/{$s['form']}/draft")->assertOk()->json('data.document');
    saveDraft($this, $s['form'], $draft['groups'], [...$draft['fields'], fieldDoc('notes', 'text')])->assertOk();
    expect(publish($this, $s['form'])['status'])->toBe('applied');
    $after = $this->postJson("/api/v1/r/{$s['form']}", ['values' => ['subject' => 'After the change', 'notes' => 'Written here']])->assertCreated()->json('data.uuid');
    $table = (string) DB::table('forms')->where('uuid', $s['form'])->value('table_name');
    $formId = (int) DB::table('forms')->where('uuid', $s['form'])->value('id');
    $notesHere = static fn () => DB::table('fields')->where('form_id', $formId)->where('key', 'notes')->exists();

    $imported = uploadPackage($this, $package)->assertCreated()->json('data');
    $rows = collect($imported['report']['objects'])->keyBy('uuid');
    expect($rows[$s['form']]['state'])->toBe('changed_here')
        ->and($rows[$s['form']]['differences'])->toContain('fields-notes')
        ->and($rows[$s['app']]['state'])->toBe('same');

    // Rename: an object that already exists here keeps its identity, so it cannot take another key.
    $errors = $this->postJson("/api/v1/packages/{$imported['uuid']}/apply", ['choices' => [$s['form'] => ['action' => 'rename', 'key' => 'leave_copy']]])
        ->assertStatus(422)->json('errors');
    expect($errors["choices.{$s['form']}"][0])->toBe(__('packages.rename_new_only', ['name' => $rows[$s['form']]['label']]));

    // Keep (the default for "changed here"): nothing here changes.
    $this->postJson("/api/v1/packages/{$imported['uuid']}/apply", [])->assertStatus(202);
    expect(waitApplied($this, $imported['uuid'])['status'])->toBe('applied')
        ->and($notesHere())->toBeTrue()
        ->and($this->getJson("/api/v1/r/{$s['form']}/{$after}")->assertOk()->json('data.values.notes'))->toBe('Written here');

    // Take: the package's version would drop "notes" and the data in it, so it is refused without the destructive confirmation ...
    $again = uploadPackage($this, $package)->assertCreated()->json('data');
    $take = [$s['form'] => ['action' => 'take'], $s['app'] => ['action' => 'keep']];
    $this->postJson("/api/v1/packages/{$again['uuid']}/apply", ['choices' => $take])->assertStatus(202);
    $refused = waitApplied($this, $again['uuid']);
    expect($refused['status'])->toBe('failed')
        ->and(collect($refused['results'])->firstWhere('uuid', $s['form'])['outcome'])->toBe('failed')
        ->and($notesHere())->toBeTrue()
        ->and($this->getJson("/api/v1/r/{$s['form']}/{$after}")->assertOk()->json('data.values.notes'))->toBe('Written here');

    // ... and with it, applied: the field goes, as confirmed, and every record stays.
    $this->postJson("/api/v1/packages/{$again['uuid']}/apply", ['choices' => $take, 'confirm_destructive' => true])->assertStatus(202);
    expect(waitApplied($this, $again['uuid'])['status'])->toBe('applied')
        ->and($notesHere())->toBeFalse()
        ->and(DB::table($table)->count())->toBe(2)
        ->and($this->getJson("/api/v1/r/{$s['form']}/{$before}")->assertOk()->json('data.values.reason'))->toBe('kept')
        ->and($this->getJson("/api/v1/r/{$s['form']}/{$after}")->assertOk()->json('data.values.subject'))->toBe('After the change');
});
