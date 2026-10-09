<script setup lang="ts">
import AutoComplete from 'primevue/autocomplete'
import Button from 'primevue/button'
import Message from 'primevue/message'
import { computed, reactive, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { get } from '@/api/http'
import type { OptionItem } from '../api'
import { useRenderer } from '../context'
import { pickText } from '../i18nText'
import { useOptions } from '../options'
import ReferenceDrawer from '../ReferenceDrawer.vue'
import type { RecordPayload } from '../types'
import type { InputProps } from './props'

/**
 * Lookup and record pickers (form/collection records, users, roles,
 * departments, countries, cities): searchable, paged, cascading by a parent
 * field, with a preview card of the chosen record, opening it in a side
 * drawer, and auto-filling configured fields from it.
 */
const props = defineProps<InputProps>()
const emit = defineEmits<{ 'update:modelValue': [value: unknown]; focus: []; blur: [] }>()
const ctx = useRenderer()
const { t } = useI18n()
const opts = useOptions(
  () => props.field,
  () => props.row,
)

const multiple = computed(() => ctx.index.value.isMultiReference(props.field))
const selected = computed<string[]>(() => {
  const v = props.modelValue
  if (v === null || v === undefined || v === '') return []
  return (Array.isArray(v) ? v : [v]).map(String)
})
const titles = computed(() => ctx.references.value[props.field.key] ?? {})
const previews = reactive<Record<string, Record<string, unknown>>>({})
const suggestions = ref<OptionItem[]>([])
const model = computed(() => {
  const items = selected.value.map((v) => ({ value: v, label: titles.value[v] ?? v }))
  return multiple.value ? items : (items[0] ?? null)
})
const targetForm = computed(() => ctx.index.value.relationOf(props.field)?.target ?? null)
const placeholder = computed(() => pickText(props.field.i18n.placeholder, ctx.locale.value) ?? t('runtime.search_records'))

async function complete(e: { query: string }): Promise<void> {
  await opts.search(e.query)
  suggestions.value = opts.items.value
  for (const o of opts.items.value) if (o.preview) previews[o.value] = o.preview
}

watch(opts.depends, (now, before) => {
  if (now !== before && selected.value.length) emit('update:modelValue', null)
})

function onChange(v: unknown): void {
  if (v === null || v === undefined || v === '') {
    emit('update:modelValue', null)
    return
  }
  if (typeof v === 'string') return // free text while typing; only picked records count
  const items = (Array.isArray(v) ? v : [v]).filter((x): x is OptionItem => typeof x === 'object' && x !== null && 'value' in x)
  for (const o of items) ctx.rememberTitle(props.field.key, o.value, o.label)
  const uuids = [...new Set(items.map((o) => o.value))]
  emit('update:modelValue', multiple.value ? (uuids.length ? uuids : null) : (uuids[0] ?? null))
  if (!multiple.value && uuids[0]) void autofill(uuids[0])
}

/** Copies configured values from the chosen record into this form (one hop). */
async function autofill(uuid: string): Promise<void> {
  const rules = props.field.behavior.autofill ?? []
  if (!rules.length || !targetForm.value) return
  let record: RecordPayload
  try {
    record = (await get<{ data: RecordPayload }>(`/r/${targetForm.value}/${uuid}`)).data
  } catch {
    return
  }
  for (const rule of rules) {
    const target = ctx.index.value.fields.get(rule.to)
    if (!target || rule.from.length !== 1) continue
    const value = record.values[rule.from[0]!] ?? null
    const row = props.row !== null && ctx.index.value.fieldRepeater.has(target.uuid) ? props.row : null
    const current = row === null ? ctx.values.value[target.key] : (ctx.values.value[row[0]] as Record<string, unknown>[] | undefined)?.[row[1]]?.[target.key]
    if (!rule.overwrite && current !== null && current !== undefined && current !== '') continue
    const titlesOfSource = record.references?.[rule.from[0]!]
    if (titlesOfSource) for (const [u, title] of Object.entries(titlesOfSource)) ctx.rememberTitle(target.key, u, title)
    ctx.setValue(target, value, row)
  }
}

const previewKeys = computed(() => (props.field.options?.preview ?? []).map((p) => p[0]!).filter(Boolean))
const preview = computed(() => {
  if (multiple.value || !selected.value[0] || !previewKeys.value.length) return null
  const p = previews[selected.value[0]]
  return p ? previewKeys.value.filter((k) => p[k] !== null && p[k] !== undefined && p[k] !== '').map((k) => ({ key: k, value: typeof p[k] === 'object' ? JSON.stringify(p[k]) : String(p[k]) })) : null
})
const drawer = ref<string | null>(null)
</script>

<template>
  <div>
    <Message v-if="opts.unavailable.value" severity="secondary" size="small" class="mb-2">{{ t('runtime.options_need_publish') }}</Message>
    <div class="flex gap-2">
      <AutoComplete
        :input-id="inputId"
        :model-value="model"
        :suggestions="suggestions"
        option-label="label"
        :multiple="multiple"
        dropdown
        force-selection
        fluid
        class="flex-1"
        :delay="300"
        :loading="opts.loading.value"
        :disabled="disabled || opts.unavailable.value || opts.waitingForParent.value"
        :invalid="invalid"
        :placeholder="opts.waitingForParent.value ? t('runtime.choose_parent_first') : placeholder"
        :empty-search-message="t('runtime.no_options')"
        @complete="complete"
        @update:model-value="onChange"
        @focus="emit('focus')"
        @blur="emit('blur')"
      >
        <template #option="{ option }">
          <div class="flex flex-col">
            <span>{{ option.label }}</span>
            <span v-if="option.preview && previewKeys.length" class="text-xs text-muted-color">
              {{
                previewKeys
                  .map((k) => option.preview[k])
                  .filter((x) => x !== null && x !== undefined && x !== '')
                  .join(' · ')
              }}
            </span>
          </div>
        </template>
      </AutoComplete>
      <Button v-if="targetForm && !multiple && selected[0]" type="button" icon="pi pi-window-maximize" outlined :aria-label="t('runtime.open_reference')" @click="drawer = selected[0]!" />
    </div>
    <dl v-if="preview && preview.length" class="mt-2 rounded-md border border-line p-2 text-sm grid grid-cols-[auto_1fr] gap-x-3 gap-y-1" data-testid="reference-preview">
      <template v-for="p in preview" :key="p.key">
        <dt class="text-muted-color">{{ p.key }}</dt>
        <dd>{{ p.value }}</dd>
      </template>
    </dl>
    <div v-if="multiple && targetForm && selected.length" class="flex flex-wrap gap-1 mt-1">
      <Button v-for="u in selected" :key="u" type="button" :label="titles[u] ?? u" icon="pi pi-window-maximize" text size="small" @click="drawer = u" />
    </div>
    <ReferenceDrawer v-if="targetForm && drawer" :form="targetForm" :record="drawer" @close="drawer = null" />
  </div>
</template>
