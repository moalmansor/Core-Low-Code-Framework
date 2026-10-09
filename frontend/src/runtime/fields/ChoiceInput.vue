<script setup lang="ts">
import AutoComplete from 'primevue/autocomplete'
import Checkbox from 'primevue/checkbox'
import Message from 'primevue/message'
import MultiSelect from 'primevue/multiselect'
import RadioButton from 'primevue/radiobutton'
import Select from 'primevue/select'
import SelectButton from 'primevue/selectbutton'
import { computed, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import type { OptionItem } from '../api'
import { useRenderer } from '../context'
import { pickText } from '../i18nText'
import { useOptions } from '../options'
import type { InputProps } from './props'

/**
 * Choice fields: dropdowns (searchable, grouped, cascading), radio and button
 * groups, color palettes, multi-selects with chips, checkbox groups and tags.
 * Static lists come from the definition; collections, forms, queries and
 * user/role/department sources come from the options endpoint.
 */
const props = defineProps<InputProps>()
const emit = defineEmits<{ 'update:modelValue': [value: unknown]; focus: []; blur: [] }>()
const ctx = useRenderer()
const { t } = useI18n()
const opts = useOptions(
  () => props.field,
  () => props.row,
)

const type = computed(() => props.field.type)
const multiple = computed(() => ctx.index.value.isList(props.field) || ['select_multiple', 'checkbox_group', 'multi_select_chips', 'tags'].includes(type.value))
const allowCustom = computed(() => props.field.options?.allowCustom === true)
const placeholder = computed(() => pickText(props.field.i18n.placeholder, ctx.locale.value) ?? t('runtime.choose'))
const selected = computed<string[]>(() => {
  const v = props.modelValue
  if (v === null || v === undefined || v === '') return []
  return (Array.isArray(v) ? v : [v]).map(String)
})
const titles = computed(() => ctx.references.value[props.field.key] ?? {})

/** Options plus the current values (so a selected remote or custom value always has a label). */
const items = computed<OptionItem[]>(() => {
  const list = [...opts.items.value]
  for (const v of selected.value) if (!list.some((o) => o.value === v)) list.push({ value: v, label: titles.value[v] ?? v })
  return list
})
const grouped = computed(() => {
  if (type.value !== 'select_grouped' && !props.field.options?.groupBy) return null
  const groups = new Map<string, OptionItem[]>()
  for (const o of items.value) groups.set(o.group ?? '', [...(groups.get(o.group ?? '') ?? []), o])
  return [...groups.entries()].map(([label, children]) => ({ label: label || t('runtime.other_options'), children }))
})
const searchable = computed(
  () => type.value === 'dropdown_search' || props.field.options?.searchable === true || items.value.length > 10 || !['static', undefined].includes(props.field.options?.source),
)
const remote = computed(() => (props.field.options?.source ?? 'static') !== 'static' || ctx.index.value.isReference(props.field))

// Cascading: a new parent value clears a choice that depended on the old one.
watch(opts.depends, (now, before) => {
  if (now === before || !selected.value.length) return
  if (!remote.value) {
    const fits = (v: string) => (props.field.options?.static ?? []).some((o) => o.value === v && (o.parent === null || o.parent === undefined || o.parent === now))
    if (selected.value.every(fits)) return
  }
  emit('update:modelValue', null)
})

function setSingle(v: unknown): void {
  emit('update:modelValue', v === null || v === undefined || v === '' ? null : String(v))
}
function setMany(v: unknown): void {
  const list = Array.isArray(v) ? v.map((x) => (typeof x === 'object' && x !== null ? (x as OptionItem).value : String(x))) : []
  emit('update:modelValue', list.length ? [...new Set(list)] : null)
}
function toggle(value: string, on: boolean): void {
  setMany(on ? [...selected.value, value] : selected.value.filter((v) => v !== value))
}

// Tags: suggestions plus free text when custom values are allowed.
const tagSuggestions = ref<OptionItem[]>([])
async function completeTags(e: { query: string }): Promise<void> {
  await opts.search(e.query)
  const q = e.query.trim()
  const list = opts.items.value.filter((o) => !selected.value.includes(o.value))
  tagSuggestions.value = allowCustom.value && q && !list.some((o) => o.value === q) ? [{ value: q, label: q }, ...list] : list
}
const tagModel = computed(() => selected.value.map((v) => items.value.find((o) => o.value === v) ?? { value: v, label: v }))
let searchTimer: number | undefined
function onFilter(e: { value: string }): void {
  if (!remote.value) return
  window.clearTimeout(searchTimer)
  searchTimer = window.setTimeout(() => opts.search(e.value), 300)
}
</script>

<template>
  <div>
    <Message v-if="opts.unavailable.value" severity="secondary" size="small" class="mb-2">{{ t('runtime.options_need_publish') }}</Message>

    <!-- Radio groups -->
    <div v-if="type === 'radio' || type === 'radio_group'" role="radiogroup" class="flex flex-wrap gap-x-5 gap-y-2" :aria-invalid="invalid" @focusin="emit('focus')" @focusout="emit('blur')">
      <label v-for="o in items" :key="o.value" class="flex items-center gap-2 cursor-pointer">
        <RadioButton :model-value="selected[0] ?? null" :value="o.value" :name="inputId" :disabled="disabled" :invalid="invalid" @update:model-value="setSingle" />
        <i v-if="o.icon" :class="o.icon" :style="o.color ? { color: o.color } : undefined" />
        <span>{{ o.label }}</span>
      </label>
    </div>

    <!-- Button group -->
    <SelectButton
      v-else-if="type === 'button_group'"
      :model-value="selected[0] ?? null"
      :options="items"
      option-label="label"
      option-value="value"
      :disabled="disabled"
      :invalid="invalid"
      :allow-empty="!required"
      @update:model-value="setSingle"
    />

    <!-- Color palette -->
    <div v-else-if="type === 'color_palette'" role="radiogroup" class="flex flex-wrap gap-2" @focusin="emit('focus')" @focusout="emit('blur')">
      <button
        v-for="o in items"
        :key="o.value"
        type="button"
        role="radio"
        :aria-checked="selected[0] === o.value"
        :aria-label="o.label"
        :title="o.label"
        :disabled="disabled"
        class="w-9 h-9 rounded-full border-2"
        :class="selected[0] === o.value ? 'border-primary ring-2 ring-primary' : 'border-line-strong'"
        :style="{ background: o.color ?? o.value }"
        @click="setSingle(selected[0] === o.value && !required ? null : o.value)"
      />
    </div>

    <!-- Checkbox group -->
    <div v-else-if="type === 'checkbox_group'" class="flex flex-wrap gap-x-5 gap-y-2" role="group" @focusin="emit('focus')" @focusout="emit('blur')">
      <label v-for="o in items" :key="o.value" class="flex items-center gap-2 cursor-pointer">
        <Checkbox :model-value="selected.includes(o.value)" binary :disabled="disabled" :invalid="invalid" @update:model-value="(on: boolean) => toggle(o.value, on)" />
        <span>{{ o.label }}</span>
      </label>
    </div>

    <!-- Tags -->
    <AutoComplete
      v-else-if="type === 'tags'"
      :input-id="inputId"
      :model-value="tagModel"
      multiple
      fluid
      :suggestions="tagSuggestions"
      option-label="label"
      :disabled="disabled"
      :invalid="invalid"
      :placeholder="placeholder"
      :typeahead="true"
      @complete="completeTags"
      @update:model-value="setMany"
      @focus="emit('focus')"
      @blur="emit('blur')"
    />

    <!-- Multi-select -->
    <MultiSelect
      v-else-if="multiple"
      :input-id="inputId"
      :model-value="selected"
      :options="grouped ?? items"
      option-label="label"
      option-value="value"
      :option-group-label="grouped ? 'label' : undefined"
      :option-group-children="grouped ? 'children' : undefined"
      :display="type === 'multi_select_chips' ? 'chip' : 'comma'"
      :filter="searchable"
      :loading="opts.loading.value"
      :selection-limit="field.options?.max ?? undefined"
      fluid
      :disabled="disabled || opts.waitingForParent.value"
      :invalid="invalid"
      :placeholder="opts.waitingForParent.value ? t('runtime.choose_parent_first') : placeholder"
      :empty-message="t('runtime.no_options')"
      @update:model-value="setMany"
      @filter="onFilter"
      @focus="emit('focus')"
      @blur="emit('blur')"
    />

    <!-- Single select (searchable, grouped, cascading, custom values) -->
    <Select
      v-else
      :input-id="inputId"
      :model-value="selected[0] ?? null"
      :options="grouped ?? items"
      option-label="label"
      option-value="value"
      :option-group-label="grouped ? 'label' : undefined"
      :option-group-children="grouped ? 'children' : undefined"
      :filter="searchable"
      :editable="allowCustom"
      :loading="opts.loading.value"
      :show-clear="!required"
      fluid
      :disabled="disabled || opts.waitingForParent.value"
      :invalid="invalid"
      :placeholder="opts.waitingForParent.value ? t('runtime.choose_parent_first') : placeholder"
      :empty-message="t('runtime.no_options')"
      :size="size"
      @update:model-value="setSingle"
      @filter="onFilter"
      @focus="emit('focus')"
      @blur="emit('blur')"
    >
      <template #option="{ option }">
        <span class="flex items-center gap-2">
          <span v-if="option.color" class="inline-block w-3 h-3 rounded-full" :style="{ background: option.color }" />
          <i v-if="option.icon" :class="option.icon" />
          {{ option.label }}
        </span>
      </template>
    </Select>
  </div>
</template>
