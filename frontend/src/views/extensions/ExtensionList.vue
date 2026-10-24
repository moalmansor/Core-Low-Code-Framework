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
import { errorText } from '../admin/building/shared'
import { extensionsApi, statusSeverity, type ExtSummary } from './api'

/**
 * Developer extensions (specification §4.19): every server hook, custom
 * endpoint and client extension, what it belongs to, where it stands in
 * review, and which version runs.
 */
const { t } = useI18n()
const router = useRouter()
const toast = useToast()
const rows = ref<ExtSummary[]>([])
const canWrite = ref(false)
const loading = ref(true)
onMounted(async () => {
  try {
    const r = await extensionsApi.list()
    rows.value = r.rows
    canWrite.value = r.canWrite
  } catch (e) {
    toast.add({ severity: 'error', summary: errorText(e, t('ext.load_failed')), life: 6000 })
  } finally {
    loading.value = false
  }
})
function open(uuid: string): void {
  void router.push({ name: 'admin.extensions.edit', params: { extension: uuid } })
}
</script>

<template>
  <div class="flex flex-col gap-6 max-w-[64rem]" data-testid="extensions-view">
    <header class="page-head">
      <div class="flex-1 min-w-0">
        <h1 class="page-title !mb-1">{{ t('admin.area.extensions') }}</h1>
        <p class="m-0 text-sm text-muted-color max-w-[var(--measure)]">{{ t('ext.subtitle') }}</p>
      </div>
      <Button v-if="canWrite" icon="pi pi-plus" :label="t('ext.new')" size="small" data-testid="ext-new" @click="open('new')" />
    </header>
    <EmptyState v-if="!loading && !rows.length" icon="pi pi-code" :title="t('ext.empty_title')" :description="t('ext.empty_text')" testid="ext-empty">
      <Button v-if="canWrite" icon="pi pi-plus" :label="t('ext.new')" size="small" @click="open('new')" />
    </EmptyState>
    <DataTable v-else :value="rows" data-key="uuid" :loading="loading" size="small" class="records-table" row-hover @row-click="(e) => open((e.data as ExtSummary).uuid)">
      <Column :header="t('ext.key')">
        <template #body="{ data }"
          ><span class="font-medium ltr-value">{{ data.key }}</span></template
        >
      </Column>
      <Column :header="t('ext.type')">
        <template #body="{ data }">
          {{ t(`ext.type_name.${data.type}`) }}
          <span v-if="data.hook_point" class="text-muted-color ltr-value"> · {{ data.hook_point }}</span>
        </template>
      </Column>
      <Column :header="t('ext.form')">
        <template #body="{ data }">
          <span v-if="data.form_name" dir="auto">{{ data.form_name }}</span>
          <EmptyValue v-else />
        </template>
      </Column>
      <Column :header="t('ext.status')">
        <template #body="{ data }">
          <Tag :severity="statusSeverity(data.status)" :value="t(`ext.status_name.${data.status}`)" />
        </template>
      </Column>
      <Column :header="t('ext.running')">
        <template #body="{ data }">
          <span v-if="data.deployed_version">{{ t('ext.version_n', { n: data.deployed_version }) }}</span>
          <EmptyValue v-else :text="t('ext.not_running')" />
        </template>
      </Column>
    </DataTable>
  </div>
</template>
