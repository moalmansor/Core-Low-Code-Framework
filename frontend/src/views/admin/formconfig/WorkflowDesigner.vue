<script setup lang="ts">
import { Handle, MarkerType, Position, VueFlow, useVueFlow, type Connection, type Edge, type Node, type NodeDragEvent } from '@vue-flow/core'
import '@vue-flow/core/dist/style.css'
import '@vue-flow/core/dist/theme-default.css'
import Button from 'primevue/button'
import InputNumber from 'primevue/inputnumber'
import InputText from 'primevue/inputtext'
import Message from 'primevue/message'
import MultiSelect from 'primevue/multiselect'
import Select from 'primevue/select'
import { useConfirm } from 'primevue/useconfirm'
import { useToast } from 'primevue/usetoast'
import { computed, nextTick, onMounted, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { ApiError } from '@/api/http'
import ExpressionInput from '@/builder/conditions/ExpressionInput.vue'
import { buildScope } from '@/builder/conditions/scope'
import { useBuilder } from '@/builder/useBuilder'
import { reportDirty } from '@/components/config/configScreen'
import SubjectPicker from '@/components/SubjectPicker.vue'
import { useSession } from '@/stores/session'
import ConfigField from '@/components/config/ConfigField.vue'
import ConfigSaveBar from '@/components/config/ConfigSaveBar.vue'
import ConfigSection from '@/components/config/ConfigSection.vue'
import EmptyState from '@/components/config/EmptyState.vue'
import SettingSwitch from '@/components/config/SettingSwitch.vue'
import TabIntro from '@/components/config/TabIntro.vue'
import LocaleFields from '../building/LocaleFields.vue'
import { errorText } from '../building/shared'
import { labelOf, newUuid, workflowApi, type StatusDoc, type TransitionDoc, type WorkflowDocument, type WorkflowState } from './api'
import ErrorList from './ErrorList.vue'
import { STATUS_COLORS, checkWorkflow, layoutStatuses, newStatus, newTransition, suggestKey } from './workflowGraph'

/**
 * The visual workflow designer (specification §4.12, architecture §19.2):
 * statuses are nodes, transitions are edges drawn between them on a Vue Flow
 * canvas, with a keyboard-friendly list beside it. Selecting a status or a
 * transition opens its properties. The whole workflow is saved as one
 * document; problems that block publishing are shown as you edit.
 */
const props = defineProps<{ form: string }>()
const emit = defineEmits<{ changed: [] }>()
const { t, locale } = useI18n()
const toast = useToast()
const confirm = useConfirm()
const session = useSession()
const builder = useBuilder()
const flow = useVueFlow('workflow-designer')

const state = ref<WorkflowState | null>(null)
const doc = ref<WorkflowDocument>({ statuses: [], transitions: [], sla: [] })
const saved = ref('')
const saving = ref(false)
const errors = ref<Record<string, string>>({})
const selected = ref<{ kind: 'status' | 'transition'; uuid: string } | null>(null)
const ANY = '__any__'

const dirty = computed(() => JSON.stringify(doc.value) !== saved.value)
const check = computed(() => checkWorkflow(doc.value))
const defaultLocale = computed(() => session.boot?.default_locale ?? 'en')
const nameOf = (o: { key: string; i18n: { name: Record<string, string> } }) => labelOf(o.i18n.name, locale.value, o.key, defaultLocale.value)
const statusById = computed(() => new Map(doc.value.statuses.map((s) => [s.uuid, s])))
const scope = computed(() => (builder.doc ? buildScope(builder.doc, builder.catalog.fields) : { fields: [], repeaters: [], rows: null }))
const fieldOptions = computed(() => (builder.doc?.fields ?? []).map((f) => ({ value: f.uuid, label: labelOf(f.i18n?.label ?? {}, locale.value, f.key, defaultLocale.value) })))
const statusOptions = computed(() => doc.value.statuses.map((s) => ({ value: s.uuid, label: nameOf(s) })))
const fromOptions = computed(() => [{ value: null, label: t('workflow.any_status') }, ...statusOptions.value])
const levels = computed(() => (['none', 'optional', 'mandatory'] as const).map((v) => ({ value: v, label: t(`workflow.level.${v}`) })))
const approvalModes = computed(() => (['none', 'all', 'any_n', 'quorum'] as const).map((v) => ({ value: v, label: t(`workflow.approval.${v}`) })))
const rejections = computed(() => (['immediate', 'wait_all'] as const).map((v) => ({ value: v, label: t(`workflow.rejection.${v}`) })))

const current = computed<StatusDoc | TransitionDoc | null>(() => {
  const s = selected.value
  if (!s) return null
  return s.kind === 'status' ? (doc.value.statuses.find((x) => x.uuid === s.uuid) ?? null) : (doc.value.transitions.find((x) => x.uuid === s.uuid) ?? null)
})
const status = computed(() => (selected.value?.kind === 'status' ? (current.value as StatusDoc | null) : null))
const transition = computed(() => (selected.value?.kind === 'transition' ? (current.value as TransitionDoc | null) : null))

function apply(s: WorkflowState): void {
  state.value = s
  const positions = layoutStatuses(s.document)
  doc.value = JSON.parse(JSON.stringify(s.document)) as WorkflowDocument
  for (const st of doc.value.statuses) st.position ??= positions.get(st.uuid) ?? { x: 0, y: 0 }
  saved.value = JSON.stringify(doc.value)
}

async function load(): Promise<void> {
  try {
    apply(await workflowApi.load(props.form))
  } catch (e) {
    toast.add({ severity: 'error', summary: errorText(e, t('workflow.load_failed')), life: 6000 })
  }
}
onMounted(load)

async function save(): Promise<void> {
  if (!state.value) return
  saving.value = true
  errors.value = {}
  try {
    apply(await workflowApi.save(props.form, doc.value, state.value.hash))
    toast.add({ severity: 'success', summary: t('workflow.saved'), life: 3000 })
    emit('changed')
  } catch (e) {
    if (e instanceof ApiError && e.status === 409) {
      toast.add({ severity: 'warn', summary: t('workflow.conflict'), life: 8000 })
      const newer = (e.body as { data?: WorkflowState }).data
      if (newer) apply(newer)
    } else {
      errors.value = e instanceof ApiError ? e.fieldErrors : {}
      toast.add({ severity: 'error', summary: errorText(e, t('workflow.save_failed')), life: 6000 })
    }
  } finally {
    saving.value = false
  }
}

function addStatus(): void {
  const index = doc.value.statuses.length
  const name = t('workflow.new_status', { n: index + 1 })
  const key = suggestKey(
    `status ${index + 1}`,
    doc.value.statuses.map((s) => s.key),
  )
  const s = newStatus(newUuid(), key, { [defaultLocale.value]: name }, index, { x: 240 * index, y: 0 })
  doc.value.statuses.push(s)
  selected.value = { kind: 'status', uuid: s.uuid }
}

function addTransition(from: string | null, to: string): void {
  const target = statusById.value.get(to)
  const name = target ? t('workflow.move_to', { status: nameOf(target) }) : t('workflow.new_transition')
  const key = suggestKey(
    `to ${target?.key ?? 'status'}`,
    doc.value.transitions.map((x) => x.key),
    't',
  )
  const tr = newTransition(newUuid(), key, { [defaultLocale.value]: name }, from, to, doc.value.transitions.length)
  doc.value.transitions.push(tr)
  selected.value = { kind: 'transition', uuid: tr.uuid }
}

function removeSelected(): void {
  const s = selected.value
  if (!s) return
  confirm.require({
    message: s.kind === 'status' ? t('workflow.remove_status_confirm') : t('workflow.remove_transition_confirm'),
    header: t('workflow.remove'),
    acceptProps: { severity: 'danger', label: t('workflow.remove') },
    rejectProps: { label: t('workflow.cancel'), severity: 'secondary' },
    accept: () => {
      if (s.kind === 'status') {
        doc.value.statuses = doc.value.statuses.filter((x) => x.uuid !== s.uuid)
        doc.value.transitions = doc.value.transitions.filter((x) => x.from !== s.uuid && x.to !== s.uuid)
        doc.value.sla = doc.value.sla.filter((x) => x.status !== s.uuid)
        if (!doc.value.statuses.some((x) => x.initial) && doc.value.statuses[0]) doc.value.statuses[0].initial = true
      } else {
        doc.value.transitions = doc.value.transitions.filter((x) => x.uuid !== s.uuid)
      }
      selected.value = null
    },
  })
}

function setInitial(s: StatusDoc, value: boolean): void {
  if (value) for (const x of doc.value.statuses) x.initial = x.uuid === s.uuid
  else s.initial = false
}

// ── Canvas ──
const flowNodes = computed<Node[]>(() => {
  const nodes: Node[] = doc.value.statuses.map((s) => ({
    id: s.uuid,
    type: 'status',
    position: s.position ?? { x: 0, y: 0 },
    data: { status: s, issue: check.value.warnings.some((w) => w.message === s.key) },
    selected: selected.value?.uuid === s.uuid,
  }))
  if (doc.value.transitions.some((x) => x.from === null)) nodes.push({ id: ANY, type: 'any', position: { x: -220, y: -120 }, data: {}, draggable: false })
  return nodes
})
const flowEdges = computed<Edge[]>(() =>
  doc.value.transitions.map((x) => ({
    id: x.uuid,
    source: x.from ?? ANY,
    target: x.to,
    label: nameOf(x) + (x.approval.mode !== 'none' ? ' ✓' : ''),
    type: 'smoothstep',
    markerEnd: MarkerType.ArrowClosed,
    animated: selected.value?.uuid === x.uuid,
    labelBgPadding: [4, 2] as [number, number],
    class: check.value.problems.some((p) => p.message === x.key) ? 'wf-edge-problem' : '',
  })),
)
function onConnect(c: Connection): void {
  if (!c.target || c.target === ANY || c.source === c.target) return
  addTransition(c.source === ANY ? null : c.source, c.target)
}
function onDragStop(e: NodeDragEvent): void {
  for (const n of e.nodes) {
    const s = statusById.value.get(n.id)
    if (s) s.position = { x: Math.round(n.position.x), y: Math.round(n.position.y) }
  }
}
watch(
  () => doc.value.statuses.length,
  async () => {
    await nextTick()
    flow.fitView({ padding: 0.2 })
  },
)

// ── Approvers ──
function addApprover(): void {
  transition.value?.approval.approvers.push({ type: 'role', uuid: '', weight: 1 })
}
function setApprover(i: number, v: { type: string; uuid: string | null } | null): void {
  const a = transition.value?.approval.approvers[i]
  if (a && v) {
    a.type = v.type as 'user' | 'role' | 'department'
    a.uuid = v.uuid ?? ''
  }
}

const issueText = (code: string, key: string) => t(`workflow.issue.${code}`, { key })
function discard(): void {
  doc.value = JSON.parse(saved.value) as WorkflowDocument
  selected.value = null
  errors.value = {}
}
reportDirty('workflow', () => dirty.value)
defineExpose({ dirty, save })
</script>

<template>
  <div class="flex flex-col gap-6" data-testid="workflow-designer">
    <TabIntro :title="t('formconfig.tab.workflow')" :text="t('workflow.intro')" />

    <EmptyState v-if="doc.statuses.length === 0" icon="pi pi-sitemap" :title="t('workflow.empty_title')" :description="t('workflow.empty_hint')" testid="wf-empty">
      <Button icon="pi pi-plus" :label="t('workflow.add_status')" size="small" data-testid="wf-add-status" @click="addStatus" />
    </EmptyState>

    <template v-else>
      <div v-if="check.problems.length || check.warnings.length" class="flex flex-col gap-2">
        <Message v-for="p in check.problems" :key="p.path + p.code" severity="error" :closable="false" data-testid="wf-problem">{{ issueText(p.code, p.message) }}</Message>
        <Message v-for="w in check.warnings" :key="w.path" severity="warn" :closable="false">{{ issueText(w.code, w.message) }}</Message>
      </div>

      <div class="grid gap-6 xl:grid-cols-[minmax(0,1fr)_24rem] items-start">
        <div class="flex flex-col gap-4 min-w-0">
          <div class="flex flex-wrap items-center gap-2" role="toolbar" :aria-label="t('workflow.toolbar')">
            <Button icon="pi pi-plus" :label="t('workflow.add_status')" size="small" outlined data-testid="wf-add-status" @click="addStatus" />
            <Button
              v-if="doc.statuses.length > 1"
              icon="pi pi-arrow-right rtl:rotate-180"
              :label="t('workflow.add_transition')"
              size="small"
              outlined
              data-testid="wf-add-transition"
              @click="addTransition(doc.statuses[0]!.uuid, doc.statuses[1]!.uuid)"
            />
            <span class="flex-1" />
            <Button icon="pi pi-expand" :label="t('workflow.fit')" size="small" text severity="secondary" @click="flow.fitView({ padding: 0.2 })" />
          </div>
          <div class="relative h-[28rem] rounded-xl border border-line bg-card" dir="ltr">
            <VueFlow
              id="workflow-designer"
              :nodes="flowNodes"
              :edges="flowEdges"
              :min-zoom="0.2"
              :max-zoom="2"
              fit-view-on-init
              @connect="onConnect"
              @node-drag-stop="onDragStop"
              @node-click="({ node }) => node.id !== ANY && (selected = { kind: 'status', uuid: node.id })"
              @edge-click="({ edge }) => (selected = { kind: 'transition', uuid: edge.id })"
              @pane-click="selected = null"
            >
              <template #node-status="{ data }">
                <div
                  class="rounded-lg border-2 bg-card px-3 py-2 text-sm shadow-sm min-w-36"
                  :style="{ borderColor: data.status.color }"
                  :class="data.issue ? 'outline outline-2 outline-warning' : ''"
                  :dir="session.direction"
                  :data-testid="`wf-node-${data.status.key}`"
                >
                  <Handle type="target" :position="Position.Left" />
                  <div class="flex items-center gap-2">
                    <span class="inline-block w-2.5 h-2.5 rounded-full shrink-0" :style="{ background: data.status.color }" aria-hidden="true" />
                    <span class="font-medium truncate">{{ nameOf(data.status) }}</span>
                  </div>
                  <div class="flex gap-1 mt-1 text-[10px] text-muted-color">
                    <span v-if="data.status.initial" class="rounded bg-primary-subtle px-1">{{ t('workflow.initial') }}</span>
                    <span v-if="data.status.final" class="rounded bg-subtle px-1">{{ t('workflow.final') }}</span>
                    <span class="ltr-value">{{ data.status.key }}</span>
                  </div>
                  <Handle type="source" :position="Position.Right" />
                </div>
              </template>
              <template #node-any>
                <div class="rounded-full border border-dashed border-line-strong bg-subtle px-3 py-1 text-xs" :dir="session.direction">
                  {{ t('workflow.any_status') }}
                  <Handle type="source" :position="Position.Right" />
                </div>
              </template>
            </VueFlow>
          </div>

          <!-- Keyboard-friendly list of the same workflow. -->
          <div class="rounded-xl border border-line bg-card px-5">
            <ConfigSection id="wf-statuses" :title="t('workflow.statuses')" :count="doc.statuses.length">
              <ul class="flex flex-col gap-1 m-0 p-0 list-none">
                <li v-for="s in doc.statuses" :key="s.uuid">
                  <button
                    type="button"
                    class="w-full text-start flex items-center gap-2 rounded-md px-2 py-1.5 hover:bg-subtle"
                    :class="selected?.uuid === s.uuid ? 'bg-primary-subtle' : ''"
                    :aria-current="selected?.uuid === s.uuid ? 'true' : undefined"
                    @click="selected = { kind: 'status', uuid: s.uuid }"
                  >
                    <span class="inline-block w-2.5 h-2.5 rounded-full" :style="{ background: s.color }" aria-hidden="true" />
                    <span class="flex-1 truncate">{{ nameOf(s) }}</span>
                    <span v-if="s.initial" class="text-xs text-muted-color">{{ t('workflow.initial') }}</span>
                    <span v-if="s.final" class="text-xs text-muted-color">{{ t('workflow.final') }}</span>
                  </button>
                </li>
              </ul>
            </ConfigSection>
            <ConfigSection id="wf-transitions" :title="t('workflow.transitions')" :count="doc.transitions.length">
              <EmptyState v-if="!doc.transitions.length" icon="pi pi-arrow-right" :title="t('workflow.no_transitions')" :description="t('workflow.no_transitions_text')" />
              <ul v-else class="flex flex-col gap-1 m-0 p-0 list-none">
                <li v-for="x in doc.transitions" :key="x.uuid">
                  <button
                    type="button"
                    class="w-full text-start rounded-md px-2 py-1.5 hover:bg-subtle text-sm"
                    :class="selected?.uuid === x.uuid ? 'bg-primary-subtle' : ''"
                    :aria-current="selected?.uuid === x.uuid ? 'true' : undefined"
                    @click="selected = { kind: 'transition', uuid: x.uuid }"
                  >
                    <span class="font-medium">{{ nameOf(x) }}</span>
                    <span class="text-muted-color">
                      · {{ x.from ? nameOf(statusById.get(x.from) ?? { key: '?', i18n: { name: {} } }) : t('workflow.any_status') }} →
                      {{ nameOf(statusById.get(x.to) ?? { key: '?', i18n: { name: {} } }) }}</span
                    >
                  </button>
                </li>
              </ul>
            </ConfigSection>
          </div>
        </div>

        <!-- Properties of the selected status or transition -->
        <aside class="rounded-xl border border-line bg-card px-5 min-w-0 xl:sticky xl:top-0" :aria-label="t('workflow.properties')">
          <div v-if="!current" class="py-5">
            <EmptyState icon="pi pi-info-circle" :title="t('workflow.nothing_selected')" :description="t('workflow.select_hint')" />
          </div>

          <template v-if="status">
            <h3 class="m-0 pt-4 text-base font-semibold">{{ t('workflow.status') }}: {{ nameOf(status) }}</h3>
            <ConfigSection id="wf-status-basics" :title="t('views.section.basics')">
              <LocaleFields v-model="status.i18n.name" :label="t('workflow.name')" field="name" id-prefix="wf-status-name" />
              <ConfigField :label="t('workflow.key')" for="wf-status-key" width="sm" :hint="t('formconfig.key_hint')">
                <InputText id="wf-status-key" v-model="status.key" class="ltr-value" size="small" />
              </ConfigField>
            </ConfigSection>
            <ConfigSection id="wf-status-look" :title="t('workflow.section.appearance')">
              <ConfigField :label="t('workflow.color')">
                <div class="flex flex-wrap gap-1.5" role="radiogroup" :aria-label="t('workflow.color')">
                  <button
                    v-for="c in STATUS_COLORS"
                    :key="c"
                    type="button"
                    role="radio"
                    :aria-checked="status.color === c"
                    :aria-label="c"
                    class="w-7 h-7 rounded-full border-2"
                    :class="status.color === c ? 'border-color' : 'border-transparent'"
                    :style="{ background: c }"
                    @click="status.color = c"
                  />
                </div>
              </ConfigField>
              <ConfigField :label="t('workflow.icon')" for="wf-status-icon" width="sm">
                <InputText id="wf-status-icon" v-model="status.icon" size="small" class="ltr-value" placeholder="pi pi-check" />
              </ConfigField>
            </ConfigSection>
            <ConfigSection id="wf-status-role" :title="t('workflow.section.role')">
              <SettingSwitch
                id="wf-status-initial"
                :model-value="status.initial"
                :label="t('workflow.initial_status')"
                :description="t('workflow.initial_desc')"
                @update:model-value="(v: boolean) => setInitial(status!, v)"
              />
              <SettingSwitch id="wf-status-final" v-model="status.final" :label="t('workflow.final_status')" :description="t('workflow.final_desc')" />
            </ConfigSection>
            <div class="py-4">
              <Button icon="pi pi-trash" severity="danger" outlined size="small" :label="t('workflow.remove_status')" @click="removeSelected" />
            </div>
          </template>

          <template v-if="transition">
            <h3 class="m-0 pt-4 text-base font-semibold">{{ t('workflow.transition') }}: {{ nameOf(transition) }}</h3>
            <ConfigSection id="wf-tr-basics" :title="t('views.section.basics')">
              <LocaleFields v-model="transition.i18n.name" :label="t('workflow.button_label')" field="name" id-prefix="wf-tr-name" />
              <ConfigField :label="t('workflow.key')" for="wf-tr-key" width="sm" :hint="t('formconfig.key_hint')">
                <InputText id="wf-tr-key" v-model="transition.key" class="ltr-value" size="small" />
              </ConfigField>
              <ConfigField :label="t('workflow.from')" for="wf-tr-from" width="md">
                <Select v-model="transition.from" input-id="wf-tr-from" :options="fromOptions" option-label="label" option-value="value" size="small" />
              </ConfigField>
              <ConfigField :label="t('workflow.to')" for="wf-tr-to" width="md">
                <Select v-model="transition.to" input-id="wf-tr-to" :options="statusOptions" option-label="label" option-value="value" size="small" />
              </ConfigField>
              <p class="m-0 text-sm text-muted-color">{{ t('workflow.permission_hint') }}</p>
            </ConfigSection>
            <ConfigSection id="wf-tr-asks" :title="t('workflow.section.asks')">
              <div class="cfg-row">
                <ConfigField :label="t('workflow.comment')" for="wf-tr-comment" width="sm">
                  <Select v-model="transition.comment" input-id="wf-tr-comment" :options="levels" option-label="label" option-value="value" size="small" />
                </ConfigField>
                <ConfigField :label="t('workflow.attachments')" for="wf-tr-att" width="sm">
                  <Select v-model="transition.attachments" input-id="wf-tr-att" :options="levels" option-label="label" option-value="value" size="small" />
                </ConfigField>
              </div>
              <ConfigField :label="t('workflow.required_fields')" for="wf-tr-required" width="lg" :hint="t('workflow.required_fields_hint')">
                <MultiSelect v-model="transition.requiredFields" input-id="wf-tr-required" :options="fieldOptions" option-label="label" option-value="value" filter display="chip" size="small" />
              </ConfigField>
              <SettingSwitch id="wf-tr-confirm" v-model="transition.confirmation" :label="t('workflow.confirmation')" :description="t('workflow.confirmation_desc')" />
            </ConfigSection>
            <ConfigSection id="wf-tr-condition" :title="t('workflow.condition')" :description="t('workflow.condition_desc')" :default-open="false">
              <ExpressionInput v-model="transition.condition" :scope="scope" expected="boolean" :label="t('workflow.condition')" />
            </ConfigSection>
            <ConfigSection id="wf-tr-approval" :title="t('workflow.approvals')" :description="t('workflow.approvals_desc')" :default-open="false">
              <ConfigField :label="t('workflow.approval_mode')" for="wf-tr-mode" width="md">
                <Select v-model="transition.approval.mode" input-id="wf-tr-mode" :options="approvalModes" option-label="label" option-value="value" size="small" />
              </ConfigField>
              <template v-if="transition.approval.mode !== 'none'">
                <span class="text-sm font-medium">{{ t('workflow.approvers') }}</span>
                <EmptyState v-if="!transition.approval.approvers.length" icon="pi pi-users" :title="t('workflow.no_approvers')" :description="t('workflow.no_approvers_text')">
                  <Button icon="pi pi-plus" size="small" outlined :label="t('workflow.add_approver')" @click="addApprover" />
                </EmptyState>
                <template v-else>
                  <div v-for="(a, i) in transition.approval.approvers" :key="i" class="cfg-row items-end">
                    <ConfigField :label="t('workflow.approver')" width="md">
                      <SubjectPicker :model-value="a.uuid ? { type: a.type, uuid: a.uuid } : null" :types="['role', 'department', 'user']" @update:model-value="(v) => setApprover(i, v)" />
                    </ConfigField>
                    <ConfigField v-if="transition.approval.mode === 'quorum'" :label="t('workflow.weight')" :for="`wf-tr-w-${i}`" width="xs">
                      <InputNumber v-model="a.weight" :input-id="`wf-tr-w-${i}`" :min="0.01" :max-fraction-digits="2" size="small" />
                    </ConfigField>
                    <Button icon="pi pi-times" text rounded severity="secondary" size="small" :aria-label="t('workflow.remove')" @click="transition.approval.approvers.splice(i, 1)" />
                  </div>
                  <Button icon="pi pi-plus" text size="small" class="self-start" :label="t('workflow.add_approver')" @click="addApprover" />
                </template>
                <ConfigField v-if="transition.approval.mode === 'any_n'" :label="t('workflow.approvals_needed')" for="wf-tr-n" width="xs">
                  <InputNumber v-model="transition.approval.n" input-id="wf-tr-n" :min="1" :max="Math.max(1, transition.approval.approvers.length)" size="small" />
                </ConfigField>
                <ConfigField v-if="transition.approval.mode === 'quorum'" :label="t('workflow.quorum')" for="wf-tr-q" width="xs">
                  <InputNumber v-model="transition.approval.quorumWeight" input-id="wf-tr-q" :min="0.01" :max-fraction-digits="2" size="small" />
                </ConfigField>
                <ConfigField :label="t('workflow.on_rejection')" for="wf-tr-rej" width="md">
                  <Select v-model="transition.approval.rejection" input-id="wf-tr-rej" :options="rejections" option-label="label" option-value="value" size="small" />
                </ConfigField>
                <ConfigField :label="t('workflow.rejection_status')" for="wf-tr-rejst" width="md">
                  <Select v-model="transition.approval.rejectionStatus" input-id="wf-tr-rejst" :options="statusOptions" option-label="label" option-value="value" show-clear size="small" />
                </ConfigField>
                <ConfigField :label="t('workflow.approval_due')" for="wf-tr-due" width="sm">
                  <InputNumber v-model="transition.approval.dueInMinutes" input-id="wf-tr-due" :min="1" :max="525600" size="small" :suffix="` ${t('workflow.minutes')}`" />
                </ConfigField>
              </template>
            </ConfigSection>
            <div class="py-4">
              <Button icon="pi pi-trash" severity="danger" outlined size="small" :label="t('workflow.remove_transition')" @click="removeSelected" />
            </div>
          </template>
        </aside>
      </div>
    </template>

    <ErrorList :errors="errors" />
    <ConfigSaveBar :dirty="dirty" :saving="saving" testid="wf" @save="save" @discard="discard" />
  </div>
</template>

<style scoped>
:deep(.wf-edge-problem path) {
  stroke: var(--danger);
}
</style>
