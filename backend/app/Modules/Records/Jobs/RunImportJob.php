<?php

declare(strict_types=1);

namespace App\Modules\Records\Jobs;

use App\Modules\Records\Exchange\ImportService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Throwable;

/**
 * Runs an import job on the `heavy` queue (architecture §8.1). The job is
 * keyed by its tracking row: running it again continues after the last
 * committed batch. Retrying a failed import from the Operations Center
 * dispatches it again.
 */
final class RunImportJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $tries = 1;

    public int $timeout = 7200;

    public function __construct(public readonly int $importJobId)
    {
        $this->onQueue('heavy');
    }

    public function handle(ImportService $imports): void
    {
        $imports->run($this->importJobId);
    }

    public function failed(Throwable $e): void
    {
        report($e);
        app(ImportService::class)->crashed($this->importJobId, $e);
    }
}
