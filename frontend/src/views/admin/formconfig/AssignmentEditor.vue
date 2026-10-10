<script setup lang="ts">
import Button from 'primevue/button'
import InputNumber from 'primevue/inputnumber'
import Select from 'primevue/select'
import { computed, onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
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
import { assignmentRulesApi, labelOf, newUuid, workflowApi, type AssignmentRuleDoc, type TransitionDoc } from './api'
import ErrorList from './ErrorList.vue'
import { useHashedDocument } from './useHashedDocument'

/**
 * Assignment rules (specification §4.25): when a record is created or a
 * transition is performed, the first rule (in order) whose condition holds
 * assigns it — to a user, role or department, to the user in a field, to the
 * creator's manager, round-robin or least-loaded within a role — with a due
 * time and priority.
 */
const props = defineProps<{ form: string }>()
const { t, locale } = useI18n()
const session = useSession()
const builder = useBuilder()
const doc = useHashedDocument<AssignmentRuleDoc[]>(
  () => assignmentRulesApi.load(props.form),
  (v, h) => assignmentRulesApi.save(props.form, v, h),
)
const transitions = ref<TransitionDoc[]>([])
onMounted(async () => {
  transitions.value = (await workflowApi.load(props.form).catch(() => null))?.document.transitions ?? []
})
const dl = computed(() => session.boot?.default_locale ?? 'en')
const scope = computed(() => (builder.doc ? buildScope(builder.doc, builder.catalog.fields) : { fields: [], repeaters: [], rows: null }))
const whenOptions = computed(() => [
  { value: null, label: t('assignment.on_create') },
  ...transitions.value.map((x) => ({ value: x.uuid, label: labelOf(x.i18n.name, locale.value, x.key, dl.value) })),
])
const strategies = computed(() =>
  (['user', 'role', 'department', 'field_user', 'creator_manager', 'round_robin', 'least_loaded'] as const).map((v) => ({ value: v, label: t(`assignment.strategy.${v}`) })),
)
const userFields = computed(() => (builder.doc?.fields ?? []).filter((f) => f.type === 'user').map((f) => ({ value: f.uuid, label: labelOf(f.i18n?.label ?? {}, locale.value, f.key, dl.value) })))
const targetType = (s: string): ('user' | 'role' | 'department')[] => (s === 'user' ? ['user'] : s === 'department' ? ['department'] : ['role'])
const needsTarget = (s: string) => ['user', 'role', 'department', 'round_robin', 'least_loaded'].includes(s)

const whenLabel = (r: AssignmentRuleDoc) => whenOptions.value.find((o) => o.value === r.transition)?.label ?? t('assignment.on_create')
function add(): void {
  doc.value.value?.push({ uuid: newUuid(), transition: null, strategy: 'role', target: null, field: null, condition: null, dueInMinutes: null, workingTime: false, priority: 0 })
}
function move(i: number, d: -1 | 1): void {
  const list = doc.value.value!
  const j = i + d
  if (j < 0 || j >= list.length) return
  ;[list[i], list[j]] = [list[j]!, list[i]!]
}
</script>

<template>
  <div class="flex flex-col gap-6" data-testid="assignment-editor">
    <TabIntro :title="t('formconfig.tab.assignment')" :text="t('assignment.hint')" />

    <EmptyState v-if="doc.value.value && !doc.value.value.length" icon="pi pi-user-plus" :title="t('assignment.empty_title')" :description="t('assignment.empty_text')" testid="ar-empty">
      <Button icon="pi pi-plus" :label="t('assignment.add')" size="small" data-testid="ar-add" @click="add" />
    </EmptyState>

    <template v-else-if="doc.value.value">
      <p class="m-0 text-sm text-muted-color">{{ t('assignment.order_hint') }}</p>
      <ConfigItem
        v-for="(r, i) in doc.value.value"
        :key="r.uuid"
        :title="`${i + 1}. ${whenLabel(r)} → ${t(`assignment.strategy.${r.strategy}`)}`"
        :subtitle="r.condition ? t('panels.conditional') : undefined"
        :index="i"
        :count="doc.value.value.length"
        movable
        :invalid="Object.keys(doc.errors.value).some((k) => k.startsWith(`rules.${i}.`))"
        :testid="`ar-rule-${i}`"
        @move="(d) => move(i, d)"
        @remove="doc.value.value!.splice(i, 1)"
      >
        <ConfigSection id="ar-who" :title="t('assignment.section.who')">
          <div class="cfg-row">
            <ConfigField :label="t('assignment.when')" :for="`ar-when-${i}`" width="md">
              <Select v-model="r.transition" :input-id="`ar-when-${i}`" :options="whenOptions" option-label="label" option-value="value" size="small" />
            </ConfigField>
            <ConfigField :label="t('assignment.strategy_label')" :for="`ar-str-${i}`" width="md">
              <Select v-model="r.strategy" :input-id="`ar-str-${i}`" :options="strategies" option-label="label" option-value="value" size="small" @update:model-value="r.target = null" />
            </ConfigField>
          </div>
          <ConfigField v-if="needsTarget(r.strategy)" :label="t('assignment.target')" width="md">
            <SubjectPicker v-model="r.target as never" :types="targetType(r.strategy)" />
          </ConfigField>
          <ConfigField v-if="r.strategy === 'field_user'" :label="t('assignment.user_field')" :for="`ar-field-${i}`" width="md">
            <Select v-model="r.field" :input-id="`ar-field-${i}`" :options="userFields" option-label="label" option-value="value" size="small" />
          </ConfigField>
        </ConfigSection>
        <ConfigSection id="ar-due" :title="t('assignment.section.due')">
          <div class="cfg-row">
            <ConfigField :label="t('assignment.due')" :for="`ar-due-${i}`" width="sm" :hint="t('assignment.due_hint')">
              <InputNumber v-model="r.dueInMinutes" :input-id="`ar-due-${i}`" :min="1" :max="525600" size="small" :suffix="` ${t('workflow.minutes')}`" />
            </ConfigField>
            <ConfigField :label="t('assignment.priority')" :for="`ar-pr-${i}`" width="xs" :hint="t('assignment.priority_hint')">
              <InputNumber v-model="r.priority" :input-id="`ar-pr-${i}`" :min="-100" :max="100" size="small" />
            </ConfigField>
          </div>
          <SettingSwitch :id="`ar-wt-${i}`" v-model="r.workingTime" :label="t('sla.working_time')" :description="t('sla.working_time_desc')" />
        </ConfigSection>
        <ConfigSection id="ar-condition" :title="t('assignment.condition')" :description="t('assignment.condition_desc')" :default-open="false">
          <ExpressionInput v-model="r.condition" :scope="scope" expected="boolean" :label="t('assignment.condition')" />
        </ConfigSection>
      </ConfigItem>
      <Button icon="pi pi-plus" :label="t('assignment.add')" size="small" outlined class="self-start" data-testid="ar-add" @click="add" />
    </template>

    <ErrorList :errors="doc.errors.value" />
    <ConfigSaveBar :dirty="doc.dirty.value" :saving="doc.saving.value" testid="ar" @save="doc.save()" @discard="doc.discard()" />
  </div>
</template>
