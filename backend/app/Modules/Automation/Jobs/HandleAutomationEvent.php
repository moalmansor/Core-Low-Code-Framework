<?php

declare(strict_types=1);

namespace App\Modules\Automation\Jobs;

use App\Modules\Automation\AutomationEngine;
use App\Modules\Core\Tenancy\TenantContext;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;

/** Turns one committed record event into the automation runs it triggers, on the `default` queue. */
final class HandleAutomationEvent implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $tries = 3;

    /** @param  array<string, mixed>  $payload */
    public function __construct(public readonly string $eventType, public readonly array $payload, public readonly int $organizationId) {}

    public function handle(AutomationEngine $engine, TenantContext $tenant): void
    {
        $tenant->set($this->organizationId);
        $engine->onEvent($this->eventType, $this->payload);
    }
}
