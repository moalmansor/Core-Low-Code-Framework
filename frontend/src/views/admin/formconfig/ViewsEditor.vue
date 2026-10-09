<script setup lang="ts">
import Button from 'primevue/button'
import InputNumber from 'primevue/inputnumber'
import InputText from 'primevue/inputtext'
import Message from 'primevue/message'
import Select from 'primevue/select'
import ToggleSwitch from 'primevue/toggleswitch'
import { computed, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import I18nInput from '@/builder/I18nInput.vue'
import { useSession } from '@/stores/session'
import { labelOf, newUuid, viewsApi, type Path, type PathNode, type ViewColumnDoc, type ViewDoc } from './api'
import ErrorList from './ErrorList.vue'
import PathPicker from './PathPicker.vue'
import SaveBar from './SaveBar.vue'
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
  return path.map((_, i) => {
    const n = nodeAt(path.slice(0, i + 1))
    return n ? labelOf(n.label, locale.value, n.key, dl.value) : path[i]
  }).join(' › ')
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
const err = (path: string) => doc.errors.value[`views.${current.value}.${path}`]
const colInvalid = (c: ViewColumnDoc, j: number) => !c.path.length || !!err(`columns.${j}.path`)
</script>

<template>
  <div class="flex flex-col gap-3" data-testid="views-editor">
    <SaveBar :dirty="doc.dirty.value" :saving="doc.saving.value" :add-label="t('views.add')" :add-disabled="!published" testid="views" @add="addView" @save="doc.save()" />
    <Message v-if="!published" severity="warn" :closable="false">{{ t('views.publish_first') }}</Message>
    <Message severity="secondary" :closable="false" class="text-sm">{{ t('views.hint') }}</Message>
    <div v-if="doc.value.value?.length" class="grid gap-3 lg:grid-cols-[16rem_minmax(0,1fr)]">
      <nav class="flex flex-col gap-1" :aria-label="t('views.list')">
        <button
          v-for="(v, i) in doc.value.value"
          :key="v.uuid"
          type="button"
          class="text-start rounded-md px-3 py-2 border border-line"
          :class="i === current ? 'bg-primary-subtle font-medium' : ''"
          :data-testid="`view-item-${v.key}`"
          @click="current = i"
        >
          {{ labelOf(v.i18n.name, locale, v.key, dl) }}
          <span v-if="v.default" class="text-xs text-muted-color">· {{ t('views.default') }}</span>
        </button>
      </nav>
      <section v-if="view" class="flex flex-col gap-3 min-w-0">
        <div class="grid gap-3 md:grid-cols-2">
          <I18nInput v-model="view.i18n.name as never" :label="t('views.name')" :maxlength="255" :invalid="!!err('i18n.name')" />
          <div class="field">
            <label for="view-key">{{ t('workflow.key') }}</label>
            <InputText id="view-key" v-model="view.key" size="small" class="ltr-value" :invalid="!!err('key')" maxlength="48" />
          </div>
        </div>
        <div class="flex flex-wrap items-end gap-4">
          <div class="field w-32">
            <label for="view-ps">{{ t('views.page_size') }}</label>
            <InputNumber v-model="view.pageSize" input-id="view-ps" :min="5" :max="100" size="small" />
          </div>
          <label class="flex items-center gap-2 text-sm"><ToggleSwitch :model-value="view.default" @update:model-value="setDefault(current)" />{{ t('views.default') }}</label>
          <label class="flex items-center gap-2 text-sm"><ToggleSwitch v-model="view.showTotals" />{{ t('views.show_totals') }}</label>
          <label class="flex items-center gap-2 text-sm"><ToggleSwitch v-model="view.columnChooser" />{{ t('views.column_chooser') }}</label>
          <label class="flex items-center gap-2 text-sm"><ToggleSwitch v-model="view.globalSearch" />{{ t('views.global_search') }}</label>
          <label class="flex items-center gap-2 text-sm"><ToggleSwitch v-model="view.includeInQueues" />{{ t('views.in_queues') }}</label>
        </div>
        <fieldset class="flex flex-wrap gap-4 text-sm">
          <legend class="font-medium mb-1">{{ t('views.row_options') }}</legend>
          <label v-for="o in ['view', 'edit', 'log'] as const" :key="o" class="flex items-center gap-2"><ToggleSwitch v-model="view.rowOptions[o]" />{{ t(`views.row.${o}`) }}</label>
        </fieldset>

        <h3 class="font-semibold">{{ t('views.columns') }}</h3>
        <div v-for="(c, j) in view.columns" :key="c.uuid ?? j" class="rounded-lg border border-line p-2 flex flex-col gap-2" :data-testid="`view-col-${j}`">
          <div class="grid gap-2 md:grid-cols-[minmax(0,2fr)_minmax(0,2fr)_auto] items-end">
            <div class="field min-w-0">
              <label :for="`vc-path-${j}`">{{ t('views.path') }}</label>
              <PathPicker v-model="c.path" :input-id="`vc-path-${j}`" :tree="tree" :default-locale="dl" :invalid="colInvalid(c, j)" />
            </div>
            <I18nInput v-model="c.i18n.label as never" :label="t('views.label_override')" :maxlength="255" />
            <div class="flex gap-1">
              <Button icon="pi pi-arrow-up" text size="small" :aria-label="t('assignment.move_up')" :disabled="j === 0" @click="move(view.columns, j, -1)" />
              <Button icon="pi pi-arrow-down" text size="small" :aria-label="t('assignment.move_down')" :disabled="j === view.columns.length - 1" @click="move(view.columns, j, 1)" />
              <Button icon="pi pi-trash" text severity="danger" size="small" :aria-label="t('workflow.remove')" @click="view.columns.splice(j, 1)" />
            </div>
          </div>
          <div class="flex flex-wrap items-end gap-3">
            <div class="field w-32">
              <label :for="`vc-w-${j}`">{{ t('views.width') }}</label>
              <InputNumber v-model="c.width" :input-id="`vc-w-${j}`" :min="40" :max="1200" size="small" />
            </div>
            <div class="field w-36">
              <label :for="`vc-pin-${j}`">{{ t('views.pin') }}</label>
              <Select v-model="c.pinned" :input-id="`vc-pin-${j}`" :options="pinned" option-label="label" option-value="value" size="small" />
            </div>
            <div class="field w-36">
              <label :for="`vc-agg-${j}`">{{ t('views.total') }}</label>
              <Select v-model="c.aggregate" :input-id="`vc-agg-${j}`" :options="aggregates" option-label="label" option-value="value" size="small" :invalid="!!err(`columns.${j}.aggregate`)" />
            </div>
            <label class="flex items-center gap-2 text-sm pb-2"><ToggleSwitch v-model="c.visible" />{{ t('views.visible') }}</label>
            <label class="flex items-center gap-2 text-sm pb-2"><ToggleSwitch v-model="c.sortable" />{{ t('views.sortable') }}</label>
          </div>
        </div>
        <div><Button icon="pi pi-plus" :label="t('views.add_column')" size="small" outlined data-testid="view-add-column" @click="addColumn" /></div>

        <h3 class="font-semibold">{{ t('views.filters') }}</h3>
        <div v-for="(f, j) in view.filters" :key="f.uuid ?? j" class="grid gap-2 md:grid-cols-[minmax(0,2fr)_minmax(0,2fr)_auto_auto] items-end rounded-lg border border-line p-2">
          <div class="field min-w-0">
            <label :for="`vf-path-${j}`">{{ t('views.path') }}</label>
            <PathPicker v-model="f.path" :input-id="`vf-path-${j}`" :tree="tree" :default-locale="dl" :invalid="!f.path.length || !!err(`filters.${j}.path`)" />
          </div>
          <I18nInput v-model="f.i18n.label as never" :label="t('views.label_override')" :maxlength="255" />
          <label class="flex items-center gap-2 text-sm pb-2"><ToggleSwitch v-model="f.quick" />{{ t('views.quick') }}</label>
          <Button icon="pi pi-trash" text severity="danger" size="small" :aria-label="t('workflow.remove')" @click="view.filters.splice(j, 1)" />
        </div>
        <div><Button icon="pi pi-plus" :label="t('views.add_filter')" size="small" outlined @click="addFilter" /></div>

        <h3 class="font-semibold">{{ t('views.default_sort') }}</h3>
        <div v-for="(s, j) in view.defaultSort" :key="j" class="flex flex-wrap items-end gap-2">
          <div class="field min-w-0 flex-1">
            <label :for="`vs-path-${j}`">{{ t('views.path') }}</label>
            <PathPicker v-model="s.path" :input-id="`vs-path-${j}`" :tree="tree" :default-locale="dl" :invalid="!!err(`defaultSort.${j}`)" />
          </div>
          <Select v-model="s.dir" :options="dirs" option-label="label" option-value="value" size="small" :aria-label="t('views.direction')" />
          <Button icon="pi pi-trash" text severity="danger" size="small" :aria-label="t('workflow.remove')" @click="view.defaultSort.splice(j, 1)" />
        </div>
        <div><Button icon="pi pi-plus" :label="t('views.add_sort')" size="small" outlined @click="view.defaultSort.push({ path: [], dir: 'asc' })" /></div>

        <p class="text-sm text-muted-color">{{ t('views.who_hint') }}</p>
        <p v-if="view.columns.length" class="text-sm text-muted-color">{{ t('views.preview_columns') }}: {{ view.columns.filter((c) => c.path.length).map((c) => pathLabel(c.path)).join(', ') }}</p>
        <div class="flex justify-end">
          <Button icon="pi pi-trash" :label="t('views.remove')" text severity="danger" size="small" @click="removeView(current)" />
        </div>
      </section>
    </div>
    <ErrorList :errors="doc.errors.value" />
  </div>
</template>
