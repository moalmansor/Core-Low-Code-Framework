import { asNode, isObject, measure, members, type RawNode } from './ast'
import { MAX_DEPTH, MAX_HOPS, MAX_NODES } from './evaluator'
import { arity, exists, isRowForm, paramTypes, requiresReference, returns } from './signatures'
import { StaticError, type StaticErrorCode } from './staticError'

/**
 * Returns the static type of a record/old/row/parent reference, or null when
 * it does not exist. `rowsPath` is the rows reference of the enclosing
 * row-form aggregate, when checking its per-row expression.
 */
export type ReferenceResolver = (scope: string, path: string[], rowsPath: string[] | null) => string | null

const ORDERABLE = ['number', 'text', 'date', 'datetime', 'time', 'duration']

const USER_KEYS: Readonly<Record<string, string>> = {
  id: 'number',
  name: 'text',
  email: 'text',
  department: 'text',
  locale: 'text',
  roles: 'list<text>',
  departments: 'list<text>',
}

const CONTEXT_KEYS: Readonly<Record<string, string>> = { mode: 'text', form: 'text', locale: 'text', timezone: 'text' }

const ARITHMETIC: Readonly<Record<string, Readonly<Record<string, string>>>> = {
  'number|number': { '+': 'number', '-': 'number', '*': 'number', '/': 'number', '%': 'number' },
  'date|number': { '+': 'date', '-': 'date' },
  'date|date': { '-': 'number' },
  'datetime|duration': { '+': 'datetime', '-': 'datetime' },
  'datetime|datetime': { '-': 'duration' },
  'duration|duration': { '+': 'duration', '-': 'duration' },
  'duration|number': { '*': 'duration', '/': 'duration' },
}

function has(table: Readonly<Record<string, unknown>>, key: string): boolean {
  return Object.prototype.hasOwnProperty.call(table, key)
}

function loose(t: string): boolean {
  return t === 'any' || t === 'null'
}

function isList(t: string): boolean {
  return t.startsWith('list<')
}

function itemType(list: string): string {
  return list.slice(5, -1)
}

function stringsOf(value: unknown): string[] {
  return members(value).map((v) => (typeof v === 'string' ? v : String(v)))
}

/**
 * Save-time checker of expression-language.md §3.1: bounds (DEPTH,
 * PATH_DEPTH), function existence and arity, operand and argument types,
 * row-scope validity, and reference existence when a resolver is supplied.
 * Static types are strings: null, boolean, number, text, date, datetime, time,
 * duration, record, any, and `list<T>`. Twin of
 * backend/app/Expressions/Checking/TypeChecker.php.
 *
 * Only definite conflicts are rejected: a value whose static type is `any`
 * (unresolved reference, mixed branches) is re-checked at runtime instead.
 */
export class TypeChecker {
  /** Rows path of the enclosing row-form aggregate, when checking its per-row expression. */
  private rowScope: string[] | null = null
  private readonly resolver: ReferenceResolver | null

  private constructor(resolver: ReferenceResolver | null) {
    this.resolver = resolver
  }

  /**
   * The expression's static type; throws StaticError.
   *
   * @param expected required result type (e.g. `boolean` for conditions)
   */
  static check(ast: RawNode, resolver: ReferenceResolver | null = null, expected: string | null = null): string {
    const [depth, nodes] = measure(ast)
    if (depth > MAX_DEPTH) throw new StaticError('DEPTH', `Expression nesting exceeds ${MAX_DEPTH} levels`)
    if (nodes > MAX_NODES) throw new StaticError('DEPTH', `Expression exceeds ${MAX_NODES} nodes`)
    const type = new TypeChecker(resolver).type(ast, '')
    if (expected !== null && !TypeChecker.assignable(type, expected)) throw new StaticError('TYPE', `Expected ${expected}, got ${type}`)
    return type
  }

  static assignable(from: string, to: string): boolean {
    if (from === to || from === 'any' || from === 'null' || to === 'any') return true
    if (isList(from) && isList(to)) return TypeChecker.assignable(itemType(from), itemType(to))
    return false
  }

  private type(node: RawNode, path: string): string {
    switch (node.k) {
      case 'lit':
        return this.literal(node, path)
      case 'list':
        return this.listType(node, path)
      case 'ref':
        return this.reference(node, path)
      case 'un':
        return this.unary(node, path)
      case 'bin':
        return this.binary(node, path)
      case 'call':
        return this.call(node, path)
      default:
        return this.fail('SYNTAX', 'Unknown node kind', path)
    }
  }

  private literal(node: RawNode, path: string): string {
    const t = node.t
    if (typeof t !== 'string' || !['null', 'boolean', 'number', 'text', 'date', 'datetime', 'time'].includes(t)) this.fail('SYNTAX', 'Unknown literal type', path)
    return t
  }

