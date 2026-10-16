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
import { packagesApi, type DriftReport, type PackageSummary } from './api'
import ExportDialog from './ExportDialog.vue'

/**
 * Configuration packages (specification §4.10, §4.22): export a set of
 * applications, forms and templates as a file, import one from another
 * environment through its drift report, and compare without importing.
 */
const TABS = ['packages', 'reports'] as const
type TabKey = (typeof TABS)[number]
const route = useRoute()
const router = useRouter()
const { t, locale } = useI18n()
const toast = useToast()
const tab = ref<TabKey>(route.params.tab === 'reports' ? 'reports' : 'packages')
watch(tab, (v) => router.replace({ name: 'admin.packages', params: { tab: v === 'packages' ? undefined : v } }))
const open = computed({ get: () => tab.value, set: (v: TabKey) => (tab.value = v) })

const rows = ref<PackageSummary[]>([])
const reports = ref<DriftReport[]>([])
const loading = ref(true)
const exporting = ref(false)
const busy = ref(false)
const importInput = ref<HTMLInputElement | null>(null)
const compareInput = ref<HTMLInputElement | null>(null)

async function load(): Promise<void> {
  try {
    ;[rows.value, reports.value] = await Promise.all([packagesApi.list(), packagesApi.reports()])
  } catch (e) {
    toast.add({ severity: 'error', summary: errorText(e, t('pkg.load_failed')), life: 6000 })
  } finally {
    loading.value = false
  }
}
onMounted(load)

async function picked(kind: 'import' | 'compare', ev: Event): Promise<void> {
  const input = ev.target as HTMLInputElement
  const file = input.files?.[0]
  input.value = ''
  if (!file) return
  busy.value = true
  try {
    if (kind === 'import') {
      const p = await packagesApi.import(file)
      await router.push({ name: 'admin.packages.package', params: { package: p.uuid } })
    } else {
      const r = await packagesApi.compare(file)
      await router.push({ name: 'admin.packages.report', params: { report: r.uuid } })
    }
  } catch (e) {
    toast.add({ severity: 'error', summary: errorText(e, t('pkg.upload_failed')), life: 8000 })
  } finally {
    busy.value = false
  }
}
function exported(): void {
  exporting.value = false
  toast.add({ severity: 'success', summary: t('pkg.exported'), life: 4000 })
  void load()
}
const statusSeverity = (s: string) => (s === 'applied' ? 'success' : s === 'failed' ? 'danger' : s === 'conflicts' ? 'warn' : s === 'applying' ? 'info' : 'secondary')
function countsText(r: DriftReport): string {
  return Object.entries(r.counts)
    .map(([state, n]) => t('pkg.count_of', { n, state: t(`pkg.state_name.${state}`) }))
    .join(' · ')
}
</script>

