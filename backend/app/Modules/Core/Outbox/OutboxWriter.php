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
    /** @var list<array<string, mixed>> context added to events published inside within() */
    private array $context = [];

    /** @var list<int> events published since the last relay */
    private array $pending = [];

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
        $top = $this->context === [] ? [] : $this->context[array_key_last($this->context)];
        if ($top !== []) {
            $payload['_context'] = $top;
        }
        $event = OutboxEvent::query()->create([
            'organization_id' => $this->tenant->organizationId(),
            'event_type' => $eventType,
            'payload' => $payload,
            'correlation_id' => $this->correlation->get(),
            'available_at' => now(),
            'attempts' => 0,
        ]);
        // Relay right after commit, all of a transaction's events at once (an import batch publishes hundreds);
        // the scheduled relay is the safety net. Ids of a transaction that rolled back match no row.
        // The first callback to run relays everything pending; the others find nothing left.
        $this->pending[] = (int) $event->id;
        DB::afterCommit(function (): void {
            [$ids, $this->pending] = [$this->pending, []];
            if ($ids !== []) {
                $this->relay->relayMany($ids);
            }
        });

        return $event;
    }

    /**
     * Runs `$fn` with `$context` attached (as `_context`) to every event it
     * publishes; automations use it so the events their steps cause carry
     * the run that caused them (loop protection, ADR-0043).
     *
     * @template T
     *
     * @param  array<string, mixed>  $context
     * @param  callable(): T  $fn
     * @return T
     */
    public function within(array $context, callable $fn): mixed
    {
        $this->context[] = $context;
        try {
            return $fn();
        } finally {
            array_pop($this->context);
        }
    }
}
