import { afterEach, describe, expect, it, vi } from 'vitest'
import { scrollBehavior } from './motion'

describe('motion', () => {
  afterEach(() => vi.unstubAllGlobals())

  it('scrolls without animation when the person asks for reduced motion', () => {
    vi.stubGlobal('matchMedia', (q: string) => ({ matches: q === '(prefers-reduced-motion: reduce)' }))
    expect(scrollBehavior()).toBe('auto')
    vi.stubGlobal('matchMedia', () => ({ matches: false }))
    expect(scrollBehavior()).toBe('smooth')
  })
})
