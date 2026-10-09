import { asNode, isObject, measure, type RawNode } from './ast'
import { civilFromDays, daysFromCivil, daysInMonth, formatDate, formatDatetime, formatTime, inRange, intdiv, isValid, localDay, parseDate, parseDatetime, parseTime, weekday } from './civil'
import type { Context } from './context'
import { Decimal } from './decimal'
import * as SafeRegex from './safeRegex'
import { arity, isRowForm } from './signatures'
import * as Unicode from './unicode'
import * as UmmAlQura from './ummAlQura'
import { encode, type EncodedValue } from './envelope'
import { isNull, items, Values, type RecordSource, type Value } from './values'

/**
 * Evaluates expression ASTs (expression-language.md §4–§9). Twin of
 * backend/app/Expressions/Evaluation/Evaluator.php: same algorithms, same
 * diagnostics, same node paths. Total: every AST yields a value plus
 * diagnostics; nothing throws to the caller. Bounded by an evaluation step
 * budget (one step per node visit and per aggregated row), identical in both
 * runtimes, so both stop at the same point.
 */

export const MAX_STEPS = 100_000
export const MAX_DEPTH = 32
export const MAX_NODES = 2000
export const MAX_HOPS = 4
export const MAX_ROWS = 10_000
export const MAX_LIST = 10_000

export type DiagnosticCode = 'DIV_ZERO' | 'OVERFLOW' | 'TYPE_MISMATCH' | 'MISSING_REF' | 'INVALID_ARG' | 'INVALID_DATE' | 'LIMIT_EXCEEDED'

export interface Diagnostic {
  code: DiagnosticCode
  node: string
}

/**
 * The evaluation result envelope of expression-language.md §6: a value plus
 * every diagnostic collected, deduplicated by code and node.
 */
export class Result {
  readonly value: Value
  readonly diagnostics: readonly Diagnostic[]

  constructor(value: Value, diagnostics: readonly Diagnostic[] = []) {
    this.value = value
    this.diagnostics = diagnostics
  }

  codes(): DiagnosticCode[] {
    return this.diagnostics.map((d) => d.code)
  }

  toJSON(): { value: EncodedValue; diagnostics: Diagnostic[] } {
    return { value: encode(this.value), diagnostics: [...this.diagnostics] }
  }
}

/** Raised internally when the step or row budget is spent (expression-language.md §8). */
export class BudgetExceeded extends Error {
  constructor() {
    super('Expression evaluation budget exceeded')
    this.name = 'BudgetExceeded'
  }
}

type Decimalish = Decimal | 'DIV_ZERO' | 'OVERFLOW' | 'INVALID_ARG'

const ORDERABLE_TYPES = ['number', 'text', 'date', 'datetime', 'time', 'duration']

/** Entries of a JSON array or object with PHP's foreach keys (list index or object key). */
function entriesOf(value: unknown): [string, unknown][] {
  if (Array.isArray(value)) return value.map((v, i) => [String(i), v])
  return isObject(value) ? Object.entries(value) : []
}

function str(value: unknown): string {
  return typeof value === 'string' ? value : String(value ?? '')
}

export class Evaluator {
  private steps = 0
  private rows = 0
  private readonly diagnostics = new Map<string, Diagnostic>()
  private context: Context

  private constructor(context: Context) {
    this.context = context
  }

  static evaluate(ast: RawNode, context: Context): Result {
    const evaluator = new Evaluator(context)
    const [depth, nodes] = measure(ast)
    if (depth > MAX_DEPTH || nodes > MAX_NODES) return new Result(Values.null(), [{ code: 'LIMIT_EXCEEDED', node: '' }])
    let value: Value
    try {
      value = evaluator.eval(ast, '')
    } catch (e) {
      if (e instanceof BudgetExceeded) return new Result(Values.null(), [{ code: 'LIMIT_EXCEEDED', node: '' }])
      throw e
    }
    return new Result(value, [...evaluator.diagnostics.values()])
  }

  // ── Nodes ────────────────────────────────────────────────────────────

  private eval(node: RawNode, path: string): Value {
    if (++this.steps > MAX_STEPS) throw new BudgetExceeded()
    switch (node.k) {
      case 'lit':
        return this.literal(node, path)
      case 'list':
        return this.listNode(node, path)
      case 'ref':
        return this.reference(node, path)
      case 'un':
        return this.unary(node, path)
      case 'bin':
        return this.binary(node, path)
      case 'call':
        return this.call(node, path)
      default:
        return this.diag('TYPE_MISMATCH', path)
    }
  }

  private literal(node: RawNode, path: string): Value {
    const v = node.v ?? null
    switch (node.t) {
      case 'null':
        return Values.null()
      case 'boolean':
        return typeof v === 'boolean' ? Values.bool(v) : this.diag('TYPE_MISMATCH', path)
      case 'number': {
        const d = typeof v === 'string' ? Decimal.parse(v) : null
        return d !== null ? Values.number(d) : this.diag('TYPE_MISMATCH', path)
      }
      case 'text':
        return typeof v === 'string' ? Values.text(Unicode.nfc(v)) : this.diag('TYPE_MISMATCH', path)
      case 'date': {
        const d = typeof v === 'string' ? parseDate(v) : null
        return d !== null ? Values.date(d) : this.diag('INVALID_DATE', path)
      }
      case 'datetime': {
        const d = typeof v === 'string' ? parseDatetime(v) : null
        return d !== null ? Values.datetime(d) : this.diag('INVALID_DATE', path)
      }
      case 'time': {
        const d = typeof v === 'string' ? parseTime(v) : null
        return d !== null ? Values.time(d) : this.diag('INVALID_ARG', path)
      }
      default:
        return this.diag('TYPE_MISMATCH', path)
    }
  }

