<?php

declare(strict_types=1);

namespace App\Modules\Actions\Jobs;

use App\Modules\Actions\ActionRunner;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * A run of an action on more records than its threshold, in the background.
 * Records already done are skipped on retry, and each record's saves are
 * journaled under the run, so a retried run never changes a record twice.
 */
final class RunAction implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 3600;

    public function __construct(public readonly int $operationId)
    {
        $this->onQueue('default');
    }

    public function handle(ActionRunner $runner): void
    {
        $runner->execute($this->operationId, true);
    }

    public function failed(\Throwable $e): void
    {
        app(ActionRunner::class)->fail($this->operationId, $e->getMessage());
    }

    /** @return list<int> */
    public function backoff(): array
    {
        return [30, 120];
    }
}
