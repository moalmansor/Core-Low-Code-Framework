import { describe, expect, it } from 'vitest'
import { daysFromCivil } from '@/expressions/civil'
import { FormIndex } from './formIndex'
import { applyDefaults, baseContext, fieldState, runRules, type RuleEnv } from './rules'
import { ast, definition, field, group, uuid } from './testing'
import type { Condition } from './types'
import { validate } from './validation'

const today = daysFromCivil(2026, 10, 4)
const env = (mode: RuleEnv['mode'] = 'create', extra: Partial<RuleEnv> = {}): RuleEnv => ({
  mode,
  user: { id: 7, uuid: 'AAAAAAAA-0000-4000-8000-000000000001', name: 'Mona', roles: ['clerk'] },
  now: { today, now: today * 86400 + 3600 },
  ...extra,
})
const t = (key: string, params?: Record<string, unknown>) => `${key}${params ? JSON.stringify(params) : ''}`

function cond(owner: string, when: unknown, effects: Condition['effects'], elseEffects: Condition['effects'] = []): Condition {
  return { uuid: uuid(), owner: { type: 'field', uuid: owner }, when: when as never, effects, else: elseEffects }
}

describe('rule runtime (twin of RuleRuntime.php)', () => {
  it('computes formulas in passes until values settle', () => {
    const qty = field('qty', 'number')
    const price = field('price', 'decimal')
    const total = field('total', 'decimal', { behavior: { formula: ast.bin('*', ast.ref('qty'), ast.ref('price')) as never } })
    const vat = field('vat', 'decimal', { behavior: { formula: ast.bin('*', ast.ref('total'), ast.num('0.15')) as never } })
    const index = new FormIndex(definition([qty, price, vat, total]))
    const { values } = runRules(index, { qty: '3', price: '10.50' }, null, env())
    expect(values.total).toBe('31.5')
    expect(values.vat).toBe('4.725')
  })

  it('applies show/hide, require, read-only and set/clear value effects', () => {
    const amount = field('amount', 'number')
    const reason = field('reason', 'text')
    const level = field('level', 'text')
    const note = field('note', 'text')
    const big = ast.bin('>', ast.ref('amount'), ast.num('1000'))
    const index = new FormIndex(
      definition(
        [amount, reason, level, note],
        [],
        [
          cond(reason.uuid, big, [{ effect: 'require', target: { type: 'field', uuid: reason.uuid } }], [{ effect: 'hide', target: { type: 'field', uuid: reason.uuid } }]),
          cond(level.uuid, big, [
            { effect: 'set_value', target: { type: 'field', uuid: level.uuid }, value: ast.text('high') as never },
            { effect: 'read_only', target: { type: 'field', uuid: level.uuid } },
          ]),
          cond(note.uuid, big, [{ effect: 'clear_value', target: { type: 'field', uuid: note.uuid } }]),
        ],
      ),
    )
    const small = runRules(index, { amount: '10', note: 'keep' }, null, env())
    expect(fieldState(index, reason, small.state, null, 'create').hidden).toBe(true)
    expect(small.values.note).toBe('keep')
    const large = runRules(index, { amount: '5000', note: 'drop' }, null, env())
    expect(fieldState(index, reason, large.state, null, 'create')).toMatchObject({ hidden: false, required: true })
    expect(large.values.level).toBe('high')
    expect(fieldState(index, level, large.state, null, 'create').readOnly).toBe(true)
    expect(large.values.note).toBeNull()
  })

  it('evaluates repeater conditions and formulas per row with row scope', () => {
    const lines = group('lines', 'repeater', { repeater: { minRows: 1 } })
    const qty = field('qty', 'number', { group: lines.uuid })
    const unit = field('unit', 'decimal', { group: lines.uuid })
    const sub = field('sub', 'decimal', { group: lines.uuid, behavior: { formula: ast.bin('*', ast.ref('qty'), ast.ref('unit')) as never } })
    const discount = field('discount', 'decimal', { group: lines.uuid })
    const grand = field('grand', 'decimal', { behavior: { formula: ast.call('sum', ast.ref('lines', 'sub')) as never } })
    const index = new FormIndex(
      definition([qty, unit, sub, discount, grand], [lines], [cond(discount.uuid, ast.bin('<', ast.ref('qty'), ast.num('10')), [{ effect: 'hide', target: { type: 'field', uuid: discount.uuid } }])]),
    )
    const { values, state } = runRules(
      index,
      {
        lines: [
          { qty: '2', unit: '5' },
          { qty: '20', unit: '1.5' },
        ],
      },
      null,
      env(),
    )
    const rows = values.lines as Record<string, unknown>[]
    expect(rows[0]!.sub).toBe('10')
    expect(rows[1]!.sub).toBe('30')
    expect(values.grand).toBe('40')
    expect(state.flag(discount.uuid, 'hidden', ['lines', 0])).toBe(true)
    expect(state.flag(discount.uuid, 'hidden', ['lines', 1])).toBe(false)
  })

  it('applies defaults on create: static, user, today, URL parameter, field, formula, option defaults and repeater rows', () => {
    const lines = group('lines', 'repeater', { repeater: { defaultRows: 2 } })
    const fields = [
      field('a', 'text', { behavior: { default: { kind: 'static', value: 'x' } } }),
      field('owner', 'user_picker', { behavior: { default: { kind: 'current_user' } } }),
      field('name', 'text', { behavior: { default: { kind: 'current_user' } } }),
      field('day', 'date', { behavior: { default: { kind: 'today' } } }),
      field('ref', 'text', { behavior: { default: { kind: 'url_param', param: 'ref' } } }),
      field('twice', 'number', { behavior: { default: { kind: 'formula', expr: ast.bin('*', ast.num('2'), ast.num('21')) as never } } }),
      field('color', 'select', {
        options: {
          source: 'static',
          static: [
            { uuid: uuid(), value: 'red' },
            { uuid: uuid(), value: 'blue', default: true },
          ],
        },
      }),
      field('qty', 'number', { group: lines.uuid, behavior: { default: { kind: 'static', value: '1' } } }),
    ]
    const copy = field('copy', 'text', { behavior: { default: { kind: 'field', field: fields[0]!.uuid } } })
    const index = new FormIndex(definition([...fields, copy], [lines]))
    const values = applyDefaults(index, { a: null }, env('create', { params: { ref: 'R-9' } }))
    expect(values).toMatchObject({ a: 'x', owner: 'aaaaaaaa-0000-4000-8000-000000000001', name: 'Mona', day: '2026-10-04', ref: 'R-9', twice: '42', color: 'blue', copy: 'x' })
    expect(values.lines).toEqual([{ qty: '1' }, { qty: '1' }])
  })

  it('reads the acting user, the mode and old values', () => {
    const f = field('f', 'text')
    const isClerk = ast.call('in', ast.text('clerk'), { k: 'ref', scope: 'user', path: ['roles'] })
    const index = new FormIndex(definition([f], [], [cond(f.uuid, isClerk, [{ effect: 'disable', target: { type: 'field', uuid: f.uuid } }])]))
    const { state } = runRules(index, {}, null, env())
    expect(fieldState(index, f, state, null, 'create').disabled).toBe(true)
  })

  it('hides a field when its group is hidden, and makes everything read-only in view mode', () => {
    const sec = group('sec', 'section')
    const f = field('f', 'text', { group: sec.uuid })
    const switcher = field('sw', 'checkbox')
    const c: Condition = { uuid: uuid(), owner: { type: 'group', uuid: sec.uuid }, when: ast.ref('sw') as never, effects: [{ effect: 'hide', target: { type: 'group', uuid: sec.uuid } }] }
    const index = new FormIndex(definition([f, switcher], [sec], [c]))
    expect(fieldState(index, f, runRules(index, { sw: true }, null, env()).state, null, 'create').hidden).toBe(true)
    expect(fieldState(index, f, runRules(index, { sw: false }, null, env()).state, null, 'view').readOnly).toBe(true)
  })

  it('collects messages and submit blocks', () => {
    const f = field('f', 'number')
    const index = new FormIndex(
      definition(
        [f],
        [],
        [
          {
            uuid: uuid(),
            owner: { type: 'form', uuid: uuid() },
            when: ast.bin('>', ast.ref('f'), ast.num('5')) as never,
            effects: [
              { effect: 'show_message', severity: 'warning', message: { en: 'Large' } },
              { effect: 'block_submit', message: { en: 'Too large' } },
            ],
          },
        ],
      ),
    )
    const { values, state } = runRules(index, { f: '9' }, null, env())
    expect(state.messages.map((m) => m.message.en)).toEqual(['Large'])
    const errors = validate({ index, values, state, context: baseContext(index, env()), locale: 'en', t })
    expect(errors._form).toEqual(['Too large'])
  })
})

