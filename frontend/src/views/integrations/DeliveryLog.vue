<script setup lang="ts">
import Button from 'primevue/button'
import Column from 'primevue/column'
import DataTable from 'primevue/datatable'
import Dialog from 'primevue/dialog'
import Paginator from 'primevue/paginator'
import Select from 'primevue/select'
import Tag from 'primevue/tag'
import { useToast } from 'primevue/usetoast'
import { computed, onMounted, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import EmptyState from '@/components/config/EmptyState.vue'
import EmptyValue from '@/components/EmptyValue.vue'
import { errorText, formatDateTime } from '../admin/building/shared'
import { statusSeverity, webhooksApi, type Delivery, type DeliveryDetail, type DeliveryStatus, type Page } from './api'

/**
 * The delivery log of a webhook or the call log of an inbound endpoint
 * (specification §4.22): newest first, filtered by result, each call opened
 * with its masked request and the answer. Outgoing deliveries can be sent
 * again.
 */
const props = defineProps<{
  load: (page: number, status: DeliveryStatus | null) => Promise<Page<Delivery>>
  direction: 'outgoing' | 'incoming'
}>()
const { t, locale } = useI18n()
const toast = useToast()
const page = ref(1)
const status = ref<DeliveryStatus | null>(null)
const rows = ref<Delivery[]>([])
const total = ref(0)
const loading = ref(false)
const statuses = computed(() =>
  (props.direction === 'outgoing' ? (['succeeded', 'failed', 'retrying', 'blocked', 'pending'] as const) : (['succeeded', 'failed', 'signature_invalid'] as const)).map((s) => ({
    value: s,
    label: t(`intg.status.${s}`),
  })),
)

async function refresh(): Promise<void> {
  loading.value = true
  try {
    const r = await props.load(page.value, status.value)
    rows.value = r.rows
    total.value = r.total
  } catch (e) {
    toast.add({ severity: 'error', summary: errorText(e, t('intg.load_failed')), life: 6000 })
  } finally {
    loading.value = false
  }
}
onMounted(refresh)
watch(status, () => {
  page.value = 1
  void refresh()
})
defineExpose({ refresh })

const open = ref<DeliveryDetail | null>(null)
async function show(d: Delivery): Promise<void> {
  try {
    open.value = await webhooksApi.delivery(d.uuid)
  } catch (e) {
    toast.add({ severity: 'error', summary: errorText(e, t('intg.load_failed')), life: 6000 })
  }
}
const retrying = ref(false)
async function retry(): Promise<void> {
  if (!open.value) return
  retrying.value = true
  try {
    await webhooksApi.retry(open.value.uuid)
    toast.add({ severity: 'success', summary: t('intg.resent'), life: 4000 })
    open.value = null
    await refresh()
  } catch (e) {
    toast.add({ severity: 'error', summary: errorText(e, t('intg.resend_failed')), life: 6000 })
  } finally {
    retrying.value = false
  }
}
function pretty(body: string | null): string {
  if (!body) return ''
  try {
    return JSON.stringify(JSON.parse(body), null, 2)
  } catch {
    return body
  }
}
</script>

<template>
  <div class="flex flex-col gap-3" data-testid="delivery-log">
    <div class="flex flex-wrap items-center gap-3">
      <label for="dl-status" class="text-sm text-muted-color">{{ t('intg.filter_status') }}</label>
      <Select v-model="status" input-id="dl-status" :options="statuses" option-label="label" option-value="value" :placeholder="t('intg.all_results')" show-clear size="small" class="w-field-sm" />
      <Button icon="pi pi-refresh" :label="t('intg.refresh')" text size="small" @click="refresh" />
    </div>
    <EmptyState v-if="!loading && !rows.length" icon="pi pi-history" :title="t('intg.log_empty_title')" :description="t(`intg.log_empty_${direction}`)" testid="delivery-empty" />
    <template v-else>
      <DataTable :value="rows" data-key="uuid" :loading="loading" size="small" class="records-table" row-hover @row-click="(e) => show(e.data as Delivery)">
        <Column :header="t('intg.when')">
          <template #body="{ data }">{{ formatDateTime(data.created_at, locale) }}</template>
        </Column>
        <Column v-if="direction === 'outgoing'" :header="t('intg.event')">
          <template #body="{ data }">
            <span v-if="data.event">{{ t(`intg.event_name.${data.event}`) }}</span>
            <EmptyValue v-else />
          </template>
        </Column>
        <Column v-else :header="t('intg.outcome')">
          <template #body="{ data }">
            <span v-if="data.operation">{{ t(`intg.operation_done.${data.operation}`) }}</span>
            <EmptyValue v-else />
          </template>
        </Column>
        <Column :header="t('intg.result')">
          <template #body="{ data }">
            <Tag :severity="statusSeverity(data.status)" :value="t(`intg.status.${data.status}`)" />
          </template>
        </Column>
        <Column :header="t('intg.answer')">
          <template #body="{ data }">
            <span v-if="data.response_status !== null" class="ltr-value">{{ data.response_status }}</span>
            <EmptyValue v-else />
          </template>
        </Column>
        <Column :header="t('intg.error')">
          <template #body="{ data }">
            <span v-if="data.error" class="text-sm line-clamp-2" dir="auto">{{ data.error }}</span>
            <EmptyValue v-else />
          </template>
        </Column>
      </DataTable>
      <Paginator v-if="total > 25" :rows="25" :total-records="total" :first="(page - 1) * 25" @page="(e) => ((page = e.page + 1), refresh())" />
    </template>

    <Dialog
      :visible="open !== null"
      modal
      :header="t('intg.delivery_title')"
      :style="{ width: '48rem', maxWidth: 'calc(100vw - 2rem)' }"
      data-testid="delivery-dialog"
      @update:visible="(v: boolean) => !v && (open = null)"
    >
      <div v-if="open" class="flex flex-col gap-4">
        <dl class="grid grid-cols-[auto_1fr] gap-x-4 gap-y-1 m-0 text-sm">
          <dt class="text-muted-color">{{ t('intg.when') }}</dt>
          <dd class="m-0">{{ formatDateTime(open.created_at, locale) }}</dd>
          <dt class="text-muted-color">{{ t('intg.result') }}</dt>
          <dd class="m-0"><Tag :severity="statusSeverity(open.status)" :value="t(`intg.status.${open.status}`)" /></dd>
          <template v-if="open.attempt">
            <dt class="text-muted-color">{{ t('intg.attempts') }}</dt>
            <dd class="m-0">{{ open.attempt }}</dd>
          </template>
          <template v-if="open.duration_ms !== null && open.duration_ms !== undefined">
            <dt class="text-muted-color">{{ t('intg.duration') }}</dt>
            <dd class="m-0">{{ t('intg.ms', { n: open.duration_ms }) }}</dd>
          </template>
          <template v-if="open.error">
            <dt class="text-muted-color">{{ t('intg.error') }}</dt>
            <dd class="m-0" dir="auto">{{ open.error }}</dd>
          </template>
        </dl>
        <section>
          <h3 class="text-sm font-semibold m-0 mb-2">{{ t('intg.request') }}</h3>
          <pre class="delivery-code" dir="ltr">{{
            Object.entries(open.request_headers)
              .map(([k, v]) => `${k}: ${v}`)
              .join('\n')
          }}</pre>
          <pre class="delivery-code mt-2" dir="ltr">{{ pretty(open.request_body) }}</pre>
        </section>
        <section>
          <h3 class="text-sm font-semibold m-0 mb-2">{{ t('intg.response') }}</h3>
          <pre v-if="open.response_body" class="delivery-code" dir="ltr">{{ pretty(open.response_body) }}</pre>
          <EmptyValue v-else />
        </section>
      </div>
      <template #footer>
        <Button :label="t('common.close')" text @click="open = null" />
        <Button v-if="direction === 'outgoing'" icon="pi pi-replay" :label="t('intg.resend')" :loading="retrying" data-testid="delivery-resend" @click="retry" />
      </template>
    </Dialog>
  </div>
</template>

<style scoped>
.delivery-code {
  margin: 0;
  padding: 0.75rem;
  border-radius: 0.5rem;
  background: var(--bg-subtle);
  font-size: 0.75rem;
  max-height: 16rem;
  overflow: auto;
  white-space: pre-wrap;
  word-break: break-all;
}
</style>
