<?php

declare(strict_types=1);

namespace App\Infrastructure\Database\Specs;

final readonly class OnlineDdlSupport
{
    public function __construct(
        public bool $online,
        public string $lockLevel,
        public string $note,
    ) {}
}
