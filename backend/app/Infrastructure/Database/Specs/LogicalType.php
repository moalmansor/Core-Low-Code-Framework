<?php

declare(strict_types=1);

namespace App\Infrastructure\Database\Specs;

enum LogicalType: string
{
    case Id = 'id';
    case BigInt = 'bigint';
    case Int = 'int';
    case SmallInt = 'smallint';
    case Bool = 'bool';
    case Decimal = 'decimal';
    case Str = 'string';
    case Code = 'code';
    case Hash = 'hash';
    case Text = 'text';
    case LongText = 'longtext';
    case Json = 'json';
    case Uuid = 'uuid';
    case DateTime = 'datetime';
    case Date = 'date';
    case Time = 'time';
    case Enum = 'enum';
}
