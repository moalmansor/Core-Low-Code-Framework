<?php

declare(strict_types=1);

namespace App\Infrastructure\Database\Specs;

final readonly class ColumnSpec
{
    /**
     * @param  list<string>  $values  allowed values for enum columns
     */
    public function __construct(
        public string $name,
        public LogicalType $type,
        public bool $nullable = false,
        public ?int $length = null,
        public ?int $precision = null,
        public ?int $scale = null,
        public bool $unsigned = false,
        public array $values = [],
        public string|int|float|bool|null $default = null,
    ) {}
}
