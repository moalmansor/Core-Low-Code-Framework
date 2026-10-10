import { describe, expect, it } from 'vitest'
import { LABEL_HEIGHT, separateLabels } from './edgeLabels'

describe('transition labels on the canvas', () => {
  it('moves a label down until it no longer covers another', () => {
    const boxes = new Map([
      ['a', { x: 100, y: 50, w: 120 }],
      ['b', { x: 110, y: 52, w: 120 }],
      ['c', { x: 105, y: 49, w: 100 }],
      ['far', { x: 400, y: 50, w: 100 }],
    ])
    const dy = separateLabels(boxes)
    expect(dy.get('c')).toBe(0)
    expect(dy.get('far')).toBe(0)
    const ys = ['a', 'b', 'c'].map((id) => boxes.get(id)!.y + dy.get(id)!).sort((p, q) => p - q)
    expect(ys[1]! - ys[0]!).toBeGreaterThanOrEqual(LABEL_HEIGHT)
    expect(ys[2]! - ys[1]!).toBeGreaterThanOrEqual(LABEL_HEIGHT)
  })
})