  private listNode(node: RawNode, path: string): Value {
    const values: Value[] = []
    for (const [i, item] of entriesOf(node.items ?? [])) values.push(this.eval(asNode(item), `${path}.items.${i}`))
    return this.makeList(values, path)
  }

  private makeList(values: Value[], path: string): Value {
    if (values.length > MAX_LIST) return this.diag('LIMIT_EXCEEDED', path)
    let type: string | null = null
    for (const item of values) {
      if (isNull(item)) continue
      if (type !== null && type !== item.type) return this.diag('TYPE_MISMATCH', path)
      type = item.type
    }
    return Values.list(values)
  }

  private reference(node: RawNode, path: string): Value {
    const raw = node.path ?? []
    if (!isObject(raw)) return this.diag('MISSING_REF', path)
    const segments = Object.values(raw).map(str)
    if (segments.length === 0) return this.diag('MISSING_REF', path)
    if (segments.length - 1 > MAX_HOPS) return this.diag('LIMIT_EXCEEDED', path)
    const scope = node.scope ?? 'record'
    const ctx = this.context
    let base: RecordSource | null
    switch (scope) {
      case 'user':
        return this.userValue(segments, path)
      case 'context':
        return this.contextValue(segments, path)
      case 'old':
        if (ctx.mode === 'create' || ctx.old === null) return Values.null()
        base = ctx.old
        break
      case 'row':
        base = ctx.row
        break
      case 'parent':
        base = ctx.parent
        break
      case 'record':
        base = ctx.row !== null && ctx.row.get(segments[0]!) !== null ? ctx.row : ctx.record
        break
      default:
        return this.diag('MISSING_REF', path)
    }
    if (base === null) return this.diag('MISSING_REF', path)
    return this.walk(Values.record(base), segments, path)
  }

  private walk(current: Value, segments: string[], path: string): Value {
    for (const segment of segments) {
      if (current.type === 'record') {
        const next = current.data.get(segment)
        if (next === null) return this.diag('MISSING_REF', path)
        current = next
      } else if (current.type === 'list') {
        const collected: Value[] = []
        for (const item of current.data) {
          this.countRow()
          if (isNull(item)) continue
          if (item.type !== 'record') return this.diag('TYPE_MISMATCH', path)
          const value = item.data.get(segment)
          if (value === null) return this.diag('MISSING_REF', path)
          if (value.type === 'list') collected.push(...value.data)
          else collected.push(value)
          if (collected.length > MAX_LIST) return this.diag('LIMIT_EXCEEDED', path)
        }
        current = Values.list(collected)
      } else if (isNull(current)) {
        return Values.null()
      } else {
        return this.diag('TYPE_MISMATCH', path)
      }
    }
    return current
  }

  private userValue(segments: string[], path: string): Value {
    const user = this.context.user
    const key = segments[0]!
    if (key === 'attributes') {
      if (segments.length < 2) return this.diag('MISSING_REF', path)
      const attributes = user.attributes ?? {}
      const name = segments[1]!
      const value = Object.prototype.hasOwnProperty.call(attributes, name) ? (attributes[name] ?? null) : null
      return value === null ? Values.null() : this.walk(value, segments.slice(2), path)
    }
    if (segments.length > 1) return this.diag('MISSING_REF', path)
    const text = (v: unknown): Value => (typeof v === 'string' ? Values.text(Unicode.nfc(v)) : Values.null())
    switch (key) {
      case 'id': {
        if (user.id === undefined || user.id === null) return Values.null()
        const id = typeof user.id === 'number' ? Math.trunc(user.id) : Number.parseInt(user.id, 10)
        return Values.int(Number.isNaN(id) ? 0 : id)
      }
      case 'name':
        return text(user.name)
      case 'email':
        return text(user.email)
      case 'department':
        return text(user.department)
      case 'locale':
        return text(user.locale ?? this.context.locale)
      case 'roles':
        return Values.list((user.roles ?? []).map((r) => Values.text(r)))
      case 'departments':
        return Values.list((user.departments ?? []).map((d) => Values.text(d)))
      default:
        return this.diag('MISSING_REF', path)
    }
  }

  private contextValue(segments: string[], path: string): Value {
    const ctx = this.context
    if (segments[0] === 'param') {
      if (segments.length !== 2) return this.diag('MISSING_REF', path)
      const key = segments[1]!
      return Object.prototype.hasOwnProperty.call(ctx.params, key) ? (ctx.params[key] ?? Values.null()) : Values.null()
    }
    if (segments.length > 1) return this.diag('MISSING_REF', path)
    switch (segments[0]) {
      case 'mode':
        return Values.text(ctx.mode)
      case 'form':
        return ctx.form === null ? Values.null() : Values.text(ctx.form)
      case 'locale':
        return Values.text(ctx.locale)
      case 'timezone':
        return Values.text(ctx.timezone)
      default:
        return this.diag('MISSING_REF', path)
    }
  }

