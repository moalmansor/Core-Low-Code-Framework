<script setup lang="ts">
import Button from 'primevue/button'
import InputText from 'primevue/inputtext'
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { brandVariables } from '@/theme/brand'
import { AA_TEXT, checkBrand, darkVariant, parseHex, PICKER_FALLBACK } from '@/theme/color'

/**
 * The brand colour in Appearance & Branding (docs/design-system.md): one
 * primary colour, an optional dark-mode shade, a live contrast check with the
 * nearest passing shade, and a preview in both modes. The server applies the
 * same check when saving.
 */
const primary = defineModel<string | null>('primary', { required: true })
const primaryDark = defineModel<string | null>('primaryDark', { required: true })
defineProps<{ errors: Record<string, string> }>()
const { t } = useI18n()

const valid = (v: string | null) => !!v && parseHex(v) !== null
const lightCheck = computed(() => (valid(primary.value) ? checkBrand(primary.value!, 'light') : null))
const darkCheck = computed(() => (valid(primaryDark.value) ? checkBrand(primaryDark.value!, 'dark') : null))
const derivedDark = computed(() => (valid(primary.value) && !primaryDark.value ? darkVariant(primary.value!) : null))
const canPreview = computed(() => (!primary.value || valid(primary.value)) && (!primaryDark.value || valid(primaryDark.value)))
const previewStyle = (mode: 'light' | 'dark') => {
  const vars = canPreview.value ? brandVariables(valid(primary.value) ? primary.value : null, valid(primaryDark.value) ? primaryDark.value : null) : {}
  const style: Record<string, string> = {}
  // The preview panes read the brand variables the way the root does in each mode.
  for (const [k, v] of Object.entries(vars)) if (mode === 'dark' ? k.endsWith('-dark') : !k.endsWith('-dark')) style[k] = v
  return style
}
const ratio = (r: number) => r.toFixed(2)
</script>

