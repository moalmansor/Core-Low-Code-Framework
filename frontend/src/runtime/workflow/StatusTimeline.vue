<script setup lang="ts">
import Message from 'primevue/message'
import { ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { ApiError, get } from '@/api/http'
import { openFile } from '../api'
import { formatDatetime } from '../format'
import StatusBadge from './StatusBadge.vue'
import { durationParts, type HistoryItem } from './types'

/**
 * The status timeline of a record (specification §4.12): every move with who
 * made it (and for whom), when, how long the record stayed in the previous
 * status, the comment, attachments and — for those who may see them — the
 * justification.
 */
const props = defineProps<{ form: string; record: string; version: number }>()
const { t, locale } = useI18n()
const items = ref<HistoryItem[] | null>(null)
const error = ref('')
async function load(): Promise<void> {
  error.value = ''
  try {
    items.value = (await get<{ data: HistoryItem[] }>(`/r/${props.form}/${props.record}/status-history`)).data
  } catch (e) {
    error.value = e instanceof ApiError ? e.message : t('records.load_failed')
  }
}
watch(() => [props.form, props.record, props.version], load, { immediate: true })
const stayed = (s: number) => {
  const d = durationParts(s)
  return t(`workflow_run.duration.${d.unit}`, { n: d.n })
}
</script>

<template>
  <Message v-if="error" severity="error">{{ error }}</Message>
  <p v-else-if="items === null" class="text-muted-color">{{ t('records.loading') }}</p>
  <p v-else-if="!items.length" class="text-muted-color">{{ t('workflow_run.timeline_empty') }}</p>
  <ol v-else class="flex flex-col gap-3 border-s-2 border-line ps-4" data-testid="status-timeline">
    <li v-for="(h, i) in items" :key="i" class="flex flex-col gap-1">
      <div class="flex flex-wrap items-center gap-2 text-sm">
        <StatusBadge v-if="h.from" :status="h.from" size="sm" />
        <i v-if="h.from" class="pi pi-arrow-right rtl:rotate-180 text-muted-color" aria-hidden="true" />
        <StatusBadge :status="h.to" size="sm" />
        <span v-if="h.transition" class="font-medium">{{ h.transition.name }}</span>
        <span v-else class="text-muted-color">{{ t(`workflow_run.source.${h.source}`) }}</span>
      </div>
      <div class="text-sm text-muted-color">
        {{ h.by ?? t('records.system_actor') }}<template v-if="h.on_behalf_of"> {{ t('workflow_run.for', { name: h.on_behalf_of }) }}</template> · {{ formatDatetime(h.at, locale)
        }}<template v-if="h.seconds_in_previous !== null"> · {{ t('workflow_run.stayed', { time: stayed(h.seconds_in_previous) }) }}</template>
      </div>
      <p v-if="h.comment" class="text-sm" dir="auto">{{ h.comment }}</p>
      <ul v-if="h.attachments.length" class="text-sm flex flex-wrap gap-2">
        <li v-for="f in h.attachments" :key="f.uuid">
          <button type="button" class="text-link inline-flex items-center gap-1" @click="openFile(f.uuid)"><i class="pi pi-paperclip" />{{ f.name }}</button>
        </li>
      </ul>
      <div v-if="h.justification" class="text-sm rounded-md bg-primary-subtle p-2" data-testid="timeline-justification">
        <span v-if="h.justification.restricted" class="text-muted-color">{{ t('workflow_run.justification_restricted') }}</span>
        <template v-else>
          <span class="font-medium">{{ t('workflow_run.justification') }}:</span>
          <span v-if="h.justification.reason_code"> [{{ h.justification.reason_code.label ?? h.justification.reason_code.code }}]</span>
          <span dir="auto"> {{ h.justification.reason_text }}</span>
          <span v-if="h.justification.note" class="text-muted-color" dir="auto"> — {{ h.justification.note }}</span>
        </template>
      </div>
    </li>
  </ol>
</template>
