<script setup lang="ts">
import Button from 'primevue/button'
import Checkbox from 'primevue/checkbox'
import InputNumber from 'primevue/inputnumber'
import InputText from 'primevue/inputtext'
import Password from 'primevue/password'
import Select from 'primevue/select'
import Textarea from 'primevue/textarea'
import { computed, onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { get } from '@/api/http'
import ExpressionInput from '@/builder/conditions/ExpressionInput.vue'
import { buildScope } from '@/builder/conditions/scope'
import FormPicker from '@/builder/FormPicker.vue'
import I18nInput from '@/builder/I18nInput.vue'
import { useBuilder } from '@/builder/useBuilder'
import ConfigField from '@/components/config/ConfigField.vue'
import ConfigItem from '@/components/config/ConfigItem.vue'
import ConfigSaveBar from '@/components/config/ConfigSaveBar.vue'
import ConfigSection from '@/components/config/ConfigSection.vue'
import EmptyState from '@/components/config/EmptyState.vue'
import SettingSwitch from '@/components/config/SettingSwitch.vue'
import TabIntro from '@/components/config/TabIntro.vue'
import SubjectPicker from '@/components/SubjectPicker.vue'
import { useSession } from '@/stores/session'
import { downloadsApi, type ProfileSummary } from '@/views/downloads/api'
import { documentsApi, type DocumentSummary } from '../documents/api'
import { templatesApi, type Placeholder, type TemplateSummary } from '../notifications/api'
import {
  actionsApi,
  labelOf,
  newUuid,
  workflowApi,
  type ActionBuiltin,
  type ActionDoc,
  type ActionPlacement,
  type ActionStepDoc,
  type FieldValueDoc,
  type RecipientType,
  type StepConfig,
  type StepType,
  type TransitionDoc,
} from './api'
import ErrorList from './ErrorList.vue'
import RecipientList from './RecipientList.vue'
import { useHashedDocument } from './useHashedDocument'

/**
 * Actions of a form (specification §4.15): built-in operations placed where
 * people need them, and custom actions made of steps that run one after the
 * other on each chosen record. Who may run an action is set in Roles &
 * permissions; here it gets its place, availability condition, confirmation,
 * justification and run mode.
 */
const props = defineProps<{ form: string }>()
const { t, locale } = useI18n()
const session = useSession()
const builder = useBuilder()
const doc = useHashedDocument<ActionDoc[]>(
  () => actionsApi.load(props.form),
  (v, h) => actionsApi.save(props.form, v, h),
)
const dl = computed(() => session.boot?.default_locale ?? 'en')
const err = (path: string) => doc.errors.value[path]
const scope = computed(() => (builder.doc ? buildScope(builder.doc, builder.catalog.fields) : { fields: [], repeaters: [], rows: null }))
const fieldLabel = (f: { key: string; i18n?: { label?: Record<string, string> } }) => labelOf(f.i18n?.label ?? {}, locale.value, f.key, dl.value)
const groupOf = (uuid: string | null | undefined) => (builder.doc?.groups ?? []).find((g) => g.uuid === uuid)
const mainFields = computed(() =>
  (builder.doc?.fields ?? []).filter((f) => !['heading', 'paragraph', 'divider', 'spacer', 'html', 'formula'].includes(f.type) && groupOf(f.group)?.type !== 'repeater'),
)
const settable = computed(() => mainFields.value.filter((f) => !['file', 'files', 'image', 'signature', 'autonumber'].includes(f.type)).map((f) => ({ value: f.uuid, label: fieldLabel(f) })))
const fileFields = computed(() => mainFields.value.filter((f) => ['file', 'files'].includes(f.type)).map((f) => ({ value: f.uuid, label: fieldLabel(f) })))
const userFields = computed(() => mainFields.value.filter((f) => f.type === 'user').map((f) => ({ value: f.key, label: fieldLabel(f) })))

const transitions = ref<TransitionDoc[]>([])
const templates = ref<TemplateSummary[]>([])
const documents = ref<DocumentSummary[]>([])
const downloadProfiles = ref<ProfileSummary[]>([])
const linkedUsers = ref<Placeholder[]>([])
onMounted(async () => {
  transitions.value = (await workflowApi.load(props.form).catch(() => null))?.document.transitions ?? []
  templates.value = await templatesApi.list(props.form).catch(() => [])
  linkedUsers.value = (await templatesApi.placeholders(props.form).catch(() => ({ fields: [] as Placeholder[] }))).fields.filter((f) => f.user && f.group === 'linked')
  documents.value = (await documentsApi.list(props.form).catch(() => null))?.rows.filter((d) => d.active) ?? []
  downloadProfiles.value = (await downloadsApi.list(props.form).catch(() => null))?.profiles.filter((p) => !p.personal && p.active) ?? []
})
const downloadOptions = computed(() => downloadProfiles.value.map((p) => ({ value: p.uuid, label: p.name })))
const downloadFormats = (uuid: string | null | undefined) => (downloadProfiles.value.find((p) => p.uuid === uuid)?.formats ?? []).map((f) => ({ value: f, label: t(`dl.format.${f}`) }))
const transitionOptions = computed(() => transitions.value.map((x) => ({ value: x.uuid, label: labelOf(x.i18n.name, locale.value, x.key, dl.value) })))
const templateOptions = computed(() => templates.value.map((x) => ({ value: x.uuid, label: x.subject })))
const documentOptions = computed(() => documents.value.map((d) => ({ value: d.uuid, label: d.name })))
const formatsOf = (uuid: string | null | undefined) => (documents.value.find((d) => d.uuid === uuid)?.formats ?? []).map((f) => ({ value: f, label: t(`doc.format_${f}`) }))

const BUILTIN_PLACES: Record<ActionBuiltin, ActionPlacement[]> = {
  duplicate: ['row', 'view_page'],
  print: ['row', 'view_page'],
  bulk_update: ['bulk', 'toolbar'],
  bulk_status: ['bulk', 'toolbar'],
  bulk_delete: ['bulk', 'toolbar'],
}
const ALL_PLACES: ActionPlacement[] = ['row', 'bulk', 'toolbar', 'view_page']
const placesOf = (a: ActionDoc) => (a.kind === 'builtin' && a.builtin ? BUILTIN_PLACES[a.builtin] : ALL_PLACES)
const builtins = computed(() => (Object.keys(BUILTIN_PLACES) as ActionBuiltin[]).map((v) => ({ value: v, label: t(`act.builtin.${v}`) })))
const newBuiltin = ref<ActionBuiltin>('bulk_update')
const stepTypes = computed(() =>
  (['update_fields', 'change_status', 'send_email', 'send_notification', 'call_webhook', 'generate_document', 'create_linked_record', 'assign', 'run_download'] as StepType[]).map((v) => ({
    value: v,
    label: t(`act.step.${v}`),
  })),
)
const newStep = ref<StepType>('update_fields')
const levels = computed(() => (['not_required', 'optional', 'mandatory'] as const).map((v) => ({ value: v, label: t(`act.level.${v}`) })))
const runModes = computed(() => (['auto', 'sync', 'queued'] as const).map((v) => ({ value: v, label: t(`act.mode.${v}`) })))
const icons = [
  'pi-bolt',
  'pi-check',
  'pi-send',
  'pi-copy',
  'pi-print',
  'pi-file',
  'pi-envelope',
  'pi-bell',
  'pi-flag',
  'pi-star',
  'pi-arrow-right',
  'pi-refresh',
  'pi-user-plus',
  'pi-link',
  'pi-trash',
]
const iconOptions = computed(() => [{ value: null, label: t('act.no_icon') }, ...icons.map((v) => ({ value: v as string | null, label: t(`act.icon_name.${v.slice(3).replace('-', '_')}`) }))])
const colors = computed(() => [
  { value: null, label: t('act.color.default') },
  ...(['primary', 'secondary', 'success', 'warn', 'danger', 'info'] as const).map((v) => ({ value: v as string | null, label: t(`act.color.${v}`) })),
])
const methods = ['POST', 'PUT', 'PATCH', 'GET', 'DELETE'].map((v) => ({ value: v, label: v }))
const bodyFormats = computed(() => (['json', 'form', 'text'] as const).map((v) => ({ value: v, label: t(`act.body_kind.${v}`) })))
const assigneeTypes = computed(() => (['user', 'role', 'department'] as const).map((v) => ({ value: v, label: t(`notif.recipient.${v}`) })))
const recipientTypes: RecipientType[] = ['actor', 'creator', 'user', 'role', 'department', 'field_user', 'linked_record_users', 'static']
const slots = ['to', 'cc', 'bcc'] as const

function baseAction(kind: 'builtin' | 'custom', builtin: ActionBuiltin | null): ActionDoc {
  const n = (doc.value.value?.length ?? 0) + 1
  return {
    uuid: newUuid(),
    key: kind === 'builtin' && builtin ? `${builtin}_${n}` : `action_${n}`,
    kind,
    builtin,
    i18n: { label: kind === 'builtin' && builtin ? { [dl.value]: t(`act.builtin.${builtin}`) } : {}, confirmation: {}, success: {} },
    placements: kind === 'builtin' && builtin ? [BUILTIN_PLACES[builtin][0]!] : ['row'],
    condition: null,
    confirm: builtin === 'bulk_delete',
    runMode: 'auto',
    queueThreshold: null,
    maxRecords: null,
    justification: 'not_required',
    icon: null,
    color: builtin === 'bulk_delete' ? 'danger' : null,
    active: true,
    steps: [],
  }
}
function removeSecret(c: StepConfig): void {
  c.secretSet = false
  c.secret = null
}
const addCustom = () => doc.value.value?.push(baseAction('custom', null))
const addBuiltin = () => doc.value.value?.push(baseAction('builtin', newBuiltin.value))
function togglePlace(a: ActionDoc, p: ActionPlacement, on: boolean): void {
  a.placements = on ? [...new Set([...a.placements, p])] : a.placements.filter((x) => x !== p)
}
function addStep(a: ActionDoc): void {
  const type = newStep.value
  const config: ActionStepDoc['config'] = {
    update_fields: { set: [{ field: settable.value[0]?.value ?? '', value: null }] },
    change_status: { transition: transitionOptions.value[0]?.value ?? '' },
    send_email: { template: templateOptions.value[0]?.value ?? null, recipients: { to: [{ type: 'actor', ref: null }], cc: [], bcc: [] }, documents: [] },
    send_notification: { template: null, recipients: { to: [{ type: 'creator', ref: null }], cc: [], bcc: [] } },
    call_webhook: { url: 'https://', method: 'POST', format: 'json', headers: [], body: '', secret: null, secretSet: false },
    generate_document: { template: documentOptions.value[0]?.value ?? null, format: documents.value[0]?.formats[0] ?? 'pdf', attachToField: null },
    create_linked_record: { form: null, linkField: null, prefill: [], open: true },
    assign: { to: { type: 'role', uuid: null } },
    run_download: { profile: downloadProfiles.value[0]?.uuid ?? null, format: downloadProfiles.value[0]?.formats[0] ?? 'xlsx' },
  }[type] as ActionStepDoc['config']
  a.steps.push({ uuid: newUuid(), type, config, condition: null, onFailure: 'stop' })
}
function move<T>(list: T[], i: number, d: -1 | 1): void {
  const j = i + d
  if (j < 0 || j >= list.length) return
  ;[list[i], list[j]] = [list[j]!, list[i]!]
}
const addValue = (list: FieldValueDoc[]) => list.push({ field: '', value: null })

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
onMounted(async () => {
  await Promise.all((doc.value.value ?? []).flatMap((a) => a.steps.filter((s) => s.type === 'create_linked_record').map((s) => loadTarget(s.config.form))))
})
function setTargetForm(cfg: ActionStepDoc['config'], form: string | null): void {
  cfg.form = form
  cfg.linkField = null
  cfg.prefill = []
  void loadTarget(form).then(() => {
    cfg.linkField = linkFields(form)[0]?.value ?? null
  })
}

const actionTitle = (a: ActionDoc) => labelOf(a.i18n.label, locale.value, a.key, dl.value) || t('act.untitled')
const actionSubtitle = (a: ActionDoc) =>
  `${a.kind === 'builtin' && a.builtin ? t(`act.builtin.${a.builtin}`) : t('act.custom', { n: a.steps.length })} · ${a.placements.map((p) => t(`act.place.${p}`)).join(', ')}${a.active ? '' : ' · ' + t('act.inactive')}`
const stepTitle = (s: ActionStepDoc, i: number) => `${i + 1}. ${t(`act.step.${s.type}`)}`
const tokenText = (token: string) => `{{ ${token} }}`
</script>

<template>
  <div class="flex flex-col gap-6" data-testid="actions-editor">
    <TabIntro :title="t('formconfig.tab.actions')" :text="t('act.hint')" />

    <EmptyState v-if="doc.value.value && !doc.value.value.length" icon="pi pi-bolt" :title="t('act.empty_title')" :description="t('act.empty_text')" testid="act-empty" />

    <template v-if="doc.value.value">
      <ConfigItem
        v-for="(a, i) in doc.value.value"
        :key="a.uuid"
        :title="actionTitle(a)"
        :subtitle="actionSubtitle(a)"
        :index="i"
        :count="doc.value.value.length"
        movable
        :invalid="Object.keys(doc.errors.value).some((k) => k.startsWith(`actions.${i}.`))"
        :testid="`act-item-${i}`"
        @move="(d: -1 | 1) => move(doc.value.value!, i, d)"
        @remove="doc.value.value!.splice(i, 1)"
      >
        <ConfigSection :id="`act-basics-${i}`" :title="t('act.section.basics')">
          <I18nInput v-model="a.i18n.label" class="w-field-md" :label="t('act.label')" :maxlength="120" :invalid="!!err(`actions.${i}.i18n.label.${dl}`)" :data-testid="`act-label-${i}`" />
          <div class="cfg-row">
            <ConfigField :label="t('act.key')" :for="`act-key-${i}`" width="sm" :hint="t('act.key_hint')" :error="err(`actions.${i}.key`)" required>
              <InputText :id="`act-key-${i}`" v-model="a.key" class="ltr-value" dir="ltr" size="small" maxlength="48" />
            </ConfigField>
            <ConfigField :label="t('act.icon')" :for="`act-icon-${i}`" width="xs">
              <Select v-model="a.icon" :input-id="`act-icon-${i}`" :options="iconOptions" option-label="label" option-value="value" size="small">
                <template #option="{ option }">
                  <span class="inline-flex items-center gap-2"><i v-if="option.value" :class="['pi', option.value]" aria-hidden="true" />{{ option.label }}</span>
                </template>
              </Select>
            </ConfigField>
            <ConfigField :label="t('act.color_label')" :for="`act-color-${i}`" width="xs">
              <Select v-model="a.color" :input-id="`act-color-${i}`" :options="colors" option-label="label" option-value="value" size="small" />
            </ConfigField>
          </div>
          <SettingSwitch :id="`act-active-${i}`" v-model="a.active" :label="t('act.active')" :description="t('act.active_desc')" />
        </ConfigSection>

        <ConfigSection :id="`act-where-${i}`" :title="t('act.section.where')" :description="t('act.permission_hint')">
          <ConfigField :label="t('act.placements')" :for="`act-place-${i}-${placesOf(a)[0]}`" width="lg" :error="err(`actions.${i}.placements`)">
            <div class="flex flex-wrap gap-4">
              <label v-for="p in placesOf(a)" :key="p" class="inline-flex items-center gap-2 text-sm">
                <Checkbox :input-id="`act-place-${i}-${p}`" :model-value="a.placements.includes(p)" binary @update:model-value="(v: boolean) => togglePlace(a, p, v)" />
                {{ t(`act.place.${p}`) }}
              </label>
            </div>
          </ConfigField>
          <div v-if="scope" class="flex flex-col gap-1">
            <h4 class="m-0 text-sm font-semibold">{{ t('act.available_when') }}</h4>
            <ExpressionInput v-model="a.condition" :scope="scope" expected="boolean" allow-empty :label="t('act.available_when')" />
            <p class="m-0 text-xs text-muted-color">{{ t('act.available_when_hint') }}</p>
          </div>
        </ConfigSection>

        <ConfigSection :id="`act-run-${i}`" :title="t('act.section.run')" :default-open="false">
          <SettingSwitch :id="`act-confirm-${i}`" v-model="a.confirm" :label="t('act.confirm')" :description="t('act.confirm_desc')" />
          <I18nInput v-if="a.confirm" v-model="a.i18n.confirmation" class="w-field-lg" multiline :rows="2" :label="t('act.confirmation')" :maxlength="500" />
          <I18nInput v-model="a.i18n.success" class="w-field-lg" :label="t('act.success')" :maxlength="255" />
          <div class="cfg-row">
            <ConfigField :label="t('act.justification')" :for="`act-just-${i}`" width="sm" :hint="t('act.justification_hint')">
              <Select v-model="a.justification" :input-id="`act-just-${i}`" :options="levels" option-label="label" option-value="value" size="small" />
            </ConfigField>
            <ConfigField :label="t('act.run_mode')" :for="`act-mode-${i}`" width="sm" :hint="t(`act.mode_hint.${a.runMode}`)">
              <Select v-model="a.runMode" :input-id="`act-mode-${i}`" :options="runModes" option-label="label" option-value="value" size="small" />
            </ConfigField>
          </div>
          <div class="cfg-row">
            <ConfigField v-if="a.runMode === 'auto'" :label="t('act.queue_threshold')" :for="`act-thr-${i}`" width="xs" :hint="t('act.default_setting')" :error="err(`actions.${i}.queueThreshold`)">
              <InputNumber v-model="a.queueThreshold" :input-id="`act-thr-${i}`" :min="1" :max="10000" size="small" fluid />
            </ConfigField>
            <ConfigField :label="t('act.max_records')" :for="`act-max-${i}`" width="xs" :hint="t('act.default_setting')" :error="err(`actions.${i}.maxRecords`)">
              <InputNumber v-model="a.maxRecords" :input-id="`act-max-${i}`" :min="1" :max="100000" size="small" fluid />
            </ConfigField>
          </div>
        </ConfigSection>

        <ConfigSection v-if="a.kind === 'custom'" :id="`act-steps-${i}`" :title="t('act.section.steps')" :count="a.steps.length" :description="t('act.steps_hint')">
          <p v-if="err(`actions.${i}.steps`)" class="m-0 text-xs text-danger">{{ err(`actions.${i}.steps`) }}</p>
          <ConfigItem
            v-for="(s, j) in a.steps"
            :key="s.uuid"
            :title="stepTitle(s, j)"
            :subtitle="s.condition ? t('act.conditional') : undefined"
            :index="j"
            :count="a.steps.length"
            movable
            :invalid="Object.keys(doc.errors.value).some((k) => k.startsWith(`actions.${i}.steps.${j}.`))"
            :testid="`act-step-${i}-${j}`"
            @move="(d: -1 | 1) => move(a.steps, j, d)"
            @remove="a.steps.splice(j, 1)"
          >
            <div class="flex flex-col gap-4">
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
                    :invalid="!!err(`actions.${i}.steps.${j}.config.set.${k}.field`)"
                  />
                  <div class="flex-1 min-w-[16rem]">
                    <ExpressionInput v-model="item.value" :scope="scope" :label="t('act.new_value')" :allow-empty="false" />
                    <p v-if="err(`actions.${i}.steps.${j}.config.set.${k}.value`)" class="m-0 text-xs text-danger">{{ err(`actions.${i}.steps.${j}.config.set.${k}.value`) }}</p>
                  </div>
                  <Button icon="pi pi-times" text rounded size="small" severity="secondary" :aria-label="t('act.remove')" @click="s.config.set!.splice(k, 1)" />
                </div>
                <p class="m-0 text-xs text-muted-color">{{ t('act.value_hint') }}</p>
                <Button icon="pi pi-plus" :label="t('act.add_field')" size="small" text class="self-start" @click="addValue(s.config.set!)" />
              </template>

              <!-- Change status -->
              <ConfigField v-else-if="s.type === 'change_status'" :label="t('act.transition')" :for="`act-tr-${i}-${j}`" width="md" :error="err(`actions.${i}.steps.${j}.config.transition`)">
                <Select v-model="s.config.transition" :input-id="`act-tr-${i}-${j}`" :options="transitionOptions" option-label="label" option-value="value" size="small" />
              </ConfigField>

              <!-- Email and notification -->
              <template v-else-if="s.type === 'send_email' || s.type === 'send_notification'">
                <ConfigField
                  :label="t('act.template')"
                  :for="`act-tpl-${i}-${j}`"
                  width="md"
                  :hint="s.type === 'send_notification' ? t('act.notify_template_hint') : undefined"
                  :error="err(`actions.${i}.steps.${j}.config.template`)"
                >
                  <Select
                    v-model="s.config.template"
                    :input-id="`act-tpl-${i}-${j}`"
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
                    :user-fields="userFields"
                    :linked-users="linkedUsers"
                    :errors="doc.errors.value"
                    :path="`actions.${i}.steps.${j}.config.recipients.${slot}`"
                    :testid="`act-${slot}-${i}-${j}`"
                  />
                </div>
                <ConfigField v-if="s.type === 'send_email' && documents.length" :label="t('notif.documents')" width="lg" :hint="t('notif.documents_hint')">
                  <div class="flex flex-col gap-2">
                    <div v-for="(d, k) in s.config.documents" :key="k" class="flex flex-wrap items-center gap-2">
                      <Select v-model="d.template" :options="documentOptions" option-label="label" option-value="value" size="small" class="w-field-md" :aria-label="t('notif.document')" />
                      <Select v-model="d.format" :options="formatsOf(d.template)" option-label="label" option-value="value" size="small" class="w-field-xs" :aria-label="t('doc.format')" />
                      <Button icon="pi pi-times" text rounded size="small" :aria-label="t('notif.remove_document')" @click="s.config.documents!.splice(k, 1)" />
                      <p v-if="err(`actions.${i}.steps.${j}.config.documents.${k}`)" class="basis-full m-0 text-xs text-danger">
                        {{ err(`actions.${i}.steps.${j}.config.documents.${k}`) }}
                      </p>
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
                  <ConfigField :label="t('act.method')" :for="`act-m-${i}-${j}`" width="xs">
                    <Select v-model="s.config.method" :input-id="`act-m-${i}-${j}`" :options="methods" option-label="label" option-value="value" size="small" />
                  </ConfigField>
                  <ConfigField :label="t('act.url')" :for="`act-url-${i}-${j}`" width="lg" :hint="t('act.url_hint')" :error="err(`actions.${i}.steps.${j}.config.url`)">
                    <InputText :id="`act-url-${i}-${j}`" v-model="s.config.url" dir="ltr" class="ltr-value" size="small" fluid />
                  </ConfigField>
                </div>
                <div v-if="s.config.method !== 'GET'" class="flex flex-col gap-2">
                  <ConfigField :label="t('act.body_format')" :for="`act-bf-${i}-${j}`" width="sm">
                    <Select v-model="s.config.format" :input-id="`act-bf-${i}-${j}`" :options="bodyFormats" option-label="label" option-value="value" size="small" />
                  </ConfigField>
                  <ConfigField
                    :label="t('act.body')"
                    :for="`act-body-${i}-${j}`"
                    width="lg"
                    :hint="t('act.body_hint', { example: tokenText('subject') })"
                    :error="err(`actions.${i}.steps.${j}.config.body`)"
                  >
                    <Textarea :id="`act-body-${i}-${j}`" v-model="s.config.body" rows="5" dir="ltr" class="ltr-value font-mono text-sm" fluid />
                  </ConfigField>
                </div>
                <ConfigField :label="t('act.headers')" width="lg" :hint="t('act.headers_hint')" :error="err(`actions.${i}.steps.${j}.config.headers`)">
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
                      <p v-if="err(`actions.${i}.steps.${j}.config.headers.${k}.name`)" class="basis-full m-0 text-xs text-danger">{{ err(`actions.${i}.steps.${j}.config.headers.${k}.name`) }}</p>
                    </div>
                    <Button icon="pi pi-plus" :label="t('act.add_header')" size="small" text class="self-start" @click="s.config.headers!.push({ name: '', value: null })" />
                  </div>
                </ConfigField>
                <ConfigField :label="t('act.secret')" :for="`act-sec-${i}-${j}`" width="md" :hint="s.config.secretSet ? t('act.secret_set') : t('act.secret_hint')">
                  <Password
                    v-model="s.config.secret as string"
                    :input-id="`act-sec-${i}-${j}`"
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
                  <ConfigField :label="t('act.document')" :for="`act-doc-${i}-${j}`" width="md" :error="err(`actions.${i}.steps.${j}.config.template`)">
                    <Select v-model="s.config.template" :input-id="`act-doc-${i}-${j}`" :options="documentOptions" option-label="label" option-value="value" size="small" />
                  </ConfigField>
                  <ConfigField :label="t('doc.format')" :for="`act-docf-${i}-${j}`" width="xs" :error="err(`actions.${i}.steps.${j}.config.format`)">
                    <Select v-model="s.config.format" :input-id="`act-docf-${i}-${j}`" :options="formatsOf(s.config.template)" option-label="label" option-value="value" size="small" />
                  </ConfigField>
                </div>
                <ConfigField :label="t('act.attach_to')" :for="`act-att-${i}-${j}`" width="md" :hint="t('act.attach_to_hint')" :error="err(`actions.${i}.steps.${j}.config.attachToField`)">
                  <Select
                    v-model="s.config.attachToField"
                    :input-id="`act-att-${i}-${j}`"
                    :options="fileFields"
                    option-label="label"
                    option-value="value"
                    show-clear
                    :placeholder="t('act.download_instead')"
                    size="small"
                  />
                </ConfigField>
              </template>

              <!-- Linked record -->
              <template v-else-if="s.type === 'create_linked_record'">
                <div class="cfg-row">
                  <ConfigField :label="t('act.target_form')" :for="`act-tf-${i}-${j}`" width="md" :error="err(`actions.${i}.steps.${j}.config.form`)">
                    <FormPicker :model-value="s.config.form ?? null" :input-id="`act-tf-${i}-${j}`" @update:model-value="(v: string | null | undefined) => setTargetForm(s.config, v ?? null)" />
                  </ConfigField>
                  <ConfigField :label="t('act.link_field')" :for="`act-lf-${i}-${j}`" width="md" :hint="t('act.link_field_hint')" :error="err(`actions.${i}.steps.${j}.config.linkField`)">
                    <Select v-model="s.config.linkField" :input-id="`act-lf-${i}-${j}`" :options="linkFields(s.config.form)" option-label="label" option-value="value" size="small" />
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
                <SettingSwitch :id="`act-open-${i}-${j}`" v-model="s.config.open as boolean" :label="t('act.open')" :description="t('act.open_desc')" />
              </template>

              <!-- Download -->
              <div v-else-if="s.type === 'run_download'" class="cfg-row">
                <ConfigField :label="t('act.download_profile')" :for="`act-dlp-${i}-${j}`" width="md" :hint="t('act.download_profile_hint')" :error="err(`actions.${i}.steps.${j}.config.profile`)">
                  <Select v-model="s.config.profile" :input-id="`act-dlp-${i}-${j}`" :options="downloadOptions" option-label="label" option-value="value" size="small" />
                </ConfigField>
                <ConfigField :label="t('doc.format')" :for="`act-dlf-${i}-${j}`" width="xs" :error="err(`actions.${i}.steps.${j}.config.format`)">
                  <Select v-model="s.config.format" :input-id="`act-dlf-${i}-${j}`" :options="downloadFormats(s.config.profile)" option-label="label" option-value="value" size="small" />
                </ConfigField>
              </div>

              <!-- Assign -->
              <div v-else-if="s.type === 'assign'" class="cfg-row">
                <ConfigField :label="t('act.assign_to')" :for="`act-as-${i}-${j}`" width="sm" :error="err(`actions.${i}.steps.${j}.config.to`)">
                  <Select
                    v-model="s.config.to!.type"
                    :input-id="`act-as-${i}-${j}`"
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

              <div class="flex flex-col gap-1">
                <h4 class="m-0 text-sm font-semibold">{{ t('act.run_when') }}</h4>
                <ExpressionInput v-model="s.condition" :scope="scope" expected="boolean" allow-empty :label="t('act.run_when')" />
              </div>
              <SettingSwitch
                :id="`act-cont-${i}-${j}`"
                :model-value="s.onFailure === 'continue'"
                :label="t('act.continue')"
                :description="t('act.continue_desc')"
                @update:model-value="(v: boolean) => (s.onFailure = v ? 'continue' : 'stop')"
              />
            </div>
          </ConfigItem>
          <div class="flex flex-wrap items-center gap-2">
            <Select v-model="newStep" :options="stepTypes" option-label="label" option-value="value" size="small" class="w-field-sm" :aria-label="t('act.step_type')" />
            <Button icon="pi pi-plus" :label="t('act.add_step')" size="small" outlined :data-testid="`act-add-step-${i}`" @click="addStep(a)" />
          </div>
        </ConfigSection>
      </ConfigItem>

      <div class="flex flex-wrap items-center gap-2">
        <Button icon="pi pi-plus" :label="t('act.add_custom')" size="small" outlined data-testid="act-add-custom" @click="addCustom" />
        <span class="text-sm text-muted-color">{{ t('act.or') }}</span>
        <Select v-model="newBuiltin" :options="builtins" option-label="label" option-value="value" size="small" class="w-field-sm" :aria-label="t('act.builtin_type')" />
        <Button icon="pi pi-plus" :label="t('act.add_builtin')" size="small" outlined data-testid="act-add-builtin" @click="addBuiltin" />
      </div>
    </template>

    <ErrorList :errors="doc.errors.value" />
    <ConfigSaveBar :dirty="doc.dirty.value" :saving="doc.saving.value" testid="act" @save="doc.save" @discard="doc.discard" />
  </div>
</template>
