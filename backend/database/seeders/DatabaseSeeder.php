<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * Install seed (specification §2): only the platform organization, the default
 * locales, the core roles, the system permission catalog with its role grants,
 * and the default settings. No business forms, demo modules, or sample data.
 * Every seeder is idempotent and safe to re-run.
 */
final class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            PlatformSeeder::class,
            AccessSeeder::class,
            SettingsSeeder::class,
        ]);
    }
}
