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

/**
 * Problems that block publishing, and warnings (the server repeats these
 * checks). Each issue's `message` carries the uuid of the status or
 * transition it concerns, so the designer can name and select it — never a
 * key in front of the user.
 */
export function checkWorkflow(doc: WorkflowDocument): { problems: Issue[]; warnings: Issue[] } {
  const problems: Issue[] = []
  const warnings: Issue[] = []
  if (doc.statuses.length === 0) return { problems, warnings }
  const initial = doc.statuses.filter((s) => s.initial)
  if (initial.length !== 1) problems.push({ path: 'statuses', code: 'initial_count', message: '' })
  const finals = new Set(doc.statuses.filter((s) => s.final).map((s) => s.uuid))
  doc.transitions.forEach((t, i) => {
    if (t.from !== null && finals.has(t.from)) problems.push({ path: `transitions.${i}.from`, code: 'final_outgoing', message: t.uuid })
  })
  const anyStatus = doc.transitions.filter((t) => t.from === null)
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
      if (!reached.has(s.uuid)) warnings.push({ path: `statuses.${i}`, code: 'unreachable', message: s.uuid })
    })
  }
  doc.statuses.forEach((s, i) => {
    // No way out: a status that is not final but has no transition leaving it (an "any status" transition counts unless it only leads back here).
    const out = doc.transitions.some((t) => t.from === s.uuid) || anyStatus.some((t) => t.to !== s.uuid)
    if (!s.final && !out) warnings.push({ path: `statuses.${i}`, code: 'dead_end', message: s.uuid })
    // No way in: a status that is not initial and no transition leads to.
    if (!s.initial && !doc.transitions.some((t) => t.to === s.uuid)) warnings.push({ path: `statuses.${i}`, code: 'no_entry', message: s.uuid })
  })
  return { problems, warnings }
}

/**
 * Positions for statuses, left to right by flow order: columns by distance
 * from the initial status (breadth-first over transitions between statuses),
 * and within a column, ordered by the average row of the statuses that lead
 * there so arrows cross as little as possible. Statuses the initial one
 * cannot reach follow to the right, laid out by their own flow.
 */
export function layoutStatuses(doc: WorkflowDocument, spacingX = 260, spacingY = 130): Map<string, { x: number; y: number }> {
  const out = new Map<string, { x: number; y: number }>()
  const depth = new Map<string, number>()
  const start = doc.statuses.find((s) => s.initial) ?? doc.statuses[0]
  if (!start) return out
  const walk = (root: string, base: number): void => {
    const queue = [root]
    depth.set(root, base)
    while (queue.length) {
      const u = queue.shift()!
      for (const t of doc.transitions) {
        if (t.from === u && !depth.has(t.to)) {
          depth.set(t.to, depth.get(u)! + 1)
          queue.push(t.to)
        }
      }
    }
  }
  walk(start.uuid, 0)
  // Statuses the initial one cannot reach follow, each group laid out by its own flow from where it starts.
  for (;;) {
    const rest = doc.statuses.filter((s) => !depth.has(s.uuid))
    if (!rest.length) break
    const root = rest.find((s) => !doc.transitions.some((t) => t.to === s.uuid && t.from !== null && !depth.has(t.from) && t.from !== s.uuid)) ?? rest[0]!
    walk(root.uuid, Math.max(0, ...depth.values()) + 1)
  }
  const columns = new Map<number, string[]>()
  for (const s of doc.statuses) {
    const d = depth.get(s.uuid)!
    columns.set(d, [...(columns.get(d) ?? []), s.uuid])
  }
  const row = new Map<string, number>()
  for (const d of [...columns.keys()].sort((a, b) => a - b)) {
    const ids = columns.get(d)!
    const order = (id: string): number => {
      const preds = doc.transitions.filter((t) => t.to === id && t.from !== null && row.has(t.from)).map((t) => row.get(t.from!)!)
      return preds.length ? preds.reduce((a, b) => a + b, 0) / preds.length : Number.MAX_SAFE_INTEGER
    }
    const sorted = d === 0 ? ids : [...ids].sort((a, b) => order(a) - order(b))
    sorted.forEach((id, r) => {
      row.set(id, r)
      out.set(id, { x: d * spacingX, y: r * spacingY })
    })
  }
  return out
}

/**
 * Which names and keys the designer still manages itself (design system
 * §5.6). A new transition is named after its target ("Move to Approved") and
 * a new item's key follows its name until the person edits them; a key is
 * locked once saved, because links and the API use it.
 */
export interface AutoState {
  name: Set<string>
  key: Set<string>
}

type Named = { uuid: string; key: string; i18n: { name: Record<string, string> } }

/** The key a name suggests, unique among the other items of its kind. */
export function keyFor(item: Named, siblings: Named[], defaultLocale: string, prefix: 's' | 't'): string {
  return suggestKey(
    item.i18n.name[defaultLocale] ?? Object.values(item.i18n.name)[0] ?? '',
    siblings.filter((x) => x.uuid !== item.uuid).map((x) => x.key),
    prefix,
  )
}

/** Re-routes a transition; its name and key follow the new target while the designer still manages them. */
export function reroute(
  doc: WorkflowDocument,
  tr: TransitionDoc,
  from: string | null,
  to: string,
  auto: AutoState,
  nameFor: (target: StatusDoc | undefined) => Record<string, string>,
  defaultLocale: string,
): void {
  tr.from = from
  tr.to = to
  if (auto.name.has(tr.uuid)) tr.i18n.name = nameFor(doc.statuses.find((s) => s.uuid === to))
  if (auto.key.has(tr.uuid)) tr.key = keyFor(tr, doc.transitions, defaultLocale, 't')
}

/** After a status is renamed: its key follows while managed, and so do the managed names of transitions leading to it. */
export function statusRenamed(doc: WorkflowDocument, status: StatusDoc, auto: AutoState, nameFor: (target: StatusDoc | undefined) => Record<string, string>, defaultLocale: string): void {
  if (auto.key.has(status.uuid)) status.key = keyFor(status, doc.statuses, defaultLocale, 's')
  for (const t of doc.transitions) if (t.to === status.uuid && auto.name.has(t.uuid)) reroute(doc, t, t.from, t.to, auto, nameFor, defaultLocale)
}

/** After the person edits a transition's name: it is theirs now; the key still follows while managed. */
export function transitionRenamed(doc: WorkflowDocument, tr: TransitionDoc, auto: AutoState, defaultLocale: string): void {
  auto.name.delete(tr.uuid)
  if (auto.key.has(tr.uuid)) tr.key = keyFor(tr, doc.transitions, defaultLocale, 't')
}
