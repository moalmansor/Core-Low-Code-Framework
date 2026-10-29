<?php

declare(strict_types=1);

namespace App\Modules\Retention\Jobs;

use App\Modules\Core\Tenancy\TenantContext;
use App\Modules\Retention\PersonalData;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\DB;

/** Searches for a personal data request's subject across forms and logs (§4.26). */
final class LocatePersonalData implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $tries = 1;

    public int $timeout = 1800;

    public function __construct(public readonly int $requestId)
    {
        $this->onQueue('heavy');
        // The heavy workers listen on their own connection (config/horizon.php); on the default one nothing runs it.
        if (config('queue.default') === 'redis') {
            $this->onConnection('redis_heavy');
        }
    }

    public function handle(PersonalData $personal, TenantContext $tenant): void
    {
        $org = DB::table('personal_data_requests')->where('id', $this->requestId)->value('organization_id');
        if ($org === null) {
            return;
        }
        $tenant->set((int) $org);
        $personal->locate($this->requestId);
    }
}
