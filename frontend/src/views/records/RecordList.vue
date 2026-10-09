<script setup lang="ts">
import AutoComplete from 'primevue/autocomplete'
import Button from 'primevue/button'
import Column from 'primevue/column'
import DataTable, { type DataTablePageEvent, type DataTableSortEvent } from 'primevue/datatable'
import IconField from 'primevue/iconfield'
import InputIcon from 'primevue/inputicon'
import InputText from 'primevue/inputtext'
import Menu from 'primevue/menu'
import Message from 'primevue/message'
import Select from 'primevue/select'
import Tag from 'primevue/tag'
import ToggleSwitch from 'primevue/toggleswitch'
import { useConfirm } from 'primevue/useconfirm'
import { useToast } from 'primevue/usetoast'
import { computed, reactive, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRoute, useRouter } from 'vue-router'
import { ApiError, ensureCsrf, get, http, send } from '@/api/http'
import { downloadBlob, fetchOptions, newUuid, type OptionItem } from '@/runtime/api'
import { fieldType } from '@/runtime/fieldTypes'
import { FormIndex } from '@/runtime/formIndex'
import { formatDatetime, formatValue } from '@/runtime/format'
import { pickText } from '@/runtime/i18nText'
import type { ClientDefinition, ClientField, RecordPayload } from '@/runtime/types'
import { useSession } from '@/stores/session'
import ImportDialog from './ImportDialog.vue'

/**
 * Records table of a published form or collection: columns the admin marked
 * visible, search, simple filters on filterable fields, sorting, paging,
 * the trash with restore, export to Excel/CSV with the current filters, and
 * import with a validation report.
 */
const route = useRoute()
const router = useRouter()
const session = useSession()
const { t, locale } = useI18n()
const toast = useToast()
const confirm = useConfirm()

const formUuid = computed(() => String(route.params.form).toLowerCase())
const definition = ref<ClientDefinition | null>(null)
const loadError = ref('')
const rows = ref<RecordPayload[]>([])
const total = ref(0)
const loading = ref(false)
const query = reactive({ search: '', sort: 'updated_at', direction: 'desc' as 'asc' | 'desc', page: 1, per_page: 25, trashed: false })
const filters = reactive<Record<string, unknown>>({})
const showFilters = ref(false)
const importOpen = ref(false)

const index = computed(() => (definition.value ? new FormIndex(definition.value) : null))
const title = computed(() =>
  definition.value ? (pickText(definition.value.names, locale.value) ?? definition.value.name ?? pickText(definition.value.form.i18n.name, locale.value) ?? definition.value.form.key) : '',
)
const can = (ability: string) => session.can(`form.${formUuid.value}.${ability}`)
const canCreate = computed(() => definition.value?.access.modes.create === true && definition.value.form.settings.modes?.create !== false)
const canEdit = computed(() => definition.value?.access.modes.edit === true && definition.value.form.settings.modes?.edit !== false)
const canDelete = computed(() => definition.value?.access.modes.delete === true)
const canRestore = computed(() => can('restore'))
const canExport = computed(() => can('export'))
const canImport = computed(() => can('import'))

const listable = (f: ClientField) => index.value !== null && index.value.isStored(f) && !index.value.fieldRepeater.has(f.uuid)
const columns = computed(() => (definition.value?.fields ?? []).filter((f) => listable(f) && f.table?.visible === true).sort((a, b) => a.order - b.order))
const filterable = computed(() => (definition.value?.fields ?? []).filter((f) => listable(f) && f.table?.filterable === true && (fieldType(f.type)?.filter ?? 'none') !== 'none'))
const columnLabel = (f: ClientField) => pickText(f.i18n.columnLabel, locale.value) ?? pickText(f.i18n.label, locale.value) ?? f.key

async function loadDefinition(): Promise<void> {
  definition.value = null
  loadError.value = ''
  try {
    definition.value = (await get<{ data: ClientDefinition }>(`/r/${formUuid.value}/definition`, { mode: 'view' })).data
    document.title = [title.value, session.systemName].filter(Boolean).join(' · ')
  } catch (e) {
    loadError.value = e instanceof ApiError && e.status !== 0 ? e.message : t('records.load_failed')
  }
}

