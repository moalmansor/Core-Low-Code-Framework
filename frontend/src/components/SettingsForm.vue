<script setup lang="ts">
import InputNumber from 'primevue/inputnumber'
import InputText from 'primevue/inputtext'
import MultiSelect from 'primevue/multiselect'
import Password from 'primevue/password'
import Select from 'primevue/select'
import Textarea from 'primevue/textarea'
import ToggleSwitch from 'primevue/toggleswitch'
import { reactive } from 'vue'
import { useI18n } from 'vue-i18n'
import type { FieldDef } from '@/views/admin/settingsSchema'

const props = defineProps<{ group: string; fields: FieldDef[]; errors: Record<string, string> }>()
const values = defineModel<Record<string, unknown>>({ required: true })
const { t, te } = useI18n()
const timezones = (Intl as unknown as { supportedValuesOf?: (k: string) => string[] }).supportedValuesOf?.('timeZone') ?? ['UTC']

function optionLabel(key: string, v: string | number): string {
  const k = `settings.option.${key}.${v === '' ? 'none' : v === ' ' ? 'space' : v}`
  return te(k) ? t(k) : String(v)
}
// Text drafts are parsed when the field loses focus, so partial lines survive typing.
const drafts = reactive<Record<string, string>>({})
function listText(key: string): string {
  return ((values.value[key] as string[] | undefined) ?? []).join('\n')
}
function setList(key: string, text: string): void {
  values.value[key] = text.split(/\r?\n/).map((x) => x.trim()).filter(Boolean)
}
function mapText(key: string): string {
  return Object.entries((values.value[key] as Record<string, string> | undefined) ?? {}).map(([a, b]) => `${a} = ${b}`).join('\n')
}
function setMap(key: string, text: string): void {
  const out: Record<string, string> = {}
  for (const line of text.split(/\r?\n/)) {
    const i = line.lastIndexOf('=')
    if (i > 0) out[line.slice(0, i).trim()] = line.slice(i + 1).trim()
  }
  values.value[key] = out
}
const label = (key: string) => t(`settings.${props.group}.${key}`)
</script>

<template>
  <div class="form-grid">
    <div v-for="f in fields" :key="f.key" class="field" :class="{ 'col-span-full': f.type === 'map' || f.type === 'list' }">
      <label v-if="f.type !== 'bool'" :for="`${group}-${f.key}`">{{ label(f.key) }}</label>
      <InputText v-if="f.type === 'text'" :id="`${group}-${f.key}`" v-model="values[f.key] as string" :class="{ 'ltr-value': f.ltr }" />
      <InputNumber v-else-if="f.type === 'number'" v-model="values[f.key] as number" :input-id="`${group}-${f.key}`" :min="f.min" :max="f.max" :use-grouping="false" />
      <label v-else-if="f.type === 'bool'" class="flex items-center gap-2 mt-6"><ToggleSwitch v-model="values[f.key] as boolean" :input-id="`${group}-${f.key}`" />{{ label(f.key) }}</label>
      <Select v-else-if="f.type === 'select'" v-model="values[f.key]" :input-id="`${group}-${f.key}`" :options="f.options!.map((v) => ({ v, l: optionLabel(f.key, v) }))" option-label="l" option-value="v" />
      <Select v-else-if="f.type === 'timezone'" v-model="values[f.key]" :input-id="`${group}-${f.key}`" :options="timezones" filter />
      <MultiSelect v-else-if="f.type === 'multiselect'" v-model="values[f.key] as string[]" :input-id="`${group}-${f.key}`" :options="f.options" display="chip" />
      <template v-else-if="f.type === 'secret'">
        <Password v-model="values[f.key] as string" :input-id="`${group}-${f.key}`" :feedback="false" toggle-mask autocomplete="new-password" fluid :placeholder="(values[`__${f.key}_set`] ? t('settings.secret_set') : t('settings.secret_not_set'))" />
        <small class="text-muted-color">{{ t('settings.secret_hint') }}</small>
      </template>
      <Textarea v-else-if="f.type === 'list'" :id="`${group}-${f.key}`" :model-value="drafts[f.key] ?? listText(f.key)" rows="3" class="ltr-value" @update:model-value="(v: string | undefined) => (drafts[f.key] = v ?? '')" @blur="drafts[f.key] !== undefined && (setList(f.key, drafts[f.key]!), delete drafts[f.key])" />
      <Textarea v-else-if="f.type === 'map'" :id="`${group}-${f.key}`" :model-value="drafts[f.key] ?? mapText(f.key)" rows="4" class="ltr-value" :placeholder="t('settings.map_hint')" @update:model-value="(v: string | undefined) => (drafts[f.key] = v ?? '')" @blur="drafts[f.key] !== undefined && (setMap(f.key, drafts[f.key]!), delete drafts[f.key])" />
      <small v-if="f.type === 'list'" class="text-muted-color">{{ t('settings.list_hint') }}</small>
      <span v-if="errors[f.key]" class="field-error">{{ errors[f.key] }}</span>
    </div>
  </div>
</template>
