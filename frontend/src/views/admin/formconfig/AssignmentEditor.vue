<script setup lang="ts">
import Button from 'primevue/button'
import InputNumber from 'primevue/inputnumber'
import Message from 'primevue/message'
import Select from 'primevue/select'
import ToggleSwitch from 'primevue/toggleswitch'
import { computed, onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import ExpressionInput from '@/builder/conditions/ExpressionInput.vue'
import { buildScope } from '@/builder/conditions/scope'
import { useBuilder } from '@/builder/useBuilder'
import SubjectPicker from '@/components/SubjectPicker.vue'
import { useSession } from '@/stores/session'
import { assignmentRulesApi, labelOf, newUuid, workflowApi, type AssignmentRuleDoc, type TransitionDoc } from './api'
import ErrorList from './ErrorList.vue'
import SaveBar from './SaveBar.vue'
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
  <div class="flex flex-col gap-3" data-testid="assignment-editor">
    <SaveBar :dirty="doc.dirty.value" :saving="doc.saving.value" :add-label="t('assignment.add')" testid="ar" @add="add" @save="doc.save()" />
    <Message severity="secondary" :closable="false" class="text-sm">{{ t('assignment.hint') }}</Message>
    <section v-for="(r, i) in doc.value.value ?? []" :key="r.uuid" class="rounded-lg border border-line p-3 flex flex-col gap-2">
      <div class="grid gap-2 md:grid-cols-3">
        <div class="field">
          <label :for="`ar-when-${i}`">{{ t('assignment.when') }}</label>
          <Select v-model="r.transition" :input-id="`ar-when-${i}`" :options="whenOptions" option-label="label" option-value="value" size="small" />
        </div>
        <div class="field">
          <label :for="`ar-str-${i}`">{{ t('assignment.strategy_label') }}</label>
          <Select v-model="r.strategy" :input-id="`ar-str-${i}`" :options="strategies" option-label="label" option-value="value" size="small" @update:model-value="r.target = null" />
        </div>
        <div v-if="needsTarget(r.strategy)" class="field min-w-0">
          <span class="text-sm font-medium">{{ t('assignment.target') }}</span>
          <SubjectPicker v-model="r.target as never" :types="targetType(r.strategy)" />
        </div>
        <div v-if="r.strategy === 'field_user'" class="field">
          <label :for="`ar-field-${i}`">{{ t('assignment.user_field') }}</label>
          <Select v-model="r.field" :input-id="`ar-field-${i}`" :options="userFields" option-label="label" option-value="value" size="small" />
        </div>
      </div>
      <ExpressionInput v-model="r.condition" :scope="scope" expected="boolean" :label="t('assignment.condition')" />
      <div class="flex flex-wrap items-end gap-3">
        <div class="field w-44">
          <label :for="`ar-due-${i}`">{{ t('assignment.due') }}</label>
          <InputNumber v-model="r.dueInMinutes" :input-id="`ar-due-${i}`" :min="1" :max="525600" size="small" :suffix="` ${t('workflow.minutes')}`" />
        </div>
        <div class="field w-32">
          <label :for="`ar-pr-${i}`">{{ t('assignment.priority') }}</label>
          <InputNumber v-model="r.priority" :input-id="`ar-pr-${i}`" :min="-100" :max="100" size="small" />
        </div>
        <label class="flex items-center gap-2 text-sm pb-2"><ToggleSwitch v-model="r.workingTime" />{{ t('sla.working_time') }}</label>
        <span class="flex-1" />
        <Button icon="pi pi-arrow-up" text size="small" :aria-label="t('assignment.move_up')" :disabled="i === 0" @click="move(i, -1)" />
        <Button icon="pi pi-arrow-down" text size="small" :aria-label="t('assignment.move_down')" :disabled="i === (doc.value.value?.length ?? 0) - 1" @click="move(i, 1)" />
        <Button icon="pi pi-trash" text severity="danger" size="small" :aria-label="t('workflow.remove')" @click="doc.value.value!.splice(i, 1)" />
      </div>
    </section>
    <ErrorList :errors="doc.errors.value" />
  </div>
</template>
