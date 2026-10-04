<script setup lang="ts">
import InputText from 'primevue/inputtext'
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { dateToInput, datetimeToInput, inputToDate, inputToDatetime, inputToTime, timeToInput } from '../dates'
import type { InputProps } from './props'

/** Date, time and date-time ranges: `{from, to}` with the start not after the end. */
const props = defineProps<InputProps>()
const emit = defineEmits<{ 'update:modelValue': [value: unknown]; focus: []; blur: [] }>()
const { t } = useI18n()

const kind = computed(() => ({ time_range: 'time', datetime_range: 'datetime-local' })[props.field.type] ?? 'date')
const timezone = computed(() => props.field.behavior.date?.timezone ?? 'user')
const range = computed(() => (typeof props.modelValue === 'object' && props.modelValue !== null ? (props.modelValue as { from?: string | null; to?: string | null }) : {}))
const toInput = (v: unknown) => (kind.value === 'time' ? timeToInput(v) : kind.value === 'datetime-local' ? datetimeToInput(v, timezone.value) : dateToInput(v))
const fromInput = (s: string) => (kind.value === 'time' ? inputToTime(s) : kind.value === 'datetime-local' ? inputToDatetime(s, timezone.value) : inputToDate(s))

function set(part: 'from' | 'to', raw: string | undefined): void {
  const v = raw ? fromInput(raw) : null
  if (raw && v === null) return
  const next = { from: range.value.from ?? null, to: range.value.to ?? null, [part]: v }
  emit('update:modelValue', next.from === null && next.to === null ? null : next)
}
</script>

<template>
  <div class="grid gap-2 sm:grid-cols-2" @focusin="emit('focus')" @focusout="emit('blur')">
    <label class="flex flex-col gap-1">
      <span class="text-xs text-muted-color">{{ t('runtime.range_from') }}</span>
      <InputText
        :id="inputId"
        :model-value="toInput(range.from)"
        :type="kind"
        fluid
        :disabled="disabled"
        :invalid="invalid"
        :max="toInput(range.to) || undefined"
        @update:model-value="(v) => set('from', v)"
      />
    </label>
    <label class="flex flex-col gap-1">
      <span class="text-xs text-muted-color">{{ t('runtime.range_to') }}</span>
      <InputText :model-value="toInput(range.to)" :type="kind" fluid :disabled="disabled" :invalid="invalid" :min="toInput(range.from) || undefined" @update:model-value="(v) => set('to', v)" />
    </label>
  </div>
</template>
