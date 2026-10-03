<script setup lang="ts">
import Button from 'primevue/button'
import Column from 'primevue/column'
import DataTable, { type DataTablePageEvent } from 'primevue/datatable'
import DatePicker from 'primevue/datepicker'
import Dialog from 'primevue/dialog'
import InputText from 'primevue/inputtext'
import Message from 'primevue/message'
import Select from 'primevue/select'
import { onMounted, reactive, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { get, send } from '@/api/http'
import { useSession } from '@/stores/session'

interface Entry {
  id: number
  occurred_at: string
  event: string
  category: string
  object_type: string | null
  object_id: number | null
  actor_user_id: number | null
  actor?: { name: string } | null
  ip_address: string | null
  user_agent: string | null
  correlation_id: string | null
  changes: { field_key: string; old: unknown; new: unknown }[] | null
  meta: Record<string, unknown> | null
  hash: string
}
const { t } = useI18n()
const session = useSession()
const rows = ref<Entry[]>([])
const total = ref(0)
const loading = ref(false)
const f = reactive({ from: null as Date | null, to: null as Date | null, event: '', category: null as string | null, object_type: '', correlation_id: '', page: 1, per_page: 25 })
const detail = ref<Entry | null>(null)
const verify = ref<{ verified: number; breaks: unknown[] } | null>(null)
const categories = ['data', 'workflow', 'auth', 'access', 'config', 'operations', 'export', 'schema', 'security']
const iso = (d: Date | null) => (d ? `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}` : undefined)
function params(): Record<string, unknown> {
  return { from: iso(f.from), to: iso(f.to), event: f.event || undefined, category: f.category || undefined, object_type: f.object_type || undefined, correlation_id: f.correlation_id || undefined }
}

async function load(): Promise<void> {
  loading.value = true
  try {
    const res = await get<{ data: Entry[]; total: number }>('/audit', { ...params(), page: f.page, per_page: f.per_page })
    rows.value = res.data
    total.value = res.total
  } finally {
    loading.value = false
  }
}
onMounted(load)
function onPage(e: DataTablePageEvent): void {
  f.page = e.page + 1
  f.per_page = e.rows
  load()
}
function exportCsv(): void {
  const q = new URLSearchParams(Object.entries(params()).filter(([, v]) => v !== undefined) as [string, string][])
  window.location.href = `/api/v1/audit/export?${q}`
}
async function verifyChain(): Promise<void> {
  verify.value = (await send<{ data: { verified: number; breaks: unknown[] } }>('post', '/audit/verify-chain')).data
}
const fmt = (v: unknown) => (v === null || v === undefined ? '—' : typeof v === 'object' ? JSON.stringify(v) : String(v))
</script>

<template>
  <div class="flex flex-wrap items-center gap-3 mb-4">
    <h1 class="page-title !mb-0 flex-1">{{ t('admin.area.audit_log') }}</h1>
    <Button severity="secondary" icon="pi pi-verified" :label="t('audit.verify')" @click="verifyChain" />
    <Button severity="secondary" icon="pi pi-download" :label="t('audit.export_csv')" @click="exportCsv" />
  </div>
  <Message v-if="verify" :severity="verify.breaks.length ? 'error' : 'success'" class="mb-3">{{
    verify.breaks.length ? t('audit.breaks_found', { n: verify.breaks.length }) : t('audit.chain_ok', { n: verify.verified })
  }}</Message>
  <form class="flex flex-wrap items-end gap-2 mb-3" @submit.prevent="((f.page = 1), load())">
    <div class="field">
      <label>{{ t('audit.from') }}</label
      ><DatePicker v-model="f.from" show-button-bar />
    </div>
    <div class="field">
      <label>{{ t('audit.to') }}</label
      ><DatePicker v-model="f.to" show-button-bar />
    </div>
    <div class="field">
      <label for="ae">{{ t('audit.event') }}</label
      ><InputText id="ae" v-model="f.event" class="ltr-value" />
    </div>
    <div class="field">
      <label for="ac">{{ t('audit.category') }}</label
      ><Select v-model="f.category" input-id="ac" :options="categories.map((c) => ({ v: c, l: t(`audit.cat.${c}`) }))" option-label="l" option-value="v" show-clear />
    </div>
    <div class="field">
      <label for="ao">{{ t('audit.object_type') }}</label
      ><InputText id="ao" v-model="f.object_type" class="ltr-value" />
    </div>
    <div class="field">
      <label for="acid">{{ t('audit.correlation_id') }}</label
      ><InputText id="acid" v-model="f.correlation_id" class="ltr-value" />
    </div>
    <Button type="submit" icon="pi pi-filter" :label="t('common.filter')" />
  </form>
  <DataTable
    :value="rows"
    lazy
    paginator
    :rows="f.per_page"
    :total-records="total"
    :loading="loading"
    data-key="id"
    size="small"
    selection-mode="single"
    @row-click="(e) => (detail = e.data)"
    @page="onPage"
  >
    <template #empty>{{ t('common.no_results') }}</template>
    <Column :header="t('audit.when')"
      ><template #body="{ data }"
        ><span class="ltr-value text-sm">{{ new Date(data.occurred_at).toLocaleString(session.locale) }}</span></template
      ></Column
    >
    <Column :header="t('audit.event')"
      ><template #body="{ data }"
        ><span class="ltr-value">{{ data.event }}</span></template
      ></Column
    >
    <Column :header="t('audit.category')"
      ><template #body="{ data }">{{ t(`audit.cat.${data.category}`) }}</template></Column
    >
    <Column :header="t('audit.object')"
      ><template #body="{ data }"
        ><span class="ltr-value">{{ data.object_type ? `${data.object_type} #${data.object_id}` : '—' }}</span></template
      ></Column
    >
    <Column :header="t('audit.actor')"
      ><template #body="{ data }">{{ data.actor?.name ?? (data.actor_user_id ? `#${data.actor_user_id}` : t('audit.system')) }}</template></Column
    >
    <Column field="ip_address" :header="t('profile.ip')"
      ><template #body="{ data }"
        ><span class="ltr-value">{{ data.ip_address }}</span></template
      ></Column
    >
  </DataTable>
  <Dialog :visible="!!detail" modal :header="detail?.event" :style="{ width: '44rem' }" @update:visible="(v: boolean) => !v && (detail = null)">
    <div v-if="detail" class="flex flex-col gap-3 text-sm">
      <table v-if="detail.changes?.length" class="w-full">
        <thead>
          <tr class="text-start">
            <th class="text-start">{{ t('audit.field') }}</th>
            <th class="text-start">{{ t('audit.old') }}</th>
            <th class="text-start">{{ t('audit.new') }}</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="c in detail.changes" :key="c.field_key" class="align-top">
            <td class="ltr-value pe-2">{{ c.field_key }}</td>
            <td class="pe-2 break-all">{{ fmt(c.old) }}</td>
            <td class="break-all">{{ fmt(c.new) }}</td>
          </tr>
        </tbody>
      </table>
      <div v-if="detail.meta">
        <strong>{{ t('audit.details') }}:</strong> <code class="ltr-value break-all">{{ JSON.stringify(detail.meta) }}</code>
      </div>
      <div>
        <strong>{{ t('audit.correlation_id') }}:</strong> <span class="ltr-value">{{ detail.correlation_id }}</span>
      </div>
      <div>
        <strong>{{ t('profile.device') }}:</strong> {{ detail.user_agent }}
      </div>
      <div>
        <strong>{{ t('audit.hash') }}:</strong> <code class="ltr-value break-all">{{ detail.hash }}</code>
      </div>
    </div>
  </Dialog>
</template>
