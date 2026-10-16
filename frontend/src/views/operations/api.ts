import { get, send } from '@/api/http'

/** The Operations Center (specification §4.2, ADR-0045). */
export type CheckState = 'ok' | 'failing' | 'disabled' | 'not_configured' | 'not_used'
export interface OpsHealth {
  checks: Record<'database' | 'cache' | 'redis' | 'smtp' | 'virus_scanner', CheckState>
  queues: Record<'default' | 'heavy' | 'mail' | 'schema', number | null>
  stuck_emails: number
  failed_emails: number
  failed_submissions: number
  failed_jobs: number
  scheduler_last_run_at: string | null
  storage: { total: number | null; free: number | null; used_percent: number | null }
  pending_outbox_events: number
  open_error_groups: number
}

export type EmailStatus = 'pending' | 'sending' | 'sent' | 'failed' | 'cancelled' | 'stuck'
export interface OpsEmail {
  uuid: string
  status: EmailStatus
  raw_status: Exclude<EmailStatus, 'stuck'>
  to: string[]
  cc: string[]
  bcc: string[]
  subject: string
  source: string
  form: { uuid: string; name: string } | null
  record: string | null
  rule: string | null
  attempts: number
  max_attempts: number
  error: string | null
  created_at: string | null
  sent_at: string | null
  next_attempt_at: string | null
  resent: boolean
}

export interface OpsSubmission {
  uuid: string
  status: 'failed' | 'rejected' | 'discarded' | 'processed' | 'processing'
  operation: 'create' | 'update' | 'delete' | 'restore' | 'transition' | 'action'
  source: string
  attempts: number
  form: { uuid: string; name: string } | null
  version: number | null
  record: string | null
  user: { name: string; email: string } | null
  error: string | null
  correlation_id: string
  created_at: string | null
  updated_at: string | null
  edited: boolean
  discard_reason: string | null
  retryable: boolean
  payload?: Record<string, unknown> | null
  trace?: string | null
}

export type JobKind = 'import' | 'export' | 'download' | 'action' | 'automation' | 'scheduled_task' | 'webhook' | 'queue_job'
export interface OpsJob {
  key: string
  kind: JobKind
  id: number
  form: { id: number; uuid: string; name: string } | null
  subject: string | null
  user: string | null
  error: string | null
  failed_at: string | null
  retryable: boolean
  attempts: number
  detail?: string | null
}

export type Metric = 'failed_submissions' | 'failed_jobs' | 'failed_emails' | 'stuck_emails' | 'failed_webhooks' | 'queue_size' | 'error_rate' | 'storage_usage' | 'scheduler_lag'
export interface AlertRuleDoc {
  uuid: string
  metric: Metric
  threshold: number
  window: number
  roles: string[]
  cooldown: number
  active: boolean
  last_triggered_at?: string | null
}
export interface AlertRules {
  rules: AlertRuleDoc[]
  hash: string
  metrics: Metric[]
  instant: Metric[]
  values: Record<string, number>
}

type Page<T> = { data: T[]; meta: { total: number } }
const clean = (p: Record<string, unknown>) => Object.fromEntries(Object.entries(p).filter(([, v]) => v !== null && v !== undefined && v !== ''))

export const opsApi = {
  health: async () => (await get<{ data: OpsHealth }>('/ops/health')).data,
  emails: (p: { status?: EmailStatus | null; search?: string; page: number }) => get<Page<OpsEmail>>('/ops/emails', clean(p)),
  emailHtml: async (uuid: string) => (await get<{ data: { url: string } }>(`/ops/emails/${uuid}/html`)).data.url,
  resend: async (emails: string[], recipients?: { to: string[]; cc: string[]; bcc: string[] }) =>
    (await send<{ data: { resent: string[] } }>('post', '/ops/emails/resend', { emails, ...(recipients ? { recipients } : {}) })).data.resent,
  cancel: async (emails: string[]) => (await send<{ data: { cancelled: number } }>('post', '/ops/emails/cancel', { emails })).data.cancelled,
  submissions: (p: { status?: string | null; page: number }) => get<Page<OpsSubmission>>('/ops/submissions', clean(p)),
  submission: async (uuid: string) => (await get<{ data: OpsSubmission }>(`/ops/submissions/${uuid}`)).data,
  editSubmission: async (uuid: string, payload: Record<string, unknown>) => (await send<{ data: OpsSubmission }>('patch', `/ops/submissions/${uuid}/payload`, { payload })).data,
  retrySubmissions: async (submissions: string[]) =>
    (await send<{ data: { results: { uuid: string; ok: boolean; error: string | null }[]; succeeded: number } }>('post', '/ops/submissions/retry', { submissions })).data,
  discardSubmission: async (uuid: string, reason: string) => (await send<{ data: OpsSubmission }>('post', `/ops/submissions/${uuid}/discard`, { reason })).data,
  jobs: (p: { kind?: JobKind | null; page: number }) => get<Page<OpsJob>>('/ops/jobs', clean(p)),
  job: async (kind: JobKind, id: number) => (await get<{ data: OpsJob }>(`/ops/jobs/${kind}/${id}`)).data,
  retryJob: (kind: JobKind, id: number) => send('post', `/ops/jobs/${kind}/${id}/retry`),
  dismissJob: (kind: JobKind, id: number, reason: string) => send('post', `/ops/jobs/${kind}/${id}/discard`, { reason }),
  alertRules: async () => (await get<{ data: AlertRules }>('/ops/alert-rules')).data,
  saveAlertRules: async (rules: AlertRuleDoc[], hash: string) => (await send<{ data: AlertRules }>('put', '/ops/alert-rules', { rules, base_hash: hash })).data,
}
