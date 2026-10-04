import { describe, expect, it } from 'vitest'
import { changedValues, conflictRows, defaultChoices, isConflictPayload, mergeAfterResolution, resolvedSubmission, type ConflictPayload } from './conflict'
import { FormIndex } from './formIndex'
import { runRules } from './rules'
import { submissionValues } from './submission'
import { ast, definition, field, uuid } from './testing'

const payload: ConflictPayload = {
  code: 'conflict',
  current_row_version: 5,
  changed_fields: [
    { field: 'status', base_value: 'new', their_value: 'approved', your_value: 'rejected' },
    { field: 'amount', base_value: '10', their_value: '12', your_value: null },
  ],
  changed_by: 'Omar',
  changed_at: '2026-10-04T08:00:00Z',
}

describe('conflict resolution', () => {
  it('recognises the 409 conflict payload only', () => {
    expect(isConflictPayload(payload)).toBe(true)
    expect(isConflictPayload({ code: 'idempotency_key_reused' })).toBe(false)
  })

  it('sends only changed top-level values', () => {
    expect(changedValues({ a: 1, b: [1], c: null }, { a: 1, b: [1, 2], c: null, d: 'x' })).toEqual({ b: [1, 2], d: 'x' })
  })

  it('lists conflicting fields (changed by both) separately from their other changes', () => {
    const rows = conflictRows(payload, { status: 'rejected', note: 'mine' })
    expect(rows.map((r) => [r.field, r.conflicting])).toEqual([
      ['status', true],
      ['amount', false],
    ])
    expect(defaultChoices(rows)).toEqual({ status: 'theirs' })
  })

  it('a field changed to the same value by both is not a conflict', () => {
    expect(conflictRows(payload, { status: 'approved' })[0]!.conflicting).toBe(false)
  })

  it('keeps their value by default and overwrites only fields the user chose', () => {
    const submitted = { status: 'rejected', note: 'mine' }
    const rows = conflictRows(payload, submitted)
    expect(resolvedSubmission(submitted, rows, defaultChoices(rows))).toEqual({ note: 'mine' })
    expect(resolvedSubmission(submitted, rows, { status: 'mine' })).toEqual({ status: 'rejected', note: 'mine' })
    expect(mergeAfterResolution({ status: 'approved', amount: '12', note: null }, { note: 'mine' })).toEqual({ status: 'approved', amount: '12', note: 'mine' })
  })
})

describe('submission body', () => {
  it('never sends computed, auto-numbered or read-only values', () => {
    const fields = [
      field('name', 'text'),
      field('locked', 'text', { access: 'read_only' }),
      field('no', 'auto_number'),
      field('calc', 'decimal', { behavior: { formula: ast.num('1') as never } }),
      field('heading', 'heading'),
      field('blocked', 'text'),
    ]
    const index = new FormIndex(
      definition(
        fields,
        [],
        [
          {
            uuid: uuid(),
            owner: { type: 'field', uuid: fields[5]!.uuid },
            when: { k: 'lit', t: 'boolean', v: true } as never,
            effects: [{ effect: 'disable', target: { type: 'field', uuid: fields[5]!.uuid } }],
          },
        ],
      ),
    )
    const values = { name: 'A', locked: 'L', no: 'N-1', calc: '1', blocked: 'B' }
    const { state } = runRules(index, values, null, { mode: 'create', user: {} })
    expect(submissionValues(index, state, 'create', values, {})).toEqual({ name: 'A' })
    expect(submissionValues(index, state, 'edit', { ...values, name: 'B' }, values)).toEqual({ name: 'B' })
    expect(submissionValues(index, state, 'edit', values, values)).toEqual({})
  })
})
