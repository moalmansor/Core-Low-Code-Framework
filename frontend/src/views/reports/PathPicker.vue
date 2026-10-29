<script setup lang="ts">
import Select from 'primevue/select'
import { computed, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { reportsApi, type TreeItem } from './api'

/**
 * Picks a field through relations, one level at a time: the form's own
 * fields, the forms it links to, the forms that link to it and its repeating
 * sections. `want` narrows the choices to numbers or dates.
 */
const props = withDefaults(defineProps<{ form: string; want?: 'any' | 'numeric' | 'date'; inputId?: string; invalid?: boolean }>(), { want: 'any', inputId: undefined })
const model = defineModel<string[] | null>({ default: null })
const { t } = useI18n()
const levels = ref<{ prefix: string[]; items: TreeItem[] }[]>([])
const chosen = ref<string[]>([])
const cache = new Map<string, TreeItem[]>()

async function level(prefix: string[]): Promise<TreeItem[]> {
  const key = `${props.form}|${prefix.join('/')}`
  if (!cache.has(key)) cache.set(key, await reportsApi.fields(props.form, prefix))
  return cache.get(key)!
}
const keyOf = (i: TreeItem) => `${i.kind}:${i.path.join('/')}`

/** Rebuilds the chain of levels for the current path. */
async function rebuild(): Promise<void> {
  const path = model.value ?? []
  const out: { prefix: string[]; items: TreeItem[] }[] = [{ prefix: [], items: await level([]) }]
  const picks: string[] = []
  for (let depth = 0; depth < path.length; depth++) {
    const items = out[depth]!.items
    const last = depth === path.length - 1
    const hit = items.find((i) => i.path.length === depth + 1 && i.path[depth] === path[depth] && (last ? i.kind === 'field' || i.kind === 'system' : i.kind !== 'field' && i.kind !== 'system'))
    if (!hit) break
    picks.push(keyOf(hit))
    if (!last) out.push({ prefix: hit.path, items: await level(hit.path) })
  }
  levels.value = out
  chosen.value = picks
}
watch(
  () => props.form,
  () => {
    cache.clear()
    void rebuild()
  },
  { immediate: true },
)
watch(model, (v, old) => {
  if (JSON.stringify(v) !== JSON.stringify(old)) void rebuild()
})

function usable(i: TreeItem): boolean {
  if (i.kind !== 'field' && i.kind !== 'system') return true
  if (props.want === 'numeric') return !!i.numeric
  if (props.want === 'date') return !!i.date
  return true
}
function options(items: TreeItem[]) {
  return items.filter(usable).map((i) => ({
    value: keyOf(i),
    label: i.kind === 'field' || i.kind === 'system' ? i.label : `${i.label} › ${i.form ?? ''}`.replace(/ › $/, ' ›'),
    group: i.kind === 'field' || i.kind === 'system' ? t('rep.pick_fields') : t('rep.pick_links'),
  }))
}
async function pick(depth: number, value: string): Promise<void> {
  const item = levels.value[depth]!.items.find((i) => keyOf(i) === value)
  if (!item) return
  chosen.value = [...chosen.value.slice(0, depth), value]
  levels.value = levels.value.slice(0, depth + 1)
  if (item.kind === 'field' || item.kind === 'system') {
    model.value = item.path
    return
  }
  model.value = null
  levels.value.push({ prefix: item.path, items: await level(item.path) })
}
const summary = computed(() => (model.value ? '' : t('rep.pick_continue')))
</script>

<template>
  <div class="flex flex-wrap items-center gap-2" data-testid="path-picker">
    <template v-for="(l, depth) in levels" :key="l.prefix.join('/')">
      <i v-if="depth > 0" class="pi pi-angle-right rtl:rotate-180 text-muted-color text-xs" aria-hidden="true" />
      <Select
        :model-value="chosen[depth] ?? null"
        :options="options(l.items)"
        option-label="label"
        option-value="value"
        size="small"
        filter
        :input-id="depth === 0 ? inputId : undefined"
        :invalid="invalid"
        :placeholder="t('rep.pick_field')"
        :aria-label="depth === 0 ? t('rep.pick_field') : t('rep.pick_linked_field')"
        class="min-w-[10rem] max-w-full"
        :data-testid="`path-level-${depth}`"
        @update:model-value="(v: string) => pick(depth, v)"
      />
    </template>
    <span v-if="summary && chosen.length" class="text-xs text-muted-color">{{ summary }}</span>
  </div>
</template>
