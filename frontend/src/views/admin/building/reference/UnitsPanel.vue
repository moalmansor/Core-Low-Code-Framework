<script setup lang="ts">
import Button from 'primevue/button'
import Column from 'primevue/column'
import DataTable from 'primevue/datatable'
import Dialog from 'primevue/dialog'
import InputNumber from 'primevue/inputnumber'
import InputText from 'primevue/inputtext'
import Select from 'primevue/select'
import { useConfirm } from 'primevue/useconfirm'
import { useToast } from 'primevue/usetoast'
import { computed, onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { get, send } from '@/api/http'
import LocaleFields from '../LocaleFields.vue'
import { errorText, fieldErrors, filledLocales } from '../shared'

interface Unit {
  uuid: string
  code: string
  dimension: string
  symbol: string
  base_unit: string | null
  factor: string
  offset: string
  precision: number
  name: string | null
  names: Record<string, string>
}

const { t } = useI18n()
const toast = useToast()
const confirm = useConfirm()
const units = ref<Unit[]>([])
const loading = ref(false)
const dimensions = computed(() => [...new Set(units.value.map((u) => u.dimension))].sort())

async function load(): Promise<void> {
  loading.value = true
  try {
    units.value = (await get<{ data: Unit[] }>('/units')).data
  } catch (e) {
    toast.add({ severity: 'error', summary: errorText(e, t('building.load_failed')), life: 6000 })
  } finally {
    loading.value = false
  }
}
onMounted(load)

interface Editing {
  uuid?: string
  code: string
  names: Record<string, string>
  dimension: string
  symbol: string
  base_unit: string | null
  factor: string
  offset: string
  precision: number
}
const editing = ref<Editing | null>(null)
const errors = ref<Record<string, string>>({})
function create(): void {
  errors.value = {}
  editing.value = { code: '', names: {}, dimension: dimensions.value[0] ?? '', symbol: '', base_unit: null, factor: '1', offset: '0', precision: 2 }
}
function edit(u: Unit): void {
  errors.value = {}
  editing.value = { uuid: u.uuid, code: u.code, names: { ...u.names }, dimension: u.dimension, symbol: u.symbol, base_unit: u.base_unit, factor: u.factor, offset: u.offset, precision: u.precision }
}
const baseOptions = computed(() =>
  units.value
    .filter((u) => u.dimension === editing.value?.dimension && u.uuid !== editing.value?.uuid && u.base_unit === null)
    .map((u) => ({ value: u.code, label: `${u.name ?? u.code} (${u.code})` })),
)
async function save(): Promise<void> {
  const e = editing.value!
  errors.value = {}
  const body = {
    code: e.code,
    name: filledLocales(e.names),
    dimension: e.dimension.trim(),
    symbol: e.symbol,
    base_unit: e.base_unit,
    factor: e.factor.trim(),
    offset: e.offset.trim(),
    precision: e.precision,
  }
  try {
    if (e.uuid) await send('patch', `/units/${e.uuid}`, body)
    else await send('post', '/units', body)
    editing.value = null
    toast.add({ severity: 'success', summary: t('common.saved'), life: 3000 })
    await load()
  } catch (err) {
    errors.value = fieldErrors(err)
    toast.add({ severity: 'error', summary: errorText(err, t('building.save_failed')), life: 6000 })
  }
}
function destroy(u: Unit): void {
  confirm.require({
    message: t('building.ref.unit_delete_confirm', { name: u.name ?? u.code }),
    header: t('common.confirm'),
    acceptProps: { label: t('common.delete'), severity: 'danger' },
    rejectProps: { label: t('common.cancel'), severity: 'secondary' },
    accept: async () => {
      try {
        await send('delete', `/units/${u.uuid}`)
        await load()
      } catch (e) {
        toast.add({ severity: 'error', summary: errorText(e, t('building.save_failed')), life: 6000 })
      }
    },
  })
}
const rule = (u: { code: string; base_unit: string | null; factor: string; offset: string }) =>
  u.base_unit ? `1 ${u.code} = ${u.factor} ${u.base_unit}${Number(u.offset) ? ` ${Number(u.offset) > 0 ? '+' : '−'} ${u.offset.replace('-', '')}` : ''}` : ''
</script>

<template>
  <div class="flex flex-wrap items-center gap-2 mb-3">
    <p class="text-sm text-muted-color flex-1">{{ t('building.ref.units_hint') }}</p>
    <Button icon="pi pi-plus" :label="t('building.ref.new_unit')" data-testid="unit-new" @click="create" />
  </div>
  <DataTable :value="units" :loading="loading" data-key="uuid" size="small" row-group-mode="subheader" group-rows-by="dimension" data-testid="unit-table">
    <template #empty>{{ t('building.ref.no_units') }}</template>
    <template #groupheader="{ data }">
      <span class="font-semibold ltr-value">{{ data.dimension }}</span>
    </template>
    <Column :header="t('building.ref.code')">
      <template #body="{ data }"
        ><span class="ltr-value font-medium">{{ data.code }}</span> <span class="text-muted-color">{{ data.symbol }}</span></template
      >
    </Column>
    <Column :header="t('building.name')" field="name" />
    <Column :header="t('building.ref.conversion')">
      <template #body="{ data }"
        ><span v-if="data.base_unit" class="ltr-value text-sm">{{ rule(data) }}</span
        ><span v-else class="text-sm text-muted-color">{{ t('building.ref.is_base_unit') }}</span></template
      >
    </Column>
    <Column :header="t('building.ref.precision')" field="precision" />
    <Column class="w-28">
      <template #body="{ data }">
        <Button icon="pi pi-pencil" text rounded :aria-label="t('common.edit')" @click="edit(data)" />
        <Button icon="pi pi-trash" text rounded severity="danger" :aria-label="t('common.delete')" @click="destroy(data)" />
      </template>
    </Column>
  </DataTable>

  <Dialog
    :visible="!!editing"
    modal
    :header="editing?.uuid ? t('building.ref.edit_unit') : t('building.ref.new_unit')"
    :style="{ width: '40rem' }"
    @update:visible="(v: boolean) => !v && (editing = null)"
  >
    <form v-if="editing" class="flex flex-col gap-3" @submit.prevent="save">
      <div class="form-grid">
        <div class="field">
          <label for="unit-code">{{ t('building.ref.code') }}</label>
          <InputText id="unit-code" v-model="editing.code" class="ltr-value" />
          <span v-if="errors.code" class="field-error">{{ errors.code }}</span>
        </div>
        <div class="field">
          <label for="unit-symbol">{{ t('building.ref.symbol') }}</label>
          <InputText id="unit-symbol" v-model="editing.symbol" maxlength="16" />
          <span v-if="errors.symbol" class="field-error">{{ errors.symbol }}</span>
        </div>
        <div class="field">
          <label for="unit-dim">{{ t('building.ref.dimension') }}</label>
          <Select v-model="editing.dimension" input-id="unit-dim" :options="dimensions" editable class="ltr-value" />
          <small class="text-muted-color">{{ t('building.ref.dimension_hint') }}</small>
          <span v-if="errors.dimension" class="field-error">{{ errors.dimension }}</span>
        </div>
        <div class="field">
          <label for="unit-base">{{ t('building.ref.base_unit') }}</label>
          <Select v-model="editing.base_unit" input-id="unit-base" :options="baseOptions" option-label="label" option-value="value" show-clear :placeholder="t('building.ref.is_base_unit')" />
        </div>
        <div class="field">
          <label for="unit-factor">{{ t('building.ref.factor') }}</label>
          <InputText id="unit-factor" v-model="editing.factor" inputmode="decimal" class="ltr-value" :disabled="!editing.base_unit" />
          <span v-if="errors.factor" class="field-error">{{ errors.factor }}</span>
        </div>
        <div class="field">
          <label for="unit-offset">{{ t('building.ref.offset') }}</label>
          <InputText id="unit-offset" v-model="editing.offset" inputmode="decimal" class="ltr-value" :disabled="!editing.base_unit" />
          <span v-if="errors.offset" class="field-error">{{ errors.offset }}</span>
        </div>
        <div class="field">
          <label for="unit-prec">{{ t('building.ref.precision') }}</label>
          <InputNumber v-model="editing.precision" input-id="unit-prec" :min="0" :max="15" show-buttons :use-grouping="false" />
        </div>
        <LocaleFields v-model="editing.names" :label="t('building.name')" field="name" :errors="errors" id-prefix="unit-name" />
      </div>
      <p class="text-sm text-muted-color">{{ t('building.ref.conversion_hint') }}</p>
      <p v-if="editing.base_unit" class="text-sm ltr-value">{{ rule(editing) }}</p>
      <div class="flex justify-end gap-2">
        <Button type="button" severity="secondary" :label="t('common.cancel')" @click="editing = null" />
        <Button type="submit" :label="t('common.save')" data-testid="unit-save" />
      </div>
    </form>
  </Dialog>
</template>
