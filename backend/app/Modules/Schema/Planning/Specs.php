<?php

declare(strict_types=1);

namespace App\Modules\Schema\Planning;

use App\Infrastructure\Database\Specs\ColumnSpec;
use App\Infrastructure\Database\Specs\ForeignKeySpec;
use App\Infrastructure\Database\Specs\IndexSpec;
use App\Infrastructure\Database\Specs\LogicalType;
use App\Infrastructure\Database\Specs\TableSpec;

/** Converts the JSON schema specs of a definition into driver specs. */
final class Specs
{
    /** @param  array<string, mixed>  $c */
    public static function column(array $c, ?string $name = null, ?bool $nullable = null): ColumnSpec
    {
        return new ColumnSpec(
            name: $name ?? $c['name'],
            type: LogicalType::from($c['type'] === 'id' ? 'id' : $c['type']),
            nullable: $nullable ?? (bool) $c['nullable'],
            length: isset($c['length']) ? (int) $c['length'] : null,
            precision: isset($c['precision']) ? (int) $c['precision'] : null,
            scale: isset($c['scale']) ? (int) $c['scale'] : null,
            unsigned: in_array($c['type'], ['bigint'], true) && (str_ends_with($c['name'], '_id') || in_array($c['name'], ['created_by', 'updated_by', 'deleted_by'], true)),
            default: self::scalarDefault($c['default'] ?? null),
        );
    }

    /** @param  array<string, mixed>  $i */
    public static function index(array $i): IndexSpec
    {
        return new IndexSpec($i['name'], $i['columns'], (bool) ($i['unique'] ?? false), $i['nullable'] ?? []);
    }

    /** @param  array<string, mixed>  $f */
    public static function foreignKey(array $f): ForeignKeySpec
    {
        return new ForeignKeySpec($f['name'], $f['column'], $f['references'], $f['referencesColumn'] ?? 'id', $f['onDelete'] ?? 'no action');
    }

    /** @param  array<string, mixed>  $t */
    public static function table(array $t): TableSpec
    {
        return new TableSpec(
            $t['name'],
            array_map(static fn (array $c) => self::column($c), array_values(array_filter($t['columns'], static fn (array $c) => ! ($c['bound'] ?? false)))),
            array_map(self::index(...), $t['indexes']),
            array_map(self::foreignKey(...), $t['foreignKeys']),
        );
    }

    private static function scalarDefault(mixed $value): string|int|float|bool|null
    {
        return is_scalar($value) ? $value : null;
    }
}
