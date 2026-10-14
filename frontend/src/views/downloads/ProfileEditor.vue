<script setup lang="ts">
import Button from 'primevue/button'
import Checkbox from 'primevue/checkbox'
import InputNumber from 'primevue/inputnumber'
import InputText from 'primevue/inputtext'
import Message from 'primevue/message'
import Select from 'primevue/select'
import SelectButton from 'primevue/selectbutton'
import Tab from 'primevue/tab'
import TabList from 'primevue/tablist'
import Tabs from 'primevue/tabs'
import { useToast } from 'primevue/usetoast'
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { onBeforeRouteLeave, useRoute, useRouter } from 'vue-router'
import { ApiError, get } from '@/api/http'
import ExpressionInput from '@/builder/conditions/ExpressionInput.vue'
import { buildScope } from '@/builder/conditions/scope'
import I18nInput from '@/builder/I18nInput.vue'
import { createBuilder, provideBuilder } from '@/builder/useBuilder'
import ConfigField from '@/components/config/ConfigField.vue'
import ConfigItem from '@/components/config/ConfigItem.vue'
import ConfigSaveBar from '@/components/config/ConfigSaveBar.vue'
import ConfigSection from '@/components/config/ConfigSection.vue'
import SettingSwitch from '@/components/config/SettingSwitch.vue'
import { provideConfigScreen } from '@/components/config/configScreen'
import { newUuid } from '@/runtime/api'
import { labelOf } from '@/runtime/i18nText'
import { useSession } from '@/stores/session'
import { errorText } from '@/views/admin/building/shared'
import ErrorList from '@/views/admin/formconfig/ErrorList.vue'
import { joinList } from '@/runtime/lists'
import {
  downloadsApi,
  pathKey,
  type Aggregate,
  type ColumnDoc,
  type ColumnInfo,
  type DlFormat,
  type FilterDoc,
  type FormatType,
  type Operator,
  type ParameterDoc,
  type ParamType,
  type PreviewResult,
  type ProfileDoc,
  type ToMany,
  type TreeNode,
} from './api'
import RelationTree from './RelationTree.vue'
import ScheduleList from './ScheduleList.vue'

/**
 * The download profile builder (specification §4.23): pick columns from the
 * relation tree (linked forms at any depth, both directions), decide how
 * paths that reach several records appear, add system, static and
 * calculated columns, fixed filters and parameters, choose the formats and
 * their options, and see the first rows as you will receive them. Shared
 * profiles are built by profile managers (who can also preview the columns
 * a role receives and schedule them); a personal profile is built by its
 * owner from what they can see.
 */
const route = useRoute()
const router = useRouter()
const { t, locale } = useI18n()
const toast = useToast()
const session = useSession()
provideConfigScreen()

const form = String(route.params.form)
const personal = route.meta.personalDownloads === true
const uuid = ref(String(route.params.profile))
const isNew = computed(() => uuid.value === 'new')
const doc = ref<ProfileDoc | null>(null)
const info = ref<Record<string, ColumnInfo>>({})
const hash = ref('')
const saved = ref('')
const saving = ref(false)
const errors = ref<Record<string, string>>({})
const dirty = computed(() => doc.value !== null && JSON.stringify(doc.value) !== saved.value)
const dl = computed(() => session.boot?.default_locale ?? 'en')
const err = (path: string) => errors.value[path]

// Formulas need the form's fields; the shared builder loads them, a personal builder has none.
const builder = personal ? null : createBuilder(form)
if (builder) provideBuilder(builder)
const scope = computed(() => (builder?.doc ? buildScope(builder.doc, builder.catalog.fields) : null))

function empty(): ProfileDoc {
  return {
    key: '',
    i18n: { name: {}, description: {} },
    formats: ['xlsx'],
    fileName: '{form} {date}',
    xlsx: { styledHeader: true, freezeHeader: true, titleRow: false, logo: false },
    csv: { delimiter: ',', bom: true },
    pdf: { orientation: 'landscape', pageNumbers: true, header: {}, footer: {} },
    applyUserFilters: true,
    allowSelected: true,
    availableIn: ['table_toolbar'],
    parameters: [],
    maxRows: 0,
    active: true,
    columns: [],
    filters: [],
  }
}
function apply(p: ProfileDoc, h: string, labels: Record<string, ColumnInfo>): void {
  doc.value = JSON.parse(JSON.stringify(p)) as ProfileDoc
  for (const k of ['header', 'footer'] as const) doc.value.pdf[k] = { ...(doc.value.pdf[k] ?? {}) }
  // Profiles saved before an option existed get its default.
  doc.value.xlsx = Object.assign({ styledHeader: true, freezeHeader: true, titleRow: false, logo: false }, doc.value.xlsx)
  info.value = { ...labels }
  hash.value = h
  saved.value = JSON.stringify(doc.value)
}
async function load(): Promise<void> {
  try {
    if (isNew.value) {
      apply(empty(), '', {})
      saved.value = ''
    } else {
      const r = await downloadsApi.load(uuid.value)
      apply(r.profile, r.hash, r.columns)
    }
  } catch (e) {
    toast.add({ severity: 'error', summary: errorText(e, t('dl.load_failed')), life: 6000 })
  }
}
onMounted(async () => {
  await load()
  if (builder) await builder.load().catch(() => undefined)
  if (!personal) roles.value = (await get<{ data: { uuid: string; name: string }[] }>('/roles').catch(() => ({ data: [] }))).data
})
onBeforeUnmount(() => builder?.cancelTimers())
onBeforeRouteLeave(() => !dirty.value || window.confirm(t('records.leave_unsaved')))

