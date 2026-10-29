<script setup lang="ts">
import Button from 'primevue/button'
import InputText from 'primevue/inputtext'
import { useI18n } from 'vue-i18n'
import { parseHex } from '@/theme/color'

/**
 * One colour of a theme, in light and dark mode: a picker, the hex value, and
 * a clear button that brings back the product colour (shown as the placeholder).
 */
const model = defineModel<{ light?: string | null; dark?: string | null } | undefined>({ required: true })
const props = defineProps<{ name: string; label: string; defaults: { light: string; dark: string }; errors: Record<string, string> }>()
const { t } = useI18n()
const valid = (v: string | null | undefined): v is string => !!v && parseHex(v) !== null
function set(mode: 'light' | 'dark', value: string | null): void {
  const next = { ...(model.value ?? {}), [mode]: value || null }
  model.value = next.light || next.dark ? next : undefined
}
const err = (mode: string) => props.errors[`tokens.colors.${props.name}.${mode}`]
</script>

<template>
  <div class="theme-color-row" :data-testid="`theme-color-${name}`">
    <span class="text-sm font-medium">{{ label }}</span>
    <div v-for="mode in ['light', 'dark'] as const" :key="mode" class="flex flex-col gap-1 min-w-0">
      <div class="flex items-center gap-2">
        <input
          type="color"
          class="h-9 w-10 shrink-0 rounded-md border border-line-input bg-card"
          :value="valid(model?.[mode]) ? model![mode]! : defaults[mode]"
          :aria-label="t('xp.theme_pick', { colour: label, mode: t(`xp.theme_mode.${mode}`) })"
          @input="set(mode, ($event.target as HTMLInputElement).value)"
        />
        <InputText
          :model-value="model?.[mode] ?? ''"
          class="ltr-value flex-1 min-w-0"
          size="small"
          maxlength="7"
          :placeholder="defaults[mode]"
          :aria-label="`${label} · ${t(`xp.theme_mode.${mode}`)}`"
          :invalid="!!err(mode)"
          :data-testid="`theme-color-${name}-${mode}`"
          @update:model-value="(v) => set(mode, (v ?? '').trim().toLowerCase())"
        />
        <Button v-if="model?.[mode]" type="button" text severity="secondary" size="small" icon="pi pi-times" :aria-label="t('xp.theme_use_product')" @click="set(mode, null)" />
      </div>
      <small v-if="err(mode)" class="field-error">{{ err(mode) }}</small>
    </div>
  </div>
</template>

<style scoped>
.theme-color-row {
  display: grid;
  grid-template-columns: minmax(7rem, 10rem) minmax(0, 1fr) minmax(0, 1fr);
  gap: 0.75rem;
  align-items: start;
}
@media (max-width: 640px) {
  .theme-color-row {
    grid-template-columns: minmax(0, 1fr);
  }
}
</style>
