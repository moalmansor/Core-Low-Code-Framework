<?php

declare(strict_types=1);

namespace App\Modules\Records\Jobs;

use App\Modules\Records\Exchange\ExportService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Throwable;

/**
 * Runs a large export on the `heavy` queue (architecture §8.1); small ones
 * run at once in the request. Retrying a failed export from the Operations
 * Center dispatches it again.
 */
final class RunExportJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $tries = 1;

    public int $timeout = 7200;

    public function __construct(public readonly int $exportJobId)
    {
        $this->onQueue('heavy');
    }

    public function handle(ExportService $exports): void
    {
        $exports->run($this->exportJobId);
    }

    public function failed(Throwable $e): void
    {
        report($e);
        app(ExportService::class)->crashed($this->exportJobId, $e);
    }
}
