import { describe, expect, it } from 'vitest'
import { entry, formatSpec, parseFormat, splitEntry, token, valueKind } from './formats'

describe('placeholder formats', () => {
  it('reads and writes the fixed list, refusing anything else', () => {
    expect(parseFormat('date:long|digits:arabic')).toEqual({ date: 'long', digits: 'arabic' })
    expect(parseFormat(' number:2 | currency:SAR ')).toEqual({ number: 2, currency: 'SAR' })
    expect(parseFormat('')).toEqual({})
    expect(parseFormat('money')).toBeNull()
    expect(parseFormat('number:9')).toBeNull()
    expect(parseFormat('currency:sar')).toBeNull()
    expect(formatSpec({ digits: 'western', number: 0, date: 'iso' })).toBe('date:iso|number:0|digits:western')
  })

  it('builds placeholders and field list entries', () => {
    expect(token('customer.name')).toBe('{{ customer.name }}')
    expect(token('total', { number: 2, digits: 'arabic' })).toBe('{{ total | number:2 | digits:arabic }}')
    expect(entry('due', { date: 'long' })).toBe('due|date:long')
    expect(entry('due', {})).toBe('due')
    expect(splitEntry('amount|currency:SAR')).toEqual({ path: 'amount', format: { currency: 'SAR' } })
    expect(splitEntry('amount')).toEqual({ path: 'amount', format: {} })
  })

  it('knows which values take which formats', () => {
    expect(valueKind('date')).toBe('date')
    expect(valueKind('datetime')).toBe('datetime')
    expect(['int', 'decimal', 'currency'].map(valueKind)).toEqual(['number', 'number', 'number'])
    expect(valueKind('string')).toBe('text')
  })
})
