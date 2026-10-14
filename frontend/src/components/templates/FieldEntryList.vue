<script setup lang="ts">
import Button from 'primevue/button'
import { computed, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { humanize } from '@/runtime/i18nText'
import { useSession } from '@/stores/session'
import { fieldTreeApi, labelOf, type PathNode } from '@/views/admin/formconfig/api'
import type { Placeholder } from '@/views/admin/notifications/api'
import FormatPicker from './FormatPicker.vue'
import { entry, splitEntry, valueKind, type Format } from './formats'
import PlaceholderPicker from './PlaceholderPicker.vue'

/**
 * The values a field list or a table of linked records shows, in order, each
 * with how it is shown (`path|format`, specification §4.17). Values are
 * chosen from the field tree, never typed.
 */
const props = withDefaults(defineProps<{ form?: string | null; tree?: PathNode[] | null; system?: Placeholder[]; errors: Record<string, string>; path: string; testid: string; addLabel?: string }>(), {
  form: null,
  tree: null,
  system: () => [],
  addLabel: undefined,
})
const items = defineModel<string[]>({ required: true })
const { t, locale } = useI18n()
const session = useSession()
const dl = computed(() => session.boot?.default_locale ?? 'en')

const loaded = ref<PathNode[]>([])
watch(
  () => props.form,
  async (f) => {
    loaded.value = !props.tree && f ? await fieldTreeApi.load(f).catch(() => []) : []
  },
  { immediate: true },
)
const nodes = computed(() => props.tree ?? loaded.value)
const byPath = computed(() => {
  const out = new Map<string, PathNode>()
  const walk = (list: PathNode[]) => list.forEach((n) => (out.set(n.path.join('.'), n), walk(n.children)))
  walk(nodes.value)
  return out
})
function labelFor(path: string): string {
  const sys = props.system.find((s) => s.token === path)
  if (sys) return sys.label
  const parts = path.split('.')
  return parts
    .map((_, i) => byPath.value.get(parts.slice(0, i + 1).join('.')))
    .map((n, i) => (n ? labelOf(n.label, locale.value, n.key, dl.value) : humanize(parts[i] ?? '')))
    .join(' › ')
}
function describe(f: Format): string {
  const parts: string[] = []
  const style = f.date ?? f.datetime
  if (style) parts.push(t(`tpl.format.date_${style}`))
  if (f.number !== undefined) parts.push(t('tpl.format.decimals_n', { n: f.number }))
  if (f.currency) parts.push(f.currency)
  if (f.digits) parts.push(t(`tpl.format.digits_${f.digits}`))
  return parts.join(' · ')
}
const rows = computed(() => items.value.map((v) => ({ ...splitEntry(v), raw: v })))
const editing = ref<number | null>(null)
function setFormat(i: number, f: Format): void {
  const next = [...items.value]
  next[i] = entry(rows.value[i]!.path, f)
  items.value = next
}
function kindOf(path: string) {
  return valueKind(byPath.value.get(path)?.storage ?? (/@(created|updated)_at$/.test(path) ? 'datetime' : null))
}
function move(i: number, d: -1 | 1): void {
  const j = i + d
  if (j < 0 || j >= items.value.length) return
  const next = [...items.value]
  ;[next[i], next[j]] = [next[j]!, next[i]!]
  items.value = next
}
const add = (path: string, f: Format) => (items.value = [...items.value, entry(path, f)])
</script>

<template>
  <div class="flex flex-col gap-2" :data-testid="testid">
    <p v-if="errors[path]" class="m-0 text-xs text-danger">{{ errors[path] }}</p>
    <p v-if="!items.length" class="m-0 text-sm text-muted-color italic">{{ t('tpl.entries_empty') }}</p>
    <ol v-else class="tpl-entries">
      <li v-for="(r, i) in rows" :key="`${i}-${r.raw}`" class="tpl-entry" :data-testid="`${testid}-${i}`">
        <div class="flex flex-wrap items-center gap-2">
          <span class="font-medium flex-1 min-w-0" dir="auto">{{ labelFor(r.path) }}</span>
          <span v-if="describe(r.format)" class="text-xs text-muted-color">{{ describe(r.format) }}</span>
          <Button
            icon="pi pi-sliders-h"
            text
            rounded
            size="small"
            severity="secondary"
            :aria-label="t('tpl.format.change')"
            :aria-expanded="editing === i"
            @click="editing = editing === i ? null : i"
          />
          <Button icon="pi pi-arrow-up" text rounded size="small" severity="secondary" :disabled="i === 0" :aria-label="t('tpl.move_up')" @click="move(i, -1)" />
          <Button icon="pi pi-arrow-down" text rounded size="small" severity="secondary" :disabled="i === items.length - 1" :aria-label="t('tpl.move_down')" @click="move(i, 1)" />
          <Button icon="pi pi-times" text rounded size="small" severity="secondary" :aria-label="t('tpl.remove_entry')" @click="items = items.filter((_, k) => k !== i)" />
        </div>
        <FormatPicker v-if="editing === i" :model-value="r.format" :kind="kindOf(r.path)" :id-prefix="`${testid}-${i}-fmt`" @update:model-value="(f: Format) => setFormat(i, f)" />
        <p v-if="errors[`${path}.${i}`]" class="m-0 text-xs text-danger">{{ errors[`${path}.${i}`] }}</p>
      </li>
    </ol>
    <PlaceholderPicker :form="form" :tree="tree" :system="system" :label="addLabel ?? t('tpl.add_value')" icon="pi pi-plus" :testid="`${testid}-add`" @choose="add" />
  </div>
</template>

<style scoped>
.tpl-entries {
  list-style: none;
  margin: 0;
  padding: 0;
  display: flex;
  flex-direction: column;
  border: 1px solid var(--border);
  border-radius: var(--radius-card);
}
.tpl-entry {
  display: flex;
  flex-direction: column;
  gap: 0.5rem;
  padding: 0.375rem 0.75rem;
}
.tpl-entry + .tpl-entry {
  border-top: 1px solid var(--border);
}
</style>
