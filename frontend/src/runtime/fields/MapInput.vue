<script setup lang="ts">
import Button from 'primevue/button'
import InputText from 'primevue/inputtext'
import { computed, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { normalizeNumber } from './number'
import type { InputProps } from './props'

/**
 * Location as latitude, longitude and an optional label, with the device's
 * position on request. No third-party map tiles or scripts are loaded (all
 * outbound traffic must pass the server's egress gateway).
 */
const props = defineProps<InputProps>()
const emit = defineEmits<{ 'update:modelValue': [value: unknown]; focus: []; blur: [] }>()
const { t } = useI18n()
const value = computed(() => (typeof props.modelValue === 'object' && props.modelValue !== null ? (props.modelValue as { lat?: string | null; lng?: string | null; label?: string | null }) : {}))
const locating = ref(false)
const error = ref('')

function set(part: 'lat' | 'lng' | 'label', raw: string | undefined): void {
  const v = part === 'label' ? (raw ?? '') : normalizeNumber(raw ?? '', false)
  if (v === null) return
  const next = { lat: value.value.lat ?? null, lng: value.value.lng ?? null, label: value.value.label ?? null, [part]: v === '' ? null : v }
  emit('update:modelValue', next.lat === null && next.lng === null && !next.label ? null : next)
}
const outOfRange = computed(() => {
  const lat = Number(value.value.lat)
  const lng = Number(value.value.lng)
  return (value.value.lat && (Number.isNaN(lat) || Math.abs(lat) > 90)) || (value.value.lng && (Number.isNaN(lng) || Math.abs(lng) > 180))
})

function locate(): void {
  error.value = ''
  if (!navigator.geolocation) {
    error.value = t('runtime.location_unavailable')
    return
  }
  locating.value = true
  navigator.geolocation.getCurrentPosition(
    (p) => {
      locating.value = false
      emit('update:modelValue', { lat: p.coords.latitude.toFixed(7), lng: p.coords.longitude.toFixed(7), label: value.value.label ?? null })
    },
    () => {
      locating.value = false
      error.value = t('runtime.location_unavailable')
    },
    { enableHighAccuracy: true, timeout: 15000 },
  )
}
</script>

<template>
  <div class="flex flex-col gap-2" @focusin="emit('focus')" @focusout="emit('blur')">
    <div class="grid gap-2 sm:grid-cols-2">
      <label class="flex flex-col gap-1">
        <span class="text-xs text-muted-color">{{ t('runtime.latitude') }}</span>
        <InputText
          :id="inputId"
          :model-value="value.lat ?? ''"
          inputmode="decimal"
          class="ltr-value"
          :disabled="disabled"
          :invalid="invalid || !!outOfRange"
          @update:model-value="(v) => set('lat', v)"
        />
      </label>
      <label class="flex flex-col gap-1">
        <span class="text-xs text-muted-color">{{ t('runtime.longitude') }}</span>
        <InputText :model-value="value.lng ?? ''" inputmode="decimal" class="ltr-value" :disabled="disabled" :invalid="invalid || !!outOfRange" @update:model-value="(v) => set('lng', v)" />
      </label>
    </div>
    <label class="flex flex-col gap-1">
      <span class="text-xs text-muted-color">{{ t('runtime.location_label') }}</span>
      <InputText :model-value="value.label ?? ''" maxlength="255" :disabled="disabled" @update:model-value="(v) => set('label', v)" />
    </label>
    <div>
      <Button type="button" icon="pi pi-map-marker" :label="t('runtime.use_my_location')" size="small" outlined :loading="locating" :disabled="disabled" @click="locate" />
    </div>
    <p v-if="outOfRange" class="field-error text-sm">{{ t('runtime.location_range') }}</p>
    <p v-if="error" class="field-error text-sm">{{ error }}</p>
  </div>
</template>
