import { describe, expect, it } from 'vitest'
import { decodeQuery, DEFAULT_STATE, emptyRow, encodeQuery, isActive, operatorsFor, toApiFilter, type ListState } from './listState'

describe('records list state', () => {
  it('offers operators by filter kind', () => {
    expect(operatorsFor('text', false)).toEqual(['contains', 'starts_with', 'equals'])
    expect(operatorsFor('choice', true)).toEqual(['equals', 'in'])
    expect(operatorsFor('choice', false)).toEqual(['equals'])
    expect(operatorsFor('date', false)).toEqual(['between'])
    expect(operatorsFor('boolean', false)).toEqual(['is'])
  })

  it('round-trips through the URL, including values with delimiters and Arabic text', () => {
    const state: ListState = {
      ...DEFAULT_STATE,
      search: 'نورة',
      page: 3,
      perPage: 50,
      sort: 'visit_date',
      direction: 'asc',
      trashed: true,
      filters: [
        { ...emptyRow('name', 'starts_with'), value: 'a:b~c,d' },
        { ...emptyRow('tier', 'in'), values: ['gold', 'a,b'] },
        { ...emptyRow('visit_date', 'between'), from: '2026-10-01', to: null },
        { ...emptyRow('supplier', 'equals'), value: '0198f6a2-0000-7000-8000-000000000001', label: 'شركة ~ أكمي' },
      ],
    }
    const query = encodeQuery(state)
    expect(decodeQuery(query, ['name', 'tier', 'visit_date', 'supplier'])).toEqual(state)
    // Unknown fields in a shared link are ignored.
    expect(decodeQuery({ 'f.secret': 'equals:x' }, ['name']).filters).toEqual([])
  })

  it('omits defaults from the URL', () => {
    expect(encodeQuery(DEFAULT_STATE)).toEqual({})
  })

  it('maps rows to the API filter, skipping empty ones', () => {
    const rows = [
      { ...emptyRow('name', 'contains'), value: 'hamad' },
      { ...emptyRow('tier', 'in'), values: ['gold'] },
      { ...emptyRow('tier2', 'equals'), value: 'gold' },
      { ...emptyRow('paid', 'is'), value: 'true' },
      { ...emptyRow('supplier', 'equals'), value: 'uuid-1', label: 'Acme' },
      { ...emptyRow('visit_date', 'between'), to: '2026-10-31' },
      emptyRow('empty', 'contains'),
    ]
    expect(rows.map(isActive)).toEqual([true, true, true, true, true, true, false])
    expect(toApiFilter(rows)).toEqual({
      name: { op: 'contains', value: 'hamad' },
      tier: { op: 'in', values: ['gold'] },
      tier2: { op: 'equals', value: 'gold' },
      paid: 'true',
      supplier: 'uuid-1',
      visit_date: { to: '2026-10-31' },
    })
  })
})
