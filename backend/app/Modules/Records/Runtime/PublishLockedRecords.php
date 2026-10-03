<?php

declare(strict_types=1);

namespace App\Modules\Records\Runtime;

use Illuminate\Support\Facades\DB;

/**
 * Records of a form are locked (read-only) while its schema is inconsistent
 * (architecture §12.2). Publish locks themselves never block record writes.
 */
final class PublishLockedRecords
{
    public static function locked(int $formId): bool
    {
        return DB::table('forms')->where('id', $formId)->where('state', 'schema_inconsistent')->exists();
    }
}
