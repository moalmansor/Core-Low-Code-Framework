import { describe, expect, it } from 'vitest'
import { applyBulk, buildChanges, cellOf, cellView, describeVector, editKey, isNoop, needsStepUp, parseDecidedBy, type Matrix, type PendingEdit } from './accessMatrix'

const FORM = '00000000-0000-4000-8000-000000000001'
const GROUP = '00000000-0000-4000-8000-000000000002'
const FIELD = '00000000-0000-4000-8000-000000000003'
const ROLE_A = '00000000-0000-4000-8000-0000000000a1'
const ROLE_B = '00000000-0000-4000-8000-0000000000b1'

const matrix: Matrix = {
  mode: 'edit',
  subject_type: 'role',
  subjects: [
    { type: 'role', uuid: ROLE_A, name: 'Clerk' },
    { type: 'role', uuid: ROLE_B, name: 'Manager' },
  ],
  targets: [
    { type: 'form', uuid: FORM, key: 'leave', label: 'Leave', depth: 0, parent: null },
    { type: 'group', uuid: GROUP, key: 'details', label: 'Details', depth: 1, parent: null },
    { type: 'field', uuid: FIELD, key: 'salary', label: 'Salary', depth: 2, parent: GROUP },
  ],
  cells: {
    [FORM]: { [ROLE_A]: { effective: 'editable', explicit: [] }, [ROLE_B]: { effective: 'editable', explicit: [] } },
    [GROUP]: { [ROLE_A]: { effective: 'read_only', explicit: [{ access: 'read_only', effect: 'allow', mode: null }] }, [ROLE_B]: { effective: 'editable', explicit: [] } },
    [FIELD]: { [ROLE_A]: { effective: 'hidden', explicit: [{ access: 'hidden', effect: 'deny', mode: 'edit' }] }, [ROLE_B]: { effective: 'editable', explicit: [] } },
  },
}

describe('access matrix state', () => {
  it('distinguishes inherited, explicit and pending cells', () => {
    expect(cellView(cellOf(matrix, FORM, ROLE_A), undefined, 'mode')).toEqual({ level: 'editable', source: 'inherited', effect: null, rule: null })
    const group = cellView(cellOf(matrix, GROUP, ROLE_A), undefined, 'mode')
    expect(group.source).toBe('explicit')
    expect(group.rule).toBeNull()
    expect(cellView(cellOf(matrix, GROUP, ROLE_A), undefined, 'all').rule).toEqual({ access: 'read_only', effect: 'allow', mode: null })
    expect(cellView(cellOf(matrix, FIELD, ROLE_A), undefined, 'mode')).toMatchObject({ level: 'hidden', source: 'explicit', effect: 'deny' })
    expect(cellView(cellOf(matrix, FIELD, ROLE_B), { access: 'required', effect: 'allow' }, 'mode')).toMatchObject({ level: 'required', source: 'pending' })
    expect(cellView(cellOf(matrix, FIELD, ROLE_A), { access: null, effect: 'allow' }, 'mode')).toMatchObject({ level: 'hidden', source: 'pending', rule: null })
    expect(cellOf(matrix, 'unknown', ROLE_A)).toEqual({ effective: null, explicit: [] })
  })

  it('detects edits that would not change anything', () => {
    expect(isNoop(cellOf(matrix, FIELD, ROLE_A), { access: 'hidden', effect: 'deny' }, 'mode')).toBe(true)
    expect(isNoop(cellOf(matrix, FIELD, ROLE_A), { access: 'hidden', effect: 'allow' }, 'mode')).toBe(false)
    expect(isNoop(cellOf(matrix, FORM, ROLE_A), { access: null, effect: 'allow' }, 'mode')).toBe(true)
    expect(isNoop(cellOf(matrix, GROUP, ROLE_A), { access: null, effect: 'allow' }, 'all')).toBe(false)
  })

  it('applies a bulk edit to a selection and builds the sparse payload', () => {
    const pending = new Map<string, PendingEdit>()
    applyBulk(
      pending,
      matrix,
      [
        { target: FIELD, subject: ROLE_A },
        { target: FIELD, subject: ROLE_B },
      ],
      { access: 'hidden', effect: 'deny' },
      'mode',
    )
    expect([...pending.keys()]).toEqual([editKey(FIELD, ROLE_B, 'mode')])
    pending.set(editKey(GROUP, ROLE_A, 'all'), { access: null, effect: 'hard_deny' })
    const changes = buildChanges(pending, matrix)
    expect(changes).toEqual([
      { target: { type: 'field', uuid: FIELD }, subject: { type: 'role', uuid: ROLE_B }, mode: 'edit', access: 'hidden', effect: 'deny' },
      { target: { type: 'group', uuid: GROUP }, subject: { type: 'role', uuid: ROLE_A }, mode: null, access: null, effect: 'allow' },
    ])
    expect(needsStepUp(changes)).toBe(false)
    pending.set(editKey(FORM, ROLE_B, 'mode'), { access: 'read_only', effect: 'hard_deny' })
    expect(needsStepUp(buildChanges(pending, matrix))).toBe(true)
  })

  it('handles the everyone subject', () => {
    const everyone: Matrix = {
      ...matrix,
      subject_type: 'everyone',
      subjects: [{ type: 'everyone', uuid: null, name: 'Everyone' }],
      cells: { [FORM]: { everyone: { effective: 'editable', explicit: [] } } },
    }
    const pending = new Map<string, PendingEdit>([[editKey(FORM, 'everyone', 'mode'), { access: 'read_only', effect: 'allow' }]])
    expect(buildChanges(pending, everyone)[0]!.subject).toEqual({ type: 'everyone', uuid: null })
  })

  it('explains resolver tiers', () => {
    expect(describeVector([2, 0, 1, 2])).toEqual({ level: 2, status: false, mode: true, subject: 'role' })
    expect(parseDecidedBy('allow:0001.0000.0001.0003')).toEqual({ kind: 'allow', vector: [1, 0, 1, 3] })
    expect(parseDecidedBy('hard_deny')).toEqual({ kind: 'hard_deny', vector: null })
    expect(parseDecidedBy('default')).toEqual({ kind: 'default', vector: null })
  })
})
