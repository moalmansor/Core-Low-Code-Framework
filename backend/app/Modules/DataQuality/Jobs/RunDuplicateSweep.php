<?php

declare(strict_types=1);

namespace App\Modules\DataQuality\Jobs;

use App\Modules\Core\Tenancy\TenantContext;
use App\Modules\DataQuality\DuplicateRules;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\DB;

/** Finds duplicate groups for a duplicate rule over a form's records, on the `heavy` queue (architecture §8.1). */
final class RunDuplicateSweep implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $tries = 1;

    public int $timeout = 1800;

    public function __construct(public readonly int $operationId)
    {
        $this->onQueue('heavy');
    }

    public function handle(DuplicateRules $service, TenantContext $tenant): void
    {
        $org = DB::table('bulk_operations')->where('id', $this->operationId)->value('organization_id');
        if ($org !== null) {
            $tenant->set((int) $org);
        }
        $service->runSweep($this->operationId);
    }
}
