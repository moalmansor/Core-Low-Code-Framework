import { describe, expect, it } from 'vitest'
import { Values as V, Decimal } from '@/expressions'
import { FormIndex } from './formIndex'
import { definition, field } from './testing'
import type { ClientField } from './types'
import { isEmpty, toApi, toExpression } from './values'

function setup(f: ClientField, relations: FormIndex['definition']['relations'] = []): FormIndex {
  return new FormIndex(definition([f], [], [], { relations }))
}

describe('API value ↔ expression value (ValuesRecord / ValueBridge twins)', () => {
  const cases: [string, unknown, string, unknown][] = [
    ['text', 'abc', 'text', 'abc'],
    ['decimal', '12.50', 'number', '12.5'],
    ['rating', '4', 'number', '4'],
    ['checkbox', true, 'boolean', true],
    ['consent', false, 'boolean', false],
    ['date', '2026-03-01', 'date', '2026-03-01'],
    ['datetime_local', '2026-03-01T10:20:30Z', 'datetime', '2026-03-01T10:20:30Z'],
    ['time', '08:15:00', 'time', '08:15:00'],
    ['duration', '3600', 'duration', '3600'],
    ['select', 'a', 'text', 'a'],
  ]
  for (const [type, api, exprType, back] of cases) {
    it(`${type}: ${JSON.stringify(api)}`, () => {
      const f = field('f', type)
      const index = setup(f)
      const v = toExpression(index, f, api)
      expect(v.type).toBe(exprType)
      expect(toApi(index, f, v)).toEqual(back)
    })
  }

  it('lists: multi choice and files', () => {
    const multi = field('m', 'checkbox_group')
    const index = setup(multi)
    const v = toExpression(index, multi, ['a', 'b'])
    expect(v.type).toBe('list')
    expect(toApi(index, multi, v)).toEqual(['a', 'b'])
    expect(toExpression(index, multi, null)).toEqual(V.list([]))
    const files = field('fs', 'file_multi')
    expect(toExpression(setup(files), files, ['u1']).type).toBe('list')
  })

  it('a single file is a one-item list', () => {
    const f = field('f', 'file')
    const v = toExpression(setup(f), f, '0f0e0d0c-0000-4000-8000-000000000001')
    expect(v.type).toBe('list')
  })

  it('ranges are two-item lists and come back as {from, to}', () => {
    const f = field('r', 'date_range')
    const index = setup(f)
    const v = toExpression(index, f, { from: '2026-01-01', to: '2026-01-31' })
    expect(v.type).toBe('list')
    expect(toApi(index, f, v)).toEqual({ from: '2026-01-01', to: '2026-01-31' })
  })

  it('currency (single and multi-currency), map, phone and JSON', () => {
    const money = field('c', 'currency', { storage: { multiCurrency: true } })
    const idx = setup(money)
    const v = toExpression(idx, money, { amount: '10.25', currency: 'SAR' })
    expect(v.type === 'number' && v.data.toString()).toBe('10.25')
    expect(toApi(idx, money, V.number(Decimal.of('3')))).toBe('3')
    const map = field('m', 'map_location')
    expect(toExpression(setup(map), map, { lat: '24.7', lng: '46.6', label: null })).toEqual(V.text('24.7,46.6'))
    const phone = field('p', 'phone_intl')
    expect(toExpression(setup(phone), phone, { number: '+966500000000', country: 'SA' })).toEqual(V.text('+966500000000'))
    const json = field('j', 'key_value')
    expect(toExpression(setup(json), json, { a: '1' })).toEqual(V.text('{"a":"1"}'))
  })

  it('references become records titled from the payload, many-to-many become lists', () => {
    const relation = { uuid: '11111111-0000-4000-8000-000000000001', key: 'emp', type: 'many_to_one' as const, target: '22222222-0000-4000-8000-000000000001', kind: 'reference' as const }
    const f = field('emp', 'lookup', { relation: relation.uuid })
    const index = setup(f, [relation])
    const v = toExpression(index, f, 'abc', { emp: { abc: 'Sara' } })
    expect(v.type).toBe('record')
    if (v.type === 'record') expect(v.data.title()).toBe('Sara')
    expect(toApi(index, f, v)).toBe('abc')
    const many = { ...relation, type: 'many_to_many' as const }
    const tags = field('tags', 'lookup', { relation: many.uuid })
    const idx2 = setup(tags, [many])
    const list = toExpression(idx2, tags, ['a', 'b'])
    expect(list.type).toBe('list')
    expect(toApi(idx2, tags, list)).toEqual(['a', 'b'])
  })

  it('booleans become text or numbers like the server does', () => {
    const t = field('t', 'text')
    expect(toApi(setup(t), t, V.bool(true))).toBe('true')
    const n = field('n', 'number')
    expect(toApi(setup(n), n, V.bool(false))).toBe('0')
  })

  it('emptiness follows the server validator', () => {
    expect(isEmpty(null)).toBe(true)
    expect(isEmpty('')).toBe(true)
    expect(isEmpty(false)).toBe(true)
    expect(isEmpty([])).toBe(true)
    expect(isEmpty({ from: null, to: '' })).toBe(true)
    expect(isEmpty({ from: '2026-01-01', to: null })).toBe(false)
    expect(isEmpty(0)).toBe(false)
  })
})
