import { get, send } from '@/api/http'

/** Access policies, trusted devices, impersonation and feature flags (specification §4.36). */
export interface TimeWindow {
  days: number[]
  from: string
  to: string
  timezone: string
}
export interface PolicyDoc {
  uuid?: string
  key: string
  i18n: { name: Record<string, string> }
  ip_allowlist: string[]
  time_windows: TimeWindow[]
  max_concurrent_sessions: number | null
  session_idle_minutes: number
  session_absolute_minutes: number
  require_trusted_device: boolean
  require_2fa: boolean
  is_admin_policy: boolean
  roles: string[]
  hash?: string
  label?: string
}
export interface TrustedDevice {
  id: number
  label: string | null
  last_used_at: string | null
  expires_at: string
  current: boolean
}

export const policiesApi = {
  list: () => get<{ data: PolicyDoc[] }>('/access-policies').then((r) => r.data),
  create: (policy: PolicyDoc) => send<{ data: PolicyDoc }>('post', '/access-policies', { policy }).then((r) => r.data),
  save: (uuid: string, policy: PolicyDoc, baseHash: string) => send<{ data: PolicyDoc }>('put', `/access-policies/${uuid}`, { policy, base_hash: baseHash }).then((r) => r.data),
  remove: (uuid: string) => send('delete', `/access-policies/${uuid}`),
}

export const devicesApi = {
  list: () => get<{ data: TrustedDevice[] }>('/me/devices').then((r) => r.data),
  revoke: (id: number) => send('delete', `/me/devices/${id}`),
}

/** ISO weekday numbers, Monday first. */
export const WEEKDAYS = [1, 2, 3, 4, 5, 6, 7] as const

/** The weekday's name in the given locale (2026-10-19 was a Monday). */
export function weekdayName(day: number, locale: string): string {
  return new Intl.DateTimeFormat(locale, { weekday: 'long', timeZone: 'UTC' }).format(new Date(Date.UTC(2026, 9, 18 + day)))
}

export function timeZones(): string[] {
  const intl = Intl as unknown as { supportedValuesOf?: (key: string) => string[] }
  return intl.supportedValuesOf?.('timeZone') ?? ['UTC']
}

export type FlagType = 'form' | 'action' | 'page' | 'capability'
export interface FlagDoc {
  uuid?: string
  key: string
  type: FlagType
  target: string | null
  enabled: boolean
  application: string | null
  audience: { roles: string[]; departments: string[]; users: string[] }
  i18n: { description: Record<string, string> }
  hash?: string
  label?: string
}
export interface Capability {
  key: string
  label: string
}

export const flagsApi = {
  list: () => get<{ data: FlagDoc[]; capabilities: Capability[] }>('/feature-flags'),
  create: (flag: FlagDoc) => send<{ data: FlagDoc }>('post', '/feature-flags', { flag }).then((r) => r.data),
  save: (uuid: string, flag: FlagDoc, baseHash: string) => send<{ data: FlagDoc }>('put', `/feature-flags/${uuid}`, { flag, base_hash: baseHash }).then((r) => r.data),
  remove: (uuid: string) => send('delete', `/feature-flags/${uuid}`),
}
