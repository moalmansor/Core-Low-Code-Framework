<?php

declare(strict_types=1);

namespace App\Modules\Core\Outbox;

use App\Modules\Core\Correlation\CorrelationId;
use App\Modules\Core\Models\OutboxEvent;
use App\Modules\Core\Tenancy\TenantContext;
use Illuminate\Support\Facades\DB;

/**
 * Transactional outbox (architecture §8.3, ADR-0010): events are written in the
 * same transaction as the change and relayed only after it commits.
 */
final class OutboxWriter
{
    public function __construct(
        private readonly TenantContext $tenant,
        private readonly CorrelationId $correlation,
        private readonly OutboxRelay $relay,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public function publish(string $eventType, array $payload): OutboxEvent
    {
        $event = OutboxEvent::query()->create([
            'organization_id' => $this->tenant->organizationId(),
            'event_type' => $eventType,
            'payload' => $payload,
            'correlation_id' => $this->correlation->get(),
            'available_at' => now(),
            'attempts' => 0,
        ]);
        // Relay right after commit; the scheduled relay is the safety net.
        DB::afterCommit(fn () => $this->relay->relayOne((int) $event->id));

        return $event;
    }
}
