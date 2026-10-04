import { computed, ref, watch, type ComputedRef, type Ref } from 'vue'
import { fetchOptions, type OptionItem } from './api'
import { useRenderer, valueOf } from './context'
import { pickText } from './i18nText'
import type { RowRef } from './rules'
import type { ClientField } from './types'

/**
 * Options of a choice, tag or lookup field. Static lists come from the
 * definition (active options, cascading by `parent`, minus options hidden
 * by a condition); every other source is served by
 * `GET /r/{form}/options/{field}` (search, paging, cascading parent value),
 * which needs a published form.
 */
export interface OptionsState {
  items: Ref<OptionItem[]>
  total: Ref<number>
  loading: Ref<boolean>
  /** Remote options need a published form (absent in builder previews). */
  unavailable: ComputedRef<boolean>
  /** Parent value of a cascading field; null when the parent is empty. */
  depends: ComputedRef<string | null>
  /** The field cascades and its parent has no value yet. */
  waitingForParent: ComputedRef<boolean>
  search(q: string): Promise<void>
  loadMore(): Promise<void>
}

export function isStaticSource(field: ClientField, isReference: boolean): boolean {
  return (field.options?.source ?? 'static') === 'static' && !isReference
}

export function useOptions(field: () => ClientField, row: () => RowRef | null): OptionsState {
  const ctx = useRenderer()
  const items = ref<OptionItem[]>([])
  const total = ref(0)
  const loading = ref(false)
  const query = ref('')
  const page = ref(1)

  const isStatic = computed(() => isStaticSource(field(), ctx.index.value.isReference(field())))
  const parentField = computed(() => {
    const dep = field().options?.dependsOn
    return dep ? (ctx.index.value.fields.get(dep) ?? null) : null
  })
  const depends = computed<string | null>(() => {
    const p = parentField.value
    if (p === null) return null
    // The parent sits in the same row when both are row fields, else in the record.
    const parentRow = row() !== null && ctx.index.value.fieldRepeater.has(p.uuid) ? row() : null
    const v = valueOf(ctx.values.value, p, parentRow)
    if (v === null || v === undefined || v === '') return null
    return Array.isArray(v) ? (v.length ? String(v[0]) : null) : String(v)
  })
  const waitingForParent = computed(() => parentField.value !== null && depends.value === null)
  const unavailable = computed(() => !isStatic.value && !ctx.formUuid.value)

  function staticItems(): OptionItem[] {
    const f = field()
    const state = ctx.state.value
    const r = row()
    const locale = ctx.locale.value
    let list = [...(f.options?.static ?? [])].filter((o) => o.active !== false)
    if (parentField.value !== null) list = list.filter((o) => o.parent === null || o.parent === undefined || o.parent === depends.value)
    list = list.filter((o) => !state.flag(o.uuid, 'hidden', r))
    list.sort((a, b) => (a.order ?? 0) - (b.order ?? 0))
    const q = query.value.trim().toLocaleLowerCase()
    return list
      .map((o) => ({ value: o.value, label: pickText(o.i18n?.label, locale) ?? o.value, group: o.group ?? null, color: o.color ?? null, icon: o.icon ?? null, parent: o.parent ?? null, uuid: o.uuid }))
      .filter((o) => q === '' || o.label.toLocaleLowerCase().includes(q) || o.value.toLocaleLowerCase().includes(q))
  }

  async function load(append: boolean): Promise<void> {
    if (isStatic.value) {
      items.value = staticItems()
      total.value = items.value.length
      return
    }
    if (unavailable.value || waitingForParent.value) {
      items.value = []
      total.value = 0
      return
    }
    loading.value = true
    try {
      const res = await fetchOptions(ctx.formUuid.value!, field().key, { q: query.value, depends: depends.value, page: page.value })
      items.value = append ? [...items.value, ...res.items] : res.items
      total.value = res.total
      for (const o of res.items) ctx.rememberTitle(field().key, o.value, o.label)
    } catch {
      if (!append) items.value = []
    } finally {
      loading.value = false
    }
  }

  watch(
    () => [isStatic.value ? JSON.stringify(staticItems()) : '', depends.value, ctx.optionsVersion(field().uuid), ctx.formUuid.value],
    () => {
      page.value = 1
      void load(false)
    },
    { immediate: true },
  )

  return {
    items,
    total,
    loading,
    unavailable,
    depends,
    waitingForParent,
    async search(q: string) {
      query.value = q
      page.value = 1
      await load(false)
    },
    async loadMore() {
      if (items.value.length >= total.value) return
      page.value += 1
      await load(true)
    },
  }
}
