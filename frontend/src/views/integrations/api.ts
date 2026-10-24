import { get, send } from '@/api/http'

/** API tokens, webhooks and inbound endpoints (specification §4.22, §4.33). */
export type I18nMap = Record<string, string>

export interface ApiToken {
  uuid: string
  name: string
  abilities: string[]
  ip_allowlist: string[]
  rate_limit_per_minute: number | null
  created_at: string
  last_used_at: string | null
  expires_at: string | null
  revoked_at: string | null
  active: boolean
  owner?: string
}
export interface Grantable {
  form: string
  key: string
  name: string
  read: boolean
  write: boolean
}
export interface NewToken {
  name: string
  abilities: string[]
  expires_in_days: number | null
  ip_allowlist: string[]
  rate_limit_per_minute: number | null
}

export const tokensApi = {
  async mine(): Promise<{ rows: ApiToken[]; grantable: Grantable[]; maxDays: number; enabled: boolean }> {
    const r = await get<{ data: ApiToken[]; meta: { grantable: Grantable[]; max_days: number; enabled: boolean } }>('/me/tokens')
    return { rows: r.data, grantable: r.meta.grantable, maxDays: r.meta.max_days, enabled: r.meta.enabled }
  },
  async create(t: NewToken): Promise<{ token: ApiToken; secret: string }> {
    const r = await send<{ data: ApiToken & { secret: string } }>('post', '/me/tokens', t)
    return { token: r.data, secret: r.data.secret }
  },
  revokeMine: (uuid: string) => send<void>('delete', `/me/tokens/${uuid}`),
  all: async () => (await get<{ data: ApiToken[] }>('/api-tokens')).data,
  revoke: (uuid: string) => send<void>('delete', `/api-tokens/${uuid}`),
}

export type WebhookEvent = 'record.created' | 'record.updated' | 'record.deleted' | 'record.status_changed'
export const WEBHOOK_EVENTS: WebhookEvent[] = ['record.created', 'record.updated', 'record.deleted', 'record.status_changed']
export type HttpMethod = 'POST' | 'PUT' | 'PATCH' | 'DELETE' | 'GET'
export interface WebhookDoc {
  uuid?: string
  key: string
  name: I18nMap
  label?: string
  form: string | null
  form_name?: string | null
  url: string
  method: HttpMethod
  events: WebhookEvent[]
  headers: { name: string; value: string }[]
  header_names?: string[]
  timeout_seconds: number
  max_retries: number
  is_active: boolean
  last_day?: Partial<Record<DeliveryStatus, number>>
}
export type DeliveryStatus = 'pending' | 'succeeded' | 'failed' | 'retrying' | 'blocked' | 'signature_invalid'
export interface Delivery {
  uuid: string
  created_at: string
  event?: string | null
  record: string | null
  operation?: string | null
  status: DeliveryStatus
  attempt?: number
  response_status: number | null
  duration_ms?: number | null
  error: string | null
  next_attempt_at?: string | null
  ip?: string | null
}
export interface DeliveryDetail extends Delivery {
  request_headers: Record<string, string>
  request_body: string | null
  response_headers: Record<string, string>
  response_body: string | null
}
export interface Page<T> {
  rows: T[]
  total: number
}

export const webhooksApi = {
  list: async () => (await get<{ data: WebhookDoc[] }>('/webhooks')).data,
  get: async (uuid: string) => (await get<{ data: WebhookDoc }>(`/webhooks/${uuid}`)).data,
  create: async (d: WebhookDoc) => (await send<{ data: WebhookDoc & { secret: string } }>('post', '/webhooks', d)).data,
  update: async (uuid: string, d: WebhookDoc) => (await send<{ data: WebhookDoc }>('patch', `/webhooks/${uuid}`, d)).data,
  remove: (uuid: string) => send<void>('delete', `/webhooks/${uuid}`),
  rotate: async (uuid: string) => (await send<{ data: { secret: string } }>('post', `/webhooks/${uuid}/secret`)).data.secret,
  test: async (uuid: string) => (await send<{ data: DeliveryDetail }>('post', `/webhooks/${uuid}/test`)).data,
  async deliveries(uuid: string, page: number, status: DeliveryStatus | null): Promise<Page<Delivery>> {
    const r = await get<{ data: Delivery[]; meta: { total: number } }>(`/webhooks/${uuid}/deliveries`, { page, status: status ?? undefined })
    return { rows: r.data, total: r.meta.total }
  },
  delivery: async (uuid: string) => (await get<{ data: DeliveryDetail }>(`/webhook-deliveries/${uuid}`)).data,
  retry: async (uuid: string) => (await send<{ data: Delivery }>('post', `/webhook-deliveries/${uuid}/retry`)).data,
}

