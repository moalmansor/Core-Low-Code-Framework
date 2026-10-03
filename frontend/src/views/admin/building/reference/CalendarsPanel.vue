<script setup lang="ts">
import Button from 'primevue/button'
import Checkbox from 'primevue/checkbox'
import Column from 'primevue/column'
import DataTable from 'primevue/datatable'
import DatePicker from 'primevue/datepicker'
import Dialog from 'primevue/dialog'
import InputNumber from 'primevue/inputnumber'
import InputText from 'primevue/inputtext'
import Select from 'primevue/select'
import Tag from 'primevue/tag'
import ToggleSwitch from 'primevue/toggleswitch'
import { useConfirm } from 'primevue/useconfirm'
import { useToast } from 'primevue/usetoast'
import { computed, onMounted, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { daysFromCivil } from '@/expressions/civil'
import { fromDays } from '@/expressions/ummAlQura'
import { useSession } from '@/stores/session'
import { get, send } from '@/api/http'
import LocaleFields from '../LocaleFields.vue'
import { errorText, fieldErrors, filledLocales, isoDay, parseIsoDay } from '../shared'

interface Hours {
  day: number
  start: string
  end: string
}
interface Calendar {
  uuid: string
  key: string
  timezone: string
  working_days: number[]
  working_hours: Hours[]
  country_code: string | null
  is_default: boolean
  name: string | null
  names: Record<string, string>
}
interface Holiday {
  uuid: string
  starts_on: string
  ends_on: string
  recurrence: 'none' | 'yearly_gregorian' | 'yearly_hijri'
  hijri_month: number | null
  hijri_day: number | null
  name: string | null
  names: Record<string, string>
}

const { t } = useI18n()
const toast = useToast()
const confirm = useConfirm()
const session = useSession()
const timezones = (Intl as unknown as { supportedValuesOf?: (k: string) => string[] }).supportedValuesOf?.('timeZone') ?? ['UTC']
const DAYS = [0, 1, 2, 3, 4, 5, 6]
const dayName = (d: number) => t(`settings.option.first_day_of_week.${d}`)
const listOf = (items: string[]) => new Intl.ListFormat(session.locale, { style: 'narrow', type: 'unit' }).format(items)
const minutes = (hhmm: string) => {
  const [h, m] = hhmm.split(':').map(Number)
  return (h ?? 0) * 60 + (m ?? 0)
}
const weeklyHours = (c: { working_hours: Hours[] }) => (c.working_hours.reduce((s, h) => s + Math.max(0, minutes(h.end) - minutes(h.start)), 0) / 60).toFixed(1)

const calendars = ref<Calendar[]>([])
const loading = ref(false)
const selected = ref<Calendar | null>(null)
async function load(): Promise<void> {
  loading.value = true
  try {
    calendars.value = (await get<{ data: Calendar[] }>('/business-calendars')).data
    selected.value = calendars.value.find((c) => c.uuid === selected.value?.uuid) ?? calendars.value[0] ?? null
  } catch (e) {
    toast.add({ severity: 'error', summary: errorText(e, t('building.load_failed')), life: 6000 })
  } finally {
    loading.value = false
  }
}
onMounted(load)

// Calendar dialog
interface Editing {
  uuid?: string
  key: string
  names: Record<string, string>
  timezone: string
  working_days: number[]
  working_hours: Hours[]
  country_code: string
  is_default: boolean
}
const editing = ref<Editing | null>(null)
const errors = ref<Record<string, string>>({})
const fill = ref({ start: '08:00', end: '16:00' })
function create(): void {
  errors.value = {}
  const tz = Intl.DateTimeFormat().resolvedOptions().timeZone || 'UTC'
  editing.value = { key: '', names: {}, timezone: tz, working_days: [0, 1, 2, 3, 4], working_hours: [], country_code: '', is_default: !calendars.value.length }
  fillHours()
}
function edit(c: Calendar): void {
  errors.value = {}
  editing.value = {
    uuid: c.uuid,
    key: c.key,
    names: { ...c.names },
    timezone: c.timezone,
    working_days: [...c.working_days],
    working_hours: c.working_hours.map((h) => ({ ...h })),
    country_code: c.country_code ?? '',
    is_default: c.is_default,
  }
}
/** Sets the same hours on every working day (one interval per day). */
function fillHours(): void {
  const e = editing.value!
  e.working_hours = [...e.working_days].sort().map((day) => ({ day, start: fill.value.start, end: fill.value.end }))
}
function addInterval(): void {
  editing.value!.working_hours.push({ day: editing.value!.working_days[0] ?? 1, start: fill.value.start, end: fill.value.end })
}
async function save(): Promise<void> {
  const e = editing.value!
  errors.value = {}
  const body = {
    key: e.key,
    name: filledLocales(e.names),
    timezone: e.timezone,
    working_days: [...e.working_days].sort(),
    working_hours: e.working_hours.filter((h) => e.working_days.includes(h.day)),
    country_code: e.country_code.trim().toUpperCase() || null,
    is_default: e.is_default,
  }
  try {
    if (e.uuid) await send('patch', `/business-calendars/${e.uuid}`, body)
    else await send('post', '/business-calendars', body)
    editing.value = null
    toast.add({ severity: 'success', summary: t('common.saved'), life: 3000 })
    await load()
  } catch (err) {
    errors.value = fieldErrors(err)
    toast.add({ severity: 'error', summary: errorText(err, t('building.save_failed')), life: 6000 })
  }
}
function destroy(c: Calendar): void {
  confirm.require({
    message: t('building.ref.calendar_delete_confirm', { name: c.name ?? c.key }),
    header: t('common.confirm'),
    acceptProps: { label: t('common.delete'), severity: 'danger' },
    rejectProps: { label: t('common.cancel'), severity: 'secondary' },
    accept: async () => {
      try {
        await send('delete', `/business-calendars/${c.uuid}`)
        await load()
      } catch (e) {
        toast.add({ severity: 'error', summary: errorText(e, t('building.save_failed')), life: 6000 })
      }
    },
  })
}

// Holidays
const holidays = ref<Holiday[]>([])
async function loadHolidays(): Promise<void> {
  holidays.value = selected.value ? (await get<{ data: Holiday[] }>(`/business-calendars/${selected.value.uuid}/holidays`)).data : []
}
watch(() => selected.value?.uuid, loadHolidays)
const recurrenceOptions = computed(() => (['none', 'yearly_gregorian', 'yearly_hijri'] as const).map((v) => ({ value: v, label: t(`building.ref.recurrence.${v}`) })))
const hijriMonths = computed(() => Array.from({ length: 12 }, (_, i) => ({ value: i + 1, label: t(`building.hijri_month.${i + 1}`) })))
const holiday = ref<{
  uuid?: string
  names: Record<string, string>
  starts: Date | null
  ends: Date | null
  recurrence: Holiday['recurrence']
  hijri_month: number | null
  hijri_day: number | null
} | null>(null)
const holidayErrors = ref<Record<string, string>>({})
function newHoliday(): void {
  holidayErrors.value = {}
  holiday.value = { names: {}, starts: null, ends: null, recurrence: 'none', hijri_month: null, hijri_day: null }
}
function editHoliday(h: Holiday): void {
  holidayErrors.value = {}
  holiday.value = { uuid: h.uuid, names: { ...h.names }, starts: parseIsoDay(h.starts_on), ends: parseIsoDay(h.ends_on), recurrence: h.recurrence, hijri_month: h.hijri_month, hijri_day: h.hijri_day }
}
/** Hijri month/day of the start date, offered when a yearly Hijri recurrence is chosen. */
const hijriOfStart = computed(() => {
  const d = holiday.value?.starts
  return d ? fromDays(daysFromCivil(d.getFullYear(), d.getMonth() + 1, d.getDate())) : null
})
watch(
  () => [holiday.value?.recurrence, holiday.value?.starts],
  () => {
    const h = holiday.value
    if (h && h.recurrence === 'yearly_hijri' && hijriOfStart.value && h.hijri_month === null) {
      h.hijri_month = hijriOfStart.value[1]
      h.hijri_day = hijriOfStart.value[2]
    }
  },
)
async function saveHoliday(): Promise<void> {
  const h = holiday.value!
  holidayErrors.value = {}
  const body = {
    name: filledLocales(h.names),
    starts_on: h.starts ? isoDay(h.starts) : null,
    ends_on: h.ends ? isoDay(h.ends) : h.starts ? isoDay(h.starts) : null,
    recurrence: h.recurrence,
    hijri_month: h.recurrence === 'yearly_hijri' ? h.hijri_month : null,
    hijri_day: h.recurrence === 'yearly_hijri' ? h.hijri_day : null,
  }
  try {
    const base = `/business-calendars/${selected.value!.uuid}/holidays`
    if (h.uuid) await send('patch', `${base}/${h.uuid}`, body)
    else await send('post', base, body)
    holiday.value = null
    await loadHolidays()
  } catch (e) {
    holidayErrors.value = fieldErrors(e)
    toast.add({ severity: 'error', summary: errorText(e, t('building.save_failed')), life: 6000 })
  }
}
function destroyHoliday(h: Holiday): void {
  confirm.require({
    message: t('building.ref.holiday_delete_confirm', { name: h.name ?? h.starts_on }),
    header: t('common.confirm'),
    acceptProps: { label: t('common.delete'), severity: 'danger' },
    rejectProps: { label: t('common.cancel'), severity: 'secondary' },
    accept: async () => {
      try {
        await send('delete', `/business-calendars/${selected.value!.uuid}/holidays/${h.uuid}`)
        await loadHolidays()
      } catch (e) {
        toast.add({ severity: 'error', summary: errorText(e, t('building.save_failed')), life: 6000 })
      }
    },
  })
}
const recurrenceText = (h: Holiday) =>
  h.recurrence === 'yearly_hijri' && h.hijri_month
    ? `${t('building.ref.recurrence.yearly_hijri')} · ${h.hijri_day} ${t(`building.hijri_month.${h.hijri_month}`)}`
    : t(`building.ref.recurrence.${h.recurrence}`)
const fmtDay = (s: string) => parseIsoDay(s)?.toLocaleDateString(session.locale) ?? s
</script>

<template>
  <div class="flex flex-wrap items-center gap-2 mb-3">
    <p class="text-sm text-muted-color flex-1">{{ t('building.ref.calendars_hint') }}</p>
    <Button icon="pi pi-plus" :label="t('building.ref.new_calendar')" data-testid="cal-new" @click="create" />
  </div>
  <DataTable v-model:selection="selected" :value="calendars" :loading="loading" selection-mode="single" data-key="uuid" size="small" data-testid="cal-table">
    <template #empty>{{ t('building.ref.no_calendars') }}</template>
    <Column :header="t('building.name')">
      <template #body="{ data }">
        <span class="font-medium">{{ data.name ?? data.key }}</span> <span class="text-xs text-muted-color ltr-value">{{ data.key }}</span>
        <Tag v-if="data.is_default" class="ms-1" severity="success" :value="t('building.ref.default')" />
      </template>
    </Column>
    <Column :header="t('building.ref.timezone')">
      <template #body="{ data }"
        ><span class="ltr-value">{{ data.timezone }}</span></template
      >
    </Column>
    <Column :header="t('building.ref.working_days')">
      <template #body="{ data }">{{ listOf(data.working_days.map((d: number) => dayName(d))) }}</template>
    </Column>
    <Column :header="t('building.ref.hours_per_week')">
      <template #body="{ data }">{{ weeklyHours(data) }}</template>
    </Column>
    <Column class="w-28">
      <template #body="{ data }">
        <Button icon="pi pi-pencil" text rounded :aria-label="t('common.edit')" @click.stop="edit(data)" />
        <Button icon="pi pi-trash" text rounded severity="danger" :aria-label="t('common.delete')" @click.stop="destroy(data)" />
      </template>
    </Column>
  </DataTable>

  <section v-if="selected" class="mt-6" data-testid="holidays">
    <div class="flex flex-wrap items-center gap-2 mb-2">
      <h3 class="font-semibold flex-1">{{ t('building.ref.holidays_of', { name: selected.name ?? selected.key }) }}</h3>
      <Button icon="pi pi-plus" size="small" :label="t('building.ref.new_holiday')" data-testid="holiday-new" @click="newHoliday" />
    </div>
    <DataTable :value="holidays" data-key="uuid" size="small">
      <template #empty>{{ t('building.ref.no_holidays') }}</template>
      <Column :header="t('building.name')" field="name" />
      <Column :header="t('building.ref.from')">
        <template #body="{ data }">{{ fmtDay(data.starts_on) }}</template>
      </Column>
      <Column :header="t('building.ref.to')">
        <template #body="{ data }">{{ fmtDay(data.ends_on) }}</template>
      </Column>
      <Column :header="t('building.ref.repeats')">
        <template #body="{ data }">{{ recurrenceText(data) }}</template>
      </Column>
      <Column class="w-28">
        <template #body="{ data }">
          <Button icon="pi pi-pencil" text rounded :aria-label="t('common.edit')" @click="editHoliday(data)" />
          <Button icon="pi pi-trash" text rounded severity="danger" :aria-label="t('common.delete')" @click="destroyHoliday(data)" />
        </template>
      </Column>
    </DataTable>
  </section>

  <Dialog
    :visible="!!editing"
    modal
    :header="editing?.uuid ? t('building.ref.edit_calendar') : t('building.ref.new_calendar')"
    :style="{ width: '46rem' }"
    @update:visible="(v: boolean) => !v && (editing = null)"
  >
    <form v-if="editing" class="flex flex-col gap-3" @submit.prevent="save">
      <div class="form-grid">
        <div class="field">
          <label for="cal-key">{{ t('building.key') }}</label>
          <InputText id="cal-key" v-model="editing.key" class="ltr-value" />
          <span v-if="errors.key" class="field-error">{{ errors.key }}</span>
        </div>
        <div class="field">
          <label for="cal-tz">{{ t('building.ref.timezone') }}</label>
          <Select v-model="editing.timezone" input-id="cal-tz" :options="timezones" filter />
          <span v-if="errors.timezone" class="field-error">{{ errors.timezone }}</span>
        </div>
        <div class="field">
          <label for="cal-cc">{{ t('building.ref.country') }}</label>
          <InputText id="cal-cc" v-model="editing.country_code" maxlength="2" class="ltr-value" placeholder="SA" />
          <span v-if="errors.country_code" class="field-error">{{ errors.country_code }}</span>
        </div>
        <LocaleFields v-model="editing.names" :label="t('building.name')" field="name" :errors="errors" id-prefix="cal-name" />
      </div>
      <fieldset class="field">
        <legend class="text-sm font-medium mb-1">{{ t('building.ref.working_days') }}</legend>
        <div class="flex flex-wrap gap-3">
          <label v-for="d in DAYS" :key="d" class="flex items-center gap-1"><Checkbox v-model="editing.working_days" :value="d" />{{ dayName(d) }}</label>
        </div>
        <span v-if="errors.working_days" class="field-error">{{ errors.working_days }}</span>
      </fieldset>
      <fieldset class="field">
        <legend class="text-sm font-medium mb-1">{{ t('building.ref.working_hours') }}</legend>
        <div class="flex flex-wrap items-center gap-2 mb-2 text-sm">
          <span>{{ t('building.ref.same_hours') }}</span>
          <InputText v-model="fill.start" type="time" class="ltr-value w-32" :aria-label="t('building.ref.start')" />
          <span>–</span>
          <InputText v-model="fill.end" type="time" class="ltr-value w-32" :aria-label="t('building.ref.end')" />
          <Button type="button" size="small" severity="secondary" :label="t('building.ref.apply_to_working_days')" @click="fillHours" />
        </div>
        <div v-for="(h, i) in editing.working_hours" :key="i" class="flex flex-wrap items-center gap-2 mb-1">
          <Select
            v-model="h.day"
            :options="editing.working_days.map((d) => ({ value: d, label: dayName(d) }))"
            option-label="label"
            option-value="value"
            class="w-40"
            :aria-label="t('building.ref.day')"
          />
          <InputText v-model="h.start" type="time" class="ltr-value w-32" :aria-label="t('building.ref.start')" />
          <span>–</span>
          <InputText v-model="h.end" type="time" class="ltr-value w-32" :aria-label="t('building.ref.end')" />
          <span class="text-xs text-muted-color">{{ t('building.ref.hours', { n: (Math.max(0, minutes(h.end) - minutes(h.start)) / 60).toFixed(1) }) }}</span>
          <Button type="button" icon="pi pi-times" text rounded severity="danger" :aria-label="t('common.remove')" @click="editing.working_hours.splice(i, 1)" />
          <span v-if="errors[`working_hours.${i}.end`]" class="field-error w-full">{{ errors[`working_hours.${i}.end`] }}</span>
        </div>
        <div>
          <Button type="button" size="small" text icon="pi pi-plus" :label="t('building.ref.add_interval')" @click="addInterval" />
        </div>
        <small class="text-muted-color">{{ t('building.ref.hours_total', { n: weeklyHours(editing) }) }}</small>
      </fieldset>
      <label class="flex items-center gap-2"><ToggleSwitch v-model="editing.is_default" />{{ t('building.ref.make_default') }}</label>
      <div class="flex justify-end gap-2">
        <Button type="button" severity="secondary" :label="t('common.cancel')" @click="editing = null" />
        <Button type="submit" :label="t('common.save')" data-testid="cal-save" />
      </div>
    </form>
  </Dialog>

  <Dialog
    :visible="!!holiday"
    modal
    :header="holiday?.uuid ? t('building.ref.edit_holiday') : t('building.ref.new_holiday')"
    :style="{ width: '36rem' }"
    @update:visible="(v: boolean) => !v && (holiday = null)"
  >
    <form v-if="holiday" class="flex flex-col gap-3" @submit.prevent="saveHoliday">
      <LocaleFields v-model="holiday.names" :label="t('building.name')" field="name" :errors="holidayErrors" id-prefix="hol-name" />
      <div class="form-grid">
        <div class="field">
          <label for="hol-from">{{ t('building.ref.from') }}</label>
          <DatePicker v-model="holiday.starts" input-id="hol-from" date-format="yy-mm-dd" />
          <span v-if="holidayErrors.starts_on" class="field-error">{{ holidayErrors.starts_on }}</span>
        </div>
        <div class="field">
          <label for="hol-to">{{ t('building.ref.to') }}</label>
          <DatePicker v-model="holiday.ends" input-id="hol-to" date-format="yy-mm-dd" :min-date="holiday.starts ?? undefined" />
          <span v-if="holidayErrors.ends_on" class="field-error">{{ holidayErrors.ends_on }}</span>
        </div>
        <div class="field">
          <label for="hol-rec">{{ t('building.ref.repeats') }}</label>
          <Select v-model="holiday.recurrence" input-id="hol-rec" :options="recurrenceOptions" option-label="label" option-value="value" />
        </div>
      </div>
      <p v-if="hijriOfStart" class="text-xs text-muted-color">{{ t('building.ref.hijri_of_start', { d: hijriOfStart[2], m: t(`building.hijri_month.${hijriOfStart[1]}`), y: hijriOfStart[0] }) }}</p>
      <div v-if="holiday.recurrence === 'yearly_hijri'" class="form-grid">
        <div class="field">
          <label for="hol-hm">{{ t('building.ref.hijri_month') }}</label>
          <Select v-model="holiday.hijri_month" input-id="hol-hm" :options="hijriMonths" option-label="label" option-value="value" />
          <span v-if="holidayErrors.hijri_month" class="field-error">{{ holidayErrors.hijri_month }}</span>
        </div>
        <div class="field">
          <label for="hol-hd">{{ t('building.ref.hijri_day') }}</label>
          <InputNumber v-model="holiday.hijri_day" input-id="hol-hd" :min="1" :max="30" :use-grouping="false" />
          <span v-if="holidayErrors.hijri_day" class="field-error">{{ holidayErrors.hijri_day }}</span>
        </div>
      </div>
      <div class="flex justify-end gap-2">
        <Button type="button" severity="secondary" :label="t('common.cancel')" @click="holiday = null" />
        <Button type="submit" :label="t('common.save')" data-testid="holiday-save" />
      </div>
    </form>
  </Dialog>
</template>
