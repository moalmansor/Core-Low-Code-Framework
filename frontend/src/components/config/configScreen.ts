import { computed, inject, onBeforeUnmount, provide, reactive, watch, type InjectionKey, type Ref } from 'vue'

/**
 * Shared state of a configuration screen (design system §5.5): which
 * sections are open (remembered in this browser, a convenience only) and
 * whether any document on the screen has unsaved changes, so leaving it or
 * switching tabs asks first.
 */
const STORAGE = 'lcf.config.sections'

function stored(): Record<string, boolean> {
  try {
    return JSON.parse(localStorage.getItem(STORAGE) ?? '{}') as Record<string, boolean>
  } catch {
    return {}
  }
}
const sections = reactive<Record<string, boolean>>(stored())

export function sectionOpen(id: string, defaultOpen: boolean): boolean {
  return sections[id] ?? defaultOpen
}

export function toggleSection(id: string, defaultOpen: boolean): void {
  sections[id] = !(sections[id] ?? defaultOpen)
  try {
    localStorage.setItem(STORAGE, JSON.stringify(sections))
  } catch {
    /* private mode: the choice lasts for this page */
  }
}

export interface ConfigScreen {
  /** Documents with unsaved changes, by editor id. */
  dirty: Record<string, boolean>
  anyDirty: Readonly<Ref<boolean>>
}

const KEY: InjectionKey<ConfigScreen> = Symbol('config-screen')

export function provideConfigScreen(): ConfigScreen {
  const dirty = reactive<Record<string, boolean>>({})
  const screen: ConfigScreen = { dirty, anyDirty: computed(() => Object.values(dirty).some(Boolean)) }
  provide(KEY, screen)
  return screen
}

/** Reports an editor's unsaved state to the screen it sits in (no-op outside one). */
export function reportDirty(id: string, dirty: () => boolean): void {
  const screen = inject(KEY, null)
  if (!screen) return
  const stop = watch(dirty, (v) => (screen.dirty[id] = v), { immediate: true })
  onBeforeUnmount(() => {
    stop()
    delete screen.dirty[id]
  })
}
