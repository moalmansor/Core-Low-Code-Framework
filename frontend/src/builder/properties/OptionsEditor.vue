<script setup lang="ts">
import Button from 'primevue/button'
import Checkbox from 'primevue/checkbox'
import InputNumber from 'primevue/inputnumber'
import InputText from 'primevue/inputtext'
import MultiSelect from 'primevue/multiselect'
import Select from 'primevue/select'
import ToggleSwitch from 'primevue/toggleswitch'
import { computed, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import RuleBuilder from '../conditions/RuleBuilder.vue'
import { pick, staticType, type ExpressionScope } from '../conditions/scope'
import FieldSelect from '../FieldSelect.vue'
import FormPicker from '../FormPicker.vue'
import { targetFields, type TargetField } from '../targets'
import type { FieldDef, FieldTypeInfo, OptionsDef, OptionSource, StaticOption } from '../types'
import { useBuilder } from '../useBuilder'
import { newUuid } from '../uuid'
import RelationEditor from './RelationEditor.vue'
import { PICKER_FALLBACK } from '@/theme/color'

/** Options of a choice field (specification §4.6 "Options", architecture §14.5). */
const props = defineProps<{ info: FieldTypeInfo }>()
const field = defineModel<FieldDef>('field', { required: true })
const { t } = useI18n()
const builder = useBuilder()

watch(
  field,
  (f) => {
    if (!f.options) f.options = { source: 'static', static: [] }
    else if (f.options.source === 'static' && !f.options.static) f.options.static = []
  },
  { immediate: true },
)
const options = computed<OptionsDef>(() => field.value.options ?? { source: 'static', static: [] })
const SOURCES: OptionSource[] = ['static', 'collection', 'form', 'query', 'users', 'roles', 'departments']
const sources = computed(() => SOURCES.map((s) => ({ value: s, label: t(`builder.options.source_${s}`) })))

function setSource(source: OptionSource): void {
  const o = options.value
  o.source = source
  if (source === 'static') o.static = o.static ?? []
  if (source === 'query') o.query = o.query ?? null
  if (source !== 'collection' && source !== 'form') o.collection = null
  else if (field.value.relation) o.collection = builder.doc?.relations.find((r) => r.uuid === field.value.relation)?.target ?? null
}

// ---------------------------------------------------------------- static list

const statics = computed<StaticOption[]>(() => options.value.static ?? [])
function addOption(): void {
  const used = new Set(statics.value.map((o) => o.value))
  let n = statics.value.length + 1
  while (used.has(`option_${n}`)) n++
  ;(options.value.static ??= []).push({ uuid: newUuid(), value: `option_${n}`, active: true, default: false, order: statics.value.length, i18n: { label: {} } })
}
function moveOption(i: number, delta: -1 | 1): void {
  const j = i + delta
  if (j < 0 || j >= statics.value.length) return
  const list = statics.value
  const [item] = list.splice(i, 1)
  list.splice(j, 0, item!)
  list.forEach((o, n) => (o.order = n))
}
function removeOption(i: number): void {
  const removed = statics.value[i]!
  builder.mutate((d) => {
    const f = d.fields.find((x) => x.uuid === field.value.uuid)
    if (!f?.options?.static) return
    f.options.static = f.options.static.filter((o) => o.uuid !== removed.uuid)
    f.options.static.forEach((o, n) => (o.order = n))
    d.conditions = d.conditions.filter((c) => !(c.owner.type === 'option' && c.owner.uuid === removed.uuid))
    for (const c of d.conditions) {
      c.effects = c.effects.filter((e) => e.target?.uuid !== removed.uuid)
      if (c.else) c.else = c.else.filter((e) => e.target?.uuid !== removed.uuid)
    }
  })
}
function setLabel(o: StaticOption, code: string, value: string | undefined): void {
  o.i18n ??= {}
  const label = { ...(o.i18n.label ?? {}) }
  if (value) label[code] = value
  else delete label[code]
  o.i18n.label = label
}
const duplicateValues = computed(() => {
  const seen = new Set<string>()
  const dup = new Set<string>()
  for (const o of statics.value) (seen.has(o.value) ? dup : seen).add(o.value)
  return dup
})

// ---------------------------------------------------------------- record sources

const target = computed(() => (options.value.source === 'query' ? (options.value.query?.from ?? null) : (options.value.collection ?? null)))
const sourceFields = ref<TargetField[]>([])
watch(
  target,
  async (uuid) => {
    sourceFields.value = uuid ? await targetFields(uuid).catch(() => []) : []
  },
  { immediate: true },
)
const keyOptions = computed(() => sourceFields.value.map((f) => ({ value: f.key, label: `${pick(f.label, builder.locale, f.key)} (${f.key})` })))
function pathModel(key: 'valuePath' | 'labelPath' | 'dependsPath' | 'groupBy') {
  return computed({
    get: () => options.value[key]?.[0] ?? null,
    set: (v: string | null) => (options.value[key] = v ? [v] : null),
  })
}
const valuePath = pathModel('valuePath')
const labelPath = pathModel('labelPath')
const dependsPath = pathModel('dependsPath')
const groupBy = pathModel('groupBy')
const preview = computed({
  get: () => (options.value.preview ?? []).map((p) => p[0]!),
  set: (v: string[]) => (options.value.preview = v.map((k) => [k])),
})

// ---------------------------------------------------------------- query

function setQueryFrom(uuid: string | null | undefined): void {
  options.value.query = uuid ? { from: uuid, where: null, sort: [], limit: 100 } : null
}
const queryScope = computed<ExpressionScope>(() => ({
  fields: sourceFields.value.map((f) => ({ uuid: f.uuid, key: f.key, label: f.label, type: f.type, valueType: staticType(builder.typeInfo(f.type)), repeater: null, options: [] })),
  repeaters: [],
  rows: null,
}))

const multiple = computed(() => props.info.multiple)
const staticValues = computed(() => statics.value.map((o) => ({ value: o.value, label: `${pick(o.i18n?.label, builder.locale, o.value)} (${o.value})` })))
const defaultsText = computed({
  get: () => (options.value.defaults ?? []).join(', '),
  set: (v: string) =>
    (options.value.defaults = v
      .split(',')
      .map((s) => s.trim())
      .filter((s) => s !== '')),
})
</script>

<template>
  <div class="flex flex-col gap-3">
    <label class="field"
      ><span>{{ t('builder.options.source') }}</span>
      <Select :model-value="options.source" :options="sources" option-label="label" option-value="value" size="small" data-testid="options-source" @update:model-value="setSource" />
    </label>

    <!-- Static list -->
    <div v-if="options.source === 'static'" class="flex flex-col gap-2">
      <div v-for="(o, i) in statics" :key="o.uuid" class="rounded border border-line p-2 flex flex-col gap-1" :data-testid="`option-${i}`">
        <div class="flex items-center gap-1">
          <InputText
            v-model="o.value"
            size="small"
            class="flex-1 ltr-value font-mono"
            :invalid="!o.value || duplicateValues.has(o.value)"
            maxlength="255"
            :aria-label="t('builder.options.value')"
            :placeholder="t('builder.options.value')"
          />
          <input
            type="color"
            :value="o.color || PICKER_FALLBACK"
            class="w-8 h-8 rounded border border-line-input"
            :aria-label="t('builder.options.color')"
            @input="(e) => (o.color = (e.target as HTMLInputElement).value)"
          />
          <Button v-if="o.color" size="small" text icon="pi pi-eraser" :aria-label="t('builder.options.clear_color')" @click="o.color = null" />
          <Button size="small" text icon="pi pi-arrow-up" :disabled="i === 0" :aria-label="t('builder.move_up')" @click="moveOption(i, -1)" />
          <Button size="small" text icon="pi pi-arrow-down" :disabled="i === statics.length - 1" :aria-label="t('builder.move_down')" @click="moveOption(i, 1)" />
          <Button size="small" text severity="danger" icon="pi pi-trash" :aria-label="t('builder.options.remove')" @click="removeOption(i)" />
        </div>
        <div v-for="l in builder.locales" :key="l.code" class="flex items-center gap-1">
          <span class="text-xs text-muted-color w-8 uppercase ltr-value">{{ l.code }}</span>
          <InputText
            :model-value="o.i18n?.label?.[l.code] ?? ''"
            :dir="l.direction"
            size="small"
            class="flex-1"
            maxlength="1000"
            :aria-label="`${t('builder.options.label')} (${l.native_name})`"
            @update:model-value="(v: string | undefined) => setLabel(o, l.code, v)"
          />
        </div>
        <div class="flex flex-wrap items-center gap-2 text-sm">
          <InputText
            :model-value="o.icon ?? ''"
            size="small"
            class="w-28 ltr-value"
            :placeholder="t('builder.icon')"
            :aria-label="t('builder.icon')"
            @update:model-value="(v: string | undefined) => (o.icon = v ? v : null)"
          />
          <InputText
            :model-value="o.group ?? ''"
            size="small"
            class="w-28 ltr-value"
            :placeholder="t('builder.options.group')"
            :aria-label="t('builder.options.group')"
            @update:model-value="(v: string | undefined) => (o.group = v ? v : null)"
          />
          <InputText
            :model-value="o.parent ?? ''"
            size="small"
            class="w-28 ltr-value"
            :placeholder="t('builder.options.parent')"
            :aria-label="t('builder.options.parent')"
            @update:model-value="(v: string | undefined) => (o.parent = v ? v : null)"
          />
          <label class="flex items-center gap-1"><Checkbox :model-value="o.default ?? false" binary @update:model-value="(v: boolean) => (o.default = v)" />{{ t('builder.options.default') }}</label>
          <label class="flex items-center gap-1"><Checkbox :model-value="o.active ?? true" binary @update:model-value="(v: boolean) => (o.active = v)" />{{ t('builder.options.active') }}</label>
        </div>
      </div>
      <Button size="small" icon="pi pi-plus" severity="secondary" :label="t('builder.options.add')" class="self-start" data-testid="add-option" @click="addOption" />
      <p class="text-xs text-muted-color">{{ t('builder.options.parent_hint') }}</p>
    </div>

    <!-- Records of a collection or form -->
    <template v-else-if="options.source === 'collection' || options.source === 'form'">
      <RelationEditor v-model:field="field" :info="info" />
      <div v-if="options.collection" class="grid grid-cols-2 gap-2">
        <label class="field"
          ><span>{{ t('builder.options.value_field') }}</span> <Select v-model="valuePath" :options="keyOptions" option-label="label" option-value="value" show-clear filter size="small"
        /></label>
        <label class="field"
          ><span>{{ t('builder.options.label_field') }}</span> <Select v-model="labelPath" :options="keyOptions" option-label="label" option-value="value" show-clear filter size="small"
        /></label>
        <label class="field"
          ><span>{{ t('builder.options.group_by') }}</span> <Select v-model="groupBy" :options="keyOptions" option-label="label" option-value="value" show-clear filter size="small"
        /></label>
        <label class="field"
          ><span>{{ t('builder.options.preview') }}</span>
          <MultiSelect v-model="preview" :options="keyOptions" option-label="label" option-value="value" :selection-limit="10" display="chip" size="small"
        /></label>
      </div>
    </template>

    <!-- Visual query -->
    <div v-else-if="options.source === 'query'" class="flex flex-col gap-2">
      <label class="field"
        ><span>{{ t('builder.options.query_from') }}</span>
        <FormPicker :model-value="options.query?.from ?? null" @update:model-value="setQueryFrom" />
      </label>
      <template v-if="options.query">
        <div class="text-sm font-medium">{{ t('builder.options.query_where') }}</div>
        <RuleBuilder :model-value="options.query.where ?? null" :scope="queryScope" @update:model-value="(a) => (options.query!.where = a ?? null)" />
        <div class="text-sm font-medium">{{ t('builder.options.query_sort') }}</div>
        <div v-for="(s, i) in options.query.sort ?? []" :key="i" class="flex gap-1">
          <Select :model-value="s.path[0]" :options="keyOptions" option-label="label" option-value="value" size="small" class="flex-1" @update:model-value="(v: string) => (s.path = [v])" />
          <Select
            :model-value="s.dir ?? 'asc'"
            :options="[
              { value: 'asc', label: t('builder.options.asc') },
              { value: 'desc', label: t('builder.options.desc') },
            ]"
            option-label="label"
            option-value="value"
            size="small"
            @update:model-value="(v) => (s.dir = v)"
          />
          <Button size="small" text severity="danger" icon="pi pi-times" :aria-label="t('builder.remove')" @click="options.query!.sort!.splice(i, 1)" />
        </div>
        <Button
          v-if="(options.query.sort ?? []).length < 5 && keyOptions.length"
          size="small"
          text
          icon="pi pi-plus"
          :label="t('builder.options.add_sort')"
          class="self-start"
          @click="(options.query!.sort ??= []).push({ path: [keyOptions[0]!.value], dir: 'asc' })"
        />
        <label class="field"
          ><span>{{ t('builder.options.limit') }}</span>
          <InputNumber :model-value="options.query.limit ?? 100" :min="1" :max="1000" size="small" @update:model-value="(v) => (options.query!.limit = v ?? 100)" />
        </label>
        <div class="grid grid-cols-2 gap-2">
          <label class="field"
            ><span>{{ t('builder.options.value_field') }}</span> <Select v-model="valuePath" :options="keyOptions" option-label="label" option-value="value" show-clear filter size="small"
          /></label>
          <label class="field"
            ><span>{{ t('builder.options.label_field') }}</span> <Select v-model="labelPath" :options="keyOptions" option-label="label" option-value="value" show-clear filter size="small"
          /></label>
        </div>
      </template>
    </div>

    <p v-else class="text-xs text-muted-color">{{ t(`builder.options.source_${options.source}_hint`) }}</p>

    <!-- Cascading -->
    <fieldset class="flex flex-col gap-2 rounded border border-line p-2">
      <legend class="text-sm font-medium px-1">{{ t('builder.options.cascading') }}</legend>
      <label class="field"
        ><span>{{ t('builder.options.depends_on') }}</span>
        <FieldSelect v-model="options.dependsOn" :exclude="field.uuid" />
      </label>
      <label v-if="options.dependsOn && (options.source === 'collection' || options.source === 'form' || options.source === 'query')" class="field"
        ><span>{{ t('builder.options.depends_path') }}</span>
        <Select v-model="dependsPath" :options="keyOptions" option-label="label" option-value="value" show-clear filter size="small" />
      </label>
      <p v-else-if="options.dependsOn && options.source === 'static'" class="text-xs text-muted-color">{{ t('builder.options.depends_static_hint') }}</p>
    </fieldset>

    <!-- Behaviour of the list -->
    <div class="grid grid-cols-2 gap-2">
      <label class="flex items-center gap-2 text-sm col-span-2"
        ><ToggleSwitch :model-value="options.allowCustom ?? false" @update:model-value="(v: boolean) => (options.allowCustom = v)" />{{ t('builder.options.allow_custom') }}</label
      >
      <label class="flex items-center gap-2 text-sm"
        ><ToggleSwitch :model-value="options.searchable ?? false" @update:model-value="(v: boolean) => (options.searchable = v)" />{{ t('builder.options.searchable') }}</label
      >
      <label class="flex items-center gap-2 text-sm"
        ><ToggleSwitch :model-value="options.lazy ?? false" @update:model-value="(v: boolean) => (options.lazy = v)" />{{ t('builder.options.lazy') }}</label
      >
      <label v-if="options.lazy" class="field"
        ><span>{{ t('builder.options.page_size') }}</span>
        <InputNumber :model-value="options.pageSize ?? 50" :min="5" :max="200" size="small" @update:model-value="(v) => (options.pageSize = v ?? 50)"
      /></label>
      <template v-if="multiple">
        <label class="field"
          ><span>{{ t('builder.options.min') }}</span> <InputNumber :model-value="options.min ?? null" :min="0" :max="1000" size="small" @update:model-value="(v) => (options.min = v ?? null)"
        /></label>
        <label class="field"
          ><span>{{ t('builder.options.max') }}</span> <InputNumber :model-value="options.max ?? null" :min="1" :max="1000" size="small" @update:model-value="(v) => (options.max = v ?? null)"
        /></label>
      </template>
      <label class="field col-span-2"
        ><span>{{ t('builder.options.defaults') }}</span>
        <MultiSelect
          v-if="options.source === 'static'"
          v-model="options.defaults"
          :options="staticValues"
          option-label="label"
          option-value="value"
          display="chip"
          size="small"
          :selection-limit="multiple ? 100 : 1"
        />
        <InputText v-else v-model="defaultsText" size="small" class="ltr-value" :placeholder="t('builder.options.defaults_hint')" />
      </label>
    </div>
  </div>
</template>
