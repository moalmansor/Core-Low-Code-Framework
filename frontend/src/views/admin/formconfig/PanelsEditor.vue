<script setup lang="ts">
import Button from 'primevue/button'
import InputNumber from 'primevue/inputnumber'
import MultiSelect from 'primevue/multiselect'
import Select from 'primevue/select'
import { computed, onMounted, reactive, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import ExpressionInput from '@/builder/conditions/ExpressionInput.vue'
import { buildScope } from '@/builder/conditions/scope'
import FormPicker from '@/builder/FormPicker.vue'
import I18nInput from '@/builder/I18nInput.vue'
import { useBuilder } from '@/builder/useBuilder'
import { useSession } from '@/stores/session'
import ConfigField from '@/components/config/ConfigField.vue'
import ConfigItem from '@/components/config/ConfigItem.vue'
import ConfigSaveBar from '@/components/config/ConfigSaveBar.vue'
import ConfigSection from '@/components/config/ConfigSection.vue'
import EmptyState from '@/components/config/EmptyState.vue'
import SettingSwitch from '@/components/config/SettingSwitch.vue'
import TabIntro from '@/components/config/TabIntro.vue'
import { labelOf, newUuid, panelsApi, viewsApi, type PanelDoc, type Path, type PathNode } from './api'
import ErrorList from './ErrorList.vue'
import PathPicker from './PathPicker.vue'
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
    (doc.value.value ?? [])
      .filter((p) => ['tabs', 'tab', 'section'].includes(p.type))
      .map((p) => ({ value: p.uuid, label: `${t(`panels.type.${p.type}`)} · ${labelOf(p.i18n.title, locale.value, p.uuid.slice(0, 8), dl.value)}` })),
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
/** How deep a panel sits in the tabs/sections tree, to indent the list like the record page. */
function depth(p: PanelDoc): number {
  const byUuid = new Map((doc.value.value ?? []).map((x) => [x.uuid, x]))
  let d = 0
  for (let cur = p.parent ? byUuid.get(p.parent) : undefined; cur && d < 10; cur = cur.parent ? byUuid.get(cur.parent) : undefined) d++
  return d
}
const titleOf = (p: PanelDoc) => labelOf(p.i18n.title, locale.value, '', dl.value) || t(`panels.type.${p.type}`)
const subtitleOf = (p: PanelDoc) => {
  const parent = (doc.value.value ?? []).find((x) => x.uuid === p.parent)
  return [t(`panels.type.${p.type}`), parent ? t('panels.inside', { name: titleOf(parent) }) : null, p.visibility ? t('panels.conditional') : null].filter(Boolean).join(' · ')
}
const hasContent = (p: PanelDoc) => ['tabs', 'tab', 'section', 'form_body', 'derived_fields', 'related_table', 'summary_widget', 'html'].includes(p.type)
</script>

<template>
  <div class="flex flex-col gap-6" data-testid="panels-editor">
    <TabIntro :title="t('formconfig.tab.panels')" :text="t('panels.hint')" />

    <EmptyState v-if="doc.value.value && !doc.value.value.length" icon="pi pi-objects-column" :title="t('panels.empty_title')" :description="t('panels.empty_text')" testid="panels-empty">
      <Button icon="pi pi-plus" :label="t('panels.add')" size="small" data-testid="panels-add" @click="add" />
    </EmptyState>

    <template v-else-if="doc.value.value">
      <ConfigItem
        v-for="(p, i) in doc.value.value"
        :key="p.uuid"
        :title="titleOf(p)"
        :subtitle="subtitleOf(p)"
        :index="i"
        :count="doc.value.value.length"
        movable
        :invalid="Object.keys(doc.errors.value).some((k) => k.startsWith(`panels.${i}.`))"
        :style="{ marginInlineStart: `${depth(p) * 1.5}rem` }"
        :testid="`panel-${i}`"
        @move="(d) => move(i, d)"
        @remove="remove(i)"
      >
        <ConfigSection id="panel-basics" :title="t('views.section.basics')">
          <div class="cfg-row">
            <ConfigField :label="t('panels.type_label')" :for="`pn-type-${i}`" width="md">
              <Select :model-value="p.type" :input-id="`pn-type-${i}`" :options="types" option-label="label" option-value="value" size="small" @update:model-value="(v) => setType(p, v)" />
            </ConfigField>
            <ConfigField :label="t('panels.parent')" :for="`pn-parent-${i}`" width="md" :error="err(i, 'parent')">
              <Select
                v-model="p.parent"
                :input-id="`pn-parent-${i}`"
                :options="containers.filter((c) => c.value !== p.uuid)"
                option-label="label"
                option-value="value"
                size="small"
                :invalid="!!err(i, 'parent')"
              />
            </ConfigField>
          </div>
          <I18nInput v-model="p.i18n.title as never" class="w-field-md" :label="t('panels.title')" :maxlength="255" />
        </ConfigSection>

        <ConfigSection v-if="hasContent(p)" id="panel-content" :title="t('panels.section.content')">
          <template v-if="['tabs', 'tab', 'section'].includes(p.type)">
            <ConfigField :label="t('panels.columns')" :for="`pn-cols-${i}`" width="xs" :hint="t('panels.columns_hint')">
              <InputNumber v-model="p.config.columns as number" :input-id="`pn-cols-${i}`" :min="1" :max="3" size="small" />
            </ConfigField>
            <SettingSwitch :id="`pn-coll-${i}`" v-model="p.config.collapsible as boolean" :label="t('panels.collapsible')" :description="t('panels.collapsible_desc')" />
          </template>

          <ConfigField v-else-if="p.type === 'form_body'" :label="t('panels.groups')" :for="`pn-groups-${i}`" width="lg" :hint="t('panels.groups_hint')">
            <MultiSelect
              v-model="p.config.groups as string[]"
              :input-id="`pn-groups-${i}`"
              :options="groups"
              option-label="label"
              option-value="value"
              :placeholder="t('panels.all_groups')"
              size="small"
              display="chip"
            />
          </ConfigField>

          <template v-else-if="p.type === 'derived_fields'">
            <EmptyState v-if="!paths(p, 'paths').length" icon="pi pi-link" :title="t('panels.no_paths')" :description="t('panels.no_paths_text')">
              <Button icon="pi pi-plus" :label="t('panels.add_path')" size="small" outlined @click="(p.config.paths as Path[]).push([])" />
            </EmptyState>
            <template v-else>
              <div v-for="(_, j) in paths(p, 'paths')" :key="j" class="cfg-row items-end">
                <ConfigField :label="t('views.path')" :for="`pn-path-${i}-${j}`" width="lg" :error="err(i, `config.paths.${j}`)">
                  <PathPicker v-model="(p.config.paths as Path[])[j]" :input-id="`pn-path-${i}-${j}`" :tree="ownTree" :default-locale="dl" :invalid="!!err(i, `config.paths.${j}`)" />
                </ConfigField>
                <Button icon="pi pi-trash" text rounded severity="danger" size="small" :aria-label="t('workflow.remove')" @click="(p.config.paths as Path[]).splice(j, 1)" />
              </div>
              <Button icon="pi pi-plus" :label="t('panels.add_path')" size="small" outlined class="self-start" @click="(p.config.paths as Path[]).push([])" />
            </template>
            <small v-if="err(i, 'config.paths')" class="field-error">{{ err(i, 'config.paths') }}</small>
          </template>

          <template v-else-if="p.type === 'related_table' || p.type === 'summary_widget'">
            <div class="cfg-row">
              <ConfigField :label="t('panels.source')" :for="`pn-src-${i}`" width="md">
                <FormPicker v-model="p.config.source as string | null" kind="form" :input-id="`pn-src-${i}`" @update:model-value="p.config.via = null" />
              </ConfigField>
              <ConfigField :label="t('panels.via')" :for="`pn-via-${i}`" width="md" :hint="t('panels.via_hint')" :error="err(i, 'config.via')">
                <Select
                  v-model="p.config.via as string | null"
                  :input-id="`pn-via-${i}`"
                  :options="links(p.config.source)"
                  option-label="label"
                  option-value="value"
                  size="small"
                  :invalid="!!err(i, 'config.via')"
                  :empty-message="t('panels.no_links')"
                />
              </ConfigField>
            </div>
            <template v-if="p.type === 'related_table'">
              <ConfigField :label="t('panels.limit')" :for="`pn-limit-${i}`" width="xs">
                <InputNumber v-model="p.config.limit as number" :input-id="`pn-limit-${i}`" :min="1" :max="100" size="small" />
              </ConfigField>
              <span class="text-sm font-medium">{{ t('panels.table_columns') }}</span>
              <EmptyState v-if="!paths(p, 'columns').length" icon="pi pi-table" :title="t('panels.no_table_columns')" :description="t('panels.no_table_columns_text')">
                <Button icon="pi pi-plus" :label="t('views.add_column')" size="small" outlined :disabled="!p.config.source" @click="(p.config.columns as Path[]).push([])" />
              </EmptyState>
              <template v-else>
                <div v-for="(_, j) in paths(p, 'columns')" :key="j" class="cfg-row items-end">
                  <ConfigField :label="t('views.path')" :for="`pn-col-${i}-${j}`" width="lg" :error="err(i, `config.columns.${j}`)">
                    <PathPicker
                      v-model="(p.config.columns as Path[])[j]"
                      :input-id="`pn-col-${i}-${j}`"
                      :tree="trees[p.config.source as string] ?? []"
                      :default-locale="dl"
                      :invalid="!!err(i, `config.columns.${j}`)"
                    />
                  </ConfigField>
                  <Button icon="pi pi-trash" text rounded severity="danger" size="small" :aria-label="t('workflow.remove')" @click="(p.config.columns as Path[]).splice(j, 1)" />
                </div>
                <Button icon="pi pi-plus" :label="t('views.add_column')" size="small" outlined class="self-start" :disabled="!p.config.source" @click="(p.config.columns as Path[]).push([])" />
              </template>
            </template>
            <div v-else class="cfg-row">
              <ConfigField :label="t('views.total')" :for="`pn-agg-${i}`" width="sm" :error="err(i, 'config.aggregate')">
                <Select
                  v-model="p.config.aggregate as string"
                  :input-id="`pn-agg-${i}`"
                  :options="aggregates"
                  option-label="label"
                  option-value="value"
                  size="small"
                  :invalid="!!err(i, 'config.aggregate')"
                />
              </ConfigField>
              <ConfigField v-if="p.config.aggregate !== 'count'" :label="t('panels.number_field')" :for="`pn-fld-${i}`" width="md">
                <Select v-model="p.config.field as string | null" :input-id="`pn-fld-${i}`" :options="numberFields(p.config.source)" option-label="label" option-value="value" size="small" />
              </ConfigField>
            </div>
          </template>

          <I18nInput v-else-if="p.type === 'html'" v-model="p.i18n.content as never" :label="t('panels.content')" multiline :rows="6" :maxlength="50000" />
        </ConfigSection>

        <ConfigSection id="panel-visibility" :title="t('panels.visibility')" :description="t('panels.visibility_desc')" :default-open="false">
          <ExpressionInput v-model="p.visibility" :scope="scope" expected="boolean" :label="t('panels.visibility')" allow-empty />
          <small v-if="err(i, 'visibility')" class="field-error">{{ err(i, 'visibility') }}</small>
        </ConfigSection>
      </ConfigItem>
      <Button icon="pi pi-plus" :label="t('panels.add')" size="small" outlined class="self-start" data-testid="panels-add" @click="add" />
    </template>

    <ErrorList :errors="doc.errors.value" />
    <ConfigSaveBar :dirty="doc.dirty.value" :saving="doc.saving.value" testid="panels" @save="doc.save()" @discard="doc.discard()" />
  </div>
</template>
