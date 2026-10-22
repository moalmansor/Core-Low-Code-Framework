import { get, send } from '@/api/http'

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
