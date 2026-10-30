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
import TabIntro from '@/components/config/TabIntro.vue'
import EmptyValue from '@/components/EmptyValue.vue'
import { formatDatetime } from '@/runtime/format'
import { errorText } from '../admin/building/shared'
import { retentionApi, severityOf, type PolicyDoc, type RetentionRun } from './api'

/** Retention policies: what each keeps, for how long, what happens then, and how the latest runs went. */
const { t, locale } = useI18n()
const router = useRouter()
const toast = useToast()
const rows = ref<PolicyDoc[]>([])
const runs = ref<RetentionRun[]>([])
const loading = ref(true)
async function load(): Promise<void> {
  try {
    ;[rows.value, runs.value] = await Promise.all([retentionApi.list(), retentionApi.runs()])
  } catch (e) {
    toast.add({ severity: 'error', summary: errorText(e, t('ret.load_failed')), life: 6000 })
  } finally {
    loading.value = false
  }
}
onMounted(load)
const open = (uuid: string) => router.push({ name: 'admin.retention.policy', params: { policy: uuid } })
</script>

<template>
  <div class="flex flex-col gap-6" data-testid="ret-policies">
    <div class="flex flex-wrap items-start gap-3">
      <TabIntro class="flex-1 min-w-0" :title="t('ret.tab.policies')" :text="t('ret.policies_intro')" />
      <Button icon="pi pi-plus" :label="t('ret.new_policy')" size="small" data-testid="ret-new-policy" @click="open('new')" />
    </div>
    <EmptyState v-if="!loading && !rows.length" icon="pi pi-calendar-times" :title="t('ret.policies_empty')" :description="t('ret.policies_empty_text')" testid="ret-policies-empty">
      <Button icon="pi pi-plus" :label="t('ret.new_policy')" size="small" @click="open('new')" />
    </EmptyState>
    <DataTable v-else :value="rows" data-key="uuid" :loading="loading" size="small" class="records-table" row-hover @row-click="(e) => open((e.data as PolicyDoc).uuid!)">
      <Column :header="t('ret.name')">
        <template #body="{ data }"
          ><span class="font-medium" dir="auto">{{ data.label }}</span></template
        >
      </Column>
      <Column :header="t('ret.applies_to')">
        <template #body="{ data }">{{ t(`ret.class.${data.data_class}`) }}</template>
      </Column>
      <Column :header="t('ret.keep')">
        <template #body="{ data }">{{ t('ret.days_then', { days: data.retention_days, action: t(`ret.action.${data.end_action}`) }) }}</template>
      </Column>
      <Column :header="t('ret.last_run')">
        <template #body="{ data }">
          <span v-if="data.last_run" class="flex flex-wrap items-center gap-2 text-sm">
            <Tag :severity="severityOf(data.last_run.status)" :value="t(`ret.status.${data.last_run.status}`)" />
            <span v-if="data.last_run.finished_at">{{ formatDatetime(data.last_run.finished_at, locale) }}</span>
          </span>
          <EmptyValue v-else />
        </template>
      </Column>
      <Column :header="t('ret.state')">
        <template #body="{ data }"><Tag :severity="data.active ? 'success' : 'secondary'" :value="data.active ? t('ret.active') : t('ret.paused')" /></template>
      </Column>
    </DataTable>

    <section class="flex flex-col gap-3">
      <h2 class="text-base font-semibold m-0">{{ t('ret.recent_runs') }}</h2>
      <DataTable :value="runs" data-key="uuid" size="small" class="records-table" data-testid="ret-runs">
        <template #empty>{{ t('ret.no_runs') }}</template>
        <Column :header="t('ret.when')">
          <template #body="{ data }"
            ><span v-if="data.started_at">{{ formatDatetime(data.started_at, locale) }}</span
            ><EmptyValue v-else
          /></template>
        </Column>
        <Column :header="t('ret.policy')">
          <template #body="{ data }"
            ><span v-if="data.policy" dir="auto">{{ data.policy.name }}</span
            ><EmptyValue v-else
          /></template>
        </Column>
        <Column :header="t('ret.state')">
          <template #body="{ data }">
            <Tag :severity="severityOf(data.status)" :value="t(`ret.status.${data.status}`)" />
            <small v-if="data.error" class="text-danger block">{{ data.error }}</small>
          </template>
        </Column>
        <Column :header="t('ret.counts')">
          <template #body="{ data }"
            ><span class="text-sm">{{ t('ret.counts_text', { scanned: data.scanned, archived: data.archived, deleted: data.deleted, held: data.skipped_legal_hold }) }}</span></template
          >
        </Column>
        <Column :header="t('ret.by')">
          <template #body="{ data }"
            ><span v-if="data.triggered_by" dir="auto">{{ data.triggered_by }}</span
            ><span v-else class="text-muted-color">{{ t('ret.by_schedule') }}</span></template
          >
        </Column>
        <Column>
          <template #body="{ data }"
            ><a v-if="data.has_export" :href="retentionApi.exportUrl(data.uuid)" class="text-link text-sm" download>{{ t('ret.download_export') }}</a></template
          >
        </Column>
      </DataTable>
    </section>
  </div>
</template>
