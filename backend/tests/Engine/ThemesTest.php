<?php

declare(strict_types=1);

use App\Modules\Experience\Themes\CustomCss;
use App\Modules\Experience\Themes\ThemeTokens;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

require_once __DIR__.'/helpers.php';

beforeEach(function () {
    $this->completeSetup();
    $this->admin = $this->superAdmin();
    $this->flushSession();
    $this->actingAs($this->admin, 'web');
});

function themeDoc(array $extra = []): array
{
    return array_replace(['key' => 't'.substr(str_replace('-', '', uid()), -10), 'application' => null, 'custom_css' => null,
        'tokens' => ['colors' => ['primary' => ['light' => '#0b6e99', 'dark' => '#4fb3df']], 'radius' => 'rounded', 'legal_links' => [['url' => 'https://example.org/privacy', 'label' => ['en' => 'Privacy']]]],
        'i18n' => ['name' => ['en' => 'Harbour', 'ar' => 'الميناء'], 'login_welcome' => ['en' => 'Welcome aboard'], 'support_contact' => ['en' => 'help@example.org'], 'browser_title' => []]], $extra);
}

it('passes every preset in both modes', function () {
    foreach (ThemeTokens::PRESETS as $key => $tokens) {
        expect(ThemeTokens::errors(ThemeTokens::report($tokens)))->toBe(0, $key)
            ->and(ThemeTokens::warnings(ThemeTokens::report($tokens)))->toBe(0, $key);
    }
});

it('refuses unreadable colours, asks to acknowledge weak contrasts, and applies the default theme', function () {
    // A pale primary cannot be read as text: refused, with the nearest readable shade in the check report.
    $pale = themeDoc(['tokens' => ['colors' => ['primary' => ['light' => '#9ecbf0']]]]);
    $this->postJson('/api/v1/themes', ['theme' => $pale])->assertUnprocessable()->assertJsonValidationErrors('tokens');
    $report = $this->postJson('/api/v1/themes/check', ['tokens' => $pale['tokens']])->assertOk()->json('data.report');
    $primary = collect($report)->first(fn ($r) => $r['check'] === 'primary' && $r['mode'] === 'light');
    expect($primary['level'])->toBe('error')->and($primary['suggestion'])->toMatch('/^#[0-9a-f]{6}$/');

    // A border that barely shows is a warning: saved only once acknowledged.
    $faint = themeDoc(['tokens' => ['colors' => ['border' => ['light' => '#fdfdfd']]]]);
    $this->postJson('/api/v1/themes', ['theme' => $faint])->assertUnprocessable()->assertJsonValidationErrors('acknowledge_warnings');
    $this->postJson('/api/v1/themes', ['theme' => $faint + ['acknowledge_warnings' => true]])->assertCreated();

    $made = $this->postJson('/api/v1/themes', ['theme' => themeDoc()])->assertCreated()->json('data');
    expect($made['version'])->toBe(1)->and(collect($made['contrast'])->where('level', '!=', 'ok')->count())->toBe(0);
    $this->getJson('/api/v1/bootstrap')->assertJsonPath('data.theme', null);
    $this->postJson("/api/v1/themes/{$made['uuid']}/default")->assertOk()->assertJsonPath('data.default', true);
    $boot = $this->getJson('/api/v1/bootstrap')->json('data.theme');
    expect($boot['colors']['primary']['light'])->toBe('#0b6e99')
        ->and($boot['radius'])->toBe('rounded')
        ->and($boot['login']['welcome'])->toBe('Welcome aboard')
        ->and($boot['login']['legal_links'][0])->toBe(['url' => 'https://example.org/privacy', 'label' => 'Privacy']);

    // Stale saves are refused; reset brings back the product theme's colours.
    $made = $this->getJson("/api/v1/themes/{$made['uuid']}")->json('data');
    $this->putJson("/api/v1/themes/{$made['uuid']}", ['theme' => themeDoc(['key' => $made['key'], 'tokens' => ['colors' => []]]), 'base_hash' => $made['hash']])->assertOk();
    $this->putJson("/api/v1/themes/{$made['uuid']}", ['theme' => themeDoc(['key' => $made['key']]), 'base_hash' => $made['hash']])->assertStatus(409);
    expect($this->postJson("/api/v1/themes/{$made['uuid']}/reset")->assertOk()->json('data.tokens.colors'))->toBe([]);

    // Only branding managers.
    $viewer = $this->makeUser();
    $this->flushSession();
    $this->actingAs($viewer, 'web');
    $this->getJson('/api/v1/themes')->assertForbidden();
    $this->postJson('/api/v1/themes', ['theme' => themeDoc()])->assertForbidden();
});

