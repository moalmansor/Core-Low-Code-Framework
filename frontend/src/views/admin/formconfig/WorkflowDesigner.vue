<script setup lang="ts">
import { Handle, MarkerType, Position, VueFlow, useVueFlow, type Connection, type Edge, type Node, type NodeDragEvent } from '@vue-flow/core'
import '@vue-flow/core/dist/style.css'
import '@vue-flow/core/dist/theme-default.css'
import Button from 'primevue/button'
import InputNumber from 'primevue/inputnumber'
import InputText from 'primevue/inputtext'
import MultiSelect from 'primevue/multiselect'
import Select from 'primevue/select'
import { useConfirm } from 'primevue/useconfirm'
import { useToast } from 'primevue/usetoast'
import { computed, markRaw, nextTick, onMounted, provide, reactive, ref, watch } from 'vue'
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
import { EDGE_LABELS, createEdgeLabels } from './edgeLabels'
import WorkflowEdge from './WorkflowEdge.vue'
import { STATUS_COLORS, checkWorkflow, keyFor, layoutStatuses, newStatus, newTransition, reroute, statusRenamed, transitionRenamed, type AutoState } from './workflowGraph'

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
  // Saved items keep their keys: links and the API use them. Names typed by people stay theirs.
  auto.key.clear()
  auto.name.clear()
  unlocked.clear()
  routing.value = null
  if (selected.value && !doc.value.statuses.some((x) => x.uuid === selected.value!.uuid) && !doc.value.transitions.some((x) => x.uuid === selected.value!.uuid)) selected.value = null
}

// ── Names, keys and routes (owner report 11a–c) ──
// The designer names a new transition after its target and keeps a new item's key in step with its name,
// until the person edits them; a saved key is locked behind "Change key". The canvas is the only place a route changes.
const auto = reactive<AutoState>({ name: new Set(), key: new Set() })
const unlocked = reactive(new Set<string>())
const savedUuids = computed(() => {
  const d = JSON.parse(saved.value || '{"statuses":[],"transitions":[]}') as WorkflowDocument
  return new Set([...d.statuses.map((x) => x.uuid), ...d.transitions.map((x) => x.uuid)])
})
const keyLocked = (uuid: string) => savedUuids.value.has(uuid) && !unlocked.has(uuid)
const nameFor = (target: StatusDoc | undefined): Record<string, string> => ({
  [defaultLocale.value]: target ? t('workflow.move_to', { status: nameOf(target) }, { locale: defaultLocale.value }) : t('workflow.new_transition', {}, { locale: defaultLocale.value }),
})
let internal = false
function managed(fn: () => void): void {
  internal = true
  try {
    fn()
  } finally {
    void nextTick(() => (internal = false))
  }
}
// A name typed by the person: a status carries its key and managed transition names along; a transition's name becomes theirs.
watch(
  () => [selected.value?.uuid, JSON.stringify(current.value?.i18n.name ?? {})] as const,
  ([uuid], [prevUuid]) => {
    if (internal || uuid !== prevUuid || !uuid) return
    managed(() => {
      if (status.value) statusRenamed(doc.value, status.value, auto, nameFor, defaultLocale.value)
      else if (transition.value) transitionRenamed(doc.value, transition.value, auto, defaultLocale.value)
    })
  },
)
function keyEdited(): void {
  if (selected.value) auto.key.delete(selected.value.uuid)
}
function changeKey(): void {
  const s = selected.value
  if (!s) return
  confirm.require({
    message: t('workflow.change_key_confirm'),
    header: t('workflow.change_key'),
    acceptProps: { severity: 'warn', label: t('workflow.change_key') },
    rejectProps: { label: t('workflow.cancel'), severity: 'secondary' },
    accept: () => unlocked.add(s.uuid),
  })
}

