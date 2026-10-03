import { describe, expect, it } from 'vitest'
import { dateParts, nextValue, periodKey, previewNext, renderPattern, validPattern, type SequenceShape } from './numberPattern'

const base: SequenceShape = { pattern: '{prefix}-{yyyy}-{seq}', prefix: 'INV', padding: 5, step: 1, reset_period: 'yearly', calendar: 'gregorian', period_key: '', current_value: 0 }

describe('number pattern preview', () => {
  it('validates patterns like the server', () => {
    expect(validPattern('{prefix}-{yyyy}{MM}-{seq:4}')).toBe(true)
    expect(validPattern('{yy}/{seq}')).toBe(true)
    expect(validPattern('{prefix}-{yyyy}')).toBe(false)
    expect(validPattern('{seq}-{unknown}')).toBe(false)
    expect(validPattern('{seq}}')).toBe(false)
    expect(validPattern('{seq:123}')).toBe(false)
  })

  it('renders every token', () => {
    const parts = dateParts('gregorian', 2026, 3, 7)
    expect(parts).toEqual({ yyyy: '2026', yy: '26', MM: '03', dd: '07' })
    expect(renderPattern('{prefix}{yy}{MM}{dd}-{seq}-{seq:2}-{yyyy}', 'PO', 4, parts, 12)).toBe('PO260307-0012-12-2026')
    expect(renderPattern('{seq:2}', '', 4, parts, 12345)).toBe('12345')
  })

  it('uses Umm al-Qura date parts for the Hijri calendar', () => {
    // 2024-03-11 is 1 Ramadan 1445.
    expect(dateParts('hijri', 2024, 3, 11)).toEqual({ yyyy: '1445', yy: '45', MM: '09', dd: '01' })
  })

  it('restarts the counter when the period changes', () => {
    const parts = dateParts('gregorian', 2026, 3, 7)
    expect(periodKey('never', parts)).toBe('all')
    expect(periodKey('daily', parts)).toBe('20260307')
    expect(periodKey('monthly', parts)).toBe('202603')
    expect(nextValue({ ...base, period_key: '2026', current_value: 41 }, parts)).toBe(42)
    expect(nextValue({ ...base, period_key: '2025', current_value: 41, step: 5 }, parts)).toBe(5)
  })

  it('previews the next number for a date', () => {
    expect(previewNext({ ...base, period_key: '2026', current_value: 9 }, new Date(2026, 0, 15))).toBe('INV-2026-00010')
    expect(previewNext(base, new Date(2026, 0, 15))).toBe('INV-2026-00001')
  })
})
