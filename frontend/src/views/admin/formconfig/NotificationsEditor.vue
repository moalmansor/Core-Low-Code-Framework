<script setup lang="ts">
import Button from 'primevue/button'
import Checkbox from 'primevue/checkbox'
import InputNumber from 'primevue/inputnumber'
import InputText from 'primevue/inputtext'
import Message from 'primevue/message'
import Select from 'primevue/select'
import { computed, onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import ExpressionInput from '@/builder/conditions/ExpressionInput.vue'
import { buildScope } from '@/builder/conditions/scope'
import I18nInput from '@/builder/I18nInput.vue'
import { useBuilder } from '@/builder/useBuilder'
import ConfigField from '@/components/config/ConfigField.vue'
import ConfigItem from '@/components/config/ConfigItem.vue'
import ConfigSaveBar from '@/components/config/ConfigSaveBar.vue'
import ConfigSection from '@/components/config/ConfigSection.vue'
import EmptyState from '@/components/config/EmptyState.vue'
import SettingSwitch from '@/components/config/SettingSwitch.vue'
import TabIntro from '@/components/config/TabIntro.vue'
import { useSession } from '@/stores/session'
import { joinList } from '@/runtime/lists'
import { templatesApi, type TemplateSummary } from '../notifications/api'
import { fieldTreeApi, labelOf, newUuid, notificationRulesApi, workflowApi, type NotificationRuleDoc, type NotificationTrigger, type PathNode, type RecipientType, type StatusDoc } from './api'
import ErrorList from './ErrorList.vue'
import RecipientList from './RecipientList.vue'
import { documentsApi, type DocumentSummary } from '../documents/api'
import { useHashedDocument } from './useHashedDocument'

/**
 * Notification rules (specification §4.16): when something happens to a
 * record of this form, who is told, on which channels, with which email
 * template. Recipients go in To, CC or BCC; a rule can wait before sending,
 * and a mandatory rule reaches people even if they turned that channel off.
 */
const props = defineProps<{ form: string }>()
const { t, locale } = useI18n()
const session = useSession()
const builder = useBuilder()
const doc = useHashedDocument<NotificationRuleDoc[]>(
  () => notificationRulesApi.load(props.form),
  (v, h) => notificationRulesApi.save(props.form, v, h),
)
const statuses = ref<StatusDoc[]>([])
const templates = ref<TemplateSummary[]>([])
const templatesHidden = ref(false)
const tree = ref<PathNode[]>([])
const documents = ref<DocumentSummary[]>([])
onMounted(async () => {
  // Attaching documents needs to see them ("Manage document templates"); attached ones are kept either way.
  documents.value = (await documentsApi.list(props.form).catch(() => null))?.rows.filter((d) => d.active) ?? []
  statuses.value = (await workflowApi.load(props.form).catch(() => null))?.document.statuses ?? []
  tree.value = await fieldTreeApi.load(props.form).catch(() => [])
  try {
    templates.value = await templatesApi.list(props.form)
  } catch {
    // Managing templates needs its own permission: the rule can still be saved by someone who has it.
    templatesHidden.value = true
  }
})
const dl = computed(() => session.boot?.default_locale ?? 'en')
const scope = computed(() => (builder.doc ? buildScope(builder.doc, builder.catalog.fields) : { fields: [], repeaters: [], rows: null }))
const fieldLabel = (f: { key: string; i18n?: { label?: Record<string, string> } }) => labelOf(f.i18n?.label ?? {}, locale.value, f.key, dl.value)
const fields = computed(() => (builder.doc?.fields ?? []).filter((f) => !['heading', 'paragraph', 'divider', 'spacer', 'html'].includes(f.type)))
const fieldOptions = computed(() => fields.value.map((f) => ({ value: f.uuid, label: fieldLabel(f) })))
const dateFields = computed(() => fields.value.filter((f) => ['date', 'datetime'].includes(f.type)).map((f) => ({ value: f.uuid, label: fieldLabel(f) })))
const fileFields = computed(() => fields.value.filter((f) => ['file', 'files', 'image', 'signature'].includes(f.type)).map((f) => ({ value: f.key, label: fieldLabel(f) })))
const statusOptions = computed(() => [
  { value: null, label: t('notif.any_status') },
  ...statuses.value.map((s) => ({ value: s.uuid as string | null, label: labelOf(s.i18n.name, locale.value, s.key, dl.value) })),
])
const triggers = computed(() =>
  (['record_created', 'record_updated', 'status_changed', 'field_changed', 'condition_met', 'scheduled_reminder', 'sla_warning', 'sla_escalation', 'approval_requested', 'assigned'] as const).map(
    (v) => ({
      value: v,
      label: t(`notif.trigger.${v}`),
    }),
  ),
)
const templateOptions = computed(() => [{ value: null, label: t('notif.no_template') }, ...templates.value.map((x) => ({ value: x.uuid as string | null, label: x.subject }))])
const recipientTypes: RecipientType[] = ['user', 'role', 'department', 'field', 'assignee', 'status_changer', 'creator', 'static']
const slots = ['to', 'cc', 'bcc'] as const
const offsetUnits = computed(() => [
  { value: 'before', label: t('notif.before') },
  { value: 'after', label: t('notif.after') },
])

const err = (path: string) => doc.errors.value[path]
const ruleTitle = (r: NotificationRuleDoc) => labelOf(r.name, locale.value, r.key, dl.value) || t(`notif.trigger.${r.trigger}`)

function add(): void {
  doc.value.value?.push({
    uuid: newUuid(),
    key: `notify_${(doc.value.value?.length ?? 0) + 1}`,
    name: {},
    trigger: 'record_created',
    fromStatus: null,
    toStatus: null,
    field: null,
    condition: null,
    schedule: null,
    template: null,
    channels: ['in_app'],
    recipients: { to: [{ type: 'creator', ref: null }], cc: [], bcc: [] },
    attachments: null,
    delayMinutes: null,
    respectPreferences: true,
    active: true,
  })
}
function setTrigger(r: NotificationRuleDoc, v: NotificationTrigger): void {
  r.trigger = v
  if (v !== 'status_changed') r.fromStatus = r.toStatus = null
  if (v !== 'field_changed') r.field = null
  r.schedule = v === 'scheduled_reminder' ? { dateField: dateFields.value[0]?.value ?? '', offsetMinutes: -1440 } : null
}
function toggleChannel(r: NotificationRuleDoc, c: 'email' | 'in_app', on: boolean): void {
  r.channels = on ? [...new Set([...r.channels, c])] : r.channels.filter((x) => x !== c)
}
/** The reminder's offset as days before or after the date. */
const offsetDays = (r: NotificationRuleDoc) => Math.abs(Math.round((r.schedule?.offsetMinutes ?? 0) / 1440))
const offsetSide = (r: NotificationRuleDoc) => ((r.schedule?.offsetMinutes ?? 0) < 0 ? 'before' : 'after')
function setOffset(r: NotificationRuleDoc, days: number | null, side: string): void {
  if (!r.schedule) return
  r.schedule.offsetMinutes = (side === 'before' ? -1 : 1) * Math.max(0, days ?? 0) * 1440
}
function setAttachments(r: NotificationRuleDoc, keys: string[]): void {
  const docs = r.attachments?.documents ?? []
  r.attachments = keys.length || docs.length ? { fields: keys, documents: docs } : null
}
type AttachedDocument = { template: string; format: 'docx' | 'pdf' }
function setDocuments(r: NotificationRuleDoc, docs: AttachedDocument[]): void {
  const keys = r.attachments?.fields ?? []
  r.attachments = keys.length || docs.length ? { fields: keys, documents: docs } : null
}
/** The first document and format this rule does not attach yet. */
function nextDocument(r: NotificationRuleDoc): AttachedDocument | null {
  const taken = new Set((r.attachments?.documents ?? []).map((d) => `${d.template}:${d.format}`))
  for (const d of documents.value) {
    for (const format of [...d.formats].sort((a, b) => (a === 'pdf' ? -1 : b === 'pdf' ? 1 : 0))) {
      if (!taken.has(`${d.uuid}:${format}`)) return { template: d.uuid, format }
    }
  }
  return null
}
function addDocument(r: NotificationRuleDoc): void {
  const next = nextDocument(r)
  if (next) setDocuments(r, [...(r.attachments?.documents ?? []), next])
}
const documentOptions = computed(() => documents.value.map((d) => ({ value: d.uuid, label: d.name })))
const formatsOf = (uuid: string) => (documents.value.find((d) => d.uuid === uuid)?.formats ?? []).map((f) => ({ value: f, label: t(`doc.format_${f}`) }))
function setDocumentTemplate(item: AttachedDocument, uuid: string): void {
  item.template = uuid
  const formats = documents.value.find((d) => d.uuid === uuid)?.formats ?? []
  if (!formats.includes(item.format)) item.format = formats[0] ?? 'pdf'
}
</script>

<template>
  <div class="flex flex-col gap-6" data-testid="notifications-editor">
    <TabIntro :title="t('formconfig.tab.notifications')" :text="t('notif.hint')" />
    <Message v-if="templatesHidden" severity="warn">{{ t('notif.templates_hidden') }}</Message>

    <EmptyState v-if="doc.value.value && !doc.value.value.length" icon="pi pi-bell" :title="t('notif.empty_title')" :description="t('notif.empty_text')" testid="nr-empty">
      <Button icon="pi pi-plus" :label="t('notif.add')" size="small" data-testid="nr-add" @click="add" />
    </EmptyState>

    <template v-else-if="doc.value.value">
      <ConfigItem
        v-for="(r, i) in doc.value.value"
        :key="r.uuid"
        :title="ruleTitle(r)"
        :subtitle="`${t(`notif.trigger.${r.trigger}`)} · ${joinList(
          r.channels.map((c) => t(`notif.channel.${c}`)),
          locale,
        )}${r.active ? '' : ' · ' + t('notif.inactive')}`"
        :invalid="Object.keys(doc.errors.value).some((k) => k.startsWith(`rules.${i}.`))"
        :testid="`nr-rule-${i}`"
        @remove="doc.value.value!.splice(i, 1)"
      >
        <ConfigSection id="nr-when" :title="t('notif.section.when')">
          <div class="cfg-row">
            <div class="w-field-md flex flex-col gap-1">
              <I18nInput v-model="r.name as never" class="w-field-md" :label="t('notif.name')" :maxlength="255" :invalid="!!err(`rules.${i}.name.${dl}`)" />
              <p v-if="err(`rules.${i}.name.${dl}`)" class="m-0 text-xs text-danger">{{ err(`rules.${i}.name.${dl}`) }}</p>
            </div>
            <ConfigField :label="t('notif.key')" :for="`nr-key-${i}`" width="sm" :error="err(`rules.${i}.key`)">
              <InputText :id="`nr-key-${i}`" v-model="r.key" class="ltr-value" dir="ltr" size="small" maxlength="48" />
            </ConfigField>
          </div>
          <ConfigField :label="t('notif.trigger_label')" :for="`nr-trigger-${i}`" width="md">
            <Select
              :model-value="r.trigger"
              :input-id="`nr-trigger-${i}`"
              :options="triggers"
              option-label="label"
              option-value="value"
              size="small"
              :data-testid="`nr-trigger-${i}`"
              @update:model-value="(v: NotificationTrigger) => setTrigger(r, v)"
            />
          </ConfigField>
          <div v-if="r.trigger === 'status_changed'" class="cfg-row">
            <ConfigField :label="t('notif.from_status')" :for="`nr-from-${i}`" width="sm" :error="err(`rules.${i}.fromStatus`)">
              <Select v-model="r.fromStatus" :input-id="`nr-from-${i}`" :options="statusOptions" option-label="label" option-value="value" size="small" />
            </ConfigField>
            <ConfigField :label="t('notif.to_status')" :for="`nr-to-${i}`" width="sm" :error="err(`rules.${i}.toStatus`)">
              <Select v-model="r.toStatus" :input-id="`nr-to-${i}`" :options="statusOptions" option-label="label" option-value="value" size="small" :data-testid="`nr-to-${i}`" />
            </ConfigField>
          </div>
          <ConfigField v-if="r.trigger === 'field_changed'" :label="t('notif.field')" :for="`nr-field-${i}`" width="md" :error="err(`rules.${i}.field`)">
            <Select v-model="r.field" :input-id="`nr-field-${i}`" :options="fieldOptions" option-label="label" option-value="value" filter size="small" />
          </ConfigField>
          <div v-if="r.trigger === 'scheduled_reminder' && r.schedule" class="cfg-row">
            <ConfigField :label="t('notif.date_field')" :for="`nr-date-${i}`" width="sm" :error="err(`rules.${i}.schedule.dateField`)">
              <Select v-model="r.schedule.dateField" :input-id="`nr-date-${i}`" :options="dateFields" option-label="label" option-value="value" size="small" />
            </ConfigField>
            <ConfigField :label="t('notif.days')" :for="`nr-days-${i}`" width="xs">
              <InputNumber :model-value="offsetDays(r)" :input-id="`nr-days-${i}`" :min="0" :max="365" size="small" @update:model-value="(v: number | null) => setOffset(r, v, offsetSide(r))" />
            </ConfigField>
            <ConfigField :label="t('notif.side')" :for="`nr-side-${i}`" width="xs">
              <Select
                :model-value="offsetSide(r)"
                :input-id="`nr-side-${i}`"
                :options="offsetUnits"
                option-label="label"
                option-value="value"
                size="small"
                @update:model-value="(v: string) => setOffset(r, offsetDays(r), v)"
              />
            </ConfigField>
          </div>
          <div v-if="r.trigger === 'condition_met'" class="flex flex-col gap-1">
            <ExpressionInput v-model="r.condition" :scope="scope" expected="boolean" :label="t('notif.condition_met')" />
            <p class="m-0 text-xs text-muted-color">{{ t('notif.condition_met_hint') }}</p>
          </div>
        </ConfigSection>

        <ConfigSection id="nr-who" :title="t('notif.section.who')" :description="t('notif.who_hint')">
          <div v-for="slot in slots" :key="slot" class="flex flex-col gap-2">
            <h4 class="m-0 text-sm font-semibold">{{ t(`notif.slot.${slot}`) }}</h4>
            <RecipientList v-model="r.recipients[slot]" :types="recipientTypes" :tree="tree" :errors="doc.errors.value" :path="`rules.${i}.recipients.${slot}`" :testid="`nr-${slot}-${i}`" />
          </div>
        </ConfigSection>

        <ConfigSection id="nr-how" :title="t('notif.section.how')">
          <div class="flex flex-wrap gap-4" role="group" :aria-label="t('notif.channels')">
            <label v-for="c in ['in_app', 'email'] as const" :key="c" class="flex items-center gap-2">
              <Checkbox :model-value="r.channels.includes(c)" binary :input-id="`nr-ch-${c}-${i}`" @update:model-value="(v: boolean) => toggleChannel(r, c, v)" />
              <span>{{ t(`notif.channel.${c}`) }}</span>
            </label>
          </div>
          <p v-if="err(`rules.${i}.channels`)" class="m-0 text-xs text-danger">{{ err(`rules.${i}.channels`) }}</p>
          <ConfigField :label="t('notif.template')" :for="`nr-tpl-${i}`" width="md" :hint="t('notif.template_hint')" :error="err(`rules.${i}.template`)">
            <Select v-model="r.template" :input-id="`nr-tpl-${i}`" :options="templateOptions" option-label="label" option-value="value" size="small" :data-testid="`nr-template-${i}`" />
          </ConfigField>
          <ConfigField v-if="fileFields.length && r.channels.includes('email')" :label="t('notif.attachments')" width="md" :hint="t('notif.attachments_hint')">
            <div class="flex flex-col gap-1">
              <label v-for="f in fileFields" :key="f.value" class="flex items-center gap-2 text-sm">
                <Checkbox
                  :model-value="r.attachments?.fields.includes(f.value) ?? false"
                  binary
                  @update:model-value="(v: boolean) => setAttachments(r, v ? [...(r.attachments?.fields ?? []), f.value] : (r.attachments?.fields ?? []).filter((x) => x !== f.value))"
                />{{ f.label }}
              </label>
            </div>
          </ConfigField>
          <ConfigField
            v-if="r.channels.includes('email') && (documents.length || r.attachments?.documents.length)"
            :label="t('notif.documents')"
            width="lg"
            :hint="t('notif.documents_hint')"
            :error="err(`rules.${i}.attachments.documents`)"
          >
            <div class="flex flex-col gap-2">
              <div v-for="(d, j) in r.attachments?.documents ?? []" :key="j" class="flex flex-wrap items-center gap-2" :data-testid="`nr-doc-${i}-${j}`">
                <Select
                  :model-value="d.template"
                  :options="documentOptions"
                  option-label="label"
                  option-value="value"
                  size="small"
                  class="w-field-md"
                  :aria-label="t('notif.document')"
                  :invalid="!!err(`rules.${i}.attachments.documents.${j}`)"
                  @update:model-value="(v: string) => setDocumentTemplate(d, v)"
                />
                <Select v-model="d.format" :options="formatsOf(d.template)" option-label="label" option-value="value" size="small" class="w-field-xs" :aria-label="t('doc.format')" />
                <Button
                  icon="pi pi-times"
                  text
                  rounded
                  size="small"
                  :aria-label="t('notif.remove_document')"
                  @click="
                    setDocuments(
                      r,
                      (r.attachments?.documents ?? []).filter((_, k) => k !== j),
                    )
                  "
                />
                <p v-if="err(`rules.${i}.attachments.documents.${j}`)" class="basis-full m-0 text-xs text-danger">{{ err(`rules.${i}.attachments.documents.${j}`) }}</p>
              </div>
              <Button v-if="nextDocument(r)" icon="pi pi-plus" :label="t('notif.add_document')" size="small" text class="self-start" :data-testid="`nr-add-doc-${i}`" @click="addDocument(r)" />
            </div>
          </ConfigField>
          <ConfigField :label="t('notif.delay')" :for="`nr-delay-${i}`" width="xs" :hint="t('notif.delay_hint')" :error="err(`rules.${i}.delayMinutes`)">
            <InputNumber v-model="r.delayMinutes" :input-id="`nr-delay-${i}`" :min="0" :max="43200" size="small" :suffix="` ${t('workflow.minutes')}`" />
          </ConfigField>
          <SettingSwitch
            :id="`nr-mand-${i}`"
            :model-value="!r.respectPreferences"
            :label="t('notif.mandatory')"
            :description="t('notif.mandatory_desc')"
            @update:model-value="(v: boolean) => (r.respectPreferences = !v)"
          />
          <SettingSwitch :id="`nr-active-${i}`" v-model="r.active" :label="t('notif.active')" :description="t('notif.active_desc')" />
        </ConfigSection>

        <ConfigSection v-if="r.trigger !== 'condition_met'" id="nr-condition" :title="t('notif.only_if')" :description="t('notif.only_if_desc')" :default-open="false">
          <ExpressionInput v-model="r.condition" :scope="scope" expected="boolean" :label="t('notif.only_if')" />
        </ConfigSection>
      </ConfigItem>
      <Button icon="pi pi-plus" :label="t('notif.add')" size="small" outlined class="self-start" data-testid="nr-add" @click="add" />
    </template>

    <ErrorList :errors="doc.errors.value" />
    <ConfigSaveBar :dirty="doc.dirty.value" :saving="doc.saving.value" testid="nr" @save="doc.save()" @discard="doc.discard()" />
  </div>
</template>
