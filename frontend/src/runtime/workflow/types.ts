import { ApiError } from '@/api/http'

/**
 * Types of the workflow, justification and assignment parts of the records
 * runtime API (architecture §21 Workflow, Justification, Assignment).
 */

export interface StatusPayload {
  uuid: string
  key: string
  name: string
  color: string
  icon: string | null
  initial?: boolean
  final?: boolean
  archived?: boolean
}

export interface TransitionOption {
  uuid: string
  key: string
  name: string
  to: StatusPayload | null
  comment: 'none' | 'optional' | 'mandatory'
  attachments: 'none' | 'optional' | 'mandatory'
  /** Fields the transition needs filled: key (to highlight the field), label in the user's language, and whether it is empty. */
  required_fields: { key: string; label: string; missing: boolean }[]
  confirmation: boolean
  style: { color?: string; icon?: string } | null
  approval: { mode: string } | null
  on_behalf_of?: string
}

export interface ApprovalSummary {
  uuid: string
  status: 'pending' | 'approved' | 'rejected' | 'cancelled'
  mode: string
  required_count: number | null
  quorum_weight: number | null
  transition: string | null
  due_at: string | null
  decisions: {
    approver: { type: string; uuid: string | null; name: string }
    weight: number
    decision: 'pending' | 'approved' | 'rejected' | string
    decided_by: string | null
    on_behalf: boolean
    comment: string | null
    decided_at: string | null
  }[]
  can_decide: boolean
}

export interface WorkflowState {
  enabled: boolean
  status: StatusPayload | null
  transitions: TransitionOption[]
  approval: ApprovalSummary | null
  claim: { by: { uuid: string; name: string | null }; at: string } | null
  assignments: { uuid: string; assignee: { type: string; uuid: string | null; name: string }; approval: boolean; due_at: string | null; priority: number; mine: boolean }[]
  sla: { state: 'running' | 'warned' | 'breached'; due_at: string; started_at: string }[]
  can: { assign: boolean; reassign: boolean }
}

export interface JustificationView {
  uuid?: string
  restricted?: boolean
  reason_text?: string | null
  reason_code?: { code: string | null; label: string | null } | null
  note?: string | null
  by?: string | null
  at?: string
  attachments?: { uuid: string; name: string }[]
}

export interface HistoryItem {
  from: StatusPayload | null
  to: StatusPayload | null
  transition: { key: string; name: string } | null
  source: string
  comment: string | null
  attachments: { uuid: string; name: string; size: number; mime: string }[]
  by: string | null
  on_behalf_of: string | null
  at: string
  seconds_in_previous: number | null
  working_seconds_in_previous: number | null
  justification: JustificationView | null
}

export interface JustificationPrompt {
  level: 'not_required' | 'optional' | 'mandatory'
  title: string | null
  help: string | null
  text: { min: number | null; max: number; required: boolean }
  reason_codes: { mode: 'none' | 'optional' | 'required'; options: { uuid: string; code: string | null; label: string; requires_note: boolean }[]; collections: string[] }
  attachments: { mode: 'none' | 'optional' | 'required'; max: number; rules: { types?: string[]; maxSizeKb?: number } | null }
  changes: { field: string; label: string; old: unknown; new: unknown }[]
}

export interface JustificationPayload {
  reason_text: string | null
  reason_code: string | null
  note: string | null
  attachments: string[]
}

/** The justification prompt carried by a 422 `justification_required` / `justification_invalid` answer, if any. */
export function justificationPrompt(e: unknown): { prompt: JustificationPrompt; errors: Record<string, string> } | null {
  if (!(e instanceof ApiError) || e.status !== 422 || (e.code !== 'justification_required' && e.code !== 'justification_invalid')) return null
  const body = e.body as { justification?: JustificationPrompt }
  if (!body.justification) return null
  return { prompt: body.justification, errors: e.code === 'justification_invalid' ? e.fieldErrors : {} }
}

/** Duration in whole minutes, hours or days for timelines and SLA badges. */
export function durationParts(seconds: number): { n: number; unit: 'minutes' | 'hours' | 'days' } {
  const minutes = Math.max(0, Math.round(seconds / 60))
  if (minutes < 120) return { n: minutes, unit: 'minutes' }
  const hours = Math.round(minutes / 60)
  if (hours < 48) return { n: hours, unit: 'hours' }
  return { n: Math.round(hours / 24), unit: 'days' }
}
