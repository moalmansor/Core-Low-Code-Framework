<?php

declare(strict_types=1);

namespace App\Modules\Notifications;

use App\Modules\Core\I18n\TranslatableRegistry;
use App\Modules\Core\Models\OutboxEvent;
use App\Modules\Core\Outbox\OutboxRelay;
use App\Modules\Notifications\Jobs\HandleNotificationEvent;
use App\Modules\Notifications\Models\EmailTemplate;
use App\Modules\Notifications\Models\NotificationChannel;
use App\Modules\Notifications\Models\NotificationRule;
use App\Modules\Records\Runtime\RecordObservers;
use Illuminate\Support\ServiceProvider;

/**
 * Wires notifications to the rest of the system: the record events they
 * listen to (through the outbox, so they fire only for committed changes),
 * the "condition became true" check during saves, and their translatable
 * texts.
 */
final class NotificationsServiceProvider extends ServiceProvider
{
    public const EVENTS = ['record.saved', 'record.transitioned', 'sla.warning', 'sla.escalation', 'approval.requested', 'record.assigned'];

    public function boot(): void
    {
        $r = $this->app->make(TranslatableRegistry::class);
        $r->register('email_template', EmailTemplate::class, ['subject', 'preheader'], 'translations.type.email_template');
        $r->register('notification_rule', NotificationRule::class, ['name'], 'translations.type.notification_rule');
        $r->register('notification_channel', NotificationChannel::class, ['name'], 'translations.type.notification_channel');

        $relay = $this->app->make(OutboxRelay::class);
        foreach (self::EVENTS as $type) {
            $relay->on($type, static function (OutboxEvent $e): void {
                // Record events of a form without notification rules are not queued (an import publishes one per row).
                if (! in_array($e->event_type, ['record.saved', 'record.transitioned'], true) || ! is_string($e->payload['form'] ?? null)
                    || Notifier::formHasRules($e->payload['form'])) {
                    HandleNotificationEvent::dispatch($e->event_type, $e->payload, $e->organization_id);
                }
            });
        }
        $this->app->make(RecordObservers::class)->observe(static fn ($rt, ?array $old, array $new) => Notifier::conditionRules($rt->form->id)->isEmpty()
            ? [] : app(Notifier::class)->conditionsMet($rt, $old, $new));
    }
}