// The tree: what has been opened so far, for full path labels and filter choices.
const nodes = ref<Record<string, TreeNode>>({})
function loaded(_prefix: string[], list: TreeNode[]): void {
  const next = { ...nodes.value }
  for (const n of list) next[n.kind + pathKey(n.path)] = n
  nodes.value = next
}
const selected = computed(() => new Set((doc.value?.columns ?? []).filter((c) => c.path).map((c) => pathKey(c.path))))
function fullLabel(path: string[], last: string): string {
  const parts: string[] = []
  for (let i = 1; i < path.length; i++) {
    const prefix = pathKey(path.slice(0, i))
    const hop = Object.values(nodes.value).find((n) => (n.kind === 'link' || n.kind === 'reverse' || n.kind === 'repeater') && pathKey(n.path) === prefix)
    parts.push(hop?.label ?? '…')
  }
  return [...parts, last].join(' › ')
}
function toggle(n: TreeNode, on: boolean): void {
  if (!doc.value) return
  if (!on) {
    doc.value.columns = doc.value.columns.filter((c) => pathKey(c.path) !== pathKey(n.path))
    return
  }
  const c: ColumnDoc = {
    uuid: newUuid(),
    kind: n.kind === 'system' ? 'system' : 'field',
    path: [...n.path],
    expression: null,
    staticValue: null,
    toMany: n.toMany ? 'aggregate' : null,
    aggregate: n.toMany ? 'join' : null,
    separator: n.toMany ? ', ' : null,
    sheet: null,
    width: null,
    format: null,
    i18n: { header: {} },
  }
  info.value = { ...info.value, [c.uuid]: { label: fullLabel(n.path, n.label), many: n.toMany, broken: false } }
  doc.value.columns.push(c)
}
function addColumn(kind: 'static' | 'calculated'): void {
  doc.value?.columns.push({
    uuid: newUuid(),
    kind,
    path: null,
    expression: null,
    staticValue: kind === 'static' ? '' : null,
    toMany: null,
    aggregate: null,
    separator: null,
    sheet: null,
    width: null,
    format: null,
    i18n: { header: {} },
  })
}
function move<T>(list: T[], i: number, d: -1 | 1): void {
  const j = i + d
  if (j < 0 || j >= list.length) return
  ;[list[i], list[j]] = [list[j]!, list[i]!]
}
const columnTitle = (c: ColumnDoc) =>
  c.i18n.header[locale.value] ||
  c.i18n.header[dl.value] ||
  (c.kind === 'static' ? t('dl.kind.static') : c.kind === 'calculated' ? t('dl.kind.calculated') : (info.value[c.uuid]?.label ?? t('dl.column')))
const columnSubtitle = (c: ColumnDoc) => (c.path ? (info.value[c.uuid]?.label ?? '') : t(`dl.kind.${c.kind}`))
const toManyModes = computed(() => (['flatten', 'aggregate', 'separate_sheet'] as ToMany[]).map((v) => ({ value: v, label: t(`dl.to_many.${v}`) })))
const aggregates = computed(() => (['join', 'count', 'sum', 'avg', 'min', 'max', 'first', 'last'] as Aggregate[]).map((v) => ({ value: v, label: t(`dl.agg.${v}`) })))
const formatTypes = computed(() => [
  { value: null, label: t('dl.fmt.none') },
  ...(['text', 'date', 'datetime', 'number', 'currency', 'percent'] as FormatType[]).map((v) => ({ value: v as FormatType | null, label: t(`dl.fmt.${v}`) })),
])
function setFormat(c: ColumnDoc, type: FormatType | null): void {
  c.format = type === null ? null : { type, decimals: c.format?.decimals ?? null, currency: c.format?.currency ?? (type === 'currency' ? 'SAR' : null), digits: c.format?.digits ?? 'latin' }
}
const digitOptions = computed(() => [
  { value: 'latin', label: '123' },
  { value: 'arabic', label: '١٢٣' },
])

