import { ensureCsrf, get, http, send } from '@/api/http'

/** Reports and dashboards (specification §4.18, ADR-0053). */
export type I18nMap = Record<string, string>
export type Visualization = 'table' | 'pivot' | 'bar' | 'line' | 'pie' | 'area' | 'kpi'
export const VISUALIZATIONS: Visualization[] = ['table', 'pivot', 'bar', 'line', 'area', 'pie', 'kpi']
export type Aggregate = 'count' | 'sum' | 'avg' | 'min' | 'max'
export type Bucket = 'day' | 'week' | 'month' | 'quarter' | 'year'

export interface Dimension {
  uuid: string
  path: string[]
  bucket: Bucket | null
  i18n: { label: I18nMap }
}
export interface Measure {
  uuid: string
  aggregate: Aggregate
  path: string[] | null
  i18n: { label: I18nMap }
}
export interface Column {
  uuid: string
  path: string[]
  i18n: { header: I18nMap }
}
export interface Filter {
  uuid: string
  path: string[]
  operator: string
  value: unknown
}
export interface ReportOptions {
  stacked: boolean
  showValues: boolean
  showLegend: boolean
  showTotals: boolean
}
export interface ReportDoc {
  uuid?: string
  key: string
  form: string | null
  application: string | null
  visualization: Visualization
  i18n: { name: I18nMap; description: I18nMap }
  dimensions: Dimension[]
  measures: Measure[]
  columns: Column[]
  filters: Filter[]
  sort: { by: 'dimension' | 'measure' | 'column'; ref: string; direction: 'asc' | 'desc' } | null
  limit: number | null
  cacheSeconds: number | null
  options: ReportOptions
  hash?: string
  label?: string
  form_name?: string | null
}
export interface ReportSummary {
  uuid: string
  key: string
  label: string
  visualization: Visualization
  form_name: string | null
  updated_at: string
}
export interface GroupRow {
  keys: (string | number | boolean | null)[]
  labels: string[]
  values: (number | string | null)[]
}
export interface Result {
  mode: 'grouped' | 'detail'
  records: number
  truncated: boolean
  omitted: string[]
  generatedAt: string
  form: string
  dimensions?: { uuid: string; label: string; bucket: Bucket | null; type: string }[]
  measures?: { uuid: string; label: string; aggregate: Aggregate; type: 'number' | 'date' }[]
  rows: GroupRow[] | (string | number | null)[][]
  totals?: (number | string | null)[]
  columns?: { uuid: string; label: string; type: string }[]
}
export interface ReportData {
  uuid: string
  label: string
  description: string | null
  visualization: Visualization
  options: ReportOptions
  result: Result
}
export interface TreeItem {
  kind: 'field' | 'link' | 'repeater' | 'reverse' | 'system'
  path: string[]
  label: string
  type?: string
  form?: string
  toMany: boolean
  numeric?: boolean
  date?: boolean
}

export type WidgetType = 'report' | 'text'
export interface WidgetDoc {
  uuid: string
  type: WidgetType
  report: string | null
  i18n: { title: I18nMap; text: I18nMap }
  roles: string[]
  layout: { w: number; h: number }
}
export interface DashboardDoc {
  uuid?: string
  key: string
  application: string | null
  active: boolean
  i18n: { name: I18nMap; description: I18nMap }
  widgets: WidgetDoc[]
  hash?: string
  label?: string
}
export interface DashboardSummary {
  uuid: string
  key: string
  label: string
  active: boolean
  widgets: number
}
export interface ShownWidget {
  uuid: string
  type: WidgetType
  layout: { w: number; h: number }
  title: string | null
  text: string | null
  report?: string
  visualization: Visualization | null
  options: ReportOptions | null
  result: Result | null
  error: string | null
}
export interface DashboardView {
  uuid: string
  label: string
  description: string | null
  widgets: ShownWidget[]
}

async function download(url: string, body: unknown, fallback: string): Promise<void> {
  await ensureCsrf()
  const res = await http.post<Blob>(url, body, { responseType: 'blob', timeout: 300000 })
  const disposition = String(res.headers['content-disposition'] ?? '')
  const match = /filename\*?=(?:UTF-8'')?"?([^";]+)"?/i.exec(disposition)
  const a = document.createElement('a')
  const href = URL.createObjectURL(res.data)
  a.href = href
  a.download = match ? decodeURIComponent(match[1]!) : fallback
  document.body.appendChild(a)
  a.click()
  a.remove()
  setTimeout(() => URL.revokeObjectURL(href), 1000)
}

export const reportsApi = {
  list: async () => (await get<{ data: ReportSummary[] }>('/reports')).data,
  mine: async () =>
    (
      await get<{
        data: { reports: { uuid: string; label: string; description: string | null; visualization: Visualization }[]; dashboards: { uuid: string; label: string; description: string | null }[] }
      }>('/insights')
    ).data,
  fields: async (form: string, prefix: string[]) => (await get<{ data: TreeItem[] }>('/reports/fields', { form, prefix })).data,
  load: async (uuid: string) => (await get<{ data: ReportDoc }>(`/reports/${uuid}`)).data,
  create: async (doc: ReportDoc) => (await send<{ data: ReportDoc }>('post', '/reports', { report: doc })).data,
  save: async (uuid: string, doc: ReportDoc, hash: string) => (await send<{ data: ReportDoc }>('put', `/reports/${uuid}`, { report: doc, base_hash: hash })).data,
  remove: (uuid: string) => send('delete', `/reports/${uuid}`),
  preview: async (doc: ReportDoc) => (await send<{ data: Result }>('post', '/reports/preview', { report: doc })).data,
  data: async (uuid: string) => (await get<{ data: ReportData }>(`/reports/${uuid}/data`)).data,
  export: (uuid: string, format: 'xlsx' | 'csv' | 'pdf', image: string | null, name: string) => download(`/reports/${uuid}/export`, { format, image }, `${name}.${format}`),
}

export const dashboardsApi = {
  list: async () => (await get<{ data: DashboardSummary[] }>('/dashboards')).data,
  load: async (uuid: string) => (await get<{ data: DashboardDoc }>(`/dashboards/${uuid}`)).data,
  create: async (doc: DashboardDoc) => (await send<{ data: DashboardDoc }>('post', '/dashboards', { dashboard: doc })).data,
  save: async (uuid: string, doc: DashboardDoc, hash: string) => (await send<{ data: DashboardDoc }>('put', `/dashboards/${uuid}`, { dashboard: doc, base_hash: hash })).data,
  remove: (uuid: string) => send('delete', `/dashboards/${uuid}`),
  view: async (uuid: string) => (await get<{ data: DashboardView }>(`/dashboards/${uuid}/view`)).data,
  export: (uuid: string, format: 'xlsx' | 'pdf', images: Record<string, string>, name: string) => download(`/dashboards/${uuid}/export`, { format, images }, `${name}.${format}`),
}

export function uid(): string {
  return crypto.randomUUID()
}
