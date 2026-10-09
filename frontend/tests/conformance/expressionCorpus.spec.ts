import { readFileSync } from 'node:fs'
import { join } from 'node:path'
import { describe, expect, it } from 'vitest'
import { canonicalJson, sortKeys, type RawNode } from '../../src/expressions/ast'
import { parseDate, parseDatetime } from '../../src/expressions/civil'
import { Context, type ContextUser } from '../../src/expressions/context'
import { decode, encode, record } from '../../src/expressions/envelope'
import { Evaluator } from '../../src/expressions/evaluator'
import { Parser } from '../../src/expressions/parser'
import { StaticError } from '../../src/expressions/staticError'
import { TypeChecker } from '../../src/expressions/typeChecker'
import type { Value } from '../../src/expressions/values'
import { WorkingCalendar, type WorkingCalendarData } from '../../src/expressions/workingCalendar'

/*
 * Runs docs/conformance/expression-corpus.json against the TypeScript runtime
 * (expression-language.md §10). The PHP reference runs the same file in
 * backend/tests/Conformance/ExpressionCorpusTest.php.
 */

type Json = Record<string, unknown>

interface CorpusContext {
  today: string
  now: string
  timezone?: string
  mode?: string
  locale?: string
  form?: string | null
  user?: Omit<ContextUser, 'attributes'> & { attributes?: Record<string, unknown> }
  params?: Record<string, unknown>
  calendar?: WorkingCalendarData
}

interface CorpusCase {
  id: string
  expr: string
  description?: string
  ast?: RawNode
  record?: Json
  old?: Json
  context?: Partial<CorpusContext>
  expect?: { value: unknown; diagnostics: (string | { code: string })[] }
  expectStatic?: { error: string }
}

interface Corpus {
  defaults: { context: CorpusContext }
  staticErrorCodes: string[]
  cases: CorpusCase[]
}

const corpus = JSON.parse(readFileSync(join(__dirname, '../../../docs/conformance/expression-corpus.json'), 'utf8')) as Corpus

const evaluationCases = corpus.cases.filter((c) => c.expectStatic === undefined)
const staticCases = corpus.cases.filter((c) => c.expectStatic !== undefined)

function decodeMap(map: Record<string, unknown> | undefined): Record<string, Value> {
  return Object.fromEntries(Object.entries(map ?? {}).map(([key, envelope]) => [key, decode(envelope)]))
}

function corpusContext(testCase: CorpusCase): Context {
  const c: CorpusContext = { ...corpus.defaults.context, ...testCase.context }
  const user = c.user ?? {}
  return new Context({
    today: parseDate(c.today)!,
    now: parseDatetime(c.now)!,
    timezone: c.timezone ?? 'UTC',
    mode: c.mode ?? 'edit',
    locale: c.locale ?? 'en',
    form: c.form ?? null,
    user: { ...user, attributes: decodeMap(user.attributes) },
    record: record(testCase.record ?? {}),
    old: testCase.old !== undefined ? record(testCase.old) : null,
    params: decodeMap(c.params),
    calendar: WorkingCalendar.fromData(c.calendar ?? {}),
  })
}

function assertResult(testCase: CorpusCase, ast: RawNode, label: string): void {
  const result = Evaluator.evaluate(ast, corpusContext(testCase))
  const expected = testCase.expect!
  expect(encode(result.value), `${testCase.id} (${label}) value`).toEqual(sortKeys(expected.value))
  const want = expected.diagnostics.map((d) => (typeof d === 'string' ? d : d.code)).sort()
  expect([...result.codes()].sort(), `${testCase.id} (${label}) diagnostics`).toEqual(want)
}

describe('expression corpus', () => {
  it('has cases of every kind', () => {
    expect(evaluationCases.length).toBeGreaterThan(0)
    expect(staticCases.length).toBeGreaterThan(0)
  })

  it.each(evaluationCases.filter((c) => c.ast !== undefined).map((c) => [c.id, c] as const))('parser reproduces the normative AST: %s', (_, testCase) => {
    expect(canonicalJson(Parser.parse(testCase.expr))).toBe(canonicalJson(testCase.ast))
  })

  it.each(evaluationCases.map((c) => [c.id, c] as const))('evaluator matches the corpus: %s', (_, testCase) => {
    assertResult(testCase, Parser.parse(testCase.expr), 'parsed')
    if (testCase.ast !== undefined) assertResult(testCase, testCase.ast, 'ast')
  })

  it.each(staticCases.map((c) => [c.id, c] as const))('static checks reject the expression: %s', (_, testCase) => {
    let code: string | null = null
    try {
      TypeChecker.check(Parser.parse(testCase.expr))
    } catch (e) {
      if (!(e instanceof StaticError)) throw e
      code = e.errorCode
    }
    expect(code).toBe(testCase.expectStatic!.error)
  })

  it('lists the static error codes the checker emits', () => {
    expect([...corpus.staticErrorCodes].sort()).toEqual(['ARITY', 'DEPTH', 'PATH_DEPTH', 'PRECISION', 'SYNTAX', 'TYPE', 'UNKNOWN_FUNCTION'])
  })
})
