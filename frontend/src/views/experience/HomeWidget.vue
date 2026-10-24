<script setup lang="ts">
import Button from 'primevue/button'
import { onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { get } from '@/api/http'
import EmptyValue from '@/components/EmptyValue.vue'
import SafeHtml, { safeHref } from '@/runtime/SafeHtml'
import type { RecordPayload } from '@/runtime/types'
import { formatDateTime } from '../admin/building/shared'
import { reportsApi, type ReportData } from '../reports/api'
import ReportChart from '../reports/ReportChart.vue'
import { homeApi, type ActivityItem, type ShownHomeWidget } from './api'

/**
 * One widget of a home screen. It loads its own data through the ordinary,
 * permission-checked endpoints, so it never shows more than the person could
 * open themselves; a widget whose data is refused says so quietly.
 */
const props = defineProps<{ widget: ShownHomeWidget }>()
const { t, locale } = useI18n()

interface WorkItem {
  assignment: string
  kind: 'work' | 'approval'
  form: { uuid: string; name: string }
  record: { uuid: string; title: string | null; number: string | null }
  due_at: string | null
  overdue: boolean
}
interface Announcement {
  uuid: string
  title: string | null
  body: string | null
  severity: 'info' | 'success' | 'warning' | 'danger'
}

const loading = ref(true)
const failed = ref(false)
const work = ref<WorkItem[]>([])
const records = ref<RecordPayload[]>([])
const activity = ref<ActivityItem[]>([])
const report = ref<ReportData | null>(null)
const news = ref<Announcement[]>([])

onMounted(async () => {
  const w = props.widget
  try {
    if (w.type === 'my_work') work.value = (await get<{ data: WorkItem[] }>('/my-work', { per_page: w.limit })).data
    else if ((w.type === 'my_records' || w.type === 'embedded_table') && w.form)
      records.value = (await get<{ data: RecordPayload[] }>(`/r/${w.form}`, { per_page: w.limit, mine: w.type === 'my_records' ? 1 : undefined })).data
    else if (w.type === 'recent_activity') activity.value = await homeApi.activity(w.limit)
    else if (w.type === 'report' && w.report) report.value = await reportsApi.data(w.report)
    else if (w.type === 'announcements') news.value = (await get<{ data: Announcement[] }>('/announcements/active', { placement: 'home' })).data.slice(0, w.limit)
  } catch {
    failed.value = true
  } finally {
    loading.value = false
  }
})
const height = (h: number) => Math.max(140, h * 150 - 60)
const formOf = props.widget.form
</script>

<template>
  <section class="home-widget" :class="`cols-${widget.layout.w}`" :data-testid="`home-widget-${widget.type}`">
    <h2 v-if="widget.title" class="m-0 text-sm font-semibold" dir="auto">{{ widget.title }}</h2>
    <p v-if="failed" class="m-0 text-sm text-muted-color">{{ t('xp.widget_unavailable') }}</p>
    <p v-else-if="loading" class="m-0 text-sm text-muted-color">{{ t('xp.loading') }}</p>
    <template v-else>
      <SafeHtml v-if="widget.type === 'html'" :html="widget.text ?? ''" />

      <ul v-else-if="widget.type === 'my_work'" class="home-list">
        <li v-if="!work.length" class="text-sm text-muted-color">{{ t('xp.nothing_waiting') }}</li>
        <li v-for="i in work" :key="i.assignment">
          <RouterLink :to="{ name: 'records.view', params: { form: i.form.uuid, record: i.record.uuid } }" class="truncate" dir="auto">{{
            i.record.title ?? i.record.number ?? i.form.name
          }}</RouterLink>
          <span class="text-xs" :class="i.overdue ? 'text-danger' : 'text-muted-color'">
            <template v-if="i.due_at">{{ formatDateTime(i.due_at, locale) }}</template
            ><EmptyValue v-else />
          </span>
        </li>
        <li v-if="work.length">
          <RouterLink :to="{ name: 'my_work' }" class="text-xs text-primary">{{ t('xp.open_my_work') }}</RouterLink>
        </li>
      </ul>

      <ul v-else-if="widget.type === 'my_records' || widget.type === 'embedded_table'" class="home-list">
        <li v-if="!records.length" class="text-sm text-muted-color">{{ t('xp.no_records') }}</li>
        <li v-for="r in records" :key="r.uuid">
          <RouterLink v-if="formOf" :to="{ name: 'records.view', params: { form: formOf, record: r.uuid } }" class="truncate" dir="auto">{{ r.title ?? r.system.record_number ?? r.uuid }}</RouterLink>
          <span v-if="r.system.status" class="text-xs text-muted-color" dir="auto">{{ r.system.status.name }}</span>
          <span class="text-xs text-muted-color"
            ><template v-if="r.system.updated_at">{{ formatDateTime(r.system.updated_at, locale) }}</template
            ><EmptyValue v-else
          /></span>
        </li>
        <li v-if="formOf">
          <RouterLink :to="{ name: 'records.list', params: { form: formOf } }" class="text-xs text-primary">{{ t('xp.open_list') }}</RouterLink>
        </li>
      </ul>

      <ul v-else-if="widget.type === 'recent_activity'" class="home-list">
        <li v-if="!activity.length" class="text-sm text-muted-color">{{ t('xp.no_activity') }}</li>
        <li v-for="a in activity" :key="`${a.form.uuid}-${a.record.uuid}`">
          <RouterLink :to="{ name: 'records.view', params: { form: a.form.uuid, record: a.record.uuid } }" class="truncate" dir="auto">{{ a.record.title ?? a.form.name }}</RouterLink>
          <span class="text-xs text-muted-color">{{ t(`xp.operation.${a.operation}`) }} · {{ formatDateTime(a.at, locale) }}</span>
        </li>
      </ul>

      <template v-else-if="widget.type === 'report' && report">
        <ReportChart :result="report.result" :visualization="report.visualization" :options="report.options" :height="height(widget.layout.h)" />
        <RouterLink :to="{ name: 'reports.view', params: { report: report.uuid } }" class="text-xs text-primary self-start">{{ t('rep.open_report') }}</RouterLink>
      </template>

      <div v-else-if="widget.type === 'shortcuts'" class="flex flex-wrap gap-2">
        <RouterLink v-for="(l, i) in widget.links" :key="i" :to="l.action === 'new' ? { name: 'records.create', params: { form: l.form } } : { name: 'records.list', params: { form: l.form } }">
          <Button as="span" :icon="l.action === 'new' ? 'pi pi-plus' : 'pi pi-list'" :label="l.label ?? t('xp.open')" size="small" outlined />
        </RouterLink>
      </div>

      <ul v-else-if="widget.type === 'pinned_links'" class="home-list">
        <li v-for="(l, i) in widget.links" :key="i">
          <a v-if="l.url && l.url.startsWith('https://') && safeHref(l.url)" :href="safeHref(l.url)!" target="_blank" rel="noopener noreferrer" dir="auto"
            >{{ l.label }} <i class="pi pi-external-link text-xs" aria-hidden="true"
          /></a>
          <RouterLink v-else-if="l.url" :to="l.url" dir="auto">{{ l.label }}</RouterLink>
        </li>
      </ul>

      <ul v-else-if="widget.type === 'announcements'" class="home-list">
        <li v-if="!news.length" class="text-sm text-muted-color">{{ t('xp.no_announcements') }}</li>
        <li v-for="n in news" :key="n.uuid" class="home-news" :class="`sev-${n.severity}`">
          <strong class="text-sm" dir="auto">{{ n.title }}</strong>
          <SafeHtml v-if="n.body" :html="n.body" class="text-sm" />
        </li>
      </ul>
    </template>
  </section>
</template>

<style scoped>
.home-widget {
  display: flex;
  flex-direction: column;
  gap: 0.5rem;
  min-width: 0;
  padding: 1rem;
  border: 1px solid var(--border);
  border-radius: var(--radius-card);
  background: var(--bg-surface);
}
.home-list {
  list-style: none;
  margin: 0;
  padding: 0;
  display: flex;
  flex-direction: column;
  gap: 0.5rem;
}
.home-list li {
  display: flex;
  align-items: baseline;
  gap: 0.5rem;
  min-width: 0;
}
.home-list li > :first-child {
  flex: 1;
  min-width: 0;
}
.home-news {
  flex-direction: column;
  align-items: stretch !important;
  padding-inline-start: 0.75rem;
  border-inline-start: 3px solid var(--border-strong);
}
.home-news.sev-info {
  border-inline-start-color: var(--primary);
}
.home-news.sev-success {
  border-inline-start-color: var(--success);
}
.home-news.sev-warning {
  border-inline-start-color: var(--warning);
}
.home-news.sev-danger {
  border-inline-start-color: var(--danger);
}
.cols-3 {
  grid-column: span 3;
}
.cols-4 {
  grid-column: span 4;
}
.cols-5 {
  grid-column: span 5;
}
.cols-6 {
  grid-column: span 6;
}
.cols-7 {
  grid-column: span 7;
}
.cols-8 {
  grid-column: span 8;
}
.cols-9 {
  grid-column: span 9;
}
.cols-10 {
  grid-column: span 10;
}
.cols-11 {
  grid-column: span 11;
}
.cols-12 {
  grid-column: span 12;
}
@media (max-width: 900px) {
  .home-widget {
    grid-column: span 12;
  }
}
</style>