// Filters: fields of this form and of forms it links to (opened in the tree).
const filterFields = computed(() =>
  Object.values(nodes.value)
    .filter((n) => n.kind === 'field' && n.path.every((s, i) => i === n.path.length - 1 || (!s.startsWith('~') && !s.startsWith('#'))))
    .map((n) => ({ value: pathKey(n.path), label: fullLabel(n.path, n.label) })),
)
const operators = computed(() =>
  (['eq', 'neq', 'gt', 'gte', 'lt', 'lte', 'contains', 'starts_with', 'in', 'between', 'empty', 'not_empty'] as Operator[]).map((v) => ({ value: v, label: t(`dl.op.${v}`) })),
)
type FilterMode = 'value' | 'parameter' | 'expression'
const filterMode = (f: FilterDoc): FilterMode => (f.parameter ? 'parameter' : f.valueExpression ? 'expression' : 'value')
const filterModes = computed(() => (['value', 'parameter', ...(scope.value ? ['expression'] : [])] as FilterMode[]).map((v) => ({ value: v, label: t(`dl.filter_mode.${v}`) })))
function setFilterMode(f: FilterDoc, m: FilterMode): void {
  f.parameter = m === 'parameter' ? (doc.value?.parameters[0]?.key ?? '') : null
  f.valueExpression = m === 'expression' ? { k: 'lit', t: 'text', v: '' } : null
  f.value = m === 'value' ? '' : null
}
function addFilter(): void {
  const first = filterFields.value[0]
  doc.value?.filters.push({ uuid: newUuid(), path: first ? (JSON.parse(first.value) as string[]) : [], operator: 'eq', value: '', valueExpression: null, parameter: null })
}
// The values of an "is one of" filter are typed into one box (machine-list), comma separated.
const textValue = (f: FilterDoc) => (Array.isArray(f.value) ? (f.value as unknown[]).join(', ') /* machine-list */ : String(f.value ?? ''))
function setTextValue(f: FilterDoc, v: string): void {
  f.value =
    f.operator === 'in'
      ? v
          .split(',')
          .map((s) => s.trim())
          .filter(Boolean)
      : v
}
const between = (f: FilterDoc, i: 0 | 1) => (Array.isArray(f.value) ? String((f.value as unknown[])[i] ?? '') : '')
function setBetween(f: FilterDoc, i: 0 | 1, v: string): void {
  const now = Array.isArray(f.value) ? [...(f.value as unknown[])] : ['', '']
  now[i] = v
  f.value = now
}

// Parameters.
const paramTypes = computed(() => (['text', 'number', 'date', 'date_range', 'choice', 'user', 'department'] as ParamType[]).map((v) => ({ value: v, label: t(`dl.param_type.${v}`) })))
function addParameter(): void {
  const n = (doc.value?.parameters.length ?? 0) + 1
  doc.value?.parameters.push({ key: `param_${n}`, type: 'text', required: false, default: null, options: [], i18n: { label: {} } })
}
const paramLabel = (p: ParameterDoc) => labelOf(p.i18n.label, locale.value, p.key)
const parameterOptions = computed(() => (doc.value?.parameters ?? []).map((p: ParameterDoc) => ({ value: p.key, label: paramLabel(p) })))

// Output.
function toggleFormat(f: DlFormat, on: boolean): void {
  if (doc.value) doc.value.formats = on ? [...new Set([...doc.value.formats, f])] : doc.value.formats.filter((x) => x !== f)
}
function togglePlace(p: 'table_toolbar' | 'record_view', on: boolean): void {
  if (doc.value) doc.value.availableIn = on ? [...new Set([...doc.value.availableIn, p])] : doc.value.availableIn.filter((x) => x !== p)
}
const delimiters = computed(() => [
  { value: ',', label: t('dl.delim.comma') },
  { value: ';', label: t('dl.delim.semicolon') },
  { value: '\t', label: t('dl.delim.tab') },
])
const orientations = computed(() => [
  { value: 'portrait', label: t('doc.portrait') },
  { value: 'landscape', label: t('doc.landscape') },
])
const hasSheets = computed(() => (doc.value?.columns ?? []).some((c) => c.toMany === 'separate_sheet'))
const token = (s: string) => `{${s}}`

// Live preview of the first rows, as the person building it would receive them.
const preview = ref<PreviewResult | null>(null)
const previewError = ref('')
const previewParams = ref<Record<string, unknown>>({})
const previewSheet = ref('')
let timer: ReturnType<typeof setTimeout> | null = null
watch(
  [doc, previewParams],
  () => {
    if (timer) clearTimeout(timer)
    timer = setTimeout(renderPreview, 700)
  },
  { deep: true },
)
onBeforeUnmount(() => timer && clearTimeout(timer))
async function renderPreview(): Promise<void> {
  if (!doc.value || !doc.value.columns.length) {
    preview.value = null
    return
  }
  try {
    preview.value = await downloadsApi.preview(form, { ...doc.value, key: doc.value.key || 'preview' }, personal, previewParams.value)
    previewError.value = ''
    if (!(previewSheet.value in preview.value.headers)) previewSheet.value = ''
  } catch (e) {
    previewError.value = e instanceof ApiError && e.status === 422 ? (Object.values(e.fieldErrors)[0] ?? t('dl.preview_invalid')) : errorText(e, t('dl.preview_failed'))
  }
}
const sheetTabs = computed(() => (preview.value ? [{ key: '', label: t('dl.main_sheet') }, ...Object.entries(preview.value.sheets).map(([key, label]) => ({ key, label }))] : []))
const cellText = (v: unknown) => (v === null || v === undefined ? '' : typeof v === 'boolean' ? (v ? '✓' : '') : String(v))

// The columns a role receives (shared profiles).
const roles = ref<{ uuid: string; name: string }[]>([])
const role = ref<string | null>(null)
const effective = ref<{ columns: string[]; omitted: string[] } | null>(null)
watch(role, async (r) => {
  effective.value = null
  if (!r || !doc.value) return
  try {
    effective.value = await downloadsApi.effective(form, { ...doc.value, key: doc.value.key || 'preview' }, r)
  } catch (e) {
    toast.add({ severity: 'error', summary: errorText(e, t('dl.preview_failed')), life: 6000 })
  }
})

