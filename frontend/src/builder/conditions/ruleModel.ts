import { Decimal, type AstNode, type RawNode, type Scope } from '@/expressions'
import { newUuid } from '../uuid'

/**
 * The visual rule builder's model (specification §4.7, architecture §14.3):
 * nested AND/OR groups whose leaves are `operand · operator · operand`. It
 * serializes directly to expression ASTs — no text parsing is involved — and
 * any AST can be read back: shapes the builder does not know become a
 * "custom expression" row that keeps the AST unchanged.
 */

export type Operand =
  | { kind: 'ref'; scope: Scope; path: string[]; ids?: string[] }
  | { kind: 'lit'; t: 'text' | 'number' | 'boolean' | 'date' | 'datetime' | 'time' | 'null'; v: string | boolean | null }
  | { kind: 'today'; offset: number }
  | { kind: 'now' }
  | { kind: 'expr'; ast: AstNode }

export const OPERATORS = [
  'eq',
  'neq',
  'gt',
  'gte',
  'lt',
  'lte',
  'between',
  'contains',
  'not_contains',
  'starts_with',
  'ends_with',
  'is_empty',
  'is_not_empty',
  'in_list',
  'not_in_list',
  'matches',
  'changed',
  'changed_from_to',
  'has_role',
  'in_department',
  'expr',
] as const
export type Operator = (typeof OPERATORS)[number]

/** How many right-hand operands each operator takes (`list` = any number). */
export const OPERATOR_ARITY: Record<Operator, number | 'list'> = {
  eq: 1,
  neq: 1,
  gt: 1,
  gte: 1,
  lt: 1,
  lte: 1,
  between: 2,
  contains: 1,
  not_contains: 1,
  starts_with: 1,
  ends_with: 1,
  is_empty: 0,
  is_not_empty: 0,
  in_list: 'list',
  not_in_list: 'list',
  matches: 1,
  changed: 0,
  changed_from_to: 2,
  has_role: 1,
  in_department: 2,
  expr: 0,
}

/** Operators that do not use a left operand. */
export const SUBJECTLESS: Operator[] = ['has_role', 'in_department', 'expr']

export interface RuleLeaf {
  id: string
  kind: 'rule'
  operator: Operator
  left: Operand | null
  right: Operand[]
  /** For `expr` rows: the AST as written in the formula editor. */
  ast?: AstNode
}

export interface RuleGroup {
  id: string
  kind: 'group'
  op: 'and' | 'or'
  negate: boolean
  children: RuleNode[]
}

export type RuleNode = RuleLeaf | RuleGroup

const COMPARISON: Record<string, Operator> = { '=': 'eq', '!=': 'neq', '>': 'gt', '>=': 'gte', '<': 'lt', '<=': 'lte' }
const COMPARISON_OP: Partial<Record<Operator, string>> = { eq: '=', neq: '!=', gt: '>', gte: '>=', lt: '<', lte: '<=' }
const TEXT_CALLS: Partial<Record<string, Operator>> = { contains: 'contains', starts_with: 'starts_with', ends_with: 'ends_with', matches: 'matches' }

export function emptyGroup(op: 'and' | 'or' = 'and'): RuleGroup {
  return { id: newUuid(), kind: 'group', op, negate: false, children: [] }
}

export function newRule(left: Operand | null = null): RuleLeaf {
  return { id: newUuid(), kind: 'rule', operator: 'eq', left, right: [{ kind: 'lit', t: 'text', v: '' }] }
}

// ---------------------------------------------------------------- to AST

const TRUE: AstNode = { k: 'lit', t: 'boolean', v: true }

export function operandToAst(o: Operand): AstNode {
  switch (o.kind) {
    case 'ref':
      return o.ids && o.ids.length === o.path.length ? { k: 'ref', scope: o.scope, path: [...o.path], ids: [...o.ids] } : { k: 'ref', scope: o.scope, path: [...o.path] }
    case 'lit':
      if (o.t === 'null') return { k: 'lit', t: 'null' }
      if (o.t === 'boolean') return { k: 'lit', t: 'boolean', v: o.v === true }
      if (o.t === 'number') return { k: 'lit', t: 'number', v: canonicalNumber(String(o.v ?? '0')) }
      if (o.t === 'text') return { k: 'lit', t: 'text', v: String(o.v ?? '').normalize('NFC') }
      return { k: 'lit', t: o.t, v: String(o.v ?? '') }
    case 'today': {
      const today: AstNode = { k: 'call', fn: 'today', args: [] }
      if (o.offset === 0) return today
      return { k: 'bin', op: o.offset > 0 ? '+' : '-', a: today, b: { k: 'lit', t: 'number', v: String(Math.abs(o.offset)) } }
    }
    case 'now':
      return { k: 'call', fn: 'now', args: [] }
    case 'expr':
      return o.ast
  }
}

