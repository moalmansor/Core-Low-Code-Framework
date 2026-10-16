import { describe, expect, it } from 'vitest'
import { joinList } from './lists'

describe('lists in the user language', () => {
  it('joins with the language own separator', () => {
    expect(joinList(['PDF', 'Word'], 'en')).toBe('PDF, Word')
    expect(joinList(['أ', 'ب', 'ج'], 'ar')).toBe('أ، وب، وج')
    expect(joinList(['a', '', 'b'], 'en')).toBe('a, b')
  })
})
