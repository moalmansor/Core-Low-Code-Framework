<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Jobs;

use App\Modules\Core\Tenancy\TenantContext;
use App\Modules\Notifications\Channels\ChannelDeliveries;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\DB;

/**
 * Sends one SMS or messaging delivery on the `mail` queue. The delivery row
 * carries the attempts and the backoff; the next attempt is a new job.
 */
final class SendChannelDelivery implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $tries = 1;

    public int $timeout = 60;

    public function __construct(public readonly int $deliveryId)
    {
        $this->onQueue('mail');
    }

    public function handle(ChannelDeliveries $deliveries, TenantContext $tenant): void
    {
        $org = DB::table('notification_deliveries')->where('id', $this->deliveryId)->value('organization_id');
        if ($org === null) {
            return;
        }
        $tenant->set((int) $org);
        $delay = $deliveries->attempt($this->deliveryId);
        // A synchronous queue (tests, a single-process install) cannot wait: the row stays pending for the next run.
        if ($delay !== null && config('queue.default') !== 'sync') {
            self::dispatch($this->deliveryId)->delay(now()->addSeconds($delay));
        }
    }
}
