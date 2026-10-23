<script setup lang="ts">
import Button from 'primevue/button'
import Column from 'primevue/column'
import DataTable from 'primevue/datatable'
import Message from 'primevue/message'
import Select from 'primevue/select'
import { useToast } from 'primevue/usetoast'
import { computed, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { get } from '@/api/http'
import EmptyValue from '@/components/EmptyValue.vue'
import { formatDate, formatDecimal } from '@/runtime/format'
import { humanize } from '@/runtime/i18nText'
import { useSession } from '@/stores/session'
import { errorText } from '../admin/building/shared'

/**
 * Usage insight (specification §4.37): which forms are used, by whom and how
 * often, where people abandon a form and which fields they leave empty, so
 * that forms can be improved on evidence.
 */
interface FormUsage {
  uuid: string
  key: string
  name: string | null
  views: number
  starts: number
  submits: number
  abandons: number
  completion: number | null
  people: number
  last_used: string | null
}
interface FormDetail {
  daily: { day: string; form_view: number; form_start: number; form_submit: number; form_abandon: number }[]
  people: { name: string | null; views: number; submits: number }[]
  anonymous: boolean
  submits: number
  empty_fields: { uuid: string | null; key: string | null; label: string | null; count: number; share: number | null }[]
}

const { t, locale } = useI18n()
/** Counts and dates follow the person's own number and date preferences. */
const n = (v: number) => formatDecimal(String(v), { decimals: 0, thousands: true, locale: locale.value })
const toast = useToast()
const session = useSession()
const days = ref(30)
const ranges = computed(() => [7, 30, 90, 365].map((v) => ({ value: v, label: t('usage.last_days', { n: n(v) }) })))
const loading = ref(false)
const tracking = ref<'people' | 'anonymous' | 'off'>('people')
const forms = ref<FormUsage[]>([])
const selected = ref<FormUsage | null>(null)
const detail = ref<FormDetail | null>(null)

function range(): { from: string; to: string } {
  const to = new Date()
  const from = new Date(to.getTime() - (days.value - 1) * 86_400_000)
  const iso = (x: Date) => x.toISOString().slice(0, 10)
  return { from: iso(from), to: iso(to) }
}
async function load(): Promise<void> {
  loading.value = true
  try {
    const r = (await get<{ data: { tracking: typeof tracking.value; forms: FormUsage[] } }>('/usage/forms', range())).data
    tracking.value = r.tracking
    forms.value = r.forms
    if (selected.value) await open(forms.value.find((f) => f.uuid === selected.value!.uuid) ?? null)
  } catch (e) {
    toast.add({ severity: 'error', summary: errorText(e, t('usage.load_failed')), life: 6000 })
  } finally {
    loading.value = false
  }
}
async function open(f: FormUsage | null): Promise<void> {
  selected.value = f
  detail.value = null
  if (!f) return
  try {
    detail.value = (await get<{ data: FormDetail }>(`/usage/forms/${f.uuid}`, range())).data
  } catch (e) {
    toast.add({ severity: 'error', summary: errorText(e, t('usage.load_failed')), life: 6000 })
  }
}
watch(days, load, { immediate: true })

const nameOf = (f: { name: string | null; key: string }) => f.name || humanize(f.key)
const pct = (v: number | null) => (v === null ? null : t('usage.percent', { n: n(Math.round(v * 100)) }))
const day = (iso: string) => formatDate(iso, locale.value)
const used = computed(() => forms.value.filter((f) => f.views + f.submits > 0))
const unused = computed(() => forms.value.filter((f) => f.views + f.submits === 0))
</script>

<template>
  <div class="flex flex-col gap-4" data-testid="usage-insight">
    <header class="page-head">
      <div class="flex-1 min-w-0">
        <h1 class="page-title !mb-1">{{ t('admin.area.usage_insight') }}</h1>
        <p class="m-0 text-sm text-muted-color max-w-[var(--measure)]">{{ t('usage.subtitle') }}</p>
      </div>
      <Select v-model="days" :options="ranges" option-label="label" option-value="value" size="small" :aria-label="t('usage.period')" data-testid="usage-range" />
    </header>
    <Message v-if="tracking !== 'people'" :severity="tracking === 'off' ? 'warn' : 'info'" :closable="false" data-testid="usage-tracking">
      {{ t(`usage.tracking.${tracking}`) }}
      <RouterLink v-if="session.can('system.manage_settings')" :to="{ name: 'admin.settings', params: { group: 'adoption' } }" class="ms-1 underline">{{ t('usage.change_setting') }}</RouterLink>
    </Message>

    <DataTable
      :value="used"
      data-key="uuid"
      :loading="loading"
      size="small"
      class="records-table"
      row-hover
      :selection="selected"
      selection-mode="single"
      @row-click="(e) => open(e.data as FormUsage)"
    >
      <template #empty>
        <span class="text-muted-color">{{ t('usage.nothing_yet') }}</span>
      </template>
      <Column :header="t('usage.form')">
        <template #body="{ data }"
          ><span class="font-medium" dir="auto">{{ nameOf(data) }}</span></template
        >
      </Column>
      <Column :header="t('usage.views')" class="text-end"
        ><template #body="{ data }">{{ n(data.views) }}</template></Column
      >
      <Column :header="t('usage.starts')" class="text-end"
        ><template #body="{ data }">{{ n(data.starts) }}</template></Column
      >
      <Column :header="t('usage.submits')" class="text-end"
        ><template #body="{ data }">{{ n(data.submits) }}</template></Column
      >
      <Column :header="t('usage.abandons')" class="text-end"
        ><template #body="{ data }">{{ n(data.abandons) }}</template></Column
      >
      <Column :header="t('usage.completion')" class="text-end">
        <template #body="{ data }"
          ><template v-if="data.completion !== null">{{ pct(data.completion) }}</template
          ><EmptyValue v-else
        /></template>
      </Column>
      <Column v-if="tracking === 'people'" :header="t('usage.people')" class="text-end"
        ><template #body="{ data }">{{ n(data.people) }}</template></Column
      >
      <Column :header="t('usage.last_used')">
        <template #body="{ data }"
          ><template v-if="data.last_used">{{ day(data.last_used) }}</template
          ><EmptyValue v-else
        /></template>
      </Column>
    </DataTable>
    <p v-if="unused.length" class="m-0 text-sm text-muted-color" data-testid="usage-unused">
      {{ t('usage.unused', { n: n(unused.length) }) }} <span dir="auto">{{ unused.map(nameOf).join(t('usage.list_separator')) }}</span>
    </p>

    <section v-if="selected" class="flex flex-col gap-4 rounded-xl border border-line bg-card p-4" data-testid="usage-detail">
      <header class="flex items-center gap-2">
        <h2 class="m-0 text-base font-semibold flex-1" dir="auto">{{ nameOf(selected) }}</h2>
        <Button icon="pi pi-times" text rounded size="small" :aria-label="t('usage.close')" @click="open(null)" />
      </header>
      <div v-if="detail" class="grid gap-6 lg:grid-cols-2">
        <div class="flex flex-col gap-2">
          <h3 class="m-0 text-sm font-semibold">{{ t('usage.empty_fields') }}</h3>
          <p class="m-0 text-xs text-muted-color">{{ t('usage.empty_fields_hint', { n: n(detail.submits) }) }}</p>
          <p v-if="!detail.empty_fields.length" class="m-0 text-sm text-muted-color">{{ t('usage.no_empty_fields') }}</p>
          <ul v-else class="m-0 p-0 list-none flex flex-col gap-2" data-testid="usage-empty-fields">
            <li v-for="(f, i) in detail.empty_fields" :key="i" class="flex flex-col gap-1">
              <div class="flex items-center gap-2 text-sm">
                <span class="flex-1 min-w-0 truncate" dir="auto">{{ f.label || (f.key ? humanize(f.key) : t('help.target_missing')) }}</span>
                <span class="tabular-nums">{{ pct(f.share) ?? n(f.count) }}</span>
              </div>
              <div class="h-1.5 rounded-full bg-subtle overflow-hidden" aria-hidden="true">
                <div class="h-full rounded-full bg-primary" :style="{ width: `${Math.round((f.share ?? 0) * 100)}%` }" />
              </div>
            </li>
          </ul>
        </div>
        <div class="flex flex-col gap-2">
          <h3 class="m-0 text-sm font-semibold">{{ t('usage.who') }}</h3>
          <p v-if="detail.anonymous" class="m-0 text-sm text-muted-color">{{ t('usage.who_anonymous') }}</p>
          <DataTable v-else :value="detail.people" size="small" class="records-table">
            <template #empty>
              <span class="text-muted-color">{{ t('usage.nothing_yet') }}</span>
            </template>
            <Column :header="t('usage.person')">
              <template #body="{ data }"
                ><span dir="auto">{{ data.name ?? t('help.target_missing') }}</span></template
              >
            </Column>
            <Column :header="t('usage.views')" class="text-end"
              ><template #body="{ data }">{{ n(data.views) }}</template></Column
            >
            <Column :header="t('usage.submits')" class="text-end"
              ><template #body="{ data }">{{ n(data.submits) }}</template></Column
            >
          </DataTable>
        </div>
        <div class="flex flex-col gap-2 lg:col-span-2">
          <h3 class="m-0 text-sm font-semibold">{{ t('usage.by_day') }}</h3>
          <DataTable :value="[...detail.daily].reverse()" size="small" class="records-table" scrollable scroll-height="18rem">
            <template #empty>
              <span class="text-muted-color">{{ t('usage.nothing_yet') }}</span>
            </template>
            <Column :header="t('usage.day')"
              ><template #body="{ data }">{{ day(data.day) }}</template></Column
            >
            <Column :header="t('usage.views')" class="text-end"
              ><template #body="{ data }">{{ n(data.form_view) }}</template></Column
            >
            <Column :header="t('usage.starts')" class="text-end"
              ><template #body="{ data }">{{ n(data.form_start) }}</template></Column
            >
            <Column :header="t('usage.submits')" class="text-end"
              ><template #body="{ data }">{{ n(data.form_submit) }}</template></Column
            >
            <Column :header="t('usage.abandons')" class="text-end"
              ><template #body="{ data }">{{ n(data.form_abandon) }}</template></Column
            >
          </DataTable>
        </div>
      </div>
    </section>
  </div>
</template>
