<script setup lang="ts">
import Button from 'primevue/button'
import Column from 'primevue/column'
import DataTable from 'primevue/datatable'
import Tag from 'primevue/tag'
import { useConfirm } from 'primevue/useconfirm'
import { useToast } from 'primevue/usetoast'
import { onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRouter } from 'vue-router'
import EmptyState from '@/components/config/EmptyState.vue'
import { errorText } from '../building/shared'
import { templatesApi, type TemplateSummary } from './api'

/**
 * Email templates (specification §4.16): the list, with the form each
 * belongs to and how many notification rules use it. Opening one leads to
 * the designer.
 */
const { t } = useI18n()
const router = useRouter()
const toast = useToast()
const confirm = useConfirm()
const rows = ref<TemplateSummary[]>([])
const loading = ref(true)

async function load(): Promise<void> {
  loading.value = true
  try {
    rows.value = await templatesApi.list()
  } catch (e) {
    toast.add({ severity: 'error', summary: errorText(e, t('tpl.load_failed')), life: 6000 })
  } finally {
    loading.value = false
  }
}
onMounted(load)

function open(uuid: string): void {
  void router.push({ name: 'admin.notifications.template', params: { template: uuid } })
}
function remove(row: TemplateSummary): void {
  confirm.require({
    header: t('tpl.delete_title'),
    message: t('tpl.delete_text', { name: row.subject }),
    acceptLabel: t('tpl.delete'),
    rejectLabel: t('common.cancel'),
    acceptProps: { severity: 'danger' },
    accept: async () => {
      try {
        await templatesApi.remove(row.uuid)
        await load()
      } catch (e) {
        toast.add({ severity: 'error', summary: errorText(e, t('tpl.delete_failed')), life: 6000 })
      }
    },
  })
}
</script>

<template>
  <div class="flex flex-col gap-6 max-w-[64rem]" data-testid="templates-view">
    <header class="page-head">
      <div class="flex-1 min-w-0">
        <h1 class="page-title !mb-1">{{ t('admin.area.notifications') }}</h1>
        <p class="m-0 text-sm text-muted-color">{{ t('tpl.subtitle') }}</p>
      </div>
      <Button icon="pi pi-plus" :label="t('tpl.new')" size="small" data-testid="tpl-new" @click="open('new')" />
    </header>

    <EmptyState v-if="!loading && !rows.length" icon="pi pi-envelope" :title="t('tpl.empty_title')" :description="t('tpl.empty_text')" testid="tpl-empty">
      <Button icon="pi pi-plus" :label="t('tpl.new')" size="small" @click="open('new')" />
    </EmptyState>
    <DataTable v-else :value="rows" data-key="uuid" :loading="loading" size="small" class="records-table" row-hover @row-click="(e) => open((e.data as TemplateSummary).uuid)">
      <Column :header="t('tpl.subject')">
        <template #body="{ data }">
          <span class="font-medium" dir="auto">{{ data.subject }}</span>
        </template>
      </Column>
      <Column :header="t('tpl.form')">
        <template #body="{ data }">
          <span v-if="data.form_name" dir="auto">{{ data.form_name }}</span>
          <span v-else class="text-muted-color">{{ t('tpl.general') }}</span>
        </template>
      </Column>
      <Column :header="t('tpl.used_by')" style="width: 9rem">
        <template #body="{ data }">{{ t('tpl.rules', { n: data.used_by }) }}</template>
      </Column>
      <Column :header="t('tpl.state')" style="width: 8rem">
        <template #body="{ data }">
          <Tag :severity="data.active ? 'success' : 'secondary'" :value="data.active ? t('tpl.active') : t('tpl.inactive')" />
        </template>
      </Column>
      <Column style="width: 4rem">
        <template #body="{ data }">
          <Button icon="pi pi-trash" text rounded severity="danger" size="small" :disabled="data.used_by > 0" :aria-label="t('tpl.delete')" @click.stop="remove(data)" />
        </template>
      </Column>
    </DataTable>
  </div>
</template>
