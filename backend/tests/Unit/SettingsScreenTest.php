<?php

declare(strict_types=1);

use App\Modules\Admin\ConsoleAreas;
use App\Modules\Core\Http\Controllers\SettingsController;
use App\Modules\Core\Settings\SettingsRegistry;

/*
 * Every setting an administrator may change is on a screen (specification:
 * everything configurable from the admin UI). The System Settings form is
 * described by frontend/src/views/admin/settingsSchema.ts; a setting added to
 * the registry without a field there could only be changed in the database.
 */

/** Groups edited by their own screens or never edited: the reason is the value. */
const SETTINGS_ELSEWHERE = [
    'setup' => 'written by the setup wizard only',
    'tenancy' => 'fixed at installation (immutable)',
    'branding' => 'Appearance & Branding screen',
    'sso' => 'the SSO providers editor of System Settings',
    'maintenance' => 'the system maintenance dialog of Applications, with its translatable message',
    'external' => 'portal registration settings of External access (ADR-0058)',
];

it('has a System Settings field for every setting', function () {
    $schema = (string) file_get_contents(dirname(__DIR__, 3).'/frontend/src/views/admin/settingsSchema.ts');
    $missing = [];
    foreach (app(SettingsRegistry::class)->groups() as $group) {
        if (isset(SETTINGS_ELSEWHERE[$group])) {
            continue;
        }
        if (preg_match('/^  '.preg_quote($group, '/').': \[(.*?)^  \],?$|^  '.preg_quote($group, '/').': \[(.*?)\],?$/ms', $schema, $m) !== 1) {
            $missing[] = $group;

            continue;
        }
        $block = ($m[1] ?? '').($m[2] ?? '');
        foreach (array_keys(app(SettingsRegistry::class)->group($group)) as $key) {
            if (! str_contains($block, "'{$key}'")) {
                $missing[] = "{$group}.{$key}";
            }
        }
    }
    expect($missing)->toBe([]);
});

/*
 * Every screen an administrator can reach is served (the unreachable-screen
 * guard; e2e/24-routes.spec.ts opens them in a browser). These run in the fast
 * suite, so a tab whose group the API does not serve, or a console tile whose
 * route the client does not have, fails before any browser starts.
 */

/** Tabs of System Settings with their own endpoint instead of /settings/{group}: the reason is the value. */
const TABS_ELSEWHERE = [
    'egress' => 'the egress allowlist editor (/egress/allowlist)',
    'locales' => 'the language manager (/locales)',
];

/** @return list<string> */
function settingsTabs(): array
{
    $view = (string) file_get_contents(dirname(__DIR__, 3).'/frontend/src/views/admin/SettingsView.vue');
    preg_match('/const tabs = computed\(\(\) => \{(.*?)\n\}\)/s', $view, $m);
    preg_match_all("/'([a-z_]+)'/", preg_replace("/session\.can\('[^']+'\)/", '', $m[1] ?? ''), $keys);

    return array_values(array_unique($keys[1]));
}

it('serves every System Settings tab, and has a tab for every group it serves', function () {
    $tabs = settingsTabs();
    expect(count($tabs))->toBeGreaterThan(10);
    $served = SettingsController::EDITABLE;
    $registry = app(SettingsRegistry::class);
    // A tab whose group the API refuses (404) is a screen nobody can open.
    $unserved = array_values(array_filter($tabs, static fn (string $g) => ! isset(TABS_ELSEWHERE[$g]) && (! in_array($g, $served, true) || $registry->group($g) === [])));
    expect($unserved)->toBe([]);
    // A group the API serves without a tab could be changed only through the API.
    expect(array_values(array_diff($served, $tabs)))->toBe([]);
});

it('has a client route for every Admin Console area', function () {
    $paths = [];
    foreach (glob(dirname(__DIR__, 3).'/frontend/src/router/*.ts') ?: [] as $file) {
        preg_match_all("/path: '([^']+)'/", (string) file_get_contents($file), $m);
        foreach ($m[1] as $path) {
            $paths[] = '/'.ltrim($path, '/');
        }
    }
    $missing = [];
    foreach (ConsoleAreas::BUILT as $key => [, , $route]) {
        $found = false;
        foreach ($paths as $path) {
            // Each optional or required parameter matches one segment; an optional one may also be absent.
            $pattern = preg_replace(['#/:[a-z_]+(\([^)]*\))?\?#', '#/:[a-z_]+(\([^)]*\))?#'], ['(/[^/]+)?', '/[^/]+'], $path);
            if (preg_match('#^'.$pattern.'$#', $route) === 1) {
                $found = true;
                break;
            }
        }
        $found || $missing[] = "{$key} {$route}";
    }
    expect($missing)->toBe([]);
});
