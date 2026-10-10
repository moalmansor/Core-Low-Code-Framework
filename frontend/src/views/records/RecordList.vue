<script setup lang="ts">
import AutoComplete from 'primevue/autocomplete'
import Button from 'primevue/button'
import Column from 'primevue/column'
import DataTable, { type DataTablePageEvent, type DataTableRowClickEvent, type DataTableSortEvent } from 'primevue/datatable'
import IconField from 'primevue/iconfield'
import InputIcon from 'primevue/inputicon'
import InputText from 'primevue/inputtext'
import Menu from 'primevue/menu'
import Message from 'primevue/message'
import MultiSelect from 'primevue/multiselect'
import Select from 'primevue/select'
import Tag from 'primevue/tag'
import { useConfirm } from 'primevue/useconfirm'
import { useToast } from 'primevue/usetoast'
import { computed, reactive, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRoute, useRouter } from 'vue-router'
import { ApiError, ensureCsrf, get, http, send } from '@/api/http'
import { downloadBlob, fetchOptions, newUuid, type OptionItem } from '@/runtime/api'
import { fieldType } from '@/runtime/fieldTypes'
import { FormIndex } from '@/runtime/formIndex'
import { formatDatetime, formatLoose, formatValue } from '@/runtime/format'
import { labelOf, pickText } from '@/runtime/i18nText'
import JustificationDialog from '@/runtime/JustificationDialog.vue'
import { useJustification } from '@/runtime/useJustification'
import StatusBadge from '@/runtime/workflow/StatusBadge.vue'
import type { ClientDefinition, ClientField, RecordPayload } from '@/runtime/types'
import { useSession } from '@/stores/session'
import { labelColor, parseHex } from '@/theme/color'
import ImportDialog from './ImportDialog.vue'
import SavedViewDialog from './SavedViewDialog.vue'
import ViewFilters from './ViewFilters.vue'
import { activeConditions, columnValue, decodeConditions, encodeConditions, shownColumns, type ActiveView, type SavedView, type ViewColumn, type ViewCondition } from './viewState'
import { decodeQuery, DEFAULT_STATE, emptyRow, encodeQuery, isActive, operatorsFor, PER_PAGE, toApiFilter, type FilterOp, type FilterRow, type ListState } from './listState'

/**
 * Records table of a published form or collection (design system: data
 * tables): a filter card with typed filters, search, page size, Reset and
 * Apply; the record count; selection with bulk actions; a per-row menu;
 * choice and yes/no values as pills; linked records as links. The list's
 * state lives in the URL.
 *
 * When the form has table views (specification §4.14), the view the user may
 * use decides the columns (including linked forms' fields and the status),
 * the filters, the totals row and the row actions; the user can switch views,
 * choose columns, and save, share and reopen their own saved views. Bulk
 * delete and restore ask for one justification for the whole selection.
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
const state = reactive<ListState>({ ...DEFAULT_STATE, filters: [] })
const filtersOpen = ref(true)
const importOpen = ref(false)
const selected = ref<RecordPayload[]>([])
const justification = useJustification()

// Table views
type Row = RecordPayload & { linked?: Record<string, unknown> }
const activeView = ref<ActiveView | null>(null)
const views = ref<{ uuid: string; key: string; name: Record<string, string> }[]>([])
const viewUuid = ref<string | null>(null)
const conditions = ref<Record<string, ViewCondition>>({})
const chosenColumns = ref<string[] | null>(null)
const totals = ref<Record<string, unknown>>({})
const savedViews = ref<SavedView[]>([])
const currentSaved = ref<SavedView | null>(null)
const savingView = ref(false)
const viewOptions = computed(() => views.value.map((v) => ({ value: v.uuid, label: labelOf(v.name, locale.value, v.key) })))
const viewColumns = computed(() => (activeView.value ? shownColumns(activeView.value, chosenColumns.value) : []))
const rowOptions = computed(() => activeView.value?.row_options ?? { view: true, edit: true, log: true })

const index = computed(() => (definition.value ? new FormIndex(definition.value) : null))
const title = computed(() =>
  definition.value ? (pickText(definition.value.names, locale.value) ?? definition.value.name ?? labelOf(definition.value.form.i18n.name, locale.value, definition.value.form.key)) : '',
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
const columnLabel = (f: ClientField) => pickText(f.i18n.columnLabel, locale.value) ?? labelOf(f.i18n.label, locale.value, f.key)
const fieldByKey = (key: string) => filterable.value.find((f) => f.key === key) ?? null
// Number and title columns only when the form produces them.
const showNumber = computed(() => rows.value.some((r) => r.system.record_number))
const showTitle = computed(() => rows.value.some((r) => r.title))

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

function params(): Record<string, unknown> {
  return {
    search: state.search.trim() || undefined,
    sort: state.sort,
    direction: state.direction,
    trashed: state.trashed ? 1 : undefined,
    filter: activeView.value ? undefined : toApiFilter(state.filters),
    view: viewUuid.value ?? undefined,
    vf: activeView.value ? activeConditions(conditions.value) : undefined,
  }
}

let seq = 0
async function load(): Promise<void> {
  if (!definition.value) return
  const mine = ++seq
  loading.value = true
  try {
    const res = await get<{
      data: Row[]
      meta: { total: number; per_page: number; view: ActiveView | null; totals: Record<string, unknown>; views: { uuid: string; key: string; name: Record<string, string> }[] }
    }>(`/r/${formUuid.value}`, { ...params(), page: state.page, per_page: state.perPage })
    if (mine !== seq) return
    rows.value = res.data
    total.value = res.meta.total
    views.value = res.meta.views ?? []
    const firstView = activeView.value === null && res.meta.view !== null
    activeView.value = res.meta.view ?? null
    if (activeView.value) viewUuid.value = activeView.value.uuid
    totals.value = res.meta.totals ?? {}
    if (firstView && !route.query.per_page && res.meta.per_page !== state.perPage) state.perPage = res.meta.per_page
  } catch (e) {
    if (mine === seq && e instanceof ApiError && e.status < 500) toast.add({ severity: 'error', summary: e.message, life: 6000 })
  } finally {
    if (mine === seq) loading.value = false
  }
}

/** Puts the state in the URL (bookmarkable) and reloads. */
function commit(resetPage = true): void {
  if (resetPage) state.page = 1
  void router.replace({ query: { ...encodeQuery(state), view: viewUuid.value ?? undefined, vf: activeView.value ? encodeConditions(conditions.value) : undefined } })
  void load()
}