async function save(): Promise<void> {
  if (!doc.value) return
  saving.value = true
  errors.value = {}
  try {
    const r = isNew.value ? await downloadsApi.create(form, doc.value, personal) : await downloadsApi.save(uuid.value, doc.value, hash.value)
    apply(r.profile, r.hash, r.columns)
    toast.add({ severity: 'success', summary: t('workflow.saved'), life: 3000 })
    if (isNew.value && r.profile.uuid) {
      uuid.value = r.profile.uuid
      await router.replace({ name: personal ? 'records.download_profile' : 'admin.downloads.profile', params: { form, profile: r.profile.uuid } })
    }
  } catch (e) {
    if (e instanceof ApiError && e.status === 409) {
      toast.add({ severity: 'warn', summary: t('workflow.conflict'), life: 8000 })
      await load()
    } else {
      errors.value = e instanceof ApiError ? e.fieldErrors : {}
      toast.add({ severity: 'error', summary: errorText(e, t('workflow.save_failed')), life: 6000 })
    }
  } finally {
    saving.value = false
  }
}
function discard(): void {
  if (saved.value) doc.value = JSON.parse(saved.value) as ProfileDoc
  errors.value = {}
}
const back = computed(() => (personal ? { name: 'records.downloads', params: { form } } : { name: 'admin.forms.configure', params: { form, tab: 'downloads' } }))
</script>

