<?php

declare(strict_types=1);

namespace App\Infrastructure\Database\Specs;

final readonly class BackupResult
{
    /**
     * @param  array<string, int>  $rowsPerTable
     */
    public function __construct(
        public string $disk,
        public string $path,
        public array $rowsPerTable,
        public int $bytes,
        public string $checksum,
    ) {}
}
