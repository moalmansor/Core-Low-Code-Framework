<?php

declare(strict_types=1);

namespace App\Modules\Extensions\Sdk;

/**
 * A server-side hook (specification §4.19): a class named `Hook` that
 * implements this interface. It runs at its hook point inside the record's
 * transaction, with a time limit; it reads and, where the point allows,
 * changes values through the context.
 */
interface ServerHook
{
    public function handle(HookContext $ctx): void;
}
