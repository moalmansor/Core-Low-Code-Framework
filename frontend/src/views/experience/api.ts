import { get, send } from '@/api/http'
import type { Ast } from '@/builder/types'

/** Custom pages (specification §4.29, ADR-0054). */
export type I18nMap = Record<string, string>
export type PageType = 'content' | 'link' | 'dashboard' | 'report' | 'form_host'
export const PAGE_TYPES: PageType[] = ['content', 'link', 'dashboard', 'report', 'form_host']

export interface PageDoc {
  uuid?: string
  key: string
  type: PageType
  application: string
  url: string | null
  dashboard: string | null
  report: string | null
  form: string | null
  i18n: { name: I18nMap; content: I18nMap }
  published: boolean
  hash?: string
  label?: string
  permission?: string
}

export interface PageSummary {
  uuid: string
  key: string
  type: PageType
  published: boolean
  label: string
  application: string
  updated_at: string
}

export interface PageView {
  uuid: string
  type: PageType
  label: string
  application: string
  content: string | null
  url: string | null
  dashboard: string | null
  report: string | null
  form: string | null
}

export const pagesApi = {
  list: (application: string) => get<{ data: PageSummary[] }>(`/pages?application=${application}`).then((r) => r.data),
  load: (uuid: string) => get<{ data: PageDoc }>(`/pages/${uuid}`).then((r) => r.data),
  create: (page: PageDoc) => send<{ data: PageDoc }>('post', '/pages', { page }).then((r) => r.data),
  save: (uuid: string, page: PageDoc, baseHash: string) => send<{ data: PageDoc }>('put', `/pages/${uuid}`, { page, base_hash: baseHash }).then((r) => r.data),
  remove: (uuid: string) => send('delete', `/pages/${uuid}`),
  view: (uuid: string) => get<{ data: PageView }>(`/pages/${uuid}/view`).then((r) => r.data),
}

/** Home screens (specification §4.29, ADR-0054). */
export type HomeTarget = 'default' | 'role' | 'department' | 'user'
export type HomeWidgetType = 'my_work' | 'my_records' | 'report' | 'shortcuts' | 'announcements' | 'recent_activity' | 'pinned_links' | 'embedded_table' | 'html'
export const HOME_WIDGETS: HomeWidgetType[] = ['my_work', 'my_records', 'report', 'shortcuts', 'pinned_links', 'recent_activity', 'announcements', 'embedded_table', 'html']

export interface HomeLink {
  url?: string
  form?: string
  action?: 'list' | 'new'
  i18n: { label: I18nMap }
}
export interface HomeWidgetDoc {
  uuid: string
  type: HomeWidgetType
  i18n: { title: I18nMap; text: I18nMap }
  report: string | null
  form: string | null
  limit: number
  links: HomeLink[]
  condition: Ast | null
  layout: { w: number; h: number }
}
export interface HomeScreenDoc {
  uuid?: string
  application: string | null
  target_type: HomeTarget
  target: string | null
  priority: number
  active: boolean
  i18n: { name: I18nMap }
  widgets: HomeWidgetDoc[]
  hash?: string
  label?: string
}
export interface HomeScreenSummary {
  uuid: string
  label: string
  target_type: HomeTarget
  target_name: string | null
  application: string | null
  application_name: string | null
  priority: number
  active: boolean
  widgets: number
}
export interface ShownHomeWidget {
  uuid: string
  type: HomeWidgetType
  title: string | null
  text: string | null
  report: string | null
  form: string | null
  limit: number
  links: { url?: string; form?: string; action?: 'list' | 'new'; label: string | null }[]
  layout: { w: number; h: number }
}
export interface ShownHome {
  uuid: string
  label: string
  widgets: ShownHomeWidget[]
}
export interface ActivityItem {
  form: { uuid: string; name: string }
  record: { uuid: string; title: string | null }
  operation: string
  at: string
}

