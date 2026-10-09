<script setup lang="ts">
import InputText from 'primevue/inputtext'
import Textarea from 'primevue/textarea'
import { computed, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useBuilder } from './useBuilder'
import type { I18nText } from './types'

/**
 * Translatable text, stored per locale (never as fixed Arabic/English
 * columns). The interface language comes first; the other enabled languages
 * fold under it, showing whether each is set (design system: properties
 * panel). Empty entries are removed.
 */
const props = withDefaults(defineProps<{ label: string; multiline?: boolean; rows?: number; maxlength?: number; id?: string; invalid?: boolean }>(), { rows: 3, maxlength: 1000, id: undefined })
const model = defineModel<I18nText | undefined>()
const builder = useBuilder()
const { locale, t } = useI18n()
const uid = props.id ?? `i18n-${Math.random().toString(36).slice(2, 9)}`
const expanded = ref(false)

const primary = computed(() => builder.locales.find((l) => l.code === locale.value) ?? builder.locales[0])
const others = computed(() => builder.locales.filter((l) => l.code !== primary.value?.code))
const setCount = computed(() => others.value.filter((l) => !!model.value?.[l.code]).length)

function update(code: string, value: string | undefined): void {
  const next: I18nText = { ...(model.value ?? {}) }
  if (value) next[code] = value
  else delete next[code]
  model.value = next
}
</script>

<template>
  <fieldset class="flex flex-col gap-1.5 min-w-0">
    <legend class="text-sm font-medium mb-1">{{ label }}</legend>
    <template v-for="l in expanded ? builder.locales : primary ? [primary] : []" :key="l.code">
      <div class="flex items-start gap-2">
        <label v-if="expanded || others.length" :for="`${uid}-${l.code}`" class="text-xs text-muted-color w-8 shrink-0 pt-2 uppercase ltr-value">{{ l.code }}</label>
        <Textarea
          v-if="multiline"
          :id="`${uid}-${l.code}`"
          :model-value="model?.[l.code] ?? ''"
          :dir="l.direction"
          :rows="rows"
          :maxlength="maxlength"
          :invalid="invalid"
          auto-resize
          class="w-full"
          :aria-label="`${label} (${l.native_name})`"
          @update:model-value="(v: string | undefined) => update(l.code, v)"
        />
        <InputText
          v-else
          :id="`${uid}-${l.code}`"
          :model-value="model?.[l.code] ?? ''"
          :dir="l.direction"
          :maxlength="maxlength"
          :invalid="invalid"
          size="small"
          class="w-full"
          :aria-label="`${label} (${l.native_name})`"
          @update:model-value="(v: string | undefined) => update(l.code, v)"
        />
      </div>
    </template>
    <button v-if="others.length" type="button" class="self-start text-xs text-muted-color hover:text-color flex items-center gap-1" :aria-expanded="expanded" @click="expanded = !expanded">
      <i :class="expanded ? 'pi pi-chevron-up' : 'pi pi-language'" class="text-[0.6875rem]" aria-hidden="true" />
      <template v-if="expanded">{{ t('builder.i18n.hide_other') }}</template>
      <template v-else>{{ t('builder.i18n.other', { names: others.map((l) => l.native_name).join(', '), set: setCount, total: others.length }) }}</template>
    </button>
  </fieldset>
</template>
