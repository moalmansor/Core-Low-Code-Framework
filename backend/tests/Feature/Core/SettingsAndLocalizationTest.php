<?php

declare(strict_types=1);

use App\Modules\Access\Models\Role;
use App\Modules\Audit\Models\AuditLog;
use App\Modules\Core\Mail\TestMail;
use App\Modules\Core\Models\Setting;
use App\Modules\Core\Settings\SettingsService;
use App\Support\Color\Contrast;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    $this->completeSetup();
    $this->actingAs($this->superAdmin(), 'web');
});

it('updates a settings group with validation and an audit trail', function () {
    $this->patchJson('/api/v1/settings/security', ['password_min_length' => 14, 'lockout_max_attempts' => 4])
        ->assertOk()->assertJsonPath('data.password_min_length', 14);
    $this->patchJson('/api/v1/settings/security', ['password_min_length' => 3])->assertUnprocessable();
    $this->patchJson('/api/v1/settings/security', ['not_a_setting' => 1])->assertUnprocessable();
    $this->getJson('/api/v1/settings/setup')->assertNotFound();
    $entry = AuditLog::query()->where('event', 'config.changed')->latest('id')->first();
    expect(collect($entry->changes)->firstWhere('field_key', 'security.password_min_length'))->toMatchArray(['old' => 12, 'new' => 14]);
});

it('keeps secrets write-only and encrypted, in the database and in the cache', function () {
    $this->patchJson('/api/v1/settings/mail', ['host' => 'smtp.example.test', 'from_address' => 'a@example.test', 'password' => 'super-secret-pw'])->assertOk();
    $shown = $this->getJson('/api/v1/settings/mail')->assertOk()->json('data');
    expect(json_encode($shown))->not->toContain('super-secret-pw')
        ->and($shown['password'] ?? null)->toBe(['is_set' => true]);
    $row = Setting::query()->where('group', 'mail')->where('key', 'password')->first();
    expect((string) $row->getRawOriginal('encrypted_value'))->not->toContain('super-secret-pw');
    expect(serialize(Cache::get('settings:'.$row->organization_id.':mail')))->not->toContain('super-secret-pw');
    expect(app(SettingsService::class)->get('mail', 'password'))->toBe('super-secret-pw');
    // An empty secret keeps the stored value; the audit entry never holds it.
    $this->patchJson('/api/v1/settings/mail', ['password' => ''])->assertOk();
    expect(app(SettingsService::class)->get('mail', 'password'))->toBe('super-secret-pw');
    expect(AuditLog::query()->where('event', 'config.changed')->get()->toJson())->not->toContain('super-secret-pw');
});

it('fixes the tenancy mode after setup', function () {
    $this->patchJson('/api/v1/settings/tenancy', ['mode' => 'multi'])->assertNotFound();
    expect(fn () => app(SettingsService::class)->update('tenancy', ['mode' => 'multi']))->toThrow(ValidationException::class);
});

it('sends a test e-mail through the configured SMTP settings', function () {
    Mail::fake();
    $this->postJson('/api/v1/settings/mail/test', ['to' => 'ops@example.test'])->assertUnprocessable();
    $this->patchJson('/api/v1/settings/mail', ['host' => 'smtp.example.test', 'from_address' => 'noreply@example.test'])->assertOk();
    $this->postJson('/api/v1/settings/mail/test', ['to' => 'ops@example.test'])->assertOk();
    Mail::assertSent(TestMail::class, fn ($m) => $m->hasTo('ops@example.test'));
});

it('renames the system per locale from branding settings', function () {
    $this->patchJson('/api/v1/settings/branding', ['system_name' => ['en' => 'Portal', 'ar' => 'البوابة']])->assertOk();
    $this->getJson('/api/v1/bootstrap')->assertJsonPath('data.system_name.ar', 'البوابة');
});

