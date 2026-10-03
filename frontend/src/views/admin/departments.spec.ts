import { describe, expect, it } from 'vitest'
import { departmentNodes } from './departments'

describe('departmentNodes', () => {
  it('maps the API tree to tree-select nodes keyed by UUID', () => {
    const leaf = { uuid: 'b', code: 'AP', name: 'Payables', names: {}, is_active: true, depth: 1, sort_order: 0, members_count: 0, manager: null, children: [] }
    const nodes = departmentNodes([{ ...leaf, uuid: 'a', code: 'FIN', name: 'Finance', depth: 0, children: [leaf] }])
    expect(nodes[0]!.key).toBe('a')
    expect(nodes[0]!.label).toBe('Finance (FIN)')
    expect(nodes[0]!.children[0]!.key).toBe('b')
  })
})
