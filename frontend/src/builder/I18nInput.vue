<script setup lang="ts">
import InputText from 'primevue/inputtext'
import Textarea from 'primevue/textarea'
import { useBuilder } from './useBuilder'
import type { I18nText } from './types'

/**
 * One input per enabled locale for translatable text (stored per locale,
 * never as fixed Arabic/English columns). Empty entries are removed.
 */
const props = withDefaults(defineProps<{ label: string; multiline?: boolean; rows?: number; maxlength?: number; id?: string; invalid?: boolean }>(), { rows: 3, maxlength: 1000, id: undefined })
const model = defineModel<I18nText | undefined>()
const builder = useBuilder()
const uid = props.id ?? `i18n-${Math.random().toString(36).slice(2, 9)}`

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
    <div v-for="l in builder.locales" :key="l.code" class="flex items-start gap-2">
      <label :for="`${uid}-${l.code}`" class="text-xs text-muted-color w-10 shrink-0 pt-2 uppercase ltr-value">{{ l.code }}</label>
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
  </fieldset>
</template>