function activeFilters(): Record<string, unknown> {
  const out: Record<string, unknown> = {}
  for (const [k, v] of Object.entries(filters)) {
    if (v === null || v === undefined || v === '') continue
    if (typeof v === 'object' && !Array.isArray(v)) {
      const r = v as { from?: string | null; to?: string | null; value?: string }
      if ('value' in r) {
        if (r.value) out[k] = r.value
        continue
      }
      const range: Record<string, string> = {}
      if (r.from) range.from = r.from
      if (r.to) range.to = r.to
      if (Object.keys(range).length) out[k] = range
    } else out[k] = v
  }
  return out
}

function params(): Record<string, unknown> {
  return {
    search: query.search.trim() || undefined,
    sort: query.sort,
    direction: query.direction,
    trashed: query.trashed ? 1 : undefined,
    filter: activeFilters(),
  }
}

let seq = 0
async function load(): Promise<void> {
  if (!definition.value) return
  const mine = ++seq
  loading.value = true
  try {
    const res = await get<{ data: RecordPayload[]; meta: { total: number } }>(`/r/${formUuid.value}`, { ...params(), page: query.page, per_page: query.per_page })
    if (mine !== seq) return
    rows.value = res.data
    total.value = res.meta.total
  } catch (e) {
    if (mine === seq && e instanceof ApiError && e.status < 500) toast.add({ severity: 'error', summary: e.message, life: 6000 })
  } finally {
    if (mine === seq) loading.value = false
  }
}

watch(
  formUuid,
  async () => {
    query.page = 1
    query.search = ''
    query.trashed = false
    query.sort = 'updated_at'
    query.direction = 'desc'
    await loadDefinition()
    resetFilters()
    await load()
  },
  { immediate: true },
)

function onPage(e: DataTablePageEvent): void {
  query.page = e.page + 1
  query.per_page = e.rows
  void load()
}
function onSort(e: DataTableSortEvent): void {
  query.sort = typeof e.sortField === 'string' ? e.sortField : 'updated_at'
  query.direction = e.sortOrder === 1 ? 'asc' : 'desc'
  query.page = 1
  void load()
}
let searchTimer: number | undefined
function onSearch(): void {
  window.clearTimeout(searchTimer)
  searchTimer = window.setTimeout(() => {
    query.page = 1
    void load()
  }, 350)
}
function applyFilters(): void {
  query.page = 1
  void load()
}
function clearFilters(): void {
  resetFilters()
  applyFilters()
}
function toggleTrash(on: boolean): void {
  query.trashed = on
  query.page = 1
  void load()
}

// Filter inputs per filter type.
const RANGE_FILTERS = ['number', 'date', 'datetime', 'time']
function resetFilters(): void {
  for (const k of Object.keys(filters)) delete filters[k]
  for (const f of filterable.value) if (RANGE_FILTERS.includes(fieldType(f.type)?.filter ?? '')) filters[f.key] = { from: null, to: null }
}
function rangeOf(key: string): { from: string | null; to: string | null } {
  return (filters[key] as { from: string | null; to: string | null } | undefined) ?? { from: null, to: null }
}
function inputType(f: ClientField): string {
  const kind = fieldType(f.type)?.filter
  return kind === 'date' || kind === 'datetime' ? 'date' : kind === 'time' ? 'time' : 'text'
}
function shownRange(f: ClientField, v: string | null): string {
  const type = inputType(f)
  return (v ?? '').slice(0, type === 'date' ? 10 : type === 'time' ? 5 : undefined)
}
/** Range bound as the server compares it: whole days for date-times, seconds for times. */
function rangeValue(f: ClientField, raw: string, end: boolean): string | null {
  if (!raw) return null
  const kind = fieldType(f.type)?.filter
  if (kind === 'datetime') return `${raw} ${end ? '23:59:59' : '00:00:00'}`
  if (kind === 'time') return raw.length === 5 ? `${raw}:${end ? '59' : '00'}` : raw
  if (kind === 'number') return raw.replace(/[^\d.-]/g, '') || null
  return raw
}
const staticOptions = (f: ClientField) => (f.options?.static ?? []).filter((o) => o.active !== false).map((o) => ({ value: o.value, label: pickText(o.i18n?.label, locale.value) ?? o.value }))
const remoteOptions = reactive<Record<string, OptionItem[]>>({})
async function completeFilter(f: ClientField, q: string): Promise<void> {
  try {
    remoteOptions[f.key] = (await fetchOptions(formUuid.value, f.key, { q })).items
  } catch {
    remoteOptions[f.key] = []
  }
}
const lookupFilter = (key: string) => (filters[key] as OptionItem | null | undefined) ?? null
const isStaticChoice = (f: ClientField) => (f.options?.source ?? 'static') === 'static' && !index.value?.isReference(f)
const booleanOptions = computed(() => [
  { value: 'true', label: t('runtime.yes') },
  { value: 'false', label: t('runtime.no') },
])