<template>
  <div class="flex flex-col gap-6" data-testid="download-editor">
    <header class="flex flex-wrap items-start gap-3">
      <div class="flex-1 min-w-0">
        <RouterLink :to="back" class="text-sm text-primary inline-flex items-center gap-1 mb-1">
          <i class="pi pi-arrow-left rtl:rotate-180" aria-hidden="true" />{{ personal ? t('dl.my_profiles') : t('formconfig.tab.downloads') }}
        </RouterLink>
        <h1 class="page-title !mb-1">{{ isNew ? t('dl.new') : t('dl.edit') }}</h1>
        <p class="m-0 text-sm text-muted-color">{{ personal ? t('dl.subtitle_personal') : t('dl.subtitle') }}</p>
      </div>
    </header>

    <div v-if="doc" class="dl-grid">
      <div class="flex flex-col gap-6 min-w-0">
        <ConfigSection id="dl-basics" :title="t('dl.section.basics')">
          <I18nInput v-model="doc.i18n.name" class="w-field-md" :label="t('dl.name')" :maxlength="255" :invalid="!!err(`i18n.name.${dl}`)" data-testid="dl-name" />
          <ConfigField :label="t('dl.key')" for="dl-key" width="sm" :hint="t('dl.key_hint')" :error="err('key')" required>
            <InputText id="dl-key" v-model="doc.key" dir="ltr" class="ltr-value" size="small" maxlength="48" data-testid="dl-key" />
          </ConfigField>
          <I18nInput v-model="doc.i18n.description" class="w-field-lg" multiline :rows="2" :label="t('dl.description')" :maxlength="1000" />
          <ConfigField :label="t('dl.available_in')" for="dl-place-table_toolbar" width="lg" :error="err('availableIn')">
            <div class="flex flex-wrap gap-4">
              <label v-for="p in ['table_toolbar', 'record_view'] as const" :key="p" class="inline-flex items-center gap-2 text-sm">
                <Checkbox :input-id="`dl-place-${p}`" :model-value="doc.availableIn.includes(p)" binary @update:model-value="(v: boolean) => togglePlace(p, v)" />
                {{ t(`dl.place.${p}`) }}
              </label>
            </div>
          </ConfigField>
          <SettingSwitch id="dl-user-filters" v-model="doc.applyUserFilters" :label="t('dl.user_filters')" :description="t('dl.user_filters_desc')" />
          <SettingSwitch id="dl-selected" v-model="doc.allowSelected" :label="t('dl.selected')" :description="t('dl.selected_desc')" />
          <SettingSwitch id="dl-active" v-model="doc.active" :label="t('dl.active')" :description="t('dl.active_desc')" />
        </ConfigSection>

        <ConfigSection id="dl-columns" :title="t('dl.section.columns')" :count="doc.columns.length" :description="t('dl.columns_hint')">
          <p v-if="err('columns')" class="m-0 text-xs text-danger">{{ err('columns') }}</p>
          <div class="dl-picker">
            <h4 class="m-0 text-sm font-semibold">{{ t('dl.tree') }}</h4>
            <RelationTree :form="form" :personal="personal" :selected="selected" @toggle="toggle" @loaded="loaded" />
          </div>
          <ConfigItem
            v-for="(c, i) in doc.columns"
            :key="c.uuid"
            :title="columnTitle(c)"
            :subtitle="columnSubtitle(c)"
            :index="i"
            :count="doc.columns.length"
            movable
            :invalid="Object.keys(errors).some((k) => k.startsWith(`columns.${i}.`))"
            :testid="`dl-col-${i}`"
            @move="(d: -1 | 1) => move(doc!.columns, i, d)"
            @remove="doc!.columns.splice(i, 1)"
          >
            <div class="flex flex-col gap-4">
              <p v-if="info[c.uuid]?.broken" class="m-0 text-xs text-danger">{{ t('dl.broken') }}</p>
              <p v-if="err(`columns.${i}.path`) || err(`columns.${i}.kind`)" class="m-0 text-xs text-danger">{{ err(`columns.${i}.path`) ?? err(`columns.${i}.kind`) }}</p>
              <I18nInput v-model="c.i18n.header" class="w-field-md" :label="t('dl.header')" :maxlength="255" :invalid="!!err(`columns.${i}.i18n.header.${dl}`)" />
              <ConfigField v-if="c.kind === 'static'" :label="t('dl.static_value')" :for="`dl-static-${i}`" width="md">
                <InputText :id="`dl-static-${i}`" v-model="c.staticValue as string" size="small" maxlength="1024" />
              </ConfigField>
              <div v-if="c.kind === 'calculated' && scope" class="flex flex-col gap-1">
                <ExpressionInput v-model="c.expression" :scope="scope" :label="t('dl.formula')" :allow-empty="false" />
                <p v-if="err(`columns.${i}.expression`)" class="m-0 text-xs text-danger">{{ err(`columns.${i}.expression`) }}</p>
                <p class="m-0 text-xs text-muted-color">{{ t('dl.formula_hint') }}</p>
              </div>
              <template v-if="info[c.uuid]?.many">
                <ConfigField :label="t('dl.to_many_label')" :for="`dl-many-${i}`" width="lg" :hint="t('dl.to_many_hint')" :error="err(`columns.${i}.toMany`)">
                  <SelectButton v-model="c.toMany" :options="toManyModes" option-label="label" option-value="value" :allow-empty="false" size="small" :data-testid="`dl-many-${i}`" />
                </ConfigField>
                <div v-if="c.toMany === 'aggregate'" class="cfg-row">
                  <ConfigField :label="t('dl.aggregate')" :for="`dl-agg-${i}`" width="sm" :error="err(`columns.${i}.aggregate`)">
                    <Select v-model="c.aggregate" :input-id="`dl-agg-${i}`" :options="aggregates" option-label="label" option-value="value" size="small" />
                  </ConfigField>
                  <ConfigField v-if="c.aggregate === 'join'" :label="t('dl.separator')" :for="`dl-sep-${i}`" width="xs">
                    <InputText :id="`dl-sep-${i}`" v-model="c.separator as string" size="small" maxlength="16" />
                  </ConfigField>
                </div>
                <ConfigField v-if="c.toMany === 'separate_sheet'" :label="t('dl.sheet')" :for="`dl-sheet-${i}`" width="sm" :hint="t('dl.sheet_hint')" :error="err(`columns.${i}.sheet`)">
                  <InputText :id="`dl-sheet-${i}`" v-model="c.sheet as string" dir="ltr" class="ltr-value" size="small" maxlength="48" />
                </ConfigField>
              </template>
              <div class="cfg-row">
                <ConfigField :label="t('dl.width')" :for="`dl-width-${i}`" width="xs" :error="err(`columns.${i}.width`)">
                  <InputNumber v-model="c.width" :input-id="`dl-width-${i}`" :min="4" :max="120" size="small" fluid />
                </ConfigField>
                <ConfigField :label="t('dl.display_format')" :for="`dl-fmt-${i}`" width="sm">
                  <Select
                    :model-value="c.format?.type ?? null"
                    :input-id="`dl-fmt-${i}`"
                    :options="formatTypes"
                    option-label="label"
                    option-value="value"
                    size="small"
                    @update:model-value="(v: FormatType | null) => setFormat(c, v)"
                  />
                </ConfigField>
                <template v-if="c.format && ['number', 'currency', 'percent'].includes(c.format.type)">
                  <ConfigField :label="t('dl.decimals')" :for="`dl-dec-${i}`" width="xs">
                    <InputNumber v-model="c.format.decimals" :input-id="`dl-dec-${i}`" :min="0" :max="6" size="small" fluid />
                  </ConfigField>
                  <ConfigField v-if="c.format.type === 'currency'" :label="t('dl.currency')" :for="`dl-cur-${i}`" width="xs" :error="err(`columns.${i}.format.currency`)">
                    <InputText :id="`dl-cur-${i}`" v-model="c.format.currency as string" dir="ltr" class="ltr-value" size="small" maxlength="3" />
                  </ConfigField>
                </template>
                <ConfigField v-if="c.format && c.format.type !== 'text'" :label="t('dl.digits')" :for="`dl-dig-${i}`" width="xs">
                  <SelectButton v-model="c.format.digits" :options="digitOptions" option-label="label" option-value="value" :allow-empty="false" size="small" />
                </ConfigField>
              </div>
            </div>
          </ConfigItem>
          <div class="flex flex-wrap gap-2">
            <Button icon="pi pi-plus" :label="t('dl.add_static')" size="small" text @click="addColumn('static')" />
            <Button v-if="scope" icon="pi pi-plus" :label="t('dl.add_calculated')" size="small" text data-testid="dl-add-calculated" @click="addColumn('calculated')" />
          </div>
          <p v-if="hasSheets" class="m-0 text-xs text-muted-color">{{ t('dl.sheets_note') }}</p>
        </ConfigSection>

        <ConfigSection id="dl-filters" :title="t('dl.section.filters')" :count="doc.filters.length" :description="t('dl.filters_hint')" :default-open="doc.filters.length > 0">
          <ConfigItem
            v-for="(f, i) in doc.filters"
            :key="f.uuid"
            :title="filterFields.find((o) => o.value === pathKey(f.path))?.label ?? t('dl.filter')"
            :subtitle="t(`dl.op.${f.operator}`)"
            :index="i"
            :count="doc.filters.length"
            :invalid="Object.keys(errors).some((k) => k.startsWith(`filters.${i}.`))"
            :testid="`dl-filter-${i}`"
            @remove="doc!.filters.splice(i, 1)"
          >
            <div class="flex flex-col gap-3">
              <div class="cfg-row">
                <ConfigField :label="t('dl.field')" :for="`dl-ff-${i}`" width="md" :hint="t('dl.filter_field_hint')" :error="err(`filters.${i}.path`)">
                  <Select
                    :model-value="pathKey(f.path)"
                    :input-id="`dl-ff-${i}`"
                    :options="filterFields"
                    option-label="label"
                    option-value="value"
                    filter
                    size="small"
                    @update:model-value="(v: string) => (f.path = JSON.parse(v))"
                  />
                </ConfigField>
                <ConfigField :label="t('dl.operator')" :for="`dl-fo-${i}`" width="sm">
                  <Select v-model="f.operator" :input-id="`dl-fo-${i}`" :options="operators" option-label="label" option-value="value" size="small" />
                </ConfigField>
              </div>
              <template v-if="!['empty', 'not_empty'].includes(f.operator)">
                <SelectButton
                  :model-value="filterMode(f)"
                  :options="filterModes"
                  option-label="label"
                  option-value="value"
                  :allow-empty="false"
                  size="small"
                  @update:model-value="(m: FilterMode) => setFilterMode(f, m)"
                />
                <ConfigField v-if="filterMode(f) === 'parameter'" :label="t('dl.parameter')" :for="`dl-fp-${i}`" width="sm" :error="err(`filters.${i}.parameter`)">
                  <Select v-model="f.parameter" :input-id="`dl-fp-${i}`" :options="parameterOptions" option-label="label" option-value="value" size="small" />
                </ConfigField>
                <div v-else-if="filterMode(f) === 'expression' && scope" class="flex flex-col gap-1">
                  <ExpressionInput v-model="f.valueExpression" :scope="scope" :label="t('dl.value')" :allow-empty="false" />
                </div>
                <div v-else-if="f.operator === 'between'" class="cfg-row">
                  <ConfigField :label="t('dl.from')" :for="`dl-fv-${i}-0`" width="sm" :error="err(`filters.${i}.value`)">
                    <InputText :id="`dl-fv-${i}-0`" :model-value="between(f, 0)" size="small" @update:model-value="(v: string | undefined) => setBetween(f, 0, v ?? '')" />
                  </ConfigField>
                  <ConfigField :label="t('dl.to')" :for="`dl-fv-${i}-1`" width="sm">
                    <InputText :id="`dl-fv-${i}-1`" :model-value="between(f, 1)" size="small" @update:model-value="(v: string | undefined) => setBetween(f, 1, v ?? '')" />
                  </ConfigField>
                </div>
                <ConfigField v-else :label="t('dl.value')" :for="`dl-fv-${i}`" width="md" :hint="f.operator === 'in' ? t('dl.in_hint') : undefined" :error="err(`filters.${i}.value`)">
                  <InputText :id="`dl-fv-${i}`" :model-value="textValue(f)" size="small" @update:model-value="(v: string | undefined) => setTextValue(f, v ?? '')" />
                </ConfigField>
              </template>
            </div>
          </ConfigItem>
          <Button icon="pi pi-plus" :label="t('dl.add_filter')" size="small" text class="self-start" :disabled="!filterFields.length" data-testid="dl-add-filter" @click="addFilter" />
        </ConfigSection>

        <ConfigSection id="dl-params" :title="t('dl.section.parameters')" :count="doc.parameters.length" :description="t('dl.parameters_hint')" :default-open="doc.parameters.length > 0">
          <ConfigItem
            v-for="(p, i) in doc.parameters"
            :key="i"
            :title="paramLabel(p)"
            :subtitle="t(`dl.param_type.${p.type}`)"
            :index="i"
            :count="doc.parameters.length"
            :testid="`dl-param-${i}`"
            @remove="doc!.parameters.splice(i, 1)"
          >
            <div class="flex flex-col gap-3">
              <I18nInput v-model="p.i18n.label" class="w-field-md" :label="t('dl.param_label')" :maxlength="120" :invalid="!!err(`parameters.${i}.i18n.label.${dl}`)" />
              <div class="cfg-row">
                <ConfigField :label="t('dl.key')" :for="`dl-pk-${i}`" width="sm" :error="err(`parameters.${i}.key`)">
                  <InputText :id="`dl-pk-${i}`" v-model="p.key" dir="ltr" class="ltr-value" size="small" maxlength="48" />
                </ConfigField>
                <ConfigField :label="t('dl.param_type_label')" :for="`dl-pt-${i}`" width="sm">
                  <Select v-model="p.type" :input-id="`dl-pt-${i}`" :options="paramTypes" option-label="label" option-value="value" size="small" />
                </ConfigField>
              </div>
              <SettingSwitch :id="`dl-pr-${i}`" v-model="p.required" :label="t('dl.required')" :description="t('dl.required_desc')" />
              <div v-if="p.type === 'choice'" class="flex flex-col gap-2">
                <h4 class="m-0 text-sm font-semibold">{{ t('dl.choices') }}</h4>
                <p v-if="err(`parameters.${i}.options`)" class="m-0 text-xs text-danger">{{ err(`parameters.${i}.options`) }}</p>
                <div v-for="(o, k) in p.options" :key="k" class="flex flex-wrap items-end gap-2">
                  <InputText v-model="o.value" dir="ltr" class="ltr-value w-field-xs" size="small" :aria-label="t('dl.choice_value')" />
                  <I18nInput v-model="o.label" class="w-field-sm" :label="t('dl.choice_label')" :maxlength="120" />
                  <Button icon="pi pi-times" text rounded size="small" severity="secondary" :aria-label="t('dl.remove')" @click="p.options.splice(k, 1)" />
                </div>
                <Button icon="pi pi-plus" :label="t('dl.add_choice')" size="small" text class="self-start" @click="p.options.push({ value: '', label: {} })" />
              </div>
            </div>
          </ConfigItem>
          <Button icon="pi pi-plus" :label="t('dl.add_parameter')" size="small" text class="self-start" data-testid="dl-add-parameter" @click="addParameter" />
        </ConfigSection>

        <ConfigSection id="dl-output" :title="t('dl.section.output')" :default-open="false">
          <ConfigField :label="t('dl.formats')" for="dl-format-xlsx" width="lg" :error="err('formats')">
            <div class="flex flex-wrap gap-4">
              <label v-for="f in ['xlsx', 'csv', 'pdf'] as DlFormat[]" :key="f" class="inline-flex items-center gap-2 text-sm">
                <Checkbox :input-id="`dl-format-${f}`" :model-value="doc.formats.includes(f)" binary :data-testid="`dl-format-${f}`" @update:model-value="(v: boolean) => toggleFormat(f, v)" />
                {{ t(`dl.format.${f}`) }}
              </label>
            </div>
          </ConfigField>
          <div class="cfg-row">
            <ConfigField
              :label="t('dl.file_name')"
              for="dl-file"
              width="md"
              :hint="t('dl.file_name_hint', { form: token('form'), date: token('date:yyyyMMdd'), user: token('user'), param: token('param.key') })"
              :error="err('fileName')"
            >
              <InputText id="dl-file" v-model="doc.fileName" size="small" maxlength="255" />
            </ConfigField>
            <ConfigField :label="t('dl.max_rows')" for="dl-max" width="xs" :hint="t('dl.max_rows_hint')" :error="err('maxRows')">
              <InputNumber v-model="doc.maxRows" input-id="dl-max" :min="0" size="small" fluid />
            </ConfigField>
          </div>
          <h4 class="m-0 text-sm font-semibold">{{ t('dl.format.xlsx') }}</h4>
          <SettingSwitch id="dl-x-style" v-model="doc.xlsx.styledHeader" :label="t('dl.xlsx.styled')" :description="t('dl.xlsx.styled_desc')" />
          <SettingSwitch id="dl-x-freeze" v-model="doc.xlsx.freezeHeader" :label="t('dl.xlsx.freeze')" :description="t('dl.xlsx.freeze_desc')" />
          <SettingSwitch id="dl-x-title" v-model="doc.xlsx.titleRow" :label="t('dl.xlsx.title')" :description="t('dl.xlsx.title_desc')" />
          <SettingSwitch id="dl-x-logo" v-model="doc.xlsx.logo" :label="t('dl.xlsx.logo')" :description="t('dl.xlsx.logo_desc')" />
          <h4 class="m-0 text-sm font-semibold">{{ t('dl.format.csv') }}</h4>
          <div class="cfg-row">
            <ConfigField :label="t('dl.delimiter')" for="dl-delim" width="sm">
              <Select v-model="doc.csv.delimiter" input-id="dl-delim" :options="delimiters" option-label="label" option-value="value" size="small" />
            </ConfigField>
          </div>
          <SettingSwitch id="dl-bom" v-model="doc.csv.bom" :label="t('dl.bom')" :description="t('dl.bom_desc')" />
          <h4 class="m-0 text-sm font-semibold">{{ t('dl.format.pdf') }}</h4>
          <ConfigField :label="t('doc.orientation')" for="dl-orient" width="lg">
            <SelectButton v-model="doc.pdf.orientation" :options="orientations" option-label="label" option-value="value" :allow-empty="false" size="small" />
          </ConfigField>
          <I18nInput v-model="doc.pdf.header" class="w-field-lg" :label="t('dl.pdf_header')" :maxlength="255" />
          <I18nInput v-model="doc.pdf.footer" class="w-field-lg" :label="t('dl.pdf_footer')" :maxlength="255" />
          <SettingSwitch id="dl-pages" v-model="doc.pdf.pageNumbers" :label="t('dl.page_numbers')" :description="t('dl.page_numbers_desc')" />
        </ConfigSection>

        <ConfigSection v-if="!isNew && uuid" id="dl-schedules" :title="t('dl.section.schedules')" :description="t('dl.schedules_hint')" :default-open="false">
          <p v-if="dirty" class="m-0 text-xs text-muted-color">{{ t('dl.save_before_schedule') }}</p>
          <ScheduleList :profile="uuid" :formats="doc.formats" :parameters="doc.parameters" :personal="personal" />
        </ConfigSection>

        <ErrorList :errors="errors" />
        <ConfigSaveBar :dirty="dirty" :saving="saving" testid="dl" @save="save" @discard="discard" />
      </div>

      <aside class="dl-side">
        <h2 class="text-base font-semibold m-0">{{ t('dl.preview') }}</h2>
        <p class="m-0 text-xs text-muted-color">{{ t('dl.preview_hint') }}</p>
        <div v-for="p in doc.parameters" :key="p.key" class="flex items-center gap-2">
          <label :for="`dl-pv-${p.key}`" class="text-sm w-32 truncate">{{ paramLabel(p) }}</label>
          <InputText :id="`dl-pv-${p.key}`" v-model="previewParams[p.key] as string" size="small" :type="p.type === 'date' ? 'date' : 'text'" />
        </div>
        <Message v-if="previewError" severity="warn">{{ previewError }}</Message>
        <p v-else-if="!doc.columns.length" class="m-0 text-sm text-muted-color">{{ t('dl.preview_empty') }}</p>
        <template v-else-if="preview">
          <p v-if="preview.omitted.length" class="m-0 text-sm text-muted-color flex items-start gap-2">
            <i class="pi pi-eye-slash mt-0.5" aria-hidden="true" />{{ t('dl.omitted', { columns: joinList(preview.omitted, locale) }) }}
          </p>
          <Tabs v-if="sheetTabs.length > 1" v-model:value="previewSheet">
            <TabList>
              <Tab v-for="s in sheetTabs" :key="s.key" :value="s.key">{{ s.label }}</Tab>
            </TabList>
          </Tabs>
          <div class="dl-table-wrap">
            <table class="dl-table" data-testid="dl-preview">
              <thead>
                <tr>
                  <th v-for="(h, k) in preview.headers[previewSheet] ?? []" :key="k" dir="auto">{{ h }}</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="(r, k) in preview.rows[previewSheet] ?? []" :key="k">
                  <td v-for="(v, j) in r" :key="j" dir="auto">{{ cellText(v) }}</td>
                </tr>
                <tr v-if="!(preview.rows[previewSheet] ?? []).length">
                  <td :colspan="Math.max(1, (preview.headers[previewSheet] ?? []).length)" class="text-muted-color">{{ t('dl.no_rows') }}</td>
                </tr>
              </tbody>
            </table>
          </div>
        </template>
        <template v-if="!personal">
          <h2 class="text-base font-semibold m-0 mt-2">{{ t('dl.as_role') }}</h2>
          <Select v-model="role" :options="roles" option-label="name" option-value="uuid" show-clear filter size="small" :placeholder="t('dl.choose_role')" data-testid="dl-role" />
          <div v-if="effective" class="text-sm flex flex-col gap-1" data-testid="dl-effective">
            <p class="m-0">{{ t('dl.role_columns', { columns: joinList(effective.columns, locale) }) }}</p>
            <p v-if="effective.omitted.length" class="m-0 text-muted-color">{{ t('dl.role_omitted', { columns: joinList(effective.omitted, locale) }) }}</p>
          </div>
        </template>
      </aside>
    </div>
  </div>