<template>
  <section class="flex flex-col gap-4" data-testid="brand-colour">
    <div>
      <h3 class="font-semibold">{{ t('settings.brand.title') }}</h3>
      <p class="text-sm text-muted-color">{{ t('settings.brand.hint') }}</p>
    </div>
    <div class="grid gap-4 md:grid-cols-2">
      <div class="field">
        <label for="brand-primary">{{ t('settings.brand.primary') }}</label>
        <div class="flex items-center gap-2">
          <input
            type="color"
            class="h-9 w-12 rounded-md border border-line-input bg-card"
            :value="valid(primary) ? primary! : PICKER_FALLBACK"
            :aria-label="t('settings.brand.primary_picker')"
            @input="primary = ($event.target as HTMLInputElement).value"
          />
          <InputText
            id="brand-primary"
            v-model="primary"
            class="ltr-value flex-1"
            placeholder="#RRGGBB"
            maxlength="7"
            :invalid="!!errors.primary_color || (!!lightCheck && !lightCheck.ok)"
            data-testid="brand-primary"
          />
          <Button v-if="primary" type="button" text severity="secondary" icon="pi pi-times" :aria-label="t('settings.brand.reset')" @click="primary = null" />
        </div>
        <p v-if="!primary" class="text-sm text-muted-color">{{ t('settings.brand.default') }}</p>
        <p v-else-if="lightCheck?.ok" class="text-sm text-success" data-testid="brand-primary-ok">
          <i class="pi pi-check" aria-hidden="true" /> {{ t('settings.brand.passes', { ratio: ratio(lightCheck.ratio) }) }}
        </p>
        <div v-else-if="lightCheck" class="text-sm flex flex-wrap items-center gap-2" data-testid="brand-primary-fails">
          <span><i class="pi pi-exclamation-triangle text-warning" aria-hidden="true" /> {{ t('settings.brand.fails', { ratio: ratio(lightCheck.ratio), min: AA_TEXT }) }}</span>
          <Button
            v-if="lightCheck.suggestion"
            type="button"
            size="small"
            outlined
            :label="t('settings.brand.use', { colour: lightCheck.suggestion })"
            data-testid="brand-primary-use"
            @click="primary = lightCheck.suggestion"
          />
        </div>
        <small v-if="errors.primary_color" class="field-error">{{ errors.primary_color }}</small>
      </div>
      <div class="field">
        <label for="brand-primary-dark">{{ t('settings.brand.primary_dark') }}</label>
        <div class="flex items-center gap-2">
          <input
            type="color"
            class="h-9 w-12 rounded-md border border-line-input bg-card"
            :value="valid(primaryDark) ? primaryDark! : (derivedDark ?? PICKER_FALLBACK)"
            :aria-label="t('settings.brand.primary_dark_picker')"
            @input="primaryDark = ($event.target as HTMLInputElement).value"
          />
          <InputText
            id="brand-primary-dark"
            v-model="primaryDark"
            class="ltr-value flex-1"
            :placeholder="derivedDark ?? '#RRGGBB'"
            maxlength="7"
            :invalid="!!errors.primary_color_dark || (!!darkCheck && !darkCheck.ok)"
          />
          <Button v-if="primaryDark" type="button" text severity="secondary" icon="pi pi-times" :aria-label="t('settings.brand.reset')" @click="primaryDark = null" />
        </div>
        <p v-if="!primaryDark" class="text-sm text-muted-color">{{ derivedDark ? t('settings.brand.derived', { colour: derivedDark }) : t('settings.brand.default') }}</p>
        <p v-else-if="darkCheck?.ok" class="text-sm text-success"><i class="pi pi-check" aria-hidden="true" /> {{ t('settings.brand.passes', { ratio: ratio(darkCheck.ratio) }) }}</p>
        <div v-else-if="darkCheck" class="text-sm flex flex-wrap items-center gap-2">
          <span><i class="pi pi-exclamation-triangle text-warning" aria-hidden="true" /> {{ t('settings.brand.fails', { ratio: ratio(darkCheck.ratio), min: AA_TEXT }) }}</span>
          <Button v-if="darkCheck.suggestion" type="button" size="small" outlined :label="t('settings.brand.use', { colour: darkCheck.suggestion })" @click="primaryDark = darkCheck.suggestion" />
        </div>
        <small v-if="errors.primary_color_dark" class="field-error">{{ errors.primary_color_dark }}</small>
      </div>
    </div>
    <div class="grid gap-3 md:grid-cols-2" :aria-label="t('settings.brand.preview')">
      <div
        v-for="mode in ['light', 'dark'] as const"
        :key="mode"
        :class="['rounded-xl border border-line p-4 flex flex-col gap-3 bg-card text-color', mode === 'dark' ? 'app-dark' : 'app-light']"
        :style="previewStyle(mode)"
      >
        <span class="eyebrow">{{ t(`settings.brand.preview_${mode}`) }}</span>
        <div class="flex flex-wrap items-center gap-2">
          <!-- Plain elements: PrimeVue resolves its variables at the page root, so it would not show a pane's colours. -->
          <span class="brand-sample-button inline-flex items-center gap-1.5 rounded-md px-3 py-1.5 text-sm font-medium"
            ><i class="pi pi-check" aria-hidden="true" />{{ t('settings.brand.sample_button') }}</span
          >
          <span class="rounded-full px-2 py-0.5 text-xs bg-primary-subtle text-on-primary-subtle">{{ t('settings.brand.sample_badge') }}</span>
          <span class="brand-sample-text text-sm font-medium">{{ t('settings.brand.sample_tab') }}</span>
        </div>
      </div>
    </div>
  </section>
</template>

<style scoped>
.brand-sample-button {
  background: var(--primary);
  color: var(--on-primary);
}
.brand-sample-text {
  color: var(--primary);
}
</style>
