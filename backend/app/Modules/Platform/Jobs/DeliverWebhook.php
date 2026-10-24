<?php

declare(strict_types=1);

namespace App\Modules\Platform\Jobs;

use App\Modules\Core\Tenancy\TenantContext;
use App\Modules\Platform\Webhooks;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\DB;

/**
 * Sends one webhook delivery. The payload travels encrypted in the job (the
 * delivery log keeps only a masked copy); attempts and backoff are managed by
 * Webhooks, so the job itself runs once.
 */
final class DeliverWebhook implements ShouldBeEncrypted, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $tries = 1;

    public int $timeout = 90;

    public function __construct(public readonly int $deliveryId, public readonly string $event, public readonly string $json) {}

    public function handle(Webhooks $webhooks, TenantContext $tenant): void
    {
        $org = DB::table('webhook_deliveries')->where('id', $this->deliveryId)->value('organization_id');
        if ($org === null) {
            return;
        }
        $tenant->set((int) $org);
        $webhooks->deliver($this->deliveryId, $this->event, $this->json);
    }
}
