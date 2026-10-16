<?php

declare(strict_types=1);

namespace App\Modules\DataQuality\Jobs;

use App\Modules\Core\Tenancy\TenantContext;
use App\Modules\DataQuality\DataRepair;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\DB;

/** Runs a data repair scan (broken links or invalid records), on the `heavy` queue (architecture §8.1). */
final class RunDataRepair implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $tries = 1;

    public int $timeout = 1800;

    public function __construct(public readonly int $operationId)
    {
        $this->onQueue('heavy');
        if (config('queue.default') === 'redis') {
            $this->onConnection('redis_heavy');
        }
    }

    public function handle(DataRepair $service, TenantContext $tenant): void
    {
        $org = DB::table('bulk_operations')->where('id', $this->operationId)->value('organization_id');
        if ($org !== null) {
            $tenant->set((int) $org);
        }
        $service->run($this->operationId);
    }
}