watch(
  formUuid,
  async () => {
    selected.value = []
    activeView.value = null
    viewUuid.value = typeof route.query.view === 'string' ? route.query.view : null
    conditions.value = decodeConditions(route.query.vf)
    chosenColumns.value = null
    currentSaved.value = null
    savedViews.value = []
    await loadDefinition()
    Object.assign(
      state,
      decodeQuery(
        route.query,
        filterable.value.map((f) => f.key),
      ),
    )
    filtersOpen.value = true
    const fromUrl = !!route.query.view || !!route.query.vf
    await load()
    await loadSavedViews()
    // The user's default saved view opens unless the URL already says what to show.
    const preferred = fromUrl ? null : savedViews.value.find((v) => v.mine && v.is_default)
    if (preferred) applySaved(preferred)
  },
  { immediate: true },
)

function onPage(e: DataTablePageEvent): void {
  state.page = e.page + 1
  commit(false)
}
function onSort(e: DataTableSortEvent): void {
  state.sort = typeof e.sortField === 'string' ? e.sortField : activeView.value ? '' : 'updated_at'
  state.direction = e.sortOrder === 1 ? 'asc' : 'desc'
  commit()
}
let searchTimer: number | undefined
function onSearch(): void {
  window.clearTimeout(searchTimer)
  searchTimer = window.setTimeout(() => commit(), 350)
}
function setPerPage(n: number): void {
  state.perPage = n
  commit()
}
function reset(): void {
  state.filters = []
  state.search = ''
  conditions.value = {}
  chosenColumns.value = null
  currentSaved.value = null
  commit()
}

// ---------------------------------------------------------------- views and saved views

function switchView(uuid: string): void {
  viewUuid.value = uuid
  activeView.value = null
  conditions.value = {}
  chosenColumns.value = null
  currentSaved.value = null
  state.sort = ''
  commit()
}
async function loadSavedViews(): Promise<void> {
  if (!activeView.value && !views.value.length) return
  try {
    savedViews.value = (await get<{ data: SavedView[] }>(`/r/${formUuid.value}/saved-views`)).data
  } catch {
    savedViews.value = []
  }
}
function applySaved(sv: SavedView): void {
  currentSaved.value = sv
  viewUuid.value = sv.view
  activeView.value = null
  conditions.value = { ...(sv.state.filters ?? {}) }
  chosenColumns.value = sv.state.columns?.length ? [...sv.state.columns] : null
  state.sort = sv.state.sort?.key ?? ''
  state.direction = sv.state.sort?.dir ?? 'asc'
  if (sv.state.page_size) state.perPage = sv.state.page_size
  state.search = sv.state.search ?? ''
  commit()
}
const savedState = computed(() => ({
  columns: chosenColumns.value ?? undefined,
  filters: activeConditions(conditions.value),
  sort: state.sort ? { key: state.sort, dir: state.direction } : null,
  page_size: state.perPage,
  search: state.search.trim() || null,
}))
async function onSavedView(): Promise<void> {
  savingView.value = false
  await loadSavedViews()
}
const savedMenu = ref<InstanceType<typeof Menu> | null>(null)
const savedItems = computed(() => [
  ...savedViews.value.map((sv) => ({
    label: sv.mine ? sv.name : `${sv.name} · ${sv.owner ?? ''}`,
    icon: sv.is_default ? 'pi pi-star-fill' : sv.is_shared ? 'pi pi-users' : 'pi pi-bookmark',
    command: () => applySaved(sv),
  })),
  { separator: true, visible: savedViews.value.length > 0 },
  { label: t('saved_views.save_current'), icon: 'pi pi-save', command: () => (savingView.value = true) },
])
const totalFor = (c: ViewColumn) => (c.key in totals.value ? formatLoose(totals.value[c.key]) || '—' : '')
const hasTotals = computed(() => !!activeView.value?.show_totals && Object.keys(totals.value).length > 0)
const columnChoices = computed(() => (activeView.value?.columns ?? []).map((c) => ({ value: c.key, label: c.label })))
function setColumns(keys: string[]): void {
  chosenColumns.value = keys
}
function toggleTrash(): void {
  state.trashed = !state.trashed
  selected.value = []
  commit()
}

