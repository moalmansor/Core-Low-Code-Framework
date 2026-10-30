<script setup lang="ts">
import Button from 'primevue/button'
import Column from 'primevue/column'
import DataTable from 'primevue/datatable'
import InputNumber from 'primevue/inputnumber'
import Select from 'primevue/select'
import Tag from 'primevue/tag'
import { useToast } from 'primevue/usetoast'
import { computed, onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import ConfigItem from '@/components/config/ConfigItem.vue'
import ConfigField from '@/components/config/ConfigField.vue'
import ConfigSaveBar from '@/components/config/ConfigSaveBar.vue'
import ConfigSection from '@/components/config/ConfigSection.vue'
import EmptyState from '@/components/config/EmptyState.vue'
import TabIntro from '@/components/config/TabIntro.vue'
import EmptyValue from '@/components/EmptyValue.vue'
import { formatDatetime } from '@/runtime/format'
import { errorText, fieldErrors } from '../admin/building/shared'
import { formatBytes, retentionApi, severityOf, type PolicyOptions, type QuotaRow, type StorageOverview, type UsageReport } from './api'

/**
 * Storage (specification §4.26): how the partitioned logs grow month by
 * month, the largest tables and the volume (alert rules in the Operations
 * Center warn when they cross a threshold), storage quotas with a warning
 * and a hard limit, and storage used by form and by user.
 */
const { t, locale } = useI18n()
const toast = useToast()
const overview = ref<StorageOverview | null>(null)
const usage = ref<UsageReport | null>(null)
const options = ref<PolicyOptions | null>(null)
const quotas = ref<QuotaRow[]>([])
const hash = ref('')
const saved = ref('')
const saving = ref(false)
const errors = ref<Record<string, string>>({})
onMounted(async () => {
  try {
    const [o, u, q, opts] = await Promise.all([retentionApi.storage(), retentionApi.usage(), retentionApi.quotas(), retentionApi.options()])
    overview.value = o
    usage.value = u
    options.value = opts
    accept(q)
  } catch (e) {
    toast.add({ severity: 'error', summary: errorText(e, t('ret.load_failed')), life: 6000 })
  }
})
function accept(q: { quotas: QuotaRow[]; hash: string }): void {
  quotas.value = q.quotas
  hash.value = q.hash
  saved.value = JSON.stringify(body())
}
const body = () => quotas.value.map((q) => ({ scope: q.scope, target: q.scope === 'organization' ? null : q.target, warn_mb: q.warn_mb, limit_mb: q.limit_mb }))
const dirty = computed(() => JSON.stringify(body()) !== saved.value)
const bytes = (n: number) => formatBytes(n, locale.value)
const scopeOptions = computed(() => (['organization', 'application', 'form'] as const).map((v) => ({ value: v, label: t(`ret.scope.${v}`) })))
const month = (iso: string) => new Intl.DateTimeFormat(locale.value, { year: 'numeric', month: 'long', timeZone: 'UTC' }).format(new Date(iso))
const months = (p: StorageOverview['partitions'][number]) => p.months.filter((m) => m.rows > 0)
function add(): void {
  quotas.value.push({ scope: 'form', target: null, warn_mb: 800, limit_mb: 1000 })
}
async function save(): Promise<void> {
  saving.value = true
  errors.value = {}
  try {
    accept(await retentionApi.saveQuotas(body() as QuotaRow[], hash.value))
    toast.add({ severity: 'success', summary: t('ret.quotas_saved'), life: 3000 })
  } catch (e) {
    errors.value = fieldErrors(e)
    toast.add({ severity: 'error', summary: errorText(e, t('ret.save_failed')), life: 8000 })
  } finally {
    saving.value = false
  }
}
function discard(): void {
  retentionApi.quotas().then(accept)
  errors.value = {}
}
</script>

<template>
  <div class="flex flex-col gap-6" data-testid="ret-storage">
    <TabIntro :title="t('ret.tab.storage')" :text="t('ret.storage_intro')" />

    <ConfigSection id="st-logs" :title="t('ret.section.logs')" :description="t('ret.logs_hint')">
      <div v-for="p in overview?.partitions ?? []" :key="p.table" class="flex flex-col gap-2">
        <h3 class="m-0 text-sm font-semibold">{{ t(`ret.source.${p.table}`) }}</h3>
        <DataTable :value="months(p)" data-key="to" size="small" class="records-table">
          <template #empty>{{ t('ret.no_log_rows') }}</template>
          <Column :header="t('ret.month')">
            <template #body="{ data }">{{ data.from ? month(data.from) : t('ret.before', { when: month(data.to) }) }}</template>
          </Column>
          <Column :header="t('ret.rows')">
            <template #body="{ data }">{{ new Intl.NumberFormat(locale).format(data.rows) }}</template>
          </Column>
        </DataTable>
        <p class="m-0 text-sm text-muted-color">{{ t('ret.months_ahead', { count: p.months.length }) }}</p>
      </div>
    </ConfigSection>

    <ConfigSection id="st-tables" :title="t('ret.section.tables')" :description="t('ret.tables_hint')">
      <p v-if="overview?.volume.used_percent != null" class="m-0 text-sm" data-testid="st-volume">
        {{ t('ret.volume', { used: overview.volume.used_percent, free: bytes(overview.volume.free ?? 0) }) }}
      </p>
      <DataTable :value="overview?.tables ?? []" data-key="table" size="small" class="records-table">
        <template #empty>{{ t('ret.no_tables') }}</template>
        <Column :header="t('ret.table')">
          <template #body="{ data }"
            ><span class="ltr-value">{{ data.table }}</span></template
          >
        </Column>
        <Column :header="t('ret.size')">
          <template #body="{ data }">{{ bytes(data.bytes) }}</template>
        </Column>
      </DataTable>
    </ConfigSection>

    <ConfigSection id="st-quotas" :title="t('ret.section.quotas')" :description="t('ret.quotas_hint')" :count="quotas.length">
      <EmptyState v-if="!quotas.length" icon="pi pi-database" :title="t('ret.quotas_empty')" :description="t('ret.quotas_empty_text')" />
      <ConfigItem v-for="(q, i) in quotas" :key="i" :title="q.scope === 'organization' ? t('ret.scope.organization') : (q.label ?? t(`ret.scope.${q.scope}`))" @remove="quotas.splice(i, 1)">
        <div class="cfg-row">
          <ConfigField :label="t('ret.scope_label')" :for="`q-scope-${i}`" width="sm">
            <Select v-model="q.scope" :input-id="`q-scope-${i}`" :options="scopeOptions" option-label="label" option-value="value" size="small" />
          </ConfigField>
          <ConfigField v-if="q.scope !== 'organization'" :label="t(`ret.scope.${q.scope}`)" :for="`q-target-${i}`" width="md" :error="errors[`quotas.${i}.target`]">
            <Select
              v-model="q.target"
              :input-id="`q-target-${i}`"
              :options="q.scope === 'form' ? (options?.forms ?? []) : (options?.applications ?? [])"
              option-label="label"
              option-value="uuid"
              size="small"
              filter
            />
          </ConfigField>
          <ConfigField :label="t('ret.warn_mb')" :for="`q-warn-${i}`" width="sm" :error="errors[`quotas.${i}.warn_mb`]">
            <InputNumber v-model="q.warn_mb" :input-id="`q-warn-${i}`" :min="1" size="small" />
          </ConfigField>
          <ConfigField :label="t('ret.limit_mb')" :for="`q-limit-${i}`" width="sm" :error="errors[`quotas.${i}.limit_mb`]">
            <InputNumber v-model="q.limit_mb" :input-id="`q-limit-${i}`" :min="1" size="small" />
          </ConfigField>
        </div>
        <p v-if="q.state" class="m-0 text-sm flex flex-wrap items-center gap-2">
          <Tag :severity="severityOf(q.state)" :value="t(`ret.quota_state.${q.state}`)" />
          <span>{{ t('ret.used', { used: bytes(q.used_bytes ?? 0) }) }}</span>
          <span v-if="q.measured_at" class="text-muted-color">{{ t('ret.measured', { when: formatDatetime(q.measured_at, locale) }) }}</span>
        </p>
      </ConfigItem>
      <div><Button icon="pi pi-plus" :label="t('ret.add_quota')" size="small" text data-testid="st-add-quota" @click="add" /></div>
    </ConfigSection>

    <ConfigSection id="st-usage" :title="t('ret.section.usage')" :description="usage ? t('ret.usage_total', { total: bytes(usage.total) }) : undefined">
      <div class="grid gap-6 md:grid-cols-2">
        <DataTable :value="usage?.forms ?? []" data-key="uuid" size="small" class="records-table">
          <template #empty>{{ t('ret.no_files') }}</template>
          <Column :header="t('ret.form')">
            <template #body="{ data }"
              ><span dir="auto">{{ data.label }}</span></template
            >
          </Column>
          <Column :header="t('ret.files')">
            <template #body="{ data }">{{ data.files }}</template>
          </Column>
          <Column :header="t('ret.size')">
            <template #body="{ data }">{{ bytes(data.bytes) }}</template>
          </Column>
        </DataTable>
        <DataTable :value="usage?.users ?? []" data-key="uuid" size="small" class="records-table">
          <template #empty>{{ t('ret.no_files') }}</template>
          <Column :header="t('ret.user')">
            <template #body="{ data }"
              ><span v-if="data.name" dir="auto">{{ data.name }}</span
              ><EmptyValue v-else
            /></template>
          </Column>
          <Column :header="t('ret.files')">
            <template #body="{ data }">{{ data.files }}</template>
          </Column>
          <Column :header="t('ret.size')">
            <template #body="{ data }">{{ bytes(data.bytes) }}</template>
          </Column>
        </DataTable>
      </div>
    </ConfigSection>

    <ConfigSaveBar :dirty="dirty" :saving="saving" testid="st-quotas" @save="save" @discard="discard" />
  </div>
</template>
