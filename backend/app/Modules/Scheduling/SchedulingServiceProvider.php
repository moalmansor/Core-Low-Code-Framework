<?php

declare(strict_types=1);

namespace App\Modules\Scheduling;

use Illuminate\Console\Events\ScheduledTaskFailed;
use Illuminate\Console\Events\ScheduledTaskFinished;
use Illuminate\Console\Scheduling\Event as ScheduledEvent;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Throwable;

/**
 * One scheduler per process, so the handlers modules register are the ones
 * `scheduler:run` uses. Runs of the system's own scheduled jobs are noted
 * for the scheduled-task manager.
 */
final class SchedulingServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(ScheduledTasks::class);
    }

    public function boot(): void
    {
        Event::listen(ScheduledTaskFinished::class, function (ScheduledTaskFinished $e): void {
            $this->note($e->task, 'succeeded', (int) round($e->runtime * 1000), null);
        });
        Event::listen(ScheduledTaskFailed::class, function (ScheduledTaskFailed $e): void {
            $this->note($e->task, 'failed', 0, $e->exception->getMessage());
        });
    }

    private function note(ScheduledEvent $task, string $status, int $ms, ?string $error): void
    {
        try {
            $name = preg_match("/artisan'?\\s+(.+)$/", (string) $task->command, $m) === 1 ? trim($m[1], "' ") : (string) ($task->description ?? $task->command);
            $timezone = $task->timezone instanceof \DateTimeZone ? $task->timezone->getName() : (string) $task->timezone;
            $this->app->make(ScheduledTasks::class)->recordSystem($name, $task->expression, $timezone, $status, $ms, $error);
        } catch (Throwable $e) {
            report($e); // never let bookkeeping break the scheduler
        }
    }
}
