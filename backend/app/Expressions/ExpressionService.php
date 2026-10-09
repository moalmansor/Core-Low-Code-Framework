<?php

declare(strict_types=1);

namespace App\Expressions;

use App\Expressions\Checking\TypeChecker;
use App\Expressions\Evaluation\Ast;
use App\Expressions\Evaluation\Context;
use App\Expressions\Evaluation\Evaluator;
use App\Expressions\Evaluation\Result;
use App\Expressions\Parsing\Parser;

/**
 * Facade over the expression language (expression-language.md): parse text
 * to the stored AST, type-check it at save time, and evaluate it. Pure — no
 * I/O, no generated or evaluated code.
 */
final class ExpressionService
{
    /**
     * Parse and check an expression for storage.
     *
     * @param  (callable(string, list<string>, ?list<string>): ?string)|null  $resolver
     * @return array{ast: array<string, mixed>, type: string}
     *
     * @throws StaticError
     */
    public function compile(string $source, ?callable $resolver = null, ?string $expected = null): array
    {
        $ast = Parser::parse($source);
        $type = TypeChecker::check($ast, $resolver, $expected);

        return ['ast' => Ast::sortKeys($ast), 'type' => $type];
    }

    /**
     * Check a stored or client-supplied AST (shape is validated against
     * expression-ast.schema.json by the caller's request validation).
     *
     * @param  array<string, mixed>  $ast
     * @param  (callable(string, list<string>, ?list<string>): ?string)|null  $resolver
     *
     * @throws StaticError
     */
    public function check(array $ast, ?callable $resolver = null, ?string $expected = null): string
    {
        return TypeChecker::check($ast, $resolver, $expected);
    }

    /** @param  array<string, mixed>  $ast */
    public function evaluate(array $ast, Context $context): Result
    {
        return Evaluator::evaluate($ast, $context);
    }
}
