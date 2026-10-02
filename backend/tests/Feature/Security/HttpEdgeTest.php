<?php

declare(strict_types=1);

use App\Modules\Access\AccessCache;
use App\Modules\Access\Models\Permission;
use App\Modules\Access\Models\PermissionAssignment;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

it('serves the SPA shell with a strict nonce-based CSP and security headers', function () {
    $response = $this->get('/login')->assertOk();
    $csp = $response->headers->get('Content-Security-Policy');
    expect($csp)->toContain("default-src 'self'")->toContain("object-src 'none'")->toContain("frame-ancestors 'none'")
        ->not->toContain('unsafe-inline')->not->toContain('unsafe-eval');
    preg_match("/'nonce-([^']+)'/", $csp, $m);
    expect($m[1] ?? '')->not->toBe('');
    $response->assertHeader('X-Content-Type-Options', 'nosniff')
        ->assertHeader('X-Frame-Options', 'DENY')
        ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
    expect($response->getContent())->toContain('<div id="app">');
});

it('locks API responses down and never caches them', function () {
    $response = $this->getJson('/api/v1/bootstrap')->assertOk();
    expect($response->headers->get('Content-Security-Policy'))->toStartWith("default-src 'none'")
        ->and($response->headers->get('Cache-Control'))->toContain('no-store');
});

it('sends HSTS on HTTPS only', function () {
    $this->get('https://localhost/login')->assertHeader('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
    $this->get('http://localhost/login')->assertHeaderMissing('Strict-Transport-Security');
});

it('propagates a well-formed correlation ID and replaces a malformed one', function () {
    $this->getJson('/api/v1/bootstrap', ['X-Correlation-ID' => '01J8ABCDEFGHJKMNPQRSTVWXYZ'])
        ->assertHeader('X-Correlation-ID', '01j8abcdefghjkmnpqrstvwxyz');
    $id = $this->getJson('/api/v1/bootstrap', ['X-Correlation-ID' => "bad\nvalue"])->headers->get('X-Correlation-ID');
    expect($id)->toMatch('/^[0-9A-Za-z]{26}$/');
});

it('rejects state-changing requests without a CSRF token from the browser', function () {
    $this->completeSetup();
    $user = $this->makeUser();
    // Re-enable CSRF verification, which the test runner normally skips.
    $this->app->instance('env', 'production');
    $this->postJson('/api/v1/auth/login', ['email' => $user->email, 'password' => TestCase::PASSWORD])->assertStatus(419);
    $this->app->instance('env', 'testing');
});

it('rate-limits login attempts', function () {
    $this->completeSetup();
    foreach (range(1, 5) as $_) {
        $this->postJson('/api/v1/auth/login', ['email' => 'x@example.test', 'password' => 'wrong-password']);
    }
    $this->postJson('/api/v1/auth/login', ['email' => 'x@example.test', 'password' => 'wrong-password'])->assertStatus(429);
});

it('shows each admin console area only to holders of its permission', function () {
    $this->completeSetup();
    $this->actingAs($this->superAdmin(), 'web');
    $areas = collect($this->getJson('/api/v1/admin/console')->assertOk()->json('data.areas'))->pluck('key');
    expect($areas->all())->toContain('users', 'roles_permissions', 'audit_log', 'error_monitoring', 'translations', 'system_settings', 'system_health');
    $this->getJson('/api/v1/admin/health')->assertOk()->assertJsonPath('data.checks.database', 'ok');

    $this->flushSession();
    $translator = $this->makeUser();
    PermissionAssignment::query()->create([
        'permission_id' => Permission::query()->where('key', 'system.manage_translations')->value('id'),
        'subject_type' => 'user', 'subject_id' => $translator->id, 'effect' => 'allow',
    ]);
    app(AccessCache::class)->bump();
    $this->actingAs($translator, 'web');
    expect(collect($this->getJson('/api/v1/admin/console')->json('data.areas'))->pluck('key')->all())->toBe(['translations']);
    $this->getJson('/api/v1/admin/health')->assertForbidden();
});

it('accepts a branding image only after type, content and size checks, and serves it', function () {
    Storage::fake('private');
    $this->completeSetup();
    $this->actingAs($this->superAdmin(), 'web');
    $this->postJson('/api/v1/settings/branding/logo', ['file' => UploadedFile::fake()->image('logo.png', 120, 40)])->assertOk();
    $this->get('/api/v1/branding/logo')->assertOk()->assertHeader('X-Content-Type-Options', 'nosniff');

    $fake = UploadedFile::fake()->createWithContent('logo.png', '<?php echo "pwned";');
    $this->postJson('/api/v1/settings/branding/logo', ['file' => $fake])->assertUnprocessable();
    $svg = UploadedFile::fake()->createWithContent('logo.svg', '<svg onload="alert(1)"/>');
    $this->postJson('/api/v1/settings/branding/logo', ['file' => $svg])->assertUnprocessable();
});