// ---------------------------------------------------------------- filters

const activeCount = computed(() => state.filters.filter(isActive).length)
const kindOf = (f: ClientField) => fieldType(f.type)?.filter ?? 'text'
const isStaticChoice = (f: ClientField) => (f.options?.source ?? 'static') === 'static' && !index.value?.isReference(f)
const addable = computed(() => filterable.value.filter((f) => !state.filters.some((r) => r.key === f.key)).map((f) => ({ value: f.key, label: columnLabel(f) })))
function addFilter(key: string | null): void {
  const f = key ? fieldByKey(key) : null
  if (!f) return
  state.filters.push(emptyRow(f.key, operatorsFor(kindOf(f), isStaticChoice(f))[0]!))
}
function removeFilter(i: number): void {
  const wasActive = isActive(state.filters[i]!)
  state.filters.splice(i, 1)
  if (wasActive) commit()
}
const operatorOptions = (r: FilterRow) => {
  const f = fieldByKey(r.key)
  return f ? operatorsFor(kindOf(f), isStaticChoice(f)).map((op) => ({ value: op, label: t(`records.op.${op}`) })) : []
}
function setOperator(r: FilterRow, op: FilterOp): void {
  const carry = op === 'in' && r.value ? { values: [r.value] } : op === 'equals' && r.values[0] ? { value: r.values[0] } : {}
  Object.assign(r, emptyRow(r.key, op), carry)
}
const staticOptions = (f: ClientField) => (f.options?.static ?? []).filter((o) => o.active !== false).map((o) => ({ value: o.value, label: pickText(o.i18n?.label, locale.value) ?? o.value }))
const booleanOptions = computed(() => [
  { value: 'true', label: t('runtime.yes') },
  { value: 'false', label: t('runtime.no') },
])
const remoteOptions = reactive<Record<string, OptionItem[]>>({})
async function completeFilter(f: ClientField, q: string): Promise<void> {
  try {
    remoteOptions[f.key] = (await fetchOptions(formUuid.value, f.key, { q })).items
  } catch {
    remoteOptions[f.key] = []
  }
}
function pickRecord(r: FilterRow, v: unknown): void {
  const o = v && typeof v === 'object' ? (v as OptionItem) : null
  r.value = o ? String(o.value) : null
  r.label = o ? o.label : null
}
function inputType(f: ClientField): string {
  const kind = kindOf(f)
  return kind === 'date' || kind === 'datetime' ? 'date' : kind === 'time' ? 'time' : 'text'
}
function shownRange(f: ClientField, v: string | null): string {
  const type = inputType(f)
  return (v ?? '').slice(0, type === 'date' ? 10 : type === 'time' ? 5 : undefined)
}
/** Range bound as the server compares it: whole days for date-times, seconds for times. */
function rangeValue(f: ClientField, raw: string, end: boolean): string | null {
  if (!raw) return null
  const kind = kindOf(f)
  if (kind === 'datetime') return `${raw} ${end ? '23:59:59' : '00:00:00'}`
  if (kind === 'time') return raw.length === 5 ? `${raw}:${end ? '59' : '00'}` : raw
  if (kind === 'number') return raw.replace(/[^\d.-]/g, '') || null
  return raw
}

// ---------------------------------------------------------------- cells

const number = (n: number) => new Intl.NumberFormat(locale.value).format(n)
const range = computed(() => {
  if (total.value === 0) return t('records.count_none')
  const from = (state.page - 1) * state.perPage + 1
  return t('records.count', { from: number(from), to: number(Math.min(total.value, from + rows.value.length - 1)), total: number(total.value) })
})

type Pill = { text: string; class?: string; style?: Record<string, string> }
type Link = { text: string; to: { name: string; params: Record<string, string> } }
type Cell = { kind: 'pills'; pills: Pill[] } | { kind: 'links'; links: Link[] } | { kind: 'text'; text: string }

function optionPill(f: ClientField, value: string): Pill {
  const o = (f.options?.static ?? []).find((x) => x.value === value)
  const text = pickText(o?.i18n?.label, locale.value) ?? value
  // The option's colour comes from the builder; the label colour is chosen to stay readable on it.
  const bg = o?.color && parseHex(o.color) ? o.color : null
  return bg ? { text, style: { background: bg, color: labelColor(bg) } } : { text, class: 'pill-neutral' }
}

