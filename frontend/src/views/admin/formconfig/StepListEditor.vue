<script setup lang="ts">
import Button from 'primevue/button'
import InputNumber from 'primevue/inputnumber'
import InputText from 'primevue/inputtext'
import MultiSelect from 'primevue/multiselect'
import Password from 'primevue/password'
import Select from 'primevue/select'
import Tag from 'primevue/tag'
import Textarea from 'primevue/textarea'
import { computed, onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { get } from '@/api/http'
import ExpressionInput from '@/builder/conditions/ExpressionInput.vue'
import { buildScope } from '@/builder/conditions/scope'
import FormPicker from '@/builder/FormPicker.vue'
import { useBuilder } from '@/builder/useBuilder'
import ConfigField from '@/components/config/ConfigField.vue'
import ConfigItem from '@/components/config/ConfigItem.vue'
import SettingSwitch from '@/components/config/SettingSwitch.vue'
import SubjectPicker from '@/components/SubjectPicker.vue'
import UserPicker from '@/components/UserPicker.vue'
import { useSession } from '@/stores/session'
import { downloadsApi, type ProfileSummary } from '@/views/downloads/api'
import { documentsApi, type DocumentSummary } from '../documents/api'
import { templatesApi, type TemplateSummary } from '../notifications/api'
import { fieldTreeApi, labelOf, newUuid, workflowApi, type ActionStepDoc, type FieldValueDoc, type PathNode, type RecipientType, type StepConfig, type StepType, type TransitionDoc } from './api'
import RecipientList from './RecipientList.vue'

/**
 * The steps of a custom action (specification §4.15) or an automation
 * (§4.31), one after the other on each record: set fields, change status,
 * messages, webhooks, documents, linked records, assignment and downloads,
 * each optionally conditional. Automations add waits, a retry choice when a
 * step fails, and downloads e-mailed to users and roles.
 */
const props = defineProps<{
  form: string
  /** Validation errors by path, from the last save. */
  errors: Record<string, string>
  /** Where these steps sit in the saved document, e.g. "actions.0.steps". */
  path: string
  /** Distinguishes element ids and test ids when several lists are on the page. */
  ids: string
  mode: 'action' | 'automation'
}>()
const steps = defineModel<ActionStepDoc[]>('steps', { required: true })
const { t, locale } = useI18n()
const session = useSession()
const builder = useBuilder()
const dl = computed(() => session.boot?.default_locale ?? 'en')
const err = (j: number, rest: string) => props.errors[`${props.path}.${j}.${rest}`]
const invalid = (j: number) => Object.keys(props.errors).some((k) => k.startsWith(`${props.path}.${j}.`))
const scope = computed(() => (builder.doc ? buildScope(builder.doc, builder.catalog.fields) : { fields: [], repeaters: [], rows: null }))
const fieldLabel = (f: { key: string; i18n?: { label?: Record<string, string> } }) => labelOf(f.i18n?.label ?? {}, locale.value, f.key, dl.value)
const groupOf = (uuid: string | null | undefined) => (builder.doc?.groups ?? []).find((g) => g.uuid === uuid)
const mainFields = computed(() =>
  (builder.doc?.fields ?? []).filter((f) => !['heading', 'paragraph', 'divider', 'spacer', 'html', 'formula'].includes(f.type) && groupOf(f.group)?.type !== 'repeater'),
)
const settable = computed(() => mainFields.value.filter((f) => !['file', 'files', 'image', 'signature', 'autonumber'].includes(f.type)).map((f) => ({ value: f.uuid, label: fieldLabel(f) })))
const fileFields = computed(() => mainFields.value.filter((f) => ['file', 'files'].includes(f.type)).map((f) => ({ value: f.uuid, label: fieldLabel(f) })))

const transitions = ref<TransitionDoc[]>([])
const templates = ref<TemplateSummary[]>([])
const documents = ref<DocumentSummary[]>([])
const downloadProfiles = ref<ProfileSummary[]>([])
const tree = ref<PathNode[]>([])
const roles = ref<{ uuid: string; name: string }[]>([])
const userNames = ref<Record<string, string>>({})
onMounted(async () => {
  transitions.value = (await workflowApi.load(props.form).catch(() => null))?.document.transitions ?? []
  templates.value = await templatesApi.list(props.form).catch(() => [])
  tree.value = await fieldTreeApi.load(props.form).catch(() => [])
  documents.value = (await documentsApi.list(props.form).catch(() => null))?.rows.filter((d) => d.active) ?? []
  downloadProfiles.value = (await downloadsApi.list(props.form).catch(() => null))?.profiles.filter((p) => !p.personal && p.active) ?? []
  if (props.mode === 'automation') {
    roles.value = (await get<{ data: { uuid: string; name: string }[] }>('/roles').catch(() => ({ data: [] }))).data
    const users = await get<{ data: { uuid: string; name: string }[] }>('/users', { per_page: 100 }).catch(() => ({ data: [] }))
    userNames.value = Object.fromEntries(users.data.map((u) => [u.uuid.toLowerCase(), u.name]))
  }
  await Promise.all(steps.value.filter((s) => s.type === 'create_linked_record').map((s) => loadTarget(s.config.form)))
})
const downloadOptions = computed(() => downloadProfiles.value.map((p) => ({ value: p.uuid, label: p.name })))
const downloadFormats = (uuid: string | null | undefined) => (downloadProfiles.value.find((p) => p.uuid === uuid)?.formats ?? []).map((f) => ({ value: f, label: t(`dl.format.${f}`) }))
const transitionOptions = computed(() => transitions.value.map((x) => ({ value: x.uuid, label: labelOf(x.i18n.name, locale.value, x.key, dl.value) })))
const templateOptions = computed(() => templates.value.map((x) => ({ value: x.uuid, label: x.subject })))
const documentOptions = computed(() => documents.value.map((d) => ({ value: d.uuid, label: d.name })))
const formatsOf = (uuid: string | null | undefined) => (documents.value.find((d) => d.uuid === uuid)?.formats ?? []).map((f) => ({ value: f, label: t(`doc.format_${f}`) }))
const roleOptions = computed(() => roles.value.map((r) => ({ value: r.uuid.toLowerCase(), label: r.name })))

const ACTION_STEPS: StepType[] = ['update_fields', 'change_status', 'send_email', 'send_notification', 'call_webhook', 'generate_document', 'create_linked_record', 'assign', 'run_download']
const stepTypes = computed(() => (props.mode === 'automation' ? [...ACTION_STEPS, 'wait_delay', 'wait_condition'] : ACTION_STEPS).map((v) => ({ value: v, label: t(`act.step.${v}`) })))
const newStep = ref<StepType>('update_fields')
const methods = ['POST', 'PUT', 'PATCH', 'GET', 'DELETE'].map((v) => ({ value: v, label: v }))
const bodyFormats = computed(() => (['json', 'form', 'text'] as const).map((v) => ({ value: v, label: t(`act.body_kind.${v}`) })))
const assigneeTypes = computed(() => (['user', 'role', 'department'] as const).map((v) => ({ value: v, label: t(`notif.recipient.${v}`) })))
const failureChoices = computed(() => (['stop', 'continue', 'retry'] as const).map((v) => ({ value: v, label: t(`auto.on_failure.${v}`) })))
const timeoutChoices = computed(() => (['stop', 'continue'] as const).map((v) => ({ value: v, label: t(`auto.on_timeout.${v}`) })))
const downloadScopes = computed(() => (['record', 'profile'] as const).map((v) => ({ value: v, label: t(`auto.download_scope.${v}`) })))
const recipientTypes: RecipientType[] = ['user', 'role', 'department', 'field', 'assignee', 'status_changer', 'creator', 'actor', 'static']
const slots = ['to', 'cc', 'bcc'] as const

function removeSecret(c: StepConfig): void {
  c.secretSet = false
  c.secret = null
}
function addStep(): void {
  const type = newStep.value
  const firstProfile = downloadProfiles.value[0]
  const config: ActionStepDoc['config'] = {
    update_fields: { set: [{ field: settable.value[0]?.value ?? '', value: null }] },
    change_status: { transition: transitionOptions.value[0]?.value ?? '' },
    send_email: { template: templateOptions.value[0]?.value ?? null, recipients: { to: [{ type: 'actor', ref: null }], cc: [], bcc: [] }, documents: [] },
    send_notification: { template: null, recipients: { to: [{ type: 'creator', ref: null }], cc: [], bcc: [] } },
    call_webhook: { url: 'https://', method: 'POST', format: 'json', headers: [], body: '', secret: null, secretSet: false },
    generate_document: {
      template: documentOptions.value[0]?.value ?? null,
      format: documents.value[0]?.formats[0] ?? 'pdf',
      attachToField: props.mode === 'automation' ? (fileFields.value[0]?.value ?? null) : null,
    },
    create_linked_record: props.mode === 'automation' ? { form: null, linkField: null, prefill: [] } : { form: null, linkField: null, prefill: [], open: true },
    assign: { to: { type: 'role', uuid: null } },
    run_download:
      props.mode === 'automation'
        ? { profile: firstProfile?.uuid ?? null, format: firstProfile?.formats[0] ?? 'xlsx', scope: 'profile', deliverTo: { users: [], roles: [] } }
        : { profile: firstProfile?.uuid ?? null, format: firstProfile?.formats[0] ?? 'xlsx' },
    wait_delay: { minutes: 60 },
    wait_condition: { condition: null, timeoutMinutes: 1440, onTimeout: 'stop' },
  }[type] as ActionStepDoc['config']
  steps.value.push({ uuid: newUuid(), type, config, condition: null, onFailure: 'stop' })
}
function move<T>(list: T[], i: number, d: -1 | 1): void {
  const j = i + d
  if (j < 0 || j >= list.length) return
  ;[list[i], list[j]] = [list[j]!, list[i]!]
}
const addValue = (list: FieldValueDoc[]) => list.push({ field: '', value: null })
function addDeliveryUser(c: StepConfig, u: { uuid: string; name: string } | null): void {
  if (!u || !c.deliverTo) return
  const id = u.uuid.toLowerCase()
  userNames.value = { ...userNames.value, [id]: u.name }
  if (!c.deliverTo.users.includes(id)) c.deliverTo.users.push(id)
}

// Fields of the form a linked record is created in: the ones that link back here, and the rest to fill.
interface TargetField {
  uuid: string
  key: string
  type: string
  relation?: string | null
  i18n?: { label?: Record<string, string> }
}
const targets = ref<Record<string, { fields: TargetField[]; relations: { uuid: string; target: string }[] }>>({})
async function loadTarget(form: string | null | undefined): Promise<void> {
  if (!form || targets.value[form]) return
  const d = (await get<{ data: { document: { fields: TargetField[]; relations: { uuid: string; target: string }[] } } }>(`/forms/${form}/draft`).catch(() => null))?.data.document
  if (d) targets.value = { ...targets.value, [form]: { fields: d.fields, relations: d.relations ?? [] } }
}
const linkFields = (form: string | null | undefined) => {
  const tg = form ? targets.value[form] : undefined
  if (!tg) return []
  const back = new Set(tg.relations.filter((r) => r.target === props.form).map((r) => r.uuid))
  return tg.fields.filter((f) => f.relation && back.has(f.relation)).map((f) => ({ value: f.uuid, label: fieldLabel(f) }))
}
const targetFields = (form: string | null | undefined) =>
  (form ? (targets.value[form]?.fields ?? []) : [])
    .filter((f) => !['heading', 'paragraph', 'divider', 'spacer', 'html', 'formula'].includes(f.type))
    .map((f) => ({ value: f.uuid, label: fieldLabel(f) }))
function setTargetForm(cfg: ActionStepDoc['config'], form: string | null): void {
  cfg.form = form
  cfg.linkField = null
  cfg.prefill = []
  void loadTarget(form).then(() => {
    cfg.linkField = linkFields(form)[0]?.value ?? null
  })
}

const stepTitle = (s: ActionStepDoc, i: number) => `${i + 1}. ${t(`act.step.${s.type}`)}`
const stepSubtitle = (s: ActionStepDoc) => [s.condition ? t('act.conditional') : null, s.onFailure === 'retry' ? t('auto.on_failure.retry') : null].filter(Boolean).join(' · ') || undefined
const tokenText = (token: string) => `{{ ${token} }}`
</script>

<template>
  <div class="flex flex-col gap-4">
    <ConfigItem
      v-for="(s, j) in steps"
      :key="s.uuid"
      :title="stepTitle(s, j)"
      :subtitle="stepSubtitle(s)"
      :index="j"
      :count="steps.length"
      movable
      :invalid="invalid(j)"
      :testid="`act-step-${ids}-${j}`"
      @move="(d: -1 | 1) => move(steps, j, d)"
      @remove="steps.splice(j, 1)"
    >
      <div class="flex flex-col gap-4">
        <p v-if="err(j, 'type')" class="m-0 text-xs text-danger">{{ err(j, 'type') }}</p>
        <!-- Set fields -->
        <template v-if="s.type === 'update_fields'">
          <div v-for="(item, k) in s.config.set" :key="k" class="flex flex-wrap items-start gap-2">
            <Select
              v-model="item.field"
              :options="settable"
              option-label="label"
              option-value="value"
              filter
              size="small"
              class="w-field-sm"
              :aria-label="t('act.field')"
              :invalid="!!err(j, `config.set.${k}.field`)"
            />
            <div class="flex-1 min-w-[16rem]">
              <ExpressionInput v-model="item.value" :scope="scope" :label="t('act.new_value')" :allow-empty="false" />
              <p v-if="err(j, `config.set.${k}.value`)" class="m-0 text-xs text-danger">{{ err(j, `config.set.${k}.value`) }}</p>
            </div>
            <Button icon="pi pi-times" text rounded size="small" severity="secondary" :aria-label="t('act.remove')" @click="s.config.set!.splice(k, 1)" />
          </div>
          <p class="m-0 text-xs text-muted-color">{{ t('act.value_hint') }}</p>
          <Button icon="pi pi-plus" :label="t('act.add_field')" size="small" text class="self-start" @click="addValue(s.config.set!)" />
        </template>

        <!-- Change status -->
        <ConfigField v-else-if="s.type === 'change_status'" :label="t('act.transition')" :for="`act-tr-${ids}-${j}`" width="md" :error="err(j, 'config.transition')">
          <Select v-model="s.config.transition" :input-id="`act-tr-${ids}-${j}`" :options="transitionOptions" option-label="label" option-value="value" size="small" />
        </ConfigField>

        <!-- Email and notification -->
        <template v-else-if="s.type === 'send_email' || s.type === 'send_notification'">
          <ConfigField
            :label="t('act.template')"
            :for="`act-tpl-${ids}-${j}`"
            width="md"
            :hint="s.type === 'send_notification' ? t('act.notify_template_hint') : undefined"
            :error="err(j, 'config.template')"
          >
            <Select
              v-model="s.config.template"
              :input-id="`act-tpl-${ids}-${j}`"
              :options="templateOptions"
              option-label="label"
              option-value="value"
              :show-clear="s.type === 'send_notification'"
              size="small"
            />
          </ConfigField>
          <div v-for="slot in s.type === 'send_email' ? slots : (['to'] as const)" :key="slot" class="flex flex-col gap-2">
            <h4 class="m-0 text-sm font-semibold">{{ t(`notif.slot.${slot}`) }}</h4>
            <RecipientList
              v-model="s.config.recipients![slot]"
              :types="s.type === 'send_email' ? recipientTypes : recipientTypes.filter((x) => x !== 'static')"
              :tree="tree"
              :errors="errors"
              :path="`${path}.${j}.config.recipients.${slot}`"
              :testid="`act-${slot}-${ids}-${j}`"
            />
          </div>
          <p v-if="mode === 'automation'" class="m-0 text-xs text-muted-color">{{ t('auto.actor_hint') }}</p>
          <ConfigField v-if="s.type === 'send_email' && documents.length" :label="t('notif.documents')" width="lg" :hint="t('notif.documents_hint')">
            <div class="flex flex-col gap-2">
              <div v-for="(d, k) in s.config.documents" :key="k" class="flex flex-wrap items-center gap-2">
                <Select v-model="d.template" :options="documentOptions" option-label="label" option-value="value" size="small" class="w-field-md" :aria-label="t('notif.document')" />
                <Select v-model="d.format" :options="formatsOf(d.template)" option-label="label" option-value="value" size="small" class="w-field-xs" :aria-label="t('doc.format')" />
                <Button icon="pi pi-times" text rounded size="small" :aria-label="t('notif.remove_document')" @click="s.config.documents!.splice(k, 1)" />
                <p v-if="err(j, `config.documents.${k}`)" class="basis-full m-0 text-xs text-danger">{{ err(j, `config.documents.${k}`) }}</p>
              </div>
              <Button
                icon="pi pi-plus"
                :label="t('notif.add_document')"
                size="small"
                text
                class="self-start"
                @click="s.config.documents!.push({ template: documents[0]!.uuid, format: documents[0]!.formats[0] ?? 'pdf' })"
              />
            </div>
          </ConfigField>
        </template>

        <!-- Webhook -->
        <template v-else-if="s.type === 'call_webhook'">
          <div class="cfg-row">
            <ConfigField :label="t('act.method')" :for="`act-m-${ids}-${j}`" width="xs">
              <Select v-model="s.config.method" :input-id="`act-m-${ids}-${j}`" :options="methods" option-label="label" option-value="value" size="small" />
            </ConfigField>
            <ConfigField :label="t('act.url')" :for="`act-url-${ids}-${j}`" width="lg" :hint="t('act.url_hint')" :error="err(j, 'config.url')">
              <InputText :id="`act-url-${ids}-${j}`" v-model="s.config.url" dir="ltr" class="ltr-value" size="small" fluid />
            </ConfigField>
          </div>
          <div v-if="s.config.method !== 'GET'" class="flex flex-col gap-2">
            <ConfigField :label="t('act.body_format')" :for="`act-bf-${ids}-${j}`" width="sm">
              <Select v-model="s.config.format" :input-id="`act-bf-${ids}-${j}`" :options="bodyFormats" option-label="label" option-value="value" size="small" />
            </ConfigField>
            <ConfigField :label="t('act.body')" :for="`act-body-${ids}-${j}`" width="lg" :hint="t('act.body_hint', { example: tokenText('subject') })" :error="err(j, 'config.body')">
              <Textarea :id="`act-body-${ids}-${j}`" v-model="s.config.body" rows="5" dir="ltr" class="ltr-value font-mono text-sm" fluid />
            </ConfigField>
          </div>
          <ConfigField :label="t('act.headers')" width="lg" :hint="t('act.headers_hint')" :error="err(j, 'config.headers')">
            <div class="flex flex-col gap-2">
              <div v-for="(h, k) in s.config.headers" :key="k" class="flex flex-wrap items-center gap-2">
                <InputText v-model="h.name" dir="ltr" size="small" class="w-field-sm ltr-value" :aria-label="t('act.header_name')" :placeholder="t('act.header_name')" />
                <Password
                  v-model="h.value as string"
                  :feedback="false"
                  toggle-mask
                  size="small"
                  :placeholder="h.set ? t('act.kept') : t('act.header_value')"
                  :aria-label="t('act.header_value')"
                  input-class="ltr-value"
                />
                <Button icon="pi pi-times" text rounded size="small" :aria-label="t('act.remove')" @click="s.config.headers!.splice(k, 1)" />
                <p v-if="err(j, `config.headers.${k}.name`)" class="basis-full m-0 text-xs text-danger">{{ err(j, `config.headers.${k}.name`) }}</p>
              </div>
              <Button icon="pi pi-plus" :label="t('act.add_header')" size="small" text class="self-start" @click="s.config.headers!.push({ name: '', value: null })" />
            </div>
          </ConfigField>
          <ConfigField :label="t('act.secret')" :for="`act-sec-${ids}-${j}`" width="md" :hint="s.config.secretSet ? t('act.secret_set') : t('act.secret_hint')">
            <Password
              v-model="s.config.secret as string"
              :input-id="`act-sec-${ids}-${j}`"
              :feedback="false"
              toggle-mask
              size="small"
              :placeholder="s.config.secretSet ? t('act.kept') : ''"
              input-class="ltr-value"
            />
          </ConfigField>
          <Button v-if="s.config.secretSet" :label="t('act.remove_secret')" text size="small" severity="danger" class="self-start" @click="removeSecret(s.config)" />
        </template>

        <!-- Document -->
        <template v-else-if="s.type === 'generate_document'">
          <div class="cfg-row">
            <ConfigField :label="t('act.document')" :for="`act-doc-${ids}-${j}`" width="md" :error="err(j, 'config.template')">
              <Select v-model="s.config.template" :input-id="`act-doc-${ids}-${j}`" :options="documentOptions" option-label="label" option-value="value" size="small" />
            </ConfigField>
            <ConfigField :label="t('doc.format')" :for="`act-docf-${ids}-${j}`" width="xs" :error="err(j, 'config.format')">
              <Select v-model="s.config.format" :input-id="`act-docf-${ids}-${j}`" :options="formatsOf(s.config.template)" option-label="label" option-value="value" size="small" />
            </ConfigField>
          </div>
          <ConfigField
            :label="t('act.attach_to')"
            :for="`act-att-${ids}-${j}`"
            width="md"
            :hint="mode === 'automation' ? t('auto.attach_to_hint') : t('act.attach_to_hint')"
            :error="err(j, 'config.attachToField')"
          >
            <Select
              v-model="s.config.attachToField"
              :input-id="`act-att-${ids}-${j}`"
              :options="fileFields"
              option-label="label"
              option-value="value"
              :show-clear="mode === 'action'"
              :placeholder="mode === 'action' ? t('act.download_instead') : undefined"
              size="small"
            />
          </ConfigField>
        </template>

        <!-- Linked record -->
        <template v-else-if="s.type === 'create_linked_record'">
          <div class="cfg-row">
            <ConfigField :label="t('act.target_form')" :for="`act-tf-${ids}-${j}`" width="md" :error="err(j, 'config.form')">
              <FormPicker :model-value="s.config.form ?? null" :input-id="`act-tf-${ids}-${j}`" @update:model-value="(v: string | null | undefined) => setTargetForm(s.config, v ?? null)" />
            </ConfigField>
            <ConfigField :label="t('act.link_field')" :for="`act-lf-${ids}-${j}`" width="md" :hint="t('act.link_field_hint')" :error="err(j, 'config.linkField')">
              <Select v-model="s.config.linkField" :input-id="`act-lf-${ids}-${j}`" :options="linkFields(s.config.form)" option-label="label" option-value="value" size="small" />
            </ConfigField>
          </div>
          <h4 class="m-0 text-sm font-semibold">{{ t('act.prefill') }}</h4>
          <div v-for="(item, k) in s.config.prefill" :key="k" class="flex flex-wrap items-start gap-2">
            <Select v-model="item.field" :options="targetFields(s.config.form)" option-label="label" option-value="value" filter size="small" class="w-field-sm" :aria-label="t('act.field')" />
            <div class="flex-1 min-w-[16rem]">
              <ExpressionInput v-model="item.value" :scope="scope" :label="t('act.prefill_value')" :allow-empty="false" />
            </div>
            <Button icon="pi pi-times" text rounded size="small" severity="secondary" :aria-label="t('act.remove')" @click="s.config.prefill!.splice(k, 1)" />
          </div>
          <Button icon="pi pi-plus" :label="t('act.add_field')" size="small" text class="self-start" @click="addValue(s.config.prefill!)" />
          <SettingSwitch v-if="mode === 'action'" :id="`act-open-${ids}-${j}`" v-model="s.config.open as boolean" :label="t('act.open')" :description="t('act.open_desc')" />
        </template>

        <!-- Download -->
        <template v-else-if="s.type === 'run_download'">
          <div class="cfg-row">
            <ConfigField
              :label="t('act.download_profile')"
              :for="`act-dlp-${ids}-${j}`"
              width="md"
              :hint="mode === 'automation' ? t('auto.download_hint') : t('act.download_profile_hint')"
              :error="err(j, 'config.profile')"
            >
              <Select v-model="s.config.profile" :input-id="`act-dlp-${ids}-${j}`" :options="downloadOptions" option-label="label" option-value="value" size="small" />
            </ConfigField>
            <ConfigField :label="t('doc.format')" :for="`act-dlf-${ids}-${j}`" width="xs" :error="err(j, 'config.format')">
              <Select v-model="s.config.format" :input-id="`act-dlf-${ids}-${j}`" :options="downloadFormats(s.config.profile)" option-label="label" option-value="value" size="small" />
            </ConfigField>
            <ConfigField v-if="mode === 'automation'" :label="t('auto.download_scope_label')" :for="`act-dls-${ids}-${j}`" width="sm" :error="err(j, 'config.scope')">
              <Select v-model="s.config.scope" :input-id="`act-dls-${ids}-${j}`" :options="downloadScopes" option-label="label" option-value="value" size="small" />
            </ConfigField>
          </div>
          <ConfigField
            v-if="mode === 'automation' && s.config.deliverTo"
            :label="t('auto.deliver_to')"
            :for="`act-dlu-${ids}-${j}`"
            width="lg"
            :hint="t('auto.deliver_to_hint')"
            :error="err(j, 'config.deliverTo')"
          >
            <div class="flex flex-col gap-2">
              <div class="flex flex-wrap gap-2">
                <Tag v-for="(u, k) in s.config.deliverTo.users" :key="u" severity="secondary">
                  <span>{{ userNames[u] ?? t('auto.a_user') }}</span>
                  <button type="button" class="ms-1" :aria-label="t('act.remove')" @click="s.config.deliverTo.users.splice(k, 1)"><i class="pi pi-times text-xs" aria-hidden="true" /></button>
                </Tag>
              </div>
              <UserPicker :input-id="`act-dlu-${ids}-${j}`" :model-value="null" @update:model-value="(u: { uuid: string; name: string } | null) => addDeliveryUser(s.config, u)" />
              <MultiSelect v-model="s.config.deliverTo.roles" :options="roleOptions" option-label="label" option-value="value" filter :placeholder="t('auto.roles')" size="small" class="w-field-md" />
            </div>
          </ConfigField>
        </template>

        <!-- Assign -->
        <div v-else-if="s.type === 'assign'" class="cfg-row">
          <ConfigField :label="t('act.assign_to')" :for="`act-as-${ids}-${j}`" width="sm" :error="err(j, 'config.to')">
            <Select
              v-model="s.config.to!.type"
              :input-id="`act-as-${ids}-${j}`"
              :options="assigneeTypes"
              option-label="label"
              option-value="value"
              size="small"
              @update:model-value="s.config.to!.uuid = null"
            />
          </ConfigField>
          <div class="w-field-md self-end">
            <SubjectPicker
              :model-value="{ type: s.config.to!.type, uuid: s.config.to!.uuid } as never"
              :types="[s.config.to!.type]"
              @update:model-value="(v: { uuid: string | null } | null) => (s.config.to!.uuid = v?.uuid ?? null)"
            />
          </div>
        </div>

        <!-- Wait -->
        <ConfigField v-else-if="s.type === 'wait_delay'" :label="t('auto.wait_minutes')" :for="`act-wm-${ids}-${j}`" width="xs" :hint="t('auto.wait_hint')" :error="err(j, 'config.minutes')">
          <InputNumber v-model="s.config.minutes" :input-id="`act-wm-${ids}-${j}`" :min="1" :max="525600" size="small" fluid />
        </ConfigField>
        <template v-else-if="s.type === 'wait_condition'">
          <div class="flex flex-col gap-1">
            <h4 class="m-0 text-sm font-semibold">{{ t('auto.wait_until') }}</h4>
            <ExpressionInput v-model="s.config.condition" :scope="scope" expected="boolean" :allow-empty="false" :label="t('auto.wait_until')" />
            <p v-if="err(j, 'config.condition')" class="m-0 text-xs text-danger">{{ err(j, 'config.condition') }}</p>
          </div>
          <div class="cfg-row">
            <ConfigField :label="t('auto.timeout')" :for="`act-wt-${ids}-${j}`" width="xs" :hint="t('auto.timeout_hint')" :error="err(j, 'config.timeoutMinutes')">
              <InputNumber v-model="s.config.timeoutMinutes" :input-id="`act-wt-${ids}-${j}`" :min="1" :max="525600" size="small" fluid />
            </ConfigField>
            <ConfigField :label="t('auto.on_timeout_label')" :for="`act-wo-${ids}-${j}`" width="sm">
              <Select v-model="s.config.onTimeout" :input-id="`act-wo-${ids}-${j}`" :options="timeoutChoices" option-label="label" option-value="value" size="small" />
            </ConfigField>
          </div>
        </template>

        <template v-if="s.type !== 'wait_delay' && s.type !== 'wait_condition'">
          <div class="flex flex-col gap-1">
            <h4 class="m-0 text-sm font-semibold">{{ t('act.run_when') }}</h4>
            <ExpressionInput v-model="s.condition" :scope="scope" expected="boolean" allow-empty :label="t('act.run_when')" />
          </div>
          <SettingSwitch
            v-if="mode === 'action'"
            :id="`act-cont-${ids}-${j}`"
            :model-value="s.onFailure === 'continue'"
            :label="t('act.continue')"
            :description="t('act.continue_desc')"
            @update:model-value="(v: boolean) => (s.onFailure = v ? 'continue' : 'stop')"
          />
          <ConfigField v-else :label="t('auto.on_failure_label')" :for="`act-of-${ids}-${j}`" width="sm" :hint="t(`auto.on_failure_hint.${s.onFailure}`)">
            <Select v-model="s.onFailure" :input-id="`act-of-${ids}-${j}`" :options="failureChoices" option-label="label" option-value="value" size="small" />
          </ConfigField>
        </template>
      </div>
    </ConfigItem>
    <div class="flex flex-wrap items-center gap-2">
      <Select v-model="newStep" :options="stepTypes" option-label="label" option-value="value" size="small" class="w-field-sm" :aria-label="t('act.step_type')" :data-testid="`act-step-type-${ids}`" />
      <Button icon="pi pi-plus" :label="t('act.add_step')" size="small" outlined :data-testid="`act-add-step-${ids}`" @click="addStep" />
    </div>
  </div>
</template>
