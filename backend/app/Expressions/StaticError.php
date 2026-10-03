<?php

declare(strict_types=1);

namespace App\Expressions;

use RuntimeException;

/**
 * A save-time rejection of an expression (expression-language.md §10 static
 * codes): SYNTAX, TYPE, UNKNOWN_FUNCTION, ARITY, PATH_DEPTH, DEPTH, PRECISION.
 */
final class StaticError extends RuntimeException
{
    public function __construct(
        public readonly string $errorCode,
        string $message,
        public readonly ?int $position = null,
        public readonly ?string $node = null,
    ) {
        parent::__construct($message);
    }

    /** @return array{code: string, message: string, position: ?int, node: ?string} */
    public function toArray(): array
    {
        return ['code' => $this->errorCode, 'message' => $this->getMessage(), 'position' => $this->position, 'node' => $this->node];
    }
}