// Choosing a route on the canvas, for a new transition or to re-route the selected one: status clicks (canvas or outline) answer the prompt.
const routing = ref<{ uuid: string | null; step: 'from' | 'to'; from: string | null } | null>(null)
function startRoute(uuid: string | null): void {
  routing.value = { uuid, step: 'from', from: null }
}
function pickStatus(id: string): void {
  const r = routing.value
  if (!r) {
    if (id !== ANY) selected.value = { kind: 'status', uuid: id }
    return
  }
  if (r.step === 'from') {
    routing.value = { ...r, step: 'to', from: id === ANY ? null : id }
    return
  }
  if (id === ANY || id === r.from) return
  routing.value = null
  if (r.uuid === null) addTransition(r.from, id)
  else {
    const tr = doc.value.transitions.find((x) => x.uuid === r.uuid)
    if (tr) managed(() => reroute(doc.value, tr, r.from, id, auto, nameFor, defaultLocale.value))
    selected.value = { kind: 'transition', uuid: r.uuid }
  }
}
function onEdgeUpdate({ edge, connection }: { edge: Edge; connection: Connection }): void {
  const tr = doc.value.transitions.find((x) => x.uuid === edge.id)
  if (!tr || !connection.target || connection.target === ANY || connection.source === connection.target) return
  managed(() => reroute(doc.value, tr, connection.source === ANY ? null : connection.source, connection.target, auto, nameFor, defaultLocale.value))
  selected.value = { kind: 'transition', uuid: tr.uuid }
}
function tidy(): void {
  const positions = layoutStatuses(doc.value)
  for (const st of doc.value.statuses) st.position = positions.get(st.uuid) ?? st.position
  void nextTick(() => flow.fitView({ padding: 0.2 }))
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
  const s = newStatus(newUuid(), '', { [defaultLocale.value]: t('workflow.new_status', { n: index + 1 }, { locale: defaultLocale.value }) }, index, { x: 260 * index, y: 0 })
  s.key = keyFor(s, doc.value.statuses, defaultLocale.value, 's')
  managed(() => {
    doc.value.statuses.push(s)
    auto.key.add(s.uuid)
    selected.value = { kind: 'status', uuid: s.uuid }
  })
}

