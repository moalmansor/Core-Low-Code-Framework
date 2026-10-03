<?php

declare(strict_types=1);

namespace App\Infrastructure\Database\Specs;

final readonly class DdlImpact
{
    public function __construct(
        public int $rows,
        public int $estimatedMs,
        public bool $online,
        public string $lockLevel,
    ) {}
}
