<script setup lang="ts">
import Button from 'primevue/button'
import InputNumber from 'primevue/inputnumber'
import Message from 'primevue/message'
import Select from 'primevue/select'
import ToggleSwitch from 'primevue/toggleswitch'
import { computed, onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { get } from '@/api/http'
import ExpressionInput from '@/builder/conditions/ExpressionInput.vue'
import { buildScope } from '@/builder/conditions/scope'
import { useBuilder } from '@/builder/useBuilder'
import SubjectPicker from '@/components/SubjectPicker.vue'
import { useSession } from '@/stores/session'
import LocaleFields from '../building/LocaleFields.vue'
import { fetchFormOptions, type FormOption } from '../building/shared'
import { justificationRulesApi, labelOf, newUuid, workflowApi, type JustificationRuleDoc, type StatusDoc, type TransitionDoc } from './api'
import ErrorList from './ErrorList.vue'
import SaveBar from './SaveBar.vue'
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
  <div class="flex flex-col gap-3" data-testid="justification-editor">
    <SaveBar :dirty="doc.dirty.value" :saving="doc.saving.value" :add-label="t('justification.add')" testid="jr" @add="add" @save="doc.save()" />
    <Message severity="secondary" :closable="false" class="text-sm">{{ t('justification.hint') }}</Message>
    <section v-for="(r, i) in doc.value.value ?? []" :key="r.uuid" class="rounded-lg border border-line p-3 flex flex-col gap-3">
      <div class="grid gap-2 md:grid-cols-3">
        <div class="field">
          <label :for="`jr-scope-${i}`">{{ t('justification.where') }}</label>
          <Select :model-value="r.scope" :input-id="`jr-scope-${i}`" :options="scopes" option-label="label" option-value="value" size="small" @update:model-value="(v) => setScope(r, v)" />
        </div>
        <div v-if="['group', 'field', 'status', 'transition'].includes(r.scope)" class="field">
          <label :for="`jr-target-${i}`">{{ t(`justification.target.${r.scope}`) }}</label>
          <Select v-model="r.target" :input-id="`jr-target-${i}`" :options="targets(r.scope)" option-label="label" option-value="value" filter size="small" />
        </div>
        <div class="field">
          <label :for="`jr-level-${i}`">{{ t('justification.level_label') }}</label>
          <Select v-model="r.level" :input-id="`jr-level-${i}`" :options="levels" option-label="label" option-value="value" size="small" />
        </div>
      </div>
      <div class="field">
        <span class="text-sm font-medium">{{ t('justification.who') }}</span>
        <SubjectPicker v-model="r.subject as never" />
      </div>
      <ExpressionInput :model-value="r.condition" :scope="scope" expected="boolean" :label="t('justification.condition')" @update:model-value="(v) => setCondition(r, v)" />
      <div v-if="r.condition" class="field md:w-1/3">
        <label :for="`jr-when-${i}`">{{ t('justification.level_when') }}</label>
        <Select v-model="r.levelWhen" :input-id="`jr-when-${i}`" :options="levels" option-label="label" option-value="value" size="small" />
      </div>
      <div class="grid gap-2 md:grid-cols-4">
        <div class="field">
          <label :for="`jr-min-${i}`">{{ t('justification.min_length') }}</label>
          <InputNumber v-model="r.text.min" :input-id="`jr-min-${i}`" :min="0" :max="5000" size="small" />
        </div>
        <div class="field">
          <label :for="`jr-max-${i}`">{{ t('justification.max_length') }}</label>
          <InputNumber v-model="r.text.max" :input-id="`jr-max-${i}`" :min="1" :max="5000" size="small" />
        </div>
        <div class="field">
          <label :for="`jr-codes-${i}`">{{ t('justification.reason_codes') }}</label>
          <Select v-model="r.reasonCodes.mode" :input-id="`jr-codes-${i}`" :options="modes" option-label="label" option-value="value" size="small" />
        </div>
        <div class="field">
          <label :for="`jr-att-${i}`">{{ t('justification.attachments') }}</label>
          <Select v-model="r.attachments.mode" :input-id="`jr-att-${i}`" :options="modes" option-label="label" option-value="value" size="small" />
        </div>
      </div>
      <div v-if="r.reasonCodes.mode !== 'none'" class="grid gap-2 md:grid-cols-2">
        <div class="field">
          <label :for="`jr-src-${i}`">{{ t('justification.code_source') }}</label>
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
        </div>
        <div v-if="r.reasonCodes.source === 'codes'" class="field">
          <label :for="`jr-set-${i}`">{{ t('justification.code_set') }}</label>
          <Select v-model="r.reasonCodes.set" :input-id="`jr-set-${i}`" :options="codeSets" editable size="small" />
        </div>
        <div v-else class="field">
          <label :for="`jr-col-${i}`">{{ t('justification.collection') }}</label>
          <Select v-model="r.reasonCodes.collection" :input-id="`jr-col-${i}`" :options="collections" option-label="name" option-value="uuid" filter size="small" />
        </div>
      </div>
      <div v-if="r.attachments.mode !== 'none'" class="field md:w-1/4">
        <label :for="`jr-maxatt-${i}`">{{ t('justification.max_attachments') }}</label>
        <InputNumber v-model="r.attachments.max" :input-id="`jr-maxatt-${i}`" :min="1" :max="20" size="small" />
      </div>
      <div class="grid gap-2 md:grid-cols-2">
        <div class="flex flex-col gap-1"><LocaleFields v-model="r.i18n.title" :label="t('justification.prompt_title')" field="title" :id-prefix="`jr-title-${i}`" /></div>
        <div class="flex flex-col gap-1"><LocaleFields v-model="r.i18n.help" :label="t('justification.prompt_help')" field="help" multiline :id-prefix="`jr-help-${i}`" /></div>
      </div>
      <div class="flex flex-wrap gap-4">
        <label class="flex items-center gap-2 text-sm"><ToggleSwitch v-model="r.showSummary" />{{ t('justification.show_summary') }}</label>
        <label class="flex items-center gap-2 text-sm"><ToggleSwitch v-model="r.active" />{{ t('justification.active') }}</label>
        <span class="flex-1" />
        <Button icon="pi pi-trash" text severity="danger" size="small" :label="t('workflow.remove')" @click="doc.value.value!.splice(i, 1)" />
      </div>
    </section>
    <ErrorList :errors="doc.errors.value" />
  </div>
</template>
