<script setup lang="ts">
import Button from 'primevue/button'
import Message from 'primevue/message'
import SplitButton from 'primevue/splitbutton'
import { useToast } from 'primevue/usetoast'
import { computed, onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRoute } from 'vue-router'
import EmptyState from '@/components/config/EmptyState.vue'
import { errorText } from '../admin/building/shared'
import { dashboardsApi, type DashboardView } from './api'
import ReportChart from './ReportChart.vue'

/** A dashboard as the viewer sees it: the widgets for their roles and reports. */
const { t } = useI18n()
const toast = useToast()
const route = useRoute()
const data = ref<DashboardView | null>(null)
const failed = ref<string | null>(null)
const exporting = ref(false)
const charts = ref<Record<string, InstanceType<typeof ReportChart> | null>>({})
async function load(): Promise<void> {
  failed.value = null
  try {
    data.value = await dashboardsApi.view(String(route.params.dashboard))
  } catch (e) {
    failed.value = errorText(e, t('rep.load_failed'))
  }
}
onMounted(load)
async function exportAs(format: 'xlsx' | 'pdf'): Promise<void> {
  if (!data.value) return
  exporting.value = true
  try {
    const images: Record<string, string> = {}
    if (format === 'pdf') {
      for (const [uuid, c] of Object.entries(charts.value)) {
        const img = c?.image()
        if (img) images[uuid] = img
      }
    }
    await dashboardsApi.export(data.value.uuid, format, images, data.value.label)
  } catch (e) {
    toast.add({ severity: 'error', summary: errorText(e, t('rep.export_failed')), life: 6000 })
  } finally {
    exporting.value = false
  }
}
const more = computed(() => [{ label: t('rep.export_pdf'), command: () => exportAs('pdf') }])
const height = (h: number) => Math.max(140, h * 150 - 60)
</script>

<template>
  <div class="flex flex-col gap-4" data-testid="dashboard-view">
    <header class="page-head">
      <div class="flex-1 min-w-0">
        <RouterLink :to="{ name: 'insights' }" class="text-sm text-primary inline-flex items-center gap-1 mb-1">
          <i class="pi pi-arrow-left rtl:rotate-180" aria-hidden="true" />{{ t('rep.insights') }}
        </RouterLink>
        <h1 class="page-title !mb-1" dir="auto">{{ data?.label ?? t('rep.dashboard') }}</h1>
        <p v-if="data?.description" class="m-0 text-sm text-muted-color" dir="auto">{{ data.description }}</p>
      </div>
      <div v-if="data && data.widgets.length" class="flex items-center gap-2">
        <Button icon="pi pi-refresh" :aria-label="t('rep.refresh')" size="small" text @click="load" />
        <SplitButton :label="t('rep.export_xlsx')" icon="pi pi-download" size="small" :model="more" :loading="exporting" data-testid="dashboard-export" @click="exportAs('xlsx')" />
      </div>
    </header>
    <Message v-if="failed" severity="error" :closable="false">{{ failed }}</Message>
    <template v-if="data">
      <EmptyState v-if="!data.widgets.length" icon="pi pi-th-large" :title="t('rep.dashboard_empty_title')" :description="t('rep.dashboard_empty_text')" testid="dashboard-view-empty" />
      <div v-else class="dash-view">
        <section v-for="w in data.widgets" :key="w.uuid" class="dash-widget" :class="`cols-${w.layout.w}`" :data-testid="`widget-${w.uuid}`">
          <h2 v-if="w.title" class="m-0 text-sm font-semibold" dir="auto">{{ w.title }}</h2>
          <p v-if="w.type === 'text'" class="m-0 text-sm whitespace-pre-line" dir="auto">{{ w.text }}</p>
          <p v-else-if="w.error" class="m-0 text-sm text-muted-color">{{ w.error }}</p>
          <ReportChart
            v-else-if="w.result && w.visualization"
            :ref="(c) => (charts[w.uuid] = c as InstanceType<typeof ReportChart> | null)"
            :result="w.result"
            :visualization="w.visualization"
            :options="w.options"
            :height="height(w.layout.h)"
          />
          <RouterLink v-if="w.report" :to="{ name: 'reports.view', params: { report: w.report } }" class="text-xs text-primary self-start">{{ t('rep.open_report') }}</RouterLink>
        </section>
      </div>
    </template>
  </div>
</template>

<style scoped>
.dash-view {
  display: grid;
  grid-template-columns: repeat(12, minmax(0, 1fr));
  gap: 1rem;
}
.dash-widget {
  display: flex;
  flex-direction: column;
  gap: 0.5rem;
  min-width: 0;
  padding: 1rem;
  border: 1px solid var(--border);
  border-radius: var(--radius-card);
  background: var(--bg-surface);
}
.cols-2 {
  grid-column: span 2;
}
.cols-3 {
  grid-column: span 3;
}
.cols-4 {
  grid-column: span 4;
}
.cols-5 {
  grid-column: span 5;
}
.cols-6 {
  grid-column: span 6;
}
.cols-7 {
  grid-column: span 7;
}
.cols-8 {
  grid-column: span 8;
}
.cols-9 {
  grid-column: span 9;
}
.cols-10 {
  grid-column: span 10;
}
.cols-11 {
  grid-column: span 11;
}
.cols-12 {
  grid-column: span 12;
}
@media (max-width: 900px) {
  .dash-widget {
    grid-column: span 12;
  }
}
</style>
