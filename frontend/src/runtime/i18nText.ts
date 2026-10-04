import type { I18nText } from './types'

/**
 * Text of a translatable map in the active locale, falling back to English
 * and then to any filled locale (translatable text is stored per locale).
 */
export function pickText(map: I18nText | null | undefined, locale: string): string | null {
  if (!map) return null
  const own = map[locale]
  if (own !== undefined && own.trim() !== '') return own
  const en = map.en
  if (en !== undefined && en.trim() !== '') return en
  for (const v of Object.values(map)) if (v.trim() !== '') return v
  return null
}
