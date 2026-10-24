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
import { joinList } from '@/runtime/lists'
import { errorText } from '../admin/building/shared'
import { webhooksApi, type WebhookDoc } from './api'

/** Outgoing webhooks: what each sends, where, and how the last day went. */
const { t, locale } = useI18n()
const router = useRouter()
const toast = useToast()
const rows = ref<WebhookDoc[]>([])
const loading = ref(true)
onMounted(async () => {
  try {
    rows.value = await webhooksApi.list()
  } catch (e) {
    toast.add({ severity: 'error', summary: errorText(e, t('intg.load_failed')), life: 6000 })
  } finally {
    loading.value = false
  }
})
function open(uuid: string): void {
  void router.push({ name: 'admin.integrations.webhook', params: { webhook: uuid } })
}
const host = (url: string) => {
  try {
    return new URL(url).host
  } catch {
    return url
  }
}
</script>

<template>
  <div class="flex flex-col gap-4" data-testid="webhooks-panel">
    <div class="flex flex-wrap items-start gap-3">
      <TabIntro class="flex-1 min-w-0" :title="t('intg.tab.webhooks')" :text="t('intg.webhooks_intro')" />
      <Button icon="pi pi-plus" :label="t('intg.webhook_new')" size="small" data-testid="webhook-new" @click="open('new')" />
    </div>
    <EmptyState v-if="!loading && !rows.length" icon="pi pi-send" :title="t('intg.webhooks_empty_title')" :description="t('intg.webhooks_empty_text')" testid="webhooks-empty">
      <Button icon="pi pi-plus" :label="t('intg.webhook_new')" size="small" @click="open('new')" />
    </EmptyState>
    <DataTable v-else :value="rows" data-key="uuid" :loading="loading" size="small" class="records-table" row-hover @row-click="(e) => open((e.data as WebhookDoc).uuid!)">
      <Column :header="t('intg.name')">
        <template #body="{ data }">
          <span class="font-medium" dir="auto">{{ data.label }}</span>
        </template>
      </Column>
      <Column :header="t('intg.address')">
        <template #body="{ data }"
          ><span class="ltr-value">{{ data.method }} {{ host(data.url) }}</span></template
        >
      </Column>
      <Column :header="t('intg.form')">
        <template #body="{ data }">
          <span v-if="data.form_name" dir="auto">{{ data.form_name }}</span>
          <span v-else class="text-muted-color">{{ t('intg.every_form') }}</span>
        </template>
      </Column>
      <Column :header="t('intg.events')">
        <template #body="{ data }">{{
          joinList(
            (data.events as string[]).map((e) => t(`intg.event_name.${e}`)),
            locale,
          )
        }}</template>
      </Column>
      <Column :header="t('intg.last_day')">
        <template #body="{ data }">
          <span class="text-sm">{{ t('intg.last_day_counts', { ok: data.last_day?.succeeded ?? 0, failed: (data.last_day?.failed ?? 0) + (data.last_day?.blocked ?? 0) }) }}</span>
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
