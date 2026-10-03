<?php

declare(strict_types=1);

namespace App\Modules\Forms\Draft;

use RuntimeException;

/** A draft document that cannot be stored (DraftValidator errors). */
final class DraftInvalid extends RuntimeException
{
    /** @param  list<array<string, mixed>>  $errors */
    public function __construct(public readonly array $errors)
    {
        parent::__construct('The draft document is invalid.');
    }
}
