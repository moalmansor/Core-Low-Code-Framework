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
import { errorText } from '../admin/building/shared'
import { inboundApi, type InboundDoc } from './api'

/** Incoming endpoints: their address, form, what they do and how the last day went. */
const { t } = useI18n()
const router = useRouter()
const toast = useToast()
const rows = ref<InboundDoc[]>([])
const loading = ref(true)
onMounted(async () => {
  try {
    rows.value = await inboundApi.list()
  } catch (e) {
    toast.add({ severity: 'error', summary: errorText(e, t('intg.load_failed')), life: 6000 })
  } finally {
    loading.value = false
  }
})
function open(uuid: string): void {
  void router.push({ name: 'admin.integrations.inbound', params: { endpoint: uuid } })
}
</script>

<template>
  <div class="flex flex-col gap-4" data-testid="inbound-panel">
    <div class="flex flex-wrap items-start gap-3">
      <TabIntro class="flex-1 min-w-0" :title="t('intg.tab.inbound')" :text="t('intg.inbound_intro')" />
      <Button icon="pi pi-plus" :label="t('intg.inbound_new')" size="small" data-testid="inbound-new" @click="open('new')" />
    </div>
    <EmptyState v-if="!loading && !rows.length" icon="pi pi-download" :title="t('intg.inbound_empty_title')" :description="t('intg.inbound_empty_text')" testid="inbound-empty">
      <Button icon="pi pi-plus" :label="t('intg.inbound_new')" size="small" @click="open('new')" />
    </EmptyState>
    <DataTable v-else :value="rows" data-key="uuid" :loading="loading" size="small" class="records-table" row-hover @row-click="(e) => open((e.data as InboundDoc).uuid!)">
      <Column :header="t('intg.address')">
        <template #body="{ data }"
          ><span class="ltr-value font-medium">/api/inbound/{{ data.slug }}</span></template
        >
      </Column>
      <Column :header="t('intg.form')">
        <template #body="{ data }"
          ><span dir="auto">{{ data.form_name }}</span></template
        >
      </Column>
      <Column :header="t('intg.inbound_kind')">
        <template #body="{ data }">{{ t(`intg.kind.${data.kind}`) }}</template>
      </Column>
      <Column :header="t('intg.operation')">
        <template #body="{ data }">{{ t(`intg.operation_name.${data.operation}`) }}</template>
      </Column>
      <Column :header="t('intg.last_day')">
        <template #body="{ data }">
          <span class="text-sm">{{ t('intg.last_day_counts', { ok: data.last_day?.succeeded ?? 0, failed: (data.last_day?.failed ?? 0) + (data.last_day?.signature_invalid ?? 0) }) }}</span>
        </template>
      </Column>
      <Column :header="t('intg.state')" style="width: 8rem">
        <template #body="{ data }">
          <Tag :severity="data.is_active ? 'success' : 'secondary'" :value="data.is_active ? t('intg.active') : t('intg.paused')" />
        </template>
      </Column>
    </DataTable>
  </div>
</template>
