import { get, http, send } from '@/api/http'
import type { Ast } from '@/builder/types'

/** Custom downloads (specification §4.23). */
export type I18nMap = Record<string, string>
export type DlFormat = 'xlsx' | 'csv' | 'pdf'
export type DlPlace = 'table_toolbar' | 'record_view'
export type ColumnKind = 'field' | 'calculated' | 'static' | 'system'
export type ToMany = 'flatten' | 'aggregate' | 'separate_sheet'
export type Aggregate = 'count' | 'sum' | 'avg' | 'min' | 'max' | 'first' | 'last' | 'join'
export type Operator = 'eq' | 'neq' | 'gt' | 'gte' | 'lt' | 'lte' | 'contains' | 'starts_with' | 'in' | 'between' | 'empty' | 'not_empty'
export type ParamType = 'text' | 'number' | 'date' | 'date_range' | 'choice' | 'user' | 'department'
export type FormatType = 'text' | 'date' | 'datetime' | 'number' | 'currency' | 'percent'

export interface ColumnFormat {
  type: FormatType
  decimals: number | null
  currency: string | null
  digits: 'latin' | 'arabic'
}
export interface ColumnDoc {
  uuid: string
  kind: ColumnKind
  path: string[] | null
  expression: Ast | null
  staticValue: string | null
  toMany: ToMany | null
  aggregate: Aggregate | null
  separator: string | null
  sheet: string | null
  width: number | null
  format: ColumnFormat | null
  i18n: { header: I18nMap }
}
export interface FilterDoc {
  uuid: string
  path: string[]
  operator: Operator
  value: unknown
  valueExpression: Ast | null
  parameter: string | null
}
export interface ParameterDoc {
  key: string
  type: ParamType
  required: boolean
  default: string | number | null
  options: { value: string; label: I18nMap }[]
  i18n: { label: I18nMap }
}
export interface ProfileDoc {
  uuid?: string
  key: string
  personal?: boolean
  i18n: { name: I18nMap; description: I18nMap }
  formats: DlFormat[]
  sheetMode?: 'single' | 'per_related_form'
  fileName: string
  xlsx: { styledHeader: boolean; freezeHeader: boolean; titleRow: boolean; logo: boolean }
  csv: { delimiter: string; bom: boolean }
  pdf: { orientation: 'portrait' | 'landscape'; pageNumbers: boolean; header: I18nMap; footer: I18nMap }
  applyUserFilters: boolean
  allowSelected: boolean
  availableIn: DlPlace[]
  parameters: ParameterDoc[]
  maxRows: number
  active: boolean
  columns: ColumnDoc[]
  filters: FilterDoc[]
}
export interface ColumnInfo {
  label: string
  many: boolean
  broken: boolean
}
export interface ProfileResult {
  profile: ProfileDoc
  hash: string
  form: string
  personal: boolean
  mine: boolean
  columns: Record<string, ColumnInfo>
}
export interface ProfileSummary {
  uuid: string
  key: string
  personal: boolean
  mine: boolean
  name: string
  formats: DlFormat[]
  active: boolean
  columns: number
  schedules: number
}
export interface TreeNode {
  kind: 'field' | 'link' | 'reverse' | 'repeater' | 'system'
  path: string[]
  label: string
  type?: string
  form?: string
  toMany: boolean
  sensitive?: boolean
}
export type Row = (string | number | boolean | null)[]
export interface PreviewResult {
  headers: Record<string, string[]>
  rows: Record<string, Row[]>
  omitted: string[]
  sheets: Record<string, string>
}
export interface ScheduleDoc {
  uuid?: string
  frequency: 'daily' | 'weekly' | 'monthly' | 'cron'
  time: string | null
  weekday: number | null
  day: number | null
  cron: string | null
  timezone: string
  format: DlFormat
  parameters: Record<string, unknown>
  recipients: { users: string[]; roles: string[] }
  active: boolean
  next_run_at?: string | null
  last_run_at?: string | null
  last_status?: string | null
}
export interface AvailableProfile {
  uuid: string
  personal: boolean
  name: string
  description: string | null
  formats: DlFormat[]
  applyUserFilters: boolean
  allowSelected: boolean
  parameters: { key: string; type: ParamType; required: boolean; default: string | number | null; label: string; options: { value: string; label: string }[] }[]
}
export interface DownloadJob {
  uuid: string
  status: 'queued' | 'running' | 'completed' | 'failed' | 'expired' | 'cancelled'
  progress: number
  format: DlFormat
  rows: number | null
  error: string | null
  file: string | null
  expires_at: string | null
  omitted: string[]
}

