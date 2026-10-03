<?php

declare(strict_types=1);

namespace App\Infrastructure\Database\Specs;

final readonly class ForeignKeySpec
{
    public function __construct(
        public string $name,
        public string $column,
        public string $referencesTable,
        public string $referencesColumn = 'id',
        public string $onDelete = 'no action',
    ) {}
}