  private listType(node: RawNode, path: string): string {
    const types: string[] = []
    const items = node.items ?? []
    const entries: [string, unknown][] = Array.isArray(items) ? items.map((v, i) => [String(i), v]) : isObject(items) ? Object.entries(items) : []
    for (const [i, item] of entries) types.push(this.type(asNode(item), `${path}.items.${i}`))
    return `list<${this.common(types, path, 'List items must share one type')}>`
  }

  private reference(node: RawNode, path: string): string {
    const segments = stringsOf(node.path ?? [])
    const scope = typeof node.scope === 'string' ? node.scope : String(node.scope ?? 'record')
    if (segments.length === 0) this.fail('SYNTAX', 'Empty reference', path)
    if (segments.length - 1 > MAX_HOPS) this.fail('PATH_DEPTH', `Relation paths are limited to ${MAX_HOPS} hops`, path)
    if (scope === 'user') {
      if (segments[0] === 'attributes') return segments.length >= 2 ? 'any' : this.fail('TYPE', 'User attribute key missing', path)
      const key = segments[0]!
      return segments.length === 1 && has(USER_KEYS, key) ? USER_KEYS[key]! : this.fail('TYPE', `Unknown user property '${segments.join('.')}'`, path)
    }
    if (scope === 'context') {
      if (segments[0] === 'param') return segments.length === 2 ? 'any' : this.fail('TYPE', 'Parameter key missing', path)
      const key = segments[0]!
      return segments.length === 1 && has(CONTEXT_KEYS, key) ? CONTEXT_KEYS[key]! : this.fail('TYPE', `Unknown context property '${segments.join('.')}'`, path)
    }
    if (!['record', 'old', 'row', 'parent'].includes(scope)) this.fail('SYNTAX', `Unknown scope '${scope}'`, path)
    if ((scope === 'row' || scope === 'parent') && this.rowScope === null) this.fail('TYPE', `@${scope} is only valid inside a row-form aggregate`, path)
    if (this.resolver === null) return 'any'
    return this.resolver(scope, segments, this.rowScope) ?? this.fail('TYPE', `Unknown reference '${segments.join('.')}'`, path)
  }

  private unary(node: RawNode, path: string): string {
    const a = this.type(asNode(node.a), `${path}.a`)
    switch (node.op) {
      case 'neg':
        if (['any', 'null', 'number', 'duration'].includes(a)) return a === 'null' ? 'number' : a
        return this.fail('TYPE', `Cannot negate ${a}`, path)
      case 'not':
        return ['any', 'null', 'boolean'].includes(a) ? 'boolean' : this.fail('TYPE', `'not' needs a boolean, got ${a}`, path)
      default:
        return this.fail('SYNTAX', 'Unknown unary operator', path)
    }
  }

  private binary(node: RawNode, path: string): string {
    const op = node.op
    const a = this.type(asNode(node.a), `${path}.a`)
    const b = this.type(asNode(node.b), `${path}.b`)
    switch (op) {
      case 'and':
      case 'or':
        for (const t of [a, b]) {
          if (!loose(t) && t !== 'boolean') this.fail('TYPE', `'${op}' needs boolean operands, got ${t}`, path)
        }
        return 'boolean'
      case '&':
      case '=':
      case '!=':
        return op === '&' ? 'text' : 'boolean'
      case '<':
      case '<=':
      case '>':
      case '>=':
        for (const t of [a, b]) {
          if (!loose(t) && !ORDERABLE.includes(t)) this.fail('TYPE', `${t} values cannot be ordered`, path)
        }
        if (!loose(a) && !loose(b) && a !== b) this.fail('TYPE', `Cannot compare ${a} with ${b}`, path)
        return 'boolean'
      case '+':
      case '-':
      case '*':
      case '/':
      case '%':
        return this.arithmetic(op, a, b, path)
      default:
        return this.fail('SYNTAX', 'Unknown binary operator', path)
    }
  }

  private arithmetic(op: string, a: string, b: string, path: string): string {
    if (loose(a) || loose(b)) {
      const candidates = new Set<string>()
      for (const [pair, ops] of Object.entries(ARITHMETIC)) {
        const [x, y] = pair.split('|')
        if (has(ops, op) && (loose(a) || a === x) && (loose(b) || b === y)) candidates.add(ops[op]!)
      }
      if (candidates.size === 0) this.fail('TYPE', `Operator '${op}' does not apply to ${a} and ${b}`, path)
      return candidates.size === 1 ? [...candidates][0]! : 'any'
    }
    const ops = ARITHMETIC[`${a}|${b}`]
    return ops !== undefined && has(ops, op) ? ops[op]! : this.fail('TYPE', `Operator '${op}' does not apply to ${a} and ${b}`, path)
  }

