<script setup lang="ts">
import Select from 'primevue/select'
import { computed, onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { get } from '@/api/http'
import ConfigField from '@/components/config/ConfigField.vue'
import type { DateStyle, Format } from './formats'

/**
 * How one value is shown (specification §4.17): a date style for dates,
 * decimal places or a currency for numbers, and Arabic-Indic or Western
 * digits for anything with digits. Only what fits the value is offered.
 */
const props = defineProps<{ kind: 'date' | 'datetime' | 'number' | 'text'; idPrefix: string }>()
const model = defineModel<Format>({ required: true })
const { t } = useI18n()

let cache: Promise<{ code: string; name: string | null }[]> | null = null
const currencies = ref<{ code: string; name: string | null }[]>([])
onMounted(async () => {
  if (props.kind !== 'number') return
  cache ??= get<{ data: { code: string; name: string | null; is_enabled: boolean }[] }>('/currencies')
    .then((r) => r.data.filter((c) => c.is_enabled))
    .catch(() => [])
  currencies.value = await cache
})

const dateStyles = computed(() => [
  { value: null as DateStyle | null, label: t('tpl.format.as_set') },
  ...(['short', 'medium', 'long', 'iso'] as DateStyle[]).map((v) => ({ value: v as DateStyle | null, label: t(`tpl.format.date_${v}`) })),
])
const decimals = computed(() => [{ value: null as number | null, label: t('tpl.format.as_set') }, ...[0, 1, 2, 3, 4, 5, 6].map((n) => ({ value: n as number | null, label: String(n) }))])
const currencyOptions = computed(() => [
  { value: null as string | null, label: t('tpl.format.no_currency') },
  ...currencies.value.map((c) => ({ value: c.code as string | null, label: c.name ? `${c.name} (${c.code})` : c.code })),
])
const digitStyles = computed(() => [
  { value: null as 'arabic' | 'western' | null, label: t('tpl.format.as_set') },
  { value: 'arabic' as const, label: t('tpl.format.digits_arabic') },
  { value: 'western' as const, label: t('tpl.format.digits_western') },
])
function set<K extends keyof Format>(key: K, value: Format[K] | null): void {
  const next = { ...model.value }
  if (value === null || value === undefined) delete next[key]
  else next[key] = value
  model.value = next
}
</script>

<template>
  <div class="cfg-row">
    <ConfigField v-if="kind === 'date' || kind === 'datetime'" :label="t('tpl.format.date')" :for="`${idPrefix}-date`" width="sm">
      <Select
        :model-value="model[kind] ?? null"
        :input-id="`${idPrefix}-date`"
        :options="dateStyles"
        option-label="label"
        option-value="value"
        size="small"
        @update:model-value="(v: DateStyle | null) => set(kind as 'date' | 'datetime', v)"
      />
    </ConfigField>
    <template v-if="kind === 'number'">
      <ConfigField :label="t('tpl.format.decimals')" :for="`${idPrefix}-dec`" width="xs">
        <Select
          :model-value="model.number ?? null"
          :input-id="`${idPrefix}-dec`"
          :options="decimals"
          option-label="label"
          option-value="value"
          size="small"
          @update:model-value="(v: number | null) => set('number', v)"
        />
      </ConfigField>
      <ConfigField :label="t('tpl.format.currency')" :for="`${idPrefix}-cur`" width="sm">
        <Select
          :model-value="model.currency ?? null"
          :input-id="`${idPrefix}-cur`"
          :options="currencyOptions"
          option-label="label"
          option-value="value"
          filter
          size="small"
          @update:model-value="(v: string | null) => set('currency', v)"
        />
      </ConfigField>
    </template>
    <ConfigField :label="t('tpl.format.digits')" :for="`${idPrefix}-dig`" width="sm">
      <Select
        :model-value="model.digits ?? null"
        :input-id="`${idPrefix}-dig`"
        :options="digitStyles"
        option-label="label"
        option-value="value"
        size="small"
        @update:model-value="(v: 'arabic' | 'western' | null) => set('digits', v)"
      />
    </ConfigField>
  </div>
</template>