export type InboundKind = 'api' | 'webhook'
export type InboundOperation = 'create' | 'update' | 'upsert'
export interface InboundDoc {
  uuid?: string
  slug: string
  kind: InboundKind
  operation: InboundOperation
  url?: string
  form: string | null
  form_name?: string | null
  match_field: string | null
  mapping: Record<string, string>
  signature_header: string | null
  signature_algorithm: 'hmac_sha256' | 'hmac_sha512' | null
  run_as_user: string | null
  run_as_user_name?: string | null
  is_active: boolean
  last_day?: Partial<Record<DeliveryStatus, number>>
}

export const inboundApi = {
  list: async (form?: string) => (await get<{ data: InboundDoc[] }>('/inbound-endpoints', form ? { form } : undefined)).data,
  get: async (uuid: string) => (await get<{ data: InboundDoc }>(`/inbound-endpoints/${uuid}`)).data,
  create: async (d: InboundDoc) => (await send<{ data: InboundDoc & { secret?: string } }>('post', '/inbound-endpoints', d)).data,
  update: async (uuid: string, d: InboundDoc) => (await send<{ data: InboundDoc & { secret?: string } }>('patch', `/inbound-endpoints/${uuid}`, d)).data,
  remove: (uuid: string) => send<void>('delete', `/inbound-endpoints/${uuid}`),
  rotate: async (uuid: string) => (await send<{ data: { secret: string } }>('post', `/inbound-endpoints/${uuid}/secret`)).data.secret,
  async deliveries(uuid: string, page: number, status: DeliveryStatus | null): Promise<Page<Delivery>> {
    const r = await get<{ data: Delivery[]; meta: { total: number } }>(`/inbound-endpoints/${uuid}/deliveries`, { page, status: status ?? undefined })
    return { rows: r.data, total: r.meta.total }
  },
}

/** A delivery status as a tag colour. */
export function statusSeverity(s: DeliveryStatus): 'success' | 'danger' | 'warn' | 'secondary' {
  return s === 'succeeded' ? 'success' : s === 'failed' || s === 'blocked' || s === 'signature_invalid' ? 'danger' : s === 'retrying' ? 'warn' : 'secondary'
}

export interface MappableField {
  uuid: string
  key: string
  label: string
  type: string
  matchable: boolean
  rows: boolean
}
export const inboundFieldsApi = {
  list: async (form: string) => (await get<{ data: MappableField[] }>('/inbound-endpoints/fields', { form })).data,
}

/** External data sources (specification §4.33, ADR-0057). */
export type SourceType = 'rest' | 'db_view'
export type SourceAuth = 'none' | 'api_key' | 'bearer' | 'basic' | 'oauth2_client_credentials'
export type CachePolicy = 'none' | 'ttl' | 'stale_while_revalidate'
export interface SourceColumn {
  key: string
  path: string
  label: Record<string, string>
}
export interface SourceDoc {
  uuid?: string
  key: string
  type: SourceType
  name: Record<string, string>
  rest_config: { url: string; method: 'GET' | 'POST'; query: { name: string; value: string }[]; body: string | null; records: string } | null
  db_connection: string | null
  db_view: string | null
  auth_type: SourceAuth
  credentials: Record<string, string | boolean | null>
  headers: { name: string; value: string | null; set?: boolean }[]
  response_mapping: { value: string; label: string; parent: string | null; columns: SourceColumn[] }
  cache_policy: CachePolicy
  cache_ttl_seconds: number | null
  active: boolean
  hash?: string
  label?: string
  used_by?: string[]
}
export interface SourceSummary {
  uuid: string
  key: string
  type: SourceType
  label: string
  active: boolean
  last_tested_at: string | null
  last_test_status: 'ok' | 'failed' | null
  target: string | null
}
export interface SourceTest {
  ok: boolean
  error: string | null
  count: number
  rows: { value: string; label: string; parent: string | null; cells: Record<string, unknown> }[]
  sample: unknown
}
export interface SourceOption {
  uuid: string
  key: string
  label: string
  cascades: boolean
  columns: { key: string; label: string | null }[]
}

