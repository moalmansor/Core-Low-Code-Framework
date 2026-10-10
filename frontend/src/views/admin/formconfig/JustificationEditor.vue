<script setup lang="ts">
import Button from 'primevue/button'
import InputNumber from 'primevue/inputnumber'
import Select from 'primevue/select'
import Tag from 'primevue/tag'
import { computed, onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { get } from '@/api/http'
import ExpressionInput from '@/builder/conditions/ExpressionInput.vue'
import { buildScope } from '@/builder/conditions/scope'
import { useBuilder } from '@/builder/useBuilder'
import SubjectPicker from '@/components/SubjectPicker.vue'
import { useSession } from '@/stores/session'
import ConfigField from '@/components/config/ConfigField.vue'
import ConfigItem from '@/components/config/ConfigItem.vue'
import ConfigSaveBar from '@/components/config/ConfigSaveBar.vue'
import ConfigSection from '@/components/config/ConfigSection.vue'
import EmptyState from '@/components/config/EmptyState.vue'
import SettingSwitch from '@/components/config/SettingSwitch.vue'
import TabIntro from '@/components/config/TabIntro.vue'
import LocaleFields from '../building/LocaleFields.vue'
import { fetchFormOptions, type FormOption } from '../building/shared'
import { justificationRulesApi, labelOf, newUuid, workflowApi, type JustificationRuleDoc, type StatusDoc, type TransitionDoc } from './api'
import ErrorList from './ErrorList.vue'
import { useHashedDocument } from './useHashedDocument'

/**
 * Edit justification rules (specification §4.24): where a reason is asked for
 * (the form, a group, a field, once a status is reached, a transition,
 * delete, restore, reassignment), for whom, at which level (with an optional
 * condition that switches it), and what the prompt collects. Off by default:
 * with no rules no one is asked.
 */
const props = defineProps<{ form: string }>()
const { t, locale } = useI18n()
const session = useSession()
const builder = useBuilder()
const doc = useHashedDocument<JustificationRuleDoc[]>(
  () => justificationRulesApi.load(props.form),
  (v, h) => justificationRulesApi.save(props.form, v, h),
)
const statuses = ref<StatusDoc[]>([])
const transitions = ref<TransitionDoc[]>([])
const codeSets = ref<string[]>([])
const collections = ref<FormOption[]>([])
onMounted(async () => {
  const [wf, codes, forms] = await Promise.all([
    workflowApi.load(props.form).catch(() => null),
    get<{ data: { set_key: string }[] }>('/justification-reason-codes').catch(() => ({ data: [] })),
    fetchFormOptions().catch(() => []),
  ])
  statuses.value = wf?.document.statuses ?? []
  transitions.value = wf?.document.transitions ?? []
  codeSets.value = [...new Set(codes.data.map((c) => c.set_key))]
  collections.value = forms.filter((f) => f.kind === 'collection')
})

const dl = computed(() => session.boot?.default_locale ?? 'en')
const scope = computed(() => (builder.doc ? buildScope(builder.doc, builder.catalog.fields) : { fields: [], repeaters: [], rows: null }))
const scopes = computed(() => (['form', 'group', 'field', 'status', 'transition', 'delete', 'restore', 'reassign'] as const).map((v) => ({ value: v, label: t(`justification.scope.${v}`) })))
const levels = computed(() => (['not_required', 'optional', 'mandatory'] as const).map((v) => ({ value: v, label: t(`justification.level.${v}`) })))
const modes = computed(() => (['none', 'optional', 'required'] as const).map((v) => ({ value: v, label: t(`justification.mode.${v}`) })))
function targets(scopeName: string): { value: string; label: string }[] {
  switch (scopeName) {
    case 'group':
      return (builder.doc?.groups ?? []).map((g) => ({ value: g.uuid, label: labelOf(g.i18n?.title ?? {}, locale.value, g.key, dl.value) }))
    case 'field':
      return (builder.doc?.fields ?? []).map((f) => ({ value: f.uuid, label: labelOf(f.i18n?.label ?? {}, locale.value, f.key, dl.value) }))
    case 'status':
      return statuses.value.map((s) => ({ value: s.uuid, label: labelOf(s.i18n.name, locale.value, s.key, dl.value) }))
    case 'transition':
      return transitions.value.map((x) => ({ value: x.uuid, label: labelOf(x.i18n.name, locale.value, x.key, dl.value) }))
    default:
      return []
  }
}

function ruleTitle(r: JustificationRuleDoc): string {
  const target = r.target ? targets(r.scope).find((o) => o.value === r.target)?.label : null
  return `${t(`justification.scope.${r.scope}`)}${target ? `: ${target}` : ''} — ${t(`justification.level.${r.level}`)}`
}
function add(): void {
  doc.value.value?.push({
    uuid: newUuid(),
    scope: 'form',
    target: null,
    subject: { type: 'everyone', uuid: null },
    level: 'mandatory',
    condition: null,
    levelWhen: null,
    text: { min: 10, max: 1000 },
    reasonCodes: { mode: 'none', source: 'codes', set: null, collection: null },
    attachments: { mode: 'none', max: null, rules: null },
    showSummary: true,
    active: true,
    i18n: { title: {}, help: {} },
  })
}
function setScope(r: JustificationRuleDoc, s: JustificationRuleDoc['scope']): void {
  r.scope = s
  r.target = targets(s)[0]?.value ?? null
}
function setCondition(r: JustificationRuleDoc, ast: JustificationRuleDoc['condition'] | undefined): void {
  r.condition = ast ?? null
  r.levelWhen = r.condition ? (r.levelWhen ?? 'mandatory') : null
}
</script>

<template>
  <div class="flex flex-col gap-6" data-testid="justification-editor">
    <TabIntro :title="t('formconfig.tab.justification')" :text="t('justification.hint')" />

    <EmptyState v-if="doc.value.value && !doc.value.value.length" icon="pi pi-comment" :title="t('justification.empty_title')" :description="t('justification.empty_text')" testid="jr-empty">
      <Button icon="pi pi-plus" :label="t('justification.add')" size="small" data-testid="jr-add" @click="add" />
    </EmptyState>

    <template v-else-if="doc.value.value">
      <ConfigItem
        v-for="(r, i) in doc.value.value"
        :key="r.uuid"
        :title="ruleTitle(r)"
        :subtitle="t(`subjects.${r.subject.type}`)"
        :index="i"
        :count="doc.value.value.length"
        :invalid="Object.keys(doc.errors.value).some((k) => k.startsWith(`rules.${i}.`))"
        :testid="`jr-rule-${i}`"
        @remove="doc.value.value!.splice(i, 1)"
      >
        <template #badges>
          <Tag v-if="!r.active" :value="t('reason_codes.inactive')" severity="secondary" />
        </template>

        <ConfigSection id="jr-where" :title="t('justification.section.where')">
          <div class="cfg-row">
            <ConfigField :label="t('justification.where')" :for="`jr-scope-${i}`" width="md">
              <Select :model-value="r.scope" :input-id="`jr-scope-${i}`" :options="scopes" option-label="label" option-value="value" size="small" @update:model-value="(v) => setScope(r, v)" />
            </ConfigField>
            <ConfigField v-if="['group', 'field', 'status', 'transition'].includes(r.scope)" :label="t(`justification.target.${r.scope}`)" :for="`jr-target-${i}`" width="md">
              <Select v-model="r.target" :input-id="`jr-target-${i}`" :options="targets(r.scope)" option-label="label" option-value="value" filter size="small" />
            </ConfigField>
          </div>
          <ConfigField :label="t('justification.who')" width="md">
            <SubjectPicker v-model="r.subject as never" />
          </ConfigField>
          <ConfigField :label="t('justification.level_label')" :for="`jr-level-${i}`" width="sm">
            <Select v-model="r.level" :input-id="`jr-level-${i}`" :options="levels" option-label="label" option-value="value" size="small" />
          </ConfigField>
          <SettingSwitch :id="`jr-active-${i}`" v-model="r.active" :label="t('justification.active')" :description="t('justification.active_desc')" />
        </ConfigSection>

        <ConfigSection id="jr-prompt" :title="t('justification.section.prompt')">
          <div class="cfg-row">
            <ConfigField :label="t('justification.min_length')" :for="`jr-min-${i}`" width="xs">
              <InputNumber v-model="r.text.min" :input-id="`jr-min-${i}`" :min="0" :max="5000" size="small" />
            </ConfigField>
            <ConfigField :label="t('justification.max_length')" :for="`jr-max-${i}`" width="xs">
              <InputNumber v-model="r.text.max" :input-id="`jr-max-${i}`" :min="1" :max="5000" size="small" />
            </ConfigField>
          </div>
          <div class="cfg-row">
            <ConfigField :label="t('justification.reason_codes')" :for="`jr-codes-${i}`" width="sm">
              <Select v-model="r.reasonCodes.mode" :input-id="`jr-codes-${i}`" :options="modes" option-label="label" option-value="value" size="small" />
            </ConfigField>
            <template v-if="r.reasonCodes.mode !== 'none'">
              <ConfigField :label="t('justification.code_source')" :for="`jr-src-${i}`" width="sm">
                <Select
                  v-model="r.reasonCodes.source"
                  :input-id="`jr-src-${i}`"
                  :options="[
                    { value: 'codes', label: t('justification.source_codes') },
                    { value: 'collection', label: t('justification.source_collection') },
                  ]"
                  option-label="label"
                  option-value="value"
                  size="small"
                />
              </ConfigField>
              <!-- A set is chosen from the sets that exist, so a typo cannot point a rule at nothing. -->
              <ConfigField
                v-if="r.reasonCodes.source === 'codes'"
                :label="t('justification.code_set')"
                :for="`jr-set-${i}`"
                width="sm"
                :hint="codeSets.length ? undefined : t('justification.no_code_sets')"
              >
                <Select v-model="r.reasonCodes.set" :input-id="`jr-set-${i}`" :options="codeSets" :disabled="!codeSets.length" size="small" />
              </ConfigField>
              <ConfigField v-else :label="t('justification.collection')" :for="`jr-col-${i}`" width="md">
                <Select v-model="r.reasonCodes.collection" :input-id="`jr-col-${i}`" :options="collections" option-label="name" option-value="uuid" filter size="small" />
              </ConfigField>
            </template>
          </div>
          <div class="cfg-row">
            <ConfigField :label="t('justification.attachments')" :for="`jr-att-${i}`" width="sm">
              <Select v-model="r.attachments.mode" :input-id="`jr-att-${i}`" :options="modes" option-label="label" option-value="value" size="small" />
            </ConfigField>
            <ConfigField v-if="r.attachments.mode !== 'none'" :label="t('justification.max_attachments')" :for="`jr-maxatt-${i}`" width="xs">
              <InputNumber v-model="r.attachments.max" :input-id="`jr-maxatt-${i}`" :min="1" :max="20" size="small" />
            </ConfigField>
          </div>
          <SettingSwitch :id="`jr-summary-${i}`" v-model="r.showSummary" :label="t('justification.show_summary')" :description="t('justification.show_summary_desc')" />
        </ConfigSection>

        <ConfigSection id="jr-when" :title="t('justification.section.when')" :description="t('justification.condition_desc')" :default-open="false">
          <ExpressionInput :model-value="r.condition" :scope="scope" expected="boolean" :label="t('justification.condition')" @update:model-value="(v) => setCondition(r, v)" />
          <ConfigField v-if="r.condition" :label="t('justification.level_when')" :for="`jr-when-${i}`" width="sm">
            <Select v-model="r.levelWhen" :input-id="`jr-when-${i}`" :options="levels" option-label="label" option-value="value" size="small" />
          </ConfigField>
        </ConfigSection>

        <ConfigSection id="jr-wording" :title="t('justification.section.wording')" :description="t('justification.wording_desc')" :default-open="false">
          <div class="w-field-lg cfg-stack"><LocaleFields v-model="r.i18n.title" :label="t('justification.prompt_title')" field="title" :id-prefix="`jr-title-${i}`" /></div>
          <div class="w-field-lg cfg-stack"><LocaleFields v-model="r.i18n.help" :label="t('justification.prompt_help')" field="help" multiline :id-prefix="`jr-help-${i}`" /></div>
        </ConfigSection>
      </ConfigItem>
      <Button icon="pi pi-plus" :label="t('justification.add')" size="small" outlined class="self-start" data-testid="jr-add" @click="add" />
    </template>

    <ErrorList :errors="doc.errors.value" />
    <ConfigSaveBar :dirty="doc.dirty.value" :saving="doc.saving.value" testid="jr" @save="doc.save()" @discard="doc.discard()" />
  </div>
</template>