describe('client validation (twin of RecordValidator.php)', () => {
  it('checks required, length, pattern, format, numbers, dates, options and custom rules', () => {
    const fields = [
      field('req', 'text', { validation: { required: true } }),
      field('short', 'text', { validation: { length: { min: 3 } } }),
      field('code', 'text', { validation: { pattern: '^[A-Z]{2}$' } }),
      field('mail', 'email', { validation: { format: 'email' } }),
      field('iban', 'iban', { validation: { format: 'iban' } }),
      field('n', 'number', { validation: { number: { min: '1', max: '10', step: '2' } } }),
      field('d', 'date', { validation: { date: { noPast: true } } }),
      field('opt', 'select', { options: { source: 'static', static: [{ uuid: uuid(), value: 'a' }] } }),
      field('cu', 'text', {
        validation: { custom: [{ when: ast.bin('=', ast.ref('cu'), ast.text('bad')) as never, messageKey: 'no_bad' }] },
        i18n: { label: { en: 'Cu' }, messages: { no_bad: { en: 'Not bad please' } } },
      }),
    ]
    const index = new FormIndex(definition(fields))
    const values = { short: 'ab', code: 'abc', mail: 'x@', iban: 'SA0380000000608010167519', n: '4', d: '2026-10-01', opt: 'z', cu: 'bad' }
    const { state } = runRules(index, values, null, env())
    const errors = validate({ index, values, state, context: baseContext(index, env()), locale: 'en', t })
    expect(Object.keys(errors).sort()).toEqual(['code', 'cu', 'd', 'mail', 'n', 'opt', 'req', 'short'])
    expect(errors.cu).toEqual(['Not bad please'])
    expect(errors.n![0]).toContain('runtime.rule.step')
  })

  it('checks repeater row counts and row fields under their row paths', () => {
    const lines = group('lines', 'repeater', { repeater: { minRows: 2, maxRows: 3 } })
    const qty = field('qty', 'number', { group: lines.uuid, validation: { required: true } })
    const index = new FormIndex(definition([qty], [lines]))
    const values = { lines: [{ qty: null }] }
    const { state } = runRules(index, values, null, env())
    const errors = validate({ index, values, state, context: baseContext(index, env()), locale: 'en', t })
    expect(errors.lines![0]).toContain('runtime.rule.min_rows')
    expect(errors['lines.0.qty']![0]).toContain('runtime.rule.required')
  })

  it('checks a group’s minimum filled fields', () => {
    const sec = group('contact', 'section', { validation: { minFilled: 1 } })
    const index = new FormIndex(definition([field('phone', 'tel', { group: sec.uuid }), field('mail', 'email', { group: sec.uuid })], [sec]))
    const { state } = runRules(index, {}, null, env())
    expect(validate({ index, values: {}, state, context: baseContext(index, env()), locale: 'en', t })['_group.contact']).toHaveLength(1)
  })
})
