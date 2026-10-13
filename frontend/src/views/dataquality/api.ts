import { get, send } from '@/api/http'
import type { JustificationPayload, JustificationPrompt } from '@/runtime/workflow/types'
import { documentApi } from '@/views/admin/formconfig/api'

/** Data quality (specification §4.35, ADR-0044): duplicate rules, sweeps, merging and repair. */
export type I18nMap = Record<string, string>
export type MatchMethod = 'exact' | 'normalized' | 'fuzzy'

export interface MatchFieldDoc {
  field: string
  method: MatchMethod
  threshold: number | null
}
export interface DuplicateRuleDoc {
  uuid: string
  i18n: { name: I18nMap; message: I18nMap }
  fields: MatchFieldDoc[]
  mode: 'all' | 'any'
  action: 'warn' | 'block'
  appliesOn: 'create' | 'update' | 'both'
  active: boolean
}

/** A record named where it is listed by itself (its number, else its first text value). */
export interface RecordRef {
  uuid: string
  number: string | null
  title: string
}

export type OperationStatus = 'queued' | 'running' | 'completed' | 'failed' | 'cancelled'
export interface SweepResult {
  uuid: string
  status: OperationStatus
  groups: RecordRef[][]
  hidden: number
  error: string | null
  finished_at: string | null
}

export type ScanType = 'orphan_scan' | 'validation_sweep'
export interface RepairFinding {
  record: string
  number: string | null
  title: string
  field: string | null
  label: string | null
  problem: 'broken_link' | 'invalid'
  message: string | null
  fixable: boolean
}
export interface RepairScan {
  uuid: string
  type: ScanType
  status: OperationStatus
  scanned: number | null
  findings: RepairFinding[]
  hidden: number
  /** More problems exist than one scan lists. */
  more: boolean
  error: string | null
  finished_at: string | null
}

export interface MergePreview {
  records: (RecordRef & { row_version: number })[]
  differ: string[]
  links: Record<string, number>
  justification: JustificationPrompt
}

/** The 409 answer to a save that looks like a duplicate. */
export interface DuplicateAnswer {
  message: string
  duplicates: RecordRef[]
  hidden: number
  blocking: boolean
  rules: string[]
}

export const duplicateRulesApi = documentApi<DuplicateRuleDoc[]>('duplicate-rules', 'rules')

export const dataQualityApi = {
  sweep: async (rule: string) => (await send<{ data: SweepResult }>('post', `/duplicate-rules/${rule}/sweep`)).data,
  sweepResult: async (operation: string) => (await get<{ data: SweepResult }>(`/data-operations/${operation}`)).data,
  repairState: async (form: string) => (await get<{ data: Record<ScanType, RepairScan | null> }>(`/forms/${form}/repair`)).data,
  scan: async (form: string, type: ScanType) => (await send<{ data: RepairScan }>('post', `/forms/${form}/repair`, { type })).data,
  scanResult: async (operation: string) => (await get<{ data: RepairScan }>(`/data-operations/${operation}`)).data,
  clearLinks: async (form: string, items: { record: string; field: string }[]) =>
    (await send<{ data: { fixed: number; failed: { record: string; field: string; message: string }[] } }>('post', `/forms/${form}/repair/clear-links`, { items })).data,
  mergePreview: async (form: string, records: string[]) => (await send<{ data: MergePreview }>('post', `/r/${form}/merge/preview`, { records })).data,
  merge: async (form: string, body: { survivor: string; merged: string[]; choices: Record<string, string>; versions: Record<string, number>; justification?: JustificationPayload | null }) =>
    (await send<{ data: { uuid: string; merged: number; repointed: Record<string, number> } }>('post', `/r/${form}/merge`, body)).data,
}

/** Whether an API error is the duplicate warning or block of a save. */
export function duplicateAnswer(body: unknown): DuplicateAnswer | null {
  const b = body as Partial<DuplicateAnswer> & { code?: string }
  return b && b.code === 'duplicate' && Array.isArray(b.duplicates) ? (b as DuplicateAnswer) : null
}

/** Shown for a record: its number and title, or whichever it has. */
export function recordName(r: { number: string | null; title: string }, fallback: string): string {
  if (r.number && r.title && r.title !== r.number) return `${r.number} · ${r.title}`
  return r.title || r.number || fallback
}
