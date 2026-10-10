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
