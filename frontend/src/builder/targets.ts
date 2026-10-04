import { builderApi, type FormSummary } from './api'
import type { I18nText } from './types'

/**
 * Other forms and collections as targets of relations, option sources,
 * sub-forms and lookups, with the fields of their published version (the
 * server checks references against the published definition).
 */

export interface TargetField {
  uuid: string
  key: string
  type: string
  label: I18nText
}

const fieldCache = new Map<string, Promise<TargetField[]>>()

export async function searchForms(search: string, kind: 'form' | 'collection' | null = null): Promise<FormSummary[]> {
  return builderApi.forms({ per_page: 50, ...(search ? { search } : {}), ...(kind ? { kind } : {}) })
}

export function targetFields(form: string): Promise<TargetField[]> {
  let cached = fieldCache.get(form)
  if (!cached) {
    cached = (async () => {
      const summary = await builderApi.form(form)
      if (summary.version === null) return []
      const definition = (await builderApi.version(form, summary.version)).definition as { fields?: { uuid: string; key: string; type: string; i18n?: { label?: I18nText } }[] }
      return (definition.fields ?? []).map((f) => ({ uuid: f.uuid, key: f.key, type: f.type, label: f.i18n?.label ?? {} }))
    })()
    cached.catch(() => fieldCache.delete(form))
    fieldCache.set(form, cached)
  }
  return cached
}