function cell(r: RecordPayload, f: ClientField): Cell {
  const value = r.values[f.key]
  const empty = value === null || value === undefined || value === '' || (Array.isArray(value) && value.length === 0)
  if (empty) return { kind: 'text', text: '—' }
  const storage = index.value?.storage(f)
  const relation = index.value?.relationOf(f)
  if (index.value?.isReference(f) && relation?.kind === 'reference' && storage === 'lookup') {
    const ids = (Array.isArray(value) ? value : [value]).map(String)
    return {
      kind: 'links',
      links: ids.map((id) => ({ text: r.references?.[f.key]?.[id] ?? t('runtime.untitled_record'), to: { name: 'records.view', params: { form: relation.target, record: id } } })),
    }
  }
  if ((storage === 'bool' || storage === 'consent') && typeof value === 'boolean') {
    return { kind: 'pills', pills: [{ text: value ? t('runtime.yes') : t('runtime.no'), class: value ? 'pill-yes' : 'pill-no' }] }
  }
  if ((storage === 'choice' || storage === 'multi_choice') && !index.value?.isReference(f)) {
    return { kind: 'pills', pills: (Array.isArray(value) ? value : [value]).map((v) => optionPill(f, String(v))) }
  }
  return {
    kind: 'text',
    text:
      formatValue(index.value, f, value, { locale: locale.value, references: r.references, files: r.files, yes: t('runtime.yes'), no: t('runtime.no'), untitled: t('runtime.untitled_record') }) || '—',
  }
}

/** A view column's cell: own fields keep their typed rendering; linked and system paths show their value. */
function viewCell(r: Row, c: ViewColumn): Cell | { kind: 'status'; status: NonNullable<RecordPayload['system']['status']> } {
  if (c.key === '@status') return r.system.status ? { kind: 'status', status: r.system.status } : { kind: 'text', text: '—' }
  if (!c.linked) {
    const f = index.value?.fieldByKey(c.key)
    if (f) return cell(r, f)
  }
  const v = columnValue(r, c)
  const text = v !== null && typeof v === 'object' && 'name' in (v as Record<string, unknown>) ? String((v as { name: unknown }).name) : formatLoose(v)
  return { kind: 'text', text: text || '—' }
}

function onRowClick(e: DataTableRowClickEvent): void {
  // Clicks on the checkbox, the menu or a link do their own thing.
  if ((e.originalEvent.target as HTMLElement | null)?.closest('button, a, input, .p-checkbox')) return
  if (!state.trashed) void router.push({ name: 'records.view', params: { form: formUuid.value, record: (e.data as RecordPayload).uuid } })
}

// ---------------------------------------------------------------- row and bulk actions

const rowMenu = ref<InstanceType<typeof Menu> | null>(null)
const actionsFor = ref<RecordPayload | null>(null)
const rowItems = computed(() => {
  const r = actionsFor.value
  if (!r) return []
  if (state.trashed) return [{ label: t('records.restore'), icon: 'pi pi-replay', visible: canRestore.value, command: () => restore(r) }]
  return [
    { label: t('records.view'), icon: 'pi pi-eye', visible: rowOptions.value.view, command: () => router.push({ name: 'records.view', params: { form: formUuid.value, record: r.uuid } }) },
    {
      label: t('common.edit'),
      icon: 'pi pi-pencil',
      visible: canEdit.value && rowOptions.value.edit,
      command: () => router.push({ name: 'records.edit', params: { form: formUuid.value, record: r.uuid } }),
    },
    { separator: true, visible: canDelete.value },
    { label: t('common.delete'), icon: 'pi pi-trash', class: 'text-danger', visible: canDelete.value, command: () => remove(r) },
  ]
})
function openRowMenu(e: Event, r: RecordPayload): void {
  actionsFor.value = r
  rowMenu.value?.toggle(e)
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
      } catch (e) {
        if (e instanceof ApiError) toast.add({ severity: 'error', summary: e.code === 'conflict' ? t('records.delete_conflict') : e.message, life: 8000 })
      }
      await load()
    },
  })
}
async function deleteRecord(r: RecordPayload): Promise<void> {
  await ensureCsrf()
  const key = newUuid()
  await justification.run((j) => http.delete(`/r/${formUuid.value}/${r.uuid}`, { data: { row_version: r.row_version, ...(j ? { justification: j } : {}) }, headers: { 'Idempotency-Key': key } }))
}
/**
 * Deletes or restores the selection in one request: the server authorises and
 * version-checks every record and asks once for a justification covering all
 * of them; records it refuses are reported, the others go through.
 */
