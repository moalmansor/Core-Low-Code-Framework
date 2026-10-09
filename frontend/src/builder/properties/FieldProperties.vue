<script setup lang="ts">
import InputNumber from 'primevue/inputnumber'
import InputText from 'primevue/inputtext'
import Message from 'primevue/message'
import Select from 'primevue/select'
import ToggleSwitch from 'primevue/toggleswitch'
import { computed, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { UI_PROPS } from '@/runtime/uiProps'
import { useSession } from '@/stores/session'
import BreakpointsInput from '../BreakpointsInput.vue'
import ConditionsEditor from '../conditions/ConditionsEditor.vue'
import { buildScope } from '../conditions/scope'
import { repeaterOf } from '../document'
import I18nInput from '../I18nInput.vue'
import PanelSection from '../panel/PanelSection.vue'
import PanelTabs from '../panel/PanelTabs.vue'
import { providePanel } from '../panel/panel'
import type { FieldDef, JustificationLevel } from '../types'
import { useBuilder } from '../useBuilder'
import BehaviorEditor from './BehaviorEditor.vue'
import EventsEditor from './EventsEditor.vue'
import IssueList from './IssueList.vue'
import KeyInput from './KeyInput.vue'
import OptionsEditor from './OptionsEditor.vue'
import RelationEditor from './RelationEditor.vue'
import StorageEditor from './StorageEditor.vue'
import TypePropsEditor from './TypePropsEditor.vue'
import ValidationEditor from './ValidationEditor.vue'

/**
 * Every property of a field (specification §4.6), in a few tabs of
 * collapsible sections with "Find a setting" across all of them
 * (docs/design-system.md, properties panel).
 */
const props = defineProps<{ uuid: string }>()
const { t } = useI18n()
const builder = useBuilder()
const session = useSession()

const field = computed<FieldDef>({
  get: () => builder.doc!.fields.find((f) => f.uuid === props.uuid)!,
  set: () => undefined,
})
const info = computed(() => builder.typeInfo(field.value.type))
const display = computed(() => info.value?.storage === 'none')
const scope = computed(() => buildScope(builder.doc!, builder.catalog.fields, repeaterOf(builder.doc!, field.value)?.key ?? null))
const isLookup = computed(() => ['lookup', 'multi_lookup'].includes(info.value?.storage ?? ''))
const hasContent = computed(() => ['static_html', 'heading', 'alert_box', 'link', 'divider', 'output'].includes(field.value.type))
const allowsRequired = computed(() => info.value?.validation.includes('required') ?? false)
// "Required" is set under Basics; the Validation tab holds the other rules and the required message.
const hasValidation = computed(() => !!info.value && (info.value.validation.some((r) => r !== 'required') || field.value.validation?.required === true))
const tabs = computed(() => ['general', 'data', hasValidation.value ? 'validation' : '', 'rules'].filter(Boolean).map((value) => ({ value, label: t(`builder.tab.${value}`) })))
const more = computed(() => (display.value ? [] : ['events', 'table']).map((value) => ({ value, label: t(`builder.tab.${value}`) })))
const available = () => [...tabs.value, ...more.value].map((x) => x.value)
const panel = providePanel('field', 'general', available)
watch(available, (list) => {
  if (!list.includes(panel.tab.value)) panel.tab.value = 'general'
})
const terms = (...keys: string[]) => keys.map((k) => t(k))

const ui = computed(() => field.value.ui!)
const table = computed(() => field.value.table!)
const exp = computed(() => field.value.export!)
const i18n = computed(() => field.value.i18n!)
const sizes = computed(() => (['small', 'medium', 'large'] as const).map((v) => ({ value: v, label: t(`builder.ui.size_${v}`) })))
const positions = computed(() => (['top', 'side', 'hidden'] as const).map((v) => ({ value: v, label: t(`builder.ui.label_${v}`) })))
const justification = computed(() => (['inherit', 'not_required', 'optional', 'mandatory'] as JustificationLevel[]).map((v) => ({ value: v, label: t(`builder.justification.${v}`) })))
const iconInvalid = computed(() => !!ui.value.icon && !/^[a-z0-9 -]{0,64}$/.test(ui.value.icon))
const cssInvalid = computed(() => !!ui.value.cssClass && !/^[A-Za-z0-9 _-]{0,128}$/.test(ui.value.cssClass))
const autocompleteInvalid = computed(() => !!ui.value.autocomplete && !/^[a-z -]{0,64}$/.test(ui.value.autocomplete))
const canCode = computed(() => session.can('system.manage_code'))
const hasTypeProps = computed(() => (UI_PROPS[field.value.type] ?? []).some((s) => s.kind !== 'file'))
const helpCount = computed(() => (['placeholder', 'help', 'tooltip', 'description', 'prefix', 'suffix'] as const).filter((k) => Object.keys(i18n.value[k] ?? {}).length > 0).length)
</script>

<template>
  <div v-if="field && info" class="flex flex-col gap-2" data-testid="field-properties">
    <header class="flex items-center gap-2">
      <i :class="info.icon" aria-hidden="true" />
      <h2 class="font-semibold flex-1 truncate">{{ t(`builder.type.${field.type}`) }}</h2>
    </header>
    <IssueList :uuid="field.uuid" />
    <PanelTabs :tabs="tabs" :more="more" />

    <!-- General -->
    <PanelSection
      id="field-basics"
      tab="general"
      :title="t('builder.section.basics')"
      :terms="terms('builder.field.key', 'builder.field.label', 'builder.validation.required', 'builder.field.content')"
    >
      <KeyInput :uuid="field.uuid" :current="field.key" :label="field.i18n?.label" />
      <I18nInput v-model="i18n.label" :label="t('builder.field.label')" data-testid="prop-label" />
      <label v-if="allowsRequired" class="flex items-center gap-2 text-sm font-medium"
        ><ToggleSwitch :model-value="field.validation?.required ?? false" data-testid="prop-required" @update:model-value="(x: boolean) => ((field.validation ??= {}).required = x)" />{{
          t('builder.validation.required')
        }}</label
      >
      <I18nInput v-if="hasContent" v-model="i18n.content" :label="t('builder.field.content')" multiline :rows="4" :maxlength="100000" />
      <I18nInput v-if="field.type === 'consent'" v-model="i18n.consentTerms" :label="t('builder.field.consent_terms')" multiline :rows="4" :maxlength="100000" />
    </PanelSection>
    <PanelSection
      v-if="!display"
      id="field-help"
      tab="general"
      :title="t('builder.section.help')"
      :hint="helpCount ? String(helpCount) : undefined"
      :default-open="false"
      :terms="terms('builder.field.placeholder', 'builder.field.help', 'builder.field.tooltip', 'builder.field.description', 'builder.field.prefix', 'builder.field.suffix')"
    >
      <I18nInput v-model="i18n.placeholder" :label="t('builder.field.placeholder')" />
      <I18nInput v-model="i18n.help" :label="t('builder.field.help')" multiline :rows="2" />
      <I18nInput v-model="i18n.tooltip" :label="t('builder.field.tooltip')" />
      <I18nInput v-model="i18n.description" :label="t('builder.field.description')" multiline :rows="2" />
      <I18nInput v-model="i18n.prefix" :label="t('builder.field.prefix')" />
      <I18nInput v-model="i18n.suffix" :label="t('builder.field.suffix')" />
    </PanelSection>
    <PanelSection v-if="hasTypeProps" id="field-type" tab="general" :title="t('builder.section.type')" :terms="(UI_PROPS[field.type] ?? []).map((s) => t(`builder.ui_prop.${s.name}`))">
      <TypePropsEditor v-model:field="field" />
    </PanelSection>
    <PanelSection id="field-layout" tab="general" :title="t('builder.tab.layout')" :terms="terms('builder.ui.size', 'builder.ui.label_position', 'builder.ui.width')">
      <div class="grid grid-cols-2 gap-2">
        <label class="field"
          ><span>{{ t('builder.ui.size') }}</span>
          <Select :model-value="ui.size ?? 'medium'" :options="sizes" option-label="label" option-value="value" size="small" @update:model-value="(v) => (ui.size = v)" />
        </label>
        <label class="field"
          ><span>{{ t('builder.ui.label_position') }}</span>
          <Select :model-value="ui.labelPosition ?? 'top'" :options="positions" option-label="label" option-value="value" size="small" @update:model-value="(v) => (ui.labelPosition = v)" />
        </label>
      </div>
      <BreakpointsInput v-model="ui.width" :label="t('builder.ui.width')" />
    </PanelSection>
    <PanelSection
      id="field-advanced"
      tab="general"
      :title="t('builder.section.advanced')"
      :default-open="false"
      :terms="terms('builder.icon', 'builder.css_class', 'builder.ui.autofocus', 'builder.ui.spellcheck', 'builder.ui.tab_index', 'builder.ui.autocomplete', 'builder.justification.label')"
    >
      <div class="grid grid-cols-2 gap-2">
        <label class="field"
          ><span>{{ t('builder.icon') }}</span>
          <InputText
            :model-value="ui.icon ?? ''"
            size="small"
            class="ltr-value"
            :invalid="iconInvalid"
            placeholder="pi pi-user"
            @update:model-value="(v: string | undefined) => (ui.icon = v ? v : null)"
          />
        </label>
        <label class="field"
          ><span>{{ t('builder.css_class') }}</span>
          <InputText :model-value="ui.cssClass ?? ''" size="small" class="ltr-value" :invalid="cssInvalid" @update:model-value="(v: string | undefined) => (ui.cssClass = v ? v : null)" />
        </label>
      </div>
      <div v-if="!display" class="grid grid-cols-2 gap-2">
        <label class="flex items-center gap-2 text-sm"
          ><ToggleSwitch :model-value="ui.autofocus ?? false" @update:model-value="(v: boolean) => (ui.autofocus = v)" />{{ t('builder.ui.autofocus') }}</label
        >
        <label class="flex items-center gap-2 text-sm"
          ><ToggleSwitch :model-value="ui.spellcheck ?? true" @update:model-value="(v: boolean) => (ui.spellcheck = v)" />{{ t('builder.ui.spellcheck') }}</label
        >
        <label class="field"
          ><span>{{ t('builder.ui.tab_index') }}</span>
          <InputNumber :model-value="ui.tabIndex ?? null" :min="-1" :max="32767" size="small" @update:model-value="(v) => (ui.tabIndex = v ?? null)" />
        </label>
        <label class="field"
          ><span>{{ t('builder.ui.autocomplete') }}</span>
          <InputText
            :model-value="ui.autocomplete ?? ''"
            size="small"
            class="ltr-value"
            :invalid="autocompleteInvalid"
            placeholder="off, email, tel"
            @update:model-value="(v: string | undefined) => (ui.autocomplete = v ? v : null)"
          />
        </label>
      </div>
      <label v-if="!display" class="field"
        ><span>{{ t('builder.justification.label') }}</span>
        <Select
          :model-value="field.justification ?? 'inherit'"
          :options="justification"
          option-label="label"
          option-value="value"
          size="small"
          @update:model-value="(v) => (field.justification = v)"
        />
        <span class="text-xs text-muted-color">{{ t('builder.justification.hint') }}</span>
      </label>
    </PanelSection>

    <!-- Data -->
    <PanelSection
      id="field-storage"
      tab="data"
      :title="t('builder.section.storage')"
      :default-open="false"
      :terms="
        terms(
          'builder.storage.column',
          'builder.storage.db_type',
          'builder.storage.length',
          'builder.storage.index',
          'builder.flags.encrypted',
          'builder.flags.personal',
          'builder.relation.title',
          'builder.relation.target',
        )
      "
    >
      <StorageEditor v-model:field="field" :info="info" />
      <RelationEditor v-if="isLookup" v-model:field="field" :info="info" />
    </PanelSection>
    <PanelSection
      v-if="info.options"
      id="field-options"
      tab="data"
      :title="t('builder.tab.options')"
      :terms="terms('builder.options.source', 'builder.options.add', 'builder.options.color', 'builder.options.default', 'builder.options.cascading', 'builder.options.searchable')"
    >
      <OptionsEditor v-model:field="field" :info="info" />
    </PanelSection>
    <PanelSection
      id="field-behavior"
      tab="data"
      :title="t('builder.tab.behavior')"
      :terms="
        terms(
          'builder.behavior.default',
          'builder.behavior.default_value',
          'builder.behavior.formula',
          'builder.behavior.mask',
          'builder.behavior.autofill',
          'builder.behavior.sequence',
          'builder.behavior.transforms',
          'builder.behavior.number_format',
          'builder.behavior.timezone',
          'builder.behavior.calendar',
        )
      "
    >
      <BehaviorEditor v-model:field="field" :info="info" :scope="scope" />
    </PanelSection>
    <PanelSection v-if="field.hook || canCode" id="field-hook" tab="data" :title="t('builder.hook.title')" :default-open="false" :terms="terms('builder.hook.extension', 'builder.hook.name')">
      <p class="text-xs text-muted-color">{{ t('builder.hook.hint') }}</p>
      <template v-if="canCode">
        <label class="field"
          ><span>{{ t('builder.hook.extension') }}</span>
          <InputText
            :model-value="field.hook?.extension ?? ''"
            size="small"
            class="ltr-value font-mono"
            @update:model-value="(v: string | undefined) => (field.hook = v ? { extension: v, hook: field.hook?.hook ?? '' } : null)"
          />
        </label>
        <label v-if="field.hook" class="field"
          ><span>{{ t('builder.hook.name') }}</span>
          <InputText v-model="field.hook.hook" size="small" class="ltr-value font-mono" maxlength="64" />
        </label>
      </template>
      <p v-else class="text-sm ltr-value font-mono">{{ field.hook?.extension }} · {{ field.hook?.hook }}</p>
    </PanelSection>

    <!-- Validation, rules, events, table -->
    <PanelSection
      v-if="hasValidation"
      id="field-validation"
      tab="validation"
      :title="t('builder.tab.validation')"
      :terms="
        terms(
          'builder.validation.length',
          'builder.validation.format',
          'builder.validation.pattern',
          'builder.validation.number',
          'builder.validation.date',
          'builder.validation.unique',
          'builder.validation.compare',
          'builder.validation.custom',
          'builder.validation.file',
        )
      "
    >
      <ValidationEditor v-model:field="field" :info="info" :scope="scope" />
    </PanelSection>
    <PanelSection id="field-rules" tab="rules" :title="t('builder.tab.rules')">
      <ConditionsEditor :owner="{ type: 'field', uuid: field.uuid }" :scope="scope" />
    </PanelSection>
    <PanelSection v-if="!display" id="field-events" tab="events" :title="t('builder.tab.events')" :terms="terms('builder.events.add', 'builder.events.when_field')">
      <EventsEditor v-model:field="field" :scope="scope" />
    </PanelSection>
    <PanelSection
      v-if="!display"
      id="field-column"
      tab="table"
      :title="t('builder.table.title')"
      :terms="terms('builder.table.visible', 'builder.table.sortable', 'builder.table.filterable', 'builder.table.searchable', 'builder.table.column_label', 'builder.table.display_format')"
    >
      <label class="flex items-center gap-2 text-sm"
        ><ToggleSwitch :model-value="table.visible ?? true" @update:model-value="(v: boolean) => (table.visible = v)" />{{ t('builder.table.visible') }}</label
      >
      <label class="flex items-center gap-2 text-sm"
        ><ToggleSwitch :model-value="table.sortable ?? false" @update:model-value="(v: boolean) => (table.sortable = v)" />{{ t('builder.table.sortable') }}</label
      >
      <label class="flex items-center gap-2 text-sm"
        ><ToggleSwitch :model-value="table.filterable ?? false" @update:model-value="(v: boolean) => (table.filterable = v)" />{{ t('builder.table.filterable') }}</label
      >
      <label class="flex items-center gap-2 text-sm"
        ><ToggleSwitch :model-value="table.searchable ?? false" @update:model-value="(v: boolean) => (table.searchable = v)" />{{ t('builder.table.searchable') }}</label
      >
      <I18nInput v-model="i18n.columnLabel" :label="t('builder.table.column_label')" />
      <label class="field"
        ><span>{{ t('builder.table.display_format') }}</span>
        <InputText :model-value="table.displayFormat ?? ''" size="small" class="ltr-value" maxlength="64" @update:model-value="(v: string | undefined) => (table.displayFormat = v ? v : null)" />
      </label>
    </PanelSection>
    <PanelSection
      v-if="!display"
      id="field-export"
      tab="table"
      :title="t('builder.export.title')"
      :terms="terms('builder.export.exportable', 'builder.export.importable', 'builder.export.excel_column', 'builder.export.print', 'builder.export.pdf')"
    >
      <label class="flex items-center gap-2 text-sm"
        ><ToggleSwitch :model-value="exp.exportable ?? true" @update:model-value="(v: boolean) => (exp.exportable = v)" />{{ t('builder.export.exportable') }}</label
      >
      <label class="flex items-center gap-2 text-sm"
        ><ToggleSwitch :model-value="exp.importable ?? true" @update:model-value="(v: boolean) => (exp.importable = v)" />{{ t('builder.export.importable') }}</label
      >
      <label class="field"
        ><span>{{ t('builder.export.excel_column') }}</span>
        <InputText :model-value="exp.excelColumn ?? ''" size="small" maxlength="128" @update:model-value="(v: string | undefined) => (exp.excelColumn = v ? v : null)" />
      </label>
      <label class="flex items-center gap-2 text-sm"><ToggleSwitch :model-value="exp.print ?? true" @update:model-value="(v: boolean) => (exp.print = v)" />{{ t('builder.export.print') }}</label>
      <label class="flex items-center gap-2 text-sm"><ToggleSwitch :model-value="exp.pdf ?? true" @update:model-value="(v: boolean) => (exp.pdf = v)" />{{ t('builder.export.pdf') }}</label>
    </PanelSection>
  </div>
  <Message v-else-if="field" severity="warn">{{ t('builder.unknown_type', { type: field.type }) }}</Message>
</template>
