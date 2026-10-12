<script setup lang="ts">
import Button from 'primevue/button'
import Column from 'primevue/column'
import DataTable from 'primevue/datatable'
import Message from 'primevue/message'
import Tag from 'primevue/tag'
import { useConfirm } from 'primevue/useconfirm'
import { useToast } from 'primevue/usetoast'
import { onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRouter } from 'vue-router'
import EmptyState from '@/components/config/EmptyState.vue'
import { errorText } from '../building/shared'
import { documentsApi, type DocumentSummary } from './api'

/**
 * Document templates (specification §4.17): Word files with placeholders and
 * designed templates, the form each belongs to, the formats it produces, and
 * how many notification rules attach it.
 */
const { t } = useI18n()
const router = useRouter()
const toast = useToast()
const confirm = useConfirm()
const rows = ref<DocumentSummary[]>([])
const pdfFromWord = ref(true)
const loading = ref(true)

async function load(): Promise<void> {
  loading.value = true
  try {
    const r = await documentsApi.list()
    rows.value = r.rows
    pdfFromWord.value = r.pdfFromWord
  } catch (e) {
    toast.add({ severity: 'error', summary: errorText(e, t('doc.load_failed')), life: 6000 })
  } finally {
    loading.value = false
  }
}
onMounted(load)

function open(uuid: string): void {
  void router.push({ name: 'admin.documents.template', params: { template: uuid } })
}
function remove(row: DocumentSummary): void {
  confirm.require({
    header: t('doc.delete_title'),
    message: t('doc.delete_text', { name: row.name }),
    acceptLabel: t('doc.delete'),
    rejectLabel: t('common.cancel'),
    acceptProps: { severity: 'danger' },
    accept: async () => {
      try {
        await documentsApi.remove(row.uuid)
        await load()
      } catch (e) {
        toast.add({ severity: 'error', summary: errorText(e, t('doc.delete_failed')), life: 6000 })
      }
    },
  })
}
</script>

<template>
  <div class="flex flex-col gap-6 max-w-[64rem]" data-testid="documents-view">
    <header class="flex flex-wrap items-start gap-3">
      <div class="flex-1 min-w-0">
        <h1 class="page-title !mb-1">{{ t('admin.area.documents') }}</h1>
        <p class="m-0 text-sm text-muted-color">{{ t('doc.subtitle') }}</p>
      </div>
      <Button icon="pi pi-plus" :label="t('doc.new')" size="small" data-testid="doc-new" @click="open('new')" />
    </header>
    <Message v-if="!pdfFromWord" severity="info">{{ t('doc.no_converter') }}</Message>

    <EmptyState v-if="!loading && !rows.length" icon="pi pi-file" :title="t('doc.empty_title')" :description="t('doc.empty_text')" testid="doc-empty">
      <Button icon="pi pi-plus" :label="t('doc.new')" size="small" @click="open('new')" />
    </EmptyState>
    <DataTable v-else :value="rows" data-key="uuid" :loading="loading" size="small" class="records-table" row-hover @row-click="(e) => open((e.data as DocumentSummary).uuid)">
      <Column :header="t('doc.name')">
        <template #body="{ data }">
          <span class="font-medium" dir="auto">{{ data.name }}</span>
        </template>
      </Column>
      <Column :header="t('doc.kind')" style="width: 10rem">
        <template #body="{ data }">{{ t(`doc.kind_${data.type}`) }}</template>
      </Column>
      <Column :header="t('doc.form')">
        <template #body="{ data }">
          <span v-if="data.form_name" dir="auto">{{ data.form_name }}</span>
          <span v-else class="text-muted-color">{{ t('doc.general') }}</span>
        </template>
      </Column>
      <Column :header="t('doc.formats')" style="width: 9rem">
        <template #body="{ data }">{{ (data.formats as string[]).map((f) => t(`doc.format_${f}`)).join(', ') }}</template>
      </Column>
      <Column :header="t('doc.state')" style="width: 8rem">
        <template #body="{ data }">
          <Tag :severity="data.active ? 'success' : 'secondary'" :value="data.active ? t('doc.active') : t('doc.inactive')" />
        </template>
      </Column>
      <Column style="width: 4rem">
        <template #body="{ data }">
          <Button
            v-tooltip.left="data.used_by > 0 ? t('doc.in_use', { n: data.used_by }) : undefined"
            icon="pi pi-trash"
            text
            rounded
            severity="danger"
            size="small"
            :disabled="data.used_by > 0"
            :aria-label="t('doc.delete')"
            @click.stop="remove(data)"
          />
        </template>
      </Column>
    </DataTable>
  </div>
</template>
