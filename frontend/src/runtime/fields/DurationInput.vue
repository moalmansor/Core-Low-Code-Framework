<script setup lang="ts">
import InputText from 'primevue/inputtext'
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { asciiDigits } from '@/expressions/unicode'
import { prop, type InputProps } from './props'

/** Duration as hours, minutes and (optionally) seconds; stored as whole seconds. */
const props = defineProps<InputProps>()
const emit = defineEmits<{ 'update:modelValue': [value: unknown]; focus: []; blur: [] }>()
const { t } = useI18n()

const total = computed(() => {
  const n = Number(props.modelValue)
  return props.modelValue === null || props.modelValue === undefined || props.modelValue === '' || !Number.isFinite(n) ? null : Math.trunc(n)
})
const withSeconds = computed(() => prop(props.field, 'seconds', false))
const parts = computed(() => {
  const s = total.value
  if (s === null) return { h: '', m: '', s: '' }
  const abs = Math.abs(s)
  return { h: String(Math.floor(abs / 3600)), m: String(Math.floor((abs % 3600) / 60)), s: String(abs % 60) }
})

function set(part: 'h' | 'm' | 's', raw: string | undefined): void {
  const clean = asciiDigits(raw ?? '').replace(/\D/g, '')
  const next = { ...parts.value, [part]: clean }
  if (next.h === '' && next.m === '' && next.s === '') {
    emit('update:modelValue', null)
    return
  }
  const seconds = Number(next.h || 0) * 3600 + Number(next.m || 0) * 60 + (withSeconds.value ? Number(next.s || 0) : 0)
  emit('update:modelValue', String(seconds))
}
</script>

<template>
  <div class="flex items-center gap-2" @focusin="emit('focus')" @focusout="emit('blur')">
    <label class="flex items-center gap-1">
      <InputText :id="inputId" :model-value="parts.h" inputmode="numeric" class="ltr-value w-20" :disabled="disabled" :invalid="invalid" @update:model-value="(v) => set('h', v)" />
      <span class="text-sm text-muted-color">{{ t('runtime.hours') }}</span>
    </label>
    <label class="flex items-center gap-1">
      <InputText :model-value="parts.m" inputmode="numeric" class="ltr-value w-16" maxlength="2" :disabled="disabled" :invalid="invalid" @update:model-value="(v) => set('m', v)" />
      <span class="text-sm text-muted-color">{{ t('runtime.minutes') }}</span>
    </label>
    <label v-if="withSeconds" class="flex items-center gap-1">
      <InputText :model-value="parts.s" inputmode="numeric" class="ltr-value w-16" maxlength="2" :disabled="disabled" :invalid="invalid" @update:model-value="(v) => set('s', v)" />
      <span class="text-sm text-muted-color">{{ t('runtime.seconds') }}</span>
    </label>
  </div>
</template>
