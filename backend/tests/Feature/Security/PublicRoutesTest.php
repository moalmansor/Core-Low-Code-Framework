<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

/**
 * Every API route requires a signed-in user (or an API token) unless it is
 * listed here with the check that replaces sign-in (specification §5, access
 * control). A new route without authentication fails this test until it is
 * deliberately added below.
 */
const PUBLIC_API_ROUTES = [
    // Before sign-in: the sign-in page, the bootstrap payload, translations, branding and themes.
    'GET api/v1/auth/sso/providers', 'GET api/v1/bootstrap', 'GET api/v1/i18n/{locale}', 'GET api/v1/branding/{kind}',
    'GET api/v1/theme-assets/{theme}/{kind}', 'GET api/v1/theme-css/{theme}',
    // Signing in, the second factor and password recovery (throttled; the answer never reveals whether an account exists).
    'POST api/v1/auth/login', 'POST api/v1/auth/two-factor-challenge', 'POST api/v1/auth/forgot-password', 'POST api/v1/auth/reset-password',
    // The first-run wizard: open only until setup completes, and then only with its one-time token.
    'GET api/v1/setup/status', 'POST api/v1/setup/token', 'POST api/v1/setup/two-factor', 'POST api/v1/setup/branding/{kind}',
    'POST api/v1/setup/mail-test', 'POST api/v1/setup/complete',
    // Short-lived signed links (the controller refuses an unsigned or expired one).
    'GET api/v1/files/download/{file}', 'GET api/v1/html-previews/{preview}',
    // The data API and its description: API tokens (lcf.token).
    'GET api/v1/data/{form}', 'POST api/v1/data/{form}', 'GET api/v1/data/{form}/{record}', 'PATCH api/v1/data/{form}/{record}',
    'DELETE api/v1/data/{form}/{record}', 'POST api/v1/data/{form}/{record}/transitions/{transition}', 'GET api/v1/openapi.json',
    // Inbound endpoints: each checks its own secret or signature.
    'POST api/inbound/{slug}',
    // External surfaces (§4.32): the external guard, single-purpose tokens, CAPTCHA and throttles.
    'GET api/v1/x/forms/{slug}', 'POST api/v1/x/forms/{slug}', 'GET api/v1/x/forms/{slug}/challenge', 'POST api/v1/x/forms/{slug}/code',
    'POST api/v1/x/forms/{slug}/unlock', 'GET api/v1/x/links/{token}', 'POST api/v1/x/links/{token}', 'GET api/v1/x/portal',
    'GET api/v1/x/portal/forms', 'POST api/v1/x/portal/login', 'POST api/v1/x/portal/logout', 'GET api/v1/x/portal/records',
    'GET api/v1/x/portal/records/{slug}/{record}', 'POST api/v1/x/portal/register', 'POST api/v1/x/portal/resend', 'POST api/v1/x/portal/verify',
    'GET api/v1/x/signatures/{token}', 'POST api/v1/x/signatures/{token}/decline', 'GET api/v1/x/signatures/{token}/document',
    'POST api/v1/x/signatures/{token}/sign',
];

it('requires authentication on every API route that is not deliberately public', function () {
    $open = [];
    foreach (Route::getRoutes() as $route) {
        if (! str_starts_with($route->uri(), 'api/')) {
            continue;
        }
        $middleware = implode(',', $route->gatherMiddleware());
        if (str_contains($middleware, 'auth:')) {
            continue;
        }
        foreach (array_diff($route->methods(), ['HEAD']) as $method) {
            $open[] = "{$method} {$route->uri()}";
        }
    }
    sort($open);
    $allowed = PUBLIC_API_ROUTES;
    sort($allowed);
    expect(array_values(array_diff($open, $allowed)))->toBe([], 'unauthenticated API routes that are not on the public list')
        ->and(array_values(array_diff($allowed, $open)))->toBe([], 'public-list entries that no longer exist or now require sign-in');
});

it('names the data API and OpenAPI routes behind API tokens', function () {
    foreach (Route::getRoutes() as $route) {
        if (str_starts_with($route->uri(), 'api/v1/data/') || $route->uri() === 'api/v1/openapi.json') {
            expect($route->gatherMiddleware())->toContain('lcf.token');
        }
    }
});
