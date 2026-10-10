import type { I18nText } from './types'

let defaultLocale = 'en'

/** The system's default language, set from the bootstrap (the fallback for every translatable text). */
export function setDefaultTextLocale(code: string): void {
  defaultLocale = code
}

/**
 * Text of a translatable map in the active locale, falling back to the
 * system's default language and then to any filled locale (translatable text
 * is stored per locale). Blank entries count as missing.
 */
export function pickText(map: I18nText | null | undefined, locale: string): string | null {
  if (!map) return null
  for (const code of [locale, defaultLocale, ...Object.keys(map)]) {
    const v = map[code]
    if (typeof v === 'string' && v.trim() !== '') return v
  }
  return null
}

/** "visit_date" → "Visit date": a key made readable, the last resort when no label exists in any language. */
export function humanize(key: string): string {
  const text = key.replace(/[_\-\s]+/g, ' ').trim()
  return text === '' ? key : text.charAt(0).toUpperCase() + text.slice(1)
}

/** A label for display: the text in the best language, else the key made readable. An internal identifier is never shown as-is. */
export function labelOf(map: I18nText | null | undefined, locale: string, key: string): string {
  return pickText(map, locale) ?? humanize(key)
}
