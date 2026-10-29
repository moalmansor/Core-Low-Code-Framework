<?php

declare(strict_types=1);

use App\Modules\Core\Settings\SettingsService;
use App\Modules\Notifications\Digests;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

require_once __DIR__.'/helpers.php';

beforeEach(function () {
    $this->completeSetup();
    $this->admin = $this->superAdmin();
    $this->flushSession();
    $this->actingAs($this->admin, 'web');
});

it('keeps number format, digest and landing page preferences, within what the organization leaves to people', function () {
    $user = $this->makeUser();
    $this->flushSession();
    $this->actingAs($user, 'web');
    $prefs = $this->patchJson('/api/v1/me/preferences', [
        'number_format' => ['group' => ' ', 'decimal' => ','], 'digest_frequency' => 'daily', 'landing_page' => ['type' => 'my_work', 'target' => null],
    ])->assertOk()->json('data');
    expect($prefs['number_format'])->toBe(['group' => ' ', 'decimal' => ',']);
    expect($prefs['digest_frequency'])->toBe('daily');
    expect($prefs['landing_page']['type'])->toBe('my_work');
    $this->patchJson('/api/v1/me/preferences', ['number_format' => ['group' => ',', 'decimal' => ',']])->assertUnprocessable()->assertJsonValidationErrors('number_format.decimal');
    $this->patchJson('/api/v1/me/preferences', ['landing_page' => ['type' => 'page', 'target' => null]])->assertUnprocessable();

    app(SettingsService::class)->write('self_service', 'editable_preferences', ['theme_mode', 'locale']);
    $this->patchJson('/api/v1/me/preferences', ['timezone' => 'Asia/Riyadh'])->assertUnprocessable()->assertJsonPath('errors.timezone.0', __('identity.preference_locked'));
    $this->patchJson('/api/v1/me/preferences', ['theme_mode' => 'dark'])->assertOk()->assertJsonPath('data.editable', ['theme_mode', 'locale']);
});

it('pins records and keeps shortcuts the person may still open', function () {
    [, $form] = createForm($this, 'tickets');
    saveDraft($this, $form, [], [fieldDoc('subject', 'text')])->assertOk();
    expect(publish($this, $form)['status'])->toBe('applied');
    $record = $this->postJson("/api/v1/r/{$form}", ['values' => ['subject' => 'Printer jam']])->assertCreated()->json('data.uuid');

    $this->postJson('/api/v1/me/pins', ['form' => $form, 'record' => $record])->assertNoContent();
    $this->postJson('/api/v1/me/pins', ['form' => $form, 'record' => $record])->assertNoContent(); // pinning again moves it up, once
    $pins = $this->getJson('/api/v1/me/pins')->assertOk()->json('data');
    expect($pins)->toHaveCount(1);
    expect($pins[0]['title'])->toBe('Printer jam');

    $this->putJson('/api/v1/me/shortcuts', ['shortcuts' => [['form' => $form, 'action' => 'new', 'label' => 'New ticket']]])->assertOk()->assertJsonPath('data.0.label', 'New ticket');

    // Someone who cannot see the form cannot pin its records or make a shortcut to it.
    $other = $this->makeUser();
    $this->flushSession();
    $this->actingAs($other, 'web');
    $this->postJson('/api/v1/me/pins', ['form' => $form, 'record' => $record])->assertNotFound();
    $this->putJson('/api/v1/me/shortcuts', ['shortcuts' => [['form' => $form, 'action' => 'list']]])->assertUnprocessable();

    // A deleted record drops out of the pins.
    $this->flushSession();
    $this->actingAs($this->admin, 'web');
    $this->deleteJson("/api/v1/r/{$form}/{$record}", ['row_version' => 1])->assertSuccessful();
    expect($this->getJson('/api/v1/me/pins')->json('data'))->toBe([]);
    $this->deleteJson("/api/v1/me/pins/{$record}")->assertNoContent();
});

it('e-mails a daily digest of unread notifications at 07:00 the person\'s time, once', function () {
    $user = $this->makeUser();
    DB::table('user_preferences')->insert(['user_id' => $user->id, 'theme_mode' => 'system', 'digest_frequency' => 'daily', 'timezone' => 'Asia/Riyadh',
        'created_at' => now()->format('Y-m-d H:i:s.u'), 'updated_at' => now()->format('Y-m-d H:i:s.u')]);
    $make = static fn (string $title, ?string $read = null) => DB::table('in_app_notifications')->insert([
        'uuid' => (string) Str::uuid7(), 'organization_id' => $user->organization_id, 'user_id' => $user->id, 'type' => 'rule', 'title' => $title,
        'body' => null, 'link' => '/my-work', 'read_at' => $read, 'created_at' => Carbon::parse('2026-10-22 12:00:00', 'UTC')->format('Y-m-d H:i:s.u'),
    ]);
    $make('Approve the <b>budget</b>');
    $make('Already seen', now()->format('Y-m-d H:i:s.u'));

    $digests = app(Digests::class);
    // 06:00 in Riyadh is too early; 07:00 sends; the next hour does not send again.
    expect($digests->sendDue(Carbon::parse('2026-10-23 03:00:00', 'UTC')))->toBe(0);
    expect($digests->sendDue(Carbon::parse('2026-10-23 04:00:00', 'UTC')))->toBe(1);
    expect($digests->sendDue(Carbon::parse('2026-10-23 05:00:00', 'UTC')))->toBe(0);
    $mail = DB::table('email_queue')->orderByDesc('id')->first();
    expect(json_decode((string) $mail->to, true))->toBe([$user->email]);
    expect($mail->body_html)->toContain('Approve the &lt;b&gt;budget&lt;/b&gt;')->not->toContain('Already seen');
    // The next day, nothing new unread: no e-mail.
    expect($digests->sendDue(Carbon::parse('2026-10-24 04:00:00', 'UTC')))->toBe(0);
});
