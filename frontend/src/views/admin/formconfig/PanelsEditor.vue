<script setup lang="ts">
import Button from 'primevue/button'
import InputNumber from 'primevue/inputnumber'
import Message from 'primevue/message'
import MultiSelect from 'primevue/multiselect'
import Select from 'primevue/select'
import ToggleSwitch from 'primevue/toggleswitch'
import { computed, onMounted, reactive, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import ExpressionInput from '@/builder/conditions/ExpressionInput.vue'
import { buildScope } from '@/builder/conditions/scope'
import FormPicker from '@/builder/FormPicker.vue'
import I18nInput from '@/builder/I18nInput.vue'
import { useBuilder } from '@/builder/useBuilder'
import { useSession } from '@/stores/session'
import { labelOf, newUuid, panelsApi, viewsApi, type PanelDoc, type Path, type PathNode } from './api'
import ErrorList from './ErrorList.vue'
import PathPicker from './PathPicker.vue'
import SaveBar from './SaveBar.vue'
import { useHashedDocument } from './useHashedDocument'

/**
 * View Mode panels (specification §4.14 "View Mode"): tabs, sections, the
 * form body, related tables and summary widgets of records that link to this
 * one, derived fields read through lookups, the status timeline, comments,
 * attachments and rich text, each with an optional visibility condition.
 * Without panels a record shows its form body.
 */
const props = defineProps<{ form: string }>()
const { t, locale } = useI18n()
const session = useSession()
const builder = useBuilder()
const doc = useHashedDocument<PanelDoc[]>(
  () => panelsApi.load(props.form),
  (v, h) => panelsApi.save(props.form, v, h),
)
const dl = computed(() => session.boot?.default_locale ?? 'en')
const scope = computed(() => (builder.doc ? buildScope(builder.doc, builder.catalog.fields) : { fields: [], repeaters: [], rows: null }))
const types = computed(() =>
  (['tabs', 'tab', 'section', 'form_body', 'related_table', 'summary_widget', 'derived_fields', 'status_timeline', 'comments', 'attachments', 'html'] as const).map((v) => ({
    value: v,
    label: t(`panels.type.${v}`),
  })),
)
const containers = computed(() =>
  [{ value: null as string | null, label: t('panels.top_level') }].concat(
    (doc.value.value ?? []).filter((p) => ['tabs', 'tab', 'section'].includes(p.type)).map((p) => ({ value: p.uuid, label: `${t(`panels.type.${p.type}`)} · ${labelOf(p.i18n.title, locale.value, p.uuid.slice(0, 8), dl.value)}` })),
  ),
)
const groups = computed(() => (builder.doc?.groups ?? []).map((g) => ({ value: g.key, label: labelOf(g.i18n?.title ?? {}, locale.value, g.key, dl.value) })))
const aggregates = computed(() => (['count', 'sum', 'avg', 'min', 'max'] as const).map((v) => ({ value: v, label: t(`views.aggregate.${v}`) })))

// Field trees: this form's (derived fields) and each source form's (related tables).
const ownTree = ref<PathNode[]>([])
const trees = reactive<Record<string, PathNode[]>>({})
onMounted(async () => {
  ownTree.value = ((await viewsApi.load(props.form).catch(() => null))?.extra.fields as PathNode[] | undefined) ?? []
})
async function loadTree(source: string | null | undefined): Promise<void> {
  if (!source || trees[source]) return
  trees[source] = ((await viewsApi.load(source).catch(() => null))?.extra.fields as PathNode[] | undefined) ?? []
}
watch(
  () => (doc.value.value ?? []).map((p) => p.config.source as string | undefined),
  (sources) => sources.forEach((s) => void loadTree(s)),
  { immediate: true },
)
/** Lookups of the source form that point at this form: the link a related table follows. */
function links(source: unknown): { value: string; label: string }[] {
  if (typeof source !== 'string') return []
  return (trees[source] ?? []).filter((n) => n.form?.uuid === props.form).map((n) => ({ value: n.key, label: labelOf(n.label, locale.value, n.key, dl.value) }))
}
function numberFields(source: unknown): { value: string; label: string }[] {
  if (typeof source !== 'string') return []
  return (trees[source] ?? [])
    .filter((n) => ['number', 'decimal', 'currency', 'percent', 'rating', 'slider'].includes(n.type))
    .map((n) => ({ value: n.key, label: labelOf(n.label, locale.value, n.key, dl.value) }))
}

function add(): void {
  const list = doc.value.value
  if (!list) return
  list.push({ uuid: newUuid(), parent: null, type: 'section', i18n: { title: {}, content: {} }, config: { columns: 1, collapsible: false }, visibility: null, order: list.length })
}
function setType(p: PanelDoc, type: PanelDoc['type']): void {
  p.type = type
  p.config =
    type === 'derived_fields'
      ? { paths: [] }
      : type === 'related_table'
        ? { source: null, via: null, columns: [], limit: 20 }
        : type === 'summary_widget'
          ? { source: null, via: null, aggregate: 'count', field: null }
          : type === 'form_body'
            ? { groups: [] }
            : ['tabs', 'tab', 'section'].includes(type)
              ? { columns: 1, collapsible: false }
              : {}
}
function remove(i: number): void {
  const list = doc.value.value!
  const gone = list[i]!.uuid
  list.splice(i, 1)
  list.forEach((p) => p.parent === gone && (p.parent = null))
}
function move(i: number, d: -1 | 1): void {
  const list = doc.value.value!
  const j = i + d
  if (j < 0 || j >= list.length) return
  ;[list[i], list[j]] = [list[j]!, list[i]!]
  list.forEach((p, k) => (p.order = k))
}
const paths = (p: PanelDoc, key: 'paths' | 'columns') => (p.config[key] as Path[] | undefined) ?? []
const err = (i: number, path: string) => doc.errors.value[`panels.${i}.${path}`]
</script>

<template>
  <div class="flex flex-col gap-3" data-testid="panels-editor">
    <SaveBar :dirty="doc.dirty.value" :saving="doc.saving.value" :add-label="t('panels.add')" testid="panels" @add="add" @save="doc.save()" />
    <Message severity="secondary" :closable="false" class="text-sm">{{ t('panels.hint') }}</Message>
    <section v-for="(p, i) in doc.value.value ?? []" :key="p.uuid" class="rounded-lg border border-line p-3 flex flex-col gap-2" :data-testid="`panel-${i}`">
      <div class="grid gap-2 md:grid-cols-[minmax(0,1fr)_minmax(0,1fr)_minmax(0,2fr)_auto] items-end">
        <div class="field">
          <label :for="`pn-type-${i}`">{{ t('panels.type_label') }}</label>
          <Select :model-value="p.type" :input-id="`pn-type-${i}`" :options="types" option-label="label" option-value="value" size="small" @update:model-value="(v) => setType(p, v)" />
        </div>
        <div class="field">
          <label :for="`pn-parent-${i}`">{{ t('panels.parent') }}</label>
          <Select
            v-model="p.parent"
            :input-id="`pn-parent-${i}`"
            :options="containers.filter((c) => c.value !== p.uuid)"
            option-label="label"
            option-value="value"
            size="small"
            :invalid="!!err(i, 'parent')"
          />
        </div>
        <I18nInput v-model="p.i18n.title as never" :label="t('panels.title')" :maxlength="255" />
        <div class="flex gap-1">
          <Button icon="pi pi-arrow-up" text size="small" :aria-label="t('assignment.move_up')" :disabled="i === 0" @click="move(i, -1)" />
          <Button icon="pi pi-arrow-down" text size="small" :aria-label="t('assignment.move_down')" :disabled="i === (doc.value.value?.length ?? 0) - 1" @click="move(i, 1)" />
          <Button icon="pi pi-trash" text severity="danger" size="small" :aria-label="t('workflow.remove')" @click="remove(i)" />
        </div>
      </div>

      <div v-if="['tabs', 'tab', 'section'].includes(p.type)" class="flex flex-wrap items-end gap-4">
        <div class="field w-32">
          <label :for="`pn-cols-${i}`">{{ t('panels.columns') }}</label>
          <InputNumber v-model="p.config.columns as number" :input-id="`pn-cols-${i}`" :min="1" :max="3" size="small" />
        </div>
        <label class="flex items-center gap-2 text-sm pb-2"><ToggleSwitch v-model="p.config.collapsible as boolean" />{{ t('panels.collapsible') }}</label>
      </div>

      <div v-else-if="p.type === 'form_body'" class="field">
        <label :for="`pn-groups-${i}`">{{ t('panels.groups') }}</label>
        <MultiSelect v-model="p.config.groups as string[]" :input-id="`pn-groups-${i}`" :options="groups" option-label="label" option-value="value" :placeholder="t('panels.all_groups')" size="small" display="chip" />
      </div>

      <div v-else-if="p.type === 'derived_fields'" class="flex flex-col gap-2">
        <span class="text-sm font-medium">{{ t('panels.paths') }}</span>
        <div v-for="(_, j) in paths(p, 'paths')" :key="j" class="flex items-end gap-2">
          <PathPicker v-model="(p.config.paths as Path[])[j]" :tree="ownTree" :default-locale="dl" class="flex-1" :invalid="!!err(i, `config.paths.${j}`)" />
          <Button icon="pi pi-trash" text severity="danger" size="small" :aria-label="t('workflow.remove')" @click="(p.config.paths as Path[]).splice(j, 1)" />
        </div>
        <div><Button icon="pi pi-plus" :label="t('panels.add_path')" size="small" outlined @click="(p.config.paths as Path[]).push([])" /></div>
        <small v-if="err(i, 'config.paths')" class="text-danger">{{ err(i, 'config.paths') }}</small>
      </div>

      <div v-else-if="p.type === 'related_table' || p.type === 'summary_widget'" class="flex flex-col gap-2">
        <div class="grid gap-2 md:grid-cols-2">
          <div class="field">
            <label :for="`pn-src-${i}`">{{ t('panels.source') }}</label>
            <FormPicker v-model="p.config.source as string | null" kind="form" :input-id="`pn-src-${i}`" @update:model-value="p.config.via = null" />
          </div>
          <div class="field">
            <label :for="`pn-via-${i}`">{{ t('panels.via') }}</label>
            <Select v-model="p.config.via as string | null" :input-id="`pn-via-${i}`" :options="links(p.config.source)" option-label="label" option-value="value" size="small" :invalid="!!err(i, 'config.via')" :empty-message="t('panels.no_links')" />
          </div>
        </div>
        <template v-if="p.type === 'related_table'">
          <span class="text-sm font-medium">{{ t('panels.table_columns') }}</span>
          <div v-for="(_, j) in paths(p, 'columns')" :key="j" class="flex items-end gap-2">
            <PathPicker v-model="(p.config.columns as Path[])[j]" :tree="trees[p.config.source as string] ?? []" :default-locale="dl" class="flex-1" :invalid="!!err(i, `config.columns.${j}`)" />
            <Button icon="pi pi-trash" text severity="danger" size="small" :aria-label="t('workflow.remove')" @click="(p.config.columns as Path[]).splice(j, 1)" />
          </div>
          <div class="flex flex-wrap items-end gap-3">
            <Button icon="pi pi-plus" :label="t('views.add_column')" size="small" outlined :disabled="!p.config.source" @click="(p.config.columns as Path[]).push([])" />
            <div class="field w-32">
              <label :for="`pn-limit-${i}`">{{ t('panels.limit') }}</label>
              <InputNumber v-model="p.config.limit as number" :input-id="`pn-limit-${i}`" :min="1" :max="100" size="small" />
            </div>
          </div>
        </template>
        <div v-else class="grid gap-2 md:grid-cols-2">
          <div class="field">
            <label :for="`pn-agg-${i}`">{{ t('views.total') }}</label>
            <Select v-model="p.config.aggregate as string" :input-id="`pn-agg-${i}`" :options="aggregates" option-label="label" option-value="value" size="small" :invalid="!!err(i, 'config.aggregate')" />
          </div>
          <div v-if="p.config.aggregate !== 'count'" class="field">
            <label :for="`pn-fld-${i}`">{{ t('panels.number_field') }}</label>
            <Select v-model="p.config.field as string | null" :input-id="`pn-fld-${i}`" :options="numberFields(p.config.source)" option-label="label" option-value="value" size="small" />
          </div>
        </div>
      </div>

      <I18nInput v-else-if="p.type === 'html'" v-model="p.i18n.content as never" :label="t('panels.content')" multiline :rows="6" :maxlength="50000" />

      <ExpressionInput v-model="p.visibility" :scope="scope" expected="boolean" :label="t('panels.visibility')" allow-empty />
      <small v-if="err(i, 'visibility')" class="text-danger">{{ err(i, 'visibility') }}</small>
    </section>
    <ErrorList :errors="doc.errors.value" />
  </div>
</template>
