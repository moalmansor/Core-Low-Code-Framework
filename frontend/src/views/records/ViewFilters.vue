<script setup lang="ts">
import AutoComplete from 'primevue/autocomplete'
import Button from 'primevue/button'
import InputText from 'primevue/inputtext'
import MultiSelect from 'primevue/multiselect'
import Select from 'primevue/select'
import { computed, reactive } from 'vue'
import { useI18n } from 'vue-i18n'
import { fetchOptions, type OptionItem } from '@/runtime/api'
import type { ViewCondition, ViewFilter } from './viewState'

/**
 * The filters of a table view (quick filters first): each offers the
 * operators the administrator allowed for it, with an input that suits its
 * type. Lookups on this form's own fields search their records.
 */
const props = defineProps<{ filters: ViewFilter[]; form: string }>()
const model = defineModel<Record<string, ViewCondition>>({ required: true })
const emit = defineEmits<{ apply: [] }>()
const { t } = useI18n()
const ordered = computed(() => [...props.filters.filter((f) => f.quick), ...props.filters.filter((f) => !f.quick)])

function cond(f: ViewFilter): ViewCondition {
  model.value[f.uuid] ??= { op: f.operators[0] ?? 'equals' }
  return model.value[f.uuid]!
}
function setOp(f: ViewFilter, op: string): void {
  model.value[f.uuid] = { op }
  if (op === 'is_empty') emit('apply')
}
function clear(f: ViewFilter): void {
  model.value[f.uuid] = { op: f.operators[0] ?? 'equals' }
  emit('apply')
}
const opOptions = (f: ViewFilter) => f.operators.map((op) => ({ value: op, label: t(`records.vop.${op}`) }))
const inputType = (f: ViewFilter) => (f.type === 'date_range' ? 'date' : 'text')
const suggestions = reactive<Record<string, OptionItem[]>>({})
const searchable = (f: ViewFilter) => (f.type === 'reference' || f.type === 'user') && f.path.length === 1 && !f.path[0]!.startsWith('@')
async function search(f: ViewFilter, q: string): Promise<void> {
  try {
    suggestions[f.uuid] = (await fetchOptions(props.form, f.path[0]!, { q })).items
  } catch {
    suggestions[f.uuid] = []
  }
}
function pick(f: ViewFilter, v: unknown): void {
  const items = (Array.isArray(v) ? v : []).filter((x): x is OptionItem => !!x && typeof x === 'object' && 'value' in x)
  cond(f).values = items.map((o) => String(o.value))
  labels[f.uuid] = Object.fromEntries(items.map((o) => [String(o.value), o.label]))
}
const labels = reactive<Record<string, Record<string, string>>>({})
const picked = (f: ViewFilter) => (cond(f).values ?? []).map((v) => ({ value: v, label: labels[f.uuid]?.[v] ?? v }))
const booleans = computed(() => [
  { value: 'true', label: t('runtime.yes') },
  { value: 'false', label: t('runtime.no') },
])
</script>

<template>
  <div class="flex flex-col gap-2" data-testid="view-filters">
    <div v-for="f in ordered" :key="f.uuid" class="view-filter" :data-testid="`view-filter-${f.path.join('.')}`">
      <span class="text-sm font-medium truncate">{{ f.label }}<i v-if="f.quick" class="pi pi-bolt text-xs text-muted-color ms-1" :aria-label="t('views.quick')" /></span>
      <Select
        :model-value="cond(f).op"
        :options="opOptions(f)"
        option-label="label"
        option-value="value"
        size="small"
        :disabled="f.operators.length < 2"
        :aria-label="t('records.operator')"
        @update:model-value="(op: string) => setOp(f, op)"
      />
      <span v-if="cond(f).op === 'is_empty'" class="text-sm text-muted-color">—</span>
      <div v-else-if="cond(f).op === 'between'" class="flex gap-2 min-w-0">
        <InputText v-model="cond(f).from" :type="inputType(f)" size="small" class="flex-1 min-w-0 ltr-value" :placeholder="t('runtime.range_from')" :aria-label="t('runtime.range_from')" />
        <InputText v-model="cond(f).to" :type="inputType(f)" size="small" class="flex-1 min-w-0 ltr-value" :placeholder="t('runtime.range_to')" :aria-label="t('runtime.range_to')" />
      </div>
      <MultiSelect
        v-else-if="cond(f).op === 'in' && f.options"
        v-model="cond(f).values"
        :options="f.options"
        option-label="label"
        option-value="value"
        size="small"
        filter
        display="chip"
        :placeholder="t('records.any')"
        :aria-label="t('records.value')"
      />
      <AutoComplete
        v-else-if="cond(f).op === 'in' && searchable(f)"
        :model-value="picked(f)"
        :suggestions="suggestions[f.uuid] ?? []"
        option-label="label"
        multiple
        dropdown
        force-selection
        size="small"
        :placeholder="t('records.any')"
        :aria-label="t('records.value')"
        @complete="(e: { query: string }) => search(f, e.query)"
        @update:model-value="(v: unknown) => pick(f, v)"
      />
      <Select
        v-else-if="f.type === 'boolean'"
        v-model="cond(f).value"
        :options="booleans"
        option-label="label"
        option-value="value"
        size="small"
        show-clear
        :placeholder="t('records.any')"
        :aria-label="t('records.value')"
      />
      <InputText v-else v-model="cond(f).value" size="small" :placeholder="t('records.value_placeholder')" :aria-label="t('records.value')" @keyup.enter="emit('apply')" />
      <Button icon="pi pi-times" text rounded size="small" severity="secondary" :aria-label="t('records.remove_filter')" @click="clear(f)" />
    </div>
  </div>
</template>

<style scoped>
.view-filter {
  display: grid;
  grid-template-columns: minmax(7rem, 10rem) minmax(8rem, 11rem) minmax(0, 1fr) auto;
  gap: 0.5rem;
  align-items: center;
}
@media (max-width: 640px) {
  .view-filter {
    grid-template-columns: minmax(0, 1fr) minmax(0, 1fr) auto;
  }
  .view-filter > :first-child {
    grid-column: 1 / -1;
  }
}
</style>
