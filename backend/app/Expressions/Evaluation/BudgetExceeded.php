<?php

declare(strict_types=1);

namespace App\Expressions\Evaluation;

use RuntimeException;

/** Raised internally when the step or row budget is spent (expression-language.md §8). */
final class BudgetExceeded extends RuntimeException {}
