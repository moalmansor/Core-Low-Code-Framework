import { describe, expect, it } from 'vitest'
import { History } from './history'

describe('undo/redo history', () => {
  it('undoes and redoes recorded states in order', () => {
    const h = new History('a')
    expect(h.record('b')).toBe(true)
    expect(h.record('c')).toBe(true)
    expect(h.undo()).toBe('b')
    expect(h.undo()).toBe('a')
    expect(h.undo()).toBeNull()
    expect(h.redo()).toBe('b')
    expect(h.redo()).toBe('c')
    expect(h.redo()).toBeNull()
    expect(h.current).toBe('c')
  })

  it('ignores a state equal to the current one', () => {
    const h = new History('a')
    expect(h.record('a')).toBe(false)
    expect(h.canUndo).toBe(false)
  })

  it('drops the redo branch when a new state is recorded after an undo', () => {
    const h = new History('a')
    h.record('b')
    h.record('c')
    h.undo()
    h.record('d')
    expect(h.canRedo).toBe(false)
    expect(h.undo()).toBe('b')
    expect(h.undo()).toBe('a')
  })

  it('keeps at most the configured number of undo steps', () => {
    const h = new History('0', 3)
    for (let i = 1; i <= 5; i++) h.record(String(i))
    expect(h.depth).toEqual({ undo: 3, redo: 0 })
    expect([h.undo(), h.undo(), h.undo(), h.undo()]).toEqual(['4', '3', '2', null])
  })

  it('starts over on reset', () => {
    const h = new History('a')
    h.record('b')
    h.reset('z')
    expect(h.current).toBe('z')
    expect(h.canUndo).toBe(false)
    expect(h.canRedo).toBe(false)
  })
})