export const sourcesApi = {
  list: () =>
    get<{ data: SourceSummary[]; meta: { connections: string[]; auth_fields: Record<SourceAuth, Record<string, boolean>>; secrets: Partial<Record<SourceAuth, string[]>> } }>('/data-sources'),
  options: () => get<{ data: SourceOption[] }>('/data-sources/options').then((r) => r.data),
  load: (uuid: string) => get<{ data: SourceDoc }>(`/data-sources/${uuid}`).then((r) => r.data),
  create: (source: SourceDoc) => send<{ data: SourceDoc }>('post', '/data-sources', { source }).then((r) => r.data),
  save: (uuid: string, source: SourceDoc, baseHash: string) => send<{ data: SourceDoc }>('put', `/data-sources/${uuid}`, { source, base_hash: baseHash }).then((r) => r.data),
  remove: (uuid: string) => send('delete', `/data-sources/${uuid}`),
  test: (uuid: string) => send<{ data: SourceTest }>('post', `/data-sources/${uuid}/test`).then((r) => r.data),
}

/** Sync jobs (specification §4.33, ADR-0057). */
export type SyncDirection = 'import' | 'export'
export type ConflictRule = 'source_wins' | 'target_wins' | 'newest_wins' | 'skip'
export interface SyncRun {
  uuid: string
  status: 'queued' | 'running' | 'succeeded' | 'failed' | 'partially_failed'
  dry_run: boolean
  created: number
  updated: number
  skipped: number
  errors: number
  error: string | null
  has_log: boolean
  started_at: string | null
  finished_at: string | null
  created_at: string | null
  triggered_by: string | null
}
export interface SyncJobDoc {
  uuid?: string
  name: Record<string, string>
  direction: SyncDirection
  source: string | null
  form: string | null
  run_as: string | null
  fields: { field: string; from?: string; to?: string }[]
  export: { url: string; method: 'POST' | 'PUT' } | null
  match: string[]
  updated_from: string | null
  conflict_rule: ConflictRule
  active: boolean
  schedule: { cron: string; timezone: string } | null
  hash?: string
  label?: string
  run_as_name?: string | null
  next_run_at?: string | null
}
export interface SyncJobSummary {
  uuid: string
  label: string
  direction: SyncDirection
  active: boolean
  source_name: string | null
  form_name: string | null
  scheduled: boolean
  last_run: SyncRun | null
}

export const syncApi = {
  list: () => get<{ data: SyncJobSummary[] }>('/sync-jobs').then((r) => r.data),
  load: (uuid: string) => get<{ data: SyncJobDoc }>(`/sync-jobs/${uuid}`).then((r) => r.data),
  create: (job: SyncJobDoc) => send<{ data: SyncJobDoc }>('post', '/sync-jobs', { job }).then((r) => r.data),
  save: (uuid: string, job: SyncJobDoc, baseHash: string) => send<{ data: SyncJobDoc }>('put', `/sync-jobs/${uuid}`, { job, base_hash: baseHash }).then((r) => r.data),
  remove: (uuid: string) => send('delete', `/sync-jobs/${uuid}`),
  run: (uuid: string, dryRun: boolean) => send<{ data: SyncRun }>('post', `/sync-jobs/${uuid}/run`, { dry_run: dryRun }).then((r) => r.data),
  runs: (uuid: string) => get<{ data: SyncRun[] }>(`/sync-jobs/${uuid}/runs`).then((r) => r.data),
  logUrl: (uuid: string, run: string) => `/api/v1/sync-jobs/${uuid}/runs/${run}/log`,
}