  private unary(node: RawNode, path: string): Value {
    const a = this.eval(asNode(node.a), `${path}.a`)
    if (isNull(a)) return Values.null()
    switch (node.op) {
      case 'neg':
        if (a.type === 'number') return Values.number(a.data.negate())
        if (a.type === 'duration') return Values.duration(a.data.negate())
        return this.diag('TYPE_MISMATCH', path)
      case 'not':
        return a.type === 'boolean' ? Values.bool(!a.data) : this.diag('TYPE_MISMATCH', path)
      default:
        return this.diag('TYPE_MISMATCH', path)
    }
  }

  private binary(node: RawNode, path: string): Value {
    const op = node.op
    if (op === 'and' || op === 'or') return this.logic(op, node, path)
    const a = this.eval(asNode(node.a), `${path}.a`)
    const b = this.eval(asNode(node.b), `${path}.b`)
    switch (op) {
      case '+':
      case '-':
      case '*':
      case '/':
      case '%':
        return this.arithmetic(op, a, b, path)
      case '&':
        return this.textResult(this.concatText(a) + this.concatText(b), path)
      case '=':
        return Values.bool(this.equal(a, b))
      case '!=':
        return Values.bool(!this.equal(a, b))
      case '<':
      case '<=':
      case '>':
      case '>=':
        return this.order(op, a, b, path)
      default:
        return this.diag('TYPE_MISMATCH', path)
    }
  }

  private logic(op: 'and' | 'or', node: RawNode, path: string): Value {
    const a = this.eval(asNode(node.a), `${path}.a`)
    if (!isNull(a) && a.type !== 'boolean') return this.diag('TYPE_MISMATCH', path)
    if (op === 'and' && a.data === false) return Values.bool(false)
    if (op === 'or' && a.data === true) return Values.bool(true)
    const b = this.eval(asNode(node.b), `${path}.b`)
    if (!isNull(b) && b.type !== 'boolean') return this.diag('TYPE_MISMATCH', path)
    if (op === 'and') {
      if (b.data === false) return Values.bool(false)
      return isNull(a) || isNull(b) ? Values.null() : Values.bool(true)
    }
    if (b.data === true) return Values.bool(true)
    return isNull(a) || isNull(b) ? Values.null() : Values.bool(false)
  }

  private arithmetic(op: string, a: Value, b: Value, path: string): Value {
    if (isNull(a) || isNull(b)) return Values.null()
    if (a.type === 'number' && b.type === 'number') {
      const x = a.data
      const y = b.data
      return this.numberResult(op === '+' ? x.add(y) : op === '-' ? x.sub(y) : op === '*' ? x.mul(y) : op === '/' ? x.div(y) : x.mod(y), path)
    }
    if (a.type === 'date' && b.type === 'number' && (op === '+' || op === '-')) {
      if (!b.data.isInteger()) return this.diag('INVALID_ARG', path)
      const days = b.data.toSmallInt()
      return this.dateResult(days === null ? null : a.data + (op === '+' ? days : -days), path)
    }
    if (a.type === 'date' && b.type === 'date' && op === '-') return Values.int(a.data - b.data)
    if (a.type === 'datetime' && b.type === 'duration' && (op === '+' || op === '-')) {
      const seconds = b.data.toSmallInt()
      const result = seconds === null ? null : a.data + (op === '+' ? seconds : -seconds)
      return result !== null && inRange(intdiv(result, 86400)) ? Values.datetime(result) : this.diag('INVALID_DATE', path)
    }
    if (a.type === 'datetime' && b.type === 'datetime' && op === '-') return Values.duration(Decimal.of(a.data - b.data))
    if (a.type === 'duration' && b.type === 'duration' && (op === '+' || op === '-')) {
      return this.durationResult(op === '+' ? a.data.add(b.data) : a.data.sub(b.data), path)
    }
    if (a.type === 'duration' && b.type === 'number' && (op === '*' || op === '/')) {
      const result = op === '*' ? a.data.mul(b.data) : a.data.div(b.data)
      return this.durationResult(typeof result === 'string' ? result : result.trunc(0), path)
    }
    return this.diag('TYPE_MISMATCH', path)
  }

  private numberResult(result: Decimalish, path: string): Value {
    return typeof result === 'string' ? this.diag(result, path) : Values.number(result)
  }

  private durationResult(result: Decimalish, path: string): Value {
    return typeof result === 'string' ? this.diag(result, path) : Values.duration(result)
  }

  private dateResult(days: number | null, path: string): Value {
    return days !== null && inRange(days) ? Values.date(days) : this.diag('INVALID_DATE', path)
  }

  private textResult(text: string, path: string): Value {
    return Unicode.length(text) > Unicode.MAX_LENGTH ? this.diag('LIMIT_EXCEEDED', path) : Values.text(text)
  }

  private order(op: string, a: Value, b: Value, path: string): Value {
    if (isNull(a) || isNull(b)) return Values.null()
    const cmp = this.compare(a, b)
    if (cmp === null) return this.diag('TYPE_MISMATCH', path)
    switch (op) {
      case '<':
        return Values.bool(cmp < 0)
      case '<=':
        return Values.bool(cmp <= 0)
      case '>':
        return Values.bool(cmp > 0)
      default:
        return Values.bool(cmp >= 0)
    }
  }

