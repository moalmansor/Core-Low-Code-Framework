import { reactive } from 'vue'
import { get, send } from '@/api/http'

/** Help content (specification §4.37, ADR-0064) as the person sees it. */
export type HelpContextType = 'form' | 'page' | 'application' | 'admin_area'
export interface HelpEntry {
  uuid: string
  title: string | null
  /** Null for a help page: it opens on its own. */
  body: string | null
  display: 'side_panel' | 'help_page'
  /** The field or group the entry is about, if any. */
  about: string | null
}
export interface HelpContext {
  /** Field or group uuid → plain text. */
  tooltips: Record<string, string>
  panel: HelpEntry[]
}

const EMPTY: HelpContext = { tooltips: {}, panel: [] }
const cache = reactive(new Map<string, HelpContext>())
const loading = new Set<string>()

/**
 * The help for one thing, loaded once per language and kept for the session;
 * undefined while it loads. Help is a convenience: a failed load shows none.
 */
export function helpFor(type: HelpContextType, target: string, locale: string): HelpContext | undefined {
  const key = `${locale}|${type}|${target}`
  const known = cache.get(key)
  if (known || loading.has(key)) return known
  loading.add(key)
  get<{ data: HelpContext }>(`/help?type=${type}&target=${encodeURIComponent(target)}`)
    .then((r) => cache.set(key, { tooltips: { ...r.data.tooltips }, panel: r.data.panel }))
    .catch(() => cache.set(key, EMPTY))
    .finally(() => loading.delete(key))
  return undefined
}

/** Forgets what was loaded, after an administrator changed help. */
export function forgetHelp(): void {
  cache.clear()
}

export const helpPage = (uuid: string) => get<{ data: { uuid: string; title: string | null; body: string | null } }>(`/help/${uuid}`).then((r) => r.data)

/** Help as an administrator edits it (GET/POST/PUT /help-content). */
export type HelpTargetType = 'form' | 'field' | 'group' | 'page' | 'application' | 'admin_area'
export const HELP_TARGETS: HelpTargetType[] = ['form', 'field', 'group', 'page', 'application', 'admin_area']
export type HelpDisplay = 'tooltip' | 'side_panel' | 'help_page'
export interface HelpDoc {
  uuid?: string
  target_type: HelpTargetType
  target: string | null
  /** The form a field or group belongs to (read only; chosen first in the editor). */
  form?: string | null
  display: HelpDisplay
  published: boolean
  sort_order: number
  i18n: { title: Record<string, string>; body: Record<string, string> }
  hash?: string
  label?: string
}
export interface HelpTarget {
  uuid: string
  label: string | null
  key: string
}
export const helpAdminApi = {
  list: () => get<{ data: HelpDoc[] }>('/help-content').then((r) => r.data),
  targets: () => get<{ data: { forms: HelpTarget[]; applications: HelpTarget[]; pages: HelpTarget[]; admin_areas: HelpTarget[] } }>('/help-content/targets').then((r) => r.data),
  formTargets: (form: string) => get<{ data: { fields: HelpTarget[]; groups: HelpTarget[] } }>(`/help-content/targets?form=${form}`).then((r) => r.data),
  create: (help: HelpDoc) => send<{ data: HelpDoc }>('post', '/help-content', { help }).then((r) => r.data),
  save: (uuid: string, help: HelpDoc, baseHash: string) => send<{ data: HelpDoc }>('put', `/help-content/${uuid}`, { help, base_hash: baseHash }).then((r) => r.data),
  remove: (uuid: string) => send('delete', `/help-content/${uuid}`),
}
