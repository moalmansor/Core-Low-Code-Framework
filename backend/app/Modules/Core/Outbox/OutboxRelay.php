<?php

declare(strict_types=1);

namespace App\Modules\Core\Outbox;

use App\Infrastructure\Database\Contracts\DatabaseDriver;
use App\Modules\Core\Models\OutboxEvent;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Delivers committed outbox events to their registered handlers. Rows are
 * claimed with SKIP LOCKED / READPAST so several workers never deliver the same
 * event twice; handlers must be idempotent.
 */
final class OutboxRelay
{
    /** @var array<string, list<callable(OutboxEvent): void>> */
    private array $handlers = [];

    public function __construct(private readonly DatabaseDriver $driver) {}

    /** @param callable(OutboxEvent): void $handler */
    public function on(string $eventType, callable $handler): void
    {
        $this->handlers[$eventType][] = $handler;
    }

    public function relayOne(int $id): void
    {
        $this->relay(DB::table('outbox_events')->where('id', $id)->whereNull('dispatched_at'));
    }

    public function relayPending(int $limit = 200): int
    {
        return $this->relay(
            DB::table('outbox_events')->whereNull('dispatched_at')->where('available_at', '<=', now())->orderBy('id')->limit($limit),
        );
    }

    private function relay(Builder $query): int
    {
        $count = 0;
        DB::transaction(function () use ($query, &$count): void {
            $ids = $this->driver->skipLocked($query)->pluck('id')->all();
            foreach (OutboxEvent::query()->whereIn('id', $ids)->orderBy('id')->get() as $event) {
                try {
                    foreach ($this->handlers[$event->event_type] ?? [] as $handler) {
                        $handler($event);
                    }
                    $event->forceFill(['dispatched_at' => now(), 'attempts' => $event->attempts + 1, 'last_error' => null])->save();
                    $count++;
                } catch (Throwable $e) {
                    $event->forceFill([
                        'attempts' => $event->attempts + 1,
                        'last_error' => mb_substr($e::class.': '.$e->getMessage(), 0, 2000),
                        'available_at' => now()->addSeconds(min(3600, 2 ** min(12, $event->attempts + 1))),
                    ])->save();
                    report($e);
                }
            }
        });

        return $count;
    }
}