it('manages locales and keeps exactly one enabled default', function () {
    $this->postJson('/api/v1/locales', ['code' => 'fr', 'native_name' => 'Français', 'direction' => 'ltr', 'calendar' => 'gregorian', 'digits' => 'western',
        'date_format' => 'dd/MM/yyyy', 'time_format' => '24h', 'number_format' => ['decimal' => ',', 'group' => ' '], 'first_day_of_week' => 1, 'fallback' => 'en'])
        ->assertCreated();
    $this->patchJson('/api/v1/locales/en', ['is_enabled' => false])->assertUnprocessable();
    $this->patchJson('/api/v1/locales/fr', ['is_default' => true])->assertOk();
    expect(DB::table('locales')->where('is_default', true)->pluck('code')->all())->toBe(['fr']);
    $this->patchJson('/api/v1/locales/fr', ['fallback' => 'fr'])->assertUnprocessable();
});

it('translates object labels and lists what is missing per locale', function () {
    $role = Role::query()->create(['key' => 'clerk', 'audience' => 'internal']);
    $role->setTranslations('name', ['en' => 'Clerk']);
    $missing = $this->getJson('/api/v1/translations?type=role&locale=ar&untranslated=1')->assertOk()->json('data');
    expect(collect($missing)->pluck('object')->all())->toContain($role->uuid);

    $this->putJson('/api/v1/translations', ['locale' => 'ar', 'items' => [['type' => 'role', 'object' => $role->uuid, 'field' => 'name', 'value' => 'كاتب']]])->assertOk();
    expect($role->translate('name', 'ar'))->toBe('كاتب');
    $missing = $this->getJson('/api/v1/translations?type=role&locale=ar&untranslated=1')->json('data');
    expect(collect($missing)->where('object', $role->uuid)->where('field', 'name')->all())->toBe([]);
});

it('overrides interface strings per locale and serves the merged catalog publicly', function () {
    $this->putJson('/api/v1/translations', ['locale' => 'ar', 'items' => [['type' => 'ui', 'key' => 'shell.sign_out', 'field' => 'text', 'value' => 'خروج']]])->assertOk();
    $this->getJson('/api/v1/i18n/ar')->assertOk()->assertJsonPath('data', fn ($d) => ($d['shell.sign_out'] ?? null) === 'خروج');
    $this->getJson('/api/v1/i18n/xx')->assertNotFound();

    $export = $this->getJson('/api/v1/translations/export?locale=ar')->assertOk()->json('data');
    expect($export['format'])->toBe('lcf.translations/v1');
    $this->postJson('/api/v1/translations/import', ['format' => 'lcf.translations/v1', 'locale' => 'ar', 'ui' => ['shell.sign_out' => 'تسجيل الخروج']])->assertOk();
    $this->getJson('/api/v1/i18n/ar')->assertJsonPath('data', fn ($d) => $d['shell.sign_out'] === 'تسجيل الخروج');
});

it('stores the language preference and answers in that language', function () {
    $user = $this->makeUser();
    $this->flushSession();
    $this->actingAs($user, 'web');
    $this->patchJson('/api/v1/me/preferences', ['locale' => 'ar', 'theme_mode' => 'dark'])->assertOk()->assertJsonPath('data.locale', 'ar');
    $this->patchJson('/api/v1/me/preferences', ['locale' => 'xx'])->assertUnprocessable();
    $this->getJson('/api/v1/users')->assertForbidden()->assertHeader('Content-Language', 'ar');
});

it('restricts settings, locales and translations to their permissions', function () {
    $this->flushSession();
    $this->actingAs($this->makeUser(), 'web');
    $this->getJson('/api/v1/settings/security')->assertForbidden();
    $this->patchJson('/api/v1/settings/branding', ['system_name' => ['en' => 'x']])->assertForbidden();
    $this->postJson('/api/v1/locales', ['code' => 'de'])->assertForbidden();
    $this->putJson('/api/v1/translations', ['locale' => 'ar', 'items' => [['type' => 'ui', 'key' => 'a', 'field' => 'text', 'value' => 'b']]])->assertForbidden();
});

