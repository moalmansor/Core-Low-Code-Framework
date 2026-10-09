<script setup lang="ts">
import Button from 'primevue/button'
import Column from 'primevue/column'
import DataTable, { type DataTablePageEvent } from 'primevue/datatable'
import Message from 'primevue/message'
import ProgressBar from 'primevue/progressbar'
import Select from 'primevue/select'
import Tag from 'primevue/tag'
import { useConfirm } from 'primevue/useconfirm'
import { useToast } from 'primevue/usetoast'
import { computed, onBeforeUnmount, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRoute, useRouter } from 'vue-router'
import { get, send } from '@/api/http'
import { useSession } from '@/stores/session'
import { errorText, formatBytes, formatDateTime } from './shared'

type PlanStatus = 'pending' | 'locked' | 'running' | 'applied' | 'failed' | 'reversing' | 'reversed' | 'inconsistent'
interface PlanRow {
  uuid: string
  form_key: string | null
  purpose: string
  status: PlanStatus
  to_version: number
  steps_total: number
  steps_applied: number
  created_at: string | null
  finished_at: string | null
  error: string | null
}
interface Step {
  sequence: number
  operation: string
  table: string
  status: 'pending' | 'applied' | 'failed' | 'reversed' | 'reverse_failed' | 'skipped'
  is_destructive: boolean
  is_online: boolean
  estimated_ms: number | null
  duration_ms: number | null
  error: string | null
  sql_preview: string | null
}
interface Snapshot {
  uuid: string
  kind: 'metadata' | 'physical_schema' | 'data_backup'
  disk: string
  path: string
  size_bytes: number
  tables: Record<string, unknown> | unknown[]
  expires_at: string
  restored_at: string | null
}
interface PlanDetail {
  uuid: string
  form: { uuid: string; key: string; state: string } | null
  purpose: string
  status: PlanStatus
  to_version: number
  steps_total: number
  steps_applied: number
  error: string | null
  queued_behind: { form: string | null; user: string | null; since: string | null } | null
  steps: Step[]
  snapshots: Snapshot[]
}

const { t } = useI18n()
const toast = useToast()
const confirm = useConfirm()
const route = useRoute()
const router = useRouter()
const session = useSession()

const STATUSES: PlanStatus[] = ['pending', 'locked', 'running', 'applied', 'failed', 'reversing', 'reversed', 'inconsistent']
const ACTIVE: PlanStatus[] = ['pending', 'locked', 'running', 'reversing']
const REPAIRABLE: PlanStatus[] = ['failed', 'reversed', 'inconsistent']
const statusSeverity: Record<string, string> = {
  pending: 'secondary',
  locked: 'info',
  running: 'info',
  applied: 'success',
  failed: 'danger',
  reversing: 'warn',
  reversed: 'warn',
  inconsistent: 'danger',
  reverse_failed: 'danger',
  skipped: 'secondary',
}
const statusOptions = computed(() => STATUSES.map((s) => ({ value: s, label: t(`building.plans.status.${s}`) })))

const rows = ref<PlanRow[]>([])
const total = ref(0)
const page = ref(1)
const status = ref<PlanStatus | null>(null)
const formFilter = computed(() => (typeof route.query.form === 'string' ? route.query.form : null))
const loading = ref(false)
const planUuid = computed(() => (route.params.plan ? String(route.params.plan) : null))
const detail = ref<PlanDetail | null>(null)
const busy = ref(false)
let timer: ReturnType<typeof setTimeout> | undefined

async function loadList(): Promise<void> {
  loading.value = true
  try {
    const params: Record<string, unknown> = { page: page.value }
    if (status.value) params.status = status.value
    if (formFilter.value) params.form = formFilter.value
    const res = await get<{ data: PlanRow[]; meta: { total: number } }>('/migration-plans', params)
    rows.value = res.data
    total.value = res.meta.total
  } catch (e) {
    toast.add({ severity: 'error', summary: errorText(e, t('building.load_failed')), life: 6000 })
  } finally {
    loading.value = false
  }
}
watch([status, formFilter], () => {
  page.value = 1
  loadList()
})
function onPage(e: DataTablePageEvent): void {
  page.value = e.page + 1
  loadList()
}

