<script setup lang="ts">
import Button from 'primevue/button'
import Column from 'primevue/column'
import DataTable from 'primevue/datatable'
import Tag from 'primevue/tag'
import { useToast } from 'primevue/usetoast'
import { computed, onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRoute, useRouter } from 'vue-router'
import { get } from '@/api/http'
import EmptyState from '@/components/config/EmptyState.vue'
import { humanize } from '@/runtime/i18nText'
import { errorText, formatDateTime } from '../admin/building/shared'
import { pagesApi, type PageSummary } from './api'

/** The pages of one application (specification §4.29): content, links, and pages showing a dashboard, report or form. */
const { t, locale } = useI18n()
const toast = useToast()
const route = useRoute()
const router = useRouter()
const app = computed(() => String(route.params.application))
const appName = ref('')
const pages = ref<PageSummary[]>([])
const loading = ref(true)

onMounted(async () => {
  try {
    const [apps, list] = await Promise.all([get<{ data: { uuid: string; key: string; name: string }[] }>('/applications'), pagesApi.list(app.value)])
    const a = apps.data.find((x) => x.uuid === app.value)
    appName.value = a ? a.name || humanize(a.key) : ''
    pages.value = list
  } catch (e) {
    toast.add({ severity: 'error', summary: errorText(e, t('xp.load_failed')), life: 6000 })
  } finally {
    loading.value = false
  }
})
const open = (page: string) => router.push({ name: 'admin.applications.page', params: { application: app.value, page } })
</script>

<template>
  <div class="flex flex-col gap-4" data-testid="pages-admin">
    <header class="page-head">
      <div class="flex-1 min-w-0">
        <RouterLink :to="{ name: 'admin.applications' }" class="text-sm text-primary inline-flex items-center gap-1 mb-1">
          <i class="pi pi-arrow-left rtl:rotate-180" aria-hidden="true" />{{ t('admin.area.applications') }}
        </RouterLink>
        <h1 class="page-title !mb-1" dir="auto">{{ t('xp.pages_title', { name: appName }) }}</h1>
        <p class="m-0 text-sm text-muted-color max-w-[var(--measure)]">{{ t('xp.pages_subtitle') }}</p>
      </div>
      <Button icon="pi pi-plus" :label="t('xp.new_page')" size="small" data-testid="page-new" @click="open('new')" />
    </header>
    <EmptyState v-if="!loading && !pages.length" icon="pi pi-book" :title="t('xp.pages_empty_title')" :description="t('xp.pages_empty_text')" testid="pages-empty">
      <Button icon="pi pi-plus" :label="t('xp.new_page')" size="small" @click="open('new')" />
    </EmptyState>
    <DataTable v-else :value="pages" data-key="uuid" :loading="loading" size="small" class="records-table" row-hover @row-click="(e) => open((e.data as PageSummary).uuid)">
      <Column :header="t('xp.name')">
        <template #body="{ data }"
          ><span class="font-medium" dir="auto">{{ data.label }}</span></template
        >
      </Column>
      <Column :header="t('xp.shows')">
        <template #body="{ data }">{{ t(`xp.type.${data.type}`) }}</template>
      </Column>
      <Column :header="t('xp.state')">
        <template #body="{ data }"><Tag :severity="data.published ? 'success' : 'secondary'" :value="data.published ? t('xp.published') : t('xp.draft')" /></template>
      </Column>
      <Column :header="t('xp.changed')">
        <template #body="{ data }">{{ formatDateTime(data.updated_at, locale) }}</template>
      </Column>
    </DataTable>
  </div>
</template>
