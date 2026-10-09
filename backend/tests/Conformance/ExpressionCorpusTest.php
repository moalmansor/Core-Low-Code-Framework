<?php

declare(strict_types=1);

use App\Expressions\Calendars\Civil;
use App\Expressions\Calendars\WorkingCalendar;
use App\Expressions\Checking\TypeChecker;
use App\Expressions\Evaluation\Ast;
use App\Expressions\Evaluation\Context;
use App\Expressions\Evaluation\Evaluator;
use App\Expressions\Parsing\Parser;
use App\Expressions\StaticError;
use App\Expressions\Values\Envelope;

/*
 * Runs docs/conformance/expression-corpus.json against the PHP reference
 * implementation (expression-language.md §10). The TypeScript twin runs the
 * same file in frontend/tests/conformance/expressionCorpus.spec.ts.
 */

function corpus(): array
{
    static $corpus = null;

    return $corpus ??= json_decode((string) file_get_contents(dirname(__DIR__, 3).'/docs/conformance/expression-corpus.json'), true, 512, JSON_THROW_ON_ERROR);
}

/** @return array<string, array{0: array<string, mixed>}> */
function corpusCases(bool $static): array
{
    $out = [];
    foreach (corpus()['cases'] as $case) {
        if (isset($case['expectStatic']) === $static) {
            $out[$case['id']] = [$case];
        }
    }

    return $out;
}

/** @param  array<string, mixed>  $case */
function corpusContext(array $case): Context
{
    $c = array_merge(corpus()['defaults']['context'], $case['context'] ?? []);
    $user = $c['user'] ?? [];
    $user['attributes'] = array_map(static fn (array $e) => Envelope::decode($e), $user['attributes'] ?? []);

    return new Context(
        today: (int) Civil::parseDate($c['today']),
        now: (int) Civil::parseDatetime($c['now']),
        timezone: $c['timezone'] ?? 'UTC',
        mode: $c['mode'] ?? 'edit',
        locale: $c['locale'] ?? 'en',
        form: $c['form'] ?? null,
        user: $user,
        record: Envelope::record($case['record'] ?? []),
        old: isset($case['old']) ? Envelope::record($case['old']) : null,
        params: array_map(static fn (array $e) => Envelope::decode($e), $c['params'] ?? []),
        calendar: WorkingCalendar::fromArray($c['calendar'] ?? []),
    );
}

/** @param  array<string, mixed>  $case */
function assertCorpusResult(array $case, array $ast, string $label): void
{
    $result = Evaluator::evaluate($ast, corpusContext($case));
    $expected = $case['expect'];
    expect(Envelope::encode($result->value))->toEqual(Ast::sortKeys($expected['value']), "{$case['id']} ({$label}) value");
    $codes = $result->codes();
    sort($codes);
    $want = array_map(static fn ($d) => is_array($d) ? $d['code'] : $d, $expected['diagnostics']);
    sort($want);
    expect($codes)->toEqual($want, "{$case['id']} ({$label}) diagnostics");
}

test('parser reproduces the normative AST', function (array $case) {
    $parsed = Parser::parse($case['expr']);
    expect(Ast::canonicalJson($parsed))->toBe(Ast::canonicalJson($case['ast']));
})->with(fn () => array_filter(corpusCases(false), static fn (array $c) => isset($c[0]['ast'])));

test('evaluator matches the corpus', function (array $case) {
    assertCorpusResult($case, Parser::parse($case['expr']), 'parsed');
    if (isset($case['ast'])) {
        assertCorpusResult($case, $case['ast'], 'ast');
    }
})->with(fn () => corpusCases(false));

test('static checks reject the expression', function (array $case) {
    $code = null;
    try {
        TypeChecker::check(Parser::parse($case['expr']));
    } catch (StaticError $e) {
        $code = $e->errorCode;
    }
    expect($code)->toBe($case['expectStatic']['error']);
})->with(fn () => corpusCases(true));

test('static error codes listed by the corpus are the ones the checker emits', function () {
    expect(corpus()['staticErrorCodes'])->toEqualCanonicalizing(['SYNTAX', 'TYPE', 'UNKNOWN_FUNCTION', 'ARITY', 'PATH_DEPTH', 'DEPTH', 'PRECISION']);
});
