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
import { errorText } from '../building/shared'
import { channelsApi, type ChannelSummary } from './api'

/**
 * SMS and messaging channels (specification §4.33, ADR-0056): providers that
 * notification rules can send through, besides email and in-app, with their
 * delivery counts for the last seven days.
 */
const { t } = useI18n()
const toast = useToast()
const router = useRouter()
const channels = ref<ChannelSummary[]>([])
const loading = ref(true)

onMounted(async () => {
  try {
    channels.value = (await channelsApi.list()).data
  } catch (e) {
    toast.add({ severity: 'error', summary: errorText(e, t('nch.load_failed')), life: 6000 })
  } finally {
    loading.value = false
  }
})
const open = (channel: string) => router.push({ name: 'admin.notification_channels.channel', params: { channel } })
</script>

<template>
  <div class="flex flex-col gap-4" data-testid="channels-admin">
    <header class="page-head">
      <div class="flex-1 min-w-0">
        <h1 class="page-title !mb-1">{{ t('admin.area.notification_channels') }}</h1>
        <p class="m-0 text-sm text-muted-color max-w-[var(--measure)]">{{ t('nch.subtitle') }}</p>
      </div>
      <Button icon="pi pi-plus" :label="t('nch.new')" size="small" data-testid="channels-new" @click="open('new')" />
    </header>
    <EmptyState v-if="!loading && !channels.length" icon="pi pi-send" :title="t('nch.empty_title')" :description="t('nch.empty_text')" testid="channels-empty">
      <Button icon="pi pi-plus" :label="t('nch.new')" size="small" @click="open('new')" />
    </EmptyState>
    <DataTable v-else :value="channels" data-key="uuid" :loading="loading" size="small" class="records-table" row-hover @row-click="(e) => open((e.data as ChannelSummary).uuid)">
      <Column :header="t('nch.name')">
        <template #body="{ data }"
          ><span class="font-medium" dir="auto">{{ data.label }}</span></template
        >
      </Column>
      <Column :header="t('nch.kind')">
        <template #body="{ data }">{{ t(`nch.type.${data.type}`) }} · {{ t(`nch.provider.${data.provider}`) }}</template>
      </Column>
      <Column :header="t('nch.last_week')">
        <template #body="{ data }">
          <span class="flex flex-wrap gap-2 text-sm">
            <span>{{ t('nch.sent_n', { n: data.week.sent }) }}</span>
            <span v-if="data.week.failed" class="text-danger">{{ t('nch.failed_n', { n: data.week.failed }) }}</span>
            <span v-if="data.week.pending" class="text-muted-color">{{ t('nch.pending_n', { n: data.week.pending }) }}</span>
          </span>
        </template>
      </Column>
      <Column :header="t('nch.state')">
        <template #body="{ data }"><Tag :severity="data.active ? 'success' : 'secondary'" :value="data.active ? t('nch.active') : t('nch.inactive')" /></template>
      </Column>
    </DataTable>
  </div>
</template>