// Cells
function cell(r: RecordPayload, f: ClientField): string {
  return formatValue(index.value, f, r.values[f.key], { locale: locale.value, references: r.references, files: r.files, yes: t('runtime.yes'), no: t('runtime.no') }) || '—'
}

// Row actions
const actionsMenu = ref<InstanceType<typeof Menu> | null>(null)
const actionsFor = ref<RecordPayload | null>(null)
const menuItems = computed(() => {
  const r = actionsFor.value
  if (!r) return []
  if (query.trashed) return [{ label: t('records.restore'), icon: 'pi pi-replay', visible: canRestore.value, command: () => restore(r) }]
  return [
    { label: t('records.view'), icon: 'pi pi-eye', command: () => router.push({ name: 'records.view', params: { form: formUuid.value, record: r.uuid } }) },
    { label: t('common.edit'), icon: 'pi pi-pencil', visible: canEdit.value, command: () => router.push({ name: 'records.edit', params: { form: formUuid.value, record: r.uuid } }) },
    { separator: true, visible: canDelete.value },
    { label: t('common.delete'), icon: 'pi pi-trash', class: 'text-red-600', visible: canDelete.value, command: () => remove(r) },
  ]
})
function openActions(e: Event, r: RecordPayload): void {
  actionsFor.value = r
  actionsMenu.value?.toggle(e)
}

function remove(r: RecordPayload): void {
  confirm.require({
    message: t('records.delete_confirm', { title: r.title ?? r.system.record_number ?? '' }),
    header: t('common.confirm'),
    acceptProps: { label: t('common.delete'), severity: 'danger' },
    rejectProps: { label: t('common.cancel'), severity: 'secondary' },
    accept: async () => {
      try {
        await deleteRecord(r)
        toast.add({ severity: 'success', summary: t('records.deleted'), life: 4000 })
        await load()
      } catch (e) {
        if (e instanceof ApiError) toast.add({ severity: 'error', summary: e.code === 'conflict' ? t('records.delete_conflict') : e.message, life: 8000 })
        await load()
      }
    },
  })
}
async function deleteRecord(r: RecordPayload): Promise<void> {
  await ensureCsrf()
  await http.delete(`/r/${formUuid.value}/${r.uuid}`, { data: { row_version: r.row_version }, headers: { 'Idempotency-Key': newUuid() } })
}
async function restore(r: RecordPayload): Promise<void> {
  try {
    await send('post', `/r/${formUuid.value}/${r.uuid}/restore`)
    toast.add({ severity: 'success', summary: t('records.restored'), life: 4000 })
    await load()
  } catch (e) {
    if (e instanceof ApiError) toast.add({ severity: 'error', summary: e.message, life: 6000 })
  }
}