async function loadDetail(): Promise<void> {
  clearTimeout(timer)
  if (!planUuid.value) {
    detail.value = null
    return
  }
  try {
    detail.value = (await get<{ data: PlanDetail }>(`/migration-plans/${planUuid.value}`)).data
    // Keep following a plan that is still executing.
    if (ACTIVE.includes(detail.value.status)) timer = setTimeout(loadDetail, 3000)
  } catch (e) {
    toast.add({ severity: 'error', summary: errorText(e, t('building.load_failed')), life: 6000 })
  }
}
watch(planUuid, loadDetail, { immediate: true })
loadList()
onBeforeUnmount(() => clearTimeout(timer))

const open = (uuid: string) => router.push({ name: 'admin.schema.plans', params: { plan: uuid }, query: route.query })
const back = () => router.push({ name: 'admin.schema.plans', query: route.query })
const canRepair = computed(() => session.can('system.repair_data') && !!detail.value && REPAIRABLE.includes(detail.value.status))
const dataBackup = computed(() => detail.value?.snapshots.find((s) => s.kind === 'data_backup') ?? null)
const progress = computed(() => (detail.value && detail.value.steps_total ? Math.round((detail.value.steps_applied / detail.value.steps_total) * 100) : 0))
const sqlOpen = ref<Record<number, boolean>>({})
const tableNames = (s: Snapshot) => (Array.isArray(s.tables) ? s.tables.map(String) : Object.entries(s.tables).map(([k, v]) => (typeof v === 'number' ? `${k} (${v})` : k)))

type RepairAction = 'retry' | 'reverse' | 'reconcile-step' | 'restore-snapshot'
function repair(action: RepairAction, step?: number): void {
  const key = action.replace('-', '_')
  confirm.require({
    header: t(`building.plans.repair.${key}`),
    message: t(`building.plans.repair.${key}_confirm`, { step: step ?? '' }),
    icon: 'pi pi-exclamation-triangle',
    acceptProps: { label: t(`building.plans.repair.${key}`), severity: action === 'restore-snapshot' ? 'danger' : 'primary' },
    rejectProps: { label: t('common.cancel'), severity: 'secondary' },
    accept: async () => {
      busy.value = true
      try {
        detail.value = (await send<{ data: PlanDetail }>('post', `/migration-plans/${planUuid.value}/${action}`, step ? { step } : {})).data
        toast.add({ severity: 'success', summary: t('building.plans.repair_done', { status: t(`building.plans.status.${detail.value.status}`) }), life: 5000 })
        await loadList()
        if (ACTIVE.includes(detail.value.status)) timer = setTimeout(loadDetail, 3000)
      } catch (e) {
        toast.add({ severity: 'error', summary: errorText(e, t('building.save_failed')), life: 10000 })
        await loadDetail()
      } finally {
        busy.value = false
      }
    },
  })
}
</script>

