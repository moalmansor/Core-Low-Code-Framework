<script setup lang="ts">
import Select from 'primevue/select'
import { computed, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { BREAKPOINTS, type Breakpoint, type Breakpoints } from './types'

/**
 * A value (1–12) per screen size; an empty size inherits the smaller one.
 * One row per size with common choices and Custom, so the rows hold at the
 * panel's width (design system: properties panel). Phone, tablet and desktop
 * show by default; large phone and wide screens on request or when set.
 */
const props = withDefaults(defineProps<{ label: string; presets?: number[] }>(), { presets: () => [12, 6, 4, 3] })
const model = defineModel<Breakpoints | undefined>()
const { t } = useI18n()
const MAIN: Breakpoint[] = ['xs', 'md', 'lg']
const showAll = ref(false)
const rows = computed(() => BREAKPOINTS.filter((bp) => showAll.value || MAIN.includes(bp) || model.value?.[bp] !== undefined))
const hidden = computed(() => BREAKPOINTS.length - rows.value.length)
const custom = ref<Partial<Record<Breakpoint, boolean>>>({})
const allValues = computed(() => [{ value: null, label: t('builder.inherit') }, ...Array.from({ length: 12 }, (_, i) => ({ value: i + 1, label: String(i + 1) }))])

function update(bp: Breakpoint, value: number | null): void {
  const next: Breakpoints = { ...(model.value ?? {}) }
  if (value === null) delete next[bp]
  else next[bp] = value
  model.value = next
}
const isCustom = (bp: Breakpoint) => custom.value[bp] === true || (model.value?.[bp] !== undefined && !props.presets.includes(model.value[bp]!))
</script>

<template>
  <fieldset class="min-w-0 flex flex-col gap-1.5">
    <legend class="text-sm font-medium mb-1">{{ label }}</legend>
    <div v-for="bp in rows" :key="bp" class="bp-row" :data-testid="`bp-${bp}`">
      <span class="text-sm truncate">{{ t(`builder.breakpoint.${bp}`) }}</span>
      <div class="flex items-center gap-1 min-w-0">
        <div class="segmented" role="group" :aria-label="t(`builder.breakpoint.${bp}`)">
          <button type="button" :aria-pressed="model?.[bp] === undefined && !custom[bp]" :title="t('builder.inherit')" @click="(update(bp, null), (custom[bp] = false))">—</button>
          <button v-for="p in presets" :key="p" type="button" :aria-pressed="model?.[bp] === p && !custom[bp]" @click="(update(bp, p), (custom[bp] = false))">{{ p }}</button>
          <button type="button" :aria-pressed="isCustom(bp)" @click="custom[bp] = true">{{ t('builder.custom') }}</button>
        </div>
        <Select
          v-if="isCustom(bp)"
          :model-value="model?.[bp] ?? null"
          :options="allValues"
          option-label="label"
          option-value="value"
          size="small"
          class="w-20"
          :aria-label="`${t(`builder.breakpoint.${bp}`)} ${t('builder.custom')}`"
          @update:model-value="(v: number | null) => update(bp, v)"
        />
      </div>
    </div>
    <button v-if="hidden > 0 || showAll" type="button" class="self-start text-xs text-muted-color hover:text-color" @click="showAll = !showAll">
      {{ showAll ? t('builder.breakpoint.fewer') : t('builder.breakpoint.more', { n: hidden }) }}
    </button>
  </fieldset>
</template>

<style scoped>
.bp-row {
  display: grid;
  grid-template-columns: minmax(4.5rem, 6rem) minmax(0, 1fr);
  gap: 0.5rem;
  align-items: center;
}
.segmented {
  display: inline-flex;
  border: 1px solid var(--border-input);
  border-radius: var(--radius-control);
  overflow: hidden;
}
.segmented button {
  padding: 0.25rem 0.5rem;
  font-size: var(--text-size-xs);
  font-variant-numeric: tabular-nums;
  color: var(--text-muted);
  background: var(--bg-surface);
  border: 0;
  border-inline-end: 1px solid var(--border);
  cursor: pointer;
}
.segmented button:last-child {
  border-inline-end: 0;
}
.segmented button[aria-pressed='true'] {
  background: var(--primary);
  color: var(--on-primary);
}
</style>
