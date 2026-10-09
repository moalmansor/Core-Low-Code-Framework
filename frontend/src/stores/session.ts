import { defineStore } from 'pinia'
import { computed, ref } from 'vue'
import { ensureCsrf, get, resetCsrf, send } from '@/api/http'
import { useLocale, type LocaleInfo } from '@/i18n'
import { applyBrand } from '@/theme/brand'

export interface Bootstrap {
  setup_completed: boolean
  system_name: Record<string, string>
  default_locale: string
  locales: (LocaleInfo & Record<string, unknown>)[]
  formats: Record<string, unknown>
  calendar: string
  branding: { logo: string | null; favicon: string | null; primary_color?: string | null; primary_color_dark?: string | null }
}

export interface Me {
  id: number
  uuid: string
  name: string
  email: string
  username: string | null
  auth_source: string
  job_title: string | null
  roles: string[]
  permissions: string[]
  two_factor: { enabled: boolean; required: boolean; pending_confirmation: boolean }
  password_expired: boolean
  preferences: { locale: string | null; theme_mode: string; timezone: string | null; calendar: string | null; digits: string | null; date_format: string | null; density: string | null }
}

const LOCALE_KEY = 'lcf.locale'

function storedLocale(): string | null {
  try {
    return localStorage.getItem(LOCALE_KEY)
  } catch {
    return null
  }
}

export const useSession = defineStore('session', () => {
  const boot = ref<Bootstrap | null>(null)
  const me = ref<Me | null>(null)
  const locale = ref('en')

  const permissions = computed(() => new Set(me.value?.permissions ?? []))
  const can = (permission: string) => permissions.value.has(permission)
  const direction = computed(() => boot.value?.locales.find((l) => l.code === locale.value)?.direction ?? 'ltr')
  const systemName = computed(() => {
    const names = boot.value?.system_name ?? {}
    return names[locale.value] || names[boot.value?.default_locale ?? 'en'] || Object.values(names)[0] || ''
  })
  /** Blocking state the shell must resolve before anything else. */
  const gate = computed<'none' | 'two_factor' | 'password_expired'>(() => {
    if (!me.value) return 'none'
    if (me.value.two_factor.required && !me.value.two_factor.enabled) return 'two_factor'
    if (me.value.password_expired) return 'password_expired'
    return 'none'
  })

  async function loadBootstrap(): Promise<void> {
    boot.value = (await get<{ data: Bootstrap }>('/bootstrap')).data
    applyBrand(boot.value.branding.primary_color, boot.value.branding.primary_color_dark)
    const preferred = storedLocale()
    const browser = navigator.language.slice(0, 2)
    const enabled = boot.value.locales.map((l) => l.code)
    const pick = [preferred, browser].find((c) => c && enabled.includes(c)) ?? boot.value.default_locale
    await switchLocale(pick, false)
    document.title = systemName.value || document.title
  }

  async function loadMe(): Promise<Me | null> {
    try {
      me.value = (await get<{ data: Me }>('/me')).data
      const pref = me.value.preferences.locale
      if (pref && pref !== locale.value) await switchLocale(pref, false)
      applyTheme(me.value.preferences.theme_mode)
    } catch {
      me.value = null
    }
    return me.value
  }

  async function switchLocale(code: string, persist = true): Promise<void> {
    const info = boot.value?.locales.find((l) => l.code === code)
    if (!info) return
    // The server answers in the saved preference first, so save it before the
    // interface switches: what reloads on the switch (navigation, names) then
    // arrives in the new language.
    if (persist && me.value) {
      try {
        await send('patch', '/me/preferences', { locale: code })
        me.value.preferences.locale = code
      } catch {
        /* the switch still applies to this browser; the error toast explains */
      }
    }
    locale.value = code
    await useLocale(code, info.direction)
    try {
      localStorage.setItem(LOCALE_KEY, code)
    } catch {
      /* private mode */
    }
  }

  /**
   * Light, dark, or the system preference (the default, also before sign-in);
   * with "system" the page follows the operating system when it changes.
   */
  let themeMode = 'system'
  const systemDark = typeof window !== 'undefined' ? window.matchMedia?.('(prefers-color-scheme: dark)') : undefined
  systemDark?.addEventListener?.('change', () => {
    if (themeMode === 'system') applyTheme('system')
  })
  function applyTheme(mode: string): void {
    themeMode = mode
    const dark = mode === 'dark' || (mode === 'system' && !!systemDark?.matches)
    document.documentElement.classList.toggle('app-dark', dark)
  }
  applyTheme('system')

  async function logout(): Promise<void> {
    try {
      await send('post', '/auth/logout')
    } finally {
      me.value = null
      applyTheme('system')
      resetCsrf()
      await ensureCsrf()
    }
  }

  return { boot, me, locale, direction, systemName, gate, can, loadBootstrap, loadMe, switchLocale, applyTheme, logout }
})
