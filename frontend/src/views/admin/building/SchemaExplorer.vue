<script setup lang="ts">
import Button from 'primevue/button'
import Column from 'primevue/column'
import DataTable from 'primevue/datatable'
import Dialog from 'primevue/dialog'
import IconField from 'primevue/iconfield'
import InputIcon from 'primevue/inputicon'
import InputText from 'primevue/inputtext'
import Message from 'primevue/message'
import MultiSelect from 'primevue/multiselect'
import Select from 'primevue/select'
import Tab from 'primevue/tab'
import TabList from 'primevue/tablist'
import TabPanel from 'primevue/tabpanel'
import TabPanels from 'primevue/tabpanels'
import Tabs from 'primevue/tabs'
import Tag from 'primevue/tag'
import ToggleSwitch from 'primevue/toggleswitch'
import { useToast } from 'primevue/usetoast'
import { computed, onMounted, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRoute, useRouter } from 'vue-router'
import { get, send } from '@/api/http'
import { useSession } from '@/stores/session'
import ErdDiagram from './ErdDiagram.vue'
import type { ErdEdgeApi, ErdNodeApi } from './erdLayout'
import { errorText, formatDateTime } from './shared'

interface TableRow {
  name: string
  role: string
  form: string
  form_key: string
  form_name: string
  kind: 'form' | 'collection'
  columns: number
  exists: boolean
  rows: number | null
}
interface TableDetail {
  name: string
  form: string | null
  columns: { name: string; type: string; nullable: boolean; default: unknown; field: string | null; system: boolean; archived: boolean }[]
  indexes: { name: string; columns: string[]; unique: boolean; primary: boolean }[]
  foreign_keys: { name: string | null; columns: string[]; foreign_table: string; foreign_columns: string[]; on_delete: string | null }[]
  rows: number
}
interface Difference {
  form?: string
  form_key?: string | null
  table?: string
  kind: string
  column?: string
  name?: string
  expected?: unknown
  actual?: unknown
  message?: string
}
interface Report {
  id: number
  form: string | null
  trigger: string
  engine: string
  status: 'running' | 'clean' | 'drift' | 'error'
  difference_count: number
  differences: Difference[]
  started_at: string
  finished_at: string | null
}

const { t } = useI18n()
const toast = useToast()
const route = useRoute()
const router = useRouter()
const session = useSession()
const TABS = ['tables', 'erd', 'reconciliation']
const tab = ref(TABS.includes(String(route.query.tab)) ? String(route.query.tab) : 'tables')
watch(tab, (v) => router.replace({ query: { ...route.query, tab: v } }))

// Tables
const tables = ref<TableRow[]>([])
const loadingTables = ref(false)
const search = ref('')
const filteredTables = computed(() => {
  const q = search.value.trim().toLowerCase()
  return q ? tables.value.filter((r) => `${r.name} ${r.form_key} ${r.form_name}`.toLowerCase().includes(q)) : tables.value
})
const formOptions = computed(() => {
  const seen = new Map<string, string>()
  for (const r of tables.value) seen.set(r.form, `${r.form_name} (${r.form_key})`)
  return [...seen.entries()].map(([value, label]) => ({ value, label })).sort((a, b) => a.label.localeCompare(b.label))
})
async function loadTables(): Promise<void> {
  loadingTables.value = true
  try {
    tables.value = (await get<{ data: TableRow[] }>('/schema/tables')).data
  } catch (e) {
    toast.add({ severity: 'error', summary: errorText(e, t('building.load_failed')), life: 6000 })
  } finally {
    loadingTables.value = false
  }
}
onMounted(loadTables)

const detail = ref<TableDetail | null>(null)
async function openTable(name: string): Promise<void> {
  try {
    detail.value = (await get<{ data: TableDetail }>(`/schema/tables/${encodeURIComponent(name)}`)).data
  } catch (e) {
    toast.add({ severity: 'error', summary: errorText(e, t('building.load_failed')), life: 6000 })
  }
}
const formatDefault = (v: unknown) => (v === null || v === undefined ? '—' : String(v))

