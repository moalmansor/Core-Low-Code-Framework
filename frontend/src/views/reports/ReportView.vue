<script setup lang="ts">
import Button from 'primevue/button'
import Message from 'primevue/message'
import SplitButton from 'primevue/splitbutton'
import { useToast } from 'primevue/usetoast'
import { computed, onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRoute } from 'vue-router'
import { errorText, formatDateTime } from '../admin/building/shared'
import { reportsApi, type ReportData } from './api'
import ReportChart from './ReportChart.vue'

/** One report, for the person viewing it, with its export. */
const { t, locale } = useI18n()
const toast = useToast()
const route = useRoute()
/** A page that shows it passes it in, and the page's own title replaces the way back. */
const props = defineProps<{ report?: string; embedded?: boolean }>()
const data = ref<ReportData | null>(null)
const failed = ref<string | null>(null)
const chart = ref<InstanceType<typeof ReportChart> | null>(null)
const exporting = ref(false)
async function load(): Promise<void> {
  failed.value = null
  try {
    data.value = await reportsApi.data(String(props.report ?? route.params.report))
  } catch (e) {
    failed.value = errorText(e, t('rep.load_failed'))
  }
}
onMounted(load)
async function exportAs(format: 'xlsx' | 'csv' | 'pdf'): Promise<void> {
  if (!data.value) return
  exporting.value = true
  try {
    await reportsApi.export(data.value.uuid, format, format === 'pdf' ? (chart.value?.image() ?? null) : null, data.value.label)
  } catch (e) {
    toast.add({ severity: 'error', summary: errorText(e, t('rep.export_failed')), life: 6000 })
  } finally {
    exporting.value = false
  }
}
const more = computed(() => [
  { label: t('rep.export_csv'), command: () => exportAs('csv') },
  { label: t('rep.export_pdf'), command: () => exportAs('pdf') },
])
</script>

<template>
  <div class="flex flex-col gap-4 max-w-[72rem]" data-testid="report-view">
    <header class="page-head">
      <div class="flex-1 min-w-0">
        <RouterLink v-if="!embedded" :to="{ name: 'insights' }" class="text-sm text-primary inline-flex items-center gap-1 mb-1">
          <i class="pi pi-arrow-left rtl:rotate-180" aria-hidden="true" />{{ t('rep.insights') }}
        </RouterLink>
        <h1 class="page-title !mb-1" dir="auto">{{ data?.label ?? t('rep.report') }}</h1>
        <p v-if="data?.description" class="m-0 text-sm text-muted-color" dir="auto">{{ data.description }}</p>
      </div>
      <div v-if="data" class="flex items-center gap-2">
        <Button icon="pi pi-refresh" :aria-label="t('rep.refresh')" size="small" text @click="load" />
        <SplitButton :label="t('rep.export_xlsx')" icon="pi pi-download" size="small" :model="more" :loading="exporting" data-testid="report-export" @click="exportAs('xlsx')" />
      </div>
    </header>
    <Message v-if="failed" severity="error" :closable="false">{{ failed }}</Message>
    <template v-if="data">
      <div class="rounded-[var(--radius-card)] border border-[var(--border)] bg-[var(--bg-surface)] p-4">
        <ReportChart ref="chart" :result="data.result" :visualization="data.visualization" :options="data.options" :height="380" />
      </div>
      <p class="m-0 text-xs text-muted-color">{{ t('rep.generated', { when: formatDateTime(data.result.generatedAt, locale), n: data.result.records }) }}</p>
    </template>
  </div>
</template>
