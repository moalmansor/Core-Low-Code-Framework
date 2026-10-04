<script setup lang="ts">
import Slider from 'primevue/slider'
import { computed } from 'vue'
import { useRenderer } from '../context'
import { pickText } from '../i18nText'
import { prop, type InputProps } from './props'

/** Slider / range with minimum, maximum and step (validation number rules, else ui props) and min/max labels. */
const props = defineProps<InputProps>()
const emit = defineEmits<{ 'update:modelValue': [value: unknown]; focus: []; blur: [] }>()
const ctx = useRenderer()

const min = computed(() => Number(props.field.validation.number?.min ?? prop(props.field, 'min', 0)))
const max = computed(() => Number(props.field.validation.number?.max ?? prop(props.field, 'max', 100)))
const step = computed(() => Number(props.field.validation.number?.step ?? prop(props.field, 'step', 1)) || 1)
const value = computed(() => (props.modelValue === null || props.modelValue === undefined || props.modelValue === '' ? min.value : Number(props.modelValue)))
const labelOf = (name: string): string | null => {
  const v = props.field.ui.props?.[name]
  return v && typeof v === 'object' && !Array.isArray(v) ? pickText(v as Record<string, string>, ctx.locale.value) : null
}
const minLabel = computed(() => labelOf('minLabel') ?? String(min.value))
const maxLabel = computed(() => labelOf('maxLabel') ?? String(max.value))
const hasValue = computed(() => props.modelValue !== null && props.modelValue !== undefined && props.modelValue !== '')

function set(v: number | number[]): void {
  const n = Array.isArray(v) ? v[0]! : v
  const decimals = (String(step.value).split('.')[1] ?? '').length
  emit('update:modelValue', n.toFixed(decimals))
}
</script>

<template>
  <div class="flex flex-col gap-2 px-2" @focusin="emit('focus')" @focusout="emit('blur')">
    <div class="flex items-center gap-3">
      <Slider
        :model-value="value"
        :min="min"
        :max="max"
        :step="step"
        :disabled="disabled"
        class="flex-1"
        :aria-label="pickText(field.i18n.label, ctx.locale.value) ?? field.key"
        @update:model-value="set"
      />
      <output :id="inputId" class="min-w-12 text-end font-medium ltr-value" :class="{ 'text-muted-color': !hasValue }">{{ hasValue ? modelValue : '—' }}</output>
    </div>
    <div class="flex justify-between text-xs text-muted-color">
      <span>{{ minLabel }}</span
      ><span>{{ maxLabel }}</span>
    </div>
  </div>
</template>
