<script setup lang="ts">
import MultiSelect from 'primevue/multiselect'
import Select from 'primevue/select'
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { pick } from './conditions/scope'
import type { FieldDef } from './types'
import { useBuilder } from './useBuilder'

/** Picks one or several fields of the form being built, by uuid. */
const props = withDefaults(defineProps<{ multiple?: boolean; filter?: (f: FieldDef) => boolean; exclude?: string | null; inputId?: string; placeholder?: string }>(), {
  multiple: false,
  filter: undefined,
  exclude: null,
  inputId: undefined,
  placeholder: undefined,
})
const single = defineModel<string | null | undefined>({ default: undefined })
const many = defineModel<string[] | undefined>('values', { default: undefined })
const builder = useBuilder()
const { t } = useI18n()

const options = computed(() =>
  (builder.doc?.fields ?? [])
    .filter((f) => f.uuid !== props.exclude && (props.filter ? props.filter(f) : builder.typeInfo(f.type)?.stored !== false))
    .map((f) => ({ value: f.uuid, label: `${pick(f.i18n?.label, builder.locale, f.key)} (${f.key})` })),
)
</script>

<template>
  <MultiSelect
    v-if="multiple"
    v-model="many"
    :options="options"
    option-label="label"
    option-value="value"
    display="chip"
    filter
    size="small"
    class="w-full"
    :label-id="inputId"
    :placeholder="placeholder ?? t('builder.pick_fields')"
  />
  <Select
    v-else
    v-model="single"
    :options="options"
    option-label="label"
    option-value="value"
    filter
    show-clear
    size="small"
    class="w-full"
    :label-id="inputId"
    :placeholder="placeholder ?? t('builder.pick_field')"
  />
</template>
