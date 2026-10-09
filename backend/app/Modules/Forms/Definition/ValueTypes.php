<?php

declare(strict_types=1);

namespace App\Modules\Forms\Definition;

use App\Modules\Forms\FieldTypes\FieldTypeRegistry;

/**
 * Static expression type of a field's value (expression-language.md §3):
 * text-like → text, numeric → number, lookups → record, multi-valued → list.
 */
final class ValueTypes
{
    /** @param  array<string, mixed>  $field  draft/definition field */
    public static function of(array $field, ?array $relation = null): string
    {
        if (! FieldTypeRegistry::has($field['type'])) {
            return 'any';
        }
        $type = FieldTypeRegistry::get($field['type']);
        $multi = $relation !== null && $relation['type'] === 'many_to_many';

        return match ($type->storage) {
            'none' => 'null',
            'multi_choice' => $relation !== null ? 'list<record>' : 'list<text>',
            'files', 'file' => 'list<text>',
            'lookup', 'user', 'role', 'department' => $multi ? 'list<record>' : 'record',
            'choice' => $relation !== null ? 'record' : 'text',
            'range_date', 'range_time', 'range_datetime' => 'list<'.$type->valueType.'>',
            'formula' => match ($field['storage']['dbType'] ?? 'decimal') {
                'string', 'text' => 'text',
                'date' => 'date',
                'datetime' => 'datetime',
                'time' => 'time',
                'bool' => 'boolean',
                'json' => 'any',
                default => 'number',
            },
            default => $type->valueType,
        };
    }
}
