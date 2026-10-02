<?php

declare(strict_types=1);

namespace App\Infrastructure\Database\Specs;

final readonly class TableStats
{
    public function __construct(
        public int $rows,
        public int $dataBytes,
    ) {}
}
