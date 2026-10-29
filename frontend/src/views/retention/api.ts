import { get, send } from '@/api/http'

/** Retention, legal holds, personal data and storage (specification §4.26). */
export type DataClass = 'records' | 'audit_logs' | 'error_logs' | 'email_logs' | 'notification_logs' | 'submission_journal' | 'download_files' | 'attachments' | 'recycle_bin'
export type EndAction = 'archive' | 'export_then_delete' | 'delete'
export type DateBasis = 'created_at' | 'updated_at' | 'final_status_at'
export type RunStatus = 'running' | 'succeeded' | 'failed' | 'partially_failed'

export interface PolicyDoc {
  uuid?: string
  name: Record<string, string>
  data_class: DataClass
  form: string | null
  application: string | null
  retention_days: number
  date_basis: DateBasis
  end_action: EndAction
  archive_disk: string | null
  schedule: { cron: string; timezone: string } | null
  active: boolean
  hash?: string
  label?: string
  next_run_at?: string | null
  last_run?: { status: RunStatus; started_at: string | null; finished_at: string | null } | null
}

export interface PolicyOptions {
  data_classes: DataClass[]
  end_actions: EndAction[]
  date_bases: DateBasis[]
  archive_disks: string[]
  forms: { uuid: string; label: string }[]
  applications: { uuid: string; label: string }[]
}

export interface RetentionRun {
  uuid: string
  policy: { uuid: string; name: string; data_class: DataClass } | null
  status: RunStatus
  scanned: number
  archived: number
  deleted: number
  skipped_legal_hold: number
  error: string | null
  has_export: boolean
  started_at: string | null
  finished_at: string | null
  triggered_by: string | null
}

export interface ArchiveEntry {
  id: number
  source: string
  record_id: number
  form: { uuid: string; label: string } | null
  archived_at: string | null
  restored_at: string | null
  restorable: boolean
}

export interface LegalHold {
  uuid: string
  form: { uuid: string; label: string } | null
  record: { uuid: string; number: string | null } | null
  condition: Record<string, unknown> | null
  reason: string
  placed_at: string | null
  placed_by: string | null
  lifted_at: string | null
  lifted_by: string | null
  lift_reason: string | null
}

export type RequestType = 'locate' | 'export' | 'delete' | 'anonymize'
export type RequestStatus = 'received' | 'locating' | 'awaiting_review' | 'executing' | 'completed' | 'rejected' | 'failed'
export interface Subject {
  email?: string
  identifier?: string
  name?: string
  user?: string
}
export interface Findings {
  user: { uuid: string; name: string; email: string; status: string; anonymized: boolean } | null
  forms: { uuid: string; key: string; name: string; records: number[]; fields: string[]; owned: number[]; held: number; unsearchable: string[] }[]
  logs: Record<'audit_logs' | 'email_queue' | 'in_app_notifications' | 'error_logs' | 'submission_journal', number>
  located_at: string
  result?: { skipped_legal_hold: number; records: number }
}
export interface PersonalRequest {
  uuid: string
  type: RequestType
  status: RequestStatus
  created_at: string | null
  created_by: string | null
  completed_at: string | null
  completed_by: string | null
  reason: string | null
  has_file: boolean
  subject?: Subject
  findings?: Findings | null
}

export interface StorageOverview {
  partitions: { table: string; months: { from: string | null; to: string; rows: number }[] }[]
  tables: { table: string; bytes: number }[]
  volume: { total: number | null; free: number | null; used_percent: number | null }
}
export interface QuotaRow {
  uuid?: string
  scope: 'organization' | 'application' | 'form'
  target: string | null
  label?: string | null
  warn_mb: number
  limit_mb: number
  used_bytes?: number
  state?: 'ok' | 'warning' | 'exceeded'
  measured_at?: string | null
}
export interface UsageReport {
  forms: { uuid: string; key: string; label: string; files: number; bytes: number }[]
  users: { uuid: string; name: string; files: number; bytes: number }[]
  total: number
}