</template>

<style scoped>
.dl-grid {
  display: grid;
  gap: 1.5rem;
  grid-template-columns: minmax(0, 1fr);
}
@media (min-width: 1200px) {
  .dl-grid {
    grid-template-columns: minmax(0, 40rem) minmax(0, 1fr);
  }
  .dl-side {
    position: sticky;
    top: 0;
    align-self: start;
    max-height: calc(100vh - 6rem);
    overflow: auto;
  }
}
.dl-side {
  display: flex;
  flex-direction: column;
  gap: 0.75rem;
}
.dl-picker {
  border: 1px solid var(--border);
  border-radius: 8px;
  padding: 0.75rem;
  max-height: 22rem;
  overflow: auto;
  display: flex;
  flex-direction: column;
  gap: 0.5rem;
}
.dl-table-wrap {
  overflow: auto;
  border: 1px solid var(--border);
  border-radius: 8px;
}
.dl-table {
  border-collapse: collapse;
  width: 100%;
  font-size: 0.8125rem;
}
.dl-table th,
.dl-table td {
  border-bottom: 1px solid var(--border);
  padding: 0.375rem 0.5rem;
  text-align: start;
  white-space: nowrap;
}
.dl-table th {
  background: var(--bg-subtle);
  font-weight: 600;
}
</style>
