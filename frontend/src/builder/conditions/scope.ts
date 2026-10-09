import type { ReferenceResolver } from '@/expressions'
import { repeaterOf } from '../document'
import type { DraftDocument, FieldTypeInfo, I18nText } from '../types'

/**
 * What an expression may refer to (expression-language.md §2, §5): the
 * fields of the form (and, inside a repeater, the fields of the row), the
 * current user, the evaluation context and the record's system values.
 */

export interface ReferenceField {
  uuid: string
  key: string
  label: I18nText
  type: string
  /** Static expression type (§3): text, number, boolean, date, … or list<…>/record. */
  valueType: string
  /** Key of the repeater the field belongs to, if any. */
  repeater: string | null
  /** Static option values, for value pickers. */
  options: { value: string; label: I18nText }[]
}

export interface ExpressionScope {
  fields: ReferenceField[]
  /** Repeater keys (references to the rows of a repeater). */
  repeaters: { key: string; label: I18nText }[]
  /** Row context: the repeater whose rows `@row` and bare names refer to. */
  rows: string | null
}

export const USER_ATTRIBUTES: Record<string, string> = {
  id: 'number',
  name: 'text',
  email: 'text',
  department: 'text',
  departments: 'list<text>',
  roles: 'list<text>',
  locale: 'text',
}
export const CONTEXT_ATTRIBUTES: Record<string, string> = { mode: 'text', form: 'text', locale: 'text', timezone: 'text' }
export const RECORD_ATTRIBUTES: Record<string, string> = { status: 'text', id: 'number', created_at: 'datetime', created_by: 'number', number: 'text' }

/** The static expression type of a field type's value. */
export function staticType(info: FieldTypeInfo | undefined): string {
  if (!info) return 'any'
  switch (info.value_type) {
    case 'list':
      return 'list<text>'
    case 'null':
      return 'null'
    default:
      return info.value_type
  }
}

export function buildScope(doc: DraftDocument, types: FieldTypeInfo[], rows: string | null = null): ExpressionScope {
  const byKey = new Map(types.map((t) => [t.key, t]))
  const fields: ReferenceField[] = []
  for (const f of doc.fields) {
    const info = byKey.get(f.type)
    if (!info || !info.stored) continue
    fields.push({
      uuid: f.uuid,
      key: f.key,
      label: f.i18n?.label ?? {},
      type: f.type,
      valueType: staticType(info),
      repeater: repeaterOf(doc, f)?.key ?? null,
      options: (f.options?.static ?? []).map((o) => ({ value: o.value, label: o.i18n?.label ?? {} })),
    })
  }
  const repeaters = doc.groups.filter((g) => g.type === 'repeater').map((g) => ({ key: g.key, label: g.i18n?.title ?? {} }))
  return { fields, repeaters, rows }
}

/** A client-side resolver for live type checking; the server's check is authoritative. */
export function resolverFor(scope: ExpressionScope): ReferenceResolver {
  const top = new Map(scope.fields.filter((f) => f.repeater === null).map((f) => [f.key, f]))
  const rowFields = (repeater: string) => new Map(scope.fields.filter((f) => f.repeater === repeater).map((f) => [f.key, f]))
  const typeOf = (f: ReferenceField, rest: string[]): string | null => {
    if (rest.length === 0) return f.valueType
    // Paths through a lookup continue into the related form, which is checked by the server.
    return f.valueType === 'record' ? 'any' : null
  }
  return (refScope, path, rowsPath) => {
    const [first, ...rest] = path
    if (first === undefined) return null
    const rowsKey = rowsPath?.[0] ?? (refScope === 'row' ? scope.rows : null)
    if ((refScope === 'row' || refScope === 'record') && rowsKey) {
      const f = rowFields(rowsKey).get(first)
      if (f) return typeOf(f, rest)
    }
    if (refScope === 'row') return null
    if (refScope === 'record' && scope.rows && rowsPath === null) {
      const f = rowFields(scope.rows).get(first)
      if (f) return typeOf(f, rest)
    }
    const f = top.get(first)
    if (f) return typeOf(f, rest)
    if (scope.repeaters.some((r) => r.key === first)) {
      if (rest.length === 0) return 'list<record>'
      const inner = rowFields(first).get(rest[0]!)
      if (!inner) return null
      const t = typeOf(inner, rest.slice(1))
      return t === null ? null : t.startsWith('list<') ? t : `list<${t}>`
    }
    return null
  }
}

/** The best label of an i18n map for the active locale. */
export function pick(text: I18nText | undefined, locale: string, fallback = ''): string {
  if (!text) return fallback
  return text[locale] || Object.values(text).find((v) => v) || fallback
}
