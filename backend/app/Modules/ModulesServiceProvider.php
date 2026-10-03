<?php

declare(strict_types=1);

namespace App\Modules;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

/**
 * Loads every module's migrations and API routes (architecture §4–§5).
 * A module is a directory under app/Modules with optional
 * `Database/Migrations` and `routes.php`.
 */
final class ModulesServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        foreach (glob(__DIR__.'/*', GLOB_ONLYDIR) ?: [] as $moduleDir) {
            if (is_dir($moduleDir.'/Database/Migrations')) {
                $this->loadMigrationsFrom($moduleDir.'/Database/Migrations');
            }
            if (is_file($moduleDir.'/routes.php') && ! $this->app->routesAreCached()) {
                Route::prefix('api/v1')->middleware('api')->group($moduleDir.'/routes.php');
            }
        }
    }
}
