<script setup lang="ts">
import Button from 'primevue/button'
import Column from 'primevue/column'
import DataTable, { type DataTablePageEvent } from 'primevue/datatable'
import Dialog from 'primevue/dialog'
import Message from 'primevue/message'
import Select from 'primevue/select'
import Textarea from 'primevue/textarea'
import { useToast } from 'primevue/usetoast'
import { computed, onMounted, reactive, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { ApiError } from '@/api/http'
import { formatDatetime } from '@/runtime/format'
import { errorText } from '@/views/admin/building/shared'
import { opsApi, type OpsSubmission } from './api'

/**
 * Failed submissions (specification §4.2): saves the system could not
 * complete, kept with the user, form, version, payload, error and
 * correlation ID. A retry runs the save again as the person who made it,
 * with the same idempotency key, so a record is never created twice; the
 * values can be corrected first; a submission can be discarded with a reason.
 */
const { t, n, locale } = useI18n()
const toast = useToast()
const f = reactive({ status: 'failed' as 'failed' | 'rejected' | 'discarded', page: 1 })
const rows = ref<OpsSubmission[]>([])
const total = ref(0)
const selected = ref<OpsSubmission[]>([])
const busy = ref(false)
const statusOptions = computed(() => (['failed', 'rejected', 'discarded'] as const).map((s) => ({ value: s, label: t(`ops.submission_status.${s}`) })))

async function load(): Promise<void> {
  const res = await opsApi.submissions({ status: f.status, page: f.page })
  rows.value = res.data
  total.value = res.meta.total
  selected.value = []
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
const when = (iso: string | null) => (iso ? formatDatetime(iso, locale.value) : '—')

async function retry(list: OpsSubmission[]): Promise<void> {
  const ok = list.filter((s) => s.retryable)
  if (!ok.length || !window.confirm(t('ops.retry_confirm', { n: ok.length }))) return
  busy.value = true
  try {
    const res = await opsApi.retrySubmissions(ok.map((s) => s.uuid))
    const failed = res.results.filter((r) => !r.ok)
    toast.add({
      severity: failed.length ? 'warn' : 'success',
      summary: t('ops.retried', { n: res.succeeded }),
      detail: failed.length ? `${t('ops.not_retried', { n: failed.length })}: ${failed[0].error ?? ''}` : undefined,
      life: 8000,
    })
    open.value = null
    await load()
  } catch (e) {
    toast.add({ severity: 'error', summary: errorText(e, t('ops.action_failed')), life: 6000 })
  } finally {
    busy.value = false
  }
}

// One submission: payload, error and trace; edit the values; discard with a reason.
const open = ref<OpsSubmission | null>(null)
const payloadText = ref('')
const payloadError = ref('')
const reason = ref('')
const discarding = ref(false)
async function show(s: OpsSubmission): Promise<void> {
  open.value = await opsApi.submission(s.uuid)
  payloadText.value = JSON.stringify(open.value.payload ?? {}, null, 2)
  payloadError.value = ''
  reason.value = ''
  discarding.value = false
}
const editable = computed(() => open.value?.status === 'failed' && ['create', 'update', 'transition'].includes(open.value.operation))
async function saveEdit(): Promise<void> {
  if (!open.value) return
  let parsed: unknown
  try {
    parsed = JSON.parse(payloadText.value)
  } catch {
    payloadError.value = t('ops.payload_invalid')
    return
  }
  if (!parsed || typeof parsed !== 'object' || Array.isArray(parsed)) {
    payloadError.value = t('ops.payload_invalid')
    return
  }
  busy.value = true
  try {
    open.value = await opsApi.editSubmission(open.value.uuid, parsed as Record<string, unknown>)
    payloadError.value = ''
    toast.add({ severity: 'success', summary: t('ops.payload_saved'), life: 4000 })
  } catch (e) {
    payloadError.value = e instanceof ApiError ? (Object.values(e.fieldErrors)[0] ?? e.message) : errorText(e, t('ops.action_failed'))
  } finally {
    busy.value = false
  }
}
async function discard(): Promise<void> {
  if (!open.value || reason.value.trim().length < 3) return
  busy.value = true
  try {
    await opsApi.discardSubmission(open.value.uuid, reason.value.trim())
    toast.add({ severity: 'success', summary: t('ops.discarded'), life: 4000 })
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
  <div class="flex flex-col gap-4" data-testid="ops-submissions">
    <div class="flex flex-wrap items-end gap-3">
      <div class="field !mb-0">
        <label for="ops-sub-status" class="text-sm">{{ t('ops.status') }}</label>
        <Select v-model="f.status" input-id="ops-sub-status" :options="statusOptions" option-label="label" option-value="value" size="small" class="w-56" @change="filter" />
      </div>
      <p class="m-0 text-sm text-muted-color flex-1 min-w-64">{{ t(`ops.submission_hint.${f.status}`) }}</p>
      <Button
        :label="t('ops.retry_n', { n: selected.length })"
        icon="pi pi-replay"
        size="small"
        :disabled="!selected.some((s) => s.retryable) || busy"
        data-testid="ops-sub-retry"
        @click="retry(selected)"
      />
    </div>
    <DataTable
      v-model:selection="selected"
      :value="rows"
      lazy
      paginator
      :rows="25"
      :total-records="total"
      data-key="uuid"
      size="small"
      data-testid="ops-sub-table"
      @page="onPage"
      @row-click="(e) => show(e.data as OpsSubmission)"
    >
      <template #empty>{{ t('ops.no_submissions') }}</template>
      <Column selection-mode="multiple" header-style="width: 3rem" />
      <Column :header="t('ops.form')">
        <template #body="{ data }">
          <div class="font-medium">{{ (data as OpsSubmission).form?.name ?? '—' }}</div>
          <div class="text-xs text-muted-color">{{ t(`ops.operation.${(data as OpsSubmission).operation}`) }} · {{ t(`ops.source.${(data as OpsSubmission).source}`) }}</div>
        </template>
      </Column>
      <Column :header="t('ops.person')">
        <template #body="{ data }">{{ (data as OpsSubmission).user?.name ?? '—' }}</template>
      </Column>
      <Column :header="t('ops.error')">
        <template #body="{ data }"
          ><span class="text-xs text-danger line-clamp-2 max-w-md" dir="auto">{{ (data as OpsSubmission).error ?? '' }}</span></template
        >
      </Column>
      <Column :header="t('ops.attempts')">
        <template #body="{ data }"
          ><span class="tabular-nums">{{ n((data as OpsSubmission).attempts) }}</span></template
        >
      </Column>
      <Column :header="t('ops.when')">
        <template #body="{ data }"
          ><span class="text-sm whitespace-nowrap text-muted-color">{{ when((data as OpsSubmission).updated_at) }}</span></template
        >
      </Column>
    </DataTable>

    <Dialog
      :visible="!!open"
      modal
      :header="t('ops.submission')"
      :style="{ width: '52rem', maxWidth: 'calc(100vw - 2rem)' }"
      data-testid="ops-sub-dialog"
      @update:visible="(v: boolean) => !v && (open = null)"
    >
      <div v-if="open" class="flex flex-col gap-4">
        <dl class="ops-facts">
          <dt>{{ t('ops.form') }}</dt>
          <dd>
            {{ open.form?.name ?? '—' }}<span v-if="open.version" class="text-muted-color"> · {{ t('ops.version', { n: open.version }) }}</span>
          </dd>
          <dt>{{ t('ops.operation_label') }}</dt>
          <dd>
            {{ t(`ops.operation.${open.operation}`) }} · {{ t(`ops.source.${open.source}`) }}
            <RouterLink v-if="open.form && open.record" :to="`/app/${open.form.uuid}/${open.record}`" class="text-primary ms-2">{{ t('ops.open_record') }}</RouterLink>
          </dd>
          <dt>{{ t('ops.person') }}</dt>
          <dd>{{ open.user ? `${open.user.name} (${open.user.email})` : '—' }}</dd>
          <dt>{{ t('ops.correlation') }}</dt>
          <dd class="ltr-value font-mono text-xs" dir="ltr">{{ open.correlation_id }}</dd>
          <dt>{{ t('ops.error') }}</dt>
          <dd class="text-danger" dir="auto">{{ open.error ?? '—' }}</dd>
          <template v-if="open.discard_reason">
            <dt>{{ t('ops.discard_reason') }}</dt>
            <dd dir="auto">{{ open.discard_reason }}</dd>
          </template>
        </dl>
        <Message v-if="open.edited" severity="info" :closable="false">{{ t('ops.edited_note') }}</Message>
        <div class="field !mb-0">
          <label for="ops-payload" class="text-sm font-medium">{{ t('ops.payload') }}</label>
          <Textarea id="ops-payload" v-model="payloadText" rows="8" auto-resize :readonly="!editable" class="font-mono text-xs" dir="ltr" data-testid="ops-sub-payload" />
          <small class="text-xs text-muted-color">{{ t('ops.payload_hint') }}</small>
          <small v-if="payloadError" class="field-error" role="alert">{{ payloadError }}</small>
        </div>
        <details v-if="open.trace">
          <summary class="text-sm cursor-pointer">{{ t('ops.trace') }}</summary>
          <pre class="ops-trace" dir="ltr">{{ open.trace }}</pre>
        </details>
        <div v-if="discarding" class="field !mb-0">
          <label for="ops-discard-reason" class="text-sm font-medium">{{ t('ops.discard_reason') }}<span class="text-danger ms-1" aria-hidden="true">*</span></label>
          <Textarea id="ops-discard-reason" v-model="reason" rows="2" auto-resize data-testid="ops-sub-reason" />
        </div>
      </div>
      <template #footer>
        <Button :label="t('common.close')" text @click="open = null" />
        <template v-if="open?.status === 'failed'">
          <Button v-if="editable" :label="t('ops.save_payload')" severity="secondary" outlined :disabled="busy" data-testid="ops-sub-save" @click="saveEdit" />
          <Button v-if="!discarding" :label="t('ops.discard')" severity="danger" outlined :disabled="busy" @click="discarding = true" />
          <Button v-else :label="t('ops.discard_confirm')" severity="danger" :disabled="reason.trim().length < 3 || busy" data-testid="ops-sub-discard" @click="discard" />
          <Button v-if="open.retryable" :label="t('ops.retry')" icon="pi pi-replay" :loading="busy" data-testid="ops-sub-retry-one" @click="retry([open])" />
        </template>
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
