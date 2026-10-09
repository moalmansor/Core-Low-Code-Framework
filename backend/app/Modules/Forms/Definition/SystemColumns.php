<?php

declare(strict_types=1);

namespace App\Modules\Forms\Definition;

/**
 * System columns of every record table (architecture §11.2) and the names a
 * field column may never take.
 */
final class SystemColumns
{
    /** @var list<string> */
    public const MAIN = [
        'id', 'uuid', 'organization_id', 'form_version_id', 'record_number', 'status_id', 'status_changed_at',
        'row_version', 'owner_user_id', 'owner_department_id', 'created_by', 'updated_by', 'created_at',
        'updated_at', 'deleted_at', 'deleted_by', 'legal_hold', 'search_text', 'external_user_id',
    ];

    /** @var list<string> */
    public const CHILD = ['id', 'uuid', 'organization_id', 'parent_id', 'sort_order', 'created_by', 'updated_by', 'created_at', 'updated_at'];

    /** @var list<string> */
    public const PIVOT = ['id', 'source_id', 'target_id', 'sort_order', 'created_at', 'created_by'];

    /** Names reserved for field columns (system columns, SQL keywords commonly mis-parsed, and archive prefixes). */
    public static function isReserved(string $column): bool
    {
        return in_array($column, [...self::MAIN, ...self::CHILD, 'source_id', 'target_id', 'key', 'order', 'group', 'select', 'from', 'where', 'table', 'user', 'index'], true)
            || str_starts_with($column, 'zz_')
            || str_contains($column, '__');
    }
}
