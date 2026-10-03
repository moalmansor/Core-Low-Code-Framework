<?php

declare(strict_types=1);

namespace App\Modules\Schema\Execution;

use RuntimeException;

/** A migration step could not be applied; `conflicts` lists offending rows for data validation. */
final class StepFailed extends RuntimeException
{
    /** @param  list<array{id: int|string, value: mixed}>  $conflicts */
    public function __construct(string $message, public readonly array $conflicts = [])
    {
        parent::__construct($message);
    }
}