it('validates the structure of nested settings such as SSO providers', function () {
    $this->patchJson('/api/v1/settings/sso', ['providers' => [['key' => 'corp', 'name' => ['en' => 'Corp'], 'issuer' => 'http://idp.example.test', 'client_id' => 'x']]])
        ->assertUnprocessable()->assertJsonValidationErrors('providers.0.issuer');
    $this->patchJson('/api/v1/settings/sso', ['providers' => [['key' => 'Bad Key', 'name' => ['en' => 'Corp'], 'issuer' => 'https://idp.example.test', 'client_id' => 'x']]])
        ->assertUnprocessable()->assertJsonValidationErrors('providers.0.key');
    $this->patchJson('/api/v1/settings/sso', [
        'providers' => [['key' => 'corp', 'name' => ['en' => 'Corp'], 'issuer' => 'https://idp.example.test', 'client_id' => 'x', 'role_map' => ['admins' => 'admin']]],
        'client_secrets' => ['corp' => 'shh'],
    ])->assertOk();
    $this->getJson('/api/v1/auth/sso/providers')->assertOk()->assertJsonFragment(['key' => 'corp', 'name' => 'Corp']);
    $this->patchJson('/api/v1/settings/ldap', ['hosts' => ['ldap.example.test; rm -rf /']])->assertUnprocessable();
});

it('translates permission labels, which are addressed by key', function () {
    $rows = $this->getJson('/api/v1/translations?type=permission&locale=ar&search=manage_users')->assertOk()->json('data');
    expect($rows[0]['key'])->toBe('system.manage_users');
    $this->putJson('/api/v1/translations', ['locale' => 'ar', 'items' => [['type' => 'permission', 'key' => 'system.manage_users', 'field' => 'label', 'value' => 'إدارة الحسابات']]])->assertOk();
    $this->withHeader('X-Locale', 'ar')->getJson('/api/v1/permissions')->assertJsonFragment(['key' => 'system.manage_users', 'label' => 'إدارة الحسابات']);
});

it('stores a readable brand colour, refuses an unreadable one with the nearest passing shade, and publishes it', function () {
    $this->patchJson('/api/v1/settings/branding', ['primary_color' => '#7c3aed'])->assertOk()->assertJsonPath('data.primary_color', '#7c3aed');
    $this->getJson('/api/v1/bootstrap')->assertOk()->assertJsonPath('data.branding.primary_color', '#7c3aed')->assertJsonPath('data.branding.primary_color_dark', null);

    // Too light to read as text on white (1.7:1): refused, with a shade that works.
    $error = $this->patchJson('/api/v1/settings/branding', ['primary_color' => '#7dd3fc'])->assertUnprocessable()->json('errors.primary_color.0');
    preg_match('/#[0-9a-f]{6}/', (string) $error, $m);
    expect($m[0] ?? null)->not->toBeNull()
        ->and(Contrast::onSurfaces($m[0], 'light'))->toBeGreaterThanOrEqual(4.5);
    // A dark-mode shade must read on the dark surfaces; a format other than #RRGGBB is refused.
    $this->patchJson('/api/v1/settings/branding', ['primary_color_dark' => '#1a3a6a'])->assertUnprocessable()->assertJsonValidationErrors('primary_color_dark');
    $this->patchJson('/api/v1/settings/branding', ['primary_color' => 'blue'])->assertUnprocessable();
    $this->patchJson('/api/v1/settings/branding', ['primary_color' => null])->assertOk();
    $entry = AuditLog::query()->where('event', 'config.changed')->latest('id')->first();
    expect(collect($entry->changes)->firstWhere('field_key', 'branding.primary_color'))->toMatchArray(['old' => '#7c3aed', 'new' => null]);
});

it('matches the contrast arithmetic of the interface', function () {
    // Values also asserted by frontend/src/theme/contrast.spec.ts.
    expect(round(Contrast::ratio('#ffffff', '#1a6fd4'), 2))->toBe(4.92)
        ->and(round(Contrast::ratio('#e6edf3', '#171e26'), 2))->toBe(14.22)
        ->and(Contrast::onSurfaces('#1a6fd4', 'light'))->toBeGreaterThanOrEqual(4.5);
});