  /** Ordering of two non-null values of one orderable type, else null. */
  private compare(a: Value, b: Value): number | null {
    if (a.type !== b.type) return null
    if ((a.type === 'number' || a.type === 'duration') && (b.type === 'number' || b.type === 'duration')) return a.data.compare(b.data)
    if (a.type === 'text' && b.type === 'text') return Unicode.compare(a.data, b.data)
    if ((a.type === 'date' || a.type === 'datetime' || a.type === 'time') && (b.type === 'date' || b.type === 'datetime' || b.type === 'time')) {
      return a.data === b.data ? 0 : a.data < b.data ? -1 : 1
    }
    return null
  }

  private equal(a: Value, b: Value): boolean {
    if (isNull(a) || isNull(b)) return isNull(a) && isNull(b)
    if (a.type !== b.type) return false
    if ((a.type === 'number' || a.type === 'duration') && (b.type === 'number' || b.type === 'duration')) return a.data.equals(b.data)
    if (a.type === 'list' && b.type === 'list') return a.data.length === b.data.length && a.data.every((item, i) => this.equal(item, b.data[i]!))
    if (a.type === 'record' && b.type === 'record') return this.sameRecord(a.data, b.data)
    return a.data === b.data
  }

  private sameRecord(a: RecordSource, b: RecordSource): boolean {
    const ia = a.identity()
    return ia !== null ? ia === b.identity() : a === b
  }

  // ── Text conversion ──────────────────────────────────────────────────

  /** `&` / concat: null → "" */
  private concatText(v: Value): string {
    return isNull(v) ? '' : (this.toText(v) ?? '')
  }

  private toText(v: Value): string | null {
    switch (v.type) {
      case 'null':
        return null
      case 'text':
        return v.data
      case 'number':
      case 'duration':
        return v.data.toString()
      case 'boolean':
        return v.data ? 'true' : 'false'
      case 'date':
        return formatDate(v.data)
      case 'datetime':
        return formatDatetime(v.data)
      case 'time':
        return formatTime(v.data)
      case 'list':
        return v.data
          .map((i) => this.toText(i))
          .filter((s): s is string => s !== null)
          .join(', ')
      case 'record':
        return v.data.title()
    }
  }

  // ── Functions ────────────────────────────────────────────────────────

  private call(node: RawNode, path: string): Value {
    const fn = str(node.fn ?? '')
    const args = entriesOf(node.args ?? []).map(([, arg]) => asNode(arg))
    const counts = arity(fn)
    if (counts === null) return this.diag('TYPE_MISMATCH', path)
    const [min, max] = counts
    if (args.length < min || (max !== null && args.length > max)) return this.diag('TYPE_MISMATCH', path)
    const arg = (i: number): Value => this.eval(args[i]!, `${path}.args.${i}`)

    // Lazily evaluated and row-scoped functions.
    switch (fn) {
      case 'if': {
        const cond = arg(0)
        if (!isNull(cond) && cond.type !== 'boolean') return this.diag('TYPE_MISMATCH', path)
        return cond.data === true ? arg(1) : arg(2)
      }
      case 'switch': {
        const x = arg(0)
        const n = args.length
        for (let i = 1; i + 1 < n; i += 2) {
          if (this.equal(x, arg(i))) return arg(i + 1)
        }
        return (n - 1) % 2 === 1 ? arg(n - 1) : Values.null()
      }
      case 'coalesce':
        for (let i = 0; i < args.length; i++) {
          const v = arg(i)
          if (!isNull(v)) return v
        }
        return Values.null()
      case 'changed':
      case 'changed_from_to':
        return this.changed(fn, args, path)
    }
    if (isRowForm(fn, args.length)) return this.rowAggregate(fn, args, path)

    const values: Value[] = []
    for (let i = 0; i < args.length; i++) values.push(arg(i))
    return this.apply(fn, values, path)
  }

  private changed(fn: string, args: RawNode[], path: string): Value {
    const ref = args[0]!
    if (ref.k !== 'ref') return this.diag('TYPE_MISMATCH', path)
    const now = this.eval(ref, `${path}.args.0`)
    const old = this.eval({ k: 'ref', scope: 'old', path: ref.path ?? [] }, `${path}.args.0`)
    if (fn === 'changed') return Values.bool(this.context.mode !== 'create' && !this.equal(now, old))
    const from = this.eval(args[1]!, `${path}.args.1`)
    const to = this.eval(args[2]!, `${path}.args.2`)
    return Values.bool(this.equal(old, from) && this.equal(now, to))
  }

  private rowAggregate(fn: string, args: RawNode[], path: string): Value {
    const rows = this.eval(args[0]!, `${path}.args.0`)
    if (isNull(rows)) return fn === 'sum' || fn === 'count' ? Values.int(0) : fn === 'join' ? Values.text('') : Values.null()
    if (rows.type !== 'list') return this.diag('TYPE_MISMATCH', path)
    const results: Value[] = []
    const outer = this.context
    for (const row of rows.data) {
      this.countRow()
      if (isNull(row)) continue
      if (row.type !== 'record') return this.diag('TYPE_MISMATCH', path)
      this.context = outer.withRow(row.data)
      try {
        results.push(this.eval(args[1]!, `${path}.args.1`))
      } finally {
        this.context = outer
      }
    }
    if (fn === 'count') {
      let count = 0
      for (const r of results) {
        if (!isNull(r) && r.type !== 'boolean') return this.diag('TYPE_MISMATCH', path)
        count += r.data === true ? 1 : 0
      }
      return Values.int(count)
    }
    if (fn === 'join') {
      const sep = this.eval(args[2]!, `${path}.args.2`)
      return this.join(results, sep, path)
    }
    return this.apply(fn, [Values.list(results)], path)
  }

