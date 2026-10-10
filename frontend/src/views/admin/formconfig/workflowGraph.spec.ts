import { describe, expect, it } from 'vitest'
import type { StatusDoc } from './api'
import { checkWorkflow, layoutStatuses, newStatus, newTransition, reroute, statusRenamed, suggestKey, transitionRenamed, type AutoState } from './workflowGraph'

const s = (uuid: string, initial = false, final = false) => ({ ...newStatus(uuid, uuid, { en: uuid }, 1, { x: 0, y: 0 }), initial, final })
const named = (uuid: string, name: string, key: string) => ({ ...newStatus(uuid, key, { en: name }, 1, { x: 0, y: 0 }) })
const moveTo = (target: StatusDoc | undefined) => ({ en: `Move to ${target?.i18n.name.en ?? '?'}` })

describe('workflow designer helpers', () => {
  it('suggests unique keys that start with a letter', () => {
    expect(suggestKey('Under Review', [])).toBe('under_review')
    expect(suggestKey('Under Review', ['under_review'])).toBe('under_review_2')
    expect(suggestKey('مراجعة', [])).toBe('s')
    expect(suggestKey('2nd check', [], 't')).toBe('t_2nd_check')
  })

  it('reports the same problems as the server, naming items by uuid (never by key)', () => {
    const doc = { statuses: [s('a', true), s('b', true, true), s('c')], transitions: [newTransition('t1', 'back', { en: 'x' }, 'b', 'a', 0)], sla: [] }
    const { problems, warnings } = checkWorkflow(doc)
    expect(problems.map((p) => p.code).sort()).toEqual(['final_outgoing', 'initial_count'])
    expect(problems.find((p) => p.code === 'final_outgoing')!.message).toBe('t1')
    expect(warnings.filter((w) => w.code === 'unreachable').map((w) => w.message)).toEqual(['c'])
    expect(checkWorkflow({ statuses: [], transitions: [], sla: [] }).problems).toEqual([])
  })

  it('finds statuses with no way out that are not final, and no way in that are not initial', () => {
    const doc = {
      statuses: [s('start', true), s('review'), s('stuck'), s('orphan'), s('done', false, true)],
      transitions: [
        newTransition('1', 'a', {}, 'start', 'review', 0),
        newTransition('2', 'b', {}, 'review', 'done', 1),
        newTransition('3', 'c', {}, 'review', 'stuck', 2),
        newTransition('4', 'd', {}, 'orphan', 'done', 3),
      ],
      sla: [],
    }
    const { warnings } = checkWorkflow(doc)
    expect(warnings.filter((w) => w.code === 'dead_end').map((w) => w.message)).toEqual(['stuck'])
    expect(warnings.filter((w) => w.code === 'no_entry').map((w) => w.message)).toEqual(['orphan'])
    expect(warnings.filter((w) => w.code === 'unreachable').map((w) => w.message)).toEqual(['orphan'])
    // An "any status" transition is a way out of every other status.
    doc.transitions.push(newTransition('5', 'cancel', {}, null, 'done', 4))
    expect(checkWorkflow(doc).warnings.filter((w) => w.code === 'dead_end')).toEqual([])
  })

  it('lays statuses out left to right by flow order, ordering each column to limit crossings', () => {
    const doc = {
      statuses: [s('a', true), s('b'), s('c'), s('d'), s('e')],
      transitions: [newTransition('1', 'x', {}, 'a', 'b', 0), newTransition('2', 'y', {}, 'a', 'c', 1), newTransition('3', 'z', {}, 'c', 'd', 2), newTransition('4', 'w', {}, 'b', 'e', 3)],
      sla: [],
    }
    const pos = layoutStatuses(doc)
    expect(pos.get('a')).toEqual({ x: 0, y: 0 })
    expect(pos.get('b')).toEqual({ x: 260, y: 0 })
    expect(pos.get('c')).toEqual({ x: 260, y: 130 })
    // e follows b (row 0) and d follows c (row 1): their column keeps that order although d comes first in the list.
    expect(pos.get('e')).toEqual({ x: 520, y: 0 })
    expect(pos.get('d')).toEqual({ x: 520, y: 130 })
  })

  it('lays out statuses the initial one cannot reach by their own flow, after the rest', () => {
    const doc = { statuses: [s('a', true), s('b'), s('c'), s('d')], transitions: [newTransition('1', 'x', {}, 'a', 'b', 0), newTransition('2', 'y', {}, 'c', 'd', 1)], sla: [] }
    const pos = layoutStatuses(doc)
    expect(pos.get('c')!.x).toBe(520)
    expect(pos.get('d')!.x).toBe(780)
  })

  it('keeps a dragged transition’s name and key in step with its route until the person edits them (owner report 11a)', () => {
    const rejected = named('r', 'Rejected', 'rejected')
    const returned = named('ret', 'Returned', 'returned_st')
    const s5 = named('s5', 'Status 5', 'status_5')
    const tr = newTransition('t', 'move_to_status_5', moveTo(s5), 'r', 's5', 0)
    const doc = { statuses: [rejected, returned, s5], transitions: [tr], sla: [] }
    const auto: AutoState = { name: new Set(['t']), key: new Set(['t']) }

    // Re-routed to Returned: the name and the key follow; nothing stale is left behind.
    reroute(doc, tr, 'r', 'ret', auto, moveTo, 'en')
    expect([tr.from, tr.to, tr.i18n.name.en, tr.key]).toEqual(['r', 'ret', 'Move to Returned', 'move_to_returned'])

    // Renamed by the person: the name is theirs; the key still follows it until saved.
    tr.i18n.name.en = 'Send back'
    transitionRenamed(doc, tr, auto, 'en')
    expect(tr.key).toBe('send_back')
    reroute(doc, tr, 'r', 's5', auto, moveTo, 'en')
    expect([tr.to, tr.i18n.name.en, tr.key]).toEqual(['s5', 'Send back', 'send_back'])

    // Once the key is the person's (edited or saved), nothing changes it.
    auto.key.delete('t')
    tr.i18n.name.en = 'Return for changes'
    transitionRenamed(doc, tr, auto, 'en')
    expect(tr.key).toBe('send_back')
  })

  it('renames managed transitions when their target status is renamed', () => {
    const a = named('a', 'Draft', 'draft')
    const b = named('b', 'Status 2', 'status_2')
    const tr = newTransition('t', 'move_to_status_2', moveTo(b), 'a', 'b', 0)
    const doc = { statuses: [a, b], transitions: [tr], sla: [] }
    const auto: AutoState = { name: new Set(['t', 'b']), key: new Set(['t', 'b']) }
    b.i18n.name.en = 'Approved'
    statusRenamed(doc, b, auto, moveTo, 'en')
    expect([b.key, tr.i18n.name.en, tr.key]).toEqual(['approved', 'Move to Approved', 'move_to_approved'])
  })
})
