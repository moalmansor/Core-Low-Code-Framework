<script setup lang="ts">
import InputGroup from 'primevue/inputgroup'
import InputGroupAddon from 'primevue/inputgroupaddon'
import InputText from 'primevue/inputtext'
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRenderer } from '../context'
import { pickText } from '../i18nText'
import { normalizeNumber } from './number'
import type { InputProps } from './props'

/**
 * Numbers as exact decimal strings (never floats, so precision 19/4 and
 * money survive): number, decimal, percentage and currency, with Arabic-Indic
 * digits accepted. Multi-currency fields hold {amount, currency}.
 */
const props = defineProps<InputProps>()
const emit = defineEmits<{ 'update:modelValue': [value: unknown]; focus: []; blur: [] }>()
const ctx = useRenderer()
const { t } = useI18n()

const multi = computed(() => props.field.type === 'currency' && props.field.storage.multiCurrency === true)
const amount = computed(() => {
  const v = props.modelValue
  if (v === null || v === undefined) return ''
  return typeof v === 'object' ? String((v as { amount?: unknown }).amount ?? '') : String(v)
})
const currency = computed(() => (multi.value && typeof props.modelValue === 'object' && props.modelValue !== null ? ((props.modelValue as { currency?: string | null }).currency ?? '') : ''))
const defaultCurrency = computed(() => props.field.behavior.number?.currency ?? '')
const placeholder = computed(() => pickText(props.field.i18n.placeholder, ctx.locale.value) ?? undefined)

function emitValue(a: string, c: string): void {
  if (!multi.value) {
    emit('update:modelValue', a === '' ? null : a)
    return
  }
  emit('update:modelValue', a === '' ? null : { amount: a, currency: c || defaultCurrency.value || null })
}
function onAmount(raw: string | undefined): void {
  const v = normalizeNumber(raw ?? '', false)
  if (v === null) return
  emitValue(v, currency.value)
}
function onCurrency(raw: string | undefined): void {
  emitValue(
    amount.value,
    (raw ?? '')
      .toUpperCase()
      .replace(/[^A-Z]/g, '')
      .slice(0, 3),
  )
}
function onBlur(): void {
  const v = normalizeNumber(amount.value, true)
  if (v !== null && v !== amount.value) emitValue(v, currency.value)
  emit('blur')
}
</script>

<template>
  <InputGroup>
    <InputGroupAddon v-if="field.type === 'currency' && !multi && defaultCurrency && field.behavior.number?.symbolPosition === 'before'">{{ defaultCurrency }}</InputGroupAddon>
    <InputText
      :id="inputId"
      :model-value="amount"
      inputmode="decimal"
      class="ltr-value"
      :disabled="disabled"
      :invalid="invalid"
      :placeholder="placeholder"
      :aria-required="required"
      :autofocus="field.ui.autofocus ?? false"
      :size="size"
      @update:model-value="onAmount"
      @focus="emit('focus')"
      @blur="onBlur"
    />
    <InputGroupAddon v-if="field.type === 'percentage'">%</InputGroupAddon>
    <InputGroupAddon v-if="field.type === 'currency' && !multi && defaultCurrency && field.behavior.number?.symbolPosition !== 'before'">{{ defaultCurrency }}</InputGroupAddon>
    <InputText
      v-if="multi"
      :model-value="currency || defaultCurrency"
      class="ltr-value !w-24"
      maxlength="3"
      :disabled="disabled"
      :aria-label="t('runtime.currency_code')"
      @update:model-value="onCurrency"
    />
  </InputGroup>
</template>