  private countRow(): void {
    if (++this.steps > MAX_STEPS) throw new BudgetExceeded()
    if (++this.rows > MAX_ROWS) throw new BudgetExceeded()
  }

  private apply(fn: string, a: Value[], path: string): Value {
    const [x = Values.null(), y = Values.null(), z = Values.null()] = a
    // Functions that accept null arguments.
    switch (fn) {
      case 'is_empty':
        return Values.bool(isNull(x) || (x.type === 'text' && x.data === '') || (x.type === 'list' && x.data.length === 0))
      case 'is_null':
        return Values.bool(isNull(x))
      case 'in':
        if (isNull(y)) return Values.bool(false)
        if (y.type !== 'list') return this.diag('TYPE_MISMATCH', path)
        return Values.bool(y.data.some((item) => this.equal(x, item)))
      case 'concat':
        return this.textResult(a.map((v) => this.concatText(v)).join(''), path)
      case 'min':
      case 'max':
        return this.minMax(fn, a, path)
      case 'sum':
      case 'avg':
      case 'count':
      case 'first':
      case 'last':
      case 'distinct':
        return this.aggregate(fn, x, path)
      case 'join':
        if (isNull(x)) return Values.text('')
        if (x.type !== 'list') return this.diag('TYPE_MISMATCH', path)
        return this.join(x.data, y, path)
      case 'to_text':
        return isNull(x) ? Values.null() : this.nullableText(this.toText(x), path)
      case 'today':
        return Values.date(this.context.today)
      case 'now':
        return Values.datetime(this.context.now)
      case 'has_role':
        if (isNull(x)) return Values.null()
        return x.type === 'text' ? Values.bool((this.context.user.roles ?? []).includes(x.data)) : this.diag('TYPE_MISMATCH', path)
      case 'in_department':
        return this.inDepartment(a, path)
    }
    if (a.some(isNull)) return Values.null()

    switch (fn) {
      // Math
      case 'abs':
        return this.num1(x, path, (d) => d.abs())
      case 'round':
      case 'trunc':
        return this.rounding(a, path, fn)
      case 'floor':
        return this.num1(x, path, (d) => d.floor())
      case 'ceil':
        return this.num1(x, path, (d) => d.ceil())
      case 'mod':
        return this.arithmetic('%', x, y, path)
      case 'power':
        return this.power(x, y, path)
      case 'sqrt':
        return this.num1(x, path, (d) => (d.isNegative() ? 'INVALID_ARG' : d.sqrt()))
      case 'clamp':
        return this.clamp(x, y, z, path)
      case 'sign':
        return this.num1(x, path, (d) => Decimal.of(d.sign))
      // Text
      case 'len':
        return x.type === 'text' ? Values.int(Unicode.length(x.data)) : this.diag('TYPE_MISMATCH', path)
      case 'upper':
        return x.type === 'text' ? this.textResult(Unicode.nfc(Unicode.upper(x.data)), path) : this.diag('TYPE_MISMATCH', path)
      case 'lower':
        return x.type === 'text' ? this.textResult(Unicode.nfc(Unicode.lower(x.data)), path) : this.diag('TYPE_MISMATCH', path)
      case 'trim':
        return x.type === 'text' ? Values.text(Unicode.trim(x.data)) : this.diag('TYPE_MISMATCH', path)
      case 'left':
      case 'right':
        return this.leftRight(fn, x, y, path)
      case 'mid':
        return this.mid(x, y, z, path)
      case 'contains':
      case 'starts_with':
      case 'ends_with':
        return this.textPredicate(fn, x, y, path)
      case 'replace':
        return this.replace(x, y, z, path)
      case 'split':
        return this.split(x, y, path)
      case 'pad_left':
        return this.padLeft(x, y, z, path)
      case 'matches':
        return this.matches(x, y, path)
      case 'normalize_arabic':
        return x.type === 'text' ? Values.text(Unicode.nfc(Unicode.normalizeArabic(x.data))) : this.diag('TYPE_MISMATCH', path)
      // Dates
      case 'date':
        return this.makeDate(a, path)
      case 'datetime':
        return x.type === 'date' && y.type === 'time' ? Values.datetime(x.data * 86400 + y.data) : this.diag('TYPE_MISMATCH', path)
      case 'year':
      case 'month':
      case 'day':
        return this.dateParts(fn, x, path)
      case 'weekday':
        return x.type === 'date' ? Values.int(weekday(x.data)) : this.diag('TYPE_MISMATCH', path)
      case 'add_days':
      case 'add_months':
      case 'add_years':
        return this.addToDate(fn, x, y, path)
      case 'diff_days':
        return x.type === 'date' && y.type === 'date' ? Values.int(y.data - x.data) : this.diag('TYPE_MISMATCH', path)
      case 'diff_months':
        return this.diffMonths(x, y, path)
      case 'start_of_month':
      case 'end_of_month':
        return this.monthBoundary(fn, x, path)
      case 'seconds':
      case 'minutes':
      case 'hours':
      case 'days':
        return this.makeDuration(fn, x, path)
      case 'duration_seconds':
        return x.type === 'duration' ? Values.number(x.data) : this.diag('TYPE_MISMATCH', path)
      case 'to_date':
        return this.toDate(x, path)
      case 'hijri_year':
      case 'hijri_month':
      case 'hijri_day':
        return this.hijriPart(fn, x, path)
      case 'from_hijri':
        return this.fromHijri(a, path)
      case 'hijri_text':
        return this.hijriText(x, path)
      case 'is_working_day':
        return x.type === 'date' ? Values.bool(this.context.workingCalendar().isWorkingDay(x.data)) : this.diag('TYPE_MISMATCH', path)
      case 'add_working_days':
        return this.addWorkingDays(x, y, path)
      // Conversion
      case 'to_number':
        return this.toNumber(x, path)
      case 'to_boolean':
        return this.toBoolean(x, path)
      default:
        return this.diag('TYPE_MISMATCH', path)
    }
  }