/** The canonical decimal text of a number literal (no trailing zeros, no `-0`). */
export function canonicalNumber(text: string): string {
  const d = Decimal.parse(text.trim())
  return d === null ? '0' : d.toString()
}

function leafToAst(leaf: RuleLeaf): AstNode {
  const left = leaf.left ? operandToAst(leaf.left) : { k: 'lit' as const, t: 'null' as const }
  const r = leaf.right.map(operandToAst)
  const arg = (i: number): AstNode => r[i] ?? { k: 'lit', t: 'null' }
  const not = (a: AstNode): AstNode => ({ k: 'un', op: 'not', a })
  const call = (fn: string, args: AstNode[]): AstNode => ({ k: 'call', fn, args })
  const cmp = COMPARISON_OP[leaf.operator]
  if (cmp) return { k: 'bin', op: cmp as '=', a: left, b: arg(0) }
  switch (leaf.operator) {
    case 'between':
      return { k: 'bin', op: 'and', a: { k: 'bin', op: '>=', a: left, b: arg(0) }, b: { k: 'bin', op: '<=', a: left, b: arg(1) } }
    case 'contains':
    case 'starts_with':
    case 'ends_with':
    case 'matches':
      return call(leaf.operator, [left, arg(0)])
    case 'not_contains':
      return not(call('contains', [left, arg(0)]))
    case 'is_empty':
      return call('is_empty', [left])
    case 'is_not_empty':
      return not(call('is_empty', [left]))
    case 'in_list':
      return call('in', [left, { k: 'list', items: r }])
    case 'not_in_list':
      return not(call('in', [left, { k: 'list', items: r }]))
    case 'changed':
      return call('changed', [left])
    case 'changed_from_to':
      return call('changed_from_to', [left, arg(0), arg(1)])
    case 'has_role':
      return call('has_role', [arg(0)])
    case 'in_department':
      return call('in_department', r.length > 1 ? [arg(0), arg(1)] : [arg(0)])
    default:
      return leaf.ast ?? TRUE
  }
}

/** Serializes a rule tree to an AST. An empty root group means "always" (`true`). */
export function ruleToAst(node: RuleNode): AstNode {
  if (node.kind === 'rule') return leafToAst(node)
  const parts = node.children.map(ruleToAst)
  let ast: AstNode
  if (parts.length === 0) ast = TRUE
  else ast = parts.slice(1).reduce<AstNode>((acc, p) => ({ k: 'bin', op: node.op, a: acc, b: p }), parts[0]!)
  return node.negate ? { k: 'un', op: 'not', a: ast } : ast
}

// ---------------------------------------------------------------- from AST

function isNode(v: unknown): v is RawNode {
  return typeof v === 'object' && v !== null && !Array.isArray(v)
}

function args(node: RawNode): RawNode[] {
  return Array.isArray(node.args) ? node.args.filter(isNode) : []
}

function same(a: unknown, b: unknown): boolean {
  return JSON.stringify(a) === JSON.stringify(b)
}

export function astToOperand(node: RawNode): Operand {
  if (node.k === 'ref' && Array.isArray(node.path)) {
    const ref: Operand = { kind: 'ref', scope: (node.scope as Scope) ?? 'record', path: node.path.map(String) }
    if (Array.isArray(node.ids)) ref.ids = node.ids.map(String)
    return ref
  }
  if (node.k === 'lit') {
    const t = String(node.t)
    if (t === 'null') return { kind: 'lit', t: 'null', v: null }
    if (t === 'boolean') return { kind: 'lit', t: 'boolean', v: node.v === true }
    if (['text', 'number', 'date', 'datetime', 'time'].includes(t)) return { kind: 'lit', t: t as 'text', v: String(node.v) }
  }
  if (node.k === 'call' && args(node).length === 0 && (node.fn === 'today' || node.fn === 'now')) return node.fn === 'today' ? { kind: 'today', offset: 0 } : { kind: 'now' }
  if (node.k === 'bin' && (node.op === '+' || node.op === '-') && isNode(node.a) && isNode(node.b)) {
    const a = node.a
    const b = node.b
    if (a.k === 'call' && a.fn === 'today' && args(a).length === 0 && b.k === 'lit' && b.t === 'number' && /^[1-9][0-9]{0,5}$/.test(String(b.v))) {
      const n = Number(b.v)
      return { kind: 'today', offset: node.op === '+' ? n : -n }
    }
  }
  return { kind: 'expr', ast: node as AstNode }
}

