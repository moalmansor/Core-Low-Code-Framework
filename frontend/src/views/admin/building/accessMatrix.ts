/**
 * State helpers for the group/field access matrix (specification §4.11,
 * architecture §16.4). The server returns, per target × subject, the
 * effective level and the explicit rules stored for that cell; the editor
 * keeps pending edits keyed by cell and scope (this mode or all modes) and
 * turns them into the sparse PUT /forms/{form}/access-rules payload.
 */

export const LEVELS = ['hidden', 'read_only', 'editable', 'required'] as const
export type Level = (typeof LEVELS)[number]
export const MODES = ['create', 'edit', 'view', 'print'] as const
export type Mode = (typeof MODES)[number]
export type Effect = 'allow' | 'deny' | 'hard_deny'
export type SubjectType = 'everyone' | 'role' | 'department' | 'user'
export type TargetType = 'form' | 'group' | 'field'
/** A rule written for the selected mode only, or for every mode. */
export type Scope = 'mode' | 'all'

export interface Target {
  type: TargetType
  uuid: string
  key: string
  label: string | null
  depth: number
  parent: string | null
}
export interface Subject {
  type: SubjectType
  uuid: string | null
  name: string
}
export interface ExplicitRule {
  access: Level
  effect: Effect
  mode: Mode | null
}
export interface Cell {
  effective: Level | null
  explicit: ExplicitRule[]
}
export interface Matrix {
  mode: Mode
  subject_type: SubjectType
  subjects: Subject[]
  targets: Target[]
  cells: Record<string, Record<string, Cell>>
}

/** A pending edit; `access: null` resets the cell to inherited (deletes the rule). */
export interface PendingEdit {
  access: Level | null
  effect: Effect
}

export interface RuleChange {
  target: { type: TargetType; uuid: string }
  subject: { type: SubjectType; uuid: string | null }
  mode: Mode | null
  access: Level | null
  effect: Effect
}

export const subjectKey = (s: Subject): string => s.uuid ?? 'everyone'
export const editKey = (targetUuid: string, subject: string, scope: Scope): string => `${targetUuid}|${subject}|${scope}`

export function cellOf(matrix: Matrix, targetUuid: string, subject: string): Cell {
  return matrix.cells[targetUuid]?.[subject] ?? { effective: null, explicit: [] }
}

/** The stored rule for one scope of a cell (mode-specific or all modes). */
export function explicitFor(cell: Cell, scope: Scope): ExplicitRule | null {
  return cell.explicit.find((r) => (scope === 'all' ? r.mode === null : r.mode !== null)) ?? null
}

export type CellSource = 'inherited' | 'explicit' | 'pending'

export interface CellView {
  level: Level | null
  source: CellSource
  effect: Effect | null
  /** The explicit rule for the edited scope after pending edits, if any. */
  rule: { access: Level; effect: Effect } | null
}

/**
 * What a cell shows: a pending edit wins, then an explicit rule in either
 * scope, then the inherited effective level. The effective level is the
 * server's resolution and is only recomputed after saving.
 */
export function cellView(cell: Cell, pending: PendingEdit | undefined, scope: Scope): CellView {
  if (pending) {
    return {
      level: pending.access ?? cell.effective,
      source: 'pending',
      effect: pending.access === null ? null : pending.effect,
      rule: pending.access === null ? null : { access: pending.access, effect: pending.effect },
    }
  }
  const own = explicitFor(cell, scope) ?? explicitFor(cell, scope === 'all' ? 'mode' : 'all')
  return {
    level: cell.effective,
    source: own ? 'explicit' : 'inherited',
    effect: own?.effect ?? null,
    rule: explicitFor(cell, scope),
  }
}

/** Whether a pending edit would change what is stored. */
export function isNoop(cell: Cell, edit: PendingEdit, scope: Scope): boolean {
  const stored = explicitFor(cell, scope)
  if (edit.access === null) return stored === null
  return stored !== null && stored.access === edit.access && stored.effect === edit.effect
}

/** Apply one edit to many cells (bulk edit across a selection), dropping no-ops. */
export function applyBulk(pending: Map<string, PendingEdit>, matrix: Matrix, cells: { target: string; subject: string }[], edit: PendingEdit, scope: Scope): void {
  for (const c of cells) {
    const key = editKey(c.target, c.subject, scope)
    if (isNoop(cellOf(matrix, c.target, c.subject), edit, scope)) pending.delete(key)
    else pending.set(key, { ...edit })
  }
}

export function buildChanges(pending: Map<string, PendingEdit>, matrix: Matrix): RuleChange[] {
  const targets = new Map(matrix.targets.map((t) => [t.uuid, t]))
  const subjects = new Map(matrix.subjects.map((s) => [subjectKey(s), s]))
  const out: RuleChange[] = []
  for (const [key, edit] of pending) {
    const [targetUuid, subject, scope] = key.split('|') as [string, string, Scope]
    const t = targets.get(targetUuid)
    const s = subjects.get(subject)
    if (!t || !s) continue
    out.push({
      target: { type: t.type, uuid: t.uuid },
      subject: { type: s.type, uuid: s.uuid },
      mode: scope === 'all' ? null : matrix.mode,
      access: edit.access,
      effect: edit.access === null ? 'allow' : edit.effect,
    })
  }
  return out
}

/** Setting a hard deny needs step-up confirmation (the server asks; the UI warns ahead). */
export function needsStepUp(changes: RuleChange[]): boolean {
  return changes.some((c) => c.effect === 'hard_deny' && c.access !== null)
}

const SUBJECT_TIERS: SubjectType[] = ['everyone', 'department', 'role', 'user']

/** Reads a resolver tier vector [element level, status-specific, mode-specific, subject rank]. */
export function describeVector(vector: number[]): { level: number; status: boolean; mode: boolean; subject: SubjectType } {
  return { level: vector[0] ?? 0, status: (vector[1] ?? 0) === 1, mode: (vector[2] ?? 0) === 1, subject: SUBJECT_TIERS[vector[3] ?? 0] ?? 'everyone' }
}

/** Splits the resolver's `decided_by` ("default", "hard_deny", "allow:0001.0000.0001.0002"). */
export function parseDecidedBy(value: string): { kind: 'default' | 'hard_deny' | 'allow' | 'deny'; vector: number[] | null } {
  const [kind, vec] = value.split(':')
  const vector = vec ? vec.split('.').map((n) => Number(n)) : null
  if (kind === 'allow' || kind === 'deny') return { kind, vector }
  return { kind: kind === 'hard_deny' ? 'hard_deny' : 'default', vector: null }
}