  private nullableText(s: string | null, path: string): Value {
    return s === null ? Values.null() : this.textResult(s, path)
  }

  private num1(x: Value, path: string, f: (d: Decimal) => Decimalish): Value {
    return x.type === 'number' ? this.numberResult(f(x.data), path) : this.diag('TYPE_MISMATCH', path)
  }

  /** An integer argument within [min, max], or null. */
  private intArg(v: Value, min: number, max: number): number | null {
    if (v.type !== 'number' || !v.data.isInteger()) return null
    const n = v.data.toSmallInt()
    return n !== null && n >= min && n <= max ? n : null
  }

  private rounding(a: Value[], path: string, mode: 'round' | 'trunc'): Value {
    const [x, digitsArg] = a
    if (x === undefined || x.type !== 'number' || (digitsArg !== undefined && digitsArg.type !== 'number')) return this.diag('TYPE_MISMATCH', path)
    const digits = digitsArg !== undefined ? this.intArg(digitsArg, -10, 20) : 0
    if (digits === null) return this.diag('INVALID_ARG', path)
    return this.numberResult(mode === 'round' ? x.data.round(digits) : x.data.trunc(digits), path)
  }

  private power(x: Value, y: Value, path: string): Value {
    if (x.type !== 'number' || y.type !== 'number') return this.diag('TYPE_MISMATCH', path)
    const n = this.intArg(y, 0, 64)
    return n === null ? this.diag('INVALID_ARG', path) : this.numberResult(x.data.pow(n), path)
  }

  private clamp(x: Value, lo: Value, hi: Value, path: string): Value {
    const c1 = this.compare(x, lo)
    const c2 = this.compare(x, hi)
    if (c1 === null || c2 === null) return this.diag('TYPE_MISMATCH', path)
    return c1 < 0 ? lo : c2 > 0 ? hi : x
  }

  private minMax(fn: 'min' | 'max', a: Value[], path: string): Value {
    const values = a.length === 1 && a[0]!.type === 'list' ? items(a[0]!) : a
    let best: Value | null = null
    for (const v of values) {
      if (isNull(v)) continue
      if (!ORDERABLE_TYPES.includes(v.type)) return this.diag('TYPE_MISMATCH', path)
      if (best === null) {
        best = v
        continue
      }
      const cmp = this.compare(v, best)
      if (cmp === null) return this.diag('TYPE_MISMATCH', path)
      if ((fn === 'min' && cmp < 0) || (fn === 'max' && cmp > 0)) best = v
    }
    return best ?? Values.null()
  }

  private aggregate(fn: string, list: Value, path: string): Value {
    if (isNull(list)) {
      if (fn === 'sum' || fn === 'count') return Values.int(0)
      return fn === 'distinct' ? Values.list([]) : Values.null()
    }
    if (list.type !== 'list') return this.diag('TYPE_MISMATCH', path)
    const values = list.data.filter((v) => !isNull(v))
    switch (fn) {
      case 'count':
        return Values.int(values.length)
      case 'first':
        return values[0] ?? Values.null()
      case 'last':
        return values[values.length - 1] ?? Values.null()
      case 'distinct': {
        const out: Value[] = []
        for (const v of values) {
          if (!out.some((seen) => this.equal(seen, v))) out.push(v)
        }
        return Values.list(out)
      }
    }
    // sum / avg
    if (values.length === 0) return fn === 'sum' ? Values.int(0) : Values.null()
    const type = values[0]!.type
    if (type !== 'number' && type !== 'duration') return this.diag('TYPE_MISMATCH', path)
    let total: Decimalish = Decimal.zero()
    for (const v of values) {
      if (v.type !== type || (v.type !== 'number' && v.type !== 'duration')) return this.diag('TYPE_MISMATCH', path)
      total = total.add(v.data)
      if (typeof total === 'string') return this.diag(total, path)
    }
    if (fn === 'avg') {
      total = total.div(Decimal.of(values.length))
      if (typeof total === 'string') return this.diag(total, path)
      if (type === 'duration') {
        total = total.trunc(0)
        if (typeof total === 'string') return this.diag(total, path)
      }
    }
    return type === 'number' ? Values.number(total) : Values.duration(total)
  }

  private join(values: readonly Value[], sep: Value, path: string): Value {
    if (isNull(sep)) return Values.null()
    if (sep.type !== 'text') return this.diag('TYPE_MISMATCH', path)
    const parts: string[] = []
    for (const item of values) {
      if (!isNull(item)) parts.push(this.toText(item) ?? '')
    }
    return this.textResult(parts.join(sep.data), path)
  }

  private inDepartment(a: Value[], path: string): Value {
    const [code = Values.null(), descendants] = a
    if (isNull(code)) return Values.null()
    if (code.type !== 'text' || (descendants !== undefined && !isNull(descendants) && descendants.type !== 'boolean')) return this.diag('TYPE_MISMATCH', path)
    const user = this.context.user
    if (descendants !== undefined && descendants.data === true) return Values.bool((user.departments ?? []).includes(code.data))
    return Values.bool((user.department ?? null) === code.data)
  }

