<?php

declare(strict_types=1);

namespace App\Expressions\Values;

/**
 * A record as seen by the expression language: field and relation values by
 * key. The runtime supplies sources backed by records and relation paths; the
 * conformance corpus supplies plain maps (ArrayRecord).
 */
interface RecordSource
{
    /** The value of a key, or null when the key does not exist (MISSING_REF). */
    public function get(string $key): ?Value;

    /** The record title, used by to_text(record). */
    public function title(): ?string;

    /** Stable identity for equality (form uuid + record id), or null. */
    public function identity(): ?string;
}
