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
import { sourcesApi, type SourceSummary } from './api'

/** External data sources: where each reads from and how its last test went. */
const { t, locale } = useI18n()
const router = useRouter()
const toast = useToast()
const rows = ref<SourceSummary[]>([])
const loading = ref(true)
onMounted(async () => {
  try {
    rows.value = (await sourcesApi.list()).data
  } catch (e) {
    toast.add({ severity: 'error', summary: errorText(e, t('intg.load_failed')), life: 6000 })
  } finally {
    loading.value = false
  }
})
const open = (uuid: string) => router.push({ name: 'admin.integrations.source', params: { source: uuid } })
</script>

<template>
  <div class="flex flex-col gap-4" data-testid="sources-panel">
    <div class="flex flex-wrap items-start gap-3">
      <TabIntro class="flex-1 min-w-0" :title="t('intg.tab.sources')" :text="t('xds.intro')" />
      <Button icon="pi pi-plus" :label="t('xds.new')" size="small" data-testid="source-new" @click="open('new')" />
    </div>
    <EmptyState v-if="!loading && !rows.length" icon="pi pi-database" :title="t('xds.empty_title')" :description="t('xds.empty_text')" testid="sources-empty">
      <Button icon="pi pi-plus" :label="t('xds.new')" size="small" @click="open('new')" />
    </EmptyState>
    <DataTable v-else :value="rows" data-key="uuid" :loading="loading" size="small" class="records-table" row-hover @row-click="(e) => open((e.data as SourceSummary).uuid)">
      <Column :header="t('intg.name')">
        <template #body="{ data }"
          ><span class="font-medium" dir="auto">{{ data.label }}</span></template
        >
      </Column>
      <Column :header="t('xds.kind')">
        <template #body="{ data }">{{ t(`xds.type.${data.type}`) }}</template>
      </Column>
      <Column :header="t('xds.reads_from')">
        <template #body="{ data }"
          ><span v-if="data.target" class="ltr-value">{{ data.target }}</span
          ><EmptyValue v-else
        /></template>
      </Column>
      <Column :header="t('xds.last_test')">
        <template #body="{ data }">
          <span v-if="data.last_tested_at" class="flex flex-wrap items-center gap-2 text-sm">
            <Tag :severity="data.last_test_status === 'ok' ? 'success' : 'danger'" :value="t(`xds.test_status.${data.last_test_status}`)" />
            {{ formatDatetime(data.last_tested_at, locale) }}
          </span>
          <EmptyValue v-else />
        </template>
      </Column>
      <Column :header="t('intg.state')">
        <template #body="{ data }"><Tag :severity="data.active ? 'success' : 'secondary'" :value="data.active ? t('intg.active') : t('intg.paused')" /></template>
      </Column>
    </DataTable>
  </div>
</template>
