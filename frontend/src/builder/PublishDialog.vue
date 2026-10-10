<script setup lang="ts">
import Button from 'primevue/button'
import Checkbox from 'primevue/checkbox'
import Dialog from 'primevue/dialog'
import InputNumber from 'primevue/inputnumber'
import InputText from 'primevue/inputtext'
import Message from 'primevue/message'
import MultiSelect from 'primevue/multiselect'
import ProgressBar from 'primevue/progressbar'
import ProgressSpinner from 'primevue/progressspinner'
import Select from 'primevue/select'
import Textarea from 'primevue/textarea'
import ToggleSwitch from 'primevue/toggleswitch'
import type { TreeNode } from 'primevue/treenode'
import TreeSelect from 'primevue/treeselect'
import { computed, onBeforeUnmount, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { ApiError } from '@/api/http'
import UserPicker from '@/components/UserPicker.vue'
import { useSession } from '@/stores/session'
import { workflowApi } from '@/views/admin/formconfig/api'
import { builderApi, referenceApi, type DepartmentTreeNode, type ImpactResponse, type MenuNode, type NamedOption, type PlanStatus } from './api'
import DiffView from './DiffView.vue'
import { labelOf } from '@/runtime/i18nText'
import I18nInput from './I18nInput.vue'
import { issueText } from './issues'
import type { I18nText } from './types'
import { useBuilder } from './useBuilder'

/**
 * Publish (specification §4.10, §4.13): impact analysis of the saved draft,
 * the confirmations it requires, where the form appears and who may use it,
 * then the migration plan runs and its progress is followed to the end.
 */
const props = withDefaults(defineProps<{ rollbackOf?: number | null }>(), { rollbackOf: null })
const visible = defineModel<boolean>('visible', { required: true })
const emit = defineEmits<{ published: [version: number] }>()
const { t, te } = useI18n()

/** A blocking item in words: the element it concerns by its label, and the problem in the interface language — never a raw path. */
function blockingText(b: { code: string; path?: string | null; message: string; detail?: string | null }): string {
  const text = b.code === 'draft_problem' && b.detail ? issueText(t, te, { code: b.detail, message: b.message, path: b.path ?? '' }) : b.message
  const m = /^(fields|groups)\.(\d+)(\.|$)/.exec(b.path ?? '')
  const el = m && builder.doc ? (m[1] === 'fields' ? builder.doc.fields[Number(m[2])] : builder.doc.groups[Number(m[2])]) : null
  if (!el) return text
  const i18n = (el as { i18n?: { label?: I18nText; title?: I18nText } }).i18n
  return `${labelOf(m![1] === 'fields' ? i18n?.label : i18n?.title, builder.locale, el.key)}: ${text}`
}
const builder = useBuilder()
const session = useSession()

type Step = 'impact' | 'placement' | 'running' | 'done'
const step = ref<Step>('impact')
const loading = ref(false)
const failure = ref<string | null>(null)
const analysis = ref<ImpactResponse | null>(null)
const confirmBlocking = ref(false)
const confirmDestructive = ref(false)
const typed = ref('')
const changeNote = ref('')
const showSql = ref(false)
const showDiff = ref(false)

// Placement
const canMenu = computed(() => session.can('system.manage_pages_menus'))
const canAllow = computed(() => session.can('system.manage_permissions'))
const addToMenu = ref(false)
const applications = ref<NamedOption[]>([])
const application = ref<string | null>(null)
const menu = ref<MenuNode[]>([])
const parent = ref<string | null>(null)
const sortOrder = ref<number | null>(null)
const icon = ref('')
const label = ref<I18nText>({})
const roles = ref<NamedOption[]>([])
const allowedRoles = ref<string[]>([])
const allowedUsers = ref<{ uuid: string; name: string; email: string }[]>([])
const pickedUser = ref<{ uuid: string; name: string; email: string } | null>(null)
const departments = ref<TreeNode[]>([])
const allowedDepartments = ref<Record<string, { checked: boolean; partialChecked: boolean }>>({})

// Progress
const plan = ref<PlanStatus | null>(null)
let poll: ReturnType<typeof setTimeout> | null = null

const impact = computed(() => analysis.value?.impact ?? null)
const blocked = computed(() => (impact.value?.blocking.length ?? 0) > 0)
const needsBlockingConfirm = computed(() => !!impact.value && (impact.value.requires_confirmation.blocking_steps || impact.value.requires_confirmation.failing_required))
const needsTyped = computed(() => !!impact.value?.requires_confirmation.destructive)
const formKey = computed(() => builder.form?.key ?? '')
const canPublish = computed(
  () => !!analysis.value && !blocked.value && (!needsBlockingConfirm.value || confirmBlocking.value) && (!needsTyped.value || (confirmDestructive.value && typed.value === formKey.value)),
)
const defaultLocale = computed(() => builder.locales.find((l) => l.is_default)?.code ?? 'en')
const placementValid = computed(() => !addToMenu.value || (!!application.value && !!label.value[defaultLocale.value]))

function flattenMenu(nodes: MenuNode[], depth = 0): { value: string; label: string }[] {
  return nodes.flatMap((n) => [{ value: n.uuid, label: `${'— '.repeat(depth)}${n.label ?? n.type}` }, ...flattenMenu(n.children ?? [], depth + 1)])
}
const menuOptions = computed(() => flattenMenu(menu.value))

function deptNodes(list: DepartmentTreeNode[]): TreeNode[] {
  return list.map((d) => ({ key: d.uuid, label: `${d.name} (${d.code})`, children: deptNodes(d.children ?? []) }))
}

async function analyse(): Promise<void> {
  loading.value = true
  failure.value = null
  analysis.value = null
  confirmBlocking.value = confirmDestructive.value = false
  typed.value = ''
  try {
    if (!(await builder.flush())) {
      failure.value = t('builder.publish.save_first')
      return
    }
    analysis.value = await builderApi.impact(builder.formUuid)
  } catch (e) {
    failure.value = e instanceof ApiError ? e.message : String(e)
  } finally {
    loading.value = false
  }
}

// Status mapping: records in statuses this version removes move to a chosen status.
const mappingTargets = ref<{ uuid: string; key: string; name: string }[]>([])
const removedWithRecords = computed(() => (impact.value?.workflow?.removed_statuses ?? []).filter((s) => s.records > 0))
watch(removedWithRecords, async (list) => {
  if (list.length && !mappingTargets.value.length) mappingTargets.value = (await workflowApi.mapping(builder.formUuid).catch(() => null))?.targets ?? []
})
async function chooseMapping(from: string, to: string): Promise<void> {
  const mappings = removedWithRecords.value.map((s) => ({ from: s.uuid, to: s.uuid === from ? to : s.to })).filter((m): m is { from: string; to: string } => !!m.to)
  try {
    await workflowApi.chooseMapping(builder.formUuid, mappings)
    await analyse()
  } catch (e) {
    failure.value = e instanceof ApiError ? e.message : String(e)
  }
}

async function loadPlacement(): Promise<void> {
  const form = builder.doc?.form
  label.value = { ...(form?.i18n?.name ?? {}) }
  icon.value = form?.icon ?? ''
  application.value = form?.application ?? null
  if (canMenu.value && !applications.value.length) applications.value = await referenceApi.applications().catch(() => [])
  if (canAllow.value && !roles.value.length) roles.value = await referenceApi.roles().catch(() => [])
  if (canAllow.value && session.can('system.manage_users') && !departments.value.length) departments.value = deptNodes(await referenceApi.departments().catch(() => []))
}

watch(application, async (app) => {
  menu.value = []
  parent.value = null
  if (app && canMenu.value) menu.value = await referenceApi.menu(app).catch(() => [])
})

watch(visible, async (open) => {
  if (open) {
    step.value = 'impact'
    plan.value = null
    changeNote.value = ''
    addToMenu.value = false
    allowedRoles.value = []
    allowedUsers.value = []
    allowedDepartments.value = {}
    await analyse()
  } else stopPolling()
})

watch(pickedUser, (u) => {
  if (u && !allowedUsers.value.some((x) => x.uuid === u.uuid)) allowedUsers.value.push(u)
  if (u) pickedUser.value = null
})

async function next(): Promise<void> {
  if (canMenu.value || canAllow.value) {
    await loadPlacement()
    step.value = 'placement'
  } else await publish()
}

async function publish(): Promise<void> {
  if (!analysis.value) return
  loading.value = true
  failure.value = null
  const allowedDepts = Object.entries(allowedDepartments.value)
    .filter(([, v]) => v.checked)
    .map(([k]) => k)
  const allowed = { roles: allowedRoles.value, users: allowedUsers.value.map((u) => u.uuid), departments: allowedDepts }
  const body: Record<string, unknown> = {
    impact_hash: analysis.value.impact_hash,
    confirm_blocking: confirmBlocking.value,
    confirm_destructive: confirmDestructive.value,
    typed_confirmation: typed.value || null,
    change_note: changeNote.value || null,
  }
  if (props.rollbackOf) body.rollback_of = props.rollbackOf
  if (addToMenu.value && application.value) {
    body.placement = { application: application.value, parent: parent.value, ...(sortOrder.value !== null ? { sort_order: sortOrder.value } : {}), icon: icon.value || null, label: label.value }
  }
  if (allowed.roles.length || allowed.users.length || allowed.departments.length) body.allowed = allowed
  try {
    const started = await builderApi.publish(builder.formUuid, body)
    step.value = 'running'
    await follow(started.plan)
  } catch (e) {
    if (e instanceof ApiError && e.code === 'impact_changed') {
      step.value = 'impact'
      await analyse()
      failure.value = t('builder.publish.impact_changed')
    } else failure.value = e instanceof ApiError ? (Object.values(e.fieldErrors)[0] ?? e.message) : String(e)
  } finally {
    loading.value = false
  }
}

const RUNNING = ['pending', 'locked', 'running', 'reversing']
async function follow(uuid: string): Promise<void> {
  stopPolling()
  try {
    plan.value = await builderApi.plan(uuid)
  } catch (e) {
    failure.value = e instanceof ApiError ? e.message : String(e)
  }
  if (plan.value && RUNNING.includes(plan.value.status)) {
    poll = setTimeout(() => void follow(uuid), 1500)
    return
  }
  step.value = 'done'
  if (plan.value?.status === 'applied') {
    emit('published', plan.value.to_version)
  }
  await builder.reload()
}
function stopPolling(): void {
  if (poll) clearTimeout(poll)
  poll = null
}
onBeforeUnmount(stopPolling)

const progress = computed(() => (plan.value && plan.value.steps_total ? Math.round((plan.value.steps_applied / plan.value.steps_total) * 100) : 0))
function seconds(ms: number): string {
  return (ms / 1000).toLocaleString(session.locale, { maximumFractionDigits: 1 })
}
const dependents = computed(() => Object.entries(impact.value?.dependents ?? {}))
</script>

<template>
  <Dialog
    v-model:visible="visible"
    modal
    :header="rollbackOf ? t('builder.publish.rollback_title', { n: rollbackOf }) : t('builder.publish.title')"
    :style="{ width: '56rem' }"
    :breakpoints="{ '900px': '95vw' }"
    :closable="step !== 'running'"
  >
    <div class="flex flex-col gap-3" data-testid="publish-dialog">
      <ol class="flex gap-2 text-xs" :aria-label="t('builder.publish.steps')">
        <li
          v-for="(s, i) in ['impact', 'placement', 'running'] as const"
          :key="s"
          :class="['flex items-center gap-1', step === s || (step === 'done' && s === 'running') ? 'font-semibold text-primary' : 'text-muted-color']"
        >
          <span class="rounded-full border w-5 h-5 flex items-center justify-center">{{ i + 1 }}</span
          >{{ t(`builder.publish.step_${s}`) }}
        </li>
      </ol>
      <Message v-if="failure" severity="error">{{ failure }}</Message>

      <!-- 1. Impact analysis -->
      <template v-if="step === 'impact'">
        <div v-if="loading" class="flex justify-center p-6"><ProgressSpinner /></div>
        <template v-else-if="impact && analysis">
          <div class="grid grid-cols-2 sm:grid-cols-4 gap-2">
            <div class="rounded border border-line p-2">
              <div class="text-xs text-muted-color">{{ t('builder.publish.version') }}</div>
              <div class="text-lg font-semibold">{{ analysis.version }}</div>
            </div>
            <div class="rounded border border-line p-2">
              <div class="text-xs text-muted-color">{{ t('builder.publish.records') }}</div>
              <div class="text-lg font-semibold">{{ impact.records.count.toLocaleString(session.locale) }}</div>
            </div>
            <div class="rounded border border-line p-2">
              <div class="text-xs text-muted-color">{{ t('builder.publish.change_class') }}</div>
              <div :class="['font-semibold', impact.schema.change_class === 'destructive' ? 'text-danger' : '']">{{ t(`builder.change_class.${impact.schema.change_class}`) }}</div>
            </div>
            <div class="rounded border border-line p-2">
              <div class="text-xs text-muted-color">{{ t('builder.publish.estimated') }}</div>
              <div class="font-semibold">{{ t('builder.publish.seconds', { n: seconds(impact.schema.estimated_ms) }) }}</div>
            </div>
          </div>

          <p class="text-sm">
            {{ t('builder.publish.diff_summary', { added: impact.diff.added ?? 0, removed: impact.diff.removed ?? 0, changed: impact.diff.changed ?? 0 }) }}
            <Button size="small" text :label="showDiff ? t('builder.publish.hide_diff') : t('builder.publish.show_diff')" @click="showDiff = !showDiff" />
          </p>
          <DiffView v-if="showDiff" :diff="analysis.diff" />

          <Message v-if="blocked" severity="error">
            <div class="font-semibold">{{ t('builder.publish.blocking') }}</div>
            <ul class="list-disc ps-5 text-sm">
              <li v-for="(b, i) in impact.blocking" :key="i">
                <span dir="auto">{{ blockingText(b) }}</span>
              </li>
            </ul>
          </Message>

          <section v-if="removedWithRecords.length" class="flex flex-col gap-2 text-sm" data-testid="status-mapping">
            <h3 class="font-semibold">{{ t('builder.publish.status_mapping') }}</h3>
            <p class="text-muted-color">{{ t('builder.publish.status_mapping_hint') }}</p>
            <div v-for="s in removedWithRecords" :key="s.uuid" class="flex flex-wrap items-center gap-2">
              <label :for="`map-${s.uuid}`" class="min-w-40">{{ t('builder.publish.status_records', { status: s.name, n: s.records }) }}</label>
              <Select
                :model-value="s.to"
                :input-id="`map-${s.uuid}`"
                :options="mappingTargets"
                option-label="name"
                option-value="uuid"
                size="small"
                :placeholder="t('builder.publish.status_target')"
                :invalid="!s.to"
                @update:model-value="(v: string) => chooseMapping(s.uuid, v)"
              />
            </div>
          </section>
          <p v-if="impact.workflow?.unassigned_records" class="text-sm">{{ t('builder.publish.unassigned_records', { n: impact.workflow.unassigned_records }) }}</p>

          <section class="flex flex-col gap-1 text-sm">
            <h3 class="font-semibold">{{ t('builder.publish.affected') }}</h3>
            <ul class="list-disc ps-5">
              <li v-if="impact.removed_fields.length">
                {{ t('builder.publish.removed_fields') }}: <span class="ltr-value">{{ impact.removed_fields.join(', ') }}</span>
              </li>
              <li v-for="f in impact.records.failing_required" :key="f.field">{{ t('builder.publish.failing_required', { key: f.key, n: f.records }) }}</li>
              <li v-for="c in impact.records.type_conflicts" :key="c.column">{{ t('builder.publish.type_conflict', { column: c.column, to: c.to, n: c.records.length }) }}</li>
              <li v-if="impact.permissions.orphaned_access_rules">{{ t('builder.publish.orphaned_rules', { n: impact.permissions.orphaned_access_rules }) }}</li>
              <li v-for="l in impact.linked_forms" :key="`${l.form}-${l.relation}`">
                {{ t('builder.publish.linked_form', { form: l.form_key ?? l.form, relation: l.relation }) }}
                <span v-if="l.broken" class="text-danger">{{ t('builder.publish.broken') }}</span>
              </li>
              <li>{{ t('builder.publish.menus', { n: impact.menus }) }}</li>
              <li v-for="[k, n] in dependents" :key="k">{{ t(`builder.publish.dependent_${k}`) }}: {{ n }}</li>
              <li v-if="impact.lock_set?.length">
                {{ t('builder.publish.lock_set') }}: <span class="ltr-value">{{ impact.lock_set.map((f) => f.key).join(', ') }}</span>
              </li>
            </ul>
          </section>

          <section class="flex flex-col gap-1 text-sm">
            <h3 class="font-semibold">{{ t('builder.publish.schema') }}</h3>
            <p>{{ t('builder.publish.schema_steps', { steps: impact.schema.steps, online: impact.schema.online }) }}</p>
            <Message v-if="impact.schema.blocking.length" severity="warn" size="small">
              <div>{{ t('builder.publish.blocking_steps') }}</div>
              <ul class="list-disc ps-5">
                <li v-for="b in impact.schema.blocking" :key="b.sequence">
                  <span class="ltr-value">{{ b.operation }} · {{ b.table }}</span> —
                  {{ t('builder.publish.rows_seconds', { rows: b.rows.toLocaleString(session.locale), s: seconds(b.estimated_ms) }) }}
                </li>
              </ul>
            </Message>
            <Message v-if="impact.schema.backup" severity="info" size="small">
              {{
                t('builder.publish.backup', {
                  tables: impact.schema.backup.tables.join(', '),
                  rows: impact.schema.backup.rows.toLocaleString(session.locale),
                  days: impact.schema.backup.retention_days,
                })
              }}
            </Message>
            <Button
              v-if="impact.schema.plan.length"
              size="small"
              text
              class="self-start"
              :icon="showSql ? 'pi pi-chevron-up' : 'pi pi-chevron-down'"
              :label="t('builder.publish.sql')"
              @click="showSql = !showSql"
            />
            <ol v-if="showSql" class="flex flex-col gap-2">
              <li v-for="s in impact.schema.plan" :key="s.sequence" class="rounded border border-line p-2">
                <div class="flex flex-wrap gap-2 text-xs mb-1">
                  <span class="font-semibold ltr-value">{{ s.sequence }}. {{ s.operation }} · {{ s.table_name }}</span>
                  <span v-if="s.is_destructive" class="text-danger">{{ t('builder.publish.destructive') }}</span>
                  <span>{{ s.is_online ? t('builder.publish.online') : t('builder.publish.offline') }}</span>
                  <span v-if="s.lock_level">{{ t('builder.publish.lock_level') }}: {{ s.lock_level }}</span>
                  <span v-if="s.rows !== undefined">{{ t('builder.publish.row_count', { n: s.rows }) }}</span>
                </div>
                <pre class="text-xs overflow-auto bg-subtle p-2 rounded max-h-48" dir="ltr">{{ s.sql_preview }}</pre>
              </li>
            </ol>
          </section>

          <section v-if="needsBlockingConfirm || needsTyped" class="flex flex-col gap-2 rounded border border-warning p-2">
            <label v-if="needsBlockingConfirm" class="flex items-start gap-2 text-sm"
              ><Checkbox v-model="confirmBlocking" binary data-testid="confirm-blocking" />{{ t('builder.publish.confirm_blocking') }}</label
            >
            <template v-if="needsTyped">
              <label class="flex items-start gap-2 text-sm"><Checkbox v-model="confirmDestructive" binary data-testid="confirm-destructive" />{{ t('builder.publish.confirm_destructive') }}</label>
              <label class="field"
                ><span>{{ t('builder.publish.type_key', { key: formKey }) }}</span>
                <InputText v-model="typed" size="small" class="ltr-value font-mono" autocomplete="off" data-testid="typed-confirmation" />
              </label>
            </template>
          </section>
          <label class="field"
            ><span>{{ t('builder.publish.change_note') }}</span>
            <Textarea v-model="changeNote" rows="2" maxlength="2000" auto-resize />
          </label>
        </template>
        <div class="flex justify-end gap-2">
          <Button severity="secondary" :label="t('common.cancel')" @click="visible = false" />
          <Button severity="secondary" icon="pi pi-refresh" :label="t('builder.publish.reanalyse')" :disabled="loading" @click="analyse" />
          <Button :label="t('common.continue')" icon="pi pi-arrow-right" icon-pos="right" :disabled="!canPublish || loading" data-testid="publish-continue" @click="next" />
        </div>
      </template>

      <!-- 2. Placement and access -->
      <template v-else-if="step === 'placement'">
        <section v-if="canMenu" class="flex flex-col gap-2">
          <label class="flex items-center gap-2 text-sm font-medium"><ToggleSwitch v-model="addToMenu" data-testid="add-to-menu" />{{ t('builder.publish.add_to_menu') }}</label>
          <template v-if="addToMenu">
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
              <label class="field"
                ><span>{{ t('builder.form_props.application') }}</span>
                <Select v-model="application" :options="applications" option-label="name" option-value="uuid" size="small" />
              </label>
              <label class="field"
                ><span>{{ t('builder.publish.parent_menu') }}</span>
                <Select v-model="parent" :options="menuOptions" option-label="label" option-value="value" show-clear size="small" :placeholder="t('builder.publish.top_level')" />
              </label>
              <label class="field"
                ><span>{{ t('builder.publish.menu_order') }}</span>
                <InputNumber v-model="sortOrder" :min="0" :max="100000" size="small" :placeholder="t('builder.publish.at_end')" />
              </label>
              <label class="field"
                ><span>{{ t('builder.icon') }}</span>
                <InputText v-model="icon" size="small" class="ltr-value" placeholder="pi pi-file" />
              </label>
            </div>
            <I18nInput v-model="label" :label="t('builder.publish.menu_label')" :maxlength="255" />
          </template>
        </section>
        <section v-if="canAllow" class="flex flex-col gap-2">
          <h3 class="text-sm font-semibold">{{ t('builder.publish.allowed') }}</h3>
          <p class="text-xs text-muted-color">{{ t('builder.publish.allowed_hint') }}</p>
          <label class="field"
            ><span>{{ t('builder.publish.allowed_roles') }}</span>
            <MultiSelect v-model="allowedRoles" :options="roles" option-label="name" option-value="uuid" display="chip" filter size="small" />
          </label>
          <div v-if="session.can('system.manage_users')" class="field">
            <span class="text-sm font-medium">{{ t('builder.publish.allowed_users') }}</span>
            <UserPicker v-model="pickedUser" />
            <ul class="flex flex-wrap gap-1">
              <li v-for="u in allowedUsers" :key="u.uuid" class="rounded-full bg-subtle px-2 py-0.5 text-xs flex items-center gap-1">
                {{ u.name }}
                <button type="button" :aria-label="t('builder.remove')" @click="allowedUsers = allowedUsers.filter((x) => x.uuid !== u.uuid)"><i class="pi pi-times text-xs" /></button>
              </li>
            </ul>
          </div>
          <label v-if="departments.length" class="field"
            ><span>{{ t('builder.publish.allowed_departments') }}</span>
            <TreeSelect v-model="allowedDepartments" :options="departments" selection-mode="checkbox" display="chip" size="small" />
          </label>
        </section>
        <div class="flex justify-end gap-2">
          <Button severity="secondary" :label="t('common.back')" @click="step = 'impact'" />
          <Button icon="pi pi-send" :label="t('builder.publish.publish')" :loading="loading" :disabled="!placementValid" data-testid="publish-confirm" @click="publish" />
        </div>
      </template>

      <!-- 3. Progress and result -->
      <template v-else>
        <div v-if="plan" class="flex flex-col gap-2">
          <p class="text-sm">{{ t(`builder.plan_status.${plan.status}`) }}</p>
          <ProgressBar v-if="step === 'running'" :value="progress" />
          <Message v-if="plan.queued_behind" severity="info" size="small">{{ t('builder.publish.queued', { form: plan.queued_behind.form, user: plan.queued_behind.user }) }}</Message>
          <ol class="text-xs flex flex-col gap-0.5">
            <li v-for="s in plan.steps" :key="s.sequence" class="flex gap-2">
              <span class="ltr-value">{{ s.sequence }}. {{ s.operation }} · {{ s.table }}</span>
              <span :class="s.status === 'failed' || s.status === 'reverse_failed' ? 'text-danger' : 'text-muted-color'">{{ t(`builder.step_status.${s.status}`) }}</span>
              <span v-if="s.error" class="text-danger">{{ s.error }}</span>
            </li>
          </ol>
          <Message v-if="step === 'done' && plan.status === 'applied'" severity="success">{{ t('builder.publish.applied', { n: plan.to_version }) }}</Message>
          <Message v-else-if="step === 'done' && plan.status === 'reversed'" severity="warn">{{ t('builder.publish.reversed', { error: plan.error ?? '' }) }}</Message>
          <Message v-else-if="step === 'done'" severity="error">
            {{ t('builder.publish.inconsistent') }}
            <RouterLink :to="`/admin/schema/plans/${plan.uuid}`" class="underline">{{ t('builder.publish.open_repair') }}</RouterLink>
          </Message>
          <ul v-if="step === 'done' && plan.snapshots.length" class="text-xs text-muted-color">
            <li v-for="s in plan.snapshots" :key="s.uuid">{{ t('builder.publish.snapshot', { kind: s.kind, path: s.path, until: new Date(s.expires_at).toLocaleDateString(session.locale) }) }}</li>
          </ul>
        </div>
        <div v-else class="flex justify-center p-6"><ProgressSpinner /></div>
        <div class="flex justify-end">
          <Button :label="t('builder.close')" :disabled="step === 'running'" @click="visible = false" />
        </div>
      </template>
    </div>
  </Dialog>
</template>
