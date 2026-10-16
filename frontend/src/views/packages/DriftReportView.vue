<script setup lang="ts">
import Message from 'primevue/message'
import Tag from 'primevue/tag'
import { useToast } from 'primevue/usetoast'
import { onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRoute } from 'vue-router'
import ConfigSection from '@/components/config/ConfigSection.vue'
import { errorText, formatDateTime } from '../admin/building/shared'
import { packagesApi, type DriftReport } from './api'
import ReportTable from './ReportTable.vue'

/** A drift report: a package (or another environment's export) compared with this environment. */
const { t, locale } = useI18n()
const toast = useToast()
const route = useRoute()
const report = ref<DriftReport | null>(null)
onMounted(async () => {
  try {
    report.value = await packagesApi.report(String(route.params.report))
  } catch (e) {
    toast.add({ severity: 'error', summary: errorText(e, t('pkg.load_failed')), life: 6000 })
  }
})
const nameOf = (uuid: string) => report.value?.objects?.find((o) => o.uuid === uuid)?.label ?? ''
</script>

<template>
  <div class="flex flex-col gap-6 max-w-[64rem]" data-testid="drift-report">
    <header class="page-head">
      <div class="flex-1 min-w-0">
        <RouterLink :to="{ name: 'admin.packages', params: { tab: 'reports' } }" class="text-sm text-primary inline-flex items-center gap-1 mb-1">
          <i class="pi pi-arrow-left rtl:rotate-180" aria-hidden="true" />{{ t('pkg.tab.reports') }}
        </RouterLink>
        <h1 class="page-title !mb-1">{{ t('pkg.report_title') }}</h1>
        <p v-if="report" class="m-0 text-sm text-muted-color" dir="auto">
          {{ t('pkg.compared_text', { source: report.source, target: report.target, when: formatDateTime(report.created_at, locale) }) }}
        </p>
      </div>
      <Tag v-if="report" :severity="report.ambiguous ? 'danger' : 'success'" :value="report.ambiguous ? t('pkg.ambiguous') : t('pkg.importable')" />
    </header>
    <template v-if="report">
      <Message v-if="report.ambiguous" severity="error" :closable="false">{{ t('pkg.ambiguous_text') }}</Message>
      <ConfigSection id="drift-objects" :title="t('pkg.objects')" :description="t('pkg.report_hint')">
        <ReportTable :rows="report.objects ?? []" />
      </ConfigSection>
      <ConfigSection v-if="report.schema?.length" id="drift-schema" :title="t('pkg.schema_title')" :description="t('pkg.schema_hint')">
        <ul class="m-0 ps-5 text-sm flex flex-col gap-1">
          <li v-for="s in report.schema" :key="s.form">{{ t('pkg.schema_row', { name: nameOf(s.form), n: s.differences.length }) }}</li>
        </ul>
      </ConfigSection>
    </template>
  </div>
</template>
