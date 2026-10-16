<?php

declare(strict_types=1);

namespace App\Modules\Automation;

use App\Modules\Automation\Jobs\HandleAutomationEvent;
use App\Modules\Automation\Models\Automation;
use App\Modules\Core\I18n\TranslatableRegistry;
use App\Modules\Core\Models\OutboxEvent;
use App\Modules\Core\Outbox\OutboxRelay;
use App\Modules\Records\Runtime\RecordObservers;
use App\Modules\Scheduling\ScheduledTasks;
use Illuminate\Support\ServiceProvider;

/**
 * Wires automations to the rest of the system: the committed record events
 * that trigger them (through the outbox), the "condition became true" check
 * during saves, their schedule triggers and their translatable texts.
 */
final class AutomationServiceProvider extends ServiceProvider
{
    public const EVENTS = ['record.saved', 'record.transitioned', 'record.deleted'];

    public function boot(): void
    {
        $this->app->make(TranslatableRegistry::class)->register('automation', Automation::class, ['name', 'description'], 'translations.type.automation');
        $relay = $this->app->make(OutboxRelay::class);
        foreach (self::EVENTS as $type) {
            $relay->on($type, static function (OutboxEvent $e): void {
                HandleAutomationEvent::dispatch($e->event_type, $e->payload, $e->organization_id);
            });
        }
        $this->app->make(RecordObservers::class)->observe(static fn ($rt, ?array $old, array $new) => app(AutomationEngine::class)->conditionsMet($rt, $old, $new));
        $this->app->make(ScheduledTasks::class)->handle('automation', static fn (object $task) => app(AutomationEngine::class)->scheduled($task));
    }
}
