<script setup lang="ts">
import { useToast } from 'primevue/usetoast'
import { onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import EmptyState from '@/components/config/EmptyState.vue'
import { errorText } from '../admin/building/shared'
import { reportsApi } from './api'

/** The reports and dashboards shared with the signed-in person. */
const { t } = useI18n()
const toast = useToast()
const data = ref<Awaited<ReturnType<typeof reportsApi.mine>> | null>(null)
onMounted(async () => {
  try {
    data.value = await reportsApi.mine()
  } catch (e) {
    toast.add({ severity: 'error', summary: errorText(e, t('rep.load_failed')), life: 6000 })
  }
})
const VIZ_ICON: Record<string, string> = {
  table: 'pi pi-table',
  pivot: 'pi pi-th-large',
  bar: 'pi pi-chart-bar',
  line: 'pi pi-chart-line',
  area: 'pi pi-wave-pulse',
  pie: 'pi pi-chart-pie',
  kpi: 'pi pi-hashtag',
}
</script>

<template>
  <div class="flex flex-col gap-6" data-testid="insights-view">
    <div>
      <h1 class="page-title !mb-1">{{ t('rep.insights') }}</h1>
      <p class="m-0 text-sm text-muted-color max-w-[var(--measure)]">{{ t('rep.insights_subtitle') }}</p>
    </div>
    <template v-if="data">
      <EmptyState
        v-if="!data.reports.length && !data.dashboards.length"
        icon="pi pi-chart-bar"
        :title="t('rep.insights_empty_title')"
        :description="t('rep.insights_empty_text')"
        testid="insights-empty"
      />
      <section v-if="data.dashboards.length" class="flex flex-col gap-3">
        <h2 class="m-0 text-base font-semibold">{{ t('rep.tab.dashboards') }}</h2>
        <ul class="insight-grid">
          <li v-for="d in data.dashboards" :key="d.uuid">
            <RouterLink :to="{ name: 'dashboards.view', params: { dashboard: d.uuid } }" class="insight-card" :data-testid="`insight-dashboard-${d.uuid}`">
              <i class="pi pi-th-large text-muted-color" aria-hidden="true" />
              <span class="font-medium" dir="auto">{{ d.label }}</span>
              <span v-if="d.description" class="text-sm text-muted-color" dir="auto">{{ d.description }}</span>
            </RouterLink>
          </li>
        </ul>
      </section>
      <section v-if="data.reports.length" class="flex flex-col gap-3">
        <h2 class="m-0 text-base font-semibold">{{ t('rep.tab.reports') }}</h2>
        <ul class="insight-grid">
          <li v-for="r in data.reports" :key="r.uuid">
            <RouterLink :to="{ name: 'reports.view', params: { report: r.uuid } }" class="insight-card" :data-testid="`insight-report-${r.uuid}`">
              <i :class="[VIZ_ICON[r.visualization], 'text-muted-color']" aria-hidden="true" />
              <span class="font-medium" dir="auto">{{ r.label }}</span>
              <span v-if="r.description" class="text-sm text-muted-color" dir="auto">{{ r.description }}</span>
            </RouterLink>
          </li>
        </ul>
      </section>
    </template>
  </div>
</template>

<style scoped>
.insight-grid {
  list-style: none;
  margin: 0;
  padding: 0;
  display: grid;
  gap: 0.75rem;
  grid-template-columns: repeat(auto-fill, minmax(min(100%, 16rem), 1fr));
}
.insight-card {
  display: flex;
  flex-direction: column;
  gap: 0.25rem;
  padding: 0.875rem 1rem;
  border: 1px solid var(--border);
  border-radius: var(--radius-card);
  background: var(--bg-surface);
  color: var(--text);
  text-decoration: none;
  height: 100%;
}
.insight-card:hover {
  border-color: var(--border-strong);
}
.insight-card:focus-visible {
  outline: 2px solid var(--focus-ring);
  outline-offset: 2px;
}
</style>