function exprLeaf(node: RawNode): RuleLeaf {
  return { id: newUuid(), kind: 'rule', operator: 'expr', left: null, right: [], ast: node as AstNode }
}

function leaf(operator: Operator, left: RawNode | null, right: RawNode[]): RuleLeaf {
  return { id: newUuid(), kind: 'rule', operator, left: left ? astToOperand(left) : null, right: right.map(astToOperand) }
}

function toLeaf(node: RawNode): RuleLeaf | null {
  if (node.k === 'bin' && isNode(node.a) && isNode(node.b)) {
    const op = COMPARISON[String(node.op)]
    if (op) return leaf(op, node.a, [node.b])
    if (node.op === 'and') {
      const a = node.a
      const b = node.b
      if (a.k === 'bin' && a.op === '>=' && b.k === 'bin' && b.op === '<=' && isNode(a.a) && isNode(a.b) && isNode(b.a) && isNode(b.b) && same(a.a, b.a)) {
        return leaf('between', a.a, [a.b, b.b])
      }
    }
    return null
  }
  if (node.k === 'call') {
    const a = args(node)
    const fn = String(node.fn)
    if (TEXT_CALLS[fn] && a.length === 2) return leaf(TEXT_CALLS[fn]!, a[0]!, [a[1]!])
    if (fn === 'is_empty' && a.length === 1) return leaf('is_empty', a[0]!, [])
    if (fn === 'in' && a.length === 2 && a[1]!.k === 'list') return leaf('in_list', a[0]!, (Array.isArray(a[1]!.items) ? a[1]!.items : []).filter(isNode))
    if (fn === 'changed' && a.length === 1 && a[0]!.k === 'ref') return leaf('changed', a[0]!, [])
    if (fn === 'changed_from_to' && a.length === 3 && a[0]!.k === 'ref') return leaf('changed_from_to', a[0]!, [a[1]!, a[2]!])
    if (fn === 'has_role' && a.length === 1) return leaf('has_role', null, [a[0]!])
    if (fn === 'in_department' && (a.length === 1 || a.length === 2)) return leaf('in_department', null, a)
    return null
  }
  if (node.k === 'un' && node.op === 'not' && isNode(node.a)) {
    const inner = toLeaf(node.a)
    if (inner?.operator === 'contains') return { ...inner, operator: 'not_contains' }
    if (inner?.operator === 'is_empty') return { ...inner, operator: 'is_not_empty' }
    if (inner?.operator === 'in_list') return { ...inner, operator: 'not_in_list' }
  }
  return null
}

function toNode(node: RawNode): RuleNode {
  if (node.k === 'bin' && (node.op === 'and' || node.op === 'or')) {
    const asLeaf = node.op === 'and' ? toLeaf(node) : null
    if (asLeaf) return asLeaf
    const op = node.op
    // Flatten the left spine of a chain of the same operator (the parser builds chains left-nested).
    const children: RawNode[] = []
    let current: RawNode = node
    while (current.k === 'bin' && current.op === op && isNode(current.a) && isNode(current.b) && !(op === 'and' && toLeaf(current))) {
      children.unshift(current.b)
      current = current.a
    }
    children.unshift(current)
    return { id: newUuid(), kind: 'group', op, negate: false, children: children.map(toNode) }
  }
  if (node.k === 'un' && node.op === 'not' && isNode(node.a)) {
    const asLeaf = toLeaf(node)
    if (asLeaf) return asLeaf
    const inner = toNode(node.a)
    if (inner.kind === 'group' && !inner.negate) return { ...inner, negate: true }
    return { id: newUuid(), kind: 'group', op: 'and', negate: true, children: [inner] }
  }
  return toLeaf(node) ?? exprLeaf(node)
}

/** Reads any condition AST into a root group. `true` (or nothing) is an empty group. */
export function astToRule(ast: RawNode | null | undefined): RuleGroup {
  if (!ast || (ast.k === 'lit' && ast.t === 'boolean' && ast.v === true)) return emptyGroup()
  const node = toNode(ast)
  if (node.kind === 'group') return node
  return { id: newUuid(), kind: 'group', op: 'and', negate: false, children: [node] }
}
