<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Remembers metadata reads for the length of one bulk unit of work: one
 * import batch or one bulk operation, where the same per-form lookups
 * (duplicate rules, notification rules, automation conditions, assignment
 * rules, the default working calendar) would otherwise run once per record.
 *
 * Outside such a unit nothing is remembered, so an ordinary request always
 * reads current metadata; inside one, a change an administrator makes takes
 * effect from the next unit (the next batch of 500 import rows).
 * Security checks (permissions, maintenance mode, record scope) never go
 * through it.
 */
final class BatchMemo
{
    private int $depth = 0;

    /** @var array<string, mixed> */
    private array $values = [];

    /**
     * @template T
     *
     * @param  callable(): T  $fn
     * @return T
     */
    public function within(callable $fn): mixed
    {
        $this->depth++;
        try {
            return $fn();
        } finally {
            if (--$this->depth === 0) {
                $this->values = [];
            }
        }
    }

    /**
     * @template T
     *
     * @param  callable(): T  $fn
     * @return T
     */
    public function remember(string $key, callable $fn): mixed
    {
        if ($this->depth === 0) {
            return $fn();
        }
        if (! array_key_exists($key, $this->values)) {
            $this->values[$key] = $fn();
        }

        return $this->values[$key];
    }
}
