import { describe, expect, it } from 'vitest'
import { activeConditions, columnValue, decodeConditions, encodeConditions, shownColumns, type ActiveView, type ViewColumn } from './viewState'

const col = (key: string, extra: Partial<ViewColumn> = {}): ViewColumn => ({
  key,
  path: key.split('.'),
  label: key,
  width: null,
  pinned: 'none',
  visible: true,
  sortable: true,
  format: null,
  aggregate: 'none',
  linked: key.includes('.') || key.startsWith('@'),
  ...extra,
})

describe('view state', () => {
  it('keeps only active conditions in the URL and reads them back', () => {
    const all = { a: { op: 'contains', value: 'x' }, b: { op: 'in', values: [] }, c: { op: 'between', from: null, to: '2026-01-01' }, d: { op: 'is_empty' } }
    expect(Object.keys(activeConditions(all))).toEqual(['a', 'c', 'd'])
    expect(decodeConditions(encodeConditions(all))).toEqual(activeConditions(all))
    expect(encodeConditions({ b: { op: 'in', values: [] } })).toBeUndefined()
  })

  it('drops malformed conditions', () => {
    expect(decodeConditions('not json')).toEqual({})
    expect(decodeConditions('[1]')).toEqual({})
    expect(decodeConditions('{"a":{"x":1},"b":{"op":"equals","value":"1"}}')).toEqual({ b: { op: 'equals', value: '1' } })
  })

  it('reads linked columns from linked values and own fields from values', () => {
    const row = { values: { amount: 5 }, linked: { 'customer.city': 'Riyadh', '@status': 'Open' } }
    expect(columnValue(row, col('amount'))).toBe(5)
    expect(columnValue(row, col('customer.city'))).toBe('Riyadh')
    expect(columnValue(row, col('@status'))).toBe('Open')
    expect(columnValue({ values: {} }, col('customer.city'))).toBeNull()
  })

  it('orders pinned columns and honours the column chooser', () => {
    const view = { column_chooser: true, columns: [col('a'), col('b', { pinned: 'end' }), col('c', { pinned: 'start' }), col('d', { visible: false })] } as ActiveView
    expect(shownColumns(view, null).map((c) => c.key)).toEqual(['c', 'a', 'b'])
    expect(shownColumns(view, ['a', 'd']).map((c) => c.key)).toEqual(['a', 'd'])
    expect(shownColumns({ ...view, column_chooser: false }, ['a']).map((c) => c.key)).toEqual(['c', 'a', 'b'])
  })
})
