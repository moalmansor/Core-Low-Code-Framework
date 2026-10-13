import { get, send } from '@/api/http'
import type { JustificationPayload, JustificationPrompt } from '../workflow/types'

/** Running a form's actions (specification §4.15). */
export type Placement = 'row' | 'bulk' | 'toolbar' | 'view_page'
export interface AvailableAction {
  uuid: string
  kind: 'builtin' | 'custom'
  builtin: 'duplicate' | 'print' | 'bulk_update' | 'bulk_status' | 'bulk_delete' | 'bulk_reassign' | null
  placements: Placement[]
  label: string
  confirm: boolean
  confirmation: string | null
  icon: string | null
  color: string | null
  conditional: boolean
}
export interface ActionTarget {
  items?: { uuid: string; row_version?: number | null }[]
  filters?: Record<string, unknown>
}
export interface BulkField {
  key: string
  type: string
  label: string
  options: { value: string; label: string }[]
}
export interface Preparation {
  count: number
  queued: boolean
  justification: (JustificationPrompt & { count: number }) | null
  inputs: { fields?: BulkField[]; transitions?: { uuid: string; label: string }[]; assignees?: ('user' | 'role' | 'department')[] } | null
  /** The first records the run covers. */
  preview: { uuid: string; title: string }[]
  /** Whether the run can be tried first without keeping anything (the bulk built-ins). */
  dry_run: boolean
}
/** What a trial run found: how many records would change and why the others would not. */
export interface DryRun {
  count: number
  tried: number
  would_succeed: number
  would_fail: number
  errors: { uuid: string; title: string; error: string }[]
}
export interface RunOutput {
  type: 'download' | 'open' | 'warning'
  file?: string
  name?: string
  url?: string
  message?: string
}
export interface ActionRun {
  uuid: string
  status: 'queued' | 'running' | 'completed' | 'completed_with_errors' | 'failed' | 'cancelled'
  progress: number
  count: number
  affected: number
  failed: number
  errors: { uuid: string; error: string }[]
  outputs: RunOutput[]
  error: string | null
  message: string | null
}

export const actionsRuntimeApi = {
  list: (form: string, placement: Placement, record?: string) => get<{ data: AvailableAction[] }>(`/r/${form}/actions`, { placement, ...(record ? { record } : {}) }).then((r) => r.data),
  prepare: (form: string, action: string, target: ActionTarget, inputs: Record<string, unknown> = {}) =>
    send<{ data: Preparation }>('post', `/r/${form}/actions/${action}/prepare`, { ...target, inputs }).then((r) => r.data),
  run: (form: string, action: string, target: ActionTarget, inputs: Record<string, unknown>, justification: JustificationPayload | null) =>
    send<{ data: ActionRun }>('post', `/r/${form}/actions/${action}/run`, { ...target, inputs, ...(justification ? { justification } : {}) }).then((r) => r.data),
  dryRun: (form: string, action: string, target: ActionTarget, inputs: Record<string, unknown>) =>
    send<{ data: DryRun }>('post', `/r/${form}/actions/${action}/dry-run`, { ...target, inputs }).then((r) => r.data),
  status: (run: string) => get<{ data: ActionRun }>(`/action-runs/${run}`).then((r) => r.data),
  cancel: (run: string) => send<{ data: ActionRun }>('post', `/action-runs/${run}/cancel`).then((r) => r.data),
}

export const finished = (r: ActionRun) => ['completed', 'completed_with_errors', 'failed', 'cancelled'].includes(r.status)