function addTransition(from: string | null, to: string): void {
  const tr = newTransition(newUuid(), '', nameFor(statusById.value.get(to)), from, to, doc.value.transitions.length)
  tr.key = keyFor(tr, doc.value.transitions, defaultLocale.value, 't')
  managed(() => {
    doc.value.transitions.push(tr)
    auto.name.add(tr.uuid)
    auto.key.add(tr.uuid)
    selected.value = { kind: 'transition', uuid: tr.uuid }
  })
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
// Statuses with a warning, and the endpoints of the selected transition, are marked on the canvas.
const flagged = computed(() => new Set(check.value.warnings.map((w) => w.message)))
const endpoints = computed(() => (transition.value ? new Set([transition.value.from ?? ANY, transition.value.to]) : new Set<string>()))
const flowNodes = computed<Node[]>(() => {
  const nodes: Node[] = doc.value.statuses.map((s) => ({
    id: s.uuid,
    type: 'status',
    position: s.position ?? { x: 0, y: 0 },
    data: { status: s, issue: flagged.value.has(s.uuid), role: selected.value?.uuid === s.uuid ? 'selected' : endpoints.value.has(s.uuid) ? 'endpoint' : null },
  }))
  if (doc.value.transitions.some((x) => x.from === null))
    nodes.push({ id: ANY, type: 'any', position: { x: -220, y: -120 }, data: { role: endpoints.value.has(ANY) ? 'endpoint' : null }, draggable: false })
  return nodes
})
const problemTransitions = computed(() => new Set(check.value.problems.map((p) => p.message)))
const flowEdges = computed<Edge[]>(() => {
  // Transitions between the same two statuses run side by side rather than on top of each other.
  const pairs = new Map<string, number>()
  return doc.value.transitions.map((x) => {
    const pair = [x.from ?? ANY, x.to].sort().join('|')
    const n = pairs.get(pair) ?? 0
    pairs.set(pair, n + 1)
    return {
      id: x.uuid,
      source: x.from ?? ANY,
      target: x.to,
      type: 'wf',
      markerEnd: MarkerType.ArrowClosed,
      updatable: true,
      data: { label: nameOf(x) + (x.approval.mode !== 'none' ? ' ✓' : ''), offset: 20 + n * 18, selected: selected.value?.uuid === x.uuid, problem: problemTransitions.value.has(x.uuid) },
    }
  })
})
provide(
  EDGE_LABELS,
  createEdgeLabels((id) => {
    if (!routing.value) selected.value = { kind: 'transition', uuid: id }
  }),
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

// Issues name the status or transition they concern; clicking one selects it.
function issueName(uuid: string): string {
  const s = statusById.value.get(uuid)
  if (s) return nameOf(s)
  const tr = doc.value.transitions.find((x) => x.uuid === uuid)
  return tr ? nameOf(tr) : ''
}
const strip = computed(() => {
  const groups: { code: string; severity: 'error' | 'warn'; items: { uuid: string; name: string }[] }[] = []
  for (const p of check.value.problems) {
    if (p.code === 'initial_count') groups.push({ code: p.code, severity: 'error', items: [] })
  }
  for (const [code, severity, list] of [
    ['final_outgoing', 'error', check.value.problems],
    ['unreachable', 'warn', check.value.warnings],
    ['dead_end', 'warn', check.value.warnings],
    ['no_entry', 'warn', check.value.warnings],
  ] as const) {
    const items = list.filter((i) => i.code === code).map((i) => ({ uuid: i.message, name: issueName(i.message) }))
    if (items.length) groups.push({ code, severity, items })
  }
  return groups
})
function selectIssue(uuid: string): void {
  selected.value = statusById.value.has(uuid) ? { kind: 'status', uuid } : { kind: 'transition', uuid }
}
const routeName = (uuid: string | null) => (uuid === null ? t('workflow.any_status') : issueName(uuid))
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
      <!-- Validation strip: what would break the workflow, by status, before publishing (owner report 11d). -->
      <section class="wf-strip" :class="strip.length ? '' : 'is-ok'" data-testid="wf-checks" :aria-label="t('workflow.checks')">
        <span v-if="!strip.length" class="text-sm flex items-center gap-2"><i class="pi pi-check-circle text-success" aria-hidden="true" />{{ t('workflow.checks_ok') }}</span>
        <div v-for="g in strip" :key="g.code" class="wf-strip-row" :data-testid="`wf-check-${g.code}`">
          <span class="wf-strip-label" :class="g.severity === 'error' ? 'text-danger' : 'text-warning'">
            <i :class="g.severity === 'error' ? 'pi pi-times-circle' : 'pi pi-exclamation-triangle'" aria-hidden="true" />{{ t(`workflow.check.${g.code}`) }}
          </span>
          <button v-for="it in g.items" :key="it.uuid" type="button" class="wf-chip" dir="auto" @click="selectIssue(it.uuid)">{{ it.name }}</button>
        </div>
      </section>

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
              @click="startRoute(null)"
            />
            <span class="flex-1" />
            <Button icon="pi pi-sitemap" :label="t('workflow.tidy')" size="small" text severity="secondary" data-testid="wf-tidy" @click="tidy" />
            <Button icon="pi pi-expand" :label="t('workflow.fit')" size="small" text severity="secondary" @click="flow.fitView({ padding: 0.2 })" />
          </div>
          <div class="relative h-[30rem] rounded-xl border border-line bg-card" dir="ltr">
            <div v-if="routing" class="wf-routing" :dir="session.direction" role="status" data-testid="wf-routing">
              <i class="pi pi-directions" aria-hidden="true" />
              <span class="flex-1">{{ routing.step === 'from' ? t('workflow.route_pick_from') : t('workflow.route_pick_to', { from: routeName(routing.from) }) }}</span>
              <Button :label="t('workflow.cancel')" size="small" text severity="secondary" @click="routing = null" />
            </div>
            <VueFlow
              id="workflow-designer"
              :nodes="flowNodes"
              :edges="flowEdges"
              :edge-types="{ wf: markRaw(WorkflowEdge) }"
              :min-zoom="0.2"
              :max-zoom="2"
              :delete-key-code="null"
              :edges-updatable="true"
              fit-view-on-init
              @connect="onConnect"
              @edge-update="onEdgeUpdate"
              @node-drag-stop="onDragStop"
              @node-click="({ node }) => pickStatus(node.id)"
              @edge-click="({ edge }) => !routing && (selected = { kind: 'transition', uuid: edge.id })"
              @pane-click="!routing && (selected = null)"
            >
              <template #node-status="{ data }">
                <div
                  class="wf-node"
                  :class="[data.role === 'selected' ? 'is-selected' : '', data.role === 'endpoint' ? 'is-endpoint' : '', data.issue ? 'is-flagged' : '', routing ? 'is-pickable' : '']"
                  :style="{ borderColor: data.status.color }"
                  :dir="session.direction"
                  :data-testid="`wf-node-${data.status.key}`"
                >
                  <Handle type="target" :position="Position.Left" />
                  <div class="flex items-center gap-2">
                    <span class="inline-block w-2.5 h-2.5 rounded-full shrink-0" :style="{ background: data.status.color }" aria-hidden="true" />
                    <span class="font-medium truncate">{{ nameOf(data.status) }}</span>
                    <i v-if="data.issue" class="pi pi-exclamation-triangle text-warning text-xs" :aria-label="t('workflow.has_warning')" />
                  </div>
                  <div v-if="data.status.initial || data.status.final" class="flex gap-1 mt-1 text-[10px] text-muted-color">
                    <span v-if="data.status.initial" class="rounded bg-primary-subtle px-1">{{ t('workflow.initial') }}</span>
                    <span v-if="data.status.final" class="rounded bg-subtle px-1">{{ t('workflow.final') }}</span>
                  </div>
                  <Handle type="source" :position="Position.Right" />
                </div>
              </template>
              <template #node-any="{ data }">
                <div class="rounded-full border border-dashed border-line-strong bg-subtle px-3 py-1 text-xs" :class="data.role === 'endpoint' ? 'wf-any-endpoint' : ''" :dir="session.direction">
                  {{ t('workflow.any_status') }}
                  <Handle type="source" :position="Position.Right" />
                </div>
              </template>
            </VueFlow>
          </div>

          <!-- Outline: the same workflow as a keyboard-friendly list; selecting here selects on the canvas. -->
          <div class="rounded-xl border border-line bg-card px-5">
            <ConfigSection id="wf-outline" :title="t('workflow.outline')" :description="t('workflow.outline_desc')" :default-open="false">
              <ul class="wf-outline">
                <li v-for="st in doc.statuses" :key="st.uuid">
                  <button type="button" class="wf-outline-item" :class="{ 'is-selected': selected?.uuid === st.uuid }" @click="pickStatus(st.uuid)">
                    <span class="inline-block w-2 h-2 rounded-full" :style="{ background: st.color }" aria-hidden="true" />
                    <span class="truncate" dir="auto">{{ nameOf(st) }}</span>
                  </button>
                  <ul v-if="doc.transitions.some((x) => x.from === st.uuid)" class="wf-outline">
                    <li v-for="x in doc.transitions.filter((x) => x.from === st.uuid)" :key="x.uuid">
                      <button
                        type="button"
                        class="wf-outline-item is-transition"
                        :class="{ 'is-selected': selected?.uuid === x.uuid }"
                        @click="!routing && (selected = { kind: 'transition', uuid: x.uuid })"
                      >
                        <i class="pi pi-arrow-right rtl:rotate-180 text-xs text-muted-color" aria-hidden="true" />
                        <span class="truncate" dir="auto">{{ nameOf(x) }}</span>
                        <span class="text-muted-color truncate" dir="auto">→ {{ routeName(x.to) }}</span>
                      </button>
                    </li>
                  </ul>
                </li>
                <li v-if="doc.transitions.some((x) => x.from === null)">
                  <span class="wf-outline-item text-muted-color">{{ t('workflow.any_status') }}</span>
                  <ul class="wf-outline">
                    <li v-for="x in doc.transitions.filter((x) => x.from === null)" :key="x.uuid">
                      <button
                        type="button"
                        class="wf-outline-item is-transition"
                        :class="{ 'is-selected': selected?.uuid === x.uuid }"
                        @click="!routing && (selected = { kind: 'transition', uuid: x.uuid })"
                      >
                        <span class="truncate" dir="auto">{{ nameOf(x) }}</span>
                        <span class="text-muted-color truncate" dir="auto">→ {{ routeName(x.to) }}</span>
                      </button>
                    </li>
                  </ul>
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
              <ConfigField
                :label="t('workflow.key')"
                for="wf-status-key"
                width="sm"
                :hint="keyLocked(status.uuid) ? t('workflow.key_locked_hint') : auto.key.has(status.uuid) ? t('workflow.key_auto_hint') : t('formconfig.key_hint')"
              >
                <div v-if="keyLocked(status.uuid)" class="flex items-center gap-2">
                  <span class="ltr-value text-sm" data-testid="wf-status-key-locked">{{ status.key }}</span>
                  <Button :label="t('workflow.change_key')" size="small" text @click="changeKey" />
                </div>
                <InputText v-else id="wf-status-key" v-model="status.key" class="ltr-value" size="small" @input="keyEdited" />
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
              <ConfigField
                :label="t('workflow.key')"
                for="wf-tr-key"
                width="sm"
                :hint="keyLocked(transition.uuid) ? t('workflow.key_locked_hint') : auto.key.has(transition.uuid) ? t('workflow.key_auto_hint') : t('formconfig.key_hint')"
              >
                <div v-if="keyLocked(transition.uuid)" class="flex items-center gap-2">
                  <span class="ltr-value text-sm" data-testid="wf-tr-key-locked">{{ transition.key }}</span>
                  <Button :label="t('workflow.change_key')" size="small" text data-testid="wf-tr-change-key" @click="changeKey" />
                </div>
                <InputText v-else id="wf-tr-key" v-model="transition.key" class="ltr-value" size="small" @input="keyEdited" />
              </ConfigField>
              <!-- The route is what the canvas shows; it changes only on the canvas (owner report 11b). -->
              <ConfigField :label="t('workflow.route')" width="lg" :hint="t('workflow.route_hint')">
                <div class="flex flex-wrap items-center gap-2 text-sm" data-testid="wf-tr-route">
                  <span class="wf-chip" dir="auto">{{ routeName(transition.from) }}</span>
                  <i class="pi pi-arrow-right rtl:rotate-180 text-muted-color" aria-hidden="true" />
                  <span class="wf-chip" dir="auto">{{ routeName(transition.to) }}</span>
                  <Button icon="pi pi-directions" :label="t('workflow.reroute')" size="small" text data-testid="wf-tr-reroute" @click="startRoute(transition.uuid)" />
                </div>
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

    <ErrorList :errors="errors" :name="(c, i) => (c === 'statuses' && doc.statuses[i] ? nameOf(doc.statuses[i]) : c === 'transitions' && doc.transitions[i] ? nameOf(doc.transitions[i]) : null)" />
    <ConfigSaveBar :dirty="dirty" :saving="saving" testid="wf" @save="save" @discard="discard" />
  </div>
</template>

<style scoped>
.wf-node {
  min-width: 9rem;
  padding: 0.5rem 0.75rem;
  font-size: var(--text-size-sm);
  background: var(--bg-surface);
  border: 2px solid;
  border-radius: 0.5rem;
  box-shadow: 0 1px 2px var(--shadow-color);
}
.wf-node.is-selected {
  outline: 3px solid var(--primary);
  outline-offset: 2px;
}
.wf-node.is-endpoint {
  outline: 2px dashed var(--primary);
  outline-offset: 2px;
}
.wf-node.is-flagged:not(.is-selected):not(.is-endpoint) {
  outline: 2px solid var(--warning);
  outline-offset: 2px;
}
.wf-node.is-pickable {
  cursor: crosshair;
}
.wf-any-endpoint {
  outline: 2px dashed var(--primary);
  outline-offset: 2px;
}
.wf-strip {
  display: flex;
  flex-direction: column;
  gap: 0.5rem;
  padding: 0.75rem 1rem;
  border: 1px solid var(--border);
  border-radius: var(--radius-card);
  background: var(--bg-surface);
}
.wf-strip-row {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 0.375rem 0.5rem;
}
.wf-strip-label {
  display: inline-flex;
  align-items: center;
  gap: 0.375rem;
  min-width: 14rem;
  font-size: var(--text-size-sm);
  font-weight: 500;
}
.wf-chip {
  padding: 0.125rem 0.625rem;
  font-size: var(--text-size-sm);
  color: var(--text);
  background: var(--bg-subtle);
  border: 1px solid var(--border);
  border-radius: 999px;
}
button.wf-chip:hover {
  border-color: var(--primary);
}
.wf-routing {
  position: absolute;
  inset-inline: 0.75rem;
  top: 0.75rem;
  z-index: 10;
  display: flex;
  align-items: center;
  gap: 0.5rem;
  padding: 0.5rem 0.75rem;
  font-size: var(--text-size-sm);
  color: var(--on-primary-subtle);
  background: var(--primary-subtle);
  border: 1px solid var(--primary);
  border-radius: var(--radius-control);
}
.wf-outline {
  display: flex;
  flex-direction: column;
  gap: 0.125rem;
  margin: 0;
  padding: 0;
  list-style: none;
}
.wf-outline .wf-outline {
  padding-inline-start: 1.25rem;
}
.wf-outline-item {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  width: 100%;
  padding: 0.25rem 0.5rem;
  font-size: var(--text-size-sm);
  text-align: start;
  background: none;
  border: 0;
  border-radius: var(--radius-control);
}
button.wf-outline-item:hover {
  background: var(--bg-subtle);
}
.wf-outline-item.is-selected {
  background: var(--primary-subtle);
  color: var(--on-primary-subtle);
}
.wf-outline-item.is-transition {
  font-size: var(--text-size-xs);
}
</style>
