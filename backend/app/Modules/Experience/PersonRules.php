<?php

declare(strict_types=1);

namespace App\Modules\Experience;

use App\Expressions\Checking\TypeChecker;
use App\Expressions\Evaluation\Context;
use App\Expressions\Evaluation\Evaluator;
use App\Expressions\StaticError;
use App\Modules\Identity\Models\User;
use App\Modules\Records\Runtime\ExpressionContext;
use Illuminate\Validation\ValidationException;

/**
 * Visibility rules on the person (ADR-0054): expressions that may read only
 * `@user` and the evaluation context — roles, department, attributes,
 * language, today — because no record is open where they apply (menu items,
 * home screen widgets, announcements). They narrow what someone sees; they
 * never grant access.
 */
final class PersonRules
{
    /** @var array<int, Context> */
    private array $contexts = [];

    public function __construct(private readonly ExpressionContext $userContext) {}

    /**
     * @param  array<string, mixed>  $ast
     *
     * @throws ValidationException
     */
    public function check(array $ast, string $attribute): void
    {
        try {
            TypeChecker::check($ast, static fn (): ?string => null, 'boolean');
        } catch (StaticError $e) {
            throw ValidationException::withMessages([$attribute => __('experience.bad_condition', ['message' => $e->getMessage()])]);
        }
    }

    /** @param  array<string, mixed>|null  $ast  null means no rule */
    public function passes(?array $ast, User $user): bool
    {
        if ($ast === null) {
            return true;
        }
        $context = $this->contexts[$user->id] ??= Context::now(config('app.timezone', 'UTC'))
            ->with(['mode' => 'view', 'user' => $this->userContext->user($user), 'locale' => app()->getLocale()]);

        return Evaluator::evaluate($ast, $context)->value->data === true;
    }
}
