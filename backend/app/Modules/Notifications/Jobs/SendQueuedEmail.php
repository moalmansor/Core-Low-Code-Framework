<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Jobs;

use App\Modules\Notifications\EmailQueue;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;

/**
 * Sends one row of the email queue on the `mail` queue (architecture §8.1).
 * The row carries the attempt count and the backoff; the job itself does not
 * retry, so a failure is recorded once with its exact reason.
 */
final class SendQueuedEmail implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $tries = 1;

    public int $timeout = 120;

    public function __construct(public readonly int $emailId)
    {
        $this->onQueue('mail');
    }

    public function handle(EmailQueue $queue): void
    {
        $queue->send($this->emailId);
    }
}
