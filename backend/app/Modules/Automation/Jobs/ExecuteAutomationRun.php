<?php

declare(strict_types=1);

namespace App\Modules\Automation\Jobs;

use App\Modules\Automation\AutomationEngine;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;

/**
 * Executes (or continues) one automation run on the `default` queue
 * (architecture §8.1). The run is claimed atomically, so a duplicate job
 * does nothing; failures are recorded on the run, never retried blindly.
 */
final class ExecuteAutomationRun implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $tries = 1;

    public int $timeout = 300;

    public function __construct(public readonly int $runId) {}

    public function handle(AutomationEngine $engine): void
    {
        $engine->execute($this->runId);
    }
}