export const homeApi = {
  mine: (application: string | null) => get<{ data: ShownHome | null }>('/home', application ? { application } : undefined).then((r) => r.data),
  activity: (limit: number) => get<{ data: ActivityItem[] }>('/home/activity', { limit }).then((r) => r.data),
  list: () => get<{ data: HomeScreenSummary[] }>('/home-screens').then((r) => r.data),
  load: (uuid: string) => get<{ data: HomeScreenDoc }>(`/home-screens/${uuid}`).then((r) => r.data),
  create: (screen: HomeScreenDoc) => send<{ data: HomeScreenDoc }>('post', '/home-screens', { screen }).then((r) => r.data),
  save: (uuid: string, screen: HomeScreenDoc, baseHash: string) => send<{ data: HomeScreenDoc }>('put', `/home-screens/${uuid}`, { screen, base_hash: baseHash }).then((r) => r.data),
  remove: (uuid: string) => send('delete', `/home-screens/${uuid}`),
}

/** Announcements and banners (specification §4.29, ADR-0054). */
export type Severity = 'info' | 'success' | 'warning' | 'danger'
export type Placement = 'banner' | 'home' | 'bell'
export interface AnnouncementDoc {
  uuid?: string
  application: string | null
  severity: Severity
  placement: Placement
  roles: string[]
  starts_at: string | null
  ends_at: string | null
  dismissible: boolean
  published: boolean
  i18n: { title: I18nMap; body: I18nMap }
  hash?: string
  label?: string
}
export interface ActiveAnnouncement {
  uuid: string
  title: string | null
  body: string | null
  severity: Severity
  placement: Placement
  dismissible: boolean
  application: { uuid: string; name: string } | null
  starts_at: string
}
export const announcementsApi = {
  list: () => get<{ data: AnnouncementDoc[] }>('/announcements').then((r) => r.data),
  create: (announcement: AnnouncementDoc) => send<{ data: AnnouncementDoc }>('post', '/announcements', { announcement }).then((r) => r.data),
  save: (uuid: string, announcement: AnnouncementDoc, baseHash: string) => send<{ data: AnnouncementDoc }>('put', `/announcements/${uuid}`, { announcement, base_hash: baseHash }).then((r) => r.data),
  remove: (uuid: string) => send('delete', `/announcements/${uuid}`),
  active: (placement?: Placement) => get<{ data: ActiveAnnouncement[] }>('/announcements/active', placement ? { placement } : undefined).then((r) => r.data),
  dismiss: (uuid: string) => send('post', `/announcements/${uuid}/dismiss`),
}

/** System maintenance mode. */
export interface MaintenanceState {
  active: boolean
  until: string | null
  message: string | null
  messages: I18nMap | null
  blocked: boolean
  can_manage: boolean
}
export const maintenanceApi = {
  get: () => get<{ data: MaintenanceState }>('/maintenance').then((r) => r.data),
  save: (enabled: boolean, until: string | null, message: I18nMap) => send<{ data: MaintenanceState }>('put', '/maintenance', { enabled, until, message }).then((r) => r.data),
}

/** Global search and its configuration. */
export interface SearchHit {
  form: { uuid: string; name: string }
  record: { uuid: string; title: string | null; subtitle: string | null }
  score: number
}
export interface SearchConfig {
  form: string
  form_name: string
  published: boolean
  enabled: boolean
  fields: { field: string; weight: number }[]
  subtitle: string | null
  roles: string[]
  available: { uuid: string; key: string; label: string }[]
}
export const searchApi = {
  search: (q: string) => get<{ data: SearchHit[] }>('/search', { q }).then((r) => r.data),
  configs: () => get<{ data: SearchConfig[] }>('/search-configs').then((r) => r.data),
  save: (form: string, config: Pick<SearchConfig, 'enabled' | 'fields' | 'subtitle' | 'roles'>) => send<{ data: SearchConfig }>('put', `/search-configs/${form}`, { config }).then((r) => r.data),
}
