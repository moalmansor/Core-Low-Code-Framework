<?php

declare(strict_types=1);

namespace App\Modules\Operations;

use App\Infrastructure\Storage\VirusScanner;
use App\Modules\Core\Mail\SettingsSmtpTransport;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Redis;
use Symfony\Component\Mailer\Transport\Smtp\Stream\SocketStream;
use Throwable;

/**
 * The Operations Center's health widgets (specification §4.2): the
 * connectivity of the database, Redis, SMTP and the virus scanner; the size
 * of each queue; the items stuck or failed; when the scheduler last ran; and
 * how full the storage disk is.
 */
final class Health
{
    /** The queues the workers serve (architecture §8.1) and the connection each is on. */
    public const QUEUES = ['default' => 'redis', 'heavy' => 'redis_heavy', 'mail' => 'redis', 'schema' => 'redis_schema'];

    public function __construct(
        private readonly SettingsSmtpTransport $smtp,
        private readonly VirusScanner $scanner,
        private readonly EmailMonitor $emails,
        private readonly FailedSubmissions $submissions,
        private readonly FailedJobs $jobs,
    ) {}

    /** @return array<string, mixed> */
    public function snapshot(): array
    {
        $storage = $this->storage();

        return [
            'checks' => [
                'database' => self::probe(static fn (): bool => DB::select('select 1 as ok') !== []),
                'cache' => self::probe(static function (): bool {
                    Cache::put('health:probe', 1, 10);

                    return Cache::get('health:probe') === 1;
                }),
                'redis' => $this->usesRedis() ? self::probe(static fn (): bool => (bool) Redis::connection()->ping()) : 'not_used',
                'smtp' => $this->smtpCheck(),
                'virus_scanner' => ! $this->scanner->enabled() ? 'disabled' : self::probe(fn (): bool => $this->scanner->ping()),
            ],
            'queues' => $this->queueSizes(),
            'stuck_emails' => $this->emails->stuckCount(),
            'failed_emails' => DB::table('email_queue')->where('status', 'failed')->count(),
            'failed_submissions' => $this->submissions->openCount(),
            'failed_jobs' => $this->jobs->count(),
            'scheduler_last_run_at' => $this->schedulerLastRun()?->toIso8601ZuluString(),
            'storage' => $storage,
            'pending_outbox_events' => DB::table('outbox_events')->whereNull('dispatched_at')->count(),
            'open_error_groups' => DB::table('error_groups')->whereIn('status', ['new', 'in_progress'])->count(),
        ];
    }

    /** @return array<string, int|null> waiting jobs per queue (null when the queue is not on Redis, so nothing waits) */
    public function queueSizes(): array
    {
        $out = [];
        foreach (self::QUEUES as $queue => $connection) {
            try {
                $out[$queue] = config('queue.default') === 'redis' ? Queue::connection($connection)->size($queue) : null;
            } catch (Throwable) {
                $out[$queue] = null;
            }
        }

        return $out;
    }

    /** The last time the scheduler ran one of its own commands (they run every minute). */
    public function schedulerLastRun(): ?Carbon
    {
        $at = DB::table('scheduled_tasks')->where('kind', 'system')->max('last_run_at');

        return $at === null ? null : Carbon::parse((string) $at, 'UTC');
    }

    /** @return array{total: int|null, free: int|null, used_percent: int|null} the disk holding the stored files */
    public function storage(): array
    {
        $path = storage_path('app');
        $total = @disk_total_space($path);
        $free = @disk_free_space($path);
        if ($total === false || $free === false || $total <= 0) {
            return ['total' => null, 'free' => null, 'used_percent' => null];
        }

        return ['total' => (int) $total, 'free' => (int) $free, 'used_percent' => (int) round(100 * ($total - $free) / $total)];
    }

    /** Connects to the configured SMTP server and says hello, without sending anything. */
    private function smtpCheck(): string
    {
        $config = $this->smtp->config();
        if ($config === null) {
            return 'not_configured';
        }

        return self::probe(static function () use ($config): bool {
            $transport = SettingsSmtpTransport::build($config);
            $stream = $transport->getStream();
            if ($stream instanceof SocketStream) {
                $stream->setTimeout(5);
            }
            $transport->start();
            $transport->stop();

            return true;
        });
    }

    private function usesRedis(): bool
    {
        return in_array('redis', [config('queue.default'), config('cache.default'), config('session.driver')], true);
    }

    private static function probe(callable $check): string
    {
        try {
            return $check() ? 'ok' : 'failing';
        } catch (Throwable) {
            return 'failing';
        }
    }
}
