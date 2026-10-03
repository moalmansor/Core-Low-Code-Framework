<?php

declare(strict_types=1);

namespace App\Infrastructure\Egress;

/** Resolves a hostname to all of its A and AAAA addresses. */
interface HostResolver
{
    /** @return list<string> */
    public function resolve(string $host): array;
}
