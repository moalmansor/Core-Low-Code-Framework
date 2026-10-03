<?php

declare(strict_types=1);

namespace App\Modules\Records\Runtime;

use RuntimeException;

/** A submitted value that does not fit its field's type. */
final class InvalidValue extends RuntimeException
{
    /** @param  array<string, mixed>  $params */
    public function __construct(public readonly string $key, public readonly array $params = [])
    {
        parent::__construct($key);
    }
}
