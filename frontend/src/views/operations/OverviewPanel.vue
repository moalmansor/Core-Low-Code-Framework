<script setup lang="ts">
import Button from 'primevue/button'
import Tag from 'primevue/tag'
import { computed, onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { formatDatetime } from '@/runtime/format'
import { opsApi, type CheckState, type OpsHealth } from './api'

/** Health widgets (specification §4.2): connectivity, queues, stuck and failed items, the scheduler, storage. */
const emit = defineEmits<{ open: [tab: 'emails' | 'submissions' | 'jobs'] }>()
const { t, n, locale } = useI18n()
const health = ref<OpsHealth | null>(null)
const loading = ref(false)
async function load(): Promise<void> {
  loading.value = true
  try {
    health.value = await opsApi.health()
  } finally {
    loading.value = false
  }
}
onMounted(load)
const severity = (v: CheckState) => (v === 'ok' ? 'success' : v === 'failing' ? 'danger' : 'secondary')
const gb = (bytes: number | null) => (bytes === null ? '—' : n(bytes / 1024 ** 3, { maximumFractionDigits: 1 }))
// The scheduler runs its own commands every minute; more than five minutes without one is a delay.
const schedulerLate = computed(() => !health.value?.scheduler_last_run_at || Date.now() - Date.parse(health.value.scheduler_last_run_at) > 5 * 60 * 1000)
const counters = computed(() =>
  health.value
    ? ([
        { key: 'stuck_emails', value: health.value.stuck_emails, tab: 'emails' },
        { key: 'failed_emails', value: health.value.failed_emails, tab: 'emails' },
        { key: 'failed_submissions', value: health.value.failed_submissions, tab: 'submissions' },
        { key: 'failed_jobs', value: health.value.failed_jobs, tab: 'jobs' },
      ] as const)
    : [],
)
</script>

<template>
  <div class="flex flex-col gap-6" data-testid="ops-overview">
    <div class="flex justify-end">
      <Button icon="pi pi-refresh" :label="t('ops.refresh')" size="small" severity="secondary" outlined :loading="loading" @click="load" />
    </div>
    <template v-if="health">
      <section class="ops-grid">
        <button v-for="c in counters" :key="c.key" type="button" class="ops-card ops-counter" :data-testid="`ops-count-${c.key}`" @click="emit('open', c.tab)">
          <span class="text-sm text-muted-color">{{ t(`ops.count.${c.key}`) }}</span>
          <span class="text-2xl font-semibold tabular-nums" :class="{ 'text-danger': c.value > 0 }">{{ n(c.value) }}</span>
        </button>
      </section>
      <section class="flex flex-col gap-2">
        <h2 class="m-0 text-base font-semibold">{{ t('ops.connectivity') }}</h2>
        <div class="ops-grid">
          <div v-for="(v, k) in health.checks" :key="k" class="ops-card flex items-center justify-between" :data-testid="`ops-check-${k}`">
            <span>{{ t(`health.check.${k}`) }}</span>
            <Tag :severity="severity(v)" :value="t(`health.state.${v}`)" />
          </div>
        </div>
      </section>
      <section class="flex flex-col gap-2">
        <h2 class="m-0 text-base font-semibold">{{ t('ops.queues') }}</h2>
        <div class="ops-grid">
          <div v-for="(v, k) in health.queues" :key="k" class="ops-card" :data-testid="`ops-queue-${k}`">
            <div class="text-sm text-muted-color">{{ t(`ops.queue.${k}`) }}</div>
            <div class="text-2xl tabular-nums">{{ v === null ? '—' : n(v) }}</div>
          </div>
        </div>
      </section>
      <section class="ops-grid">
        <div class="ops-card" data-testid="ops-scheduler">
          <div class="text-sm text-muted-color">{{ t('ops.scheduler') }}</div>
          <div :class="{ 'text-danger': schedulerLate }">{{ health.scheduler_last_run_at ? formatDatetime(health.scheduler_last_run_at, locale) : t('ops.never') }}</div>
        </div>
        <div class="ops-card" data-testid="ops-storage">
          <div class="text-sm text-muted-color">{{ t('ops.storage') }}</div>
          <div v-if="health.storage.used_percent !== null">
            <span class="text-2xl tabular-nums" :class="{ 'text-danger': health.storage.used_percent >= 90 }">{{ n(health.storage.used_percent) }}%</span>
            <span class="text-sm text-muted-color"> · {{ t('ops.storage_free', { free: gb(health.storage.free), total: gb(health.storage.total) }) }}</span>
          </div>
          <div v-else>—</div>
        </div>
        <div class="ops-card">
          <div class="text-sm text-muted-color">{{ t('health.pending_events') }}</div>
          <div class="text-2xl tabular-nums">{{ n(health.pending_outbox_events) }}</div>
        </div>
        <RouterLink to="/admin/errors" class="ops-card ops-counter">
          <span class="text-sm text-muted-color">{{ t('health.open_errors') }}</span>
          <span class="text-2xl tabular-nums">{{ n(health.open_error_groups) }}</span>
        </RouterLink>
      </section>
    </template>
  </div>
</template>

<style scoped>
.ops-grid {
  display: grid;
  gap: 1rem;
  grid-template-columns: repeat(auto-fill, minmax(14rem, 1fr));
}
.ops-card {
  padding: 1rem;
  border-radius: 0.75rem;
  border: 1px solid var(--color-line);
  background: var(--color-card);
}
.ops-counter {
  display: flex;
  flex-direction: column;
  gap: 0.25rem;
  text-align: start;
  cursor: pointer;
  color: inherit;
}
.ops-counter:hover {
  border-color: var(--color-line-strong);
}
</style>
