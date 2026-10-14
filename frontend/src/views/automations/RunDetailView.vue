<script setup lang="ts">
import Message from 'primevue/message'
import Tag from 'primevue/tag'
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import EmptyValue from '@/components/EmptyValue.vue'
import { formatDatetime } from '@/runtime/format'
import { runSeverity, type RunDetail, type RunLogEntry } from './api'

/**
 * One automation run (specification §4.31): what started it, the records it
 * covered and changed, and each step's outcome with its duration and error.
 */
const props = defineProps<{ run: RunDetail }>()
const { t, n, locale } = useI18n()
const numbers = computed(() => Object.fromEntries(props.run.record_list.map((r) => [r.uuid, r.number])))
const when = (iso: string | null) => (iso ? formatDatetime(iso, locale.value) : '')
const duration = computed(() => (props.run.duration_ms === null ? '' : t('auto.duration_s', { s: n(props.run.duration_ms / 1000, { maximumFractionDigits: 1 }) })))
const recordName = (uuid: string | null) => (uuid === null ? '' : (numbers.value[uuid] ?? t('auto.a_record')))
const logSeverity = (s: RunLogEntry['status']) =>
  ({ succeeded: 'success', failed: 'danger', warning: 'warn', retrying: 'warn', skipped: 'secondary', simulated: 'info', waiting: 'info' })[s] as 'success' | 'danger' | 'warn' | 'info' | 'secondary'
const recordLink = (uuid: string) => (props.run.form ? `/app/${props.run.form}/${uuid}` : null)
</script>

<template>
  <div class="flex flex-col gap-4" data-testid="auto-run-detail">
    <div class="flex flex-wrap items-center gap-2">
      <Tag :severity="runSeverity(run.status)" :value="t(`auto.status.${run.status}`)" data-testid="auto-run-status" />
      <Tag v-if="run.test" severity="info" :value="t('auto.test_run')" />
      <span class="text-sm text-muted-color">{{ t(`auto.trigger.${run.trigger}`) }}</span>
    </div>
    <dl class="run-facts">
      <dt>{{ t('auto.started') }}</dt>
      <dd>{{ when(run.started_at ?? run.created_at) }}</dd>
      <dt>{{ t('auto.finished') }}</dt>
      <dd>{{ when(run.finished_at) }}<EmptyValue v-if="!run.finished_at" /></dd>
      <dt>{{ t('auto.duration') }}</dt>
      <dd>{{ duration }}<EmptyValue v-if="!duration" /></dd>
      <dt>{{ t('auto.records') }}</dt>
      <dd>{{ t('auto.records_value', { n: n(run.records), changed: n(run.affected) }) }}</dd>
      <template v-if="run.by">
        <dt>{{ t('auto.started_by') }}</dt>
        <dd>{{ run.by }}</dd>
      </template>
      <template v-if="run.trigger_ref.file">
        <dt>{{ t('auto.file') }}</dt>
        <dd dir="auto">{{ run.trigger_ref.file }}</dd>
      </template>
      <template v-if="run.resume_at && (run.status === 'waiting' || run.status === 'queued')">
        <dt>{{ t('auto.resumes') }}</dt>
        <dd>{{ when(run.resume_at) }}</dd>
      </template>
      <template v-if="run.depth > 0">
        <dt>{{ t('auto.chain') }}</dt>
        <dd>{{ t('auto.chain_value', { n: n(run.depth) }) }}</dd>
      </template>
    </dl>
    <Message v-if="run.error" :severity="run.status === 'skipped_loop' ? 'warn' : 'error'" :closable="false">{{ run.error }}</Message>
    <div v-if="run.log.length" class="flex flex-col gap-2">
      <h3 class="m-0 text-sm font-semibold">{{ t('auto.steps_run') }}</h3>
      <ol class="run-log">
        <li v-for="(e, k) in run.log" :key="k" class="run-entry" :data-testid="`auto-log-${k}`">
          <Tag :severity="logSeverity(e.status)" :value="t(`auto.log_status.${e.status}`)" />
          <div class="min-w-0 flex-1">
            <div class="text-sm">
              <span class="font-medium">{{ e.type ? t(`act.step.${e.type}`) : t('auto.the_record') }}</span>
              <template v-if="e.record">
                ·
                <a v-if="recordLink(e.record)" :href="recordLink(e.record)!" class="text-primary">{{ recordName(e.record) }}</a>
              </template>
              <span v-if="e.ms" class="text-xs text-muted-color"> · {{ t('auto.ms', { ms: n(e.ms) }) }}</span>
            </div>
            <p v-if="e.message" class="m-0 text-xs text-muted-color" dir="auto">{{ e.message }}</p>
          </div>
        </li>
      </ol>
    </div>
  </div>
</template>

<style scoped>
.run-facts {
  display: grid;
  grid-template-columns: max-content 1fr;
  gap: 0.25rem 1rem;
  margin: 0;
  font-size: 0.875rem;
}
.run-facts dt {
  color: var(--text-muted);
}
.run-facts dd {
  margin: 0;
}
.run-log {
  list-style: none;
  margin: 0;
  padding: 0;
  display: flex;
  flex-direction: column;
  gap: 0.5rem;
}
.run-entry {
  display: flex;
  align-items: flex-start;
  gap: 0.5rem;
}
</style>
