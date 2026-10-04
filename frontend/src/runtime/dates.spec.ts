import { describe, expect, it } from 'vitest'
import { datetimeToInput, inputToDatetime, inputToMonth, inputToTime, inputToWeek, monthToInput, timeToInput, weekToInput } from './dates'
import { toE164, nationalPart } from './fields/countries'
import { applyMask } from './fields/mask'
import { normalizeNumber } from './fields/number'
import { formatDecimal, toArabicIndic } from './format'

describe('input ↔ API conversions', () => {
  it('local date-times map to UTC by the field timezone', () => {
    expect(inputToDatetime('2026-10-04T10:30', 'utc')).toBe('2026-10-04T10:30:00Z')
    expect(inputToDatetime('2026-10-04T10:30', 'fixed:Asia/Riyadh')).toBe('2026-10-04T07:30:00Z')
    expect(datetimeToInput('2026-10-04T07:30:00Z', 'fixed:Asia/Riyadh')).toBe('2026-10-04T10:30')
    expect(inputToDatetime('٢٠٢٦-١٠-٠٤T10:30', 'utc')).toBe('2026-10-04T10:30:00Z')
    expect(inputToDatetime('nonsense', 'utc')).toBeNull()
  })

  it('times, months and ISO weeks use the server shapes', () => {
    expect(inputToTime('09:05')).toBe('09:05:00')
    expect(timeToInput('09:05:00')).toBe('09:05')
    expect(inputToTime('25:00')).toBeNull()
    expect(inputToMonth('2026-02')).toBe('2026-02-01')
    expect(monthToInput('2026-02-01')).toBe('2026-02')
    expect(inputToWeek('2026-W01')).toBe('2025-12-29')
    expect(weekToInput('2025-12-29')).toBe('2026-W01')
    expect(weekToInput('2026-10-04')).toBe('2026-W40')
  })

  it('numbers stay exact decimal strings and accept Arabic-Indic digits', () => {
    expect(normalizeNumber('١٬٢٣٤٫٥', false)).toBe('1234.5')
    expect(normalizeNumber('12.', false)).toBe('12.')
    expect(normalizeNumber('12.', true)).toBe('12')
    expect(normalizeNumber('007', true)).toBe('7')
    expect(normalizeNumber('.5', true)).toBe('0.5')
    expect(normalizeNumber('1e5', false)).toBeNull()
    expect(formatDecimal('1234567.891', { decimals: 2, locale: 'en' })).toBe('1,234,567.89')
    expect(formatDecimal('9.995', { decimals: 2, locale: 'en' })).toBe('10.00')
    expect(formatDecimal('12345678901234567890.1234', { locale: 'en' })).toBe('12,345,678,901,234,567,890.1234')
    expect(toArabicIndic('2026')).toBe('٢٠٢٦')
  })

  it('masks and phone numbers', () => {
    expect(applyMask('999-999-9999', '5551234567')).toBe('555-123-4567')
    expect(applyMask('AA-9999', 'sa1234')).toBe('SA-1234')
    expect(toE164('SA', '0501234567')).toBe('+966501234567')
    expect(nationalPart('+966501234567', 'SA')).toBe('501234567')
  })
})
