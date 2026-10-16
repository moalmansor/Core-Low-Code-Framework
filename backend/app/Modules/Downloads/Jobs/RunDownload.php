<?php

declare(strict_types=1);

namespace App\Modules\Downloads\Jobs;

use App\Modules\Downloads\DownloadJobs;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Throwable;

/**
 * Produces a large download on the `heavy` queue (architecture §8.1, §19.8);
 * small ones are produced in the request. A failed download is retried from
 * its owner's dialog or the Operations Center, which dispatches it again.
 */
final class RunDownload implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $tries = 1;

    public int $timeout = 7200;

    public function __construct(public readonly int $downloadJobId)
    {
        $this->onQueue('heavy');
        if (config('queue.default') === 'redis') {
            $this->onConnection('redis_heavy');
        }
    }

    public function handle(DownloadJobs $jobs): void
    {
        $jobs->run($this->downloadJobId);
    }

    public function failed(Throwable $e): void
    {
        report($e);
        app(DownloadJobs::class)->crashed($this->downloadJobId);
    }
}
