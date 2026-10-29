<?php

declare(strict_types=1);

namespace App\Modules\Integrations\Jobs;

use App\Modules\Core\Tenancy\TenantContext;
use App\Modules\Integrations\SyncJobs;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\DB;

/** Runs one queued sync run (ADR-0057). A failed run is retried from the Operations Center, not by the queue. */
final class RunSync implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $tries = 1;

    public int $timeout = 1800;

    public function __construct(public readonly int $runId) {}

    public function handle(SyncJobs $sync, TenantContext $tenant): void
    {
        $org = DB::table('sync_runs')->where('id', $this->runId)->value('organization_id');
        if ($org === null) {
            return;
        }
        $tenant->set((int) $org);
        $sync->execute($this->runId);
    }
}
