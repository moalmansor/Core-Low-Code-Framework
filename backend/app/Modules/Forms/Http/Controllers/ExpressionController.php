<?php

declare(strict_types=1);

namespace App\Modules\Forms\Http\Controllers;

use App\Expressions\Calendars\Civil;
use App\Expressions\Checking\TypeChecker;
use App\Expressions\Evaluation\Ast;
use App\Expressions\Evaluation\Context;
use App\Expressions\Evaluation\Evaluator;
use App\Expressions\Parsing\Parser;
use App\Expressions\StaticError;
use App\Expressions\Values\Envelope;
use App\Modules\Forms\Draft\DraftRepository;
use App\Modules\Forms\Draft\DraftValidator;
use App\Modules\Forms\Models\Form;
use App\Modules\Identity\Models\User;
use App\Modules\Records\Runtime\ExpressionContext;
use App\Support\Json\SchemaValidator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use InvalidArgumentException;

/**
 * Expression tooling for the builder (architecture §21.2 "Expressions"):
 * parse text to the stored AST (server-side, the reference parser), type-check
 * an AST against a form's draft, and evaluate an AST in a sandbox with sample
 * values. Nothing here reads or writes records.
 */
final class ExpressionController extends Controller
{
    public function __construct(private readonly DraftRepository $drafts, private readonly DraftValidator $validator, private readonly SchemaValidator $schemas) {}

    public function parse(Request $request): JsonResponse
    {
        Gate::authorize('system.manage_forms');
        $data = $request->validate([
            'source' => ['required', 'string', 'max:20000'],
            'form' => ['sometimes', 'nullable', 'uuid'],
            'expected' => ['sometimes', 'nullable', Rule::in(['boolean', 'number', 'text', 'date', 'datetime', 'time', 'duration'])],
            'rows' => ['sometimes', 'nullable', 'string', 'max:48'],
        ]);
        try {
            $ast = Parser::parse($data['source']);
            $type = TypeChecker::check($ast, $this->resolver($data['form'] ?? null, $data['rows'] ?? null), $data['expected'] ?? null);
        } catch (StaticError $e) {
            return response()->json(['data' => ['ok' => false, 'error' => $e->toArray()]]);
        }

        return response()->json(['data' => ['ok' => true, 'ast' => Ast::sortKeys($ast), 'type' => $type]]);
    }

    public function check(Request $request): JsonResponse
    {
        Gate::authorize('system.manage_forms');
        $data = $request->validate([
            'ast' => ['required', 'array'],
            'form' => ['sometimes', 'nullable', 'uuid'],
            'expected' => ['sometimes', 'nullable', 'string', 'max:32'],
            'rows' => ['sometimes', 'nullable', 'string', 'max:48'],
        ]);
        if (! $this->schemas->isValid('https://schemas.core-lcf/expression-ast/v1', $data['ast'])) {
            return response()->json(['data' => ['ok' => false, 'error' => ['code' => 'SYNTAX', 'message' => 'Malformed expression tree.', 'position' => null, 'node' => null]]]);
        }
        try {
            $type = TypeChecker::check($data['ast'], $this->resolver($data['form'] ?? null, $data['rows'] ?? null), $data['expected'] ?? null);
        } catch (StaticError $e) {
            return response()->json(['data' => ['ok' => false, 'error' => $e->toArray()]]);
        }

        return response()->json(['data' => ['ok' => true, 'type' => $type]]);
    }

    /** Sandboxed evaluation with values the admin supplies; the acting user is the admin themself. */
    public function evaluate(Request $request): JsonResponse
    {
        Gate::authorize('system.manage_forms');
        $data = $request->validate([
            'ast' => ['required', 'array'],
            'record' => ['sometimes', 'array', 'max:200'],
            'old' => ['sometimes', 'array', 'max:200'],
            'mode' => ['sometimes', Rule::in(['create', 'edit', 'view', 'print'])],
            'today' => ['sometimes', 'date_format:Y-m-d'],
        ]);
        if (! $this->schemas->isValid('https://schemas.core-lcf/expression-ast/v1', $data['ast'])) {
            return response()->json(['message' => 'Malformed expression tree.', 'code' => 'invalid_ast'], 422);
        }
        /** @var User $user */
        $user = Auth::user();
        try {
            $record = Envelope::record($data['record'] ?? []);
            $old = isset($data['old']) ? Envelope::record($data['old']) : null;
        } catch (InvalidArgumentException|\TypeError $e) {
            return response()->json(['message' => $e->getMessage(), 'code' => 'invalid_value'], 422);
        }
        $context = Context::now(config('app.timezone', 'UTC'))->with([
            'mode' => $data['mode'] ?? 'edit',
            'record' => $record,
            'old' => $old,
            'user' => app(ExpressionContext::class)->user($user),
        ]);
        if (isset($data['today'])) {
            $context = $context->with(['today' => (int) Civil::parseDate($data['today'])]);
        }

        return response()->json(['data' => Evaluator::evaluate($data['ast'], $context)->toArray()]);
    }

    /** @return (callable(string, list<string>, ?list<string>): ?string)|null */
    private function resolver(?string $formUuid, ?string $rows): ?callable
    {
        if ($formUuid === null) {
            return null;
        }
        $form = Form::query()->where('uuid', $formUuid)->firstOrFail();
        $resolver = $this->validator->resolver($this->drafts->normalize($this->drafts->load($form)));

        return $rows === null ? $resolver : static fn (string $s, array $p, ?array $r) => $resolver($s, $p, $r ?? [$rows]);
    }
}
