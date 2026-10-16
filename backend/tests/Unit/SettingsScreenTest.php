<?php

declare(strict_types=1);

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
