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
import { externalFormsApi, type ExternalFormSummary } from './api'

/** The external forms: where each is published, who may use it, and whether it takes submissions now. */
const { t } = useI18n()
const toast = useToast()
const router = useRouter()
const forms = ref<ExternalFormSummary[]>([])
const loading = ref(true)

onMounted(async () => {
  try {
    forms.value = await externalFormsApi.list()
  } catch (e) {
    toast.add({ severity: 'error', summary: errorText(e, t('xa.load_failed')), life: 6000 })
  } finally {
    loading.value = false
  }
})
const open = (form: string) => router.push({ name: 'admin.external.form', params: { form } })
const state = (f: ExternalFormSummary) => (f.availability === null ? 'open' : f.availability)
</script>

<template>
  <div class="flex flex-col gap-4" data-testid="xa-forms">
    <div class="flex flex-wrap items-start gap-3">
      <p class="m-0 text-sm text-muted-color flex-1 min-w-0 max-w-[var(--measure)]">{{ t('xa.forms_hint') }}</p>
      <Button icon="pi pi-plus" :label="t('xa.new_form')" size="small" data-testid="xa-new-form" @click="open('new')" />
    </div>
    <EmptyState v-if="!loading && !forms.length" icon="pi pi-globe" :title="t('xa.forms_empty')" :description="t('xa.forms_empty_text')" testid="xa-forms-empty" />
    <DataTable v-else :value="forms" data-key="uuid" :loading="loading" size="small" class="records-table" row-hover @row-click="(e) => open((e.data as ExternalFormSummary).uuid)">
      <Column :header="t('xa.title')">
        <template #body="{ data }">
          <div class="flex flex-col">
            <span class="font-medium" dir="auto">{{ data.label }}</span>
            <a :href="data.url" class="text-sm text-link ltr-value" target="_blank" rel="noopener" @click.stop>{{ data.url }}</a>
          </div>
        </template>
      </Column>
      <Column :header="t('xa.form')">
        <template #body="{ data }"
          ><span v-if="data.form_name" dir="auto">{{ data.form_name }}</span
          ><EmptyValue v-else
        /></template>
      </Column>
      <Column :header="t('xa.audience')">
        <template #body="{ data }">{{ t(`xa.audience_${data.audience}`) }}</template>
      </Column>
      <Column :header="t('xa.submissions')">
        <template #body="{ data }">{{ data.cap === null ? data.submissions : t('xa.of_cap', { n: data.submissions, cap: data.cap }) }}</template>
      </Column>
      <Column :header="t('xa.state')">
        <template #body="{ data }"><Tag :severity="state(data) === 'open' ? 'success' : 'secondary'" :value="t(`xa.availability.${state(data)}`)" /></template>
      </Column>
    </DataTable>
  </div>
</template>
