<script setup lang="ts">
import Button from 'primevue/button'
import InputNumber from 'primevue/inputnumber'
import Message from 'primevue/message'
import Select from 'primevue/select'
import ToggleSwitch from 'primevue/toggleswitch'
import { useToast } from 'primevue/usetoast'
import { computed, onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { ApiError, get, send } from '@/api/http'
import ExpressionInput from '@/builder/conditions/ExpressionInput.vue'
import { buildScope } from '@/builder/conditions/scope'
import { useBuilder } from '@/builder/useBuilder'
import SubjectPicker from '@/components/SubjectPicker.vue'
import { useSession } from '@/stores/session'
import { errorText } from '../building/shared'
import { labelOf, newUuid, type EscalationDoc, type SlaDoc, type StatusDoc, type TransitionDoc } from './api'

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
const calendarOptions = computed(() => calendars.value.map((c) => ({ value: c.uuid, label: c.name || c.key })))
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
</script>

<template>
  <div class="flex flex-col gap-3" data-testid="sla-editor">
    <div class="flex flex-wrap items-center gap-2">
      <Button icon="pi pi-plus" :label="t('sla.add')" size="small" :disabled="statuses.length === 0" data-testid="sla-add" @click="add" />
      <span class="flex-1" />
      <span v-if="dirty" class="text-sm text-muted-color">{{ t('workflow.unsaved') }}</span>
      <Button icon="pi pi-save" :label="t('workflow.save')" size="small" :loading="saving" :disabled="!dirty" data-testid="sla-save" @click="save" />
    </div>
    <Message v-if="statuses.length === 0" severity="info" :closable="false">{{ t('sla.no_statuses') }}</Message>
    <Message severity="secondary" :closable="false" class="text-sm">{{ t('sla.hint') }}</Message>

    <section v-for="(r, i) in rules" :key="r.uuid" class="rounded-lg border border-line p-3 flex flex-col gap-3">
      <div class="grid gap-3 md:grid-cols-4">
        <div class="field">
          <label :for="`sla-st-${i}`">{{ t('sla.status') }}</label>
          <Select v-model="r.status" :input-id="`sla-st-${i}`" :options="statusOptions" option-label="label" option-value="value" size="small" :invalid="!!err(`sla.${i}.status`)" />
        </div>
        <div class="field">
          <label :for="`sla-dur-${i}`">{{ t('sla.duration') }}</label>
          <InputNumber v-model="r.durationMinutes" :input-id="`sla-dur-${i}`" :min="1" :max="5256000" size="small" :suffix="` ${t('workflow.minutes')}`" :invalid="!!err(`sla.${i}.durationMinutes`)" />
        </div>
        <div class="field">
          <label :for="`sla-warn-${i}`">{{ t('sla.warn_before') }}</label>
          <InputNumber v-model="r.warnBeforeMinutes" :input-id="`sla-warn-${i}`" :min="1" size="small" :suffix="` ${t('workflow.minutes')}`" :invalid="!!err(`sla.${i}.warnBeforeMinutes`)" />
        </div>
        <div class="flex flex-col gap-2 justify-end">
          <label class="flex items-center gap-2 text-sm"><ToggleSwitch v-model="r.workingTime" />{{ t('sla.working_time') }}</label>
          <label class="flex items-center gap-2 text-sm"><ToggleSwitch v-model="r.active" />{{ t('sla.active') }}</label>
        </div>
      </div>
      <div v-if="r.workingTime" class="field md:w-1/2">
        <label :for="`sla-cal-${i}`">{{ t('sla.calendar') }}</label>
        <Select v-model="r.calendar" :input-id="`sla-cal-${i}`" :options="calendarOptions" option-label="label" option-value="value" show-clear :placeholder="t('sla.calendar_default')" size="small" />
      </div>
      <ExpressionInput v-model="r.condition" :scope="scope" expected="boolean" :label="t('sla.condition')" />

      <fieldset class="rounded border border-line p-2 flex flex-col gap-2">
        <legend class="text-sm font-medium px-1">{{ t('sla.escalations') }}</legend>
        <div v-for="(e, j) in r.escalations" :key="j" class="flex flex-wrap items-start gap-2 border-b border-line pb-2">
          <div class="field w-40">
            <label :for="`sla-after-${i}-${j}`">{{ t('sla.after') }}</label>
            <InputNumber v-model="e.afterMinutes" :input-id="`sla-after-${i}-${j}`" :min="0" size="small" :suffix="` ${t('workflow.minutes')}`" />
          </div>
          <div class="field w-40">
            <label :for="`sla-act-${i}-${j}`">{{ t('sla.action_label') }}</label>
            <Select :model-value="e.action" :input-id="`sla-act-${i}-${j}`" :options="actions" option-label="label" option-value="value" size="small" @update:model-value="(v) => setAction(e, v)" />
          </div>
          <div class="flex-1 min-w-60 flex flex-col gap-1">
            <template v-if="e.action === 'notify'">
              <label class="flex items-center gap-2 text-sm"
                ><ToggleSwitch :model-value="notifyTargets(e).some((x) => x.type === 'assignee')" @update:model-value="(v: boolean) => toggleBuiltIn(e, 'assignee', v)" />{{
                  t('sla.notify_assignee')
                }}</label
              >
              <label class="flex items-center gap-2 text-sm"
                ><ToggleSwitch :model-value="notifyTargets(e).some((x) => x.type === 'owner')" @update:model-value="(v: boolean) => toggleBuiltIn(e, 'owner', v)" />{{ t('sla.notify_owner') }}</label
              >
              <template v-for="(target, k) in notifyTargets(e)" :key="k">
                <div v-if="target.type !== 'assignee' && target.type !== 'owner'" class="flex gap-1">
                  <SubjectPicker
                    :model-value="target.uuid ? { type: target.type, uuid: target.uuid } : null"
                    :types="['role', 'department', 'user']"
                    class="flex-1"
                    @update:model-value="(v) => v && Object.assign(target, v)"
                  />
                  <Button icon="pi pi-times" text size="small" severity="secondary" :aria-label="t('workflow.remove')" @click="e.params.to = notifyTargets(e).filter((_, x) => x !== k)" />
                </div>
              </template>
              <Button icon="pi pi-plus" text size="small" :label="t('sla.add_recipient')" class="self-start" @click="addNotifySubject(e)" />
              <p class="text-xs text-muted-color">{{ t('sla.notify_hint') }}</p>
            </template>
            <SubjectPicker
              v-else-if="e.action === 'reassign'"
              :model-value="!Array.isArray(e.params.to) && e.params.to?.uuid ? { type: e.params.to.type, uuid: e.params.to.uuid } : null"
              :types="['role', 'department', 'user']"
              @update:model-value="(v) => (e.params.to = v ? { type: v.type, uuid: v.uuid } : { type: 'role', uuid: null })"
            />
            <Select v-else v-model="e.params.transition" :options="transitionOptions" option-label="label" option-value="value" size="small" :aria-label="t('sla.transition')" />
          </div>
          <Button icon="pi pi-times" text severity="secondary" size="small" :aria-label="t('workflow.remove')" @click="r.escalations.splice(j, 1)" />
        </div>
        <Button icon="pi pi-plus" text size="small" class="self-start" :label="t('sla.add_escalation')" @click="addEscalation(r)" />
      </fieldset>
      <Button icon="pi pi-trash" text severity="danger" size="small" class="self-start" :label="t('sla.remove')" @click="rules.splice(i, 1)" />
    </section>
    <Message v-for="(m, k) in errors" :key="k" severity="error" :closable="false" class="text-sm"
      ><span class="ltr-value">{{ k }}</span
      >: {{ m }}</Message
    >
  </div>
</template>
