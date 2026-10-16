<script setup lang="ts">
import Button from 'primevue/button'
import Column from 'primevue/column'
import DataTable, { type DataTablePageEvent } from 'primevue/datatable'
import Dialog from 'primevue/dialog'
import InputText from 'primevue/inputtext'
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
import { opsApi, type EmailStatus, type OpsEmail } from './api'

/**
 * The e-mail queue monitor (specification §4.2): every e-mail with its
 * status, recipients, subject, form, record and rule, attempts and the exact
 * failure; resend (single or bulk, or to edited recipients), cancel, and the
 * rendered HTML in a sandboxed frame.
 */
const { t, n, locale } = useI18n()
const toast = useToast()
const STATUSES: EmailStatus[] = ['pending', 'sending', 'sent', 'failed', 'cancelled', 'stuck']
const f = reactive({ status: null as EmailStatus | null, search: '', page: 1 })
const rows = ref<OpsEmail[]>([])
const total = ref(0)
const selected = ref<OpsEmail[]>([])
const busy = ref(false)
const statusOptions = computed(() => [{ value: null, label: t('ops.all') }, ...STATUSES.map((s) => ({ value: s, label: t(`ops.email_status.${s}`) }))])

async function load(): Promise<void> {
  const res = await opsApi.emails({ status: f.status, search: f.search, page: f.page })
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
const severity = (s: EmailStatus) =>
  ({ pending: 'info', sending: 'info', sent: 'success', failed: 'danger', cancelled: 'secondary', stuck: 'warn' })[s] as 'info' | 'success' | 'danger' | 'secondary' | 'warn'
const when = (iso: string | null) => (iso ? formatDatetime(iso, locale.value) : null)
const filtered = computed(() => f.status !== null || f.search.trim() !== '')
function clearFilter(): void {
  f.status = null
  f.search = ''
  filter()
}

async function resend(list: OpsEmail[]): Promise<void> {
  if (!list.length || !window.confirm(t('ops.resend_confirm', { n: list.length }))) return
  busy.value = true
  try {
    const out = await opsApi.resend(list.map((e) => e.uuid))
    toast.add({ severity: 'success', summary: t('ops.resent', { n: out.length }), life: 4000 })
    await load()
  } catch (e) {
    toast.add({ severity: 'error', summary: errorText(e, t('ops.action_failed')), life: 6000 })
  } finally {
    busy.value = false
  }
}
async function cancel(list: OpsEmail[]): Promise<void> {
  const pending = list.filter((e) => e.raw_status === 'pending')
  if (!pending.length || !window.confirm(t('ops.cancel_confirm', { n: pending.length }))) return
  busy.value = true
  try {
    toast.add({ severity: 'success', summary: t('ops.cancelled', { n: await opsApi.cancel(pending.map((e) => e.uuid)) }), life: 4000 })
    await load()
  } catch (e) {
    toast.add({ severity: 'error', summary: errorText(e, t('ops.action_failed')), life: 6000 })
  } finally {
    busy.value = false
  }
}

async function cancelOpen(): Promise<void> {
  if (!open.value) return
  await cancel([open.value])
  open.value = null
}

// One e-mail: its details, the rendered HTML, and resend to edited recipients.
const open = ref<OpsEmail | null>(null)
const htmlUrl = ref<string | null>(null)
const editing = reactive({ to: '', cc: '', bcc: '' })
const editError = ref('')
async function show(e: OpsEmail): Promise<void> {
  open.value = e
  // Addresses are edited as text, one list per slot (machine-list).
  editing.to = e.to.join(', ') // machine-list
  editing.cc = e.cc.join(', ') // machine-list
  editing.bcc = e.bcc.join(', ') // machine-list
  editError.value = ''
  htmlUrl.value = null
  htmlUrl.value = await opsApi.emailHtml(e.uuid).catch(() => null)
}
const list = (s: string) =>
  s
    .split(/[,;\s]+/)
    .map((x) => x.trim())
    .filter(Boolean)
async function resendEdited(): Promise<void> {
  if (!open.value) return
  busy.value = true
  editError.value = ''
  try {
    await opsApi.resend([open.value.uuid], { to: list(editing.to), cc: list(editing.cc), bcc: list(editing.bcc) })
    toast.add({ severity: 'success', summary: t('ops.resent', { n: 1 }), life: 4000 })
    open.value = null
    await load()
  } catch (e) {
    editError.value = errorText(e, t('ops.action_failed'))
  } finally {
    busy.value = false
  }
}
</script>

<template>
  <div class="flex flex-col gap-6" data-testid="ops-emails">
    <TabIntro :title="t('ops.tab.emails')" :text="t('ops.emails_hint')" />
    <div class="flex flex-wrap items-end gap-3">
      <ConfigField :label="t('ops.status')" for="ops-email-status" width="sm">
        <Select v-model="f.status" input-id="ops-email-status" :options="statusOptions" option-label="label" option-value="value" size="small" data-testid="ops-email-status" @change="filter" />
      </ConfigField>
      <ConfigField :label="t('ops.search')" for="ops-email-search" width="md">
        <InputText id="ops-email-search" v-model="f.search" size="small" :placeholder="t('ops.search_emails')" @keyup.enter="filter" />
      </ConfigField>
      <span class="flex-1" />
      <Button
        :label="t('ops.resend_n', { n: selected.length })"
        icon="pi pi-replay"
        size="small"
        outlined
        :disabled="!selected.length || busy"
        data-testid="ops-email-resend"
        @click="resend(selected)"
      />
      <Button
        :label="t('ops.cancel_n', { n: selected.length })"
        icon="pi pi-ban"
        size="small"
        severity="secondary"
        outlined
        :disabled="!selected.some((e) => e.raw_status === 'pending') || busy"
        @click="cancel(selected)"
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
      data-testid="ops-email-table"
      @page="onPage"
      @row-click="(e) => show(e.data as OpsEmail)"
    >
      <template #empty>
        <EmptyState icon="pi pi-envelope" :title="filtered ? t('ops.no_emails_match') : t('ops.no_emails')" :description="t('ops.no_emails_text')">
          <Button v-if="filtered" :label="t('ops.clear_filter')" icon="pi pi-filter-slash" size="small" text @click="clearFilter" />
        </EmptyState>
      </template>
      <Column selection-mode="multiple" header-style="width: 3rem" />
      <Column :header="t('ops.status')">
        <template #body="{ data }"><Tag :severity="severity((data as OpsEmail).status)" :value="t(`ops.email_status.${(data as OpsEmail).status}`)" /></template>
      </Column>
      <Column :header="t('ops.email')">
        <template #body="{ data }">
          <div class="font-medium" dir="auto">{{ (data as OpsEmail).subject }}</div>
          <div class="text-xs text-muted-color ltr-value truncate max-w-md">{{ (data as OpsEmail).to.join(', ') }}</div>
        </template>
      </Column>
      <Column :header="t('ops.form')">
        <template #body="{ data }">
          <span v-if="(data as OpsEmail).form" class="text-sm">{{ (data as OpsEmail).form!.name }}</span>
          <EmptyValue v-else :text="t('ops.no_form')" />
          <div v-if="(data as OpsEmail).rule" class="text-xs text-muted-color">{{ (data as OpsEmail).rule }}</div>
        </template>
      </Column>
      <Column :header="t('ops.attempts')">
        <template #body="{ data }"
          ><span class="tabular-nums">{{ n((data as OpsEmail).attempts) }} / {{ n((data as OpsEmail).max_attempts) }}</span></template
        >
      </Column>
      <Column :header="t('ops.error')">
        <template #body="{ data }"
          ><span v-if="(data as OpsEmail).error" class="text-xs text-danger line-clamp-2 max-w-xs" dir="auto">{{ (data as OpsEmail).error }}</span
          ><EmptyValue v-else :text="t('ops.no_error')"
        /></template>
      </Column>
      <Column :header="t('ops.created')">
        <template #body="{ data }"
          ><span class="text-sm whitespace-nowrap text-muted-color">{{ when((data as OpsEmail).created_at) ?? '' }}</span></template
        >
      </Column>
    </DataTable>

    <Dialog
      :visible="!!open"
      modal
      :header="open?.subject"
      :style="{ width: '56rem', maxWidth: 'calc(100vw - 2rem)' }"
      data-testid="ops-email-dialog"
      @update:visible="(v: boolean) => !v && (open = null)"
    >
      <div v-if="open" class="flex flex-col gap-4">
        <dl class="ops-facts">
          <dt>{{ t('ops.status') }}</dt>
          <dd><Tag :severity="severity(open.status)" :value="t(`ops.email_status.${open.status}`)" /></dd>
          <dt>{{ t('ops.form') }}</dt>
          <dd>
            <RouterLink v-if="open.form && open.record" :to="`/app/${open.form.uuid}/${open.record}`" class="text-primary">{{ open.form.name }}</RouterLink>
            <span v-else-if="open.form">{{ open.form.name }}</span>
            <EmptyValue v-else :compact="false" :text="t('ops.no_form')" />
          </dd>
          <dt>{{ t('ops.rule') }}</dt>
          <dd>{{ open.rule ?? t(`ops.source.${open.source}`) }}</dd>
          <dt>{{ t('ops.attempts') }}</dt>
          <dd>{{ n(open.attempts) }} / {{ n(open.max_attempts) }}</dd>
          <dt>{{ t('ops.sent') }}</dt>
          <dd>
            <template v-if="open.sent_at">{{ when(open.sent_at) }}</template>
            <EmptyValue v-else :compact="false" :text="t('ops.not_sent')" />
          </dd>
          <template v-if="open.error">
            <dt>{{ t('ops.error') }}</dt>
            <dd class="text-danger ltr-value" dir="ltr">{{ open.error }}</dd>
          </template>
        </dl>
        <iframe v-if="htmlUrl" :src="htmlUrl" sandbox="" class="ops-frame" :title="t('ops.rendered')" data-testid="ops-email-html" />
        <div class="flex flex-col gap-2">
          <h3 class="m-0 text-sm font-semibold">{{ t('ops.edit_recipients') }}</h3>
          <ConfigField v-for="k in ['to', 'cc', 'bcc'] as const" :key="k" :label="t(`ops.rcpt.${k}`)" :for="`ops-rcpt-${k}`" width="lg" :hint="k === 'to' ? t('ops.rcpt_hint') : undefined">
            <Textarea :id="`ops-rcpt-${k}`" v-model="editing[k]" rows="1" auto-resize dir="ltr" :data-testid="`ops-rcpt-${k}`" />
          </ConfigField>
          <small v-if="editError" class="field-error" role="alert">{{ editError }}</small>
        </div>
      </div>
      <template #footer>
        <Button :label="t('common.close')" text @click="open = null" />
        <Button v-if="open?.raw_status === 'pending'" :label="t('ops.cancel_email')" severity="secondary" outlined :disabled="busy" @click="cancelOpen" />
        <Button :label="t('ops.resend_to_these')" icon="pi pi-replay" :loading="busy" :disabled="!list(editing.to).length" data-testid="ops-email-resend-edited" @click="resendEdited" />
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
.ops-frame {
  width: 100%;
  height: 22rem;
  border: 1px solid var(--color-line);
  border-radius: 0.5rem;
  background: var(--color-paper);
}
</style>
