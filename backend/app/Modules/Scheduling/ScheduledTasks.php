<?php

declare(strict_types=1);

namespace App\Modules\Scheduling;

use App\Modules\Core\Correlation\CorrelationId;
use App\Modules\Core\Tenancy\TenantContext;
use Cron\CronExpression;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

/**
 * The scheduler (architecture §8.3): `scheduled_tasks` rows with a cron
 * expression in their own time zone. Every minute `scheduler:run` claims the
 * tasks that are due, atomically (`claimed_until`), so a task runs once even
 * with several schedulers; each kind has a handler registered by its module.
 * A run records when it ran, how long it took and how it ended, then the next
 * run is computed. A task whose handler fails is not retried before its next
 * time; the error is kept for the Operations Center.
 */
final class ScheduledTasks
{
    private const CLAIM_MINUTES = 30;

    /** @var array<string, callable(object): void> */
    private array $handlers = [];

    public function __construct(private readonly TenantContext $tenant, private readonly CorrelationId $correlation) {}

    /** @param  callable(object): void  $handler  receives the scheduled_tasks row */
    public function handle(string $kind, callable $handler): void
    {
        $this->handlers[$kind] = $handler;
    }

    public static function valid(string $cron, string $timezone): bool
    {
        if (! CronExpression::isValidExpression($cron) || ! in_array($timezone, timezone_identifiers_list(), true)) {
            return false;
        }

        return true;
    }

    public function next(string $cron, string $timezone, ?Carbon $after = null): Carbon
    {
        $after ??= Carbon::now('UTC');
        $at = (new CronExpression($cron))->getNextRunDate($after->copy()->setTimezone($timezone)->toDateTime(), 0, false, $timezone);

        return Carbon::instance($at)->setTimezone('UTC');
    }

    /** Creates or updates the task an owner (a download schedule, an automation…) runs on. */
    public function put(?int $id, string $kind, string $ownerType, int $ownerId, string $name, string $cron, string $timezone, bool $enabled): int
    {
        $now = Carbon::now('UTC');
        $values = [
            'updated_at' => $now->format('Y-m-d H:i:s.u'), 'name' => mb_substr($name, 0, 255), 'cron_expression' => $cron, 'timezone' => $timezone,
            'is_enabled' => $enabled, 'next_run_at' => $this->next($cron, $timezone, $now)->format('Y-m-d H:i:s.u'),
        ];
        if ($id !== null && DB::table('scheduled_tasks')->where('id', $id)->exists()) {
            DB::table('scheduled_tasks')->where('id', $id)->update($values);

            return $id;
        }

        return (int) DB::table('scheduled_tasks')->insertGetId($values + [
            'uuid' => (string) Str::uuid7(), 'organization_id' => $this->tenant->organizationId(), 'created_at' => $now->format('Y-m-d H:i:s.u'),
            'kind' => $kind, 'owner_type' => $ownerType, 'owner_id' => $ownerId,
        ]);
    }

    public function disable(?int $id): void
    {
        if ($id !== null) {
            DB::table('scheduled_tasks')->where('id', $id)->update(['is_enabled' => false, 'updated_at' => Carbon::now('UTC')->format('Y-m-d H:i:s.u')]);
        }
    }

    /** Claims and runs every due task; returns how many ran. */
    public function runDue(?Carbon $now = null): int
    {
        $now ??= Carbon::now('UTC');
        $stamp = $now->format('Y-m-d H:i:s.u');
        $host = mb_substr((string) gethostname().':'.getmypid(), 0, 128);
        $ran = 0;
        $due = DB::table('scheduled_tasks')->where('is_enabled', true)->where('next_run_at', '<=', $stamp)
            ->where(static fn ($q) => $q->whereNull('claimed_until')->orWhere('claimed_until', '<', $stamp))
            ->orderBy('next_run_at')->limit(100)->pluck('id');
        foreach ($due as $id) {
            $claimed = DB::table('scheduled_tasks')->where('id', $id)->where('is_enabled', true)->where('next_run_at', '<=', $stamp)
                ->where(static fn ($q) => $q->whereNull('claimed_until')->orWhere('claimed_until', '<', $stamp))
                ->update(['claimed_until' => $now->copy()->addMinutes(self::CLAIM_MINUTES)->format('Y-m-d H:i:s.u'), 'claimed_by' => $host, 'last_status' => 'running']);
            if ($claimed === 0) {
                continue; // another scheduler took it
            }
            $task = DB::table('scheduled_tasks')->where('id', $id)->first();
            $started = microtime(true);
            $status = 'succeeded';
            $error = null;
            $this->correlation->set((string) Str::uuid7());
            try {
                $handler = $this->handlers[$task->kind] ?? null;
                if ($handler === null) {
                    $status = 'skipped';
                } else {
                    $handler($task);
                }
            } catch (Throwable $e) {
                report($e);
                $status = 'failed';
                $error = mb_substr($e->getMessage(), 0, 2000);
            }
            DB::table('scheduled_tasks')->where('id', $id)->update([
                'last_run_at' => $stamp, 'last_status' => $status, 'last_error' => $error, 'last_duration_ms' => (int) round((microtime(true) - $started) * 1000),
                'next_run_at' => $this->next((string) $task->cron_expression, (string) $task->timezone, $now)->format('Y-m-d H:i:s.u'),
                'claimed_until' => null, 'claimed_by' => null, 'updated_at' => Carbon::now('UTC')->format('Y-m-d H:i:s.u'),
            ]);
            $ran++;
        }

        return $ran;
    }
}
