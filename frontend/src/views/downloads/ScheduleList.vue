<script setup lang="ts">
import Button from 'primevue/button'
import InputNumber from 'primevue/inputnumber'
import InputText from 'primevue/inputtext'
import MultiSelect from 'primevue/multiselect'
import Select from 'primevue/select'
import Tag from 'primevue/tag'
import { useToast } from 'primevue/usetoast'
import { computed, onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { ApiError, get } from '@/api/http'
import ConfigField from '@/components/config/ConfigField.vue'
import ConfigItem from '@/components/config/ConfigItem.vue'
import EmptyState from '@/components/config/EmptyState.vue'
import SettingSwitch from '@/components/config/SettingSwitch.vue'
import UserPicker from '@/components/UserPicker.vue'
import { formatDatetime } from '@/runtime/format'
import { labelOf } from '@/runtime/i18nText'
import { errorText } from '@/views/admin/building/shared'
import { downloadsApi, type DlFormat, type ParameterDoc, type ScheduleDoc } from './api'

/**
 * Scheduled downloads of a saved profile (specification §4.23): daily,
 * weekly, monthly or a cron expression, in a time zone, emailed to users
 * and roles. Each recipient receives their own run, with only what they may
 * see; a personal profile is only ever sent to its owner.
 */
const props = defineProps<{ profile: string; formats: DlFormat[]; parameters: ParameterDoc[]; personal: boolean }>()
const { t, locale } = useI18n()
const toast = useToast()
const list = ref<(ScheduleDoc & { _new?: boolean })[]>([])
const roles = ref<{ uuid: string; name: string }[]>([])
const userNames = ref<Record<string, string>>({})
const errors = ref<Record<number, Record<string, string>>>({})
const saving = ref<number | null>(null)
const zones = (() => {
  try {
    return (Intl as unknown as { supportedValuesOf: (k: string) => string[] }).supportedValuesOf('timeZone')
  } catch {
    return ['UTC']
  }
})()
const frequencies = computed(() => (['daily', 'weekly', 'monthly', 'cron'] as const).map((v) => ({ value: v, label: t(`dl.freq.${v}`) })))
const weekdays = computed(() => [0, 1, 2, 3, 4, 5, 6].map((d) => ({ value: d, label: new Intl.DateTimeFormat(locale.value, { weekday: 'long' }).format(new Date(Date.UTC(2023, 0, 1 + d))) })))
const formatOptions = computed(() => props.formats.map((f) => ({ value: f, label: t(`dl.format.${f}`) })))

async function load(): Promise<void> {
  try {
    list.value = await downloadsApi.schedules(props.profile)
    if (!props.personal) roles.value = (await get<{ data: { uuid: string; name: string }[] }>('/roles')).data
    const users = await get<{ data: { uuid: string; name: string }[] }>('/users', { per_page: 100 }).catch(() => ({ data: [] }))
    userNames.value = Object.fromEntries(users.data.map((u) => [u.uuid.toLowerCase(), u.name]))
  } catch (e) {
    toast.add({ severity: 'error', summary: errorText(e, t('dl.load_failed')), life: 6000 })
  }
}
onMounted(load)

function add(): void {
  list.value.push({
    _new: true,
    frequency: 'weekly',
    time: '07:00',
    weekday: 0,
    day: 1,
    cron: null,
    timezone: Intl.DateTimeFormat().resolvedOptions().timeZone || 'UTC',
    format: props.formats[0] ?? 'xlsx',
    parameters: {},
    recipients: { users: [], roles: [] },
    active: true,
  })
}
async function save(i: number): Promise<void> {
  const s = list.value[i]!
  saving.value = i
  errors.value = { ...errors.value, [i]: {} }
  try {
    const { _new, ...body } = s
    const saved = _new || !s.uuid ? await downloadsApi.createSchedule(props.profile, body) : await downloadsApi.saveSchedule(s.uuid, body)
    list.value[i] = saved
    toast.add({ severity: 'success', summary: t('dl.schedule_saved'), life: 3000 })
  } catch (e) {
    errors.value = { ...errors.value, [i]: e instanceof ApiError ? e.fieldErrors : {} }
    toast.add({ severity: 'error', summary: errorText(e, t('dl.save_failed')), life: 6000 })
  } finally {
    saving.value = null
  }
}
async function remove(i: number): Promise<void> {
  const s = list.value[i]!
  if (s.uuid && !s._new) {
    if (!window.confirm(t('dl.schedule_delete_confirm'))) return
    await downloadsApi.removeSchedule(s.uuid).catch((e) => toast.add({ severity: 'error', summary: errorText(e, t('dl.save_failed')), life: 6000 }))
  }
  list.value.splice(i, 1)
}
function addUser(i: number, u: { uuid: string; name: string } | null): void {
  if (!u) return
  const s = list.value[i]!
  userNames.value = { ...userNames.value, [u.uuid.toLowerCase()]: u.name }
  if (!s.recipients.users.includes(u.uuid.toLowerCase())) s.recipients.users.push(u.uuid.toLowerCase())
}
const summary = (s: ScheduleDoc) =>
  [t(`dl.freq.${s.frequency}`), s.frequency === 'cron' ? s.cron : s.time, s.timezone, t(`dl.format.${s.format}`)].filter(Boolean).join(' · ') + (s.active ? '' : ' · ' + t('dl.inactive'))
const when = (iso: string) => formatDatetime(iso, locale.value)
const err = (i: number, path: string) => errors.value[i]?.[path]
</script>

<template>
  <div class="flex flex-col gap-3" data-testid="dl-schedules">
    <EmptyState v-if="!list.length" icon="pi pi-calendar-clock" :title="t('dl.no_schedules')" :description="t('dl.no_schedules_desc')">
      <Button icon="pi pi-plus" :label="t('dl.add_schedule')" size="small" data-testid="dl-add-schedule-first" @click="add" />
    </EmptyState>
    <ConfigItem v-for="(s, i) in list" :key="s.uuid ?? `new-${i}`" :title="summary(s)" :index="i" :count="list.length" :testid="`dl-schedule-${i}`" @remove="remove(i)">
      <div class="flex flex-col gap-4">
        <p v-if="s.next_run_at" class="m-0 text-xs text-muted-color">
          {{ t('dl.next_run', { at: when(s.next_run_at) }) }}<template v-if="s.last_run_at"> · {{ t('dl.last_run', { at: when(s.last_run_at) }) }}</template>
          <Tag v-if="s.last_status === 'failed'" severity="danger" :value="t('dl.run_failed')" class="ms-1" />
        </p>
        <div class="cfg-row">
          <ConfigField :label="t('dl.frequency')" :for="`dl-freq-${i}`" width="sm" :error="err(i, 'frequency')">
            <Select v-model="s.frequency" :input-id="`dl-freq-${i}`" :options="frequencies" option-label="label" option-value="value" size="small" />
          </ConfigField>
          <ConfigField v-if="s.frequency !== 'cron'" :label="t('dl.time')" :for="`dl-time-${i}`" width="xs" :error="err(i, 'time')">
            <InputText :id="`dl-time-${i}`" v-model="s.time as string" type="time" size="small" class="ltr-value" dir="ltr" />
          </ConfigField>
          <ConfigField v-if="s.frequency === 'weekly'" :label="t('dl.weekday')" :for="`dl-wd-${i}`" width="sm">
            <Select v-model="s.weekday" :input-id="`dl-wd-${i}`" :options="weekdays" option-label="label" option-value="value" size="small" />
          </ConfigField>
          <ConfigField v-if="s.frequency === 'monthly'" :label="t('dl.day')" :for="`dl-day-${i}`" width="xs" :hint="t('dl.day_hint')">
            <InputNumber v-model="s.day" :input-id="`dl-day-${i}`" :min="1" :max="28" size="small" fluid />
          </ConfigField>
          <ConfigField v-if="s.frequency === 'cron'" :label="t('dl.cron')" :for="`dl-cron-${i}`" width="sm" :hint="t('dl.cron_hint')" :error="err(i, 'cron')">
            <InputText :id="`dl-cron-${i}`" v-model="s.cron as string" size="small" class="ltr-value" dir="ltr" />
          </ConfigField>
        </div>
        <div class="cfg-row">
          <ConfigField :label="t('dl.timezone')" :for="`dl-tz-${i}`" width="md" :error="err(i, 'timezone')">
            <Select v-model="s.timezone" :input-id="`dl-tz-${i}`" :options="zones" filter size="small" />
          </ConfigField>
          <ConfigField :label="t('dl.format_label')" :for="`dl-sf-${i}`" width="xs" :error="err(i, 'format')">
            <Select v-model="s.format" :input-id="`dl-sf-${i}`" :options="formatOptions" option-label="label" option-value="value" size="small" />
          </ConfigField>
        </div>
        <div v-for="p in parameters" :key="p.key" class="cfg-row">
          <ConfigField :label="labelOf(p.i18n.label, locale, p.key)" :for="`dl-sp-${i}-${p.key}`" width="sm" :error="err(i, `parameters.${p.key}`)" :required="p.required">
            <InputText :id="`dl-sp-${i}-${p.key}`" v-model="s.parameters[p.key] as string" size="small" :type="p.type === 'date' ? 'date' : 'text'" />
          </ConfigField>
        </div>
        <ConfigField :label="t('dl.recipients')" :for="`dl-ru-${i}`" width="lg" :hint="personal ? t('dl.recipients_personal') : t('dl.recipients_hint')" :error="err(i, 'recipients')">
          <div v-if="!personal" class="flex flex-col gap-2">
            <div class="flex flex-wrap gap-2">
              <Tag v-for="(u, k) in s.recipients.users" :key="u" severity="secondary">
                <span>{{ userNames[u] ?? t('dl.user') }}</span>
                <button type="button" class="ms-1" :aria-label="t('dl.remove')" @click="s.recipients.users.splice(k, 1)"><i class="pi pi-times text-xs" aria-hidden="true" /></button>
              </Tag>
            </div>
            <UserPicker :input-id="`dl-ru-${i}`" :model-value="null" @update:model-value="(u: { uuid: string; name: string } | null) => addUser(i, u)" />
            <MultiSelect
              v-model="s.recipients.roles"
              :options="roles.map((r) => ({ value: r.uuid.toLowerCase(), label: r.name }))"
              option-label="label"
              option-value="value"
              filter
              :placeholder="t('dl.roles')"
              :aria-label="t('dl.roles')"
              size="small"
              class="w-field-md"
            />
          </div>
        </ConfigField>
        <SettingSwitch :id="`dl-sa-${i}`" v-model="s.active" :label="t('dl.schedule_active')" :description="t('dl.schedule_active_desc')" />
        <Button :label="t('common.save')" icon="pi pi-check" size="small" class="self-start" :loading="saving === i" :data-testid="`dl-schedule-save-${i}`" @click="save(i)" />
      </div>
    </ConfigItem>
    <Button v-if="list.length" icon="pi pi-plus" :label="t('dl.add_schedule')" size="small" outlined class="self-start" data-testid="dl-add-schedule" @click="add" />
  </div>
</template>
