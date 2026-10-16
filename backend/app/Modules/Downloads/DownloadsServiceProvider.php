<?php

declare(strict_types=1);

namespace App\Modules\Downloads;

use App\Modules\Scheduling\ScheduledTasks;
use Illuminate\Support\ServiceProvider;

/** Scheduled downloads run from the scheduler, one run per recipient. */
final class DownloadsServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->app->make(ScheduledTasks::class)->handle('download', static fn (object $task) => app(DownloadSchedules::class)->run($task));
    }
}
