<?php

declare(strict_types=1);

namespace App\Modules\Extensions;

use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

/** Developer extensions (specification §4.19, ADR-0051). */
final class ExtensionsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // One runner per request or job: it keeps the deployed hooks and the failures to report.
        $this->app->scoped(HookRunner::class);
    }

    public function boot(): void
    {
        // A failure inside a queued job is written once the job is over.
        Event::listen(JobProcessed::class, fn () => $this->app->make(HookRunner::class)->flush());
    }
}
