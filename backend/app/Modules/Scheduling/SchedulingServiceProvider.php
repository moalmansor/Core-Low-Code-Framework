<?php

declare(strict_types=1);

namespace App\Modules\Scheduling;

use Illuminate\Support\ServiceProvider;

/** One scheduler per process, so the handlers modules register are the ones `scheduler:run` uses. */
final class SchedulingServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(ScheduledTasks::class);
    }
}
