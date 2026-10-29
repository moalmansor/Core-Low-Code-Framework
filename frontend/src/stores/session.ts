import { defineStore } from 'pinia'
import { setDisplayPrefs } from '@/runtime/displayPrefs'
import { computed, ref } from 'vue'
import { ensureCsrf, get, resetCsrf, send } from '@/api/http'
import { useLocale, type LocaleInfo } from '@/i18n'
import { applyTheme as paintTheme, type ClientTheme } from '@/theme/theme'
import { setDefaultTextLocale } from '@/runtime/i18nText'

export interface Bootstrap {
  setup_completed: boolean
  system_name: Record<string, string>
  default_locale: string
  locales: (LocaleInfo & Record<string, unknown>)[]
  formats: Record<string, unknown>
  calendar: string
  branding: { logo: string | null; favicon: string | null; primary_color?: string | null; primary_color_dark?: string | null }
  /** System-wide maintenance mode, shown on the sign-in page. */
  maintenance?: { active: boolean; until: string | null; message: string | null }
  /** The organization's default theme (ADR-0055), or null for the product theme. */
  theme?: ClientTheme | null
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
  /** Set while an administrator acts as this user (ADR-0061). */
  impersonation?: { by: string; reason: string; expires_at: string } | null
  /** Capabilities a feature flag has not released to this person yet. */
  capabilities_off?: string[]
  preferences: {
    locale: string | null
    theme_mode: string
    timezone: string | null
    calendar: string | null
    digits: string | null
    date_format: string | null
    density: string | null
    notification_channels?: { email: boolean; in_app: boolean; sms?: boolean }
    number_format?: { group: string | null; decimal: string } | null
    digest_frequency?: 'none' | 'daily' | 'weekly'
    landing_page?: { type: 'home' | 'my_work' | 'application' | 'page' | 'form'; target?: string | null } | null
    /** What the person may change; the rest is set by the organization. */
    editable?: string[]
  }
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
  /** Whether a capability a feature flag gates has been released to this person (ADR-0062). */
  const capable = (capability: string) => !(me.value?.capabilities_off ?? []).includes(capability)
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
    if (boot.value.default_locale) setDefaultTextLocale(boot.value.default_locale)
    useTheme(null, true)
    const preferred = storedLocale()
    const browser = navigator.language.slice(0, 2)
    const enabled = boot.value.locales.map((l) => l.code)
    const pick = [preferred, browser].find((c) => c && enabled.includes(c)) ?? boot.value.default_locale
    await switchLocale(pick, false)
    document.title = browserTitle.value || document.title
  }

  /** The theme in effect: an application's own while the user works in it, else the organization's. */
  const theme = ref<ClientTheme | null>(null)
  let painted: string | null = null
  function useTheme(appTheme: ClientTheme | null, force = false): void {
    const next = appTheme ?? boot.value?.theme ?? null
    const stamp = next ? `${next.uuid}:${next.version}` : ''
    if (!force && painted === stamp) return
    painted = stamp
    theme.value = next
    const b = boot.value?.branding
    paintTheme(next, { primary: b?.primary_color, primaryDark: b?.primary_color_dark, favicon: b?.favicon })
  }
  /** The title shown in the browser tab: the theme's, else the system name. */
  const browserTitle = computed(() => theme.value?.browser_title || systemName.value)

  async function loadMe(): Promise<Me | null> {
    try {
      me.value = (await get<{ data: Me }>('/me')).data
      const pref = me.value.preferences.locale
      if (pref && pref !== locale.value) await switchLocale(pref, false)
      applyTheme(me.value.preferences.theme_mode)
      applyPersonal(me.value.preferences)
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

  /** Dates, numbers and density the way the person chose (ADR-0063). */
  function applyPersonal(p: Me['preferences'] | null): void {
    setDisplayPrefs(
      p
        ? {
            timeZone: p.timezone,
            calendar: (p.calendar as 'gregorian' | 'hijri' | 'both' | null) ?? null,
            digits: (p.digits as 'western' | 'arabic_indic' | null) ?? null,
            dateFormat: p.date_format,
            number: p.number_format ?? null,
          }
        : null,
    )
    const root = document.documentElement
    for (const d of ['compact', 'normal', 'comfortable']) root.classList.toggle(`density-${d}`, p?.density === d)
  }
  /** Whether the person may change a preference themselves. */
  const mayChange = (preference: string) => (me.value?.preferences.editable ?? [preference]).includes(preference)

  async function logout(): Promise<void> {
    try {
      await send('post', '/auth/logout')
    } finally {
      me.value = null
      applyTheme('system')
      applyPersonal(null)
      resetCsrf()
      await ensureCsrf()
    }
  }

  return {
    applyPersonal,
    mayChange,
    capable,
    boot,
    me,
    locale,
    direction,
    systemName,
    browserTitle,
    theme,
    useTheme,
    gate,
    can,
    loadBootstrap,
    loadMe,
    switchLocale,
    applyTheme,
    logout,
  }
})
