<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Jobs;

use App\Modules\Core\Tenancy\TenantContext;
use App\Modules\Notifications\Notifier;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;

/**
 * Turns one committed record event into notifications, on the `default`
 * queue (architecture §8.1), so saving a record never waits for templates
 * to render.
 */
final class HandleNotificationEvent implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $tries = 3;

    /** @param  array<string, mixed>  $payload */
    public function __construct(public readonly string $eventType, public readonly array $payload, public readonly int $organizationId) {}

    public function handle(Notifier $notifier, TenantContext $tenant): void
    {
        $tenant->set($this->organizationId);
        $p = $this->payload;
        match ($this->eventType) {
            'record.saved' => $this->saved($notifier, $p),
            'record.transitioned' => $notifier->handle('status_changed', $p),
            'sla.warning' => $notifier->handle('sla_warning', $p),
            'sla.escalation' => $this->escalation($notifier, $p),
            'approval.requested' => $notifier->handle('approval_requested', $p),
            'record.assigned' => $this->assigned($notifier, $p),
            default => null,
        };
    }

    /** @param  array<string, mixed>  $p */
    private function saved(Notifier $notifier, array $p): void
    {
        if (($p['operation'] ?? null) === 'create') {
            $notifier->handle('record_created', $p);
        } else {
            $notifier->handle('record_updated', $p);
            $notifier->handle('field_changed', $p);
        }
        if (($p['notification_conditions'] ?? []) !== []) {
            $notifier->handle('condition_met', $p);
        }
    }

    /** @param  array<string, mixed>  $p */
    private function escalation(Notifier $notifier, array $p): void
    {
        $notifier->escalated($p);
        $notifier->handle('sla_escalation', $p);
    }

    /** @param  array<string, mixed>  $p */
    private function assigned(Notifier $notifier, array $p): void
    {
        $notifier->assigned($p);
        $notifier->handle('assigned', $p);
    }
}
