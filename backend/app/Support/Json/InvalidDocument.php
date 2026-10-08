<?php

declare(strict_types=1);

namespace App\Support\Json;

use RuntimeException;

/**
 * A metadata document failed its JSON schema. Reported to Error Monitoring so
 * the technical detail (JSON pointers, schema messages) stays out of the
 * interface: users see a sentence in their language and the reference.
 */
final class InvalidDocument extends RuntimeException
{
    /** @param  array<string, list<string>>  $errors  JSON pointer => schema messages */
    public function __construct(public readonly string $schemaId, public readonly array $errors)
    {
        $lines = [];
        foreach ($errors as $pointer => $messages) {
            $lines[] = $pointer.': '.implode('; ', $messages);
        }
        parent::__construct("Document does not match {$schemaId}\n".implode("\n", array_slice($lines, 0, 50)));
    }
}
