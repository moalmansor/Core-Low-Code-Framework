import { describe, expect, it } from 'vitest'
import { checkWorkflow, layoutStatuses, newStatus, newTransition, suggestKey } from './workflowGraph'

const s = (uuid: string, initial = false, final = false) => ({ ...newStatus(uuid, uuid, { en: uuid }, 1, { x: 0, y: 0 }), initial, final })

describe('workflow designer helpers', () => {
  it('suggests unique keys that start with a letter', () => {
    expect(suggestKey('Under Review', [])).toBe('under_review')
    expect(suggestKey('Under Review', ['under_review'])).toBe('under_review_2')
    expect(suggestKey('مراجعة', [])).toBe('s')
    expect(suggestKey('2nd check', [], 't')).toBe('t_2nd_check')
  })

  it('reports the same problems as the server', () => {
    const doc = { statuses: [s('a', true), s('b', true, true), s('c')], transitions: [newTransition('t1', 'back', { en: 'x' }, 'b', 'a', 0)], sla: [] }
    const { problems, warnings } = checkWorkflow(doc)
    expect(problems.map((p) => p.code).sort()).toEqual(['final_outgoing', 'initial_count'])
    expect(warnings.map((w) => w.message)).toEqual(['c'])
    expect(checkWorkflow({ statuses: [], transitions: [], sla: [] }).problems).toEqual([])
  })

  it('lays statuses out by distance from the initial one', () => {
    const doc = { statuses: [s('a', true), s('b'), s('c'), s('d')], transitions: [newTransition('1', 'x', {}, 'a', 'b', 0), newTransition('2', 'y', {}, 'a', 'c', 1)], sla: [] }
    const pos = layoutStatuses(doc)
    expect(pos.get('a')).toEqual({ x: 0, y: 0 })
    expect(pos.get('b')!.x).toBe(240)
    expect(pos.get('c')).toEqual({ x: 240, y: 120 })
    expect(pos.get('d')!.x).toBe(480)
  })
})
