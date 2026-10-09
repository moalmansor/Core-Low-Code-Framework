<script setup lang="ts">
import Button from 'primevue/button'
import InputNumber from 'primevue/inputnumber'
import InputText from 'primevue/inputtext'
import Message from 'primevue/message'
import MultiSelect from 'primevue/multiselect'
import Select from 'primevue/select'
import Tab from 'primevue/tab'
import TabList from 'primevue/tablist'
import TabPanel from 'primevue/tabpanel'
import TabPanels from 'primevue/tabpanels'
import Tabs from 'primevue/tabs'
import ToggleSwitch from 'primevue/toggleswitch'
import { computed, onMounted, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { referenceApi, type NamedOption } from '../api'
import BreakpointsInput from '../BreakpointsInput.vue'
import ConditionsEditor from '../conditions/ConditionsEditor.vue'
import ExpressionInput from '../conditions/ExpressionInput.vue'
import { buildScope, pick } from '../conditions/scope'
import { dataAncestor, descendants, uniqueKey } from '../document'
import FormPicker from '../FormPicker.vue'
import I18nInput from '../I18nInput.vue'
import type { GroupDef, JustificationLevel, RepeaterSettings } from '../types'
import { useBuilder } from '../useBuilder'
import { newUuid } from '../uuid'
import IssueList from './IssueList.vue'
import KeyInput from './KeyInput.vue'

/** Every property of a group (specification §4.5). */
const props = defineProps<{ uuid: string }>()
const { t } = useI18n()
const builder = useBuilder()
const tab = ref('general')

const group = computed<GroupDef>(() => builder.doc!.groups.find((g) => g.uuid === props.uuid)!)
const layout = computed(() => group.value.layout!)
const i18n = computed(() => group.value.i18n!)
const rowsKey = computed(() => {
  const d = dataAncestor(builder.doc!, group.value.uuid)
  return d?.type === 'repeater' ? d.key : null
})
const scope = computed(() => buildScope(builder.doc!, builder.catalog.fields, rowsKey.value))
const structural = computed(() => ['tabs', 'wizard', 'row'].includes(group.value.type))
const spacing = computed(() => (['none', 'sm', 'md', 'lg'] as const).map((v) => ({ value: v, label: t(`builder.layout.spacing_${v}`) })))
const borders = computed(() => (['none', 'subtle', 'strong'] as const).map((v) => ({ value: v, label: t(`builder.layout.border_${v}`) })))
const backgrounds = computed(() => (['none', 'subtle', 'accent'] as const).map((v) => ({ value: v, label: t(`builder.layout.background_${v}`) })))
const justification = computed(() => (['inherit', 'not_required', 'optional', 'mandatory'] as JustificationLevel[]).map((v) => ({ value: v, label: t(`builder.justification.${v}`) })))

// ---------------------------------------------------------------- validation

function validation() {
  group.value.validation ??= {}
  return group.value.validation
}
function addRule(): void {
  ;(validation().rules ??= []).push({ when: { k: 'lit', t: 'boolean', v: true }, message: {} })
}

// ---------------------------------------------------------------- repeater

watch(
  group,
  (g) => {
    if (g?.type === 'repeater' && !g.repeater) g.repeater = {}
  },
  { immediate: true },
)
const repeater = computed<RepeaterSettings>(() => group.value.repeater ?? {})
const repeaterFields = computed(() => {
  if (group.value.type !== 'repeater') return []
  const ids = new Set(descendants(builder.doc!, group.value.uuid).fields)
  return builder.doc!.fields.filter((f) => ids.has(f.uuid) && builder.typeInfo(f.type)?.stored).map((f) => ({ value: f.uuid, label: `${pick(f.i18n?.label, builder.locale, f.key)} (${f.key})` }))
})
const fns = computed(() => (['sum', 'avg', 'min', 'max', 'count'] as const).map((v) => ({ value: v, label: t(`builder.repeater.fn_${v}`) })))
const roles = ref<NamedOption[]>([])
const rolesUnavailable = ref(false)
onMounted(async () => {
  if (group.value.type !== 'repeater') return
  try {
    roles.value = await referenceApi.roles()
  } catch {
    rolesUnavailable.value = true
  }
})
function setPermission(kind: 'add' | 'remove' | 'reorder', value: string[]): void {
  repeater.value.rowPermissions = { ...(repeater.value.rowPermissions ?? {}), [kind]: value }
}

// ---------------------------------------------------------------- sub-form

const subformRelation = computed(() => builder.doc!.relations.find((r) => r.uuid === group.value.subform?.relation))
function setSubform(form: string | null | undefined): void {
  builder.mutate((d) => {
    const g = d.groups.find((x) => x.uuid === props.uuid)
    if (!g) return
    const existing = d.relations.find((r) => r.uuid === g.subform?.relation)
    if (!form) {
      if (existing) d.relations = d.relations.filter((r) => r.uuid !== existing.uuid)
      g.subform = null
      return
    }
    if (existing) existing.target = form
    else {
      const uuid = newUuid()
      d.relations.push({
        uuid,
        key: uniqueKey(g.key, new Set(d.relations.map((r) => r.key))),
        type: 'one_to_many',
        target: form,
        kind: 'subform',
        onDelete: 'cascade',
        display: null,
        value: null,
        inverse: null,
      })
      g.subform = { form, relation: uuid }
      return
    }
    g.subform = { form, relation: existing.uuid }
  })
}
const deletes = computed(() => (['restrict', 'cascade', 'set_null'] as const).map((v) => ({ value: v, label: t(`builder.relation.on_delete_${v}`) })))
const iconInvalid = computed(() => !!layout.value.icon && !/^[a-z0-9 -]{0,64}$/.test(layout.value.icon))
const cssInvalid = computed(() => !!layout.value.cssClass && !/^[A-Za-z0-9 _-]{0,128}$/.test(layout.value.cssClass))
</script>

<template>
  <div v-if="group" class="flex flex-col gap-2" data-testid="group-properties">
    <header class="flex items-center gap-2">
      <i class="pi pi-objects-column" aria-hidden="true" />
      <h2 class="font-semibold flex-1 truncate">{{ t(`builder.group.${group.type}`) }}</h2>
    </header>
    <IssueList :uuid="group.uuid" />
    <Tabs v-model:value="tab" scrollable>
      <TabList>
        <Tab value="general">{{ t('builder.tab.general') }}</Tab>
        <Tab value="layout">{{ t('builder.tab.layout') }}</Tab>
        <Tab v-if="!structural" value="validation">{{ t('builder.tab.validation') }}</Tab>
        <Tab value="rules">{{ t('builder.tab.rules') }}</Tab>
      </TabList>
      <TabPanels class="!px-0">
        <TabPanel value="general" class="flex flex-col gap-3">
          <KeyInput :uuid="group.uuid" :current="group.key" :label="group.i18n?.title" />
          <I18nInput v-model="i18n.title" :label="t('builder.group_props.title')" data-testid="prop-title" />
          <I18nInput v-model="i18n.description" :label="t('builder.group_props.description')" multiline :rows="2" />

          <template v-if="group.type === 'repeater'">
            <fieldset class="flex flex-col gap-2 rounded border border-line p-2">
              <legend class="text-sm font-medium px-1">{{ t('builder.repeater.title') }}</legend>
              <div class="grid grid-cols-3 gap-2">
                <label class="field"
                  ><span>{{ t('builder.repeater.min_rows') }}</span
                  ><InputNumber :model-value="repeater.minRows ?? 0" :min="0" :max="10000" size="small" @update:model-value="(v) => (repeater.minRows = v ?? 0)"
                /></label>
                <label class="field"
                  ><span>{{ t('builder.repeater.max_rows') }}</span
                  ><InputNumber :model-value="repeater.maxRows ?? null" :min="1" :max="10000" size="small" @update:model-value="(v) => (repeater.maxRows = v ?? null)"
                /></label>
                <label class="field"
                  ><span>{{ t('builder.repeater.default_rows') }}</span
                  ><InputNumber :model-value="repeater.defaultRows ?? 0" :min="0" :max="100" size="small" @update:model-value="(v) => (repeater.defaultRows = v ?? 0)"
                /></label>
              </div>
              <label class="field"
                ><span>{{ t('builder.repeater.display') }}</span>
                <Select
                  :model-value="repeater.display ?? 'table'"
                  :options="[
                    { value: 'table', label: t('builder.repeater.display_table') },
                    { value: 'cards', label: t('builder.repeater.display_cards') },
                  ]"
                  option-label="label"
                  option-value="value"
                  size="small"
                  @update:model-value="(v) => (repeater.display = v)"
                />
              </label>
              <div class="text-sm font-medium">{{ t('builder.repeater.aggregates') }}</div>
              <div v-for="(a, i) in repeater.aggregates ?? []" :key="i" class="flex flex-col gap-1 rounded bg-subtle p-2">
                <div class="flex gap-1">
                  <Select v-model="a.fn" :options="fns" option-label="label" option-value="value" size="small" class="w-32" />
                  <Select v-model="a.field" :options="repeaterFields" option-label="label" option-value="value" size="small" class="flex-1" />
                  <Button size="small" text severity="danger" icon="pi pi-times" :aria-label="t('builder.remove')" @click="repeater.aggregates!.splice(i, 1)" />
                </div>
                <I18nInput v-model="a.label" :label="t('builder.repeater.aggregate_label')" />
              </div>
              <Button
                v-if="repeaterFields.length && (repeater.aggregates ?? []).length < 20"
                size="small"
                text
                icon="pi pi-plus"
                :label="t('builder.repeater.add_aggregate')"
                class="self-start"
                @click="(repeater.aggregates ??= []).push({ field: repeaterFields[0]!.value, fn: 'sum', label: {} })"
              />
              <div class="text-sm font-medium">{{ t('builder.repeater.permissions') }}</div>
              <p class="text-xs text-muted-color">{{ t('builder.repeater.permissions_hint') }}</p>
              <Message v-if="rolesUnavailable" severity="secondary" size="small">{{ t('builder.repeater.roles_unavailable') }}</Message>
              <template v-else>
                <label v-for="k in ['add', 'remove', 'reorder'] as const" :key="k" class="field"
                  ><span>{{ t(`builder.repeater.can_${k}`) }}</span>
                  <MultiSelect
                    :model-value="repeater.rowPermissions?.[k] ?? []"
                    :options="roles"
                    option-label="name"
                    option-value="uuid"
                    display="chip"
                    size="small"
                    :placeholder="t('builder.repeater.everyone')"
                    @update:model-value="(v) => setPermission(k, v)"
                  />
                </label>
              </template>
            </fieldset>
          </template>

          <fieldset v-if="group.type === 'wizard' || group.type === 'step'" class="flex flex-col gap-2 rounded border border-line p-2">
            <legend class="text-sm font-medium px-1">{{ t('builder.wizard.title') }}</legend>
            <label class="flex items-center gap-2 text-sm"
              ><ToggleSwitch :model-value="group.wizard?.validateBeforeNext ?? true" @update:model-value="(v: boolean) => (group.wizard = { ...(group.wizard ?? {}), validateBeforeNext: v })" />{{
                t('builder.wizard.validate_before_next')
              }}</label
            >
            <label class="flex items-center gap-2 text-sm"
              ><ToggleSwitch :model-value="group.wizard?.allowJump ?? false" @update:model-value="(v: boolean) => (group.wizard = { ...(group.wizard ?? {}), allowJump: v })" />{{
                t('builder.wizard.allow_jump')
              }}</label
            >
          </fieldset>

          <fieldset v-if="group.type === 'subform'" class="flex flex-col gap-2 rounded border border-line p-2">
            <legend class="text-sm font-medium px-1">{{ t('builder.subform.title') }}</legend>
            <p class="text-xs text-muted-color">{{ t('builder.subform.hint') }}</p>
            <label class="field"
              ><span>{{ t('builder.subform.form') }}</span>
              <FormPicker :model-value="group.subform?.form ?? null" kind="form" :exclude="builder.formUuid" @update:model-value="setSubform" />
            </label>
            <label v-if="subformRelation" class="field"
              ><span>{{ t('builder.relation.on_delete') }}</span>
              <Select v-model="subformRelation.onDelete" :options="deletes" option-label="label" option-value="value" size="small" />
            </label>
          </fieldset>

          <div v-if="!structural" class="grid grid-cols-2 gap-2">
            <label class="flex items-center gap-2 text-sm col-span-2"
              ><ToggleSwitch :model-value="group.collapsible ?? false" @update:model-value="(v: boolean) => (group.collapsible = v)" />{{ t('builder.group_props.collapsible') }}</label
            >
            <label v-if="group.collapsible" class="field"
              ><span>{{ t('builder.group_props.default_state') }}</span>
              <Select
                :model-value="group.defaultState ?? 'open'"
                :options="[
                  { value: 'open', label: t('builder.group_props.open') },
                  { value: 'closed', label: t('builder.group_props.closed') },
                ]"
                option-label="label"
                option-value="value"
                size="small"
                @update:model-value="(v) => (group.defaultState = v)"
              />
            </label>
          </div>
          <label class="field"
            ><span>{{ t('builder.justification.label') }}</span>
            <Select
              :model-value="group.justification ?? 'inherit'"
              :options="justification"
              option-label="label"
              option-value="value"
              size="small"
              @update:model-value="(v) => (group.justification = v)"
            />
          </label>
        </TabPanel>

        <TabPanel value="layout" class="flex flex-col gap-3">
          <BreakpointsInput v-model="layout.columns" :label="t('builder.layout.columns')" />
          <BreakpointsInput v-if="group.type === 'column' || group.parent" v-model="layout.span" :label="t('builder.layout.span')" />
          <div class="grid grid-cols-2 gap-2">
            <label class="field"
              ><span>{{ t('builder.layout.spacing') }}</span>
              <Select :model-value="layout.spacing ?? 'md'" :options="spacing" option-label="label" option-value="value" size="small" @update:model-value="(v) => (layout.spacing = v)" />
            </label>
            <label class="field"
              ><span>{{ t('builder.layout.border') }}</span>
              <Select :model-value="layout.border ?? 'none'" :options="borders" option-label="label" option-value="value" size="small" @update:model-value="(v) => (layout.border = v)" />
            </label>
            <label class="field"
              ><span>{{ t('builder.layout.background') }}</span>
              <Select :model-value="layout.background ?? 'none'" :options="backgrounds" option-label="label" option-value="value" size="small" @update:model-value="(v) => (layout.background = v)" />
            </label>
            <label class="field"
              ><span>{{ t('builder.icon') }}</span>
              <InputText :model-value="layout.icon ?? ''" size="small" class="ltr-value" :invalid="iconInvalid" @update:model-value="(v: string | undefined) => (layout.icon = v ? v : null)" />
            </label>
            <label class="field col-span-2"
              ><span>{{ t('builder.css_class') }}</span>
              <InputText :model-value="layout.cssClass ?? ''" size="small" class="ltr-value" :invalid="cssInvalid" @update:model-value="(v: string | undefined) => (layout.cssClass = v ? v : null)" />
            </label>
          </div>
        </TabPanel>

        <TabPanel v-if="!structural" value="validation" class="flex flex-col gap-3">
          <label class="field"
            ><span>{{ t('builder.group_props.min_filled') }}</span>
            <InputNumber :model-value="group.validation?.minFilled ?? null" :min="0" :max="1000" size="small" @update:model-value="(v) => (validation().minFilled = v ?? null)" />
          </label>
          <I18nInput
            v-if="group.validation?.minFilled"
            :model-value="group.validation.minFilledMessage"
            :label="t('builder.validation.message')"
            @update:model-value="(m) => (validation().minFilledMessage = m)"
          />
          <div class="text-sm font-medium">{{ t('builder.group_props.rules') }}</div>
          <p class="text-xs text-muted-color">{{ t('builder.group_props.rules_hint') }}</p>
          <div v-for="(r, i) in group.validation?.rules ?? []" :key="i" class="rounded border border-line p-2 flex flex-col gap-2">
            <div class="flex justify-end">
              <Button size="small" text severity="danger" icon="pi pi-trash" :aria-label="t('builder.remove')" @click="group.validation!.rules!.splice(i, 1)" />
            </div>
            <ExpressionInput :model-value="r.when" :scope="scope" expected="boolean" :allow-empty="false" :label="t('builder.validation.custom_when')" @update:model-value="(a) => a && (r.when = a)" />
            <I18nInput v-model="r.message" :label="t('builder.validation.message')" />
          </div>
          <Button v-if="(group.validation?.rules ?? []).length < 50" size="small" text icon="pi pi-plus" :label="t('builder.validation.add_custom')" class="self-start" @click="addRule" />
        </TabPanel>

        <TabPanel value="rules">
          <ConditionsEditor :owner="{ type: 'group', uuid: group.uuid }" :scope="scope" />
        </TabPanel>
      </TabPanels>
    </Tabs>
  </div>
</template>
