import { ApiError, get } from '@/api/http'

/** First validation message of an API error, else its message. */
export function errorText(e: unknown, fallback: string): string {
  if (e instanceof ApiError) return Object.values(e.fieldErrors)[0] || e.message || fallback
  return fallback
}

/** Validation messages keyed by field, for inline display. */
export function fieldErrors(e: unknown): Record<string, string> {
  return e instanceof ApiError ? e.fieldErrors : {}
}

export function saveBlob(blob: Blob, filename: string): void {
  const a = document.createElement('a')
  a.href = URL.createObjectURL(blob)
  a.download = filename
  a.click()
  URL.revokeObjectURL(a.href)
}

/** Drops empty per-locale values so a blank translation falls back instead of being stored. */
export function filledLocales(values: Record<string, string | null | undefined>): Record<string, string> {
  const out: Record<string, string> = {}
  for (const [code, v] of Object.entries(values)) if (typeof v === 'string' && v.trim() !== '') out[code] = v.trim()
  return out
}

/** "YYYY-MM-DD" of a local calendar date. */
export function isoDay(d: Date): string {
  return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`
}

export function parseIsoDay(s: string | null | undefined): Date | null {
  const m = /^(\d{4})-(\d{2})-(\d{2})/.exec(s ?? '')
  return m ? new Date(Number(m[1]), Number(m[2]) - 1, Number(m[3])) : null
}

export function formatDateTime(iso: string | null | undefined, locale: string): string {
  if (!iso) return '—'
  const d = new Date(iso)
  return Number.isNaN(d.getTime()) ? iso : d.toLocaleString(locale)
}

export function formatBytes(n: number): string {
  if (n < 1024) return `${n} B`
  if (n < 1024 * 1024) return `${(n / 1024).toFixed(1)} KB`
  if (n < 1024 * 1024 * 1024) return `${(n / 1024 / 1024).toFixed(1)} MB`
  return `${(n / 1024 / 1024 / 1024).toFixed(2)} GB`
}

export type IncludeMode = 'structure' | 'structure_permissions' | 'everything'
export const INCLUDE_MODES: IncludeMode[] = ['structure', 'structure_permissions', 'everything']

export interface FormSummary {
  uuid: string
  key: string
  kind: 'form' | 'collection'
  name: string
  state: 'draft' | 'published' | 'unpublished' | 'archived' | 'schema_inconsistent'
  binding_mode: 'managed' | 'bound'
  table_name: string | null
  icon: string | null
  version: number | null
  next_version: number | null
  application: { uuid: string; key: string } | null
  record_count: number | null
  updated_at: string | null
}

/** Every form and collection (all pages of GET /forms), for pickers. Needs Manage Forms. */
export async function fetchAllForms(params: Record<string, unknown> = {}): Promise<FormSummary[]> {
  const out: FormSummary[] = []
  for (let page = 1; page <= 100; page++) {
    const res = await get<{ data: FormSummary[]; meta: { total: number } }>('/forms', { ...params, page, per_page: 100 })
    out.push(...res.data)
    if (out.length >= res.meta.total || res.data.length === 0) break
  }
  return out
}
