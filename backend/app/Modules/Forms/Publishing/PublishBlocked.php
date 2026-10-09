<?php

declare(strict_types=1);

namespace App\Modules\Forms\Publishing;

use RuntimeException;

/** A publish that cannot start; `reason` is a stable code for the UI. */
final class PublishBlocked extends RuntimeException
{
    public function __construct(public readonly string $reason, string $message)
    {
        parent::__construct($message);
    }
}