const encodePath = (path: string[]) => JSON.stringify(path)

export const downloadsApi = {
  list: (form: string) => get<{ data: { profiles: ProfileSummary[]; published: boolean; can_manage: boolean; can_personal: boolean } }>(`/forms/${form}/download-profiles`).then((r) => r.data),
  create: (form: string, profile: ProfileDoc, personal: boolean) => send<{ data: ProfileResult }>('post', `/forms/${form}/download-profiles`, { profile, personal }).then((r) => r.data),
  load: (uuid: string) => get<{ data: ProfileResult }>(`/download-profiles/${uuid}`).then((r) => r.data),
  save: (uuid: string, profile: ProfileDoc, hash: string) => send<{ data: ProfileResult }>('put', `/download-profiles/${uuid}`, { profile, base_hash: hash }).then((r) => r.data),
  remove: (uuid: string) => http.delete(`/download-profiles/${uuid}`),
  duplicate: (uuid: string) => send<{ data: ProfileResult }>('post', `/download-profiles/${uuid}/duplicate`).then((r) => r.data),
  tree: (form: string, path: string[], personal: boolean) =>
    get<{ data: TreeNode[]; max_depth: number }>(`/forms/${form}/download-tree`, { path: path.length ? encodePath(path) : undefined, personal: personal ? 1 : undefined }),
  preview: (form: string, profile: ProfileDoc, personal: boolean, parameters: Record<string, unknown>) =>
    send<{ data: PreviewResult }>('post', `/forms/${form}/download-preview`, { profile, personal, parameters }).then((r) => r.data),
  effective: (form: string, profile: ProfileDoc, role: string) =>
    send<{ data: { columns: string[]; omitted: string[] } }>('post', `/forms/${form}/download-effective`, { profile, role }).then((r) => r.data),
  schedules: (profile: string) => get<{ data: ScheduleDoc[] }>(`/download-profiles/${profile}/schedules`).then((r) => r.data),
  createSchedule: (profile: string, schedule: ScheduleDoc) => send<{ data: ScheduleDoc }>('post', `/download-profiles/${profile}/schedules`, { schedule }).then((r) => r.data),
  saveSchedule: (uuid: string, schedule: ScheduleDoc) => send<{ data: ScheduleDoc }>('put', `/download-schedules/${uuid}`, { schedule }).then((r) => r.data),
  removeSchedule: (uuid: string) => http.delete(`/download-schedules/${uuid}`),
  // Running.
  available: (form: string, place: DlPlace) => get<{ data: AvailableProfile[] }>(`/r/${form}/downloads`, { place }).then((r) => r.data),
  run: (form: string, profile: string, body: { format: DlFormat; parameters: Record<string, unknown>; filters?: Record<string, unknown> | null; uuids?: string[]; record?: string }) =>
    send<{ data: DownloadJob }>('post', `/r/${form}/downloads/${profile}`, body).then((r) => r.data),
  status: (job: string) => get<{ data: DownloadJob }>(`/download-jobs/${job}`).then((r) => r.data),
  cancel: (job: string) => send<{ data: DownloadJob }>('post', `/download-jobs/${job}/cancel`).then((r) => r.data),
  retry: (job: string) => send<{ data: DownloadJob }>('post', `/download-jobs/${job}/retry`).then((r) => r.data),
}

export const pathKey = (path: string[] | null) => JSON.stringify(path ?? [])
export const finishedJob = (j: DownloadJob) => ['completed', 'failed', 'expired', 'cancelled'].includes(j.status)