<template>
  <div class="flex flex-wrap items-center gap-3 mb-4">
    <RouterLink :to="{ name: 'admin.schema' }" class="text-sm"><i class="pi pi-arrow-left rtl:rotate-180 me-1" />{{ t('admin.area.schema') }}</RouterLink>
    <h1 class="page-title !mb-0 flex-1">{{ t('building.plans.title') }}</h1>
  </div>

  <div class="flex flex-col xl:flex-row gap-4">
    <section class="xl:w-[34rem] shrink-0">
      <div class="flex flex-wrap items-center gap-2 mb-3">
        <Select v-model="status" :options="statusOptions" option-label="label" option-value="value" show-clear :placeholder="t('building.status')" class="w-48" />
        <Tag v-if="formFilter" severity="secondary">
          <span>{{ t('building.plans.one_form') }}</span>
          <button type="button" class="ms-1 cursor-pointer" :aria-label="t('common.remove')" @click="router.push({ name: 'admin.schema.plans', params: route.params })">
            <i class="pi pi-times text-xs" />
          </button>
        </Tag>
        <Button icon="pi pi-refresh" text severity="secondary" :aria-label="t('building.refresh')" @click="loadList" />
      </div>
      <DataTable
        :value="rows"
        lazy
        paginator
        :rows="25"
        :total-records="total"
        :loading="loading"
        data-key="uuid"
        size="small"
        selection-mode="single"
        :selection="rows.find((r) => r.uuid === planUuid)"
        data-testid="plans-table"
        @page="onPage"
        @row-click="(e) => open(e.data.uuid)"
      >
        <template #empty>{{ t('building.plans.empty') }}</template>
        <Column :header="t('building.plans.form')">
          <template #body="{ data }"
            ><span class="ltr-value">{{ data.form_key ?? '—' }}</span> <span class="text-xs text-muted-color">v{{ data.to_version }}</span></template
          >
        </Column>
        <Column :header="t('building.plans.purpose')">
          <template #body="{ data }">{{ t(`building.plans.purpose_kind.${data.purpose}`) }}</template>
        </Column>
        <Column :header="t('building.status')">
          <template #body="{ data }"><Tag :severity="statusSeverity[data.status]" :value="t(`building.plans.status.${data.status}`)" /></template>
        </Column>
        <Column :header="t('building.plans.steps')">
          <template #body="{ data }">{{ data.steps_applied }}/{{ data.steps_total }}</template>
        </Column>
        <Column :header="t('building.plans.created')">
          <template #body="{ data }"
            ><span class="text-xs">{{ formatDateTime(data.created_at, session.locale) }}</span></template
          >
        </Column>
      </DataTable>
    </section>

    <section class="flex-1 min-w-0">
      <Message v-if="!planUuid" severity="info">{{ t('building.plans.pick') }}</Message>
      <div v-else-if="detail" class="flex flex-col gap-4" data-testid="plan-detail">
        <div class="flex flex-wrap items-center gap-2">
          <Button icon="pi pi-times" text rounded class="xl:hidden" :aria-label="t('common.back')" @click="back" />
          <h2 class="text-lg font-semibold">
            <span class="ltr-value">{{ detail.form?.key ?? '—' }}</span> · {{ t(`building.plans.purpose_kind.${detail.purpose}`) }} · v{{ detail.to_version }}
          </h2>
          <Tag :severity="statusSeverity[detail.status]" :value="t(`building.plans.status.${detail.status}`)" />
          <Tag v-if="detail.form?.state === 'schema_inconsistent'" severity="danger" :value="t('building.form_state.schema_inconsistent')" />
        </div>
        <ProgressBar :value="progress" class="h-2" :show-value="false" />
        <p class="text-sm text-muted-color">{{ t('building.plans.progress', { applied: detail.steps_applied, total: detail.steps_total }) }}</p>
        <Message v-if="detail.queued_behind" severity="warn">
          {{ t('building.plans.queued', { form: detail.queued_behind.form ?? '—', user: detail.queued_behind.user ?? '—', since: formatDateTime(detail.queued_behind.since, session.locale) }) }}
        </Message>
        <Message v-if="detail.error" severity="error"
          ><span class="ltr-value whitespace-pre-wrap">{{ detail.error }}</span></Message
        >
        <Message v-if="detail.form?.state === 'schema_inconsistent'" severity="warn">{{ t('building.plans.inconsistent_hint') }}</Message>

        <div v-if="canRepair" class="rounded-xl border border-warning p-4 flex flex-col gap-3" data-testid="repair-panel">
          <h3 class="font-semibold"><i class="pi pi-wrench me-1" />{{ t('building.plans.repair_title') }}</h3>
          <p class="text-sm">{{ t('building.plans.repair_hint') }}</p>
          <div class="flex flex-wrap gap-2">
            <Button v-if="detail.status !== 'reversed'" icon="pi pi-replay" :label="t('building.plans.repair.retry')" :loading="busy" data-testid="repair-retry" @click="repair('retry')" />
            <Button icon="pi pi-undo" severity="secondary" :label="t('building.plans.repair.reverse')" :loading="busy" data-testid="repair-reverse" @click="repair('reverse')" />
            <Button
              v-if="dataBackup && !dataBackup.restored_at"
              icon="pi pi-history"
              severity="danger"
              :label="t('building.plans.repair.restore_snapshot')"
              :loading="busy"
              data-testid="repair-restore"
              @click="repair('restore-snapshot')"
            />
          </div>
          <p class="text-xs text-muted-color">{{ t('building.plans.reconcile_step_hint') }}</p>
        </div>
        <Message v-else-if="detail && REPAIRABLE.includes(detail.status) && !session.can('system.repair_data')" severity="secondary" size="small">{{ t('building.plans.repair_permission') }}</Message>

        <section>
          <h3 class="font-semibold mb-2">{{ t('building.plans.steps') }}</h3>
          <ol class="flex flex-col gap-2">
            <li v-for="s in detail.steps" :key="s.sequence" class="rounded-lg border border-line p-2" :data-testid="`step-${s.sequence}`">
              <div class="flex flex-wrap items-center gap-2">
                <span class="font-mono text-xs w-6 text-center">{{ s.sequence }}</span>
                <span class="font-medium">{{ t(`building.plans.op.${s.operation}`) }}</span>
                <span class="ltr-value text-sm text-muted-color">{{ s.table }}</span>
                <Tag :severity="statusSeverity[s.status]" :value="t(`building.plans.step_status.${s.status}`)" />
                <Tag v-if="s.is_destructive" severity="danger" :value="t('building.plans.destructive')" />
                <Tag v-if="s.is_online" severity="info" :value="t('building.plans.online')" />
                <span class="text-xs text-muted-color ms-auto">
                  <template v-if="s.duration_ms !== null">{{ t('building.plans.took', { ms: s.duration_ms }) }}</template>
                  <template v-else-if="s.estimated_ms !== null">{{ t('building.plans.estimated', { ms: s.estimated_ms }) }}</template>
                </span>
                <Button
                  v-if="s.sql_preview"
                  size="small"
                  text
                  :label="sqlOpen[s.sequence] ? t('building.plans.hide_sql') : t('building.plans.show_sql')"
                  @click="sqlOpen[s.sequence] = !sqlOpen[s.sequence]"
                />
                <Button
                  v-if="canRepair && (s.status === 'failed' || s.status === 'reverse_failed')"
                  size="small"
                  severity="warn"
                  :label="t('building.plans.repair.reconcile_step')"
                  :data-testid="`reconcile-step-${s.sequence}`"
                  @click="repair('reconcile-step', s.sequence)"
                />
              </div>
              <p v-if="s.error" class="text-sm text-danger mt-1 ltr-value whitespace-pre-wrap">{{ s.error }}</p>
              <pre v-if="sqlOpen[s.sequence] && s.sql_preview" class="ltr-value text-xs mt-2 p-2 rounded bg-subtle overflow-auto whitespace-pre-wrap">{{ s.sql_preview }}</pre>
            </li>
            <li v-if="!detail.steps.length" class="text-muted-color">{{ t('building.plans.no_steps') }}</li>
          </ol>
        </section>

        <section>
          <h3 class="font-semibold mb-2">{{ t('building.plans.snapshots') }}</h3>
          <p v-if="!detail.snapshots.length" class="text-sm text-muted-color">{{ t('building.plans.no_snapshots') }}</p>
          <div v-for="s in detail.snapshots" :key="s.uuid" class="rounded-lg border border-line p-2 mb-2 text-sm">
            <div class="flex flex-wrap items-center gap-2">
              <span class="font-medium">{{ t(`building.plans.snapshot_kind.${s.kind}`) }}</span>
              <span class="text-muted-color">{{ formatBytes(s.size_bytes) }}</span>
              <Tag v-if="s.restored_at" severity="success" :value="t('building.plans.restored_at', { at: formatDateTime(s.restored_at, session.locale) })" />
            </div>
            <div class="text-xs mt-1">
              {{ t('building.plans.location') }}: <span class="ltr-value">{{ s.disk }}:{{ s.path }}</span>
            </div>
            <div class="text-xs">{{ t('building.plans.kept_until', { at: formatDateTime(s.expires_at, session.locale) }) }}</div>
            <div class="text-xs text-muted-color ltr-value">{{ tableNames(s).join(', ') }}</div>
          </div>
        </section>
      </div>
    </section>
  </div>
</template>
