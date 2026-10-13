<script setup lang="ts">
import AutoComplete from 'primevue/autocomplete'
import Button from 'primevue/button'
import Dialog from 'primevue/dialog'
import InputNumber from 'primevue/inputnumber'
import InputText from 'primevue/inputtext'
import Message from 'primevue/message'
import Select from 'primevue/select'
import { useToast } from 'primevue/usetoast'
import { computed, onBeforeUnmount, onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { onBeforeRouteLeave, useRoute, useRouter } from 'vue-router'
import { ApiError, get } from '@/api/http'
import ExpressionInput from '@/builder/conditions/ExpressionInput.vue'
import { buildScope } from '@/builder/conditions/scope'
import I18nInput from '@/builder/I18nInput.vue'
import { createBuilder, provideBuilder } from '@/builder/useBuilder'
import ConfigField from '@/components/config/ConfigField.vue'
import ConfigItem from '@/components/config/ConfigItem.vue'
import ConfigSaveBar from '@/components/config/ConfigSaveBar.vue'
import ConfigSection from '@/components/config/ConfigSection.vue'
import SettingSwitch from '@/components/config/SettingSwitch.vue'
import UserPicker from '@/components/UserPicker.vue'
import { labelOf } from '@/runtime/i18nText'
import { useSession } from '@/stores/session'
import { errorText } from '@/views/admin/building/shared'
import { newUuid, workflowApi, type StatusDoc } from '@/views/admin/formconfig/api'
import ErrorList from '@/views/admin/formconfig/ErrorList.vue'
import StepListEditor from '@/views/admin/formconfig/StepListEditor.vue'
import { automationsApi, EVENT_TRIGGERS, SET_TRIGGERS, type AutomationDoc, type Frequency, type RunDetail, type TriggerDoc, type TriggerType } from './api'
import RunDetailView from './RunDetailView.vue'
import RunHistory from './RunHistory.vue'

/**
 * Building an automation (specification §4.31, ADR-0043): what starts it,
 * the condition the record must meet, the steps, and how it runs — as whom,
 * how many at once, how failures are retried, how many records a run may
 * change and when a run waits for confirmation, and how deep a chain of
 * automations may go. Saved whole with a concurrency hash; tested against a
 * sample record without keeping anything; started by hand when it has the
 * manual trigger; with its run history below.
 */
const route = useRoute()
const router = useRouter()
const { t, locale } = useI18n()
const toast = useToast()
const session = useSession()
const form = String(route.params.form)
const uuid = ref(String(route.params.automation))
const isNew = computed(() => uuid.value === 'new')
const doc = ref<AutomationDoc | null>(null)
const hash = ref('')
const saved = ref('')
const errors = ref<Record<string, string>>({})
const saving = ref(false)
const canRun = ref(false)
const dirty = computed(() => doc.value !== null && JSON.stringify(doc.value) !== saved.value)
const dl = computed(() => session.boot?.default_locale ?? 'en')
const err = (path: string) => errors.value[path]
const history = ref<InstanceType<typeof RunHistory> | null>(null)

const builder = createBuilder(form)
provideBuilder(builder)
const scope = computed(() => (builder.doc ? buildScope(builder.doc, builder.catalog.fields) : null))
const statuses = ref<StatusDoc[]>([])
const runAsName = ref('')

const TRIGGERS: TriggerType[] = ['record_created', 'record_updated', 'record_deleted', 'field_changed', 'status_changed', 'condition_true', 'schedule', 'date_reached', 'watched_folder', 'manual']
const triggerOptions = computed(() => TRIGGERS.map((v) => ({ value: v, label: t(`auto.trigger.${v}`) })))
const newTrigger = ref<TriggerType>('record_created')
const frequencies = computed(() => (['hourly', 'daily', 'weekly', 'monthly', 'cron'] as Frequency[]).map((v) => ({ value: v, label: t(`auto.freq.${v}`) })))
const weekdays = computed(() => [0, 1, 2, 3, 4, 5, 6].map((d) => ({ value: d, label: new Intl.DateTimeFormat(locale.value, { weekday: 'long' }).format(new Date(Date.UTC(2023, 0, 1 + d))) })))
const zones = (() => {
  try {
    return (Intl as unknown as { supportedValuesOf: (k: string) => string[] }).supportedValuesOf('timeZone')
  } catch {
    return ['UTC']
  }
})()
const units = computed(() => (['minutes', 'hours', 'days'] as const).map((v) => ({ value: v, label: t(`auto.unit.${v}`) })))
const directions = computed(() => (['before', 'after'] as const).map((v) => ({ value: v, label: t(`auto.direction.${v}`) })))
const UNIT_MINUTES = { minutes: 1, hours: 60, days: 1440 } as const

const fieldLabel = (f: { key: string; i18n?: { label?: Record<string, string> } }) => labelOf(f.i18n?.label ?? {}, locale.value, f.key)
const groupOf = (g: string | null | undefined) => (builder.doc?.groups ?? []).find((x) => x.uuid === g)
const mainFields = computed(() => (builder.doc?.fields ?? []).filter((f) => !['heading', 'paragraph', 'divider', 'spacer', 'html'].includes(f.type) && groupOf(f.group)?.type !== 'repeater'))
const fieldsOf = (types: string[] | null) => mainFields.value.filter((f) => (types === null ? f.type !== 'formula' : types.includes(f.type))).map((f) => ({ value: f.uuid, label: fieldLabel(f) }))
const changeFields = computed(() => fieldsOf(null))
const dateFields = computed(() => fieldsOf(['date', 'datetime_local', 'month', 'week', 'calendar_date']))
const fileFields = computed(() => fieldsOf(['file', 'files']))
const textFields = computed(() => fieldsOf(['text', 'textarea', 'email', 'url']))
const statusOptions = computed(() => [
  { value: null as string | null, label: t('auto.any_status') },
  ...statuses.value.map((s) => ({ value: s.uuid as string | null, label: labelOf(s.i18n.name, locale.value, s.key) })),
])

const triggerTypes = computed(() => (doc.value?.triggers ?? []).map((x) => x.type))
const onlyEvents = computed(() => triggerTypes.value.length > 0 && triggerTypes.value.every((x) => EVENT_TRIGGERS.includes(x) || x === 'manual'))
const hasManual = computed(() => triggerTypes.value.includes('manual'))
const runsOverSets = computed(() => triggerTypes.value.some((x) => SET_TRIGGERS.includes(x)))
const runAsOptions = computed(() => [
  { value: 'triggering_user', label: t('auto.run_as.triggering_user'), disabled: !onlyEvents.value },
  { value: 'specific_user', label: t('auto.run_as.specific_user'), disabled: false },
])

function empty(): AutomationDoc {
  return {
    key: '',
    i18n: { name: {}, description: {} },
    enabled: true,
    triggers: [{ uuid: newUuid(), type: 'record_created' }],
    condition: null,
    steps: [],
    runAs: 'triggering_user',
    runAsUser: null,
    concurrency: 5,
    retry: { maxAttempts: 3, backoffSeconds: [60, 300, 900] },
    maxRecords: 1000,
    confirmAbove: null,
    maxChainDepth: 3,
  }
}
function apply(a: AutomationDoc, h: string): void {
  doc.value = JSON.parse(JSON.stringify(a)) as AutomationDoc
  hash.value = h
  saved.value = JSON.stringify(doc.value)
}
async function load(): Promise<void> {
  try {
    if (isNew.value) {
      apply(empty(), '')
      saved.value = ''
    } else {
      const r = await automationsApi.load(uuid.value)
      apply(r.automation, r.hash)
      canRun.value = r.can_run
      runAsName.value = r.run_as_name ?? ''
    }
  } catch (e) {
    toast.add({ severity: 'error', summary: errorText(e, t('auto.load_failed')), life: 6000 })
  }
}
onMounted(async () => {
  await load()
  await builder.load().catch(() => undefined)
  statuses.value = (await workflowApi.load(form).catch(() => null))?.document.statuses ?? []
})
onBeforeUnmount(() => builder.cancelTimers())
onBeforeRouteLeave(() => !dirty.value || window.confirm(t('records.leave_unsaved')))

function addTrigger(): void {
  const type = newTrigger.value
  const tz = Intl.DateTimeFormat().resolvedOptions().timeZone || 'UTC'
  const trigger: TriggerDoc = { uuid: newUuid(), type }
  if (type === 'field_changed') trigger.field = changeFields.value[0]?.value ?? null
  if (type === 'status_changed') Object.assign(trigger, { fromStatus: null, toStatus: null })
  if (type === 'schedule') trigger.schedule = { frequency: 'daily', time: '07:00', weekday: 0, day: 1, minute: 0, cron: null, timezone: tz }
  if (type === 'date_reached') Object.assign(trigger, { dateField: dateFields.value[0]?.value ?? null, offsetMinutes: -1440 })
  if (type === 'watched_folder') Object.assign(trigger, { folder: '', fileField: fileFields.value[0]?.value ?? null, nameField: null })
  doc.value?.triggers.push(trigger)
  if (doc.value && !onlyEvents.value && doc.value.runAs === 'triggering_user') doc.value.runAs = 'specific_user'
}
// The offset of a date trigger, shown as an amount, a unit and before/after.
function offsetParts(m: number | null | undefined): { amount: number; unit: 'minutes' | 'hours' | 'days'; direction: 'before' | 'after' } {
  const v = Math.abs(m ?? 0)
  const unit = v % 1440 === 0 && v > 0 ? 'days' : v % 60 === 0 && v > 0 ? 'hours' : 'minutes'
  return { amount: v / UNIT_MINUTES[unit], unit, direction: (m ?? 0) < 0 ? 'before' : 'after' }
}
function setOffset(trig: TriggerDoc, part: 'amount' | 'unit' | 'direction', value: number | string | null): void {
  const p = { ...offsetParts(trig.offsetMinutes), [part]: value }
  const minutes = Number(p.amount ?? 0) * UNIT_MINUTES[p.unit as keyof typeof UNIT_MINUTES]
  trig.offsetMinutes = p.direction === 'before' ? -minutes : minutes
}
function setRunAsUser(u: { uuid: string; name: string } | null): void {
  if (!doc.value || !u) return
  doc.value.runAsUser = u.uuid.toLowerCase()
  runAsName.value = u.name
}
const backoffText = computed({
  get: () => (doc.value?.retry.backoffSeconds ?? []).join(', '),
  set: (v: string) => {
    if (doc.value)
      doc.value.retry.backoffSeconds = v
        .split(/[,\s]+/)
        .map((x) => Number.parseInt(x, 10))
        .filter((x) => Number.isFinite(x))
  },
})
const confirmValue = (): number | null => doc.value?.confirmAbove ?? null
function setConfirm(v: number | null): void {
  if (doc.value) doc.value.confirmAbove = v
}

async function save(): Promise<void> {
  if (!doc.value) return
  saving.value = true
  errors.value = {}
  try {
    const r = isNew.value ? await automationsApi.create(form, doc.value) : await automationsApi.save(uuid.value, doc.value, hash.value)
    apply(r.automation, r.hash)
    canRun.value = r.can_run
    runAsName.value = r.run_as_name ?? ''
    toast.add({ severity: 'success', summary: t('auto.saved'), life: 3000 })
    if (isNew.value && r.automation.uuid) {
      uuid.value = r.automation.uuid
      await router.replace({ name: 'admin.automations.edit', params: { form, automation: r.automation.uuid } })
    }
  } catch (e) {
    if (e instanceof ApiError && e.status === 409) {
      toast.add({ severity: 'warn', summary: t('auto.conflict'), life: 8000 })
      await load()
    } else {
      errors.value = e instanceof ApiError ? e.fieldErrors : {}
      toast.add({ severity: 'error', summary: errorText(e, t('auto.save_failed')), life: 6000 })
    }
  } finally {
    saving.value = false
  }
}
function discard(): void {
  doc.value = saved.value ? (JSON.parse(saved.value) as AutomationDoc) : empty()
  errors.value = {}
}

// Test against a sample record, and "Run now".
const testOpen = ref(false)
const testRecord = ref<{ uuid: string; title: string } | null>(null)
const testSuggestions = ref<{ uuid: string; title: string }[]>([])
const testResult = ref<RunDetail | null>(null)
const testing = ref(false)
async function searchRecords(e: { query: string }): Promise<void> {
  const r = await get<{ data: { uuid: string; title: string | null; values: Record<string, unknown> }[] }>(`/r/${form}`, { search: e.query || undefined, per_page: 10 }).catch(() => ({ data: [] }))
  // Records without a title are shown by their first text value.
  const firstText = (v: Record<string, unknown>) => Object.values(v ?? {}).find((x): x is string => typeof x === 'string' && x.trim() !== '')
  testSuggestions.value = r.data.map((x) => ({ uuid: x.uuid, title: x.title ?? firstText(x.values) ?? t('auto.a_record') }))
}
function openTest(): void {
  testResult.value = null
  testOpen.value = true
}
async function runTest(): Promise<void> {
  if (!testRecord.value) return
  testing.value = true
  try {
    testResult.value = await automationsApi.test(uuid.value, testRecord.value.uuid)
    await history.value?.reload()
  } catch (e) {
    toast.add({ severity: 'error', summary: errorText(e, t('auto.test_failed')), life: 6000 })
  } finally {
    testing.value = false
  }
}
async function runNow(): Promise<void> {
  if (!window.confirm(t('auto.run_now_confirm'))) return
  try {
    const r = await automationsApi.run(uuid.value)
    toast.add({ severity: r.status === 'failed' ? 'error' : 'success', summary: r.status === 'failed' ? (r.error ?? t('auto.status.failed')) : t(`auto.started_status.${r.status}`), life: 6000 })
    await history.value?.reload()
  } catch (e) {
    toast.add({ severity: 'error', summary: errorText(e, t('auto.run_failed')), life: 6000 })
  }
}
const triggerTitle = (x: TriggerDoc, i: number) => `${i + 1}. ${t(`auto.trigger.${x.type}`)}`
const back = { name: 'admin.forms.configure', params: { form, tab: 'automations' } }
const name = computed(() => (doc.value ? labelOf(doc.value.i18n.name, locale.value, '') : ''))
</script>

<template>
  <div class="flex flex-col gap-6" data-testid="automation-editor">
    <header class="flex flex-wrap items-start gap-3">
      <div class="flex-1 min-w-0">
        <RouterLink :to="back" class="text-sm text-primary inline-flex items-center gap-1 mb-1">
          <i class="pi pi-arrow-left rtl:rotate-180" aria-hidden="true" />{{ t('formconfig.tab.automations') }}
        </RouterLink>
        <h1 class="page-title !mb-1" dir="auto">{{ isNew ? t('auto.new') : name || t('auto.edit') }}</h1>
        <p class="m-0 text-sm text-muted-color">{{ t('auto.subtitle') }}</p>
      </div>
      <div v-if="!isNew" class="flex flex-wrap gap-2">
        <Button :label="t('auto.test')" icon="pi pi-play" size="small" outlined :disabled="dirty" data-testid="auto-test" @click="openTest" />
        <Button v-if="hasManual && canRun" :label="t('auto.run_now')" icon="pi pi-bolt" size="small" :disabled="dirty || !doc?.enabled" data-testid="auto-run-now" @click="runNow" />
      </div>
    </header>
    <Message v-if="dirty && !isNew" severity="info" :closable="false">{{ t('auto.save_before_test') }}</Message>

    <template v-if="doc">
      <ConfigSection id="auto-basics" :title="t('auto.section.basics')">
        <I18nInput v-model="doc.i18n.name" class="w-field-md" :label="t('auto.name')" :maxlength="120" :invalid="!!err(`i18n.name.${dl}`)" data-testid="auto-name" />
        <ConfigField :label="t('auto.key')" for="auto-key" width="sm" :hint="t('auto.key_hint')" :error="err('key')" required>
          <InputText id="auto-key" v-model="doc.key" dir="ltr" class="ltr-value" size="small" maxlength="48" data-testid="auto-key" />
        </ConfigField>
        <I18nInput v-model="doc.i18n.description" class="w-field-lg" multiline :rows="2" :label="t('auto.description')" :maxlength="500" />
        <SettingSwitch id="auto-enabled" v-model="doc.enabled" :label="t('auto.enabled')" :description="t('auto.enabled_desc')" />
      </ConfigSection>

      <ConfigSection id="auto-triggers" :title="t('auto.section.triggers')" :count="doc.triggers.length" :description="t('auto.triggers_hint')">
        <p v-if="err('triggers')" class="m-0 text-xs text-danger">{{ err('triggers') }}</p>
        <ConfigItem
          v-for="(trig, i) in doc.triggers"
          :key="trig.uuid"
          :title="triggerTitle(trig, i)"
          :index="i"
          :count="doc.triggers.length"
          :invalid="Object.keys(errors).some((k) => k.startsWith(`triggers.${i}.`))"
          :testid="`auto-trigger-${i}`"
          @remove="doc.triggers.splice(i, 1)"
        >
          <div class="flex flex-col gap-4">
            <p v-if="err(`triggers.${i}.type`)" class="m-0 text-xs text-danger">{{ err(`triggers.${i}.type`) }}</p>
            <p class="m-0 text-xs text-muted-color">{{ t(`auto.trigger_hint.${trig.type}`) }}</p>
            <ConfigField v-if="trig.type === 'field_changed'" :label="t('auto.field')" :for="`auto-tf-${i}`" width="md" :error="err(`triggers.${i}.field`)">
              <Select v-model="trig.field" :input-id="`auto-tf-${i}`" :options="changeFields" option-label="label" option-value="value" filter size="small" />
            </ConfigField>
            <div v-else-if="trig.type === 'status_changed'" class="cfg-row">
              <ConfigField :label="t('auto.from_status')" :for="`auto-fs-${i}`" width="sm" :error="err(`triggers.${i}.fromStatus`)">
                <Select v-model="trig.fromStatus" :input-id="`auto-fs-${i}`" :options="statusOptions" option-label="label" option-value="value" size="small" />
              </ConfigField>
              <ConfigField :label="t('auto.to_status')" :for="`auto-ts-${i}`" width="sm" :error="err(`triggers.${i}.toStatus`)">
                <Select v-model="trig.toStatus" :input-id="`auto-ts-${i}`" :options="statusOptions" option-label="label" option-value="value" size="small" />
              </ConfigField>
            </div>
            <template v-else-if="trig.type === 'schedule' && trig.schedule">
              <div class="cfg-row">
                <ConfigField :label="t('auto.frequency')" :for="`auto-sf-${i}`" width="sm" :error="err(`triggers.${i}.schedule.frequency`)">
                  <Select v-model="trig.schedule.frequency" :input-id="`auto-sf-${i}`" :options="frequencies" option-label="label" option-value="value" size="small" />
                </ConfigField>
                <ConfigField v-if="trig.schedule.frequency === 'hourly'" :label="t('auto.at_minute')" :for="`auto-sm-${i}`" width="xs">
                  <InputNumber v-model="trig.schedule.minute" :input-id="`auto-sm-${i}`" :min="0" :max="59" size="small" fluid />
                </ConfigField>
                <ConfigField v-else-if="trig.schedule.frequency !== 'cron'" :label="t('auto.time')" :for="`auto-st-${i}`" width="xs" :error="err(`triggers.${i}.schedule.time`)">
                  <InputText :id="`auto-st-${i}`" v-model="trig.schedule.time as string" type="time" size="small" class="ltr-value" dir="ltr" />
                </ConfigField>
                <ConfigField v-if="trig.schedule.frequency === 'weekly'" :label="t('auto.weekday')" :for="`auto-sw-${i}`" width="sm">
                  <Select v-model="trig.schedule.weekday" :input-id="`auto-sw-${i}`" :options="weekdays" option-label="label" option-value="value" size="small" />
                </ConfigField>
                <ConfigField v-if="trig.schedule.frequency === 'monthly'" :label="t('auto.day')" :for="`auto-sd-${i}`" width="xs" :hint="t('auto.day_hint')">
                  <InputNumber v-model="trig.schedule.day" :input-id="`auto-sd-${i}`" :min="1" :max="28" size="small" fluid />
                </ConfigField>
                <ConfigField v-if="trig.schedule.frequency === 'cron'" :label="t('auto.cron')" :for="`auto-sc-${i}`" width="sm" :hint="t('auto.cron_hint')" :error="err(`triggers.${i}.schedule.cron`)">
                  <InputText :id="`auto-sc-${i}`" v-model="trig.schedule.cron as string" size="small" class="ltr-value" dir="ltr" />
                </ConfigField>
              </div>
              <ConfigField :label="t('auto.timezone')" :for="`auto-sz-${i}`" width="md" :error="err(`triggers.${i}.schedule.timezone`)">
                <Select v-model="trig.schedule.timezone" :input-id="`auto-sz-${i}`" :options="zones" filter size="small" />
              </ConfigField>
            </template>
            <template v-else-if="trig.type === 'date_reached'">
              <ConfigField :label="t('auto.date_field')" :for="`auto-df-${i}`" width="md" :error="err(`triggers.${i}.dateField`)">
                <Select v-model="trig.dateField" :input-id="`auto-df-${i}`" :options="dateFields" option-label="label" option-value="value" size="small" />
              </ConfigField>
              <div class="cfg-row">
                <ConfigField :label="t('auto.offset')" :for="`auto-oa-${i}`" width="xs" :error="err(`triggers.${i}.offsetMinutes`)">
                  <InputNumber
                    :model-value="offsetParts(trig.offsetMinutes).amount"
                    :input-id="`auto-oa-${i}`"
                    :min="0"
                    size="small"
                    fluid
                    @update:model-value="(v: number | null) => setOffset(trig, 'amount', v)"
                  />
                </ConfigField>
                <ConfigField :label="t('auto.offset_unit')" :for="`auto-ou-${i}`" width="xs">
                  <Select
                    :model-value="offsetParts(trig.offsetMinutes).unit"
                    :input-id="`auto-ou-${i}`"
                    :options="units"
                    option-label="label"
                    option-value="value"
                    size="small"
                    @update:model-value="(v: string) => setOffset(trig, 'unit', v)"
                  />
                </ConfigField>
                <ConfigField :label="t('auto.offset_direction')" :for="`auto-od-${i}`" width="sm">
                  <Select
                    :model-value="offsetParts(trig.offsetMinutes).direction"
                    :input-id="`auto-od-${i}`"
                    :options="directions"
                    option-label="label"
                    option-value="value"
                    size="small"
                    @update:model-value="(v: string) => setOffset(trig, 'direction', v)"
                  />
                </ConfigField>
              </div>
            </template>
            <template v-else-if="trig.type === 'watched_folder'">
              <ConfigField :label="t('auto.folder')" :for="`auto-wf-${i}`" width="md" :hint="t('auto.folder_hint')" :error="err(`triggers.${i}.folder`)">
                <InputText :id="`auto-wf-${i}`" v-model="trig.folder as string" size="small" class="ltr-value" dir="ltr" :data-testid="`auto-folder-${i}`" />
              </ConfigField>
              <div class="cfg-row">
                <ConfigField :label="t('auto.file_field')" :for="`auto-ff-${i}`" width="sm" :error="err(`triggers.${i}.fileField`)">
                  <Select v-model="trig.fileField" :input-id="`auto-ff-${i}`" :options="fileFields" option-label="label" option-value="value" size="small" />
                </ConfigField>
                <ConfigField :label="t('auto.name_field')" :for="`auto-nf-${i}`" width="sm" :hint="t('auto.name_field_hint')" :error="err(`triggers.${i}.nameField`)">
                  <Select v-model="trig.nameField" :input-id="`auto-nf-${i}`" :options="textFields" option-label="label" option-value="value" show-clear size="small" />
                </ConfigField>
              </div>
            </template>
          </div>
        </ConfigItem>
        <div class="flex flex-wrap items-center gap-2">
          <Select
            v-model="newTrigger"
            :options="triggerOptions"
            option-label="label"
            option-value="value"
            size="small"
            class="w-field-sm"
            :aria-label="t('auto.trigger_type')"
            data-testid="auto-trigger-type"
          />
          <Button icon="pi pi-plus" :label="t('auto.add_trigger')" size="small" outlined data-testid="auto-add-trigger" @click="addTrigger" />
        </div>
      </ConfigSection>

      <ConfigSection id="auto-condition" :title="t('auto.section.condition')" :description="t('auto.condition_hint')">
        <ExpressionInput v-if="scope" v-model="doc.condition" :scope="scope" expected="boolean" allow-empty :label="t('auto.condition')" />
        <p v-if="err('condition')" class="m-0 text-xs text-danger">{{ err('condition') }}</p>
      </ConfigSection>

      <ConfigSection id="auto-steps" :title="t('auto.section.steps')" :count="doc.steps.length" :description="runsOverSets ? t('auto.steps_hint_sets') : t('auto.steps_hint')">
        <p v-if="err('steps')" class="m-0 text-xs text-danger">{{ err('steps') }}</p>
        <StepListEditor v-model:steps="doc.steps" :form="form" :errors="errors" path="steps" ids="auto" mode="automation" />
      </ConfigSection>

      <ConfigSection id="auto-run" :title="t('auto.section.run')" :description="t('auto.run_hint')">
        <div class="cfg-row">
          <ConfigField :label="t('auto.run_as_label')" for="auto-runas" width="sm" :error="err('runAs')">
            <Select v-model="doc.runAs" input-id="auto-runas" :options="runAsOptions" option-label="label" option-value="value" option-disabled="disabled" size="small" data-testid="auto-run-as" />
          </ConfigField>
          <ConfigField v-if="doc.runAs === 'specific_user'" :label="t('auto.run_as_user')" for="auto-runas-user" width="md" :hint="t('auto.run_as_user_hint')" :error="err('runAsUser')">
            <div class="flex flex-col gap-1">
              <span v-if="doc.runAsUser" class="text-sm" data-testid="auto-run-as-name">{{ runAsName || t('auto.a_user') }}</span>
              <UserPicker input-id="auto-runas-user" :model-value="null" @update:model-value="setRunAsUser" />
            </div>
          </ConfigField>
        </div>
        <div class="cfg-row">
          <ConfigField :label="t('auto.max_records')" for="auto-max" width="xs" :hint="t('auto.max_records_hint')" :error="err('maxRecords')">
            <InputNumber v-model="doc.maxRecords" input-id="auto-max" :min="1" :max="100000" size="small" fluid />
          </ConfigField>
          <ConfigField :label="t('auto.confirm_above')" for="auto-confirm" width="xs" :hint="t('auto.confirm_above_hint')" :error="err('confirmAbove')">
            <InputNumber :model-value="confirmValue()" input-id="auto-confirm" :min="1" :max="99999" size="small" fluid @update:model-value="setConfirm" />
          </ConfigField>
          <ConfigField :label="t('auto.concurrency')" for="auto-conc" width="xs" :hint="t('auto.concurrency_hint')" :error="err('concurrency')">
            <InputNumber v-model="doc.concurrency" input-id="auto-conc" :min="1" :max="50" size="small" fluid />
          </ConfigField>
        </div>
        <div class="cfg-row">
          <ConfigField :label="t('auto.max_attempts')" for="auto-attempts" width="xs" :hint="t('auto.max_attempts_hint')" :error="err('retry.maxAttempts')">
            <InputNumber v-model="doc.retry.maxAttempts" input-id="auto-attempts" :min="1" :max="10" size="small" fluid />
          </ConfigField>
          <ConfigField :label="t('auto.backoff')" for="auto-backoff" width="sm" :hint="t('auto.backoff_hint')" :error="err('retry.backoffSeconds')">
            <InputText id="auto-backoff" v-model="backoffText" size="small" class="ltr-value" dir="ltr" />
          </ConfigField>
          <ConfigField :label="t('auto.max_chain_depth')" for="auto-depth" width="xs" :hint="t('auto.max_chain_depth_hint')" :error="err('maxChainDepth')">
            <InputNumber v-model="doc.maxChainDepth" input-id="auto-depth" :min="1" :max="10" size="small" fluid />
          </ConfigField>
        </div>
      </ConfigSection>

      <ConfigSection v-if="!isNew" id="auto-history" :title="t('auto.section.history')" :description="t('auto.history_hint')">
        <RunHistory ref="history" :automation="uuid" :can-run="canRun" />
      </ConfigSection>

      <ErrorList :errors="errors" />
      <ConfigSaveBar :dirty="dirty" :saving="saving" testid="auto" @save="save" @discard="discard" />
    </template>

    <Dialog v-model:visible="testOpen" modal :header="t('auto.test_title')" class="w-full max-w-2xl" data-testid="auto-test-dialog">
      <div class="flex flex-col gap-4">
        <p class="m-0 text-sm text-muted-color">{{ t('auto.test_hint') }}</p>
        <div class="field">
          <label for="auto-test-record" class="text-sm font-medium">{{ t('auto.sample_record') }}</label>
          <AutoComplete
            v-model="testRecord"
            input-id="auto-test-record"
            :suggestions="testSuggestions"
            option-label="title"
            force-selection
            dropdown
            :placeholder="t('auto.pick_record')"
            data-testid="auto-test-record"
            @complete="searchRecords"
          />
        </div>
        <RunDetailView v-if="testResult" :run="testResult" />
      </div>
      <template #footer>
        <Button :label="t('common.close')" text @click="testOpen = false" />
        <Button :label="t('auto.run_test')" icon="pi pi-play" :loading="testing" :disabled="!testRecord" data-testid="auto-run-test" @click="runTest" />
      </template>
    </Dialog>
  </div>
</template>
