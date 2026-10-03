<?php

declare(strict_types=1);

namespace App\Expressions\Values;

/** A record given as a map of already-typed values (corpus, previews, tests). */
final class ArrayRecord implements RecordSource
{
    /** @param  array<string, Value>  $values */
    public function __construct(
        private readonly array $values,
        private readonly ?string $title = null,
        private readonly ?string $identity = null,
    ) {}

    public function get(string $key): ?Value
    {
        return $this->values[$key] ?? null;
    }

    public function title(): ?string
    {
        return $this->title;
    }

    public function identity(): ?string
    {
        return $this->identity;
    }

    /** @return array<string, Value> */
    public function values(): array
    {
        return $this->values;
    }
}
