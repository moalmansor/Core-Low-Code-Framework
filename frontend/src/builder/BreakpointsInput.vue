<script setup lang="ts">
import Select from 'primevue/select'
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { BREAKPOINTS, type Breakpoint, type Breakpoints } from './types'

/** Grid columns (1–12) per breakpoint; an empty breakpoint inherits the smaller one. */
defineProps<{ label: string }>()
const model = defineModel<Breakpoints | undefined>()
const { t } = useI18n()
const options = computed(() => [{ value: null, label: t('builder.inherit') }, ...Array.from({ length: 12 }, (_, i) => ({ value: i + 1, label: String(i + 1) }))])

function update(bp: Breakpoint, value: number | null): void {
  const next: Breakpoints = { ...(model.value ?? {}) }
  if (value === null) delete next[bp]
  else next[bp] = value
  model.value = next
}
</script>

<template>
  <fieldset class="min-w-0">
    <legend class="text-sm font-medium mb-1">{{ label }}</legend>
    <div class="grid grid-cols-5 gap-1">
      <label v-for="bp in BREAKPOINTS" :key="bp" class="flex flex-col gap-0.5 text-xs text-muted-color">
        <span class="ltr-value">{{ t(`builder.breakpoint.${bp}`) }}</span>
        <Select :model-value="model?.[bp] ?? null" :options="options" option-label="label" option-value="value" size="small" class="w-full" @update:model-value="(v: number | null) => update(bp, v)" />
      </label>
    </div>
  </fieldset>
</template>
