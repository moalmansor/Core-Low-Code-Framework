<script setup lang="ts">
import Button from 'primevue/button'
import Column from 'primevue/column'
import DataTable from 'primevue/datatable'
import Tab from 'primevue/tab'
import TabList from 'primevue/tablist'
import TabPanel from 'primevue/tabpanel'
import TabPanels from 'primevue/tabpanels'
import Tabs from 'primevue/tabs'
import Tag from 'primevue/tag'
import { useToast } from 'primevue/usetoast'
import { computed, onMounted, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRoute, useRouter } from 'vue-router'
import EmptyState from '@/components/config/EmptyState.vue'
import TabIntro from '@/components/config/TabIntro.vue'
import { errorText, formatDateTime } from '../admin/building/shared'
import { dashboardsApi, reportsApi, type DashboardSummary, type ReportSummary } from './api'

/** Admin › Reports & dashboards (specification §4.18): every report and dashboard of the organization. */
const TABS = ['reports', 'dashboards'] as const
type TabKey = (typeof TABS)[number]
const route = useRoute()
const router = useRouter()
const { t, locale } = useI18n()
const toast = useToast()
const tab = ref<TabKey>(route.params.tab === 'dashboards' ? 'dashboards' : 'reports')
watch(tab, (v) => router.replace({ name: 'admin.reports', params: { tab: v === 'reports' ? undefined : v } }))
const open = computed({ get: () => tab.value, set: (v: TabKey) => (tab.value = v) })
const reports = ref<ReportSummary[]>([])
const dashboards = ref<DashboardSummary[]>([])
const loading = ref(true)
onMounted(async () => {
  try {
    ;[reports.value, dashboards.value] = await Promise.all([reportsApi.list(), dashboardsApi.list()])
  } catch (e) {
    toast.add({ severity: 'error', summary: errorText(e, t('rep.load_failed')), life: 6000 })
  } finally {
    loading.value = false
  }
})
const report = (uuid: string) => router.push({ name: 'admin.reports.report', params: { report: uuid } })
const dashboard = (uuid: string) => router.push({ name: 'admin.reports.dashboard', params: { dashboard: uuid } })
</script>

<template>
  <div class="flex flex-col" data-testid="reports-admin">
    <h1 class="page-title !mb-1">{{ t('admin.area.reports') }}</h1>
    <p class="m-0 text-sm text-muted-color max-w-[var(--measure)]">{{ t('rep.subtitle') }}</p>
    <Tabs v-model:value="open" scrollable class="mt-6">
      <TabList>
        <Tab v-for="k in TABS" :key="k" :value="k" :data-testid="`rep-tab-${k}`">{{ t(`rep.tab.${k}`) }}</Tab>
      </TabList>
      <TabPanels class="!px-0 !pt-6 !pb-0 !bg-transparent">
        <TabPanel value="reports">
          <div class="flex flex-col gap-4">
            <div class="flex flex-wrap items-start gap-3">
              <TabIntro class="flex-1 min-w-0" :title="t('rep.tab.reports')" :text="t('rep.reports_intro')" />
              <Button icon="pi pi-plus" :label="t('rep.new_report')" size="small" data-testid="report-new" @click="report('new')" />
            </div>
            <EmptyState v-if="!loading && !reports.length" icon="pi pi-chart-bar" :title="t('rep.reports_empty_title')" :description="t('rep.reports_empty_text')" testid="reports-empty">
              <Button icon="pi pi-plus" :label="t('rep.new_report')" size="small" @click="report('new')" />
            </EmptyState>
            <DataTable v-else :value="reports" data-key="uuid" :loading="loading" size="small" class="records-table" row-hover @row-click="(e) => report((e.data as ReportSummary).uuid)">
              <Column :header="t('rep.name')">
                <template #body="{ data }"
                  ><span class="font-medium" dir="auto">{{ data.label }}</span></template
                >
              </Column>
              <Column :header="t('rep.form')">
                <template #body="{ data }"
                  ><span dir="auto">{{ data.form_name }}</span></template
                >
              </Column>
              <Column :header="t('rep.shown_as')" style="width: 10rem">
                <template #body="{ data }"><Tag severity="secondary" :value="t(`rep.viz.${data.visualization}`)" /></template>
              </Column>
              <Column :header="t('rep.changed')" style="width: 12rem">
                <template #body="{ data }">{{ formatDateTime(data.updated_at, locale) }}</template>
              </Column>
            </DataTable>
          </div>
        </TabPanel>
        <TabPanel value="dashboards">
          <div class="flex flex-col gap-4">
            <div class="flex flex-wrap items-start gap-3">
              <TabIntro class="flex-1 min-w-0" :title="t('rep.tab.dashboards')" :text="t('rep.dashboards_intro')" />
              <Button icon="pi pi-plus" :label="t('rep.new_dashboard')" size="small" data-testid="dashboard-new" @click="dashboard('new')" />
            </div>
            <EmptyState v-if="!loading && !dashboards.length" icon="pi pi-th-large" :title="t('rep.dashboards_empty_title')" :description="t('rep.dashboards_empty_text')" testid="dashboards-empty">
              <Button icon="pi pi-plus" :label="t('rep.new_dashboard')" size="small" @click="dashboard('new')" />
            </EmptyState>
            <DataTable v-else :value="dashboards" data-key="uuid" :loading="loading" size="small" class="records-table" row-hover @row-click="(e) => dashboard((e.data as DashboardSummary).uuid)">
              <Column :header="t('rep.name')">
                <template #body="{ data }"
                  ><span class="font-medium" dir="auto">{{ data.label }}</span></template
                >
              </Column>
              <Column :header="t('rep.widgets')" style="width: 8rem">
                <template #body="{ data }">{{ data.widgets }}</template>
              </Column>
              <Column :header="t('rep.state')" style="width: 8rem">
                <template #body="{ data }"><Tag :severity="data.active ? 'success' : 'secondary'" :value="data.active ? t('rep.active') : t('rep.inactive')" /></template>
              </Column>
            </DataTable>
          </div>
        </TabPanel>
      </TabPanels>
    </Tabs>
  </div>
</template>