function bulk(operation: 'delete' | 'restore'): void {
  const list = [...selected.value]
  confirm.require({
    message: t(operation === 'delete' ? 'records.delete_selected_confirm' : 'records.restore_selected_confirm', { n: number(list.length) }),
    header: t('common.confirm'),
    acceptProps: { label: operation === 'delete' ? t('common.delete') : t('records.restore'), severity: operation === 'delete' ? 'danger' : 'primary' },
    rejectProps: { label: t('common.cancel'), severity: 'secondary' },
    accept: async () => {
      try {
        const items = list.map((r) => (operation === 'delete' ? { uuid: r.uuid, row_version: r.row_version } : { uuid: r.uuid }))
        const res = await justification.run((j) =>
          send<{ data: { succeeded: number; results: { status: string }[] } }>('post', `/r/${formUuid.value}/bulk-${operation}`, { items, ...(j ? { justification: j } : {}) }),
        )
        if (res === null) return
        const failed = res.data.results.length - res.data.succeeded
        selected.value = []
        toast.add({
          severity: failed ? 'warn' : 'success',
          summary: t(operation === 'delete' ? 'records.deleted_n' : 'records.restored_n', { n: number(res.data.succeeded) }),
          detail: failed ? t(operation === 'delete' ? 'records.not_deleted_n' : 'records.not_restored_n', { n: number(failed) }) : undefined,
          life: 8000,
        })
      } catch (e) {
        if (e instanceof ApiError) toast.add({ severity: 'error', summary: e.message, life: 8000 })
      }
      await load()
    },
  })
}
async function restore(r: RecordPayload): Promise<void> {
  try {
    const done = await justification.run((j) => send('post', `/r/${formUuid.value}/${r.uuid}/restore`, j ? { justification: j } : {}))
    if (done === null) return
    toast.add({ severity: 'success', summary: t('records.restored'), life: 4000 })
    await load()
  } catch (e) {
    if (e instanceof ApiError) toast.add({ severity: 'error', summary: e.message, life: 6000 })
  }
}

const exporting = ref(false)
async function exportAs(format: 'xlsx' | 'csv', onlySelected = false): Promise<void> {
  exporting.value = true
  try {
    const extra = onlySelected ? { uuids: selected.value.map((r) => r.uuid) } : {}
    await downloadBlob(`/r/${formUuid.value}/export`, { ...params(), ...extra, format }, `${definition.value?.form.key ?? 'records'}.${format}`)
  } catch (e) {
    toast.add({ severity: 'error', summary: e instanceof ApiError && e.status === 429 ? t('records.export_throttled') : t('records.export_failed'), life: 6000 })
  } finally {
    exporting.value = false
  }
}

const actionsMenu = ref<InstanceType<typeof Menu> | null>(null)
const actionItems = computed(() => {
  const n = selected.value.length
  return [
    { label: t('records.import'), icon: 'pi pi-upload', visible: canImport.value && !state.trashed, command: () => (importOpen.value = true) },
    { label: t('records.export_xlsx'), icon: 'pi pi-file-excel', visible: canExport.value, command: () => exportAs('xlsx') },
    { label: t('records.export_csv'), icon: 'pi pi-file', visible: canExport.value, command: () => exportAs('csv') },
    { separator: true, visible: n > 0 && (canExport.value || canDelete.value) },
    { label: t('records.export_selected', { n: number(n) }), icon: 'pi pi-file-excel', visible: n > 0 && canExport.value, command: () => exportAs('xlsx', true) },
    { label: t('records.delete_selected', { n: number(n) }), icon: 'pi pi-trash', class: 'text-danger', visible: n > 0 && canDelete.value && !state.trashed, command: () => bulk('delete') },
    { label: t('records.restore_selected', { n: number(n) }), icon: 'pi pi-replay', visible: n > 0 && canRestore.value && state.trashed, command: () => bulk('restore') },
    { separator: true, visible: canRestore.value },
    { label: state.trashed ? t('records.leave_trash') : t('records.show_trash'), icon: state.trashed ? 'pi pi-arrow-left' : 'pi pi-trash', visible: canRestore.value, command: toggleTrash },
  ]
})
const hasActions = computed(() => actionItems.value.some((i) => i.visible !== false && !('separator' in i)))
const perPageOptions = computed(() => PER_PAGE.map((n) => ({ value: n, label: t('records.per_page', { n: number(n) }) })))
</script>

