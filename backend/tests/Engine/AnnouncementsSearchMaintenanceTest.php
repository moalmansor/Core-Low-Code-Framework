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

function announcement(array $extra = []): array
{
    return array_replace(['severity' => 'warning', 'placement' => 'banner', 'application' => null, 'roles' => [], 'starts_at' => null, 'ends_at' => null,
        'dismissible' => true, 'published' => true, 'i18n' => ['title' => ['en' => 'Upgrade tonight', 'ar' => 'تحديث الليلة'], 'body' => ['en' => '<p>Save your work<script>x()</script></p>']]], $extra);
}

it('shows announcements to their audience, in their window, until dismissed', function () {
    $made = $this->postJson('/api/v1/announcements', ['announcement' => announcement()])->assertCreated()->json('data');
    expect($made['i18n']['body']['en'])->toBe('<p>Save your work</p>');
    $this->postJson('/api/v1/announcements', ['announcement' => announcement(['i18n' => ['title' => ['en' => 'Later'], 'body' => []], 'starts_at' => now()->addDay()->toIso8601String()])])->assertCreated();
    $this->postJson('/api/v1/announcements', ['announcement' => announcement(['i18n' => ['title' => ['en' => 'Admins'], 'body' => []], 'placement' => 'home',
        'roles' => [strtolower((string) DB::table('roles')->where('key', 'super_admin')->value('uuid'))]])])->assertCreated();
    $this->postJson('/api/v1/announcements', ['announcement' => announcement(['i18n' => ['title' => ['en' => 'Draft'], 'body' => []], 'published' => false])])->assertCreated();
    $this->postJson('/api/v1/announcements', ['announcement' => announcement(['ends_at' => now()->subDay()->toIso8601String()])])->assertUnprocessable()->assertJsonValidationErrors('ends_at');

    $viewer = $this->makeUser();
    $this->flushSession();
    $this->actingAs($viewer, 'web');
    $active = $this->getJson('/api/v1/announcements/active')->assertOk()->json('data');
    expect(array_column($active, 'title'))->toBe(['Upgrade tonight'])
        ->and($active[0]['body'])->toBe('<p>Save your work</p>');
    $this->getJson('/api/v1/announcements')->assertForbidden();
    $this->postJson("/api/v1/announcements/{$made['uuid']}/dismiss")->assertNoContent();
    expect($this->getJson('/api/v1/announcements/active')->json('data'))->toBe([]);

    // The admin still sees it, and the role-limited one on home screens.
    $this->flushSession();
    $this->actingAs($this->admin, 'web');
    expect(array_column($this->getJson('/api/v1/announcements/active')->json('data'), 'title'))->toBe(['Admins', 'Upgrade tonight']);
    expect(array_column($this->getJson('/api/v1/announcements/active?placement=home')->json('data'), 'title'))->toBe(['Admins']);
    $this->putJson("/api/v1/announcements/{$made['uuid']}", ['announcement' => announcement(['published' => false]), 'base_hash' => $made['hash']])->assertOk();
    $this->putJson("/api/v1/announcements/{$made['uuid']}", ['announcement' => announcement(), 'base_hash' => $made['hash']])->assertStatus(409);
});

