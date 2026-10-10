<script setup lang="ts">
import Button from 'primevue/button'
import InputText from 'primevue/inputtext'
import MultiSelect from 'primevue/multiselect'
import Select from 'primevue/select'
import { computed, onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
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
import { labelOf, newUuid, panelsApi, printLayoutsApi, type PanelDoc, type PrintLayoutDoc } from './api'
import ErrorList from './ErrorList.vue'
import { useHashedDocument } from './useHashedDocument'

/**
 * Print layouts (specification §4.14 "Print"): paper and orientation, header
 * and footer text, the logo, and the sections printed in order — the form
 * body, chosen fields, a View Mode panel, the status history or a page
 * break. Users with the print permission print a record as HTML or PDF; the
 * default layout applies when none is chosen.
 */
const props = defineProps<{ form: string }>()
const { t, locale } = useI18n()
const session = useSession()
const builder = useBuilder()
const doc = useHashedDocument<PrintLayoutDoc[]>(
  () => printLayoutsApi.load(props.form),
  (v, h) => printLayoutsApi.save(props.form, v, h),
)
const dl = computed(() => session.boot?.default_locale ?? 'en')
const panels = ref<PanelDoc[]>([])
onMounted(async () => {
  panels.value = (await panelsApi.load(props.form).catch(() => null))?.value ?? []
})
const papers = computed(() => (['a4', 'a3', 'letter', 'legal'] as const).map((v) => ({ value: v, label: t(`print.paper.${v}`) })))
const orientations = computed(() => (['portrait', 'landscape'] as const).map((v) => ({ value: v, label: t(`print.orientation.${v}`) })))
const sectionTypes = computed(() => (['form_body', 'fields', 'panel', 'status_history', 'page_break'] as const).map((v) => ({ value: v, label: t(`print.section.${v}`) })))
const fieldOptions = computed(() => (builder.doc?.fields ?? []).map((f) => ({ value: f.key, label: labelOf(f.i18n?.label ?? {}, locale.value, f.key, dl.value) })))
const panelOptions = computed(() => panels.value.map((p) => ({ value: p.uuid, label: `${t(`panels.type.${p.type}`)} · ${labelOf(p.i18n.title, locale.value, p.uuid.slice(0, 8), dl.value)}` })))

function add(): void {
  const list = doc.value.value
  if (!list) return
  list.push({
    uuid: newUuid(),
    key: `layout_${list.length + 1}`,
    i18n: { name: {}, header: {}, footer: {} },
    paper: 'a4',
    orientation: 'portrait',
    layout: { sections: [{ type: 'form_body' }] },
    showLogo: true,
    default: list.length === 0,
  })
}
function setDefault(i: number): void {
  doc.value.value!.forEach((l, j) => (l.default = i === j))
}
function remove(i: number): void {
  const list = doc.value.value!
  const wasDefault = list[i]!.default
  list.splice(i, 1)
  if (wasDefault && list[0]) list[0].default = true
}
function move<T>(list: T[], i: number, d: -1 | 1): void {
  const j = i + d
  if (j < 0 || j >= list.length) return
  ;[list[i], list[j]] = [list[j]!, list[i]!]
}
const err = (path: string) => doc.errors.value[path]
const summary = (l: PrintLayoutDoc) => [t(`print.paper.${l.paper}`), t(`print.orientation.${l.orientation}`), l.default ? t('views.default') : null].filter(Boolean).join(' · ')
</script>

<template>
  <div class="flex flex-col gap-6" data-testid="print-editor">
    <TabIntro :title="t('formconfig.tab.print')" :text="t('print.hint')" />

    <EmptyState v-if="doc.value.value && !doc.value.value.length" icon="pi pi-print" :title="t('print.empty_title')" :description="t('print.empty_text')" testid="print-empty">
      <Button icon="pi pi-plus" :label="t('print.add')" size="small" data-testid="print-add" @click="add" />
    </EmptyState>

    <template v-else-if="doc.value.value">
      <ConfigItem
        v-for="(l, i) in doc.value.value"
        :key="l.uuid"
        :title="labelOf(l.i18n.name, locale, l.key, dl)"
        :subtitle="summary(l)"
        :index="i"
        :count="doc.value.value.length"
        :invalid="Object.keys(doc.errors.value).some((k) => k.startsWith(`layouts.${i}.`))"
        :testid="`print-layout-${i}`"
        @remove="remove(i)"
      >
        <ConfigSection id="print-basics" :title="t('views.section.basics')">
          <I18nInput v-model="l.i18n.name as never" class="w-field-md" :label="t('print.name')" :maxlength="255" :invalid="!!err(`layouts.${i}.i18n.name`)" />
          <ConfigField :label="t('workflow.key')" :for="`pl-key-${i}`" width="sm" :hint="t('formconfig.key_hint')" :error="err(`layouts.${i}.key`)">
            <InputText :id="`pl-key-${i}`" v-model="l.key" size="small" class="ltr-value" maxlength="48" :invalid="!!err(`layouts.${i}.key`)" />
          </ConfigField>
          <SettingSwitch :id="`pl-default-${i}`" :model-value="l.default" :label="t('views.default')" :description="t('print.default_desc')" @update:model-value="setDefault(i)" />
        </ConfigSection>

        <ConfigSection id="print-page" :title="t('print.section_page')">
          <div class="cfg-row">
            <ConfigField :label="t('print.paper_label')" :for="`pl-paper-${i}`" width="sm">
              <Select v-model="l.paper" :input-id="`pl-paper-${i}`" :options="papers" option-label="label" option-value="value" size="small" />
            </ConfigField>
            <ConfigField :label="t('print.orientation_label')" :for="`pl-or-${i}`" width="sm">
              <Select v-model="l.orientation" :input-id="`pl-or-${i}`" :options="orientations" option-label="label" option-value="value" size="small" />
            </ConfigField>
          </div>
          <SettingSwitch :id="`pl-logo-${i}`" v-model="l.showLogo" :label="t('print.show_logo')" :description="t('print.show_logo_desc')" />
        </ConfigSection>

        <ConfigSection id="print-content" :title="t('print.sections')" :count="l.layout.sections.length" :description="t('print.sections_desc')">
          <EmptyState v-if="!l.layout.sections.length" icon="pi pi-list" :title="t('print.no_sections')" :description="t('print.no_sections_text')">
            <Button icon="pi pi-plus" :label="t('print.add_section')" size="small" outlined @click="l.layout.sections.push({ type: 'form_body' })" />
          </EmptyState>
          <template v-else>
            <ol class="cfg-stack list-none p-0 m-0">
              <li v-for="(sec, j) in l.layout.sections" :key="j" class="cfg-row items-end">
                <ConfigField :label="`${j + 1}. ${t('print.section_type')}`" :for="`pl-sec-${i}-${j}`" width="sm" :error="err(`layouts.${i}.layout.sections.${j}.type`)">
                  <Select v-model="sec.type" :input-id="`pl-sec-${i}-${j}`" :options="sectionTypes" option-label="label" option-value="value" size="small" />
                </ConfigField>
                <ConfigField v-if="sec.type === 'fields'" :label="t('print.fields')" :for="`pl-fields-${i}-${j}`" width="lg" :error="err(`layouts.${i}.layout.sections.${j}.fields`)">
                  <MultiSelect v-model="sec.fields" :input-id="`pl-fields-${i}-${j}`" :options="fieldOptions" option-label="label" option-value="value" size="small" display="chip" filter />
                </ConfigField>
                <ConfigField v-else-if="sec.type === 'panel'" :label="t('print.panel')" :for="`pl-panel-${i}-${j}`" width="md" :error="err(`layouts.${i}.layout.sections.${j}.panel`)">
                  <Select v-model="sec.panel" :input-id="`pl-panel-${i}-${j}`" :options="panelOptions" option-label="label" option-value="value" size="small" />
                </ConfigField>
                <div class="flex">
                  <Button icon="pi pi-arrow-up" text rounded size="small" severity="secondary" :aria-label="t('assignment.move_up')" :disabled="j === 0" @click="move(l.layout.sections, j, -1)" />
                  <Button
                    icon="pi pi-arrow-down"
                    text
                    rounded
                    size="small"
                    severity="secondary"
                    :aria-label="t('assignment.move_down')"
                    :disabled="j === l.layout.sections.length - 1"
                    @click="move(l.layout.sections, j, 1)"
                  />
                  <Button icon="pi pi-trash" text rounded size="small" severity="danger" :aria-label="t('workflow.remove')" @click="l.layout.sections.splice(j, 1)" />
                </div>
              </li>
            </ol>
            <Button icon="pi pi-plus" :label="t('print.add_section')" size="small" outlined class="self-start" @click="l.layout.sections.push({ type: 'fields', fields: [] })" />
          </template>
        </ConfigSection>

        <ConfigSection id="print-header-footer" :title="t('print.section_header_footer')" :default-open="false">
          <I18nInput v-model="l.i18n.header as never" class="w-field-lg" :label="t('print.header')" :maxlength="1000" />
          <I18nInput v-model="l.i18n.footer as never" class="w-field-lg" :label="t('print.footer')" :maxlength="1000" />
        </ConfigSection>
      </ConfigItem>
      <Button icon="pi pi-plus" :label="t('print.add')" size="small" outlined class="self-start" data-testid="print-add" @click="add" />
    </template>

    <ErrorList :errors="doc.errors.value" />
    <ConfigSaveBar :dirty="doc.dirty.value" :saving="doc.saving.value" testid="print" @save="doc.save()" @discard="doc.discard()" />
  </div>
</template>
