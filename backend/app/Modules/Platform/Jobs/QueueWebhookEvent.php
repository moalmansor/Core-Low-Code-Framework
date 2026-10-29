<?php

declare(strict_types=1);

namespace App\Modules\Platform\Jobs;

use App\Modules\Core\Tenancy\TenantContext;
use App\Modules\Platform\Webhooks;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;

/** Turns one committed record event into a delivery per subscribed webhook, on the `default` queue. */
final class QueueWebhookEvent implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $tries = 3;

    /** @param  array<string, mixed>  $payload */
    public function __construct(public readonly string $eventType, public readonly array $payload, public readonly int $organizationId) {}

    public function handle(Webhooks $webhooks, TenantContext $tenant): void
    {
        $tenant->set($this->organizationId);
        $webhooks->fanOut($this->eventType, $this->payload, $this->organizationId);
    }
}
