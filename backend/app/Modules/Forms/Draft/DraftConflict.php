<?php

declare(strict_types=1);

namespace App\Modules\Forms\Draft;

use RuntimeException;

/** Another session saved the draft since this one loaded it (architecture §13.1). */
final class DraftConflict extends RuntimeException
{
    public function __construct(public readonly ?string $currentUpdatedAt, public readonly ?int $updatedBy)
    {
        parent::__construct('The draft was changed by someone else.');
    }
}