<template>
  <Message v-if="loadError" severity="error" data-testid="records-error">{{ loadError }}</Message>
  <div v-else-if="definition" class="flex flex-col gap-4">
    <header class="flex flex-wrap items-start gap-3">
      <div class="flex items-center gap-3 flex-1 min-w-0">
        <span class="records-icon shrink-0" aria-hidden="true"><i :class="definition.form.icon || 'pi pi-list'" /></span>
        <div class="min-w-0">
          <h1 class="page-title !mb-0 flex items-center gap-2" data-testid="records-title">
            <span class="truncate">{{ title }}</span>
            <Tag v-if="state.trashed" severity="warn" :value="t('records.trash')" />
          </h1>
          <p class="text-sm text-muted-color tabular-nums" data-testid="records-count">{{ range }}</p>
        </div>
      </div>
      <div class="flex flex-wrap items-center gap-2">
        <Select
          v-if="viewOptions.length > 1"
          :model-value="viewUuid"
          :options="viewOptions"
          option-label="label"
          option-value="value"
          size="small"
          :aria-label="t('saved_views.view')"
          data-testid="records-view"
          @update:model-value="switchView"
        />
        <template v-if="activeView">
          <Button
            :label="currentSaved?.name ?? t('saved_views.title')"
            icon="pi pi-bookmark"
            severity="secondary"
            outlined
            aria-haspopup="true"
            data-testid="records-saved-views"
            @click="(e: Event) => savedMenu?.toggle(e)"
          />
          <Menu ref="savedMenu" :model="savedItems" popup />
        </template>
        <RouterLink v-if="canCreate && !state.trashed" v-slot="{ navigate }" :to="{ name: 'records.create', params: { form: formUuid } }" custom>
          <Button icon="pi pi-plus" :label="t('records.new')" data-testid="records-new" @click="navigate" />
        </RouterLink>
        <template v-if="hasActions">
          <Button
            :label="t('common.actions')"
            icon="pi pi-chevron-down"
            icon-pos="right"
            severity="secondary"
            outlined
            :loading="exporting"
            aria-haspopup="true"
            data-testid="records-actions"
            @click="(e: Event) => actionsMenu?.toggle(e)"
          />
          <Menu ref="actionsMenu" :model="actionItems" popup />
        </template>
      </div>
    </header>

    <section class="rounded-xl border border-line bg-card" data-testid="records-filters" :aria-label="t('records.filters')">
      <div class="flex flex-wrap items-center gap-2 px-3 py-2.5">
        <button type="button" class="flex items-center gap-2 font-medium" :aria-expanded="filtersOpen" data-testid="records-filters-toggle" @click="filtersOpen = !filtersOpen">
          <i class="pi pi-filter text-muted-color" aria-hidden="true" />{{ t('records.filters') }}
          <span v-if="activeCount" class="rounded-full px-2 text-xs bg-primary-subtle text-on-primary-subtle" data-testid="records-filter-count">{{ number(activeCount) }}</span>
          <i :class="filtersOpen ? 'pi pi-chevron-up' : 'pi pi-chevron-down'" class="text-xs text-muted-color" aria-hidden="true" />
        </button>
        <IconField v-if="activeView?.global_search !== false" class="ms-2 grow max-w-80">
          <InputIcon class="pi pi-search" />
          <InputText
            v-model="state.search"
            class="w-full"
            size="small"
            :placeholder="t('common.search')"
            :aria-label="t('common.search')"
            data-testid="records-search"
            @input="onSearch"
            @keyup.enter="commit()"
          />
        </IconField>
        <span class="flex-1" />
        <Select
          :model-value="state.perPage"
          :options="perPageOptions"
          option-label="label"
          option-value="value"
          size="small"
          :aria-label="t('records.per_page_label')"
          data-testid="records-per-page"
          @update:model-value="setPerPage"
        />
        <Button :label="t('records.reset')" icon="pi pi-refresh" size="small" severity="secondary" outlined data-testid="records-reset" @click="reset" />
        <Button :label="t('records.apply_refresh')" icon="pi pi-search" size="small" data-testid="records-apply-filters" @click="commit()" />
      </div>
      <div v-if="filtersOpen && activeView" class="border-t border-line px-3 py-3 flex flex-col gap-3">
        <ViewFilters v-if="activeView.filters.length" v-model="conditions" :filters="activeView.filters" :form="formUuid" @apply="commit()" />
        <div v-if="activeView.column_chooser" class="flex flex-wrap items-center gap-2">
          <label for="records-columns" class="text-sm font-medium">{{ t('saved_views.columns') }}</label>
          <MultiSelect
            input-id="records-columns"
            :model-value="viewColumns.map((c) => c.key)"
            :options="columnChoices"
            option-label="label"
            option-value="value"
            size="small"
            :max-selected-labels="3"
            class="w-72 max-w-full"
            data-testid="records-columns"
            @update:model-value="setColumns"
          />
        </div>
      </div>
      <div v-else-if="filtersOpen && filterable.length" class="border-t border-line px-3 py-3 flex flex-col gap-2">
        <div class="flex flex-wrap items-center gap-2">
          <Select
            :model-value="null"
            :options="addable"
            option-label="label"
            option-value="value"
            size="small"
            class="w-64 max-w-full"
            :placeholder="t('records.add_filter')"
            :disabled="addable.length === 0"
            data-testid="records-add-filter"
            @update:model-value="addFilter"
          />
          <span v-if="state.filters.length === 0" class="text-sm text-muted-color">{{ t('records.add_filter_hint') }}</span>
        </div>
        <div v-for="(r, i) in state.filters" :key="r.key" class="filter-row" :data-testid="`records-filter-${r.key}`">
          <span class="text-sm font-medium truncate">{{ fieldByKey(r.key) ? columnLabel(fieldByKey(r.key)!) : r.key }}</span>
          <Select
            :model-value="r.op"
            :options="operatorOptions(r)"
            option-label="label"
            option-value="value"
            size="small"
            :disabled="operatorOptions(r).length < 2"
            :aria-label="t('records.operator')"
            @update:model-value="(op: FilterOp) => setOperator(r, op)"
          />
          <template v-if="fieldByKey(r.key)">
            <div v-if="r.op === 'between'" class="flex gap-2 min-w-0">
              <InputText
                :type="inputType(fieldByKey(r.key)!)"
                :model-value="shownRange(fieldByKey(r.key)!, r.from)"
                size="small"
                class="flex-1 min-w-0 ltr-value"
                :placeholder="t('runtime.range_from')"
                :aria-label="t('runtime.range_from')"
                @update:model-value="(v) => (r.from = rangeValue(fieldByKey(r.key)!, v ?? '', false))"
              />
              <InputText
                :type="inputType(fieldByKey(r.key)!)"
                :model-value="shownRange(fieldByKey(r.key)!, r.to)"
                size="small"
                class="flex-1 min-w-0 ltr-value"
                :placeholder="t('runtime.range_to')"
                :aria-label="t('runtime.range_to')"
                @update:model-value="(v) => (r.to = rangeValue(fieldByKey(r.key)!, v ?? '', true))"
              />
            </div>
            <Select
              v-else-if="r.op === 'is'"
              v-model="r.value"
              :options="booleanOptions"
              option-label="label"
              option-value="value"
              size="small"
              show-clear
              :placeholder="t('records.any')"
              :aria-label="t('records.value')"
            />
            <MultiSelect
              v-else-if="r.op === 'in'"
              v-model="r.values"
              :options="staticOptions(fieldByKey(r.key)!)"
              option-label="label"
              option-value="value"
              size="small"
              filter
              display="chip"
              :placeholder="t('records.any')"
              :aria-label="t('records.value')"
            />
            <Select
              v-else-if="r.op === 'equals' && kindOf(fieldByKey(r.key)!) === 'choice' && isStaticChoice(fieldByKey(r.key)!)"
              v-model="r.value"
              :options="staticOptions(fieldByKey(r.key)!)"
              option-label="label"
              option-value="value"
              size="small"
              show-clear
              filter
              :placeholder="t('records.any')"
              :aria-label="t('records.value')"
            />
            <AutoComplete
              v-else-if="['lookup', 'choice'].includes(kindOf(fieldByKey(r.key)!))"
              :model-value="r.value ? { value: r.value, label: r.label ?? r.value } : null"
              :suggestions="remoteOptions[r.key] ?? []"
              option-label="label"
              dropdown
              force-selection
              size="small"
              :placeholder="t('records.any')"
              :aria-label="t('records.value')"
              @complete="(e: { query: string }) => completeFilter(fieldByKey(r.key)!, e.query)"
              @update:model-value="(v: unknown) => pickRecord(r, v)"
            />
            <InputText v-else v-model="r.value" size="small" :placeholder="t('records.value_placeholder')" :aria-label="t('records.value')" @keyup.enter="commit()" />
          </template>
          <Button icon="pi pi-times" text rounded size="small" severity="secondary" :aria-label="t('records.remove_filter')" @click="removeFilter(i)" />
        </div>
      </div>
    </section>

    <DataTable
      v-model:selection="selected"
      :value="rows"
      lazy
      paginator
      :rows="state.perPage"
      :first="(state.page - 1) * state.perPage"
      :total-records="total"
      :loading="loading"
      data-key="uuid"
      removable-sort
      :sort-field="state.sort"
      :sort-order="state.direction === 'asc' ? 1 : -1"
      scrollable
      class="records-table rounded-xl border border-line overflow-hidden cursor-pointer"
      data-testid="records-table"
      @page="onPage"
      @sort="onSort"
      @row-click="onRowClick"
    >
      <template #empty>{{ state.trashed ? t('records.trash_empty') : t('common.no_results') }}</template>
      <Column selection-mode="multiple" style="width: 2.75rem" />
      <Column style="width: 3rem">
        <template #body="{ data }">
          <Button
            icon="pi pi-ellipsis-v"
            text
            rounded
            size="small"
            severity="secondary"
            :aria-label="t('common.actions')"
            :data-testid="`record-actions-${(data as RecordPayload).uuid}`"
            @click.stop="(e: Event) => openRowMenu(e, data as RecordPayload)"
          />
        </template>
      </Column>
      <template v-if="activeView">
        <Column
          v-for="c in viewColumns"
          :key="c.key"
          :field="c.key"
          :header="c.label"
          :sortable="c.sortable"
          :style="c.width ? { minWidth: `${c.width}px` } : undefined"
          :frozen="c.pinned === 'start'"
        >
          <template #body="{ data }">
            <template v-for="vc in [viewCell(data as Row, c)]" :key="vc.kind">
              <StatusBadge v-if="vc.kind === 'status'" :status="vc.status" size="sm" />
              <span v-else-if="vc.kind === 'pills'" class="flex flex-wrap gap-1">
                <span v-for="(p, n) in vc.pills" :key="n" :class="['pill', p.class]" :style="p.style" data-testid="cell-pill">{{ p.text }}</span>
              </span>
              <span v-else-if="vc.kind === 'links'" class="flex flex-wrap gap-x-3 gap-y-1">
                <RouterLink v-for="(l, n) in vc.links" :key="n" :to="l.to" class="record-link" data-testid="cell-link" @click.stop>
                  <span dir="auto">{{ l.text }}</span
                  ><i class="pi pi-external-link" aria-hidden="true" />
                </RouterLink>
              </span>
              <span v-else :class="/\s/.test(vc.text) ? 'line-clamp-2' : 'whitespace-nowrap'" dir="auto">{{ vc.text }}</span>
            </template>
          </template>
          <template v-if="hasTotals" #footer>
            <span class="font-semibold tabular-nums" :data-testid="`total-${c.key}`">{{ totalFor(c) }}</span>
          </template>
        </Column>
      </template>
      <template v-else>
        <Column v-if="showNumber" field="record_number" :header="t('records.record_number')" sortable>
          <template #body="{ data }">
            <span class="ltr-value font-mono text-sm">{{ (data as RecordPayload).system.record_number ?? '—' }}</span>
          </template>
        </Column>
        <Column v-if="showTitle" :header="t('records.record_title')">
          <template #body="{ data }">{{ (data as RecordPayload).title ?? '—' }}</template>
        </Column>
        <Column v-for="f in columns" :key="f.uuid" :field="f.key" :header="columnLabel(f)" :sortable="f.table?.sortable === true">
          <template #body="{ data }">
            <template v-for="c in [cell(data as RecordPayload, f)]" :key="c.kind">
              <span v-if="c.kind === 'pills'" class="flex flex-wrap gap-1">
                <span v-for="(p, n) in c.pills" :key="n" :class="['pill', p.class]" :style="p.style" data-testid="cell-pill">{{ p.text }}</span>
              </span>
              <span v-else-if="c.kind === 'links'" class="flex flex-wrap gap-x-3 gap-y-1">
                <RouterLink v-for="(l, n) in c.links" :key="n" :to="l.to" class="record-link" data-testid="cell-link" @click.stop>
                  <span dir="auto">{{ l.text }}</span
                  ><i class="pi pi-external-link" aria-hidden="true" />
                </RouterLink>
              </span>
              <!-- A single token (a code, a number) never breaks; longer text wraps to two lines. -->
              <span v-else :class="/\s/.test(c.text) ? 'line-clamp-2' : 'whitespace-nowrap'">{{ c.text }}</span>
            </template>
          </template>
        </Column>
        <Column field="updated_at" :header="t('records.updated_at')" sortable>
          <template #body="{ data }">
            <span class="text-sm whitespace-nowrap text-muted-color">{{ (data as RecordPayload).system.updated_at ? formatDatetime((data as RecordPayload).system.updated_at!, locale) : '—' }}</span>
          </template>
        </Column>
      </template>
    </DataTable>
    <Menu ref="rowMenu" :model="rowItems" popup />
    <SavedViewDialog v-if="savingView && activeView" :form="formUuid" :view="activeView.uuid" :state="savedState" :current="currentSaved" @saved="onSavedView" @close="savingView = false" />
    <JustificationDialog :prompt="justification.prompt.value" :errors="justification.errors.value" :busy="justification.busy.value" @submit="justification.submit" @cancel="justification.cancel" />
    <ImportDialog v-if="importOpen" :form="formUuid" :form-key="definition.form.key" @close="importOpen = false" @imported="load" />
  </div>
</template>

<style scoped>
.records-icon {
  display: inline-grid;
  place-items: center;
  width: 2.5rem;
  height: 2.5rem;
  border-radius: var(--radius-card);
  background: var(--primary);
  color: var(--on-primary);
  font-size: 1.125rem;
}
.filter-row {
  display: grid;
  grid-template-columns: minmax(7rem, 10rem) minmax(8rem, 11rem) minmax(0, 1fr) auto;
  gap: 0.5rem;
  align-items: center;
}
@media (max-width: 640px) {
  .filter-row {
    grid-template-columns: minmax(0, 1fr) minmax(0, 1fr) auto;
  }
  .filter-row > :first-child {
    grid-column: 1 / -1;
  }
}
.record-link {
  display: inline-flex;
  align-items: center;
  gap: 0.25rem;
  color: var(--link);
  text-decoration: none;
}
.record-link:hover {
  text-decoration: underline;
}
.record-link .pi {
  font-size: 0.6875rem;
}
</style>
