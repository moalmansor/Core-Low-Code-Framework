<?php

declare(strict_types=1);

namespace App\Modules\Core\Correlation;

use Illuminate\Support\Str;

/**
 * The correlation ID that follows a request through jobs, emails, hooks, audit
 * and error entries (architecture §8.4, specification §4.21).
 */
final class CorrelationId
{
    public const HEADER = 'X-Correlation-ID';

    private ?string $id = null;

    public function get(): string
    {
        return $this->id ??= (string) Str::ulid();
    }

    /** Accept an inbound id only if it is a well-formed UUID or ULID. */
    public function setFromInbound(?string $candidate): void
    {
        if ($candidate !== null && (Str::isUuid($candidate) || Str::isUlid($candidate))) {
            $this->id = strtolower($candidate);

            return;
        }
        $this->id = (string) Str::ulid();
    }

    public function set(string $id): void
    {
        $this->id = $id;
    }
}
