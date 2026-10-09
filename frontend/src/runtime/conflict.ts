import type { Values } from './types'
import { same } from './values'

/**
 * Optimistic concurrency on the client (specification §4.9): saves send only
 * the fields the user changed with the version they loaded; a 409 `conflict`
 * (backend RecordPipeline::conflict) lists what changed since. The user
 * resolves each conflicting field (keep theirs, or overwrite with mine) and
 * the resubmission carries only the fields they chose to overwrite, with the
 * new row version. Nothing is ever overwritten silently.
 */

export interface ConflictPayload {
  message?: string
  code: 'conflict'
  current_row_version: number
  changed_fields: { field: string; base_value: unknown; their_value: unknown; your_value: unknown }[]
  changed_by: string | null
  changed_at: string | null
}

export type Choice = 'mine' | 'theirs'

export interface ConflictRow {
  field: string
  base: unknown
  theirs: unknown
  mine: unknown
  /** The user changed this field too: a real conflict that needs a choice. */
  conflicting: boolean
}

export function isConflictPayload(body: unknown): body is ConflictPayload {
  return typeof body === 'object' && body !== null && (body as { code?: unknown }).code === 'conflict' && Array.isArray((body as { changed_fields?: unknown }).changed_fields)
}

/** Top-level keys whose value differs from what was loaded. */
export function changedValues(loaded: Values, current: Values): Values {
  const out: Values = {}
  for (const key of new Set([...Object.keys(loaded), ...Object.keys(current)])) {
    if (!same(loaded[key], current[key])) out[key] = current[key] ?? null
  }
  return out
}

export function conflictRows(payload: ConflictPayload, submitted: Values): ConflictRow[] {
  return payload.changed_fields.map((c) => {
    const conflicting = Object.prototype.hasOwnProperty.call(submitted, c.field) && !same(submitted[c.field], c.their_value)
    return { field: c.field, base: c.base_value, theirs: c.their_value, mine: conflicting ? submitted[c.field] : c.your_value, conflicting }
  })
}

/** Fields that need the user's decision; each defaults to keeping the other user's value. */
export function defaultChoices(rows: ConflictRow[]): Record<string, Choice> {
  const out: Record<string, Choice> = {}
  for (const r of rows) if (r.conflicting) out[r.field] = 'theirs'
  return out
}

/**
 * The resolved submission: everything the user changed that nobody else
 * touched, plus the conflicting fields they chose to overwrite.
 */
export function resolvedSubmission(submitted: Values, rows: ConflictRow[], choices: Record<string, Choice>): Values {
  const out: Values = { ...submitted }
  for (const r of rows) {
    if (r.conflicting && choices[r.field] !== 'mine') delete out[r.field]
  }
  return out
}

/**
 * The values to show after resolving: the latest record's values with the
 * user's kept changes applied on top.
 */
export function mergeAfterResolution(latest: Values, submission: Values): Values {
  return { ...latest, ...submission }
}
