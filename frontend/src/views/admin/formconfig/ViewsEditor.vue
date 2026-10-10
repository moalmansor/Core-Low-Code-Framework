<script setup lang="ts">
import Button from 'primevue/button'
import Dialog from 'primevue/dialog'
import InputNumber from 'primevue/inputnumber'
import InputText from 'primevue/inputtext'
import Message from 'primevue/message'
import Select from 'primevue/select'
import { useToast } from 'primevue/usetoast'
import { computed, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { send } from '@/api/http'
import I18nInput from '@/builder/I18nInput.vue'
import ConfigField from '@/components/config/ConfigField.vue'
import ConfigItem from '@/components/config/ConfigItem.vue'
import ConfigSaveBar from '@/components/config/ConfigSaveBar.vue'
import ConfigSection from '@/components/config/ConfigSection.vue'
import EmptyState from '@/components/config/EmptyState.vue'
import SettingSwitch from '@/components/config/SettingSwitch.vue'
import TabIntro from '@/components/config/TabIntro.vue'
import { useSession } from '@/stores/session'
import LocaleFields from '../building/LocaleFields.vue'
import { errorText, fieldErrors, filledLocales } from '../building/shared'
import { labelOf, newUuid, viewsApi, type Path, type PathNode, type ViewColumnDoc, type ViewDoc } from './api'
import ErrorList from './ErrorList.vue'
import PathPicker from './PathPicker.vue'
import { useHashedDocument } from './useHashedDocument'

/**
 * Table views of a form (specification §4.14 "Table view"): columns from the
 * form, its system columns and the forms it links to, their order, width,
 * pinning and totals; filters and quick filters; default sort and page size.
 * Who uses which view is granted in the access matrix (`view.{uuid}.use`);
 * the default view applies when none of a user's views matches.
 */
const props = defineProps<{ form: string }>()
const { t, locale } = useI18n()
const session = useSession()
const doc = useHashedDocument<ViewDoc[]>(
  () => viewsApi.load(props.form),
  (v, h) => viewsApi.save(props.form, v, h),
)
const dl = computed(() => session.boot?.default_locale ?? 'en')
const tree = computed(() => (doc.extra.value.fields as PathNode[] | undefined) ?? [])
const published = computed(() => doc.extra.value.published !== false)
const current = ref(0)
const view = computed(() => doc.value.value?.[current.value] ?? null)

const pinned = computed(() => (['none', 'start', 'end'] as const).map((v) => ({ value: v, label: t(`views.pinned.${v}`) })))
const aggregates = computed(() => (['none', 'count', 'sum', 'avg', 'min', 'max'] as const).map((v) => ({ value: v, label: t(`views.aggregate.${v}`) })))
const dirs = computed(() => (['asc', 'desc'] as const).map((v) => ({ value: v, label: t(`views.dir.${v}`) })))

function nodeAt(path: Path): PathNode | undefined {
  let nodes = tree.value
  let found: PathNode | undefined
  for (const key of path) {
    found = nodes.find((n) => n.key === key)
    if (!found) return undefined
    nodes = found.children
  }
  return found
}
function pathLabel(path: Path): string {
  return path
    .map((_, i) => {
      const n = nodeAt(path.slice(0, i + 1))
      return n ? labelOf(n.label, locale.value, n.key, dl.value) : path[i]
    })
    .join(' › ')
}

function addView(): void {
  const list = doc.value.value
  if (!list) return
  list.push({
    uuid: newUuid(),
    key: `view_${list.length + 1}`,
    i18n: { name: {} },
    default: list.length === 0,
    priority: list.length,
    pageSize: 25,
    defaultSort: [],
    showTotals: false,
    columnChooser: true,
    globalSearch: true,
    rowOptions: { view: true, edit: true, log: true },
    includeInQueues: false,
    columns: [],
    filters: [],
  })
  current.value = list.length - 1
}
function removeView(i: number): void {
  const list = doc.value.value!
  const wasDefault = list[i]!.default
  list.splice(i, 1)
  if (wasDefault && list[0]) list[0].default = true
  current.value = Math.max(0, Math.min(current.value, list.length - 1))
}
function setDefault(i: number): void {
  doc.value.value!.forEach((v, j) => (v.default = i === j))
}
function addColumn(): void {
  view.value?.columns.push({ uuid: newUuid(), path: [], i18n: { label: {} }, width: null, pinned: 'none', visible: true, sortable: true, aggregate: 'none' })
}
function addFilter(): void {
  view.value?.filters.push({ uuid: newUuid(), path: [], i18n: { label: {} }, quick: false })
}
function move<T>(list: T[], i: number, d: -1 | 1): void {
  const j = i + d
  if (j < 0 || j >= list.length) return
  ;[list[i], list[j]] = [list[j]!, list[i]!]
}
// Save a saved view as a blueprint (specification §4.15): other forms create views from it.
const toast = useToast()
const canBlueprint = computed(() => session.can('system.manage_blueprints'))
const blueprint = ref<{ view: string; names: Record<string, string>; category: string } | null>(null)
const blueprintErrors = ref<Record<string, string>>({})
function openBlueprint(): void {
  if (!view.value) return
  blueprintErrors.value = {}
  blueprint.value = { view: view.value.uuid, names: { ...view.value.i18n.name }, category: '' }
}
async function saveBlueprint(): Promise<void> {
  const b = blueprint.value!
  try {
    await send('post', '/blueprints', { source: b.view, source_type: 'view', name: filledLocales(b.names), ...(b.category ? { category: b.category } : {}) })
    blueprint.value = null
    toast.add({ severity: 'success', summary: t('views.blueprint_saved'), life: 4000 })
  } catch (e) {
    blueprintErrors.value = fieldErrors(e)
    toast.add({ severity: 'error', summary: errorText(e, t('workflow.save_failed')), life: 6000 })
  }
}
const savedUuids = computed(() => new Set(((doc.extra.value.views as ViewDoc[] | undefined) ?? []).map((v) => v.uuid)))

/** One line describing a column for its folded header. */
function columnSummary(c: ViewColumnDoc): string {
  const parts = [
    c.width ? `${c.width}px` : null,
    c.pinned !== 'none' ? t(`views.pinned.${c.pinned}`) : null,
    c.aggregate !== 'none' ? t(`views.aggregate.${c.aggregate}`) : null,
    !c.visible ? t('views.hidden') : null,
  ]
  return parts.filter(Boolean).join(' · ')
}
const err = (path: string) => doc.errors.value[`views.${current.value}.${path}`]
const colInvalid = (c: ViewColumnDoc, j: number) => !c.path.length || !!err(`columns.${j}.path`)
</script>

<template>
  <div class="flex flex-col gap-6" data-testid="views-editor">
    <TabIntro :title="t('formconfig.tab.views')" :text="t('views.hint')" />
    <Message v-if="!published" severity="warn" :closable="false">{{ t('views.publish_first') }}</Message>

    <EmptyState v-if="doc.value.value && !doc.value.value.length" icon="pi pi-table" :title="t('views.empty_title')" :description="t('views.empty_text')" testid="views-empty">
      <Button icon="pi pi-plus" :label="t('views.add')" size="small" :disabled="!published" data-testid="views-add" @click="addView" />
    </EmptyState>

    <div v-else-if="doc.value.value" class="grid gap-6 lg:grid-cols-[14rem_minmax(0,1fr)] items-start">
      <nav class="flex flex-col gap-1" :aria-label="t('views.list')">
        <button
          v-for="(v, i) in doc.value.value"
          :key="v.uuid"
          type="button"
          class="text-start rounded-md px-3 py-2 flex flex-col"
          :class="i === current ? 'bg-primary-subtle text-on-primary-subtle font-medium' : 'hover:bg-subtle'"
          :aria-current="i === current ? 'true' : undefined"
          :data-testid="`view-item-${v.key}`"
          @click="current = i"
        >
          <span class="truncate">{{ labelOf(v.i18n.name, locale, v.key, dl) }}</span>
          <span v-if="v.default" class="text-xs text-muted-color">{{ t('views.default') }}</span>
        </button>
        <Button icon="pi pi-plus" :label="t('views.add')" text size="small" class="self-start mt-1" :disabled="!published" data-testid="views-add" @click="addView" />
      </nav>

      <div v-if="view" class="rounded-xl border border-line bg-card px-5 min-w-0">
        <ConfigSection id="view-basics" :title="t('views.section.basics')">
          <I18nInput v-model="view.i18n.name as never" class="w-field-md" :label="t('views.name')" :maxlength="255" :invalid="!!err('i18n.name')" />
          <div class="cfg-row">
            <ConfigField :label="t('workflow.key')" for="view-key" width="sm" :hint="t('formconfig.key_hint')" :error="err('key')">
              <InputText id="view-key" v-model="view.key" size="small" class="ltr-value" :invalid="!!err('key')" maxlength="48" />
            </ConfigField>
            <ConfigField :label="t('views.page_size')" for="view-ps" width="xs" :error="err('pageSize')">
              <InputNumber v-model="view.pageSize" input-id="view-ps" :min="5" :max="100" size="small" />
            </ConfigField>
          </div>
        </ConfigSection>

        <ConfigSection id="view-columns" :title="t('views.columns')" :count="view.columns.length">
          <EmptyState v-if="!view.columns.length" icon="pi pi-table" :title="t('views.no_columns')" :description="t('views.no_columns_text')">
            <Button icon="pi pi-plus" :label="t('views.add_column')" size="small" outlined data-testid="view-add-column" @click="addColumn" />
          </EmptyState>
          <template v-else>
            <ConfigItem
              v-for="(c, j) in view.columns"
              :key="c.uuid ?? j"
              :title="c.path.length ? pathLabel(c.path) : t('views.new_column')"
              :subtitle="columnSummary(c)"
              :index="j"
              :count="view.columns.length"
              movable
              :invalid="colInvalid(c, j)"
              :testid="`view-col-${j}`"
              @move="(d) => move(view!.columns, j, d)"
              @remove="view!.columns.splice(j, 1)"
            >
              <ConfigField :label="t('views.path')" :for="`vc-path-${j}`" width="lg" :error="err(`columns.${j}.path`)">
                <PathPicker v-model="c.path" :input-id="`vc-path-${j}`" :tree="tree" :default-locale="dl" :invalid="colInvalid(c, j)" />
              </ConfigField>
              <I18nInput v-model="c.i18n.label as never" class="w-field-md" :label="t('views.label_override')" :maxlength="255" />
              <div class="cfg-row">
                <ConfigField :label="t('views.width')" :for="`vc-w-${j}`" width="xs" :hint="t('views.width_hint')">
                  <InputNumber v-model="c.width" :input-id="`vc-w-${j}`" :min="40" :max="1200" size="small" />
                </ConfigField>
                <ConfigField :label="t('views.pin')" :for="`vc-pin-${j}`" width="sm">
                  <Select v-model="c.pinned" :input-id="`vc-pin-${j}`" :options="pinned" option-label="label" option-value="value" size="small" />
                </ConfigField>
                <ConfigField :label="t('views.total')" :for="`vc-agg-${j}`" width="sm" :error="err(`columns.${j}.aggregate`)">
                  <Select v-model="c.aggregate" :input-id="`vc-agg-${j}`" :options="aggregates" option-label="label" option-value="value" size="small" :invalid="!!err(`columns.${j}.aggregate`)" />
                </ConfigField>
              </div>
              <div class="cfg-stack">
                <SettingSwitch :id="`vc-vis-${j}`" v-model="c.visible" :label="t('views.visible')" :description="t('views.visible_desc')" />
                <SettingSwitch :id="`vc-sort-${j}`" v-model="c.sortable" :label="t('views.sortable')" :description="t('views.sortable_desc')" />
              </div>
            </ConfigItem>
            <Button icon="pi pi-plus" :label="t('views.add_column')" size="small" outlined class="self-start" data-testid="view-add-column" @click="addColumn" />
          </template>
        </ConfigSection>

        <ConfigSection id="view-filters" :title="t('views.filters')" :count="view.filters.length">
          <EmptyState v-if="!view.filters.length" icon="pi pi-filter" :title="t('views.no_filters')" :description="t('views.no_filters_text')">
            <Button icon="pi pi-plus" :label="t('views.add_filter')" size="small" outlined @click="addFilter" />
          </EmptyState>
          <template v-else>
            <ConfigItem
              v-for="(f, j) in view.filters"
              :key="f.uuid ?? j"
              :title="f.path.length ? pathLabel(f.path) : t('views.new_filter')"
              :subtitle="f.quick ? t('views.quick') : undefined"
              :index="j"
              :count="view.filters.length"
              movable
              :invalid="!f.path.length || !!err(`filters.${j}.path`)"
              @move="(d) => move(view!.filters, j, d)"
              @remove="view!.filters.splice(j, 1)"
            >
              <ConfigField :label="t('views.path')" :for="`vf-path-${j}`" width="lg" :error="err(`filters.${j}.path`)">
                <PathPicker v-model="f.path" :input-id="`vf-path-${j}`" :tree="tree" :default-locale="dl" :invalid="!f.path.length || !!err(`filters.${j}.path`)" />
              </ConfigField>
              <I18nInput v-model="f.i18n.label as never" class="w-field-md" :label="t('views.label_override')" :maxlength="255" />
              <SettingSwitch :id="`vf-quick-${j}`" v-model="f.quick" :label="t('views.quick')" :description="t('views.quick_desc')" />
            </ConfigItem>
            <Button icon="pi pi-plus" :label="t('views.add_filter')" size="small" outlined class="self-start" @click="addFilter" />
          </template>
        </ConfigSection>

        <ConfigSection id="view-sort" :title="t('views.default_sort')" :count="view.defaultSort.length">
          <EmptyState v-if="!view.defaultSort.length" icon="pi pi-sort-alt" :title="t('views.no_sort')" :description="t('views.no_sort_text')">
            <Button icon="pi pi-plus" :label="t('views.add_sort')" size="small" outlined @click="view.defaultSort.push({ path: [], dir: 'asc' })" />
          </EmptyState>
          <template v-else>
            <div v-for="(s, j) in view.defaultSort" :key="j" class="cfg-row items-end">
              <ConfigField :label="j === 0 ? t('views.sort_by') : t('views.then_by')" :for="`vs-path-${j}`" width="lg" :error="err(`defaultSort.${j}`)">
                <PathPicker v-model="s.path" :input-id="`vs-path-${j}`" :tree="tree" :default-locale="dl" :invalid="!!err(`defaultSort.${j}`)" />
              </ConfigField>
              <ConfigField :label="t('views.direction')" :for="`vs-dir-${j}`" width="sm">
                <Select v-model="s.dir" :input-id="`vs-dir-${j}`" :options="dirs" option-label="label" option-value="value" size="small" />
              </ConfigField>
              <Button icon="pi pi-trash" text rounded severity="danger" size="small" :aria-label="t('workflow.remove')" @click="view.defaultSort.splice(j, 1)" />
            </div>
            <Button icon="pi pi-plus" :label="t('views.add_sort')" size="small" outlined class="self-start" @click="view.defaultSort.push({ path: [], dir: 'asc' })" />
          </template>
        </ConfigSection>

        <ConfigSection id="view-usage" :title="t('views.section.usage')" :description="t('views.who_hint')">
          <div class="cfg-stack">
            <SettingSwitch id="view-default" :model-value="view.default" :label="t('views.default')" :description="t('views.default_desc')" @update:model-value="setDefault(current)" />
            <SettingSwitch id="view-queues" v-model="view.includeInQueues" :label="t('views.in_queues')" :description="t('views.in_queues_desc')" />
          </div>
        </ConfigSection>

        <ConfigSection id="view-user-options" :title="t('views.section.user_options')" :default-open="false">
          <div class="cfg-stack">
            <SettingSwitch id="view-chooser" v-model="view.columnChooser" :label="t('views.column_chooser')" :description="t('views.column_chooser_desc')" />
            <SettingSwitch id="view-search" v-model="view.globalSearch" :label="t('views.global_search')" :description="t('views.global_search_desc')" />
            <SettingSwitch id="view-totals" v-model="view.showTotals" :label="t('views.show_totals')" :description="t('views.show_totals_desc')" />
          </div>
        </ConfigSection>

        <ConfigSection id="view-row-actions" :title="t('views.row_options')" :description="t('views.row_options_desc')" :default-open="false">
          <div class="cfg-stack">
            <SettingSwitch
              v-for="o in ['view', 'edit', 'log'] as const"
              :id="`view-row-${o}`"
              :key="o"
              v-model="view.rowOptions[o]"
              :label="t(`views.row.${o}`)"
              :description="t(`views.row_desc.${o}`)"
            />
          </div>
        </ConfigSection>

        <ConfigSection id="view-manage" :title="t('views.section.manage')" :default-open="false">
          <div class="flex flex-wrap gap-2">
            <Button
              v-if="canBlueprint"
              icon="pi pi-clone"
              :label="t('views.save_blueprint')"
              outlined
              size="small"
              :disabled="doc.dirty.value || !savedUuids.has(view.uuid)"
              data-testid="view-save-blueprint"
              @click="openBlueprint"
            />
            <Button icon="pi pi-trash" :label="t('views.remove')" outlined severity="danger" size="small" @click="removeView(current)" />
          </div>
        </ConfigSection>
      </div>
    </div>

    <ErrorList :errors="doc.errors.value" />
    <ConfigSaveBar :dirty="doc.dirty.value" :saving="doc.saving.value" testid="views" @save="doc.save()" @discard="doc.discard()" />

    <Dialog :visible="!!blueprint" modal :header="t('views.save_blueprint')" class="w-full max-w-lg" @update:visible="(v) => !v && (blueprint = null)">
      <form v-if="blueprint" class="flex flex-col gap-4" @submit.prevent="saveBlueprint">
        <LocaleFields v-model="blueprint.names" :label="t('views.name')" field="name" :errors="blueprintErrors" id-prefix="vbp-name" />
        <ConfigField :label="t('views.blueprint_category')" for="vbp-cat" width="sm" :error="blueprintErrors.category">
          <InputText id="vbp-cat" v-model="blueprint.category" class="ltr-value" maxlength="64" :invalid="!!blueprintErrors.category" />
        </ConfigField>
        <div class="flex justify-end gap-2">
          <Button type="button" :label="t('workflow.cancel')" text @click="blueprint = null" />
          <Button type="submit" :label="t('workflow.save')" icon="pi pi-check" data-testid="vbp-save" />
        </div>
      </form>
    </Dialog>
  </div>
</template>
