<script setup lang="ts">
import InputText from 'primevue/inputtext'
import Select from 'primevue/select'
import { computed, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { asciiDigits } from '@/expressions/unicode'
import { useRenderer } from '../context'
import { countryName, DIAL_CODES, nationalPart, toE164 } from './countries'
import { prop, type InputProps } from './props'

/** Phone number with country code, stored as `{number: E.164, country: ISO code}`. */
const props = defineProps<InputProps>()
const emit = defineEmits<{ 'update:modelValue': [value: unknown]; focus: []; blur: [] }>()
const ctx = useRenderer()
const { t } = useI18n()

const value = computed(() => (typeof props.modelValue === 'object' && props.modelValue !== null ? (props.modelValue as { number?: string; country?: string | null }) : null))
const country = ref<string | null>(value.value?.country ?? (prop(props.field, 'defaultCountry', '') || null))
watch(value, (v) => {
  if (v?.country && v.country !== country.value) country.value = v.country
})
const national = computed(() => (value.value?.number ? nationalPart(value.value.number, value.value.country ?? country.value) : ''))
const countries = computed(() =>
  Object.keys(DIAL_CODES)
    .map((code) => ({ code, label: `${countryName(code, ctx.locale.value)} (+${DIAL_CODES[code]})` }))
    .sort((a, b) => a.label.localeCompare(b.label, ctx.locale.value)),
)

function emitValue(c: string | null, digits: string): void {
  const number = toE164(c, digits)
  emit('update:modelValue', number ? { number, country: c } : null)
}
function onCountry(c: string | null): void {
  country.value = c
  emitValue(c, national.value)
}
function onNumber(raw: string | undefined): void {
  emitValue(country.value, asciiDigits(raw ?? '').replace(/[^\d]/g, ''))
}
</script>

<template>
  <div class="flex gap-2" @focusin="emit('focus')" @focusout="emit('blur')">
    <Select
      :model-value="country"
      :options="countries"
      option-label="label"
      option-value="code"
      filter
      :placeholder="t('runtime.country')"
      class="w-44 shrink-0"
      :disabled="disabled"
      :invalid="invalid"
      :aria-label="t('runtime.country')"
      @update:model-value="onCountry"
    />
    <InputText
      :id="inputId"
      :model-value="national"
      type="tel"
      inputmode="tel"
      class="ltr-value flex-1"
      :disabled="disabled"
      :invalid="invalid"
      :aria-required="required"
      @update:model-value="onNumber"
    />
  </div>
</template>
