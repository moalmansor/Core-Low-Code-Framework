import { describe, expect, it } from 'vitest'
import { GAP_X, GAP_Y, NODE_WIDTH, layoutErd, nodeHeight } from './erdLayout'

describe('ERD layout', () => {
  const nodes = [
    { id: 'lcf_orders', columns: 4 },
    { id: 'lcf_customers', columns: 2 },
    { id: 'lcf_order_lines', columns: 3 },
    { id: 'lcf_products', columns: 1 },
  ]
  const edges = [
    { from: 'lcf_orders', to: 'lcf_customers' },
    { from: 'lcf_order_lines', to: 'lcf_orders' },
    { from: 'lcf_order_lines', to: 'lcf_products' },
  ]

  it('places referenced tables in earlier columns', () => {
    const boxes = layoutErd(nodes, edges)
    expect(boxes.get('lcf_customers')!.rank).toBe(0)
    expect(boxes.get('lcf_products')!.rank).toBe(0)
    expect(boxes.get('lcf_orders')!.rank).toBe(1)
    expect(boxes.get('lcf_order_lines')!.rank).toBe(2)
    expect(boxes.get('lcf_order_lines')!.x).toBe(2 * (NODE_WIDTH + GAP_X))
  })

  it('stacks a column without overlap', () => {
    const boxes = layoutErd(nodes, edges)
    const a = boxes.get('lcf_customers')!
    const b = boxes.get('lcf_products')!
    expect(a.y).toBe(0)
    expect(b.y).toBe(nodeHeight(2) + GAP_Y)
  })

  it('mirrors columns for right-to-left reading', () => {
    const boxes = layoutErd(nodes, edges, 'rtl')
    expect(boxes.get('lcf_order_lines')!.x).toBe(-2 * (NODE_WIDTH + GAP_X))
    expect(boxes.get('lcf_customers')!.x).toBe(0)
  })

  it('survives cycles, self references and unknown tables', () => {
    const boxes = layoutErd(
      [
        { id: 'a', columns: 1 },
        { id: 'b', columns: 1 },
      ],
      [
        { from: 'a', to: 'b' },
        { from: 'b', to: 'a' },
        { from: 'a', to: 'a' },
        { from: 'a', to: 'missing' },
      ],
    )
    expect(boxes.size).toBe(2)
    expect(new Set([boxes.get('a')!.rank, boxes.get('b')!.rank])).toEqual(new Set([0, 1]))
  })

  it('is deterministic regardless of input order', () => {
    const one = layoutErd(nodes, edges)
    const two = layoutErd([...nodes].reverse(), [...edges].reverse())
    expect([...two.entries()].sort()).toEqual([...one.entries()].sort())
  })
})