  private leftRight(fn: 'left' | 'right', s: Value, count: Value, path: string): Value {
    if (s.type !== 'text' || count.type !== 'number') return this.diag('TYPE_MISMATCH', path)
    const n = this.intArg(count, 0, Number.MAX_SAFE_INTEGER)
    if (n === null) return this.diag('INVALID_ARG', path)
    const len = Unicode.length(s.data)
    return Values.text(fn === 'left' ? Unicode.substr(s.data, 0, n) : Unicode.substr(s.data, Math.max(0, len - n)))
  }

  private mid(s: Value, startArg: Value, countArg: Value, path: string): Value {
    if (s.type !== 'text' || startArg.type !== 'number' || countArg.type !== 'number') return this.diag('TYPE_MISMATCH', path)
    const start = this.intArg(startArg, 1, Number.MAX_SAFE_INTEGER)
    const count = this.intArg(countArg, 0, Number.MAX_SAFE_INTEGER)
    if (start === null || count === null) return this.diag('INVALID_ARG', path)
    return Values.text(Unicode.substr(s.data, start - 1, count))
  }

  private textPredicate(fn: string, s: Value, sub: Value, path: string): Value {
    if (s.type !== 'text' || sub.type !== 'text') return this.diag('TYPE_MISMATCH', path)
    if (fn === 'contains') return Values.bool(s.data.includes(sub.data))
    if (fn === 'starts_with') return Values.bool(s.data.startsWith(sub.data))
    return Values.bool(s.data.endsWith(sub.data))
  }

  private replace(s: Value, find: Value, replacement: Value, path: string): Value {
    if (s.type !== 'text' || find.type !== 'text' || replacement.type !== 'text') return this.diag('TYPE_MISMATCH', path)
    if (find.data === '') return s
    return this.textResult(Unicode.nfc(s.data.split(find.data).join(replacement.data)), path)
  }

  private split(s: Value, sep: Value, path: string): Value {
    if (s.type !== 'text' || sep.type !== 'text') return this.diag('TYPE_MISMATCH', path)
    if (sep.data === '') return this.diag('INVALID_ARG', path)
    const parts = s.data.split(sep.data)
    if (parts.length > MAX_LIST) return this.diag('LIMIT_EXCEEDED', path)
    return Values.list(parts.map((p) => Values.text(Unicode.nfc(p))))
  }

  private padLeft(s: Value, width: Value, ch: Value, path: string): Value {
    if (s.type !== 'text' || width.type !== 'number' || ch.type !== 'text') return this.diag('TYPE_MISMATCH', path)
    if (Unicode.length(ch.data) !== 1) return this.diag('INVALID_ARG', path)
    const n = this.intArg(width, 0, Number.MAX_SAFE_INTEGER)
    if (n === null) return this.diag('INVALID_ARG', path)
    if (n > Unicode.MAX_LENGTH) return this.diag('LIMIT_EXCEEDED', path)
    const len = Unicode.length(s.data)
    return this.textResult(ch.data.repeat(Math.max(0, n - len)) + s.data, path)
  }

  private matches(s: Value, pattern: Value, path: string): Value {
    if (s.type !== 'text' || pattern.type !== 'text') return this.diag('TYPE_MISMATCH', path)
    const result = SafeRegex.matches(s.data, pattern.data)
    return result === null ? this.diag('INVALID_ARG', path) : Values.bool(result)
  }

  private makeDate(a: Value[], path: string): Value {
    if (a.some((v) => v.type !== 'number')) return this.diag('TYPE_MISMATCH', path)
    const y = this.intArg(a[0]!, 1, 9999)
    const m = this.intArg(a[1]!, 1, 12)
    const d = this.intArg(a[2]!, 1, 31)
    if (y === null || m === null || d === null || !isValid(y, m, d)) return this.diag('INVALID_ARG', path)
    return Values.date(daysFromCivil(y, m, d))
  }

  private dateParts(fn: string, x: Value, path: string): Value {
    if (x.type !== 'date') return this.diag('TYPE_MISMATCH', path)
    const [y, m, d] = civilFromDays(x.data)
    return Values.int(fn === 'year' ? y : fn === 'month' ? m : d)
  }

  private addToDate(fn: string, x: Value, count: Value, path: string): Value {
    if (x.type !== 'date' || count.type !== 'number') return this.diag('TYPE_MISMATCH', path)
    const n = this.intArg(count, -4_000_000, 4_000_000)
    if (n === null) return count.data.isInteger() ? this.diag('INVALID_DATE', path) : this.diag('INVALID_ARG', path)
    if (fn === 'add_days') return this.dateResult(x.data + n, path)
    const [y, m, d] = civilFromDays(x.data)
    const months = y * 12 + (m - 1) + (fn === 'add_months' ? n : n * 12)
    const ny = intdiv(months, 12)
    const nm = (months % 12) + 1
    if (ny < 1 || ny > 9999) return this.diag('INVALID_DATE', path)
    return Values.date(daysFromCivil(ny, nm, Math.min(d, daysInMonth(ny, nm))))
  }

