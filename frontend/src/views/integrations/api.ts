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
