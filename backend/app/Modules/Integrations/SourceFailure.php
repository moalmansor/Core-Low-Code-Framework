<?php

declare(strict_types=1);

namespace App\Modules\Integrations;

use RuntimeException;

/** An external data source could not be read; the message is safe to show. */
final class SourceFailure extends RuntimeException {}