  private diffMonths(from: Value, to: Value, path: string): Value {
    if (from.type !== 'date' || to.type !== 'date') return this.diag('TYPE_MISMATCH', path)
    const [y1, m1, d1] = civilFromDays(from.data)
    const [y2, m2, d2] = civilFromDays(to.data)
    let months = (y2 - y1) * 12 + (m2 - m1)
    if (months > 0 && d2 < d1) months--
    else if (months < 0 && d2 > d1) months++
    return Values.int(months)
  }

  private monthBoundary(fn: string, x: Value, path: string): Value {
    if (x.type !== 'date') return this.diag('TYPE_MISMATCH', path)
    const [y, m] = civilFromDays(x.data)
    return Values.date(daysFromCivil(y, m, fn === 'start_of_month' ? 1 : daysInMonth(y, m)))
  }

  private makeDuration(fn: string, x: Value, path: string): Value {
    if (x.type !== 'number') return this.diag('TYPE_MISMATCH', path)
    const factor = fn === 'seconds' ? 1 : fn === 'minutes' ? 60 : fn === 'hours' ? 3600 : 86400
    const seconds = x.data.mul(Decimal.of(factor))
    return this.durationResult(typeof seconds === 'string' ? seconds : seconds.trunc(0), path)
  }

  private toDate(v: Value, path: string): Value {
    if (v.type === 'date') return v
    if (v.type === 'text') {
      const days = parseDate(Unicode.trim(v.data))
      return days === null ? this.diag('INVALID_ARG', path) : Values.date(days)
    }
    if (v.type === 'datetime') {
      let days: number
      try {
        days = localDay(v.data, this.context.timezone)
      } catch {
        return this.diag('INVALID_ARG', path)
      }
      return this.dateResult(days, path)
    }
    return this.diag('TYPE_MISMATCH', path)
  }

  private hijriPart(fn: string, x: Value, path: string): Value {
    if (x.type !== 'date') return this.diag('TYPE_MISMATCH', path)
    const h = UmmAlQura.fromDays(x.data)
    if (h === null) return this.diag('INVALID_DATE', path)
    return Values.int(fn === 'hijri_year' ? h[0] : fn === 'hijri_month' ? h[1] : h[2])
  }

  private fromHijri(a: Value[], path: string): Value {
    if (a.some((v) => v.type !== 'number')) return this.diag('TYPE_MISMATCH', path)
    const y = this.intArg(a[0]!, 1, 9999)
    const m = this.intArg(a[1]!, -99, 99)
    const d = this.intArg(a[2]!, -99, 99)
    if (y === null || m === null || d === null) return this.diag('INVALID_ARG', path)
    const days = UmmAlQura.toDays(y, m, d)
    return typeof days === 'string' ? this.diag(days, path) : Values.date(days)
  }

  private hijriText(x: Value, path: string): Value {
    if (x.type !== 'date') return this.diag('TYPE_MISMATCH', path)
    const h = UmmAlQura.fromDays(x.data)
    if (h === null) return this.diag('INVALID_DATE', path)
    return Values.text(`${String(h[0]).padStart(4, '0')}-${String(h[1]).padStart(2, '0')}-${String(h[2]).padStart(2, '0')}`)
  }

  private addWorkingDays(x: Value, count: Value, path: string): Value {
    if (x.type !== 'date' || count.type !== 'number') return this.diag('TYPE_MISMATCH', path)
    const n = this.intArg(count, -36600, 36600)
    if (n === null) return this.diag('INVALID_ARG', path)
    const calendar = this.context.workingCalendar()
    if (!calendar.hasWorkingDays()) return this.diag('INVALID_ARG', path)
    let day = x.data
    const step = n >= 0 ? 1 : -1
    let remaining = Math.abs(n)
    while (remaining > 0) {
      this.countRow()
      day += step
      if (!inRange(day)) return this.diag('INVALID_DATE', path)
      if (calendar.isWorkingDay(day)) remaining--
    }
    return Values.date(day)
  }

  private toNumber(v: Value, path: string): Value {
    if (v.type === 'number') return v
    if (v.type === 'boolean') return Values.int(v.data ? 1 : 0)
    if (v.type !== 'text') return this.diag('TYPE_MISMATCH', path)
    const d = Decimal.parse(Unicode.asciiDigits(Unicode.trim(v.data)))
    if (d === null) return this.diag('INVALID_ARG', path)
    return this.numberResult(Decimal.limit(d), path)
  }

  private toBoolean(v: Value, path: string): Value {
    if (v.type === 'boolean') return v
    if (v.type === 'number') {
      if (v.data.isZero()) return Values.bool(false)
      return v.data.equals(Decimal.of(1)) ? Values.bool(true) : this.diag('INVALID_ARG', path)
    }
    if (v.type === 'text') {
      // PHP strtolower: ASCII only.
      const word = Unicode.trim(v.data).replace(/[A-Z]/g, (c) => c.toLowerCase())
      if (word === 'true') return Values.bool(true)
      if (word === 'false') return Values.bool(false)
      return this.diag('INVALID_ARG', path)
    }
    return this.diag('TYPE_MISMATCH', path)
  }

  private diag(code: DiagnosticCode, path: string): Value {
    const key = `${code}|${path}`
    if (!this.diagnostics.has(key)) this.diagnostics.set(key, { code, node: path.replace(/^\.+/, '') })
    return Values.null()
  }
}

/** Evaluates an AST in a context; never throws (expression-language.md §1). */
export function evaluate(ast: RawNode, context: Context): Result {
  return Evaluator.evaluate(ast, context)
}