  private call(node: RawNode, path: string): string {
    const fn = typeof node.fn === 'string' ? node.fn : String(node.fn ?? '')
    const args = members(node.args ?? []).map(asNode)
    if (!exists(fn)) this.fail('UNKNOWN_FUNCTION', `Unknown function '${fn}'`, path)
    const [min, max] = arity(fn)!
    const n = args.length
    if (n < min || (max !== null && n > max)) {
      this.fail('ARITY', `'${fn}' takes ${max === min ? min : max === null ? `at least ${min}` : `${min}–${max}`} arguments`, path)
    }
    if (requiresReference(fn) && args[0]?.k !== 'ref') this.fail('TYPE', `The first argument of '${fn}' must be a field reference`, `${path}.args.0`)
    if (isRowForm(fn, n)) return this.rowForm(fn, args, path)

    const types: string[] = []
    args.forEach((arg, i) => {
      types[i] = this.type(arg, `${path}.args.${i}`)
      this.expectArg(fn, i, n, types[i]!, `${path}.args.${i}`)
    })
    return this.resultType(fn, types, path)
  }

  private rowForm(fn: string, args: RawNode[], path: string): string {
    if (args[0]!.k !== 'ref') this.fail('TYPE', `The first argument of row-form '${fn}' must reference a repeater or to-many relation`, `${path}.args.0`)
    if (this.rowScope !== null) this.fail('TYPE', 'Row-form aggregates cannot be nested', path)
    const rows = this.type(args[0]!, `${path}.args.0`)
    this.expectArg(fn, 0, args.length, rows, `${path}.args.0`)
    this.rowScope = stringsOf(args[0]!.path ?? [])
    let exprType: string
    try {
      exprType = this.type(args[1]!, `${path}.args.1`)
    } finally {
      this.rowScope = null
    }
    this.expectArg(fn, 1, args.length, exprType, `${path}.args.1`)
    if (fn === 'join') {
      const sep = this.type(args[2]!, `${path}.args.2`)
      this.expectArg(fn, 2, 3, sep, `${path}.args.2`)
      return 'text'
    }
    if (fn === 'count') return 'number'
    if (fn === 'sum' || fn === 'avg') return this.numericAggregate(exprType, path)
    return exprType
  }

  private expectArg(fn: string, index: number, count: number, type: string, path: string): void {
    if (loose(type)) return
    const allowed = paramTypes(fn, index, count)
    if (allowed.includes('any') || allowed.includes(type)) return
    if (isList(type) && allowed.includes('list')) return
    this.fail('TYPE', `Argument ${index + 1} of '${fn}' must be ${allowed.join(' or ')}, got ${type}`, path)
  }

  private resultType(fn: string, types: string[], path: string): string {
    const result = returns(fn)
    if (result.startsWith('arg:')) return types[Number(result.slice(4))] ?? 'any'
    switch (result) {
      case 'branches':
        return this.branches(fn, types, path)
      case 'minmax':
        return types.length === 1 && isList(types[0]!) ? itemType(types[0]!) : this.common(types, path, `Arguments of '${fn}' must share one type`)
      case 'aggregate':
        return this.listAggregate(fn, types[0]!, path)
      default:
        return result
    }
  }

  private branches(fn: string, types: string[], path: string): string {
    let results: string[] = []
    if (fn === 'if') results = [types[1]!, types[2]!]
    else if (fn === 'coalesce') results = types
    else if (fn === 'switch') results = types.filter((_, i) => i > 0 && (i % 2 === 0 || i === types.length - 1))
    return this.common(results, path, `Results of '${fn}' must share one type`)
  }

  private listAggregate(fn: string, list: string, path: string): string {
    const item = isList(list) ? itemType(list) : 'any'
    return fn === 'sum' || fn === 'avg' ? this.numericAggregate(item, path) : item
  }

  private numericAggregate(item: string, path: string): string {
    if (loose(item)) return 'any'
    return item === 'number' || item === 'duration' ? item : this.fail('TYPE', `Cannot sum ${item} values`, path)
  }

  private common(types: string[], path: string, message: string): string {
    let result: string | null = null
    let sawAny = false
    for (const t of types) {
      if (t === 'null') continue
      if (t === 'any') {
        sawAny = true
        continue
      }
      if (result !== null && result !== t) {
        if (isList(result) && isList(t)) {
          result = TypeChecker.assignable(t, result) ? result : TypeChecker.assignable(result, t) ? t : this.fail('TYPE', message, path)
          continue
        }
        this.fail('TYPE', message, path)
      }
      result = t
    }
    return sawAny ? 'any' : (result ?? 'null')
  }

  private fail(code: StaticErrorCode, message: string, path: string): never {
    throw new StaticError(code, message, null, path.replace(/^\.+/, ''))
  }
}

/** Type-checks an AST; returns its static type or throws StaticError. */
export function check(ast: RawNode, resolver: ReferenceResolver | null = null, expected: string | null = null): string {
  return TypeChecker.check(ast, resolver, expected)
}
