<?php

declare(strict_types=1);

namespace App\Modules\Extensions;

use RuntimeException;

/** An extension ran past its time limit. */
final class HookTimeout extends RuntimeException {}
