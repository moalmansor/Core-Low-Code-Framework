<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Modules\Core\Models\Setting;
use App\Modules\Core\Settings\SettingsRegistry;
use App\Modules\Core\Settings\SettingsService;
use Illuminate\Database\Seeder;

/** Default system settings: the secure baseline (specification §2, §5). */
final class SettingsSeeder extends Seeder
{
    public function run(SettingsRegistry $registry, SettingsService $settings): void
    {
        foreach ($registry->groups() as $group) {
            foreach ($registry->group($group) as $key => $definition) {
                if ($definition->secret || ($group === 'setup')) {
                    continue; // secrets and setup state start empty
                }
                if (! Setting::query()->where('group', $group)->where('key', $key)->exists()) {
                    $settings->write($group, $key, $definition->default);
                }
            }
        }
    }
}
