<?php

declare(strict_types=1);

namespace App\Infrastructure\Egress;

use RuntimeException;

/** An outbound request was refused by the egress policy (architecture §7.4). */
final class EgressViolation extends RuntimeException {}