// Export
const exportMenu = ref<InstanceType<typeof Menu> | null>(null)
const exporting = ref(false)
async function exportAs(format: 'xlsx' | 'csv'): Promise<void> {
  exporting.value = true
  try {
    await downloadBlob(`/r/${formUuid.value}/export`, { ...params(), format }, `${definition.value?.form.key ?? 'records'}.${format}`)
  } catch (e) {
    toast.add({ severity: 'error', summary: e instanceof ApiError && e.status === 429 ? t('records.export_throttled') : t('records.export_failed'), life: 6000 })
  } finally {
    exporting.value = false
  }
}
const exportItems = computed(() => [
  { label: t('records.export_xlsx'), icon: 'pi pi-file-excel', command: () => exportAs('xlsx') },
  { label: t('records.export_csv'), icon: 'pi pi-file', command: () => exportAs('csv') },
])
</script>

<template>
  <Message v-if="loadError" severity="error" data-testid="records-error">{{ loadError }}</Message>
  <template v-else-if="definition">
    <div class="flex flex-wrap items-center gap-3 mb-4">
      <h1 class="page-title !mb-0 flex-1 flex items-center gap-2" data-testid="records-title">
        <i v-if="definition.form.icon" :class="definition.form.icon" />{{ title }}
        <Tag v-if="query.trashed" severity="warn" :value="t('records.trash')" />
      </h1>
      <Button v-if="canImport && !query.trashed" icon="pi pi-upload" :label="t('records.import')" severity="secondary" outlined data-testid="records-import" @click="importOpen = true" />
      <Button
        v-if="canExport"
        icon="pi pi-download"
        :label="t('records.export')"
        severity="secondary"
        outlined
        :loading="exporting"
        aria-haspopup="true"
        data-testid="records-export"
        @click="(e: Event) => exportMenu?.toggle(e)"
      />
      <Menu ref="exportMenu" :model="exportItems" popup />
      <RouterLink v-if="canCreate && !query.trashed" v-slot="{ navigate }" :to="{ name: 'records.create', params: { form: formUuid } }" custom>
        <Button icon="pi pi-plus" :label="t('records.new')" data-testid="records-new" @click="navigate" />
      </RouterLink>
    </div>

    <div class="flex flex-wrap items-center gap-2 mb-3">
      <IconField>
        <InputIcon class="pi pi-search" />
        <InputText v-model="query.search" :placeholder="t('common.search')" :aria-label="t('common.search')" data-testid="records-search" @input="onSearch" @keyup.enter="applyFilters" />
      </IconField>
      <Button v-if="filterable.length" icon="pi pi-filter" :label="t('records.filters')" severity="secondary" text :aria-expanded="showFilters" @click="showFilters = !showFilters" />
      <div class="flex-1" />
      <label v-if="canRestore" class="flex items-center gap-2 text-sm">
        <ToggleSwitch :model-value="query.trashed" data-testid="records-trash" @update:model-value="toggleTrash" />{{ t('records.show_trash') }}
      </label>
    </div>

    <div v-if="showFilters && filterable.length" class="rounded-lg border border-surface-200 dark:border-surface-700 p-3 mb-3" data-testid="records-filters">
      <div class="form-grid">
        <div v-for="f in filterable" :key="f.uuid" class="field">
          <label :for="`flt-${f.key}`">{{ columnLabel(f) }}</label>
          <template v-if="['number', 'date', 'datetime', 'time'].includes(fieldType(f.type)?.filter ?? '')">
            <div class="flex gap-2">
              <InputText
                :id="`flt-${f.key}`"
                :type="inputType(f)"
                :model-value="shownRange(f, rangeOf(f.key).from)"
                class="flex-1 min-w-0 ltr-value"
                :placeholder="t('runtime.range_from')"
                :aria-label="`${columnLabel(f)} ${t('runtime.range_from')}`"
                @update:model-value="(v) => (rangeOf(f.key).from = rangeValue(f, v ?? '', false))"
              />
              <InputText
                :type="inputType(f)"
                :model-value="shownRange(f, rangeOf(f.key).to)"
                class="flex-1 min-w-0 ltr-value"
                :placeholder="t('runtime.range_to')"
                :aria-label="`${columnLabel(f)} ${t('runtime.range_to')}`"
                @update:model-value="(v) => (rangeOf(f.key).to = rangeValue(f, v ?? '', true))"
              />
            </div>
          </template>
          <Select
            v-else-if="fieldType(f.type)?.filter === 'boolean'"
            :id="`flt-${f.key}`"
            v-model="filters[f.key] as string"
            :options="booleanOptions"
            option-label="label"
            option-value="value"
            show-clear
            :placeholder="t('records.any')"
          />
          <Select
            v-else-if="fieldType(f.type)?.filter === 'choice' && isStaticChoice(f)"
            :id="`flt-${f.key}`"
            v-model="filters[f.key] as string"
            :options="staticOptions(f)"
            option-label="label"
            option-value="value"
            show-clear
            filter
            :placeholder="t('records.any')"
          />
          <AutoComplete
            v-else-if="fieldType(f.type)?.filter === 'lookup' || fieldType(f.type)?.filter === 'choice'"
            :input-id="`flt-${f.key}`"
            :model-value="lookupFilter(f.key)"
            :suggestions="remoteOptions[f.key] ?? []"
            option-label="label"
            dropdown
            force-selection
            :placeholder="t('records.any')"
            @complete="(e: { query: string }) => completeFilter(f, e.query)"
            @update:model-value="(v: unknown) => (filters[f.key] = v && typeof v === 'object' ? { value: (v as OptionItem).value, label: (v as OptionItem).label } : null)"
          />
          <InputText v-else :id="`flt-${f.key}`" v-model="filters[f.key] as string" @keyup.enter="applyFilters" />
        </div>
      </div>
      <div class="flex gap-2 mt-3">
        <Button :label="t('records.apply_filters')" icon="pi pi-check" size="small" data-testid="records-apply-filters" @click="applyFilters" />
        <Button :label="t('records.clear_filters')" icon="pi pi-times" size="small" severity="secondary" text @click="clearFilters" />
      </div>
    </div>

    <DataTable
      :value="rows"
      lazy
      paginator
      :rows="query.per_page"
      :rows-per-page-options="[10, 25, 50, 100]"
      :total-records="total"
      :loading="loading"
      data-key="uuid"
      size="small"
      striped-rows
      removable-sort
      :sort-field="query.sort"
      :sort-order="query.direction === 'asc' ? 1 : -1"
      scrollable
      class="cursor-pointer"
      data-testid="records-table"
      @page="onPage"
      @sort="onSort"
      @row-click="(e) => !query.trashed && router.push({ name: 'records.view', params: { form: formUuid, record: (e.data as RecordPayload).uuid } })"
    >
      <template #empty>{{ query.trashed ? t('records.trash_empty') : t('common.no_results') }}</template>
      <Column field="record_number" :header="t('records.record_number')" sortable>
        <template #body="{ data }">
          <span class="ltr-value font-medium">{{ (data as RecordPayload).system.record_number ?? '—' }}</span>
        </template>
      </Column>
      <Column :header="t('records.record_title')">
        <template #body="{ data }">{{ (data as RecordPayload).title ?? '—' }}</template>
      </Column>
      <Column v-for="f in columns" :key="f.uuid" :field="f.key" :header="columnLabel(f)" :sortable="f.table?.sortable === true">
        <template #body="{ data }">
          <span class="line-clamp-2" dir="auto">{{ cell(data as RecordPayload, f) }}</span>
        </template>
      </Column>
      <Column field="updated_at" :header="t('records.updated_at')" sortable>
        <template #body="{ data }">
          <span class="text-sm whitespace-nowrap">{{ (data as RecordPayload).system.updated_at ? formatDatetime((data as RecordPayload).system.updated_at!, locale) : '—' }}</span>
        </template>
      </Column>
      <Column style="width: 3.5rem">
        <template #body="{ data }">
          <Button
            icon="pi pi-ellipsis-v"
            text
            rounded
            :aria-label="t('common.actions')"
            :data-testid="`record-actions-${(data as RecordPayload).uuid}`"
            @click.stop="(e: Event) => openActions(e, data as RecordPayload)"
          />
        </template>
      </Column>
    </DataTable>
    <Menu ref="actionsMenu" :model="menuItems" popup />
    <ImportDialog v-if="importOpen" :form="formUuid" :form-key="definition.form.key" @close="importOpen = false" @imported="load" />
  </template>
</template>
