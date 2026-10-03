<?php

declare(strict_types=1);

namespace App\Infrastructure\Database\Specs;

final readonly class TableSpec
{
    /**
     * @param  list<ColumnSpec>  $columns
     * @param  list<IndexSpec>  $indexes
     * @param  list<ForeignKeySpec>  $foreignKeys
     */
    public function __construct(
        public string $name,
        public array $columns,
        public array $indexes = [],
        public array $foreignKeys = [],
    ) {}
}
