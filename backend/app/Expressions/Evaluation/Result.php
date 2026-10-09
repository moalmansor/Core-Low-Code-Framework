<?php

declare(strict_types=1);

namespace App\Expressions\Evaluation;

use App\Expressions\Values\Envelope;
use App\Expressions\Values\Value;

/**
 * The evaluation result envelope of expression-language.md §6: a value plus
 * every diagnostic collected, deduplicated by code and node.
 */
final class Result
{
    /** @param  list<array{code: string, node: string}>  $diagnostics */
    public function __construct(
        public readonly Value $value,
        public readonly array $diagnostics = [],
    ) {}

    /** @return list<string> */
    public function codes(): array
    {
        return array_map(static fn (array $d): string => $d['code'], $this->diagnostics);
    }

    /** @return array{value: array<string, mixed>, diagnostics: list<array{code: string, node: string}>} */
    public function toArray(): array
    {
        return ['value' => Envelope::encode($this->value), 'diagnostics' => $this->diagnostics];
    }
}
