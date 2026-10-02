<script setup lang="ts">
import Button from 'primevue/button'
import Column from 'primevue/column'
import DataTable from 'primevue/datatable'
import Dialog from 'primevue/dialog'
import InputNumber from 'primevue/inputnumber'
import InputText from 'primevue/inputtext'
import Select from 'primevue/select'
import Tag from 'primevue/tag'
import ToggleSwitch from 'primevue/toggleswitch'
import { useToast } from 'primevue/usetoast'
import { onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { ApiError, get, send } from '@/api/http'
import { useSession } from '@/stores/session'

interface LocaleRow {
  code: string
  native_name: string
  direction: 'ltr' | 'rtl'
  calendar: string
  digits: string
  date_format: string
  time_format: string
  number_format: { decimal: string; group: string }
  first_day_of_week: number
  fallback: string | null
  is_enabled: boolean
  is_default: boolean
  sort_order: number
}
const { t } = useI18n()
const toast = useToast()
const session = useSession()
const rows = ref<LocaleRow[]>([])
const editing = ref<(LocaleRow & { isNew?: boolean }) | null>(null)
const errors = ref<Record<string, string>>({})
const groupSeparators = [',', '.', ' ', '٬', "'", '']

async function load(): Promise<void> {
  rows.value = (await get<{ data: LocaleRow[] }>('/locales')).data
}
onMounted(load)

function create(): void {
  errors.value = {}
  editing.value = {
    isNew: true,
    code: '',
    native_name: '',
    direction: 'ltr',
    calendar: 'gregorian',
    digits: 'western',
    date_format: 'yyyy-MM-dd',
    time_format: '24h',
    number_format: { decimal: '.', group: ',' },
    first_day_of_week: 0,
    fallback: session.boot?.default_locale ?? 'en',
    is_enabled: false,
    is_default: false,
    sort_order: rows.value.length + 1,
  }
}

function edit(row: LocaleRow): void {
  errors.value = {}
  editing.value = JSON.parse(JSON.stringify(row))
}

async function save(): Promise<void> {
  const e = editing.value!
  const { isNew, code, ...rest } = e
  try {
    if (isNew) await send('post', '/locales', { code, ...rest })
    else await send('patch', `/locales/${code}`, rest)
    editing.value = null
    await load()
    await session.loadBootstrap()
    toast.add({ severity: 'success', summary: t('common.saved'), life: 3000 })
  } catch (err) {
    if (err instanceof ApiError) errors.value = { ...err.fieldErrors, _: Object.keys(err.fieldErrors).length ? '' : err.message }
  }
}
</script>

<template>
  <div class="flex justify-between items-center mb-3">
    <p class="text-muted-color">{{ t('locales.hint') }}</p>
    <Button icon="pi pi-plus" :label="t('locales.add')" @click="create" />
  </div>
  <DataTable :value="rows" size="small" data-key="code">
    <Column :header="t('locales.language')"
      ><template #body="{ data }"
        >{{ data.native_name }} <span class="ltr-value text-muted-color">({{ data.code }})</span></template
      ></Column
    >
    <Column :header="t('locales.direction')"
      ><template #body="{ data }">{{ t(`locales.dir.${data.direction}`) }}</template></Column
    >
    <Column :header="t('locales.status')"
      ><template #body="{ data }"
        ><Tag :severity="data.is_enabled ? 'success' : 'secondary'" :value="data.is_enabled ? t('common.enabled') : t('common.disabled')" />
        <Tag v-if="data.is_default" severity="info" :value="t('locales.default')" /></template
    ></Column>
    <Column style="width: 4rem"
      ><template #body="{ data }"><Button icon="pi pi-pencil" text :aria-label="t('common.edit')" @click="edit(data)" /></template
    ></Column>
  </DataTable>

  <Dialog :visible="!!editing" modal :header="editing?.isNew ? t('locales.add') : editing?.native_name" :style="{ width: '40rem' }" @update:visible="(v: boolean) => !v && (editing = null)">
    <form v-if="editing" class="form-grid" @submit.prevent="save">
      <p v-if="errors._" class="field-error col-span-full">{{ errors._ }}</p>
      <div class="field">
        <label for="lc">{{ t('locales.code') }}</label
        ><InputText id="lc" v-model="editing.code" :disabled="!editing.isNew" class="ltr-value" /><span v-if="errors.code" class="field-error">{{ errors.code }}</span>
      </div>
      <div class="field">
        <label for="ln">{{ t('locales.native_name') }}</label
        ><InputText id="ln" v-model="editing.native_name" :dir="editing.direction" />
      </div>
      <div class="field">
        <label for="ldir">{{ t('locales.direction') }}</label
        ><Select v-model="editing.direction" input-id="ldir" :options="['ltr', 'rtl'].map((v) => ({ v, l: t(`locales.dir.${v}`) }))" option-label="l" option-value="v" />
      </div>
      <div class="field">
        <label for="lcal">{{ t('settings.calendar.system') }}</label
        ><Select v-model="editing.calendar" input-id="lcal" :options="['gregorian', 'hijri', 'both'].map((v) => ({ v, l: t(`settings.calendar_system.${v}`) }))" option-label="l" option-value="v" />
      </div>
      <div class="field">
        <label for="ldg">{{ t('settings.formats.digits') }}</label
        ><Select v-model="editing.digits" input-id="ldg" :options="['western', 'arabic_indic'].map((v) => ({ v, l: t(`settings.digits.${v}`) }))" option-label="l" option-value="v" />
      </div>
      <div class="field">
        <label for="ldf">{{ t('settings.formats.date_format') }}</label
        ><InputText id="ldf" v-model="editing.date_format" class="ltr-value" />
      </div>
      <div class="field">
        <label for="ltf">{{ t('settings.formats.time_format') }}</label
        ><Select v-model="editing.time_format" input-id="ltf" :options="['24h', '12h']" />
      </div>
      <div class="field">
        <label for="ldec">{{ t('settings.formats.decimal_separator') }}</label
        ><Select v-model="editing.number_format.decimal" input-id="ldec" :options="['.', ',', '٫']" />
      </div>
      <div class="field">
        <label for="lgrp">{{ t('settings.formats.thousands_separator') }}</label
        ><Select v-model="editing.number_format.group" input-id="lgrp" :options="groupSeparators" />
      </div>
      <div class="field">
        <label for="lfd">{{ t('settings.formats.first_day_of_week') }}</label
        ><InputNumber v-model="editing.first_day_of_week" input-id="lfd" :min="0" :max="6" />
      </div>
      <div class="field">
        <label for="lfb">{{ t('locales.fallback') }}</label
        ><Select v-model="editing.fallback" input-id="lfb" :options="rows.filter((r) => r.code !== editing!.code)" option-label="native_name" option-value="code" show-clear />
      </div>
      <div class="field">
        <label for="lso">{{ t('common.sort_order') }}</label
        ><InputNumber v-model="editing.sort_order" input-id="lso" :min="0" />
      </div>
      <label class="flex items-center gap-2"><ToggleSwitch v-model="editing.is_enabled" />{{ t('common.enabled') }}</label>
      <label class="flex items-center gap-2"><ToggleSwitch v-model="editing.is_default" />{{ t('locales.default') }}</label>
      <div class="col-span-full flex justify-end gap-2">
        <Button type="button" severity="secondary" :label="t('common.cancel')" @click="editing = null" /><Button type="submit" :label="t('common.save')" />
      </div>
    </form>
  </Dialog>
</template>
