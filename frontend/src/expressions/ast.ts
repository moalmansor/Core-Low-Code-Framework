/**
 * The JSON AST of expression-language.md §6 and structural helpers over it.
 * Twin of backend/app/Expressions/Evaluation/Ast.php.
 */

export type Scope = 'record' | 'old' | 'user' | 'context' | 'row' | 'parent'
export type BinaryOp = '+' | '-' | '*' | '/' | '%' | '&' | '=' | '!=' | '<' | '<=' | '>' | '>=' | 'and' | 'or'

export type LiteralNode = { k: 'lit'; t: 'null' } | { k: 'lit'; t: 'boolean'; v: boolean } | { k: 'lit'; t: 'number' | 'text' | 'date' | 'datetime' | 'time'; v: string }
export type ListNode = { k: 'list'; items: AstNode[] }
export type RefNode = { k: 'ref'; scope: Scope; path: string[]; ids?: string[] }
export type UnaryNode = { k: 'un'; op: 'neg' | 'not'; a: AstNode }
export type BinaryNode = { k: 'bin'; op: BinaryOp; a: AstNode; b: AstNode }
export type CallNode = { k: 'call'; fn: string; args: AstNode[] }

/** A well-formed AST node as the parser produces it. */
export type AstNode = LiteralNode | ListNode | RefNode | UnaryNode | BinaryNode | CallNode

/**
 * Any JSON object offered as a node. Stored or client-supplied ASTs are read
 * defensively through this shape, the way the PHP runtime reads arrays.
 */
export type RawNode = { readonly [key: string]: unknown }

export function isObject(value: unknown): value is RawNode {
  return typeof value === 'object' && value !== null
}

/** The value as a node; anything that is not an object reads as an empty node. */
export function asNode(value: unknown): RawNode {
  return isObject(value) ? value : {}
}

/** The members of a JSON array or object (PHP's foreach over a decoded array). */
export function members(value: unknown): unknown[] {
  if (Array.isArray(value)) return value
  return isObject(value) ? Object.values(value) : []
}

export function children(node: RawNode): RawNode[] {
  let candidates: unknown
  switch (node.k) {
    case 'list':
      candidates = node.items ?? []
      break
    case 'call':
      candidates = node.args ?? []
      break
    case 'un':
      candidates = [node.a ?? null]
      break
    case 'bin':
      candidates = [node.a ?? null, node.b ?? null]
      break
    default:
      candidates = []
  }
  return (isObject(candidates) ? members(candidates) : []).filter(isObject)
}

/** Depth (nodes on the longest root-to-leaf path) and total node count. */
export function measure(node: RawNode): [number, number] {
  let depth = 0
  let nodes = 1
  for (const child of children(node)) {
    const [d, n] = measure(child)
    depth = Math.max(depth, d)
    nodes += n
  }
  return [depth + 1, nodes]
}

/** Keys sorted recursively (ASTs are compared and stored in this form). */
export function sortKeys(value: unknown): unknown {
  if (Array.isArray(value)) return value.map(sortKeys)
  if (!isObject(value)) return value
  const out: Record<string, unknown> = {}
  for (const key of Object.keys(value).sort()) out[key] = sortKeys(value[key])
  return out
}

/**
 * Canonical JSON (keys sorted recursively, slashes and Unicode unescaped,
 * U+2028/U+2029 escaped as PHP's json_encode does), used for parser parity
 * and for storage.
 */
export function canonicalJson(ast: unknown): string {
  return JSON.stringify(sortKeys(ast)).replace(/[\u2028\u2029]/g, (c) => (c === '\u2028' ? '\\u2028' : '\\u2029'))
}

/** Every reference node with its node path, in evaluation order. */
export function references(node: RawNode, path = ''): { scope: string; path: string[]; node: string }[] {
  let refs: { scope: string; path: string[]; node: string }[] = []
  if (node.k === 'ref') {
    refs.push({ scope: typeof node.scope === 'string' ? node.scope : 'record', path: members(node.path ?? []).map(String), node: path.replace(/^\.+/, '') })
  }
  const key = node.k === 'list' ? 'items' : node.k === 'call' ? 'args' : null
  if (key !== null) {
    members(node[key] ?? []).forEach((child, i) => {
      refs = [...refs, ...references(asNode(child), `${path}.${key}.${i}`)]
    })
  } else if (node.k === 'un' || node.k === 'bin') {
    for (const side of ['a', 'b']) {
      if (isObject(node[side])) refs = [...refs, ...references(node[side], `${path}.${side}`)]
    }
  }
  return refs
}
