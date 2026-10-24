<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Channels;

use App\Modules\Core\Correlation\CorrelationId;
use App\Modules\Notifications\Jobs\SendChannelDelivery;
use App\Modules\Notifications\Models\NotificationChannel;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;

/**
 * The delivery log of SMS and messaging channels (notification_deliveries,
 * ADR-0056): one row per message, sent by a queued job, retried with backoff
 * when the provider fails in a way that may pass (a timeout, a 5xx, a 429),
 * held back when the channel's per-minute limit is reached, and shown in the
 * Operations Center when it finally fails.
 */
final class ChannelDeliveries
{
    public const MAX_ATTEMPTS = 4;

    /** Minutes before the 2nd, 3rd and 4th attempt. */
    private const BACKOFF_MINUTES = [1, 5, 30];

    public function __construct(private readonly ChannelProviders $providers, private readonly CorrelationId $correlation) {}

    /**
     * Logs a message for sending and queues it.
     *
     * @param  array{to: string, title: string, text: string, link: string}  $message
     * @param  array{form_id?: int|null, record_id?: int|null, rule_id?: int|null}  $context
     */
    public function queue(NotificationChannel $channel, ?int $userId, array $message, array $context): int
    {
        $now = Carbon::now('UTC')->format('Y-m-d H:i:s.u');
        $id = (int) DB::table('notification_deliveries')->insertGetId([
            'organization_id' => $channel->organization_id, 'created_at' => $now, 'updated_at' => $now,
            'notification_channel_id' => $channel->id, 'notification_rule_id' => $context['rule_id'] ?? null, 'user_id' => $userId,
            'recipient' => mb_substr($message['to'], 0, 255),
            'payload' => json_encode(['title' => $message['title'], 'text' => $message['text'], 'link' => $message['link'],
                'form_id' => $context['form_id'] ?? null, 'record_id' => $context['record_id'] ?? null], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'status' => 'pending', 'attempts' => 0, 'correlation_id' => $this->correlation->get(),
        ]);
        SendChannelDelivery::dispatch($id)->afterCommit();

        return $id;
    }

    /**
     * One attempt. Returns the delay in seconds before the next one, or null when done.
     */
    public function attempt(int $id): ?int
    {
        $d = DB::table('notification_deliveries')->where('id', $id)->first();
        if ($d === null || $d->status !== 'pending') {
            return null;
        }
        /** @var NotificationChannel|null $channel */
        $channel = NotificationChannel::query()->withoutGlobalScope('organization')->find($d->notification_channel_id);
        $now = Carbon::now('UTC')->format('Y-m-d H:i:s.u');
        if ($channel === null || ! $channel->is_active) {
            DB::table('notification_deliveries')->where('id', $id)->update(['status' => 'cancelled', 'error' => __('notifications.channel.inactive'), 'updated_at' => $now]);

            return null;
        }
        if ($channel->rate_limit_per_minute !== null) {
            $key = 'notification-channel:'.$channel->id;
            if (RateLimiter::tooManyAttempts($key, $channel->rate_limit_per_minute)) {
                // Held back, not failed: it goes out when the minute is over.
                return max(1, RateLimiter::availableIn($key));
            }
            RateLimiter::hit($key, 60);
        }
        $payload = json_decode((string) $d->payload, true) ?: [];
        $attempt = (int) $d->attempts + 1;
        try {
            $answer = $this->providers->send($channel->type, $channel->provider, (array) $channel->config,
                ['to' => (string) $d->recipient, 'title' => (string) ($payload['title'] ?? ''), 'text' => (string) ($payload['text'] ?? ''), 'link' => (string) ($payload['link'] ?? '')]);
            DB::table('notification_deliveries')->where('id', $id)->update(['status' => 'sent', 'attempts' => $attempt, 'sent_at' => $now, 'error' => null, 'updated_at' => $now]);
            unset($answer);

            return null;
        } catch (ChannelFailure $e) {
            $again = $e->retryable && $attempt < self::MAX_ATTEMPTS;
            DB::table('notification_deliveries')->where('id', $id)->update([
                'status' => $again ? 'pending' : 'failed', 'attempts' => $attempt, 'error' => mb_substr($e->getMessage(), 0, 2000), 'updated_at' => $now,
            ]);

            return $again ? self::BACKOFF_MINUTES[$attempt - 1] * 60 : null;
        }
    }

    /** A failed or cancelled delivery goes out again (from the Operations Center). */
    public function retry(int $id): void
    {
        $updated = DB::table('notification_deliveries')->where('id', $id)->whereIn('status', ['failed', 'cancelled'])
            ->update(['status' => 'pending', 'attempts' => 0, 'error' => null, 'updated_at' => Carbon::now('UTC')->format('Y-m-d H:i:s.u')]);
        if ($updated > 0) {
            SendChannelDelivery::dispatch($id);
        }
    }
}
