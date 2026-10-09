<?php

declare(strict_types=1);

namespace App\Modules\Records\Runtime;

use RuntimeException;

/**
 * A record operation that cannot proceed, mapped to an HTTP status by the
 * controller: 403 forbidden, 404 not_found, 409 conflict / in_progress,
 * 422 invalid, 423 locked.
 */
final class RecordException extends RuntimeException
{
    /** @param  array<string, mixed>  $payload */
    public function __construct(public readonly int $status, public readonly string $reason, string $message, public readonly array $payload = [])
    {
        parent::__construct($message);
    }
}
