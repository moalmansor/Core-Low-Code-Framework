import { readFileSync } from 'node:fs'
import { join } from 'node:path'
import { describe, expect, it } from 'vitest'
import { canonicalJson, parse, type AstNode } from '@/expressions'
import { signature, ungrouped } from './functions'
import { printExpression } from './print'
import { astToRule, emptyGroup, ruleToAst, type RuleGroup, type RuleLeaf } from './ruleModel'

const field = (key: string) => ({ kind: 'ref' as const, scope: 'record' as const, path: [key] })
const text = (v: string) => ({ kind: 'lit' as const, t: 'text' as const, v })
const num = (v: string) => ({ kind: 'lit' as const, t: 'number' as const, v })
const rule = (operator: RuleLeaf['operator'], left: RuleLeaf['left'], right: RuleLeaf['right'] = []): RuleLeaf => ({ id: 'x', kind: 'rule', operator, left, right })

/** The model without the generated ids, for comparison. */
function shape(node: unknown): unknown {
  if (Array.isArray(node)) return node.map(shape)
  if (node && typeof node === 'object') {
    const out: Record<string, unknown> = {}
    for (const [k, v] of Object.entries(node)) if (k !== 'id') out[k] = shape(v)
    return out
  }
  return node
}

describe('condition builder ⇄ AST', () => {
  it('serializes the builder to the same AST the parser produces for the equivalent text', () => {
    const root: RuleGroup = {
      id: 'r',
      kind: 'group',
      op: 'and',
      negate: false,
      children: [
        rule('gt', field('amount'), [num('1000')]),
        rule('eq', { kind: 'ref', scope: 'user', path: ['department'] }, [text('FIN')]),
        { id: 'g', kind: 'group', op: 'or', negate: false, children: [rule('is_empty', field('note')), rule('in_list', field('status'), [text('a'), text('b')])] },
      ],
    }
    const expected = parse('amount > 1000 and @user.department = "FIN" and (is_empty(note) or in(status, ["a", "b"]))')
    expect(canonicalJson(ruleToAst(root))).toBe(canonicalJson(expected))
  })

  it('round-trips every operator through the AST', () => {
    const leaves: RuleLeaf[] = [
      rule('eq', field('a'), [text('x')]),
      rule('neq', field('a'), [field('b')]),
      rule('gte', field('n'), [num('-2.5')]),
      rule('lt', field('d'), [{ kind: 'today', offset: 7 }]),
      rule('lte', field('d'), [{ kind: 'today', offset: -3 }]),
      rule('between', field('n'), [num('1'), num('10')]),
      rule('contains', field('a'), [text('x')]),
      rule('not_contains', field('a'), [text('x')]),
      rule('starts_with', field('a'), [text('x')]),
      rule('ends_with', field('a'), [text('x')]),
      rule('is_empty', field('a')),
      rule('is_not_empty', field('a')),
      rule('in_list', field('a'), [text('x'), text('y')]),
      rule('not_in_list', field('a'), [text('x')]),
      rule('matches', field('a'), [text('^[0-9]{10}$')]),
      rule('changed', field('a')),
      rule('changed_from_to', field('status'), [text('draft'), text('sent')]),
      rule('has_role', null, [text('manager')]),
      rule('in_department', null, [text('FIN'), { kind: 'lit', t: 'boolean', v: true }]),
      rule('gt', { kind: 'ref', scope: 'context', path: ['mode'] }, [{ kind: 'now' }]),
    ]
    for (const leaf of leaves) {
      const root: RuleGroup = { id: 'r', kind: 'group', op: 'and', negate: false, children: [leaf] }
      const ast = ruleToAst(root)
      const back = astToRule(ast)
      expect(shape(back), leaf.operator).toEqual(shape(root))
      expect(canonicalJson(ruleToAst(back))).toBe(canonicalJson(ast))
    }
  })

  it('round-trips nested and negated groups', () => {
    const root: RuleGroup = {
      id: 'r',
      kind: 'group',
      op: 'or',
      negate: true,
      children: [
        { id: 'a', kind: 'group', op: 'and', negate: false, children: [rule('eq', field('a'), [text('1')]), rule('eq', field('b'), [text('2')]), rule('eq', field('c'), [text('3')])] },
        rule('is_not_empty', field('d')),
        { id: 'b', kind: 'group', op: 'and', negate: true, children: [rule('eq', field('e'), [num('5')])] },
      ],
    }
    const ast = ruleToAst(root)
    expect(shape(astToRule(ast))).toEqual(shape(root))
    expect(canonicalJson(ast)).toBe(canonicalJson(parse('not ((a = "1" and b = "2" and c = "3") or not is_empty(d) or not (e = 5))')))
  })

  it('keeps unknown shapes as custom expressions without changing the AST', () => {
    const ast = parse('round(amount * 0.15, 2) > sum(items, qty * price) or if(a, b, c)')
    const model = astToRule(ast)
    expect(canonicalJson(ruleToAst(model))).toBe(canonicalJson(ast))
    expect(model.op).toBe('or')
    expect((model.children[0] as RuleLeaf).operator).toBe('gt')
    expect((model.children[1] as RuleLeaf).operator).toBe('expr')
  })

  it('reads `true` as an empty group and writes an empty group as `true`', () => {
    expect(shape(astToRule({ k: 'lit', t: 'boolean', v: true }))).toEqual(shape(emptyGroup()))
    expect(ruleToAst(emptyGroup())).toEqual({ k: 'lit', t: 'boolean', v: true })
  })

  it('canonicalizes number literals and normalizes text', () => {
    const ast = ruleToAst({ id: 'r', kind: 'group', op: 'and', negate: false, children: [rule('eq', field('n'), [num('01.500')]), rule('eq', field('t'), [text('é')])] }) as AstNode & {
      a: { b: { v: string } }
      b: { b: { v: string } }
    }
    expect(ast.a.b.v).toBe('1.5')
    expect(ast.b.b.v).toBe('é')
  })
})

describe('printing ASTs as text', () => {
  const corpus = JSON.parse(readFileSync(join(__dirname, '../../../../docs/conformance/expression-corpus.json'), 'utf8')) as { cases: { id: string; expr?: string }[] }

  it('prints every parseable corpus expression so that it parses back to the same AST', () => {
    let checked = 0
    for (const c of corpus.cases) {
      if (!c.expr) continue
      let ast: AstNode
      try {
        ast = parse(c.expr)
      } catch {
        continue
      }
      const printed = printExpression(ast)
      expect(canonicalJson(parse(printed)), `${c.id}: ${printed}`).toBe(canonicalJson(ast))
      checked++
    }
    expect(checked).toBeGreaterThan(100)
  })

  it('adds only the parentheses the grammar needs', () => {
    expect(printExpression(parse('(a + b) * c - (d - e)'))).toBe('(a + b) * c - (d - e)')
    expect(printExpression(parse('a - b - c'))).toBe('a - b - c')
    expect(printExpression(parse('not (a = 1) and (b or c)'))).toBe('not a = 1 and (b or c)')
    expect(printExpression(parse('(a = b) = c'))).toBe('(a = b) = c')
    expect(printExpression(parse('@user.name & "\\n" & d"2026-01-31"'))).toBe('@user.name & "\\n" & d"2026-01-31"')
  })
})

describe('function list', () => {
  it('lists every registered function with a signature', () => {
    expect(ungrouped()).toEqual([])
    expect(signature('round')).toBe('round(number, number?) → number')
  })
})
