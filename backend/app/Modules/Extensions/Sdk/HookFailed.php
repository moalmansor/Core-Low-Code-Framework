<?php

declare(strict_types=1);

namespace App\Modules\Extensions\Sdk;

use RuntimeException;

/** Thrown by a hook (through HookContext::fail) to refuse the operation with a message for the user. */
final class HookFailed extends RuntimeException {}
