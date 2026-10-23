<script setup lang="ts">
import Button from 'primevue/button'
import Column from 'primevue/column'
import DataTable from 'primevue/datatable'
import Tag from 'primevue/tag'
import { useToast } from 'primevue/usetoast'
import { onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRouter } from 'vue-router'
import EmptyState from '@/components/config/EmptyState.vue'
import EmptyValue from '@/components/EmptyValue.vue'
import TabIntro from '@/components/config/TabIntro.vue'
import { formatDatetime } from '@/runtime/format'
import { errorText } from '../admin/building/shared'
import { syncApi, type SyncJobSummary } from './api'

/** Sync jobs: what each moves, between which source and form, and how its last run went. */
const { t, locale } = useI18n()
const router = useRouter()
const toast = useToast()
const rows = ref<SyncJobSummary[]>([])
const loading = ref(true)
onMounted(async () => {
  try {
    rows.value = await syncApi.list()
  } catch (e) {
    toast.add({ severity: 'error', summary: errorText(e, t('intg.load_failed')), life: 6000 })
  } finally {
    loading.value = false
  }
})
const open = (uuid: string) => router.push({ name: 'admin.integrations.sync', params: { job: uuid } })
const severity = (s: string) => (s === 'succeeded' ? 'success' : s === 'failed' ? 'danger' : s === 'partially_failed' ? 'warn' : 'secondary')
</script>

<template>
  <div class="flex flex-col gap-4" data-testid="sync-panel">
    <div class="flex flex-wrap items-start gap-3">
      <TabIntro class="flex-1 min-w-0" :title="t('intg.tab.sync')" :text="t('sync.intro')" />
      <Button icon="pi pi-plus" :label="t('sync.new')" size="small" data-testid="sync-new" @click="open('new')" />
    </div>
    <EmptyState v-if="!loading && !rows.length" icon="pi pi-sync" :title="t('sync.empty_title')" :description="t('sync.empty_text')" testid="sync-empty">
      <Button icon="pi pi-plus" :label="t('sync.new')" size="small" @click="open('new')" />
    </EmptyState>
    <DataTable v-else :value="rows" data-key="uuid" :loading="loading" size="small" class="records-table" row-hover @row-click="(e) => open((e.data as SyncJobSummary).uuid)">
      <Column :header="t('intg.name')">
        <template #body="{ data }"
          ><span class="font-medium" dir="auto">{{ data.label }}</span></template
        >
      </Column>
      <Column :header="t('sync.what')">
        <template #body="{ data }">
          <span dir="auto">{{
            data.direction === 'import' ? t('sync.import_from_to', { source: data.source_name, form: data.form_name }) : t('sync.export_from_to', { source: data.source_name, form: data.form_name })
          }}</span>
        </template>
      </Column>
      <Column :header="t('sync.last_run')">
        <template #body="{ data }">
          <span v-if="data.last_run" class="flex flex-wrap items-center gap-2 text-sm">
            <Tag :severity="severity(data.last_run.status)" :value="t(`sync.status.${data.last_run.status}`)" />
            <span v-if="data.last_run.finished_at">{{ formatDatetime(data.last_run.finished_at, locale) }}</span>
          </span>
          <EmptyValue v-else />
        </template>
      </Column>
      <Column :header="t('sync.schedule')">
        <template #body="{ data }">{{ data.scheduled ? t('sync.scheduled') : t('sync.on_demand') }}</template>
      </Column>
      <Column :header="t('intg.state')">
        <template #body="{ data }"><Tag :severity="data.active ? 'success' : 'secondary'" :value="data.active ? t('intg.active') : t('intg.paused')" /></template>
      </Column>
    </DataTable>
  </div>
</template>
