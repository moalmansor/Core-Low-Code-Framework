<script setup lang="ts">
import Button from 'primevue/button'
import Listbox from 'primevue/listbox'
import Message from 'primevue/message'
import MultiSelect from 'primevue/multiselect'
import Paginator, { type PageState } from 'primevue/paginator'
import Popover from 'primevue/popover'
import Select from 'primevue/select'
import SelectButton from 'primevue/selectbutton'
import Tab from 'primevue/tab'
import TabList from 'primevue/tablist'
import TabPanel from 'primevue/tabpanel'
import TabPanels from 'primevue/tabpanels'
import Tabs from 'primevue/tabs'
import Tag from 'primevue/tag'
import ToggleSwitch from 'primevue/toggleswitch'
import TreeSelect from 'primevue/treeselect'
import { useToast } from 'primevue/usetoast'
import { computed, onMounted, reactive, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { onBeforeRouteLeave, useRoute } from 'vue-router'
import { get, send } from '@/api/http'
import { withStepUp } from '@/api/stepup'
import StepUpDialog from '@/components/StepUpDialog.vue'
import UserPicker from '@/components/UserPicker.vue'
import { useSession } from '@/stores/session'
import { departmentNodes, type DepartmentApi, type DepartmentNode } from '@/views/admin/departments'
import {
  LEVELS,
  MODES,
  applyBulk,
  buildChanges,
  cellOf,
  cellView,
  describeVector,
  editKey,
  isNoop,
  needsStepUp,
  parseDecidedBy,
  subjectKey,
  type Effect,
  type Level,
  type Matrix,
  type Mode,
  type PendingEdit,
  type Scope,
  type Subject,
  type SubjectType,
  type Target,
} from './accessMatrix'
import { errorText } from './shared'

const { t } = useI18n()
const toast = useToast()
const route = useRoute()
const session = useSession()
const stepUp = ref<InstanceType<typeof StepUpDialog> | null>(null)
const formUuid = computed(() => String(route.params.form))
const tab = ref('form')

interface RoleOption {
  uuid: string
  name: string
}
type UserOption = { uuid: string; name: string; email: string }

const roles = ref<RoleOption[]>([])
const departments = ref<DepartmentNode[]>([])
const flatDepartments = computed(() => {
  const out: { uuid: string; label: string }[] = []
  const walk = (nodes: DepartmentNode[]): void => {
    for (const n of nodes) {
      out.push({ uuid: n.key, label: n.label })
      walk(n.children)
    }
  }
  walk(departments.value)
  return out
})
const allTargets = ref<Target[]>([])
const formLabel = computed(() => allTargets.value[0]?.label || allTargets.value[0]?.key || '')

onMounted(async () => {
  try {
    const [r, everyone] = await Promise.all([
      get<{ data: RoleOption[] }>('/roles'),
      get<{ data: Matrix }>(`/forms/${formUuid.value}/access-matrix`, { subject_type: 'everyone', mode: 'edit', deviating_only: 0 }),
    ])
    roles.value = r.data
    allTargets.value = everyone.data.targets
    if (session.can('system.manage_users')) departments.value = departmentNodes((await get<{ data: DepartmentApi[] }>('/departments/tree')).data)
  } catch (e) {
    toast.add({ severity: 'error', summary: errorText(e, t('building.load_failed')), life: 6000 })
  }
})

const levelLabel = (l: Level | null) => (l ? t(`building.level.${l}`) : '—')
const levelIcon: Record<Level, string> = { hidden: 'pi pi-eye-slash', read_only: 'pi pi-eye', editable: 'pi pi-pencil', required: 'pi pi-asterisk' }
const modeOptions = computed(() => MODES.map((m) => ({ value: m, label: t(`building.access.mode.${m}`) })))
const effectOptions = computed(() => (['allow', 'deny', 'hard_deny'] as const).map((v) => ({ value: v, label: t(`access.effect.${v}`) })))
const targetName = (tg: Target) => tg.label || tg.key

// ── Form-level grants (permission assignments on form.{uuid}.*) ──────────

type GrantEffect = 'allow' | 'deny' | 'hard_deny' | null
interface Grant {
  permission: string
  effect: GrantEffect
  include_descendants: boolean
}
interface CatalogEntry {
  key: string
  label: string
  is_dangerous: boolean
}
const ORDER = ['view', 'create', 'edit', 'delete', 'restore', 'export', 'import', 'print', 'view_log']
const catalog = ref<CatalogEntry[]>([])
const grantSubjectType = ref<'role' | 'user' | 'department'>('role')
const grantRole = ref<RoleOption | null>(null)
const grantUser = ref<UserOption | null>(null)
const grantDept = ref<Record<string, boolean> | null>(null)
const grantsOriginal = ref<Record<string, Grant>>({})
const grantsDraft = ref<Record<string, Grant>>({})
const grantSubject = computed(() => {
  if (grantSubjectType.value === 'role') return grantRole.value?.uuid
  if (grantSubjectType.value === 'user') return grantUser.value?.uuid
  return grantDept.value ? Object.keys(grantDept.value)[0] : undefined
})
const grantEffectOptions = computed(() => [{ value: null, label: t('access.effect.inherit') }, ...effectOptions.value])
const grantSubjectOptions = computed(() => (['role', 'user', 'department'] as const).map((v) => ({ value: v, label: t(`access.subject.${v}`) })))
const grantChanges = computed(() =>
  catalog.value
    .map((p) => p.key)
    .filter(
      (k) =>
        (grantsDraft.value[k]?.effect ?? null) !== (grantsOriginal.value[k]?.effect ?? null) ||
        (grantsDraft.value[k]?.include_descendants ?? false) !== (grantsOriginal.value[k]?.include_descendants ?? false),
    ),
)

watch(
  tab,
  async (v) => {
    if (v !== 'form' || catalog.value.length) return
    try {
      const prefix = `form.${formUuid.value}.`
      const all = (await get<{ data: CatalogEntry[] }>('/permissions', { scope_type: 'form' })).data
      const rank = (k: string) => {
        const i = ORDER.indexOf(k.slice(prefix.length))
        return i < 0 ? ORDER.length : i
      }
      catalog.value = all.filter((p) => p.key.startsWith(prefix)).sort((a, b) => rank(a.key) - rank(b.key) || a.key.localeCompare(b.key))
    } catch (e) {
      toast.add({ severity: 'error', summary: errorText(e, t('building.load_failed')), life: 6000 })
    }
  },
  { immediate: true },
)

async function loadGrants(): Promise<void> {
  grantsOriginal.value = {}
  grantsDraft.value = {}
  if (!grantSubject.value) return
  const list = (await get<{ data: Grant[] }>('/permission-assignments', { subject_type: grantSubjectType.value, subject: grantSubject.value })).data
  for (const g of list) grantsOriginal.value[g.permission] = { ...g }
  grantsDraft.value = JSON.parse(JSON.stringify(grantsOriginal.value))
}
watch([grantSubjectType, grantSubject], loadGrants)

function setGrant(key: string, effect: GrantEffect): void {
  grantsDraft.value[key] = { permission: key, effect, include_descendants: grantsDraft.value[key]?.include_descendants ?? false }
}
async function saveGrants(): Promise<void> {
  const grants = grantChanges.value.map((k) => ({ permission: k, effect: grantsDraft.value[k]?.effect ?? null, include_descendants: grantsDraft.value[k]?.include_descendants ?? false }))
  try {
    const done = await withStepUp(stepUp, (code) => send('put', '/permission-assignments', { subject_type: grantSubjectType.value, subject: grantSubject.value, grants, confirmation_code: code }))
    if (done === null) return
    toast.add({ severity: 'success', summary: t('common.saved'), life: 3000 })
    await loadGrants()
  } catch (e) {
    toast.add({ severity: 'error', summary: errorText(e, t('building.save_failed')), life: 8000 })
  }
}

// ── Group and field access matrix ────────────────────────────────────────

const view = reactive({
  mode: 'edit' as Mode,
  subjectType: 'role' as SubjectType,
  subjects: [] as string[],
  group: null as string | null,
  deviatingOnly: true,
  page: 1,
  perPage: 10,
})
const scope = ref<Scope>('mode')
const userSubjects = ref<UserOption[]>([])
const addUser = ref<UserOption | null>(null)
const matrix = ref<Matrix | null>(null)
const totalSubjects = ref(0)
const loadingMatrix = ref(false)
const pending = ref(new Map<string, PendingEdit>())
const pendingCount = computed(() => pending.value.size)
const subjectTypeOptions = computed(() => (['everyone', 'role', 'department', 'user'] as const).map((v) => ({ value: v, label: t(`building.access.subject.${v}`) })))
const scopeOptions = computed(() => [
  { value: 'mode', label: t('building.access.scope_mode') },
  { value: 'all', label: t('building.access.scope_all') },
])
const groupOptions = computed(() => allTargets.value.filter((x) => x.type === 'group').map((g) => ({ value: g.uuid, label: `${'· '.repeat(Math.max(0, g.depth - 1))}${targetName(g)}` })))

async function loadMatrix(): Promise<void> {
  loadingMatrix.value = true
  try {
    const params: Record<string, unknown> = { mode: view.mode, subject_type: view.subjectType, deviating_only: view.deviatingOnly ? 1 : 0, page: view.page, per_page: view.perPage }
    if (view.group) params.group = view.group
    const chosen = view.subjectType === 'user' ? userSubjects.value.map((u) => u.uuid) : view.subjectType === 'everyone' ? [] : view.subjects
    if (chosen.length) params.subjects = chosen
    const res = await get<{ data: Matrix; meta: { total_subjects: number } }>(`/forms/${formUuid.value}/access-matrix`, params)
    matrix.value = res.data
    totalSubjects.value = res.meta.total_subjects
    if (view.subjectType === 'user' && !userSubjects.value.length) userSubjects.value = res.data.subjects.map((s) => ({ uuid: s.uuid!, name: s.name, email: '' }))
  } catch (e) {
    toast.add({ severity: 'error', summary: errorText(e, t('building.load_failed')), life: 6000 })
  } finally {
    loadingMatrix.value = false
  }
}
// Declared first so a subject type change resets the filters before the reload below runs.
watch(
  () => view.subjectType,
  () => {
    view.subjects = []
    view.page = 1
    userSubjects.value = []
  },
)
watch(
  () => [view.mode, view.subjectType, view.subjects.join(','), view.group, view.deviatingOnly, view.page],
  () => {
    if (tab.value === 'matrix') loadMatrix()
  },
)
watch(tab, (v) => {
  if (v === 'matrix' && !matrix.value) loadMatrix()
})
watch(addUser, (u) => {
  if (!u) return
  if (!userSubjects.value.some((x) => x.uuid === u.uuid) && userSubjects.value.length < 50) userSubjects.value.push(u)
  addUser.value = null
  loadMatrix()
})
function removeUserSubject(uuid: string): void {
  userSubjects.value = userSubjects.value.filter((u) => u.uuid !== uuid)
  loadMatrix()
}
function onSubjectPage(e: PageState): void {
  view.page = e.page + 1
}

// Cell editing
const bulk = ref(false)
const selection = ref(new Set<string>())
const cellKey = (target: string, subject: string) => `${target}|${subject}`
const editor = ref<InstanceType<typeof Popover> | null>(null)
const editing = ref<{ target: Target; subject: Subject; access: Level | null; effect: Effect } | null>(null)
const bulkEdit = reactive({ access: 'read_only' as Level | null, effect: 'allow' as Effect })
const levelOptions = computed(() => [{ value: null, label: t('building.access.reset_inherited') }, ...LEVELS.map((l) => ({ value: l, label: levelLabel(l) }))])

function viewOf(target: Target, subject: Subject) {
  const key = subjectKey(subject)
  return cellView(cellOf(matrix.value!, target.uuid, key), pending.value.get(editKey(target.uuid, key, scope.value)), scope.value)
}
function explicitBadges(target: Target, subject: Subject): string[] {
  return cellOf(matrix.value!, target.uuid, subjectKey(subject)).explicit.map(
    (r) => `${r.mode ? t(`building.access.mode.${r.mode}`) : t('building.access.all_modes')}: ${levelLabel(r.access)} · ${t(`access.effect.${r.effect}`)}`,
  )
}
function clickCell(event: Event, target: Target, subject: Subject): void {
  const key = cellKey(target.uuid, subjectKey(subject))
  if (bulk.value) {
    const next = new Set(selection.value)
    if (next.has(key)) next.delete(key)
    else next.add(key)
    selection.value = next
    return
  }
  const v = viewOf(target, subject)
  editing.value = { target, subject, access: v.rule?.access ?? null, effect: v.rule?.effect ?? 'allow' }
  editor.value?.toggle(event)
}
function applyEdit(): void {
  const e = editing.value!
  const key = subjectKey(e.subject)
  const edit = { access: e.access, effect: e.effect }
  const map = new Map(pending.value)
  if (isNoop(cellOf(matrix.value!, e.target.uuid, key), edit, scope.value)) map.delete(editKey(e.target.uuid, key, scope.value))
  else map.set(editKey(e.target.uuid, key, scope.value), edit)
  pending.value = map
  editor.value?.hide()
  editing.value = null
}
function applyBulkEdit(): void {
  const map = new Map(pending.value)
  applyBulk(
    map,
    matrix.value!,
    [...selection.value].map((k) => {
      const [target, subject] = k.split('|') as [string, string]
      return { target, subject }
    }),
    { access: bulkEdit.access, effect: bulkEdit.effect },
    scope.value,
  )
  pending.value = map
  selection.value = new Set()
}
function selectRow(target: Target): void {
  const next = new Set(selection.value)
  for (const s of matrix.value?.subjects ?? []) next.add(cellKey(target.uuid, subjectKey(s)))
  selection.value = next
}
function discardMatrix(): void {
  pending.value = new Map()
  selection.value = new Set()
}
async function saveMatrix(): Promise<void> {
  const changes = buildChanges(pending.value, matrix.value!)
  if (!changes.length) return
  try {
    const done = await withStepUp(stepUp, (code) => send('put', `/forms/${formUuid.value}/access-rules`, { changes, confirmation_code: code }))
    if (done === null) return
    toast.add({ severity: 'success', summary: t('building.access.rules_saved', { n: changes.length }), life: 3000 })
    discardMatrix()
    await loadMatrix()
  } catch (e) {
    toast.add({ severity: 'error', summary: errorText(e, t('building.save_failed')), life: 8000 })
  }
}
const willNeedStepUp = computed(() => (matrix.value ? needsStepUp(buildChanges(pending.value, matrix.value)) : false))
onBeforeRouteLeave(() => (!pendingCount.value && !grantChanges.value.length) || window.confirm(t('building.unsaved_leave')))

function cellClass(target: Target, subject: Subject): string {
  const v = viewOf(target, subject)
  const parts = ['w-full rounded-md px-2 py-1 text-sm flex items-center gap-1 border']
  if (v.source === 'pending') parts.push('border-dashed border-warning bg-warning-subtle')
  else if (v.source === 'explicit') parts.push('font-semibold border-primary bg-primary-subtle')
  else parts.push('border-transparent text-muted-color italic')
  if (selection.value.has(cellKey(target.uuid, subjectKey(subject)))) parts.push('ring-2 ring-primary')
  return parts.join(' ')
}

// ── Explain access ───────────────────────────────────────────────────────

interface Candidate {
  uuid: string
  target_type: 'form' | 'group' | 'field'
  target_key: string | null
  subject_type: SubjectType
  subject_name: string
  mode: Mode | null
  access: Level
  effect: Effect
  vector: number[]
}
interface Explanation {
  mode: Mode
  default: Level
  candidates: Candidate[]
  tiers: { vector: number[]; rules: string[]; value_after: Level }[]
  hard_denies: Candidate[]
  resolved: Level
  gate: Record<'view' | 'create' | 'edit' | 'delete' | 'print', boolean>
  result: Level
  decided_by: string
}
const explainUser = ref<UserOption | null>(null)
const explainTarget = ref<string | null>(null)
const explainMode = ref<Mode>('edit')
const explanation = ref<Explanation | null>(null)
const explaining = ref(false)
const targetOptions = computed(() => allTargets.value.map((x) => ({ value: x.uuid, label: `${'· '.repeat(x.depth)}${targetName(x)}`, type: x.type })))
async function runExplain(): Promise<void> {
  if (!explainUser.value) return
  const target = allTargets.value.find((x) => x.uuid === explainTarget.value)
  const params: Record<string, unknown> = { user: explainUser.value.uuid, mode: explainMode.value }
  if (target?.type === 'field') params.field = target.uuid
  if (target?.type === 'group') params.group = target.uuid
  explaining.value = true
  try {
    explanation.value = (await get<{ data: Explanation }>(`/forms/${formUuid.value}/access-explain`, params)).data
  } catch (e) {
    toast.add({ severity: 'error', summary: errorText(e, t('building.load_failed')), life: 6000 })
  } finally {
    explaining.value = false
  }
}
function tierText(vector: number[]): string {
  const d = describeVector(vector)
  const parts = [t('building.access.tier_level', { n: d.level }), t(`building.access.subject.${d.subject}`)]
  if (d.mode) parts.push(t('building.access.mode_specific'))
  return parts.join(' · ')
}
const decided = computed(() => {
  if (!explanation.value) return ''
  const d = parseDecidedBy(explanation.value.decided_by)
  if (d.kind === 'default') return t('building.access.decided_default', { level: levelLabel(explanation.value.default) })
  if (d.kind === 'hard_deny') return t('building.access.decided_hard_deny')
  return t(`building.access.decided_${d.kind}`, { tier: tierText(d.vector ?? []) })
})
</script>

<template>
  <div class="flex flex-wrap items-center gap-3 mb-4">
    <RouterLink :to="{ name: 'admin.forms' }" class="text-sm"><i class="pi pi-arrow-left rtl:rotate-180 me-1" />{{ t('admin.area.forms') }}</RouterLink>
    <h1 class="page-title !mb-0 flex-1">{{ t('building.access.title', { name: formLabel }) }}</h1>
  </div>
  <Message severity="secondary" size="small" class="mb-3">{{ t('access.precedence_hint') }}</Message>

  <Tabs v-model:value="tab">
    <TabList>
      <Tab value="form" data-testid="access-tab-form">{{ t('building.access.tab_form') }}</Tab>
      <Tab value="matrix" data-testid="access-tab-matrix">{{ t('building.access.tab_matrix') }}</Tab>
      <Tab value="explain" data-testid="access-tab-explain">{{ t('building.access.tab_explain') }}</Tab>
    </TabList>
    <TabPanels>
      <TabPanel value="form">
        <div class="flex flex-col lg:flex-row gap-4">
          <aside class="lg:w-72 shrink-0 flex flex-col gap-3">
            <SelectButton v-model="grantSubjectType" :options="grantSubjectOptions" option-label="label" option-value="value" :allow-empty="false" />
            <Listbox v-if="grantSubjectType === 'role'" v-model="grantRole" :options="roles" option-label="name" class="w-full" filter data-testid="grant-roles" />
            <UserPicker v-else-if="grantSubjectType === 'user'" v-model="grantUser" />
            <TreeSelect v-else v-model="grantDept" :options="departments" selection-mode="single" :placeholder="t('access.pick_department')" />
          </aside>
          <section class="flex-1 min-w-0">
            <Message v-if="!grantSubject" severity="info">{{ t('access.pick_subject') }}</Message>
            <template v-else>
              <p class="text-sm text-muted-color mb-3">{{ t('building.access.form_level_hint') }}</p>
              <div class="rounded-lg border border-line divide-y divide-line">
                <div v-for="p in catalog" :key="p.key" class="flex flex-wrap items-center gap-3 p-2" :data-testid="`grant-${p.key.split('.').pop()}`">
                  <div class="flex-1 min-w-48">
                    <div>{{ p.label }} <Tag v-if="p.is_dangerous" severity="danger" :value="t('access.dangerous')" /></div>
                    <div class="text-xs text-muted-color ltr-value">{{ p.key }}</div>
                  </div>
                  <label v-if="grantSubjectType === 'department' && grantsDraft[p.key]?.effect" class="flex items-center gap-1 text-sm">
                    <ToggleSwitch v-model="grantsDraft[p.key]!.include_descendants" />{{ t('access.include_sub_departments') }}
                  </label>
                  <SelectButton
                    :model-value="grantsDraft[p.key]?.effect ?? null"
                    :options="grantEffectOptions"
                    option-label="label"
                    option-value="value"
                    :allow-empty="false"
                    size="small"
                    @update:model-value="(v: GrantEffect) => setGrant(p.key, v)"
                  />
                </div>
                <p v-if="!catalog.length" class="p-3 text-muted-color">{{ t('building.access.no_form_permissions') }}</p>
              </div>
              <div class="sticky bottom-0 py-3 bg-subtle flex justify-end gap-2">
                <span v-if="grantChanges.length" class="self-center text-sm">{{ t('access.pending_changes', { n: grantChanges.length }) }}</span>
                <Button severity="secondary" :label="t('common.reset')" :disabled="!grantChanges.length" @click="loadGrants" />
                <Button :label="t('common.save')" icon="pi pi-check" :disabled="!grantChanges.length" data-testid="grant-save" @click="saveGrants" />
              </div>
            </template>
          </section>
        </div>
      </TabPanel>

      <TabPanel value="matrix">
        <div class="flex flex-wrap items-end gap-3 mb-3">
          <div class="field">
            <label>{{ t('building.access.mode_label') }}</label>
            <SelectButton v-model="view.mode" :options="modeOptions" option-label="label" option-value="value" :allow-empty="false" :disabled="pendingCount > 0" data-testid="matrix-mode" />
          </div>
          <div class="field">
            <label for="mx-type">{{ t('building.access.subject_type') }}</label>
            <Select v-model="view.subjectType" input-id="mx-type" :options="subjectTypeOptions" option-label="label" option-value="value" :disabled="pendingCount > 0" class="w-44" />
          </div>
          <div v-if="view.subjectType === 'role'" class="field">
            <label for="mx-roles">{{ t('building.access.only_roles') }}</label>
            <MultiSelect
              v-model="view.subjects"
              input-id="mx-roles"
              :options="roles"
              option-label="name"
              option-value="uuid"
              filter
              :max-selected-labels="2"
              :disabled="pendingCount > 0"
              class="w-56"
            />
          </div>
          <div v-else-if="view.subjectType === 'department' && flatDepartments.length" class="field">
            <label for="mx-depts">{{ t('building.access.only_departments') }}</label>
            <MultiSelect
              v-model="view.subjects"
              input-id="mx-depts"
              :options="flatDepartments"
              option-label="label"
              option-value="uuid"
              filter
              :max-selected-labels="2"
              :disabled="pendingCount > 0"
              class="w-56"
            />
          </div>
          <div v-else-if="view.subjectType === 'user'" class="field">
            <label>{{ t('building.access.add_user') }}</label>
            <UserPicker v-model="addUser" />
          </div>
          <div class="field">
            <label for="mx-group">{{ t('building.access.group_filter') }}</label>
            <Select
              v-model="view.group"
              input-id="mx-group"
              :options="groupOptions"
              option-label="label"
              option-value="value"
              show-clear
              :placeholder="t('building.access.whole_form')"
              :disabled="pendingCount > 0"
              class="w-52"
            />
          </div>
          <label class="flex items-center gap-2 pb-2"><ToggleSwitch v-model="view.deviatingOnly" :disabled="pendingCount > 0" />{{ t('building.access.deviating_only') }}</label>
        </div>
        <div v-if="view.subjectType === 'user' && userSubjects.length" class="flex flex-wrap gap-2 mb-3">
          <Tag v-for="u in userSubjects" :key="u.uuid" severity="secondary">
            <span>{{ u.name }}</span>
            <button type="button" class="ms-1 cursor-pointer" :aria-label="t('common.remove')" :disabled="pendingCount > 0" @click="removeUserSubject(u.uuid)">
              <i class="pi pi-times text-xs" />
            </button>
          </Tag>
        </div>
        <div class="flex flex-wrap items-center gap-3 mb-3">
          <span class="text-sm">{{ t('building.access.write_for') }}</span>
          <SelectButton v-model="scope" :options="scopeOptions" option-label="label" option-value="value" :allow-empty="false" size="small" :disabled="pendingCount > 0" />
          <label class="flex items-center gap-2 text-sm"><ToggleSwitch v-model="bulk" data-testid="matrix-bulk" />{{ t('building.access.bulk') }}</label>
          <div class="flex flex-wrap items-center gap-3 text-xs ms-auto">
            <span class="italic text-muted-color">{{ t('building.access.legend_inherited') }}</span>
            <span class="font-semibold px-1 border border-primary rounded">{{ t('building.access.legend_explicit') }}</span>
            <span class="px-1 border border-dashed border-warning rounded">{{ t('building.access.legend_pending') }}</span>
            <span><i class="pi pi-lock text-danger" /> {{ t('access.effect.hard_deny') }}</span>
            <span><i class="pi pi-minus-circle text-warning" /> {{ t('access.effect.deny') }}</span>
          </div>
        </div>
        <Message v-if="pendingCount > 0" severity="secondary" size="small" class="mb-3">{{ t('building.access.filters_locked') }}</Message>
        <div v-if="bulk && selection.size" class="flex flex-wrap items-center gap-2 mb-3 p-2 rounded-lg bg-subtle" data-testid="bulk-bar">
          <span class="text-sm">{{ t('building.access.selected', { n: selection.size }) }}</span>
          <Select v-model="bulkEdit.access" :options="levelOptions" option-label="label" option-value="value" class="w-48" />
          <Select v-model="bulkEdit.effect" :options="effectOptions" option-label="label" option-value="value" :disabled="bulkEdit.access === null" class="w-40" />
          <Button size="small" :label="t('building.access.apply_selected')" @click="applyBulkEdit" />
          <Button size="small" severity="secondary" :label="t('building.access.clear_selection')" @click="selection = new Set()" />
        </div>

        <div v-if="matrix" class="overflow-auto rounded-lg border border-line" data-testid="access-matrix">
          <table class="min-w-full text-sm">
            <thead class="bg-subtle">
              <tr>
                <th class="p-2 text-start sticky start-0 bg-subtle min-w-56">{{ t('building.access.element') }}</th>
                <th v-for="s in matrix.subjects" :key="subjectKey(s)" class="p-2 text-start min-w-40">{{ s.name }}</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="tg in matrix.targets" :key="tg.uuid" class="border-t border-line">
                <th class="p-2 text-start font-normal sticky start-0 bg-card" :style="{ paddingInlineStart: `${0.5 + tg.depth * 1.25}rem` }">
                  <div class="flex items-center gap-2">
                    <i :class="tg.type === 'form' ? 'pi pi-file' : tg.type === 'group' ? 'pi pi-folder' : 'pi pi-minus'" class="text-muted-color text-xs" />
                    <span :class="tg.type !== 'field' ? 'font-medium' : ''">{{ targetName(tg) }}</span>
                    <span class="text-xs text-muted-color ltr-value">{{ tg.key }}</span>
                    <Button v-if="bulk" icon="pi pi-check-square" text rounded size="small" :aria-label="t('building.access.select_row')" @click="selectRow(tg)" />
                  </div>
                </th>
                <td v-for="s in matrix.subjects" :key="subjectKey(s)" class="p-1">
                  <button type="button" :class="cellClass(tg, s)" :title="explicitBadges(tg, s).join('\n')" :data-testid="`cell-${tg.key}-${s.uuid ?? 'everyone'}`" @click="clickCell($event, tg, s)">
                    <i v-if="viewOf(tg, s).level" :class="levelIcon[viewOf(tg, s).level!]" class="text-xs" />
                    <span class="flex-1 text-start">{{ levelLabel(viewOf(tg, s).level) }}</span>
                    <i v-if="viewOf(tg, s).effect === 'hard_deny'" class="pi pi-lock text-danger" />
                    <i v-else-if="viewOf(tg, s).effect === 'deny'" class="pi pi-minus-circle text-warning" />
                  </button>
                </td>
              </tr>
              <tr v-if="!matrix.targets.length">
                <td :colspan="matrix.subjects.length + 1" class="p-3 text-muted-color">{{ view.deviatingOnly ? t('building.access.nothing_deviates') : t('common.no_results') }}</td>
              </tr>
              <tr v-if="!matrix.subjects.length">
                <td class="p-3 text-muted-color">{{ view.subjectType === 'user' ? t('building.access.no_user_subjects') : t('common.no_results') }}</td>
              </tr>
            </tbody>
          </table>
        </div>
        <Paginator v-if="totalSubjects > view.perPage" :rows="view.perPage" :total-records="totalSubjects" :first="(view.page - 1) * view.perPage" class="mt-2" @page="onSubjectPage" />
        <p v-if="loadingMatrix" class="text-sm text-muted-color mt-2"><i class="pi pi-spin pi-spinner me-1" />{{ t('building.loading') }}</p>

        <div class="sticky bottom-0 py-3 bg-subtle flex flex-wrap justify-end items-center gap-2">
          <span v-if="willNeedStepUp" class="text-sm text-danger"><i class="pi pi-shield me-1" />{{ t('building.access.stepup_needed') }}</span>
          <span v-if="pendingCount" class="text-sm">{{ t('access.pending_changes', { n: pendingCount }) }}</span>
          <Button severity="secondary" :label="t('common.reset')" :disabled="!pendingCount" @click="discardMatrix" />
          <Button :label="t('common.save')" icon="pi pi-check" :disabled="!pendingCount" data-testid="matrix-save" @click="saveMatrix" />
        </div>

        <Popover ref="editor">
          <div v-if="editing" class="flex flex-col gap-3 w-72" data-testid="cell-editor">
            <div class="text-sm">
              <div class="font-medium">{{ targetName(editing.target) }}</div>
              <div class="text-muted-color">{{ editing.subject.name }} · {{ scope === 'all' ? t('building.access.all_modes') : t(`building.access.mode.${view.mode}`) }}</div>
            </div>
            <ul v-if="explicitBadges(editing.target, editing.subject).length" class="text-xs text-muted-color list-disc ps-4">
              <li v-for="(b, i) in explicitBadges(editing.target, editing.subject)" :key="i">{{ b }}</li>
            </ul>
            <p v-else class="text-xs text-muted-color">{{ t('building.access.inherits', { level: levelLabel(viewOf(editing.target, editing.subject).level) }) }}</p>
            <div class="field">
              <label for="ce-level">{{ t('building.access.level') }}</label>
              <Select v-model="editing.access" input-id="ce-level" :options="levelOptions" option-label="label" option-value="value" data-testid="cell-level" />
            </div>
            <div class="field">
              <label for="ce-effect">{{ t('building.access.effect') }}</label>
              <Select v-model="editing.effect" input-id="ce-effect" :options="effectOptions" option-label="label" option-value="value" :disabled="editing.access === null" data-testid="cell-effect" />
              <small class="text-muted-color">{{ t(`building.access.effect_hint.${editing.effect}`) }}</small>
            </div>
            <div class="flex justify-end gap-2">
              <Button size="small" severity="secondary" :label="t('common.cancel')" @click="editor?.hide()" />
              <Button size="small" :label="t('building.apply')" data-testid="cell-apply" @click="applyEdit" />
            </div>
          </div>
        </Popover>
      </TabPanel>

      <TabPanel value="explain">
        <p class="text-sm text-muted-color mb-3">{{ t('building.access.explain_hint') }}</p>
        <div class="flex flex-wrap items-end gap-3 mb-4">
          <div class="field">
            <label>{{ t('access.subject.user') }}</label>
            <UserPicker v-model="explainUser" />
          </div>
          <div class="field">
            <label for="ex-target">{{ t('building.access.element') }}</label>
            <Select
              v-model="explainTarget"
              input-id="ex-target"
              :options="targetOptions"
              option-label="label"
              option-value="value"
              filter
              show-clear
              :placeholder="t('building.access.whole_form')"
              class="w-64"
            />
          </div>
          <div class="field">
            <label for="ex-mode">{{ t('building.access.mode_label') }}</label>
            <Select v-model="explainMode" input-id="ex-mode" :options="modeOptions" option-label="label" option-value="value" class="w-40" />
          </div>
          <Button icon="pi pi-question-circle" :label="t('access.explain')" :disabled="!explainUser" :loading="explaining" data-testid="explain-run" @click="runExplain" />
        </div>

        <div v-if="explanation" class="flex flex-col gap-4" data-testid="explain-result">
          <Message :severity="explanation.result === 'hidden' ? 'warn' : 'success'">
            <div class="font-semibold">{{ t('building.access.result', { level: levelLabel(explanation.result) }) }}</div>
            <div class="text-sm">{{ decided }}</div>
          </Message>
          <div class="grid gap-3 md:grid-cols-3">
            <div class="rounded-lg border border-line p-3">
              <div class="text-xs text-muted-color">{{ t('building.access.default_level') }}</div>
              <div class="font-medium">{{ levelLabel(explanation.default) }}</div>
            </div>
            <div class="rounded-lg border border-line p-3">
              <div class="text-xs text-muted-color">{{ t('building.access.after_rules') }}</div>
              <div class="font-medium">{{ levelLabel(explanation.resolved) }}</div>
            </div>
            <div class="rounded-lg border border-line p-3">
              <div class="text-xs text-muted-color mb-1">{{ t('building.access.form_gate') }}</div>
              <div class="flex flex-wrap gap-2 text-sm">
                <span v-for="(ok, ability) in explanation.gate" :key="ability"
                  ><i :class="ok ? 'pi pi-check-circle text-success' : 'pi pi-times-circle text-danger'" class="me-1" />{{ t(`building.access.ability.${ability}`) }}</span
                >
              </div>
            </div>
          </div>
          <div>
            <h3 class="font-semibold mb-2">{{ t('building.access.tiers') }}</h3>
            <ol class="list-decimal ps-5 text-sm">
              <li v-for="(tier, i) in explanation.tiers" :key="i">{{ tierText(tier.vector) }} → {{ levelLabel(tier.value_after) }}</li>
              <li v-for="h in explanation.hard_denies" :key="h.uuid" class="text-danger">{{ t('access.effect.hard_deny') }}: {{ h.subject_name }} → {{ levelLabel(h.access) }}</li>
              <li v-if="!explanation.tiers.length && !explanation.hard_denies.length">{{ t('building.access.no_rules') }}</li>
            </ol>
          </div>
          <div v-if="explanation.candidates.length" class="overflow-auto">
            <h3 class="font-semibold mb-2">{{ t('building.access.candidates') }}</h3>
            <table class="min-w-full text-sm">
              <thead>
                <tr class="text-start">
                  <th class="p-1 text-start">{{ t('building.access.element') }}</th>
                  <th class="p-1 text-start">{{ t('building.access.subject_type') }}</th>
                  <th class="p-1 text-start">{{ t('building.access.mode_label') }}</th>
                  <th class="p-1 text-start">{{ t('building.access.level') }}</th>
                  <th class="p-1 text-start">{{ t('building.access.effect') }}</th>
                  <th class="p-1 text-start">{{ t('building.access.tier') }}</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="c in explanation.candidates" :key="`${c.uuid}-${c.vector.join('.')}`" class="border-t border-line">
                  <td class="p-1">
                    {{ t(`building.access.target.${c.target_type}`) }} <span class="ltr-value text-muted-color">{{ c.target_key }}</span>
                  </td>
                  <td class="p-1">{{ t(`building.access.subject.${c.subject_type}`) }}: {{ c.subject_name }}</td>
                  <td class="p-1">{{ c.mode ? t(`building.access.mode.${c.mode}`) : t('building.access.all_modes') }}</td>
                  <td class="p-1">{{ levelLabel(c.access) }}</td>
                  <td class="p-1">{{ t(`access.effect.${c.effect}`) }}</td>
                  <td class="p-1">{{ tierText(c.vector) }}</td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
      </TabPanel>
    </TabPanels>
  </Tabs>
  <StepUpDialog ref="stepUp" />
</template>
