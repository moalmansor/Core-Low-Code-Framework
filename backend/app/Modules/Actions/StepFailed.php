<?php

declare(strict_types=1);

namespace App\Modules\Actions;

use RuntimeException;

/** A step of an action could not do its work for a record; the message is for the user. */
final class StepFailed extends RuntimeException {}
