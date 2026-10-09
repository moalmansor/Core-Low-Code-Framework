import { inject, provide, reactive, ref, watch, type InjectionKey, type Ref } from 'vue'

/**
 * Shared structure of the builder's properties panels (design system:
 * properties panel): a few tabs, collapsible sections inside each tab, and a
 * "Find a setting" search across every tab. Which sections an admin has
 * opened or closed is remembered in this browser (a convenience only).
 */
export interface PanelContext {
  tab: Ref<string>
  query: Ref<string>
  isOpen(id: string, defaultOpen: boolean): boolean
  toggle(id: string, defaultOpen: boolean): void
}

const KEY: InjectionKey<PanelContext> = Symbol('properties-panel')
const STORAGE = 'lcf.builder.sections'
// The last tab per kind of panel, so moving between fields keeps the admin on the same tab.
const lastTab = new Map<string, string>()

function stored(): Record<string, boolean> {
  try {
    return JSON.parse(localStorage.getItem(STORAGE) ?? '{}') as Record<string, boolean>
  } catch {
    return {}
  }
}

export function providePanel(kind: string, initialTab: string, available: () => string[] = () => [initialTab]): PanelContext {
  const open = reactive<Record<string, boolean>>(stored())
  const remembered = lastTab.get(kind)
  const tab = ref(remembered && available().includes(remembered) ? remembered : initialTab)
  watch(tab, (v) => lastTab.set(kind, v))
  const ctx: PanelContext = {
    tab,
    query: ref(''),
    isOpen: (id, defaultOpen) => open[id] ?? defaultOpen,
    toggle(id, defaultOpen) {
      open[id] = !(open[id] ?? defaultOpen)
      try {
        localStorage.setItem(STORAGE, JSON.stringify(open))
      } catch {
        /* private mode: the choice lasts for this page */
      }
    },
  }
  provide(KEY, ctx)
  return ctx
}

export function usePanel(): PanelContext {
  const ctx = inject(KEY)
  if (!ctx) throw new Error('PanelSection used outside a properties panel')
  return ctx
}

/** Case- and diacritic-insensitive match of a search query against labels (Arabic and Latin). */
export function matches(query: string, terms: string[]): boolean {
  const norm = (s: string) =>
    s
      .normalize('NFKD')
      .replace(/[̀-ًͯ-ٰٟ]/g, '')
      .replace(/[إأآ]/g, 'ا')
      .replace(/ى/g, 'ي')
      .replace(/ة/g, 'ه')
      .toLowerCase()
  const q = norm(query.trim())
  return q === '' || terms.some((t) => norm(t).includes(q))
}
