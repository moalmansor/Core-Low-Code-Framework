<?php

declare(strict_types=1);

namespace App\Infrastructure\Database\Specs;

final readonly class IndexSpec
{
    /**
     * @param  list<string>  $columns
     * @param  list<string>  $nullableColumns  key columns that allow NULL (filtered on SQL Server)
     */
    public function __construct(
        public string $name,
        public array $columns,
        public bool $unique = false,
        public array $nullableColumns = [],
    ) {}
}