export const retentionApi = {
  options: () => get<{ data: PolicyOptions }>('/retention/options').then((r) => r.data),
  list: () => get<{ data: PolicyDoc[] }>('/retention/policies').then((r) => r.data),
  load: (uuid: string) => get<{ data: PolicyDoc }>(`/retention/policies/${uuid}`).then((r) => r.data),
  create: (policy: PolicyDoc) => send<{ data: PolicyDoc }>('post', '/retention/policies', { policy }).then((r) => r.data),
  save: (uuid: string, policy: PolicyDoc, baseHash: string) => send<{ data: PolicyDoc }>('put', `/retention/policies/${uuid}`, { policy, base_hash: baseHash }).then((r) => r.data),
  remove: (uuid: string) => send('delete', `/retention/policies/${uuid}`),
  run: (uuid: string) => send('post', `/retention/policies/${uuid}/run`),
  runs: (policy?: string) => get<{ data: RetentionRun[] }>('/retention/runs', policy ? { policy } : undefined).then((r) => r.data),
  exportUrl: (run: string) => `/api/v1/retention/runs/${run}/export`,
  archive: (page = 1) => get<{ data: ArchiveEntry[]; meta: { total: number; page: number; last_page: number } }>('/retention/archive', { page }),
  restore: (id: number) => send('post', `/retention/archive/${id}/restore`),
  storage: () => get<{ data: StorageOverview }>('/retention/storage').then((r) => r.data),
  quotas: () => get<{ data: { quotas: QuotaRow[]; hash: string } }>('/retention/quotas').then((r) => r.data),
  saveQuotas: (quotas: QuotaRow[], baseHash: string) => send<{ data: { quotas: QuotaRow[]; hash: string } }>('put', '/retention/quotas', { quotas, base_hash: baseHash }).then((r) => r.data),
  usage: () => get<{ data: UsageReport }>('/retention/usage').then((r) => r.data),
}

export const holdsApi = {
  list: () => get<{ data: LegalHold[] }>('/legal-holds').then((r) => r.data),
  forms: () => get<{ data: { uuid: string; label: string }[] }>('/legal-holds/forms').then((r) => r.data),
  place: (body: { form: string; record?: string | null; condition?: Record<string, unknown> | null; reason: string }) =>
    send<{ data: { uuid: string; held: number } }>('post', '/legal-holds', body).then((r) => r.data),
  lift: (uuid: string, reason: string) => send('post', `/legal-holds/${uuid}/lift`, { reason }),
}

export const personalApi = {
  list: () => get<{ data: PersonalRequest[] }>('/personal-data-requests').then((r) => r.data),
  load: (uuid: string) => get<{ data: PersonalRequest }>(`/personal-data-requests/${uuid}`).then((r) => r.data),
  create: (type: RequestType, subject: Subject) => send<{ data: PersonalRequest }>('post', '/personal-data-requests', { type, subject }).then((r) => r.data),
  locate: (uuid: string) => send<{ data: PersonalRequest }>('post', `/personal-data-requests/${uuid}/locate`).then((r) => r.data),
  execute: (uuid: string, reason: string, code: string | null) =>
    send<{ data: PersonalRequest }>('post', `/personal-data-requests/${uuid}/execute`, { reason, ...(code ? { confirmation_code: code } : {}) }).then((r) => r.data),
  reject: (uuid: string, reason: string) => send<{ data: PersonalRequest }>('post', `/personal-data-requests/${uuid}/reject`, { reason }).then((r) => r.data),
  fileUrl: (uuid: string) => `/api/v1/personal-data-requests/${uuid}/file`,
}

/** Bytes as the user reads them (KB, MB, GB), in their own digits. */
export function formatBytes(bytes: number, locale: string): string {
  const units = ['B', 'KB', 'MB', 'GB', 'TB']
  let v = bytes
  let i = 0
  while (v >= 1024 && i < units.length - 1) {
    v /= 1024
    i++
  }
  return `${new Intl.NumberFormat(locale, { maximumFractionDigits: i === 0 ? 0 : 1 }).format(v)} ${units[i]}`
}

export const severityOf = (s: string) =>
  s === 'succeeded' || s === 'completed' || s === 'ok'
    ? 'success'
    : s === 'failed' || s === 'exceeded'
      ? 'danger'
      : s === 'partially_failed' || s === 'warning' || s === 'awaiting_review'
        ? 'warn'
        : 'secondary'