it('sanitizes and scopes custom CSS, and serves it as its own stylesheet', function () {
    expect(CustomCss::compile(".card-title { color: #0b6e99; font-weight: 600 }\n/* note */ h2, .lead { letter-spacing: 0.01em; }"))
        ->toBe(".app-content .card-title {\n  color: #0b6e99;\n  font-weight: 600;\n}\n\n.app-content h2,\n.app-content .lead {\n  letter-spacing: 0.01em;\n}\n");
    foreach ([
        '@import "x.css";',
        '.a { background-color: url(https://evil.example/x.png) }',
        '.a { color: red !important }',
        '.a { display: none }',
        '.maintenance-banner { color: red }',
        '.p-dialog .x { color: red }',
        'body { color: red }',
        '.a { color: red',
    ] as $bad) {
        expect(fn () => CustomCss::compile($bad))->toThrow(ValidationException::class, null, $bad);
    }

    $made = $this->postJson('/api/v1/themes', ['theme' => themeDoc(['custom_css' => '.lead { color: #0b6e99 }'])])->assertCreated()->json('data');
    $this->postJson('/api/v1/themes', ['theme' => themeDoc(['custom_css' => '.lead { position: fixed }'])])->assertUnprocessable()->assertJsonValidationErrors('custom_css');
    $this->postJson("/api/v1/themes/{$made['uuid']}/default")->assertOk();
    $css = $this->getJson('/api/v1/bootstrap')->json('data.theme.css');
    expect($css)->toContain("/api/v1/theme-css/{$made['uuid']}.css");
    $this->app['auth']->forgetGuards();
    $this->flushSession();
    $res = $this->get(parse_url($css, PHP_URL_PATH));
    $res->assertOk();
    expect($res->headers->get('Content-Type'))->toStartWith('text/css')->and($res->getContent())->toContain('.app-content .lead');
});

it('gives an application its own theme, carries artwork into emails, and round-trips through export', function () {
    [$app] = createForm($this, 'tickets');
    $made = $this->postJson('/api/v1/themes', ['theme' => themeDoc(['application' => $app])])->assertCreated()->json('data');
    $this->postJson('/api/v1/themes', ['theme' => themeDoc(['application' => $app])])->assertUnprocessable()->assertJsonValidationErrors('application');
    $this->postJson("/api/v1/themes/{$made['uuid']}/default")->assertStatus(422);
    expect(DB::table('applications')->where('uuid', $app)->value('theme_id'))->not->toBeNull();
    grantPermission($this->admin->id, "app.{$app}.access");
    $this->putJson("/api/v1/applications/{$app}/menu", ['items' => [['type' => 'header', 'label' => ['en' => 'Main'], 'children' => [['type' => 'link', 'url' => '/x', 'label' => ['en' => 'X']]]]]])->assertOk();
    $nav = collect($this->getJson('/api/v1/navigation')->json('data'))->firstWhere('uuid', $app);
    expect($nav['theme']['uuid'])->toBe($made['uuid']);

    // Artwork: re-encoded images only, served publicly, used in the email layout of the default theme.
    $org = $this->postJson('/api/v1/themes', ['theme' => themeDoc()])->assertCreated()->json('data');
    $this->postJson("/api/v1/themes/{$org['uuid']}/default")->assertOk();
    $this->post("/api/v1/themes/{$org['uuid']}/assets/email_header", ['file' => UploadedFile::fake()->image('header.png', 600, 120)], ['Accept' => 'application/json'])->assertOk();
    $this->post("/api/v1/themes/{$org['uuid']}/assets/nonsense", ['file' => UploadedFile::fake()->image('x.png')], ['Accept' => 'application/json'])->assertNotFound();
    $url = $this->getJson("/api/v1/themes/{$org['uuid']}")->json('data.assets.email_header');
    expect($url)->toContain('/api/v1/theme-assets/');
    $this->get(parse_url($url, PHP_URL_PATH))->assertOk()->assertHeader('Content-Type', 'image/png');
    $html = (fn () => $this->layout('<p>Hi</p>', '', ['style' => ['logo' => true]], 'en', 'Hi'))->call(app(App\Modules\Notifications\TemplateRenderer::class));
    expect($html)->toContain('/api/v1/theme-assets/'.$org['uuid'].'/email_header');

    // Export and import: a new theme with a free key, the same tokens and texts.
    $exported = $this->getJson("/api/v1/themes/{$org['uuid']}/export")->assertOk()->json();
    expect($exported['format'])->toBe('lcf-theme');
    $imported = $this->postJson('/api/v1/themes/import', ['theme' => $exported])->assertCreated()->json('data');
    expect($imported['key'])->toBe($org['key'].'_2')
        ->and($imported['tokens']['colors'])->toEqual($org['tokens']['colors'])
        ->and($imported['i18n']['name'])->toEqual($org['i18n']['name']);
    $this->postJson('/api/v1/themes/import', ['theme' => ['format' => 'other']])->assertUnprocessable();
});
