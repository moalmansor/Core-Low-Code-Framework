import { get, http, send } from '@/api/http'
import type { Ast } from '@/builder/types'
import type { ActionStepDoc } from '@/views/admin/formconfig/api'

/** Automations (specification §4.31, ADR-0043). */
export type I18nMap = Record<string, string>
export type TriggerType =
  'record_created' | 'record_updated' | 'record_deleted' | 'field_changed' | 'status_changed' | 'condition_true' | 'schedule' | 'date_reached' | 'watched_folder' | 'inbound_webhook' | 'manual'
export type Frequency = 'hourly' | 'daily' | 'weekly' | 'monthly' | 'cron'
export type RunStatus = 'queued' | 'awaiting_confirmation' | 'running' | 'waiting' | 'succeeded' | 'failed' | 'partially_failed' | 'cancelled' | 'skipped_loop'

/** Triggers caused by someone's change to one record; the others need a run-as user. */
export const EVENT_TRIGGERS: TriggerType[] = ['record_created', 'record_updated', 'record_deleted', 'field_changed', 'status_changed', 'condition_true']
/** Triggers whose run covers every record that meets the condition. */
export const SET_TRIGGERS: TriggerType[] = ['schedule', 'manual']

export interface ScheduleDoc {
  frequency: Frequency
  time: string | null
  weekday: number | null
  day: number | null
  minute: number | null
  cron: string | null
  timezone: string
}
export interface TriggerDoc {
  uuid: string
  type: TriggerType
  field?: string | null
  fromStatus?: string | null
  toStatus?: string | null
  schedule?: ScheduleDoc | null
  dateField?: string | null
  offsetMinutes?: number | null
  folder?: string | null
  fileField?: string | null
  nameField?: string | null
  inboundEndpoint?: string | null
}
export interface AutomationDoc {
  uuid?: string
  key: string
  i18n: { name: I18nMap; description: I18nMap }
  enabled: boolean
  triggers: TriggerDoc[]
  condition: Ast | null
  steps: ActionStepDoc[]
  runAs: 'triggering_user' | 'specific_user'
  runAsUser: string | null
  concurrency: number
  retry: { maxAttempts: number; backoffSeconds: number[] }
  maxRecords: number
  confirmAbove: number | null
  maxChainDepth: number
}
export interface AutomationResult {
  automation: AutomationDoc
  hash: string
  form: string | null
  can_run: boolean
  run_as_name: string | null
}
export interface AutomationSummary {
  uuid: string
  key: string
  name: string
  enabled: boolean
  form: { uuid: string; name: string } | null
  triggers: TriggerType[]
  steps: number
  lastRun: { status: RunStatus; at: string } | null
}
export interface RunSummary {
  uuid: string
  status: RunStatus
  trigger: TriggerType
  test: boolean
  depth: number
  records: number
  affected: number
  by: string | null
  created_at: string
  started_at: string | null
  finished_at: string | null
  resume_at: string | null
  duration_ms: number | null
  error: string | null
}
export interface RunLogEntry {
  step: string | null
  type: string | null
  record: string | null
  status: 'succeeded' | 'failed' | 'skipped' | 'simulated' | 'waiting' | 'warning' | 'retrying'
  message: string | null
  ms: number
  at: string
}
export interface RunDetail extends RunSummary {
  automation: string | null
  form: string | null
  trigger_ref: { file?: string; due?: string; at?: string }
  record_list: { uuid: string; number: string | null }[]
  parent: { uuid: string; automation: string | null } | null
  log: RunLogEntry[]
}
export interface ScheduledTask {
  uuid: string
  kind: 'system' | 'automation' | 'download' | 'sync' | 'retention' | 'report' | 'reconciliation'
  name: string
  owner: { type: 'automation' | 'download_profile'; uuid: string; form: string } | null
  cron: string
  timezone: string
  enabled: boolean
  next_run_at: string | null
  last_run_at: string | null
  last_status: 'succeeded' | 'failed' | 'running' | 'skipped' | null
  last_duration_ms: number | null
  last_error: string | null
}

export const automationsApi = {
  all: () => get<{ data: AutomationSummary[] }>('/automations').then((r) => r.data),
  list: (form: string) => get<{ data: { automations: AutomationSummary[]; published: boolean } }>(`/forms/${form}/automations`).then((r) => r.data),
  create: (form: string, automation: AutomationDoc) => send<{ data: AutomationResult }>('post', `/forms/${form}/automations`, { automation }).then((r) => r.data),
  load: (uuid: string) => get<{ data: AutomationResult }>(`/automations/${uuid}`).then((r) => r.data),
  save: (uuid: string, automation: AutomationDoc, hash: string) => send<{ data: AutomationResult }>('put', `/automations/${uuid}`, { automation, base_hash: hash }).then((r) => r.data),
  enable: (uuid: string, enabled: boolean) => send<{ data: AutomationResult }>('post', `/automations/${uuid}/enable`, { enabled }).then((r) => r.data),
  remove: (uuid: string) => http.delete(`/automations/${uuid}`),
  test: (uuid: string, record: string) => send<{ data: RunDetail }>('post', `/automations/${uuid}/test`, { record }).then((r) => r.data),
  run: (uuid: string) => send<{ data: RunSummary }>('post', `/automations/${uuid}/run`).then((r) => r.data),
  runs: (uuid: string, page: number, status: RunStatus | null) =>
    get<{ data: { rows: RunSummary[]; total: number } }>(`/automations/${uuid}/runs`, { page, status: status ?? undefined }).then((r) => r.data),
  runDetail: (run: string) => get<{ data: RunDetail }>(`/automation-runs/${run}`).then((r) => r.data),
  confirm: (run: string) => send<{ data: RunDetail }>('post', `/automation-runs/${run}/confirm`).then((r) => r.data),
  cancel: (run: string) => send<{ data: RunDetail }>('post', `/automation-runs/${run}/cancel`).then((r) => r.data),
  retry: (run: string) => send<{ data: RunDetail }>('post', `/automation-runs/${run}/retry`).then((r) => r.data),
  scheduledTasks: () => get<{ data: ScheduledTask[] }>('/scheduled-tasks').then((r) => r.data),
}

/** How a run's status is shown. */
export const runSeverity = (s: RunStatus): 'success' | 'danger' | 'warn' | 'info' | 'secondary' =>
  ({
    succeeded: 'success',
    failed: 'danger',
    partially_failed: 'warn',
    skipped_loop: 'warn',
    awaiting_confirmation: 'warn',
    running: 'info',
    waiting: 'info',
    queued: 'secondary',
    cancelled: 'secondary',
  })[s] as 'success' | 'danger' | 'warn' | 'info' | 'secondary'
