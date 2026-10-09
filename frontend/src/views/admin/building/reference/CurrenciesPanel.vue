<script setup lang="ts">
import Button from 'primevue/button'
import Column from 'primevue/column'
import DataTable from 'primevue/datatable'
import DatePicker from 'primevue/datepicker'
import Dialog from 'primevue/dialog'
import InputNumber from 'primevue/inputnumber'
import InputText from 'primevue/inputtext'
import Select from 'primevue/select'
import Tag from 'primevue/tag'
import ToggleSwitch from 'primevue/toggleswitch'
import { useToast } from 'primevue/usetoast'
import { computed, onMounted, reactive, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { get, send } from '@/api/http'
import { useSession } from '@/stores/session'
import LocaleFields from '../LocaleFields.vue'
import { errorText, fieldErrors, filledLocales, formatDateTime } from '../shared'

interface Currency {
  uuid: string
  code: string
  symbol: string
  decimals: number
  rounding: 'half_up' | 'half_even' | 'down' | 'up'
  symbol_position: 'before' | 'after'
  is_base: boolean
  is_enabled: boolean
  name: string | null
  names: Record<string, string>
}
interface Rate {
  id: number
  base: string | null
  quote: string | null
  rate: string
  effective_at: string
  source: string
}

const { t } = useI18n()
const toast = useToast()
const session = useSession()
const currencies = ref<Currency[]>([])
const loading = ref(false)
const codes = computed(() => currencies.value.map((c) => c.code))

async function load(): Promise<void> {
  loading.value = true
  try {
    currencies.value = (await get<{ data: Currency[] }>('/currencies')).data
  } catch (e) {
    toast.add({ severity: 'error', summary: errorText(e, t('building.load_failed')), life: 6000 })
  } finally {
    loading.value = false
  }
}

const roundingOptions = computed(() => (['half_up', 'half_even', 'down', 'up'] as const).map((v) => ({ value: v, label: t(`building.ref.rounding.${v}`) })))
const positionOptions = computed(() => (['before', 'after'] as const).map((v) => ({ value: v, label: t(`building.ref.symbol_position.${v}`) })))
const sample = (c: Pick<Currency, 'symbol' | 'decimals' | 'symbol_position'>) => {
  const n = (1234.5).toLocaleString(session.locale, { minimumFractionDigits: c.decimals, maximumFractionDigits: c.decimals })
  return c.symbol_position === 'before' ? `${c.symbol} ${n}` : `${n} ${c.symbol}`
}

interface Editing {
  uuid?: string
  code: string
  names: Record<string, string>
  symbol: string
  decimals: number
  rounding: Currency['rounding']
  symbol_position: Currency['symbol_position']
  is_base: boolean
  is_enabled: boolean
}
const editing = ref<Editing | null>(null)
const errors = ref<Record<string, string>>({})
function create(): void {
  errors.value = {}
  editing.value = { code: '', names: {}, symbol: '', decimals: 2, rounding: 'half_up', symbol_position: 'before', is_base: !currencies.value.length, is_enabled: true }
}
function edit(c: Currency): void {
  errors.value = {}
  editing.value = {
    uuid: c.uuid,
    code: c.code,
    names: { ...c.names },
    symbol: c.symbol,
    decimals: c.decimals,
    rounding: c.rounding,
    symbol_position: c.symbol_position,
    is_base: c.is_base,
    is_enabled: c.is_enabled,
  }
}
async function save(): Promise<void> {
  const e = editing.value!
  errors.value = {}
  const body = { name: filledLocales(e.names), symbol: e.symbol, decimals: e.decimals, rounding: e.rounding, symbol_position: e.symbol_position, is_base: e.is_base, is_enabled: e.is_enabled }
  try {
    if (e.uuid) await send('patch', `/currencies/${e.uuid}`, body)
    else await send('post', '/currencies', { ...body, code: e.code.trim().toUpperCase() })
    editing.value = null
    toast.add({ severity: 'success', summary: t('common.saved'), life: 3000 })
    await load()
  } catch (err) {
    errors.value = fieldErrors(err)
    toast.add({ severity: 'error', summary: errorText(err, t('building.save_failed')), life: 6000 })
  }
}

// Exchange rates (append-only history)
const rates = ref<Rate[]>([])
const rateFilter = reactive({ base: null as string | null, quote: null as string | null })
async function loadRates(): Promise<void> {
  try {
    const params: Record<string, unknown> = {}
    if (rateFilter.base) params.base = rateFilter.base
    if (rateFilter.quote) params.quote = rateFilter.quote
    rates.value = (await get<{ data: Rate[] }>('/exchange-rates', params)).data
  } catch (e) {
    toast.add({ severity: 'error', summary: errorText(e, t('building.load_failed')), life: 6000 })
  }
}
watch(() => [rateFilter.base, rateFilter.quote], loadRates)
onMounted(async () => {
  await load()
  await loadRates()
})
const newRate = ref<{ base: string | null; quote: string | null; rate: string; at: Date } | null>(null)
const rateErrors = ref<Record<string, string>>({})
function openRate(): void {
  rateErrors.value = {}
  newRate.value = { base: currencies.value.find((c) => c.is_base)?.code ?? null, quote: null, rate: '', at: new Date() }
}
async function saveRate(): Promise<void> {
  const r = newRate.value!
  rateErrors.value = {}
  try {
    await send('post', '/exchange-rates', { base: r.base, quote: r.quote, rate: r.rate.trim(), effective_at: r.at.toISOString() })
    newRate.value = null
    toast.add({ severity: 'success', summary: t('common.saved'), life: 3000 })
    await loadRates()
  } catch (e) {
    rateErrors.value = fieldErrors(e)
    toast.add({ severity: 'error', summary: errorText(e, t('building.save_failed')), life: 6000 })
  }
}
</script>

<template>
  <div class="flex flex-wrap items-center gap-2 mb-3">
    <p class="text-sm text-muted-color flex-1">{{ t('building.ref.currencies_hint') }}</p>
    <Button icon="pi pi-plus" :label="t('building.ref.new_currency')" data-testid="cur-new" @click="create" />
  </div>
  <DataTable :value="currencies" :loading="loading" data-key="uuid" size="small" striped-rows data-testid="cur-table">
    <template #empty>{{ t('building.ref.no_currencies') }}</template>
    <Column :header="t('building.ref.code')">
      <template #body="{ data }"
        ><span class="ltr-value font-medium">{{ data.code }}</span> <Tag v-if="data.is_base" severity="success" :value="t('building.ref.base_currency')"
      /></template>
    </Column>
    <Column :header="t('building.name')" field="name" />
    <Column :header="t('building.ref.display')">
      <template #body="{ data }">{{ sample(data) }}</template>
    </Column>
    <Column :header="t('building.ref.rounding_label')">
      <template #body="{ data }">{{ t(`building.ref.rounding.${data.rounding}`) }}</template>
    </Column>
    <Column :header="t('building.status')">
      <template #body="{ data }"><Tag :severity="data.is_enabled ? 'success' : 'secondary'" :value="data.is_enabled ? t('common.enabled') : t('common.disabled')" /></template>
    </Column>
    <Column class="w-16">
      <template #body="{ data }"><Button icon="pi pi-pencil" text rounded :aria-label="t('common.edit')" @click="edit(data)" /></template>
    </Column>
  </DataTable>

  <section class="mt-6">
    <div class="flex flex-wrap items-center gap-2 mb-2">
      <h3 class="font-semibold flex-1">{{ t('building.ref.exchange_rates') }}</h3>
      <Select v-model="rateFilter.base" :options="codes" show-clear :placeholder="t('building.ref.base')" class="w-32" />
      <Select v-model="rateFilter.quote" :options="codes" show-clear :placeholder="t('building.ref.quote')" class="w-32" />
      <Button icon="pi pi-plus" size="small" :label="t('building.ref.new_rate')" :disabled="codes.length < 2" data-testid="rate-new" @click="openRate" />
    </div>
    <p class="text-xs text-muted-color mb-2">{{ t('building.ref.rates_hint') }}</p>
    <DataTable :value="rates" data-key="id" size="small" paginator :rows="20">
      <template #empty>{{ t('building.ref.no_rates') }}</template>
      <Column :header="t('building.ref.pair')">
        <template #body="{ data }"
          ><span class="ltr-value">1 {{ data.base }} = {{ data.rate }} {{ data.quote }}</span></template
        >
      </Column>
      <Column :header="t('building.ref.effective')">
        <template #body="{ data }">{{ formatDateTime(data.effective_at, session.locale) }}</template>
      </Column>
      <Column :header="t('building.ref.source')">
        <template #body="{ data }">{{ t(`building.ref.rate_source.${data.source}`) }}</template>
      </Column>
    </DataTable>
  </section>

  <Dialog
    :visible="!!editing"
    modal
    :header="editing?.uuid ? t('building.ref.edit_currency') : t('building.ref.new_currency')"
    :style="{ width: '40rem' }"
    @update:visible="(v: boolean) => !v && (editing = null)"
  >
    <form v-if="editing" class="flex flex-col gap-3" @submit.prevent="save">
      <div class="form-grid">
        <div class="field">
          <label for="cur-code">{{ t('building.ref.code') }}</label>
          <InputText id="cur-code" v-model="editing.code" :disabled="!!editing.uuid" maxlength="3" class="ltr-value uppercase" placeholder="SAR" />
          <span v-if="errors.code" class="field-error">{{ errors.code }}</span>
        </div>
        <div class="field">
          <label for="cur-symbol">{{ t('building.ref.symbol') }}</label>
          <InputText id="cur-symbol" v-model="editing.symbol" maxlength="8" />
          <span v-if="errors.symbol" class="field-error">{{ errors.symbol }}</span>
        </div>
        <div class="field">
          <label for="cur-dec">{{ t('building.ref.decimals') }}</label>
          <InputNumber v-model="editing.decimals" input-id="cur-dec" :min="0" :max="6" show-buttons :use-grouping="false" />
        </div>
        <div class="field">
          <label for="cur-round">{{ t('building.ref.rounding_label') }}</label>
          <Select v-model="editing.rounding" input-id="cur-round" :options="roundingOptions" option-label="label" option-value="value" />
        </div>
        <div class="field">
          <label for="cur-pos">{{ t('building.ref.symbol_position_label') }}</label>
          <Select v-model="editing.symbol_position" input-id="cur-pos" :options="positionOptions" option-label="label" option-value="value" />
        </div>
        <LocaleFields v-model="editing.names" :label="t('building.name')" field="name" :errors="errors" id-prefix="cur-name" />
      </div>
      <p class="text-sm">{{ t('building.ref.display') }}: {{ sample(editing) }}</p>
      <label class="flex items-center gap-2"><ToggleSwitch v-model="editing.is_enabled" />{{ t('common.enabled') }}</label>
      <label class="flex items-center gap-2"><ToggleSwitch v-model="editing.is_base" />{{ t('building.ref.make_base') }}</label>
      <div class="flex justify-end gap-2">
        <Button type="button" severity="secondary" :label="t('common.cancel')" @click="editing = null" />
        <Button type="submit" :label="t('common.save')" data-testid="cur-save" />
      </div>
    </form>
  </Dialog>

  <Dialog :visible="!!newRate" modal :header="t('building.ref.new_rate')" :style="{ width: '32rem' }" @update:visible="(v: boolean) => !v && (newRate = null)">
    <form v-if="newRate" class="flex flex-col gap-3" @submit.prevent="saveRate">
      <div class="form-grid">
        <div class="field">
          <label for="rate-base">{{ t('building.ref.base') }}</label>
          <Select v-model="newRate.base" input-id="rate-base" :options="codes" />
          <span v-if="rateErrors.base" class="field-error">{{ rateErrors.base }}</span>
        </div>
        <div class="field">
          <label for="rate-quote">{{ t('building.ref.quote') }}</label>
          <Select v-model="newRate.quote" input-id="rate-quote" :options="codes.filter((c) => c !== newRate?.base)" />
          <span v-if="rateErrors.quote" class="field-error">{{ rateErrors.quote }}</span>
        </div>
        <div class="field">
          <label for="rate-value">{{ t('building.ref.rate') }}</label>
          <InputText id="rate-value" v-model="newRate.rate" inputmode="decimal" class="ltr-value" placeholder="3.75" />
          <span v-if="rateErrors.rate" class="field-error">{{ rateErrors.rate }}</span>
        </div>
        <div class="field">
          <label for="rate-at">{{ t('building.ref.effective') }}</label>
          <DatePicker v-model="newRate.at" input-id="rate-at" show-time hour-format="24" />
          <span v-if="rateErrors.effective_at" class="field-error">{{ rateErrors.effective_at }}</span>
        </div>
      </div>
      <p v-if="newRate.base && newRate.quote && newRate.rate" class="text-sm ltr-value">1 {{ newRate.base }} = {{ newRate.rate }} {{ newRate.quote }}</p>
      <div class="flex justify-end gap-2">
        <Button type="button" severity="secondary" :label="t('common.cancel')" @click="newRate = null" />
        <Button type="submit" :label="t('common.save')" data-testid="rate-save" />
      </div>
    </form>
  </Dialog>
</template>
