import type { RawNode } from '@/expressions'

/**
 * Prints an expression AST (expression-language.md §6) as text in the
 * grammar of §2, with the fewest parentheses that keep the same tree. Used to
 * show a stored AST in the formula editor; parsing the printed text yields
 * the same AST again.
 */

const PRECEDENCE: Record<string, number> = {
  or: 1,
  and: 2,
  '=': 4,
  '!=': 4,
  '<': 4,
  '<=': 4,
  '>': 4,
  '>=': 4,
  '&': 5,
  '+': 6,
  '-': 6,
  '*': 7,
  '/': 7,
  '%': 7,
}
const NOT = 3
const NEG = 8
const PRIMARY = 9

function quote(text: string): string {
  let out = '"'
  for (const ch of text) {
    const code = ch.codePointAt(0)!
    if (ch === '"') out += '\\"'
    else if (ch === '\\') out += '\\\\'
    else if (ch === '\n') out += '\\n'
    else if (ch === '\t') out += '\\t'
    else if (code < 0x20 || code === 0x7f) out += `\\u${code.toString(16).padStart(4, '0')}`
    else out += ch
  }
  return `${out}"`
}

function precedence(node: RawNode): number {
  if (node.k === 'bin') return PRECEDENCE[String(node.op)] ?? PRIMARY
  if (node.k === 'un') return node.op === 'not' ? NOT : NEG
  if (node.k === 'lit' && node.t === 'number' && String(node.v).startsWith('-')) return NEG
  return PRIMARY
}

function child(node: unknown): RawNode {
  return node && typeof node === 'object' ? (node as RawNode) : { k: 'lit', t: 'null' }
}

function wrap(node: RawNode, min: number): string {
  const text = printExpression(node)
  return precedence(node) < min ? `(${text})` : text
}

export function printExpression(node: RawNode): string {
  switch (node.k) {
    case 'lit':
      switch (node.t) {
        case 'null':
          return 'null'
        case 'boolean':
          return node.v === true ? 'true' : 'false'
        case 'number':
          return String(node.v)
        case 'text':
          return quote(String(node.v ?? ''))
        case 'date':
          return `d"${String(node.v)}"`
        case 'datetime':
          return `dt"${String(node.v)}"`
        case 'time':
          return `t"${String(node.v)}"`
        default:
          return 'null'
      }
    case 'list':
      return `[${(Array.isArray(node.items) ? node.items : []).map((i) => printExpression(child(i))).join(', ')}]`
    case 'ref': {
      const path = (Array.isArray(node.path) ? node.path : []).map(String).join('.')
      const scope = String(node.scope ?? 'record')
      return scope === 'record' ? path : `@${scope}.${path}`
    }
    case 'call':
      return `${String(node.fn)}(${(Array.isArray(node.args) ? node.args : []).map((a) => printExpression(child(a))).join(', ')})`
    case 'un':
      if (node.op === 'not') return `not ${wrap(child(node.a), NOT)}`
      return `-${wrap(child(node.a), NEG)}`
    case 'bin': {
      const op = String(node.op)
      const p = PRECEDENCE[op] ?? PRIMARY
      // Comparisons are non-associative; every other binary operator is left-associative.
      const left = wrap(child(node.a), p === 4 ? p + 1 : p)
      const right = wrap(child(node.b), p + 1)
      return `${left} ${op} ${right}`
    }
    default:
      return 'null'
  }
}
