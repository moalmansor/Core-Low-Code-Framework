import { STATUS_COLORS } from '@/theme/statusPalette'
import type { Issue, StatusDoc, TransitionDoc, WorkflowDocument } from './api'

export { STATUS_COLORS }

/**
 * Pure helpers of the workflow designer: factories, key suggestions, the
 * same structural checks the server makes (exactly one initial status, no
 * transitions out of final statuses, unreachable statuses), and a layered
 * layout for statuses that have no saved position.
 */


export function suggestKey(name: string, taken: Iterable<string>, prefix = 's'): string {
  const base =
    name
      .toLowerCase()
      .normalize('NFKD')
      .replace(/[^a-z0-9]+/g, '_')
      .replace(/^_+|_+$/g, '')
      .slice(0, 40) || prefix
  const start = /^[a-z]/.test(base) ? base : `${prefix}_${base}`
  const used = new Set(taken)
  let key = start
  for (let i = 2; used.has(key); i++) key = `${start}_${i}`
  return key
}

export function newStatus(uuid: string, key: string, name: Record<string, string>, index: number, position: { x: number; y: number }): StatusDoc {
  return { uuid, key, i18n: { name }, color: STATUS_COLORS[index % STATUS_COLORS.length]!, icon: null, initial: index === 0, final: false, order: index, position }
}

export function newTransition(uuid: string, key: string, name: Record<string, string>, from: string | null, to: string, order: number): TransitionDoc {
  return {
    uuid,
    key,
    from,
    to,
    i18n: { name },
    condition: null,
    requiredFields: [],
    comment: 'none',
    attachments: 'none',
    approval: { mode: 'none', approvers: [], n: null, quorumWeight: null, dueInMinutes: null, rejection: 'immediate', rejectionStatus: null },
    confirmation: false,
    style: null,
    order,
    edge: null,
  }
}

/** Problems that block publishing, and warnings (the server repeats these checks). */
export function checkWorkflow(doc: WorkflowDocument): { problems: Issue[]; warnings: Issue[] } {
  const problems: Issue[] = []
  const warnings: Issue[] = []
  if (doc.statuses.length === 0) return { problems, warnings }
  const initial = doc.statuses.filter((s) => s.initial)
  if (initial.length !== 1) problems.push({ path: 'statuses', code: 'initial_count', message: '' })
  const finals = new Set(doc.statuses.filter((s) => s.final).map((s) => s.uuid))
  doc.transitions.forEach((t, i) => {
    if (t.from !== null && finals.has(t.from)) problems.push({ path: `transitions.${i}.from`, code: 'final_outgoing', message: t.key })
  })
  if (initial.length > 0) {
    const reached = new Set(initial.map((s) => s.uuid))
    let grew = true
    while (grew) {
      grew = false
      for (const t of doc.transitions) {
        if ((t.from === null || reached.has(t.from)) && !reached.has(t.to)) {
          reached.add(t.to)
          grew = true
        }
      }
    }
    doc.statuses.forEach((s, i) => {
      if (!reached.has(s.uuid)) warnings.push({ path: `statuses.${i}`, code: 'unreachable', message: s.key })
    })
  }
  return { problems, warnings }
}

/**
 * Positions for statuses without one: columns by distance from the initial
 * status (breadth-first), rows within a column.
 */
export function layoutStatuses(doc: WorkflowDocument, spacingX = 240, spacingY = 120): Map<string, { x: number; y: number }> {
  const out = new Map<string, { x: number; y: number }>()
  const depth = new Map<string, number>()
  const start = doc.statuses.find((s) => s.initial) ?? doc.statuses[0]
  if (!start) return out
  const queue = [start.uuid]
  depth.set(start.uuid, 0)
  while (queue.length) {
    const u = queue.shift()!
    for (const t of doc.transitions) {
      if ((t.from === u || t.from === null) && !depth.has(t.to)) {
        depth.set(t.to, depth.get(u)! + 1)
        queue.push(t.to)
      }
    }
  }
  const maxDepth = Math.max(0, ...depth.values())
  const rows = new Map<number, number>()
  for (const s of doc.statuses) {
    const d = depth.get(s.uuid) ?? maxDepth + 1
    const r = rows.get(d) ?? 0
    rows.set(d, r + 1)
    out.set(s.uuid, { x: d * spacingX, y: r * spacingY })
  }
  return out
}
