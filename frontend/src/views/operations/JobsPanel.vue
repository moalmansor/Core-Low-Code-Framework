<script setup lang="ts">
import Button from 'primevue/button'
import Column from 'primevue/column'
import DataTable, { type DataTablePageEvent } from 'primevue/datatable'
import Dialog from 'primevue/dialog'
import Select from 'primevue/select'
import Tag from 'primevue/tag'
import Textarea from 'primevue/textarea'
import { useToast } from 'primevue/usetoast'
import { computed, onMounted, reactive, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import EmptyValue from '@/components/EmptyValue.vue'
import ConfigField from '@/components/config/ConfigField.vue'
import EmptyState from '@/components/config/EmptyState.vue'
import TabIntro from '@/components/config/TabIntro.vue'
import { formatDatetime } from '@/runtime/format'
import { errorText } from '@/views/admin/building/shared'
import { opsApi, type JobKind, type OpsJob } from './api'

/**
 * Failed jobs (specification §4.2): imports, exports, custom downloads,
 * action runs and data-quality scans, automation runs, scheduled tasks,
 * outbound webhook calls and the queue's own failures. Inspect, retry where
 * that is safe, or dismiss with a reason; the failure itself is kept.
 */
const { t, n, locale } = useI18n()
const toast = useToast()
const KINDS: JobKind[] = ['import', 'export', 'download', 'action', 'automation', 'scheduled_task', 'webhook', 'webhook_delivery', 'channel_delivery', 'data_source', 'sync_run', 'extension', 'queue_job']
const f = reactive({ kind: null as JobKind | null, page: 1 })
const rows = ref<OpsJob[]>([])
const total = ref(0)
const busy = ref(false)
const kindOptions = computed(() => [{ value: null, label: t('ops.all') }, ...KINDS.map((k) => ({ value: k, label: t(`ops.kind.${k}`) }))])

async function load(): Promise<void> {
  const res = await opsApi.jobs({ kind: f.kind, page: f.page })
  rows.value = res.data
  total.value = res.meta.total
}
onMounted(load)
function onPage(e: DataTablePageEvent): void {
  f.page = e.page + 1
  void load()
}
function filter(): void {
  f.page = 1
  void load()
}
const when = (iso: string | null) => (iso ? formatDatetime(iso, locale.value) : '')

async function retry(job: OpsJob): Promise<void> {
  if (!window.confirm(t('ops.retry_job_confirm'))) return
  busy.value = true
  try {
    await opsApi.retryJob(job.kind, job.id)
    toast.add({ severity: 'success', summary: t('ops.job_retried'), life: 4000 })
    open.value = null
    await load()
  } catch (e) {
    toast.add({ severity: 'error', summary: errorText(e, t('ops.action_failed')), life: 6000 })
  } finally {
    busy.value = false
  }
}

const open = ref<OpsJob | null>(null)
const reason = ref('')
const dismissing = ref(false)
async function show(job: OpsJob): Promise<void> {
  open.value = await opsApi.job(job.kind, job.id)
  reason.value = ''
  dismissing.value = false
}
async function dismiss(): Promise<void> {
  if (!open.value || reason.value.trim().length < 3) return
  busy.value = true
  try {
    await opsApi.dismissJob(open.value.kind, open.value.id, reason.value.trim())
    toast.add({ severity: 'success', summary: t('ops.job_dismissed'), life: 4000 })
    open.value = null
    await load()
  } catch (e) {
    toast.add({ severity: 'error', summary: errorText(e, t('ops.action_failed')), life: 6000 })
  } finally {
    busy.value = false
  }
}
</script>

<template>
  <div class="flex flex-col gap-6" data-testid="ops-jobs">
    <TabIntro :title="t('ops.tab.jobs')" :text="t('ops.jobs_hint')" />
    <ConfigField :label="t('ops.kind_label')" for="ops-job-kind" width="sm">
      <Select v-model="f.kind" input-id="ops-job-kind" :options="kindOptions" option-label="label" option-value="value" size="small" data-testid="ops-job-kind" @change="filter" />
    </ConfigField>
    <DataTable :value="rows" lazy paginator :rows="25" :total-records="total" data-key="key" size="small" data-testid="ops-job-table" @page="onPage" @row-click="(e) => show(e.data as OpsJob)">
      <template #empty>
        <EmptyState icon="pi pi-check-circle" :title="t('ops.no_jobs')" :description="t('ops.no_jobs_text')" />
      </template>
      <Column :header="t('ops.kind_label')">
        <template #body="{ data }"><Tag severity="secondary" :value="t(`ops.kind.${(data as OpsJob).kind}`)" /></template>
      </Column>
      <Column :header="t('ops.what')">
        <template #body="{ data }">
          <div v-if="(data as OpsJob).subject ?? (data as OpsJob).form?.name" class="font-medium" dir="auto">{{ (data as OpsJob).subject ?? (data as OpsJob).form?.name }}</div>
          <EmptyValue v-else />
          <div v-if="(data as OpsJob).form && (data as OpsJob).subject" class="text-xs text-muted-color">{{ (data as OpsJob).form!.name }}</div>
        </template>
      </Column>
      <Column :header="t('ops.person')">
        <template #body="{ data }"
          ><template v-if="(data as OpsJob).user">{{ (data as OpsJob).user }}</template
          ><EmptyValue v-else :text="t('ops.by_system')"
        /></template>
      </Column>
      <Column :header="t('ops.error')">
        <template #body="{ data }"
          ><span v-if="(data as OpsJob).error" class="text-xs text-danger line-clamp-2 max-w-md" dir="auto">{{ (data as OpsJob).error }}</span
          ><EmptyValue v-else :text="t('ops.no_error')"
        /></template>
      </Column>
      <Column :header="t('ops.when')">
        <template #body="{ data }"
          ><span class="text-sm whitespace-nowrap text-muted-color">{{ when((data as OpsJob).failed_at) }}</span></template
        >
      </Column>
      <Column header-style="width: 7rem">
        <template #body="{ data }">
          <Button
            v-if="(data as OpsJob).retryable"
            :label="t('ops.retry')"
            size="small"
            text
            :disabled="busy"
            :data-testid="`ops-job-retry-${(data as OpsJob).key}`"
            @click.stop="retry(data as OpsJob)"
          />
        </template>
      </Column>
    </DataTable>

    <Dialog
      :visible="!!open"
      modal
      :header="open ? t(`ops.kind.${open.kind}`) : ''"
      :style="{ width: '52rem', maxWidth: 'calc(100vw - 2rem)' }"
      data-testid="ops-job-dialog"
      @update:visible="(v: boolean) => !v && (open = null)"
    >
      <div v-if="open" class="flex flex-col gap-4">
        <dl class="ops-facts">
          <dt>{{ t('ops.what') }}</dt>
          <dd dir="auto">
            <template v-if="open.subject">{{ open.subject }}</template
            ><EmptyValue v-else :compact="false" />
          </dd>
          <dt>{{ t('ops.form') }}</dt>
          <dd>
            <template v-if="open.form">{{ open.form.name }}</template
            ><EmptyValue v-else :compact="false" :text="t('ops.no_form')" />
          </dd>
          <dt>{{ t('ops.person') }}</dt>
          <dd>
            <template v-if="open.user">{{ open.user }}</template
            ><EmptyValue v-else :compact="false" :text="t('ops.by_system')" />
          </dd>
          <dt>{{ t('ops.attempts') }}</dt>
          <dd>{{ n(open.attempts) }}</dd>
          <dt>{{ t('ops.when') }}</dt>
          <dd>{{ when(open.failed_at) }}</dd>
          <dt>{{ t('ops.error') }}</dt>
          <dd dir="auto">
            <span v-if="open.error" class="text-danger">{{ open.error }}</span
            ><EmptyValue v-else :compact="false" :text="t('ops.no_error')" />
          </dd>
        </dl>
        <p v-if="!open.retryable" class="m-0 text-sm text-muted-color">
          {{ t(`ops.not_retryable.${['webhook', 'webhook_delivery', 'data_source', 'extension'].includes(open.kind) ? open.kind : 'other'}`) }}
        </p>
        <details v-if="open.detail">
          <summary class="text-sm cursor-pointer">{{ t('ops.detail') }}</summary>
          <pre class="ops-trace" dir="ltr">{{ open.detail }}</pre>
        </details>
        <ConfigField v-if="dismissing" :label="t('ops.dismiss_reason')" for="ops-dismiss-reason" width="lg" required>
          <Textarea id="ops-dismiss-reason" v-model="reason" rows="2" auto-resize data-testid="ops-job-reason" />
        </ConfigField>
      </div>
      <template #footer>
        <Button :label="t('common.close')" text @click="open = null" />
        <Button v-if="!dismissing" :label="t('ops.dismiss')" severity="secondary" outlined :disabled="busy" data-testid="ops-job-dismiss" @click="dismissing = true" />
        <Button v-else :label="t('ops.dismiss_confirm')" severity="danger" :disabled="reason.trim().length < 3 || busy" data-testid="ops-job-dismiss-confirm" @click="dismiss" />
        <Button v-if="open?.retryable" :label="t('ops.retry')" icon="pi pi-replay" :loading="busy" @click="retry(open)" />
      </template>
    </Dialog>
  </div>
</template>

<style scoped>
.ops-facts {
  display: grid;
  grid-template-columns: max-content 1fr;
  gap: 0.375rem 1rem;
  margin: 0;
  font-size: 0.875rem;
}
.ops-facts dt {
  color: var(--color-muted);
}
.ops-facts dd {
  margin: 0;
}
.ops-trace {
  max-height: 16rem;
  overflow: auto;
  font-size: 0.75rem;
  padding: 0.75rem;
  border-radius: 0.5rem;
  background: var(--color-subtle);
  white-space: pre-wrap;
}
</style>