it('takes the system offline for everyone but those who may switch maintenance mode', function () {
    [, $form] = createForm($this, 'tickets');
    saveDraft($this, $form, [], [fieldDoc('subject', 'text')])->assertOk();
    publish($this, $form);
    $viewer = $this->makeUser();
    grantPermission($viewer->id, "form.{$form}.view");

    $this->putJson('/api/v1/maintenance', ['enabled' => true, 'message' => ['en' => 'Back at noon', 'ar' => 'نعود ظهرًا']])->assertOk()->assertJsonPath('data.active', true);
    $this->getJson("/api/v1/r/{$form}")->assertOk();
    expect(DB::table('audit_logs')->where('event', 'maintenance.enabled')->exists())->toBeTrue();
    $this->getJson('/api/v1/bootstrap')->assertJsonPath('data.maintenance.active', true)->assertJsonPath('data.maintenance.message', 'Back at noon');

    $this->flushSession();
    $this->actingAs($viewer, 'web');
    $this->getJson("/api/v1/r/{$form}")->assertStatus(503)->assertJsonPath('code', 'maintenance')->assertJsonPath('message', 'Back at noon');
    $this->getJson('/api/v1/me')->assertOk();
    $this->getJson('/api/v1/maintenance')->assertOk()->assertJsonPath('data.blocked', true)->assertJsonPath('data.messages', null);
    $this->putJson('/api/v1/maintenance', ['enabled' => false])->assertForbidden();

    $this->flushSession();
    $this->actingAs($this->admin, 'web');
    $this->putJson('/api/v1/maintenance', ['enabled' => false])->assertOk()->assertJsonPath('data.active', false);
    $this->flushSession();
    $this->actingAs($viewer, 'web');
    $this->getJson("/api/v1/r/{$form}")->assertOk();
});

it('searches the configured fields of the forms a person may see, within their scope and field access', function () {
    [, $form] = createForm($this, 'tickets');
    $fields = [fieldDoc('subject', 'text'), fieldDoc('code', 'text'), fieldDoc('secret', 'text', null, ['flags' => ['sensitive' => true]])];
    saveDraft($this, $form, [], $fields)->assertOk();
    publish($this, $form);
    foreach ([['Printer jam', 'PR-1'], ['Scanner', 'printer-2'], ['Phone', 'PH-3']] as [$subject, $code]) {
        $this->postJson("/api/v1/r/{$form}", ['values' => ['subject' => $subject, 'code' => $code, 'secret' => 'printer']])->assertCreated();
    }
    $config = $this->getJson('/api/v1/search-configs')->assertOk()->json('data.0');
    expect(array_column($config['available'], 'key'))->toBe(['subject', 'code']);
    $uuids = array_column($config['available'], 'uuid', 'key');
    $this->putJson("/api/v1/search-configs/{$form}", ['config' => ['enabled' => true, 'fields' => [['field' => $fields[2]['uuid'], 'weight' => 5]]]])
        ->assertUnprocessable()->assertJsonValidationErrors('fields.0');
    $this->putJson("/api/v1/search-configs/{$form}", ['config' => ['enabled' => true, 'subtitle' => $uuids['code'],
        'fields' => [['field' => $uuids['subject'], 'weight' => 5], ['field' => $uuids['code'], 'weight' => 1]]]])->assertOk();

    $hits = $this->getJson('/api/v1/search?q=printer')->assertOk()->json('data');
    expect($hits)->toHaveCount(2)
        ->and($hits[0]['record']['subtitle'])->toBe('PR-1')
        ->and($hits[0]['score'])->toBeGreaterThan($hits[1]['score']);
    // A form's name is read once per form, not once per hit: two hits cost what one does.
    $nameReads = function (string $q): int {
        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->getJson('/api/v1/search?q='.$q)->assertOk();
        DB::disableQueryLog();

        return collect(DB::getQueryLog())->filter(fn (array $e) => preg_match('/from\W+translations\W/i', $e['query']) === 1)->count();
    };
    expect($nameReads('printer'))->toBe($nameReads('PH-3'));

    // Someone without the form finds nothing; someone with it finds records; the sensitive field is never searched.
    $viewer = $this->makeUser();
    $this->flushSession();
    $this->actingAs($viewer, 'web');
    expect($this->getJson('/api/v1/search?q=printer')->json('data'))->toBe([]);
    grantPermission($viewer->id, "form.{$form}.view");
    expect($this->getJson('/api/v1/search?q=printer')->json('data'))->toHaveCount(2);
    expect($this->getJson('/api/v1/search?q=PH-3')->json('data'))->toHaveCount(1);
    $this->getJson('/api/v1/search?q=x')->assertUnprocessable();
    $this->getJson('/api/v1/search-configs')->assertForbidden();
});
