<script setup lang="ts">
import InputNumber from 'primevue/inputnumber'
import InputText from 'primevue/inputtext'
import Select from 'primevue/select'
import ToggleSwitch from 'primevue/toggleswitch'
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { UI_PROPS, type UiPropSpec } from '@/runtime/uiProps'
import I18nInput from '../I18nInput.vue'
import type { FieldDef, I18nText, PropValue } from '../types'

/** The type-specific `ui.props` the renderer reads (runtime/uiProps.ts). */
const field = defineModel<FieldDef>('field', { required: true })
const { t } = useI18n()

// Images shown by a display element are given by address; uploading is done in the records area.
const specs = computed<UiPropSpec[]>(() => (UI_PROPS[field.value.type] ?? []).filter((s) => s.kind !== 'file'))

function value(name: string): PropValue | undefined {
  return field.value.ui?.props?.[name]
}
function set(name: string, v: PropValue | undefined): void {
  field.value.ui ??= {}
  const props = { ...(field.value.ui.props ?? {}) }
  if (v === undefined || v === null || v === '' || (typeof v === 'object' && !Array.isArray(v) && Object.keys(v).length === 0)) delete props[name]
  else props[name] = v
  field.value.ui.props = props
}
</script>

<template>
  <div v-if="specs.length" class="grid grid-cols-2 gap-2">
    <template v-for="s in specs" :key="s.name">
      <label v-if="s.kind === 'number'" class="field"
        ><span>{{ t(`builder.ui_prop.${s.name}`) }}</span>
        <InputNumber :model-value="typeof value(s.name) === 'number' ? (value(s.name) as number) : null" :max-fraction-digits="4" size="small" @update:model-value="(v) => set(s.name, v ?? undefined)" />
      </label>
      <label v-else-if="s.kind === 'boolean'" class="flex items-center gap-2 text-sm col-span-2"
        ><ToggleSwitch :model-value="value(s.name) === true" @update:model-value="(v: boolean) => set(s.name, v)" />{{ t(`builder.ui_prop.${s.name}`) }}</label
      >
      <label v-else-if="s.kind === 'choice'" class="field"
        ><span>{{ t(`builder.ui_prop.${s.name}`) }}</span>
        <Select
          :model-value="value(s.name) ?? null"
          :options="(s.choices ?? []).map((c) => ({ value: c, label: t(`builder.ui_choice.${c}`) }))"
          option-label="label"
          option-value="value"
          show-clear
          size="small"
          @update:model-value="(v) => set(s.name, v ?? undefined)"
        />
      </label>
      <label v-else-if="s.kind === 'text'" class="field col-span-2"
        ><span>{{ t(`builder.ui_prop.${s.name}`) }}</span>
        <InputText :model-value="String(value(s.name) ?? '')" size="small" class="ltr-value" maxlength="2048" @update:model-value="(v: string | undefined) => set(s.name, v)" />
      </label>
      <div v-else-if="s.kind === 'i18n'" class="col-span-2">
        <I18nInput :model-value="(value(s.name) as I18nText | undefined) ?? {}" :label="t(`builder.ui_prop.${s.name}`)" @update:model-value="(v) => set(s.name, v)" />
      </div>
    </template>
  </div>
</template>
