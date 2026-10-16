<script setup lang="ts">
import Button from 'primevue/button'
import Column from 'primevue/column'
import DataTable from 'primevue/datatable'
import Message from 'primevue/message'
import Select from 'primevue/select'
import Tag from 'primevue/tag'
import { computed, onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import EmptyValue from '@/components/EmptyValue.vue'
import EmptyState from '@/components/config/EmptyState.vue'
import { parseCron, weekdayName } from '@/runtime/cron'
import { formatDatetime } from '@/runtime/format'
import { errorText } from '@/views/admin/building/shared'
import { automationsApi, type ScheduledTask } from './api'

/**
 * The scheduled-task manager (specification §4.31): everything scheduled
 * across the system — automation schedules, scheduled downloads and the
 * system's own jobs — with when each runs next, how it last ended, and where
 * to change it.
 */
const { t, te, locale } = useI18n()
const rows = ref<ScheduledTask[]>([])
const loading = ref(false)
const loadError = ref('')
const kind = ref<ScheduledTask['kind'] | null>(null)

async function load(): Promise<void> {
  loading.value = true
  try {
    rows.value = await automationsApi.scheduledTasks()
    loadError.value = ''
  } catch (e) {
    loadError.value = errorText(e, t('auto.load_failed'))
  } finally {
    loading.value = false
  }
}
onMounted(load)

const kinds = computed(() => [
  { value: null, label: t('sched.any_kind') },
  ...[...new Set(rows.value.map((r) => r.kind))].map((k) => ({ value: k as ScheduledTask['kind'] | null, label: t(`sched.kind.${k}`) })),
])
const shown = computed(() => rows.value.filter((r) => kind.value === null || r.kind === kind.value))
const when = (iso: string) => formatDatetime(iso, locale.value)
// A schedule in words where it has a common shape; anything else as written.
function scheduleText(cron: string): string | null {
  const c = parseCron(cron)
  if (c === null) return null
  if (c.kind === 'hourly') return t('sched.every_hour', { minute: c.minute })
  if (c.kind === 'daily') return t('sched.every_day', { time: c.time })
  if (c.kind === 'weekly') return t('sched.every_week', { day: weekdayName(c.weekday, locale.value), time: c.time })
  return t('sched.every_month', { day: c.day, time: c.time })
}
const severity = (s: ScheduledTask['last_status']) => (s === 'succeeded' ? 'success' : s === 'failed' ? 'danger' : s === 'running' ? 'info' : 'secondary')
const link = (r: ScheduledTask) =>
  r.owner?.type === 'automation'
    ? { name: 'admin.automations.edit', params: { form: r.owner.form, automation: r.owner.uuid } }
    : r.owner?.type === 'download_profile'
      ? { name: 'admin.downloads.profile', params: { form: r.owner.form, profile: r.owner.uuid } }
      : null
// The system's jobs are named by their command; people see what they do.
function taskName(r: ScheduledTask): string {
  if (r.kind !== 'system') return r.name
  const key = `sched.system.${r.name
    .replace(/--hours=\d+/, '')
    .replace(/[^a-z]+/g, '_')
    .replace(/^_+|_+$/g, '')}`
  return te(key) ? t(key) : t('sched.system_job')
}
</script>

<template>
  <div class="flex flex-col gap-6" data-testid="scheduled-tasks">
    <header class="page-head">
      <div class="flex-1 min-w-0">
        <h1 class="page-title !mb-1">{{ t('admin.area.scheduled_tasks') }}</h1>
        <p class="m-0 text-sm text-muted-color">{{ t('sched.hint') }}</p>
      </div>
      <Select v-model="kind" :options="kinds" option-label="label" option-value="value" size="small" class="w-field-sm" :placeholder="t('sched.any_kind')" :aria-label="t('sched.kind_label')" />
      <Button icon="pi pi-refresh" :label="t('auto.refresh')" text size="small" @click="load" />
    </header>
    <Message v-if="loadError" severity="error">{{ loadError }}</Message>
    <DataTable :value="shown" :loading="loading" data-key="uuid" size="small" scrollable>
      <template #empty><EmptyState icon="pi pi-calendar-clock" :title="t('sched.empty')" /></template>
      <Column :header="t('sched.name')">
        <template #body="{ data }">
          <RouterLink v-if="link(data)" :to="link(data)!" class="text-primary" dir="auto">{{ taskName(data) }}</RouterLink>
          <span v-else dir="auto">{{ taskName(data) }}</span>
        </template>
      </Column>
      <Column :header="t('sched.kind_label')">
        <template #body="{ data }"><Tag severity="secondary" :value="t(`sched.kind.${data.kind}`)" /></template>
      </Column>
      <Column :header="t('sched.schedule')">
        <template #body="{ data }"
          ><span v-if="scheduleText(data.cron)" class="text-sm" :title="data.cron">{{ scheduleText(data.cron) }}</span
          ><span v-else class="ltr-value text-sm" dir="ltr">{{ data.cron }}</span> <span class="text-xs text-muted-color ltr-value" dir="ltr">{{ data.timezone }}</span></template
        >
      </Column>
      <Column :header="t('sched.next_run')">
        <template #body="{ data }"
          ><template v-if="!data.enabled">{{ t('sched.off') }}</template
          ><template v-else-if="data.next_run_at">{{ when(data.next_run_at) }}</template
          ><EmptyValue v-else
        /></template>
      </Column>
      <Column :header="t('sched.last_run')">
        <template #body="{ data }"
          ><template v-if="data.last_run_at">{{ when(data.last_run_at) }}</template
          ><EmptyValue v-else :text="t('sched.never_run')"
        /></template>
      </Column>
      <Column :header="t('sched.last_result')">
        <template #body="{ data }">
          <Tag v-if="data.last_status" :severity="severity(data.last_status)" :value="t(`sched.status.${data.last_status}`)" :title="data.last_error ?? undefined" />
          <EmptyValue v-else :text="t('sched.never_run')" />
        </template>
      </Column>
    </DataTable>
  </div>
</template>
