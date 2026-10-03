import { sortKeys, type AstNode, type RawNode } from './ast'
import { Context } from './context'
import { Evaluator, type Result } from './evaluator'
import { Parser } from './parser'
import { TypeChecker, type ReferenceResolver } from './typeChecker'

/**
 * Facade over the expression language (docs/expression-language.md), the
 * client twin of backend/app/Expressions/ExpressionService.php: parse text to
 * the AST, type-check it, and evaluate it. Pure — no I/O, no generated or
 * evaluated code. The server's parser and checker remain authoritative for
 * what is stored; this runtime gives the form builder and the form runtime
 * identical results in the browser.
 */

/** Parses expression text to its AST; throws StaticError. */
export function parse(source: string): AstNode {
  return Parser.parse(source)
}

/** Type-checks an AST; returns its static type or throws StaticError. */
export function check(ast: RawNode, resolver: ReferenceResolver | null = null, expected: string | null = null): string {
  return TypeChecker.check(ast, resolver, expected)
}

/** Parses and checks an expression; the AST is returned in canonical key order. */
export function compile(source: string, resolver: ReferenceResolver | null = null, expected: string | null = null): { ast: AstNode; type: string } {
  const ast = Parser.parse(source)
  const type = TypeChecker.check(ast, resolver, expected)
  return { ast: sortKeys(ast) as AstNode, type }
}

/** Evaluates an AST; never throws: problems are diagnostics on the result. */
export function evaluate(ast: RawNode, context: Context): Result {
  return Evaluator.evaluate(ast, context)
}

export { canonicalJson, measure, references, sortKeys } from './ast'
export type { AstNode, BinaryOp, RawNode, Scope } from './ast'
export { Context } from './context'
export type { ContextFields, ContextUser } from './context'
export { Decimal } from './decimal'
export { decode, encode, record } from './envelope'
export type { EncodedValue } from './envelope'
export { Result } from './evaluator'
export type { Diagnostic, DiagnosticCode } from './evaluator'
export { StaticError } from './staticError'
export type { StaticErrorCode } from './staticError'
export type { ReferenceResolver } from './typeChecker'
export { ArrayRecord, Values } from './values'
export type { RecordSource, Value, ValueType } from './values'
export { WorkingCalendar } from './workingCalendar'
export type { WorkingCalendarData } from './workingCalendar'
