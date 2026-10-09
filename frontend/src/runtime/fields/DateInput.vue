<script setup lang="ts">
import InputText from 'primevue/inputtext'
import { computed } from 'vue'
import { formatDate } from '@/expressions/civil'
import { useRenderer } from '../context'
import { dateToInput, datetimeToInput, hijriText, inputToDate, inputToDatetime, inputToMonth, inputToTime, inputToWeek, monthToInput, timeToInput, weekToInput } from '../dates'
import { baseContext } from '../rules'
import type { InputProps } from './props'

/**
 * Date, month, week, time and local date-time inputs (native pickers, which
 * follow the reader's locale and direction). Values are converted to the API
 * shapes; the Hijri date is shown beside Gregorian when the field asks for
 * the Hijri or dual calendar.
 */
const props = defineProps<InputProps>()
const emit = defineEmits<{ 'update:modelValue': [value: unknown]; focus: []; blur: [] }>()
const ctx = useRenderer()

const kind = computed(() => ({ month: 'month', week: 'week', time: 'time', datetime_local: 'datetime-local' })[props.field.type] ?? 'date')
const timezone = computed(() => props.field.behavior.date?.timezone ?? 'user')
const shown = computed(() => {
  const v = props.modelValue
  switch (kind.value) {
    case 'month':
      return monthToInput(v)
    case 'week':
      return weekToInput(v)
    case 'time':
      return timeToInput(v)
    case 'datetime-local':
      return datetimeToInput(v, timezone.value)
    default:
      return dateToInput(v)
  }
})
const step = computed(() => {
  const minutes = props.field.behavior.date?.timeStep
  return (kind.value === 'time' || kind.value === 'datetime-local') && minutes ? minutes * 60 : undefined
})
const today = computed(() => formatDate(baseContext(ctx.index.value, ctx.env.value).today))
const minAttr = computed(() => (kind.value === 'date' && props.field.validation.date?.noPast ? today.value : undefined))
const maxAttr = computed(() => (kind.value === 'date' && props.field.validation.date?.noFuture ? today.value : undefined))
const calendar = computed(() => props.field.behavior.date?.calendar ?? 'gregorian')
const hijri = computed(() => (kind.value === 'date' && calendar.value !== 'gregorian' ? hijriText(props.modelValue, ctx.locale.value) : ''))

function set(raw: string | undefined): void {
  const s = raw ?? ''
  if (s === '') {
    emit('update:modelValue', null)
    return
  }
  const v =
    kind.value === 'month'
      ? inputToMonth(s)
      : kind.value === 'week'
        ? inputToWeek(s)
        : kind.value === 'time'
          ? inputToTime(s)
          : kind.value === 'datetime-local'
            ? inputToDatetime(s, timezone.value)
            : inputToDate(s)
  if (v !== null) emit('update:modelValue', v)
}
</script>

<template>
  <div>
    <InputText
      :id="inputId"
      :model-value="shown"
      :type="kind"
      fluid
      :step="step"
      :min="minAttr"
      :max="maxAttr"
      :disabled="disabled"
      :invalid="invalid"
      :aria-required="required"
      :size="size"
      @update:model-value="set"
      @focus="emit('focus')"
      @blur="emit('blur')"
    />
    <small v-if="hijri" class="block text-muted-color mt-1" data-testid="hijri-date">{{ hijri }}</small>
  </div>
</template>
