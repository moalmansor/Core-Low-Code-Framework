<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Channels;

use RuntimeException;

/** A provider refused or could not be reached; `retryable` says whether trying again can help. */
final class ChannelFailure extends RuntimeException
{
    public function __construct(string $message, public readonly bool $retryable)
    {
        parent::__construct($message);
    }
}
