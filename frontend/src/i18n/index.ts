import { createI18n } from 'vue-i18n'
import { http, setRequestLocale } from '@/api/http'
import { bundled } from './bundled'

/**
 * Interface strings come from the server catalog (bundled defaults merged with
 * the administrator's overrides, per locale, with fallback). The two bundled
 * catalogs are compiled in only so the login page renders before the network.
 */
export const i18n = createI18n({
  legacy: false,
  locale: 'en',
  fallbackLocale: 'en',
  flatJson: true,
  missingWarn: false,
  fallbackWarn: false,
  messages: bundled,
})

export interface LocaleInfo {
  code: string
  native_name: string
  direction: 'ltr' | 'rtl'
  is_default: boolean
}

const loaded = new Set<string>()

export async function useLocale(code: string, direction: 'ltr' | 'rtl'): Promise<void> {
  if (!loaded.has(code)) {
    try {
      const { data } = await http.get<{ data: Record<string, string> }>(`/i18n/${code}`)
      i18n.global.setLocaleMessage(code, data.data)
      loaded.add(code)
    } catch {
      // keep the bundled strings
    }
  }
  i18n.global.locale.value = code
  setRequestLocale(code)
  document.documentElement.lang = code
  document.documentElement.dir = direction
}

export function reloadCatalog(code: string): void {
  loaded.delete(code)
}
