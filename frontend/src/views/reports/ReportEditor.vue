<script setup lang="ts">
import Button from 'primevue/button'
import InputNumber from 'primevue/inputnumber'
import InputText from 'primevue/inputtext'
import Message from 'primevue/message'
import Select from 'primevue/select'
import SelectButton from 'primevue/selectbutton'
import { useConfirm } from 'primevue/useconfirm'
import { useToast } from 'primevue/usetoast'
import { computed, onMounted, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { onBeforeRouteLeave, useRoute, useRouter } from 'vue-router'
import FormPicker from '@/builder/FormPicker.vue'
import I18nInput from '@/builder/I18nInput.vue'
import ConfigField from '@/components/config/ConfigField.vue'
import ConfigItem from '@/components/config/ConfigItem.vue'
import ConfigSaveBar from '@/components/config/ConfigSaveBar.vue'
import ConfigSection from '@/components/config/ConfigSection.vue'
import EmptyState from '@/components/config/EmptyState.vue'
import SettingSwitch from '@/components/config/SettingSwitch.vue'
import { provideConfigScreen } from '@/components/config/configScreen'
import { errorText, fieldErrors } from '../admin/building/shared'
import { reportsApi, uid, VISUALIZATIONS, type Aggregate, type Bucket, type ReportDoc, type Result } from './api'
import PathPicker from './PathPicker.vue'
import ReportChart from './ReportChart.vue'

/**
 * The report builder (specification §4.18): a base form, what to group by
 * (through links, dates by period), the values to work out, or a list of
 * columns; fixed filters, sort and limit; and how to show it. The preview
 * runs the report as the person building it.
 */
provideConfigScreen()
const { t } = useI18n()
const toast = useToast()
const confirm = useConfirm()
const route = useRoute()
const router = useRouter()
const isNew = computed(() => route.params.report === 'new')
const doc = ref<ReportDoc | null>(null)
const saved = ref('')
const saving = ref(false)
const errors = ref<Record<string, string>>({})
const preview = ref<Result | null>(null)
const previewError = ref<string | null>(null)
const previewing = ref(false)

function blank(): ReportDoc {
  return {
    key: '',
    form: null,
    application: null,
    visualization: 'bar',
    i18n: { name: {}, description: {} },
    dimensions: [],
    measures: [],
    columns: [],
    filters: [],
    sort: null,
    limit: null,
    cacheSeconds: null,
    options: { stacked: false, showValues: false, showLegend: true, showTotals: false },
  }
}
onMounted(async () => {
  try {
    doc.value = isNew.value ? blank() : await reportsApi.load(String(route.params.report))
    saved.value = JSON.stringify(doc.value)
    if (!isNew.value) void runPreview()
  } catch (e) {
    toast.add({ severity: 'error', summary: errorText(e, t('rep.load_failed')), life: 6000 })
  }
})
const dirty = computed(() => doc.value !== null && JSON.stringify(doc.value) !== saved.value)
onBeforeRouteLeave(() => !dirty.value || window.confirm(t('records.leave_unsaved')))
const err = (k: string) => errors.value[k]

const vizOptions = computed(() => VISUALIZATIONS.map((v) => ({ value: v, label: t(`rep.viz.${v}`), icon: VIZ_ICON[v] })))
const VIZ_ICON: Record<string, string> = {
  table: 'pi pi-table',
  pivot: 'pi pi-th-large',
  bar: 'pi pi-chart-bar',
  line: 'pi pi-chart-line',
  area: 'pi pi-wave-pulse',
  pie: 'pi pi-chart-pie',
  kpi: 'pi pi-hashtag',
}
const aggregates = computed(() => (['count', 'sum', 'avg', 'min', 'max'] as Aggregate[]).map((a) => ({ value: a, label: t(`rep.agg.${a}`) })))
const buckets = computed(() => [{ value: null, label: t('rep.bucket.none') }, ...(['day', 'week', 'month', 'quarter', 'year'] as Bucket[]).map((b) => ({ value: b, label: t(`rep.bucket.${b}`) }))])
const operators = computed(() => ['eq', 'neq', 'gt', 'gte', 'lt', 'lte', 'contains', 'starts_with', 'in', 'empty', 'not_empty'].map((o) => ({ value: o, label: t(`rep.op.${o}`) })))
const isList = computed(() => doc.value?.visualization === 'table' && (doc.value.columns.length > 0 || (doc.value.dimensions.length === 0 && doc.value.measures.length === 0)))
const sortOptions = computed(() => {
  if (!doc.value) return []
  const d = doc.value
  return [
    { value: null, label: t('rep.sort_default') },
    ...d.dimensions.map((x, i) => ({ value: `dimension:${x.uuid}`, label: t('rep.sort_grouping', { n: i + 1 }) })),
    ...d.measures.map((x, i) => ({ value: `measure:${x.uuid}`, label: t('rep.sort_value', { n: i + 1 }) })),
    ...d.columns.map((x, i) => ({ value: `column:${x.uuid}`, label: t('rep.sort_column', { n: i + 1 }) })),
  ]
})
const sortKey = computed({
  get: () => (doc.value?.sort ? `${doc.value.sort.by}:${doc.value.sort.ref}` : null),
  set: (v: string | null) => {
    if (!doc.value) return
    if (!v) {
      doc.value.sort = null
      return
    }
    const [by, ref] = v.split(':') as ['dimension' | 'measure' | 'column', string]
    doc.value.sort = { by, ref, direction: doc.value.sort?.direction ?? 'asc' }
  },
})
const directions = computed(() => [
  { value: 'asc', label: t('rep.asc') },
  { value: 'desc', label: t('rep.desc') },
])

function addDimension(): void {
  doc.value!.dimensions.push({ uuid: uid(), path: [], bucket: null, i18n: { label: {} } })
}
function addMeasure(): void {
  doc.value!.measures.push({ uuid: uid(), aggregate: 'count', path: null, i18n: { label: {} } })
}
function addColumn(): void {
  doc.value!.columns.push({ uuid: uid(), path: [], i18n: { header: {} } })
}
function addFilter(): void {
  doc.value!.filters.push({ uuid: uid(), path: [], operator: 'eq', value: '' })
}
function move<T>(list: T[], i: number, delta: -1 | 1): void {
  const j = i + delta
  if (j < 0 || j >= list.length) return
  ;[list[i], list[j]] = [list[j]!, list[i]!]
}
function filterValue(v: unknown): string {
  return Array.isArray(v) ? v.join(', ') : v === null || v === undefined ? '' : String(v) // machine-list: the value box's own input format
}
function setFilterValue(i: number, op: string, text: string): void {
  doc.value!.filters[i]!.value =
    op === 'in'
      ? text
          .split(',')
          .map((s) => s.trim())
          .filter(Boolean)
      : text
}
// A new base form starts the fields over.
watch(
  () => doc.value?.form,
  (f, old) => {
    if (!doc.value || old === undefined || old === null || f === old) return
    Object.assign(doc.value, { dimensions: [], measures: [], columns: [], filters: [], sort: null })
    preview.value = null
  },
)

async function runPreview(): Promise<void> {
  if (!doc.value?.form) return
  previewing.value = true
  previewError.value = null
  errors.value = {}
  try {
    preview.value = await reportsApi.preview(doc.value)
  } catch (e) {
    errors.value = fieldErrors(e)
    previewError.value = errorText(e, t('rep.preview_failed'))
    preview.value = null
  } finally {
    previewing.value = false
  }
}
async function save(): Promise<void> {
  if (!doc.value) return
  saving.value = true
  errors.value = {}
  try {
    const out = isNew.value ? await reportsApi.create(doc.value) : await reportsApi.save(doc.value.uuid!, doc.value, doc.value.hash!)
    doc.value = out
    saved.value = JSON.stringify(out)
    toast.add({ severity: 'success', summary: t('rep.saved'), life: 3000 })
    if (isNew.value) await router.replace({ name: 'admin.reports.report', params: { report: out.uuid } })
    void runPreview()
  } catch (e) {
    errors.value = fieldErrors(e)
    toast.add({ severity: 'error', summary: errorText(e, t('rep.save_failed')), life: 8000 })
  } finally {
    saving.value = false
  }
}
function discard(): void {
  doc.value = JSON.parse(saved.value) as ReportDoc
  errors.value = {}
}
function remove(): void {
  confirm.require({
    header: t('rep.delete_title'),
    message: t('rep.delete_text'),
    acceptProps: { label: t('rep.delete'), severity: 'danger' },
    rejectProps: { label: t('rep.cancel'), text: true },
    accept: async () => {
      try {
        await reportsApi.remove(doc.value!.uuid!)
        saved.value = JSON.stringify(doc.value)
        await router.push({ name: 'admin.reports' })
      } catch (e) {
        toast.add({ severity: 'error', summary: errorText(e, t('rep.delete_failed')), life: 6000 })
      }
    },
  })
}
</script>

<template>
  <div class="flex flex-col gap-6 max-w-[64rem]" data-testid="report-editor">
    <header class="page-head">
      <div class="flex-1 min-w-0">
        <RouterLink :to="{ name: 'admin.reports' }" class="text-sm text-primary inline-flex items-center gap-1 mb-1">
          <i class="pi pi-arrow-left rtl:rotate-180" aria-hidden="true" />{{ t('admin.area.reports') }}
        </RouterLink>
        <h1 class="page-title !mb-1">{{ isNew ? t('rep.new_report') : (doc?.label ?? t('rep.report')) }}</h1>
        <p class="m-0 text-sm text-muted-color">{{ t('rep.editor_subtitle') }}</p>
      </div>
      <div class="flex items-center gap-2">
        <Button v-if="!isNew && doc" icon="pi pi-eye" :label="t('rep.open')" size="small" text @click="router.push({ name: 'reports.view', params: { report: doc.uuid } })" />
        <Button v-if="!isNew" icon="pi pi-trash" :label="t('rep.delete')" size="small" severity="danger" text data-testid="report-delete" @click="remove" />
      </div>
    </header>

    <template v-if="doc">
      <ConfigSection id="report-basics" :title="t('rep.section.basics')">
        <div class="cfg-row">
          <I18nInput v-model="doc.i18n.name" class="w-field-md" :label="t('rep.name')" :maxlength="255" :invalid="!!err('i18n.name')" />
          <ConfigField :label="t('rep.key')" for="report-key" width="sm" :hint="t('rep.key_hint')" :error="err('key')" required>
            <InputText id="report-key" v-model="doc.key" dir="ltr" class="ltr-value" size="small" maxlength="48" :disabled="!isNew" data-testid="report-key" />
          </ConfigField>
        </div>
        <ConfigField :label="t('rep.form')" for="report-form" width="md" :hint="t('rep.form_hint')" :error="err('form')" required>
          <FormPicker v-model="doc.form" input-id="report-form" />
        </ConfigField>
        <I18nInput v-model="doc.i18n.description" class="w-field-lg" :label="t('rep.description')" multiline :rows="2" :maxlength="255" />
      </ConfigSection>

      <ConfigSection id="report-show" :title="t('rep.section.show')" :description="t(`rep.viz_hint.${doc.visualization}`)">
        <SelectButton v-model="doc.visualization" :options="vizOptions" option-label="label" option-value="value" :allow-empty="false" data-testid="report-viz" class="flex-wrap">
          <template #option="{ option }"
            ><i :class="option.icon" aria-hidden="true" /><span>{{ option.label }}</span></template
          >
        </SelectButton>
        <small v-if="err('visualization')" class="text-danger">{{ err('visualization') }}</small>
      </ConfigSection>

      <template v-if="doc.form">
        <ConfigSection id="report-dimensions" :title="t('rep.section.group')" :description="t('rep.group_hint')" :count="doc.dimensions.length">
          <ConfigItem
            v-for="(d, i) in doc.dimensions"
            :key="d.uuid"
            :title="t('rep.grouping_n', { n: i + 1 })"
            :index="i"
            :count="doc.dimensions.length"
            movable
            :invalid="!!err(`dimensions.${i}.path`) || !!err(`dimensions.${i}.bucket`)"
            :testid="`report-dim-${i}`"
            @move="(delta) => move(doc!.dimensions, i, delta)"
            @remove="doc!.dimensions.splice(i, 1)"
          >
            <div class="cfg-row">
              <ConfigField :label="t('rep.field')" :for="`dim-${d.uuid}`" width="full" :error="err(`dimensions.${i}.path`)">
                <PathPicker v-model="d.path" :form="doc.form" :input-id="`dim-${d.uuid}`" />
              </ConfigField>
            </div>
            <div class="cfg-row">
              <ConfigField :label="t('rep.bucket_label')" :for="`bucket-${d.uuid}`" width="sm" :hint="t('rep.bucket_hint')" :error="err(`dimensions.${i}.bucket`)">
                <Select v-model="d.bucket" :input-id="`bucket-${d.uuid}`" :options="buckets" option-label="label" option-value="value" size="small" />
              </ConfigField>
              <I18nInput v-model="d.i18n.label" class="w-field-md" :label="t('rep.own_label')" :maxlength="255" />
            </div>
          </ConfigItem>
          <div><Button icon="pi pi-plus" :label="t('rep.add_grouping')" size="small" text :disabled="doc.dimensions.length >= 3" data-testid="report-add-dim" @click="addDimension" /></div>
        </ConfigSection>

        <ConfigSection id="report-measures" :title="t('rep.section.values')" :description="t('rep.values_hint')" :count="doc.measures.length">
          <ConfigItem
            v-for="(m, i) in doc.measures"
            :key="m.uuid"
            :title="t('rep.value_n', { n: i + 1 })"
            :index="i"
            :count="doc.measures.length"
            movable
            :invalid="!!err(`measures.${i}.path`)"
            :testid="`report-measure-${i}`"
            @move="(delta) => move(doc!.measures, i, delta)"
            @remove="doc!.measures.splice(i, 1)"
          >
            <div class="cfg-row">
              <ConfigField :label="t('rep.aggregate')" :for="`agg-${m.uuid}`" width="sm">
                <Select
                  v-model="m.aggregate"
                  :input-id="`agg-${m.uuid}`"
                  :options="aggregates"
                  option-label="label"
                  option-value="value"
                  size="small"
                  :data-testid="`report-agg-${i}`"
                  @update:model-value="(a: Aggregate) => a !== 'count' && m.path === null && (m.path = [])"
                />
              </ConfigField>
              <ConfigField :label="t('rep.of_field')" :for="`mf-${m.uuid}`" width="lg" :hint="m.aggregate === 'count' ? t('rep.count_hint') : undefined" :error="err(`measures.${i}.path`)">
                <div class="flex flex-col gap-1">
                  <PathPicker
                    :model-value="m.path && m.path.length ? m.path : null"
                    :form="doc.form"
                    :want="m.aggregate === 'sum' || m.aggregate === 'avg' ? 'numeric' : 'any'"
                    :input-id="`mf-${m.uuid}`"
                    @update:model-value="(p) => (m.path = p ?? (m.aggregate === 'count' ? null : []))"
                  />
                  <Button v-if="m.aggregate === 'count' && m.path" :label="t('rep.count_records_instead')" size="small" text class="self-start" @click="m.path = null" />
                </div>
              </ConfigField>
            </div>
            <I18nInput v-model="m.i18n.label" class="w-field-md" :label="t('rep.own_label')" :maxlength="255" />
          </ConfigItem>
          <div><Button icon="pi pi-plus" :label="t('rep.add_value')" size="small" text :disabled="doc.measures.length >= 8" data-testid="report-add-measure" @click="addMeasure" /></div>
        </ConfigSection>

        <ConfigSection v-if="isList" id="report-columns" :title="t('rep.section.columns')" :description="t('rep.columns_hint')" :count="doc.columns.length">
          <ConfigItem
            v-for="(c, i) in doc.columns"
            :key="c.uuid"
            :title="t('rep.column_n', { n: i + 1 })"
            :index="i"
            :count="doc.columns.length"
            movable
            :invalid="!!err(`columns.${i}.path`)"
            :testid="`report-column-${i}`"
            @move="(delta) => move(doc!.columns, i, delta)"
            @remove="doc!.columns.splice(i, 1)"
          >
            <ConfigField :label="t('rep.field')" :for="`col-${c.uuid}`" width="full" :error="err(`columns.${i}.path`)">
              <PathPicker v-model="c.path" :form="doc.form" :input-id="`col-${c.uuid}`" />
            </ConfigField>
            <I18nInput v-model="c.i18n.header" class="w-field-md" :label="t('rep.own_label')" :maxlength="255" />
          </ConfigItem>
          <div><Button icon="pi pi-plus" :label="t('rep.add_column')" size="small" text data-testid="report-add-column" @click="addColumn" /></div>
        </ConfigSection>

        <ConfigSection id="report-filters" :title="t('rep.section.filters')" :description="t('rep.filters_hint')" :count="doc.filters.length" :default-open="doc.filters.length > 0">
          <ConfigItem
            v-for="(f, i) in doc.filters"
            :key="f.uuid"
            :title="t('rep.filter_n', { n: i + 1 })"
            :index="i"
            :count="doc.filters.length"
            :invalid="!!err(`filters.${i}.path`) || !!err(`filters.${i}.value`)"
            :testid="`report-filter-${i}`"
            @remove="doc!.filters.splice(i, 1)"
          >
            <ConfigField :label="t('rep.field')" :for="`flt-${f.uuid}`" width="full" :error="err(`filters.${i}.path`)">
              <PathPicker v-model="f.path" :form="doc.form" :input-id="`flt-${f.uuid}`" />
            </ConfigField>
            <div class="cfg-row">
              <ConfigField :label="t('rep.operator')" :for="`op-${f.uuid}`" width="sm">
                <Select v-model="f.operator" :input-id="`op-${f.uuid}`" :options="operators" option-label="label" option-value="value" size="small" />
              </ConfigField>
              <ConfigField
                v-if="!['empty', 'not_empty'].includes(f.operator)"
                :label="t('rep.value')"
                :for="`val-${f.uuid}`"
                width="md"
                :hint="f.operator === 'in' ? t('rep.in_hint') : undefined"
                :error="err(`filters.${i}.value`)"
              >
                <InputText :id="`val-${f.uuid}`" :model-value="filterValue(f.value)" size="small" dir="auto" @update:model-value="(v) => setFilterValue(i, f.operator, String(v ?? ''))" />
              </ConfigField>
            </div>
          </ConfigItem>
          <div><Button icon="pi pi-plus" :label="t('rep.add_filter')" size="small" text @click="addFilter" /></div>
        </ConfigSection>

        <ConfigSection id="report-display" :title="t('rep.section.display')" :default-open="false">
          <div class="cfg-row">
            <ConfigField :label="t('rep.sort')" for="report-sort" width="sm" :error="err('sort.ref')">
              <Select v-model="sortKey" input-id="report-sort" :options="sortOptions" option-label="label" option-value="value" size="small" />
            </ConfigField>
            <ConfigField v-if="doc.sort" :label="t('rep.direction')" for="report-dir" width="sm">
              <Select v-model="doc.sort.direction" input-id="report-dir" :options="directions" option-label="label" option-value="value" size="small" />
            </ConfigField>
            <ConfigField :label="t('rep.limit')" for="report-limit" width="xs" :hint="t('rep.limit_hint')" :error="err('limit')">
              <InputNumber v-model="doc.limit" input-id="report-limit" :min="1" :max="10000" size="small" :use-grouping="false" />
            </ConfigField>
            <ConfigField :label="t('rep.cache')" for="report-cache" width="xs" :hint="t('rep.cache_hint')" :error="err('cacheSeconds')">
              <InputNumber v-model="doc.cacheSeconds" input-id="report-cache" :min="0" :max="86400" size="small" :use-grouping="false" />
            </ConfigField>
          </div>
          <SettingSwitch
            v-if="['bar', 'line', 'area'].includes(doc.visualization)"
            id="report-stacked"
            v-model="doc.options.stacked"
            :label="t('rep.stacked')"
            :description="t('rep.stacked_desc')"
          />
          <SettingSwitch id="report-values" v-model="doc.options.showValues" :label="t('rep.show_values')" :description="t('rep.show_values_desc')" />
          <SettingSwitch id="report-legend" v-model="doc.options.showLegend" :label="t('rep.show_legend')" :description="t('rep.show_legend_desc')" />
          <SettingSwitch id="report-totals" v-model="doc.options.showTotals" :label="t('rep.show_totals')" :description="t('rep.show_totals_desc')" />
        </ConfigSection>

        <ConfigSection id="report-preview" :title="t('rep.section.preview')" :description="t('rep.preview_hint')">
          <div><Button icon="pi pi-play" :label="t('rep.run_preview')" size="small" outlined :loading="previewing" data-testid="report-preview" @click="runPreview" /></div>
          <Message v-if="previewError" severity="error" :closable="false">{{ previewError }}</Message>
          <ReportChart v-if="preview" :result="preview" :visualization="doc.visualization" :options="doc.options" />
          <EmptyState v-else-if="!previewError" icon="pi pi-chart-bar" :title="t('rep.preview_empty_title')" :description="t('rep.preview_empty_text')" testid="report-preview-empty" />
          <p v-if="!isNew" class="m-0 text-xs text-muted-color">{{ t('rep.access_hint') }}</p>
        </ConfigSection>
      </template>

      <ConfigSaveBar :dirty="dirty || isNew" :saving="saving" testid="report" @save="save" @discard="discard" />
    </template>
  </div>
</template>
