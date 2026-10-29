<?php

declare(strict_types=1);

namespace App\Modules\Retention\Jobs;

use App\Modules\Core\Tenancy\TenantContext;
use App\Modules\Retention\RetentionEngine;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\DB;

/** Runs one retention policy on the heavy queue (§4.26). A failed run is recorded in its run row and run again on the next schedule, not by the queue. */
final class RunRetentionPolicy implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $tries = 1;

    public int $timeout = 3600;

    public function __construct(public readonly int $policyId, public readonly ?int $userId)
    {
        $this->onQueue('heavy');
        // The heavy workers listen on their own connection (config/horizon.php); on the default one nothing runs it.
        if (config('queue.default') === 'redis') {
            $this->onConnection('redis_heavy');
        }
    }

    public function handle(RetentionEngine $engine, TenantContext $tenant): void
    {
        $org = DB::table('retention_policies')->where('id', $this->policyId)->value('organization_id');
        if ($org === null) {
            return;
        }
        $tenant->set((int) $org);
        $engine->run($this->policyId, $this->userId);
    }
}
