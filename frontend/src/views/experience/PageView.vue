<script setup lang="ts">
import Button from 'primevue/button'
import Message from 'primevue/message'
import { ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRoute } from 'vue-router'
import SafeHtml, { safeHref } from '@/runtime/SafeHtml'
import RecordList from '@/views/records/RecordList.vue'
import DashboardView from '@/views/reports/DashboardView.vue'
import ReportView from '@/views/reports/ReportView.vue'
import { errorText } from '../admin/building/shared'
import { pagesApi, type PageView } from './api'

/**
 * A custom page as the person opening it sees it (specification §4.29): its
 * content, a link, or the dashboard, report or form it hosts. The server
 * decides whether the page — and what it shows — is theirs to open.
 */
const { t } = useI18n()
const route = useRoute()
const page = ref<PageView | null>(null)
const failed = ref<string | null>(null)

async function load(): Promise<void> {
  failed.value = null
  page.value = null
  try {
    page.value = await pagesApi.view(String(route.params.page))
    document.title = page.value.label
  } catch (e) {
    failed.value = errorText(e, t('xp.page_unavailable'))
  }
}
watch(() => route.params.page, load, { immediate: true })

const isExternal = (url: string) => url.startsWith('https://')
</script>

<template>
  <div class="flex flex-col gap-4" data-testid="page-view">
    <Message v-if="failed" severity="warn" :closable="false" data-testid="page-failed">{{ failed }}</Message>
    <template v-else-if="page">
      <template v-if="page.type === 'content' || page.type === 'link'">
        <header class="page-head">
          <h1 class="page-title !mb-0" dir="auto">{{ page.label }}</h1>
        </header>
        <article v-if="page.type === 'content'" class="max-w-[60rem] rounded-xl border border-line bg-card p-6" data-testid="page-content">
          <SafeHtml :html="page.content ?? ''" />
        </article>
        <div v-else-if="page.url && safeHref(page.url)" class="flex flex-col items-start gap-3 max-w-[40rem]">
          <p class="m-0 text-muted-color">{{ isExternal(page.url) ? t('xp.link_external') : t('xp.link_internal') }}</p>
          <a v-if="isExternal(page.url)" :href="safeHref(page.url)!" target="_blank" rel="noopener noreferrer" data-testid="page-link">
            <Button as="span" icon="pi pi-external-link" :label="t('xp.open_link')" />
          </a>
          <RouterLink v-else :to="page.url" data-testid="page-link"><Button as="span" icon="pi pi-arrow-right rtl:rotate-180" :label="t('xp.open_link')" /></RouterLink>
          <span class="ltr-value text-sm text-muted-color" dir="ltr">{{ page.url }}</span>
        </div>
      </template>
      <DashboardView v-else-if="page.type === 'dashboard' && page.dashboard" :key="page.dashboard" :dashboard="page.dashboard" embedded />
      <ReportView v-else-if="page.type === 'report' && page.report" :key="page.report" :report="page.report" embedded />
      <RecordList v-else-if="page.type === 'form_host' && page.form" :key="page.form" :form="page.form" />
    </template>
  </div>
</template>