<template>
  <div class="flex flex-col" data-testid="packages-view">
    <h1 class="page-title !mb-1">{{ t('admin.area.packages') }}</h1>
    <p class="m-0 text-sm text-muted-color max-w-[var(--measure)]">{{ t('pkg.subtitle') }}</p>
    <input ref="importInput" type="file" accept="application/json,.json" class="hidden" data-testid="pkg-import-file" @change="picked('import', $event)" />
    <input ref="compareInput" type="file" accept="application/json,.json" class="hidden" data-testid="pkg-compare-file" @change="picked('compare', $event)" />
    <ExportDialog :visible="exporting" @close="exporting = false" @exported="exported" />
    <Tabs v-model:value="open" scrollable class="mt-6">
      <TabList>
        <Tab v-for="k in TABS" :key="k" :value="k" :data-testid="`pkg-tab-${k}`">{{ t(`pkg.tab.${k}`) }}</Tab>
      </TabList>
      <TabPanels class="!px-0 !pt-6 !pb-0 !bg-transparent">
        <TabPanel value="packages">
          <div class="flex flex-col gap-4">
            <div class="flex flex-wrap items-start gap-3">
              <TabIntro class="flex-1 min-w-0" :title="t('pkg.tab.packages')" :text="t('pkg.packages_intro')" />
              <div class="flex flex-wrap gap-2">
                <Button icon="pi pi-upload" :label="t('pkg.import')" size="small" outlined :loading="busy" data-testid="pkg-import" @click="importInput?.click()" />
                <Button icon="pi pi-download" :label="t('pkg.export')" size="small" data-testid="pkg-export" @click="exporting = true" />
              </div>
            </div>
            <EmptyState v-if="!loading && !rows.length" icon="pi pi-box" :title="t('pkg.empty_title')" :description="t('pkg.empty_text')" testid="pkg-empty" />
            <DataTable
              v-else
              :value="rows"
              data-key="uuid"
              :loading="loading"
              size="small"
              class="records-table"
              row-hover
              @row-click="(e) => router.push({ name: 'admin.packages.package', params: { package: (e.data as PackageSummary).uuid } })"
            >
              <Column :header="t('pkg.name')">
                <template #body="{ data }">
                  <div class="flex flex-col">
                    <span class="font-medium" dir="auto">{{ data.name }}</span>
                    <span class="text-xs text-muted-color">{{ t('pkg.objects_count', { n: data.objects }) }}</span>
                  </div>
                </template>
              </Column>
              <Column :header="t('pkg.direction')" style="width: 9rem">
                <template #body="{ data }">{{ t(`pkg.direction_name.${data.direction}`) }}</template>
              </Column>
              <Column :header="t('pkg.source')">
                <template #body="{ data }">
                  <span v-if="data.direction === 'import'" dir="auto">{{ data.source }}</span>
                  <span v-else class="text-muted-color">{{ t('pkg.this_environment') }}</span>
                </template>
              </Column>
              <Column :header="t('pkg.when')">
                <template #body="{ data }">
                  <div class="flex flex-col text-sm">
                    <span>{{ formatDateTime(data.created_at, locale) }}</span>
                    <span v-if="data.by" class="text-xs text-muted-color" dir="auto">{{ data.by }}</span>
                  </div>
                </template>
              </Column>
              <Column :header="t('pkg.status')" style="width: 10rem">
                <template #body="{ data }">
                  <Tag :severity="statusSeverity(data.status)" :value="t(`pkg.status_name.${data.direction === 'export' ? 'exported' : data.status}`)" />
                </template>
              </Column>
            </DataTable>
          </div>
        </TabPanel>
        <TabPanel value="reports">
          <div class="flex flex-col gap-4">
            <div class="flex flex-wrap items-start gap-3">
              <TabIntro class="flex-1 min-w-0" :title="t('pkg.tab.reports')" :text="t('pkg.reports_intro')" />
              <Button icon="pi pi-arrows-h" :label="t('pkg.compare')" size="small" :loading="busy" data-testid="pkg-compare" @click="compareInput?.click()" />
            </div>
            <EmptyState v-if="!loading && !reports.length" icon="pi pi-arrows-h" :title="t('pkg.reports_empty_title')" :description="t('pkg.reports_empty_text')" testid="pkg-reports-empty" />
            <DataTable
              v-else
              :value="reports"
              data-key="uuid"
              :loading="loading"
              size="small"
              class="records-table"
              row-hover
              @row-click="(e) => router.push({ name: 'admin.packages.report', params: { report: (e.data as DriftReport).uuid } })"
            >
              <Column :header="t('pkg.compared')">
                <template #body="{ data }">
                  <span class="font-medium" dir="auto">{{ data.source }}</span>
                </template>
              </Column>
              <Column :header="t('pkg.when')">
                <template #body="{ data }">{{ formatDateTime(data.created_at, locale) }}</template>
              </Column>
              <Column :header="t('pkg.summary')">
                <template #body="{ data }">
                  <span class="text-sm">{{ countsText(data) }}</span>
                </template>
              </Column>
              <Column :header="t('pkg.status')" style="width: 10rem">
                <template #body="{ data }">
                  <Tag :severity="data.ambiguous ? 'danger' : 'success'" :value="data.ambiguous ? t('pkg.ambiguous') : t('pkg.importable')" />
                </template>
              </Column>
            </DataTable>
          </div>
        </TabPanel>
      </TabPanels>
    </Tabs>
  </div>
</template>