// ERD
const erdForms = ref<string[]>([])
const erd = ref<{ nodes: ErdNodeApi[]; edges: ErdEdgeApi[] } | null>(null)
const showSystem = ref(false)
const loadingErd = ref(false)
async function loadErd(): Promise<void> {
  loadingErd.value = true
  try {
    erd.value = (await get<{ data: { nodes: ErdNodeApi[]; edges: ErdEdgeApi[] } }>('/schema/erd', erdForms.value.length ? { forms: erdForms.value } : {})).data
  } catch (e) {
    toast.add({ severity: 'error', summary: errorText(e, t('building.load_failed')), life: 6000 })
  } finally {
    loadingErd.value = false
  }
}
watch(
  tab,
  (v) => {
    if (v === 'erd' && !erd.value) loadErd()
    if (v === 'reconciliation' && reports.value === null) loadReports()
  },
  { immediate: true },
)
watch(erdForms, loadErd)

// Reconciliation
const reports = ref<Report[] | null>(null)
const reconcileForm = ref<string | null>(null)
const running = ref(false)
const expanded = ref<Record<number, boolean>>({})
async function loadReports(): Promise<void> {
  try {
    reports.value = (await get<{ data: Report[] }>('/schema/reconciliation-reports')).data
  } catch (e) {
    reports.value = []
    toast.add({ severity: 'error', summary: errorText(e, t('building.load_failed')), life: 6000 })
  }
}
async function reconcileNow(): Promise<void> {
  running.value = true
  try {
    const report = (await send<{ data: Report }>('post', '/schema/reconcile', reconcileForm.value ? { form: reconcileForm.value } : {})).data
    toast.add({
      severity: report.status === 'clean' ? 'success' : 'warn',
      summary: report.status === 'clean' ? t('building.schema.reconcile_clean') : t('building.schema.reconcile_drift', { n: report.difference_count }),
      life: 5000,
    })
    await loadReports()
    expanded.value = { [report.id]: true }
  } catch (e) {
    toast.add({ severity: 'error', summary: errorText(e, t('building.save_failed')), life: 8000 })
  } finally {
    running.value = false
  }
}
/** Differences name the owning form by uuid; show its key when known. */
const formKeyOf = (uuid: string | undefined) => (uuid ? (tables.value.find((r) => r.form === uuid)?.form_key ?? uuid) : '—')
const reportSeverity: Record<string, string> = { clean: 'success', drift: 'warn', error: 'danger', running: 'info' }
const showValue = (v: unknown) => (v === null || v === undefined ? '—' : Array.isArray(v) ? v.join(', ') : String(v))
</script>

