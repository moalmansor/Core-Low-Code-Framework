<script setup lang="ts">
import Button from 'primevue/button'
import InputNumber from 'primevue/inputnumber'
import Select from 'primevue/select'
import Tag from 'primevue/tag'
import { useToast } from 'primevue/usetoast'
import { computed, onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { ApiError, get, send } from '@/api/http'
import ExpressionInput from '@/builder/conditions/ExpressionInput.vue'
import { buildScope } from '@/builder/conditions/scope'
import { useBuilder } from '@/builder/useBuilder'
import { reportDirty } from '@/components/config/configScreen'
import SubjectPicker from '@/components/SubjectPicker.vue'
import { useSession } from '@/stores/session'
import ConfigField from '@/components/config/ConfigField.vue'
import ConfigItem from '@/components/config/ConfigItem.vue'
import ConfigSaveBar from '@/components/config/ConfigSaveBar.vue'
import ConfigSection from '@/components/config/ConfigSection.vue'
import EmptyState from '@/components/config/EmptyState.vue'
import SettingSwitch from '@/components/config/SettingSwitch.vue'
import TabIntro from '@/components/config/TabIntro.vue'
import { errorText } from '../building/shared'
import ErrorList from './ErrorList.vue'
import { labelOf, newUuid, type EscalationDoc, type SlaDoc, type StatusDoc, type TransitionDoc } from './api'
import { humanize } from '@/runtime/i18nText'

/**
 * SLA rules of a form (specification §4.12): time allowed in a status
 * (optionally in working time of a business calendar), a warning before the
 * deadline, and escalations after it — notify, reassign or move the record.
 * The rules are part of the workflow and take effect when the form is
 * published.
 */
const props = defineProps<{ form: string }>()
const { t, locale } = useI18n()
const toast = useToast()
const session = useSession()
const builder = useBuilder()

const rules = ref<SlaDoc[]>([])
const statuses = ref<StatusDoc[]>([])
const transitions = ref<TransitionDoc[]>([])
const calendars = ref<{ uuid: string; name: string; key: string }[]>([])
const hash = ref('')
const saved = ref('[]')
const saving = ref(false)
const errors = ref<Record<string, string>>({})

const dirty = computed(() => JSON.stringify(rules.value) !== saved.value)
const scope = computed(() => (builder.doc ? buildScope(builder.doc, builder.catalog.fields) : { fields: [], repeaters: [], rows: null }))
const defaultLocale = computed(() => session.boot?.default_locale ?? 'en')
const statusOptions = computed(() => statuses.value.map((s) => ({ value: s.uuid, label: labelOf(s.i18n.name, locale.value, s.key, defaultLocale.value) })))
const transitionOptions = computed(() => transitions.value.map((x) => ({ value: x.uuid, label: labelOf(x.i18n.name, locale.value, x.key, defaultLocale.value) })))
const calendarOptions = computed(() => calendars.value.map((c) => ({ value: c.uuid, label: c.name || humanize(c.key) })))
const actions = computed(() => (['notify', 'reassign', 'transition'] as const).map((v) => ({ value: v, label: t(`sla.action.${v}`) })))

function apply(data: { sla: SlaDoc[]; statuses: StatusDoc[]; transitions: TransitionDoc[]; hash: string }): void {
  rules.value = JSON.parse(JSON.stringify(data.sla)) as SlaDoc[]
  statuses.value = data.statuses
  transitions.value = data.transitions
  hash.value = data.hash
  saved.value = JSON.stringify(rules.value)
}

async function load(): Promise<void> {
  try {
    const [sla, cals] = await Promise.all([
      get<{ data: { sla: SlaDoc[]; statuses: StatusDoc[]; transitions: TransitionDoc[]; hash: string } }>(`/forms/${props.form}/sla-rules`),
      get<{ data: { uuid: string; name: string; key: string }[] }>('/business-calendars').catch(() => ({ data: [] })),
    ])
    apply(sla.data)
    calendars.value = cals.data
  } catch (e) {
    toast.add({ severity: 'error', summary: errorText(e, t('workflow.load_failed')), life: 6000 })
  }
}
onMounted(load)

function add(): void {
  const first = statuses.value[0]
  if (!first) return
  rules.value.push({ uuid: newUuid(), status: first.uuid, durationMinutes: 1440, workingTime: false, calendar: null, warnBeforeMinutes: null, escalations: [], condition: null, active: true })
}
function addEscalation(r: SlaDoc): void {
  r.escalations.push({ afterMinutes: 0, action: 'notify', params: { to: [{ type: 'assignee' }] } })
}
function setAction(e: EscalationDoc, action: EscalationDoc['action']): void {
  e.action = action
  e.params = action === 'notify' ? { to: [{ type: 'assignee' }] } : action === 'reassign' ? { to: { type: 'role', uuid: null } } : { transition: transitions.value[0]?.uuid }
}
const notifyTargets = (e: EscalationDoc) => (Array.isArray(e.params.to) ? e.params.to : [])
function toggleBuiltIn(e: EscalationDoc, type: 'assignee' | 'owner', on: boolean): void {
  const list = notifyTargets(e).filter((x) => x.type !== type)
  e.params.to = on ? [...list, { type }] : list
}
function addNotifySubject(e: EscalationDoc): void {
  e.params.to = [...notifyTargets(e), { type: 'role', uuid: null }]
}

async function save(): Promise<void> {
  saving.value = true
  errors.value = {}
  try {
    const { data } = await send<{ data: { sla: SlaDoc[]; statuses: StatusDoc[]; transitions: TransitionDoc[]; hash: string } }>('put', `/forms/${props.form}/sla-rules`, {
      sla: rules.value,
      base_hash: hash.value,
    })
    apply(data)
    toast.add({ severity: 'success', summary: t('workflow.saved'), life: 3000 })
  } catch (e) {
    if (e instanceof ApiError && e.status === 409) {
      toast.add({ severity: 'warn', summary: t('workflow.conflict'), life: 8000 })
      await load()
    } else {
      errors.value = e instanceof ApiError ? e.fieldErrors : {}
      toast.add({ severity: 'error', summary: errorText(e, t('workflow.save_failed')), life: 6000 })
    }
  } finally {
    saving.value = false
  }
}
const err = (path: string) => errors.value[path]
function discard(): void {
  rules.value = JSON.parse(saved.value) as SlaDoc[]
  errors.value = {}
}
reportDirty('sla', () => dirty.value)
const statusName = (uuid: string) => statusOptions.value.find((o) => o.value === uuid)?.label ?? t('sla.status')
function duration(minutes: number): string {
  if (minutes % 1440 === 0) return t('workflow_run.duration.days', { n: minutes / 1440 })
  if (minutes % 60 === 0) return t('workflow_run.duration.hours', { n: minutes / 60 })
  return t('workflow_run.duration.minutes', { n: minutes })
}
const ruleSummary = (r: SlaDoc) =>
  [duration(r.durationMinutes), r.workingTime ? t('sla.working_time') : null, r.escalations.length ? t('sla.escalation_count', { n: r.escalations.length }) : null].filter(Boolean).join(' · ')
</script>

<template>
  <div class="flex flex-col gap-6" data-testid="sla-editor">
    <TabIntro :title="t('formconfig.tab.sla')" :text="t('sla.hint')" />

    <EmptyState v-if="statuses.length === 0" icon="pi pi-clock" :title="t('sla.no_statuses_title')" :description="t('sla.no_statuses')" testid="sla-no-statuses" />
    <EmptyState v-else-if="!rules.length" icon="pi pi-clock" :title="t('sla.empty_title')" :description="t('sla.empty_text')" testid="sla-empty">
      <Button icon="pi pi-plus" :label="t('sla.add')" size="small" data-testid="sla-add" @click="add" />
    </EmptyState>

    <template v-else>
      <ConfigItem
        v-for="(r, i) in rules"
        :key="r.uuid"
        :title="statusName(r.status)"
        :subtitle="ruleSummary(r)"
        :index="i"
        :count="rules.length"
        :invalid="Object.keys(errors).some((k) => k.startsWith(`sla.${i}.`))"
        :testid="`sla-rule-${i}`"
        @remove="rules.splice(i, 1)"
      >
        <template #badges>
          <Tag v-if="!r.active" :value="t('reason_codes.inactive')" severity="secondary" />
        </template>

        <ConfigSection id="sla-time" :title="t('sla.section.time')">
          <ConfigField :label="t('sla.status')" :for="`sla-st-${i}`" width="md" :error="err(`sla.${i}.status`)">
            <Select v-model="r.status" :input-id="`sla-st-${i}`" :options="statusOptions" option-label="label" option-value="value" size="small" :invalid="!!err(`sla.${i}.status`)" />
          </ConfigField>
          <div class="cfg-row">
            <ConfigField :label="t('sla.duration')" :for="`sla-dur-${i}`" width="sm" :error="err(`sla.${i}.durationMinutes`)">
              <InputNumber
                v-model="r.durationMinutes"
                :input-id="`sla-dur-${i}`"
                :min="1"
                :max="5256000"
                size="small"
                :suffix="` ${t('workflow.minutes')}`"
                :invalid="!!err(`sla.${i}.durationMinutes`)"
              />
            </ConfigField>
            <ConfigField :label="t('sla.warn_before')" :for="`sla-warn-${i}`" width="sm" :hint="t('sla.warn_hint')" :error="err(`sla.${i}.warnBeforeMinutes`)">
              <InputNumber v-model="r.warnBeforeMinutes" :input-id="`sla-warn-${i}`" :min="1" size="small" :suffix="` ${t('workflow.minutes')}`" :invalid="!!err(`sla.${i}.warnBeforeMinutes`)" />
            </ConfigField>
          </div>
          <SettingSwitch :id="`sla-wt-${i}`" v-model="r.workingTime" :label="t('sla.working_time')" :description="t('sla.working_time_desc')" />
          <ConfigField v-if="r.workingTime" :label="t('sla.calendar')" :for="`sla-cal-${i}`" width="md">
            <Select
              v-model="r.calendar"
              :input-id="`sla-cal-${i}`"
              :options="calendarOptions"
              option-label="label"
              option-value="value"
              show-clear
              :placeholder="t('sla.calendar_default')"
              size="small"
            />
          </ConfigField>
          <SettingSwitch :id="`sla-active-${i}`" v-model="r.active" :label="t('sla.active')" :description="t('sla.active_desc')" />
        </ConfigSection>

        <ConfigSection id="sla-escalations" :title="t('sla.escalations')" :count="r.escalations.length" :description="t('sla.escalations_desc')">
          <EmptyState v-if="!r.escalations.length" icon="pi pi-bell" :title="t('sla.no_escalations')" :description="t('sla.no_escalations_text')">
            <Button icon="pi pi-plus" :label="t('sla.add_escalation')" size="small" outlined @click="addEscalation(r)" />
          </EmptyState>
          <template v-else>
            <div v-for="(e, j) in r.escalations" :key="j" class="cfg-stack rounded-lg border border-line p-3">
              <div class="cfg-row items-end">
                <ConfigField :label="t('sla.after')" :for="`sla-after-${i}-${j}`" width="sm" :hint="t('sla.after_hint')">
                  <InputNumber v-model="e.afterMinutes" :input-id="`sla-after-${i}-${j}`" :min="0" size="small" :suffix="` ${t('workflow.minutes')}`" />
                </ConfigField>
                <ConfigField :label="t('sla.action_label')" :for="`sla-act-${i}-${j}`" width="sm">
                  <Select
                    :model-value="e.action"
                    :input-id="`sla-act-${i}-${j}`"
                    :options="actions"
                    option-label="label"
                    option-value="value"
                    size="small"
                    @update:model-value="(v) => setAction(e, v)"
                  />
                </ConfigField>
                <span class="flex-1" />
                <Button icon="pi pi-trash" text rounded severity="danger" size="small" :aria-label="t('workflow.remove')" @click="r.escalations.splice(j, 1)" />
              </div>
              <template v-if="e.action === 'notify'">
                <SettingSwitch
                  :id="`sla-na-${i}-${j}`"
                  :model-value="notifyTargets(e).some((x) => x.type === 'assignee')"
                  :label="t('sla.notify_assignee')"
                  @update:model-value="(v: boolean) => toggleBuiltIn(e, 'assignee', v)"
                />
                <SettingSwitch
                  :id="`sla-no-${i}-${j}`"
                  :model-value="notifyTargets(e).some((x) => x.type === 'owner')"
                  :label="t('sla.notify_owner')"
                  @update:model-value="(v: boolean) => toggleBuiltIn(e, 'owner', v)"
                />
                <template v-for="(target, k) in notifyTargets(e)" :key="k">
                  <div v-if="target.type !== 'assignee' && target.type !== 'owner'" class="cfg-row items-end">
                    <ConfigField :label="t('sla.recipient')" width="md">
                      <SubjectPicker
                        :model-value="target.uuid ? { type: target.type, uuid: target.uuid } : null"
                        :types="['role', 'department', 'user']"
                        @update:model-value="(v) => v && Object.assign(target, v)"
                      />
                    </ConfigField>
                    <Button icon="pi pi-times" text rounded size="small" severity="secondary" :aria-label="t('workflow.remove')" @click="e.params.to = notifyTargets(e).filter((_, x) => x !== k)" />
                  </div>
                </template>
                <Button icon="pi pi-plus" text size="small" :label="t('sla.add_recipient')" class="self-start" @click="addNotifySubject(e)" />
                <p class="m-0 text-sm text-muted-color">{{ t('sla.notify_hint') }}</p>
              </template>
              <ConfigField v-else-if="e.action === 'reassign'" :label="t('sla.reassign_to')" width="md">
                <SubjectPicker
                  :model-value="!Array.isArray(e.params.to) && e.params.to?.uuid ? { type: e.params.to.type, uuid: e.params.to.uuid } : null"
                  :types="['role', 'department', 'user']"
                  @update:model-value="(v) => (e.params.to = v ? { type: v.type, uuid: v.uuid } : { type: 'role', uuid: null })"
                />
              </ConfigField>
              <ConfigField v-else :label="t('sla.transition')" :for="`sla-tr-${i}-${j}`" width="md">
                <Select v-model="e.params.transition" :input-id="`sla-tr-${i}-${j}`" :options="transitionOptions" option-label="label" option-value="value" size="small" />
              </ConfigField>
            </div>
            <Button icon="pi pi-plus" :label="t('sla.add_escalation')" size="small" outlined class="self-start" @click="addEscalation(r)" />
          </template>
        </ConfigSection>

        <ConfigSection id="sla-condition" :title="t('sla.condition')" :description="t('sla.condition_desc')" :default-open="false">
          <ExpressionInput v-model="r.condition" :scope="scope" expected="boolean" :label="t('sla.condition')" />
        </ConfigSection>
      </ConfigItem>
      <Button icon="pi pi-plus" :label="t('sla.add')" size="small" outlined class="self-start" data-testid="sla-add" @click="add" />
    </template>

    <ErrorList :errors="errors" />
    <ConfigSaveBar :dirty="dirty" :saving="saving" testid="sla" @save="save" @discard="discard" />
  </div>
</template>
