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
 * One 500-row batch of an insert-mode import, on the `heavy` queue, so the
 * stack's heavy workers share a large import (ADR-0072). The batch commits
 * once: delivered again, it finds its row in import_job_batches and stops.
 */
final class RunImportBatchJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $tries = 3;

    public int $timeout = 1800;

    /** @var list<int> seconds before the second and third attempt */
    public array $backoff = [10, 60];

    public function __construct(public readonly int $importJobId, public readonly int $batchNo)
    {
        $this->onQueue('heavy');
        if (config('queue.default') === 'redis') {
            $this->onConnection('redis_heavy');
        }
    }

    public function handle(ImportService $imports): void
    {
        $imports->runBatch($this->importJobId, $this->batchNo);
    }

    public function failed(Throwable $e): void
    {
        report($e);
        app(ImportService::class)->crashed($this->importJobId, $e);
    }
}
