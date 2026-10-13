<script setup lang="ts">
import Button from 'primevue/button'
import Column from 'primevue/column'
import DataTable, { type DataTablePageEvent } from 'primevue/datatable'
import Drawer from 'primevue/drawer'
import Select from 'primevue/select'
import Tag from 'primevue/tag'
import { useToast } from 'primevue/usetoast'
import { computed, onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { formatDatetime } from '@/runtime/format'
import { errorText } from '@/views/admin/building/shared'
import { automationsApi, runSeverity, type RunDetail, type RunStatus, type RunSummary } from './api'
import RunDetailView from './RunDetailView.vue'

/**
 * The run history of an automation (specification §4.31): newest first,
 * filtered by outcome; a run opens with its steps. Those who may run
 * automations confirm a large run, cancel a waiting one, or retry a failed one.
 */
const props = defineProps<{ automation: string; canRun: boolean }>()
const { t, locale } = useI18n()
const toast = useToast()
const rows = ref<RunSummary[]>([])
const total = ref(0)
const page = ref(1)
const status = ref<RunStatus | null>(null)
const loading = ref(false)
const open = ref<RunDetail | null>(null)
const busy = ref(false)
const STATUSES: RunStatus[] = ['succeeded', 'failed', 'partially_failed', 'awaiting_confirmation', 'waiting', 'queued', 'running', 'cancelled', 'skipped_loop']
const statusOptions = computed(() => [{ value: null, label: t('auto.any_status') }, ...STATUSES.map((s) => ({ value: s as RunStatus | null, label: t(`auto.status.${s}`) }))])

async function load(): Promise<void> {
  loading.value = true
  try {
    const r = await automationsApi.runs(props.automation, page.value, status.value)
    rows.value = r.rows
    total.value = r.total
  } catch (e) {
    toast.add({ severity: 'error', summary: errorText(e, t('auto.load_failed')), life: 6000 })
  } finally {
    loading.value = false
  }
}
onMounted(load)
defineExpose({ reload: load })

function filterChanged(): void {
  page.value = 1
  void load()
}
function onPage(e: DataTablePageEvent): void {
  page.value = e.page + 1
  void load()
}
async function show(r: RunSummary): Promise<void> {
  try {
    open.value = await automationsApi.runDetail(r.uuid)
  } catch (e) {
    toast.add({ severity: 'error', summary: errorText(e, t('auto.load_failed')), life: 6000 })
  }
}
async function act(kind: 'confirm' | 'cancel' | 'retry'): Promise<void> {
  if (!open.value) return
  busy.value = true
  try {
    open.value = await automationsApi[kind](open.value.uuid)
    toast.add({ severity: 'success', summary: t(`auto.done.${kind}`), life: 3000 })
    await load()
  } catch (e) {
    toast.add({ severity: 'error', summary: errorText(e, t('auto.save_failed')), life: 6000 })
  } finally {
    busy.value = false
  }
}
const duration = (ms: number | null) => (ms === null ? '—' : t('auto.duration_s', { s: (ms / 1000).toFixed(1) }))
</script>

<template>
  <div class="flex flex-col gap-3" data-testid="auto-runs">
    <div class="flex flex-wrap items-center gap-2">
      <Select v-model="status" :options="statusOptions" option-label="label" option-value="value" size="small" class="w-field-sm" :aria-label="t('auto.filter_status')" @change="filterChanged" />
      <Button icon="pi pi-refresh" :label="t('auto.refresh')" text size="small" data-testid="auto-runs-refresh" @click="load" />
    </div>
    <DataTable :value="rows" lazy paginator :rows="25" :total-records="total" :loading="loading" data-key="uuid" size="small" selection-mode="single" @row-click="(e) => show(e.data)" @page="onPage">
      <template #empty>{{ t('auto.no_runs') }}</template>
      <Column :header="t('auto.started')">
        <template #body="{ data }">{{ formatDatetime(data.started_at ?? data.created_at, locale) }}</template>
      </Column>
      <Column :header="t('auto.trigger_col')">
        <template #body="{ data }"> {{ t(`auto.trigger.${data.trigger}`) }}<Tag v-if="data.test" severity="info" :value="t('auto.test_run')" class="ms-1" /> </template>
      </Column>
      <Column :header="t('auto.records')">
        <template #body="{ data }">{{ t('auto.records_value', { n: data.records, changed: data.affected }) }}</template>
      </Column>
      <Column :header="t('auto.duration')">
        <template #body="{ data }">{{ duration(data.duration_ms) }}</template>
      </Column>
      <Column :header="t('auto.outcome')">
        <template #body="{ data }"><Tag :severity="runSeverity(data.status)" :value="t(`auto.status.${data.status}`)" /></template>
      </Column>
    </DataTable>
    <Drawer :visible="open !== null" position="right" class="!w-full md:!w-[34rem]" :header="t('auto.run')" @update:visible="(v: boolean) => !v && (open = null)">
      <template v-if="open">
        <RunDetailView :run="open" />
        <div v-if="canRun" class="flex flex-wrap gap-2 mt-4">
          <Button v-if="open.status === 'awaiting_confirmation'" :label="t('auto.confirm_run')" icon="pi pi-check" size="small" :loading="busy" data-testid="auto-confirm" @click="act('confirm')" />
          <Button
            v-if="['awaiting_confirmation', 'waiting', 'queued'].includes(open.status)"
            :label="t('auto.cancel_run')"
            icon="pi pi-times"
            severity="secondary"
            outlined
            size="small"
            :loading="busy"
            @click="act('cancel')"
          />
          <Button
            v-if="!open.test && (open.status === 'failed' || open.status === 'partially_failed')"
            :label="t('auto.retry_run')"
            icon="pi pi-refresh"
            size="small"
            outlined
            :loading="busy"
            @click="act('retry')"
          />
        </div>
      </template>
    </Drawer>
  </div>
</template>
