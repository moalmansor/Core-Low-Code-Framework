<?php

declare(strict_types=1);

namespace Tests;

/**
 * Tables whose rows are written by migrations themselves (the audit chain
 * heads) survive truncation between engine tests.
 */
trait EngineTruncation
{
    /** @var list<string> */
    protected array $exceptTables = ['migrations', 'audit_chain_heads'];
}