<template>
  <div class="flex flex-wrap items-center gap-3 mb-4">
    <h1 class="page-title !mb-0 flex-1">{{ t('admin.area.schema') }}</h1>
    <Button icon="pi pi-list-check" severity="secondary" :label="t('building.plans.title')" @click="router.push({ name: 'admin.schema.plans' })" />
  </div>

  <Tabs v-model:value="tab">
    <TabList>
      <Tab value="tables" data-testid="schema-tab-tables">{{ t('building.schema.tab_tables') }}</Tab>
      <Tab value="erd" data-testid="schema-tab-erd">{{ t('building.schema.tab_erd') }}</Tab>
      <Tab value="reconciliation" data-testid="schema-tab-reconciliation">{{ t('building.schema.tab_reconciliation') }}</Tab>
    </TabList>
    <TabPanels>
      <TabPanel value="tables">
        <div class="flex flex-wrap gap-2 mb-3">
          <IconField>
            <InputIcon class="pi pi-search" />
            <InputText v-model="search" :placeholder="t('common.search')" data-testid="schema-search" />
          </IconField>
          <Button icon="pi pi-refresh" severity="secondary" text :aria-label="t('building.refresh')" @click="loadTables" />
        </div>
        <DataTable
          :value="filteredTables"
          :loading="loadingTables"
          data-key="name"
          size="small"
          striped-rows
          paginator
          :rows="50"
          data-testid="schema-tables"
          @row-click="(e) => openTable(e.data.name)"
        >
          <template #empty>{{ t('building.schema.no_tables') }}</template>
          <Column :header="t('building.schema.table')" sortable field="name">
            <template #body="{ data }"
              ><span class="ltr-value font-medium cursor-pointer">{{ data.name }}</span></template
            >
          </Column>
          <Column :header="t('building.schema.role')">
            <template #body="{ data }">{{ t(`building.schema.table_role.${data.role}`) }}</template>
          </Column>
          <Column :header="t('building.schema.owner')" sortable field="form_name">
            <template #body="{ data }"
              >{{ data.form_name }} <span class="text-xs text-muted-color ltr-value">{{ data.form_key }}</span></template
            >
          </Column>
          <Column :header="t('building.kind_label')">
            <template #body="{ data }">{{ t(`building.kind.${data.kind}`) }}</template>
          </Column>
          <Column :header="t('building.schema.columns')" field="columns" sortable />
          <Column :header="t('building.schema.rows')" field="rows" sortable>
            <template #body="{ data }">{{ data.rows ?? '—' }}</template>
          </Column>
          <Column :header="t('building.status')">
            <template #body="{ data }">
              <Tag :severity="data.exists ? 'success' : 'danger'" :value="data.exists ? t('building.schema.present') : t('building.schema.missing')" />
            </template>
          </Column>
        </DataTable>
      </TabPanel>

      <TabPanel value="erd">
        <div class="flex flex-wrap items-center gap-3 mb-3">
          <MultiSelect v-model="erdForms" :options="formOptions" option-label="label" option-value="value" filter :placeholder="t('building.schema.all_forms')" :max-selected-labels="3" class="w-72" />
          <label class="flex items-center gap-2 text-sm"><ToggleSwitch v-model="showSystem" />{{ t('building.schema.show_system') }}</label>
          <span class="text-sm text-muted-color">{{ t('building.schema.erd_hint') }}</span>
        </div>
        <p v-if="loadingErd" class="text-muted-color"><i class="pi pi-spin pi-spinner me-1" />{{ t('building.loading') }}</p>
        <Message v-else-if="erd && !erd.nodes.length" severity="info">{{ t('building.schema.no_tables') }}</Message>
        <ErdDiagram v-else-if="erd" :nodes="erd.nodes" :edges="erd.edges" :show-system="showSystem" @open="openTable" />
      </TabPanel>

      <TabPanel value="reconciliation">
        <p class="text-sm text-muted-color mb-3">{{ t('building.schema.reconcile_hint') }}</p>
        <div class="flex flex-wrap items-center gap-2 mb-4">
          <Select v-model="reconcileForm" :options="formOptions" option-label="label" option-value="value" filter show-clear :placeholder="t('building.schema.all_forms')" class="w-72" />
          <Button icon="pi pi-play" :label="t('building.schema.reconcile_now')" :loading="running" data-testid="reconcile-now" @click="reconcileNow" />
        </div>
        <DataTable v-model:expanded-rows="expanded" :value="reports ?? []" :loading="reports === null" data-key="id" size="small" data-testid="reconcile-reports">
          <template #empty>{{ t('building.schema.no_reports') }}</template>
          <Column expander class="w-12" />
          <Column :header="t('building.schema.started')">
            <template #body="{ data }">{{ formatDateTime(data.started_at, session.locale) }}</template>
          </Column>
          <Column :header="t('building.schema.scope')">
            <template #body="{ data }"
              ><span v-if="data.form" class="ltr-value">{{ data.form }}</span
              ><span v-else>{{ t('building.schema.all_forms') }}</span></template
            >
          </Column>
          <Column :header="t('building.schema.trigger')">
            <template #body="{ data }">{{ t(`building.schema.trigger_kind.${data.trigger}`) }}</template>
          </Column>
          <Column :header="t('building.schema.engine')">
            <template #body="{ data }"
              ><span class="ltr-value">{{ data.engine }}</span></template
            >
          </Column>
          <Column :header="t('building.status')">
            <template #body="{ data }">
              <Tag :severity="reportSeverity[data.status]" :value="t(`building.schema.report_status.${data.status}`)" />
            </template>
          </Column>
          <Column :header="t('building.schema.differences')" field="difference_count" />
          <template #expansion="{ data }">
            <p v-if="!data.differences.length" class="text-sm text-muted-color p-2">{{ t('building.schema.no_differences') }}</p>
            <table v-else class="min-w-full text-sm">
              <thead>
                <tr>
                  <th class="p-1 text-start">{{ t('building.schema.owner') }}</th>
                  <th class="p-1 text-start">{{ t('building.schema.table') }}</th>
                  <th class="p-1 text-start">{{ t('building.schema.difference') }}</th>
                  <th class="p-1 text-start">{{ t('building.schema.object') }}</th>
                  <th class="p-1 text-start">{{ t('building.schema.expected') }}</th>
                  <th class="p-1 text-start">{{ t('building.schema.actual') }}</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="(d, i) in data.differences as Difference[]" :key="i" class="border-t border-line">
                  <td class="p-1 ltr-value">{{ d.form_key ?? formKeyOf(d.form) }}</td>
                  <td class="p-1 ltr-value">
                    <button v-if="d.table" type="button" class="underline cursor-pointer" @click="openTable(d.table)">{{ d.table }}</button><span v-else>—</span>
                  </td>
                  <td class="p-1">{{ t(`building.schema.diff_kind.${d.kind}`) }}</td>
                  <td class="p-1 ltr-value">{{ d.column ?? d.name ?? d.message ?? '—' }}</td>
                  <td class="p-1 ltr-value">{{ showValue(d.expected) }}</td>
                  <td class="p-1 ltr-value">{{ showValue(d.actual) }}</td>
                </tr>
              </tbody>
            </table>
          </template>
        </DataTable>
      </TabPanel>
    </TabPanels>
  </Tabs>

  <Dialog :visible="!!detail" modal :header="detail?.name" :style="{ width: '56rem' }" :breakpoints="{ '960px': '95vw' }" @update:visible="(v: boolean) => !v && (detail = null)">
    <div v-if="detail" class="flex flex-col gap-4 text-sm" data-testid="table-detail">
      <div class="flex flex-wrap gap-4">
        <span
          >{{ t('building.schema.rows') }}: <b>{{ detail.rows }}</b></span
        >
        <span
          >{{ t('building.schema.columns') }}: <b>{{ detail.columns.length }}</b></span
        >
        <Button
          v-if="detail.form"
          size="small"
          text
          icon="pi pi-pencil"
          :label="t('building.forms.open_builder')"
          @click="router.push({ name: 'admin.forms.builder', params: { form: detail.form } })"
        />
      </div>
      <section>
        <h3 class="font-semibold mb-1">{{ t('building.schema.columns') }}</h3>
        <table class="min-w-full">
          <thead>
            <tr>
              <th class="p-1 text-start">{{ t('building.schema.column') }}</th>
              <th class="p-1 text-start">{{ t('building.schema.type') }}</th>
              <th class="p-1 text-start">{{ t('building.schema.nullable') }}</th>
              <th class="p-1 text-start">{{ t('building.schema.default') }}</th>
              <th class="p-1 text-start">{{ t('building.schema.field') }}</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="c in detail.columns" :key="c.name" class="border-t border-line" :class="c.archived ? 'opacity-60' : ''">
              <td class="p-1 ltr-value">
                {{ c.name }} <Tag v-if="c.system" severity="secondary" :value="t('building.schema.system')" /><Tag v-if="c.archived" severity="warn" :value="t('building.schema.archived')" />
              </td>
              <td class="p-1 ltr-value">{{ c.type }}</td>
              <td class="p-1">{{ c.nullable ? t('building.yes') : t('building.no') }}</td>
              <td class="p-1 ltr-value">{{ formatDefault(c.default) }}</td>
              <td class="p-1 ltr-value">{{ c.field ?? '—' }}</td>
            </tr>
          </tbody>
        </table>
      </section>
      <section>
        <h3 class="font-semibold mb-1">{{ t('building.schema.indexes') }}</h3>
        <p v-if="!detail.indexes.length" class="text-muted-color">{{ t('common.none') }}</p>
        <ul v-else class="flex flex-col gap-1">
          <li v-for="i in detail.indexes" :key="i.name" class="flex flex-wrap items-center gap-2">
            <span class="ltr-value">{{ i.name }}</span>
            <span class="ltr-value text-muted-color">({{ i.columns.join(', ') }})</span>
            <Tag v-if="i.primary" severity="info" :value="t('building.schema.primary')" />
            <Tag v-else-if="i.unique" severity="secondary" :value="t('building.schema.unique')" />
          </li>
        </ul>
      </section>
      <section>
        <h3 class="font-semibold mb-1">{{ t('building.schema.foreign_keys') }}</h3>
        <p v-if="!detail.foreign_keys.length" class="text-muted-color">{{ t('common.none') }}</p>
        <ul v-else class="flex flex-col gap-1">
          <li v-for="(f, i) in detail.foreign_keys" :key="f.name ?? i" class="ltr-value">
            {{ f.columns.join(', ') }} → {{ f.foreign_table }}({{ f.foreign_columns.join(', ') }}) <span class="text-muted-color">ON DELETE {{ f.on_delete ?? '—' }}</span>
            <span v-if="f.name" class="text-xs text-muted-color ms-2">{{ f.name }}</span>
          </li>
        </ul>
      </section>
    </div>
  </Dialog>
</template>
