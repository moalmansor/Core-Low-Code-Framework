<script setup lang="ts">
import Button from 'primevue/button'
import Column from 'primevue/column'
import DataTable from 'primevue/datatable'
import Tag from 'primevue/tag'
import { useConfirm } from 'primevue/useconfirm'
import { useToast } from 'primevue/usetoast'
import { onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import TabIntro from '@/components/config/TabIntro.vue'
import EmptyValue from '@/components/EmptyValue.vue'
import { formatDatetime } from '@/runtime/format'
import { errorText } from '../admin/building/shared'
import { retentionApi, type ArchiveEntry } from './api'

/** What retention moved to cold storage: archived records (restorable under their own id) and verified copies of logs. */
const { t, locale } = useI18n()
const toast = useToast()
const confirm = useConfirm()
const rows = ref<ArchiveEntry[]>([])
const total = ref(0)
const page = ref(1)
const loading = ref(true)
async function load(p = page.value): Promise<void> {
  loading.value = true
  try {
    const r = await retentionApi.archive(p)
    rows.value = r.data
    total.value = r.meta.total
    page.value = r.meta.page
  } catch (e) {
    toast.add({ severity: 'error', summary: errorText(e, t('ret.load_failed')), life: 6000 })
  } finally {
    loading.value = false
  }
}
onMounted(() => load(1))
const what = (a: ArchiveEntry) =>
  a.form
    ? a.form.label
    : t(
        `ret.source.${['audit_logs', 'error_logs', 'email_queue', 'in_app_notifications', 'notification_deliveries', 'submission_journal', 'download_jobs', 'files'].includes(a.source) ? a.source : 'other'}`,
      )
function restore(a: ArchiveEntry): void {
  confirm.require({
    header: t('ret.restore_title'),
    message: t('ret.restore_text'),
    acceptProps: { label: t('ret.restore') },
    rejectProps: { label: t('ret.cancel'), text: true },
    accept: async () => {
      try {
        await retentionApi.restore(a.id)
        toast.add({ severity: 'success', summary: t('ret.restored'), life: 4000 })
        await load()
      } catch (e) {
        toast.add({ severity: 'error', summary: errorText(e, t('ret.restore_failed')), life: 8000 })
      }
    },
  })
}
</script>

<template>
  <div class="flex flex-col gap-4" data-testid="ret-archive">
    <TabIntro :title="t('ret.tab.archive')" :text="t('ret.archive_intro')" />
    <DataTable
      :value="rows"
      data-key="id"
      :loading="loading"
      size="small"
      class="records-table"
      lazy
      paginator
      :rows="50"
      :total-records="total"
      :first="(page - 1) * 50"
      @page="(e) => load(e.page + 1)"
    >
      <template #empty>{{ t('ret.archive_empty') }}</template>
      <Column :header="t('ret.archived_at')">
        <template #body="{ data }"
          ><span v-if="data.archived_at">{{ formatDatetime(data.archived_at, locale) }}</span
          ><EmptyValue v-else
        /></template>
      </Column>
      <Column :header="t('ret.what')">
        <template #body="{ data }"
          ><span dir="auto">{{ what(data) }}</span></template
        >
      </Column>
      <Column :header="t('ret.state')">
        <template #body="{ data }">
          <Tag v-if="data.restored_at" severity="success" :value="t('ret.restored_on', { when: formatDatetime(data.restored_at, locale) })" />
          <Tag v-else severity="secondary" :value="t('ret.in_cold_storage')" />
        </template>
      </Column>
      <Column>
        <template #body="{ data }">
          <Button v-if="data.restorable" icon="pi pi-replay" :label="t('ret.restore')" size="small" text :data-testid="`ret-restore-${data.id}`" @click="restore(data)" />
        </template>
      </Column>
    </DataTable>
  </div>
</template>
