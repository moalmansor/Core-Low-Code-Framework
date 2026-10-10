<script setup lang="ts">
import Button from 'primevue/button'
import InputText from 'primevue/inputtext'
import Message from 'primevue/message'
import MultiSelect from 'primevue/multiselect'
import Select from 'primevue/select'
import ToggleSwitch from 'primevue/toggleswitch'
import { computed, onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import I18nInput from '@/builder/I18nInput.vue'
import { useBuilder } from '@/builder/useBuilder'
import { useSession } from '@/stores/session'
import { labelOf, newUuid, panelsApi, printLayoutsApi, type PanelDoc, type PrintLayoutDoc } from './api'
import ErrorList from './ErrorList.vue'
import SaveBar from './SaveBar.vue'
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
</script>

<template>
  <div class="flex flex-col gap-3" data-testid="print-editor">
    <SaveBar :dirty="doc.dirty.value" :saving="doc.saving.value" :add-label="t('print.add')" testid="print" @add="add" @save="doc.save()" />
    <Message severity="secondary" :closable="false" class="text-sm">{{ t('print.hint') }}</Message>
    <section v-for="(l, i) in doc.value.value ?? []" :key="l.uuid" class="rounded-lg border border-line p-3 flex flex-col gap-3" :data-testid="`print-layout-${i}`">
      <div class="grid gap-2 md:grid-cols-2">
        <I18nInput v-model="l.i18n.name as never" :label="t('print.name')" :maxlength="255" :invalid="!!err(`layouts.${i}.i18n.name`)" />
        <div class="field">
          <label :for="`pl-key-${i}`">{{ t('workflow.key') }}</label>
          <InputText :id="`pl-key-${i}`" v-model="l.key" size="small" class="ltr-value" maxlength="48" :invalid="!!err(`layouts.${i}.key`)" />
        </div>
      </div>
      <div class="flex flex-wrap items-end gap-4">
        <div class="field w-36">
          <label :for="`pl-paper-${i}`">{{ t('print.paper_label') }}</label>
          <Select v-model="l.paper" :input-id="`pl-paper-${i}`" :options="papers" option-label="label" option-value="value" size="small" />
        </div>
        <div class="field w-40">
          <label :for="`pl-or-${i}`">{{ t('print.orientation_label') }}</label>
          <Select v-model="l.orientation" :input-id="`pl-or-${i}`" :options="orientations" option-label="label" option-value="value" size="small" />
        </div>
        <label class="flex items-center gap-2 text-sm pb-2"><ToggleSwitch v-model="l.showLogo" />{{ t('print.show_logo') }}</label>
        <label class="flex items-center gap-2 text-sm pb-2"><ToggleSwitch :model-value="l.default" @update:model-value="setDefault(i)" />{{ t('views.default') }}</label>
      </div>
      <div class="grid gap-2 md:grid-cols-2">
        <I18nInput v-model="l.i18n.header as never" :label="t('print.header')" :maxlength="1000" />
        <I18nInput v-model="l.i18n.footer as never" :label="t('print.footer')" :maxlength="1000" />
      </div>
      <span class="text-sm font-medium">{{ t('print.sections') }}</span>
      <div v-for="(s, j) in l.layout.sections" :key="j" class="grid gap-2 md:grid-cols-[minmax(0,1fr)_minmax(0,2fr)_auto] items-end">
        <Select
          v-model="s.type"
          :options="sectionTypes"
          option-label="label"
          option-value="value"
          size="small"
          :aria-label="t('print.section_type')"
          :invalid="!!err(`layouts.${i}.layout.sections.${j}.type`)"
        />
        <MultiSelect
          v-if="s.type === 'fields'"
          v-model="s.fields"
          :options="fieldOptions"
          option-label="label"
          option-value="value"
          size="small"
          display="chip"
          :aria-label="t('print.fields')"
          :invalid="!!err(`layouts.${i}.layout.sections.${j}.fields`)"
        />
        <Select
          v-else-if="s.type === 'panel'"
          v-model="s.panel"
          :options="panelOptions"
          option-label="label"
          option-value="value"
          size="small"
          :aria-label="t('print.panel')"
          :invalid="!!err(`layouts.${i}.layout.sections.${j}.panel`)"
        />
        <span v-else />
        <div class="flex gap-1">
          <Button icon="pi pi-arrow-up" text size="small" :aria-label="t('assignment.move_up')" :disabled="j === 0" @click="move(l.layout.sections, j, -1)" />
          <Button icon="pi pi-arrow-down" text size="small" :aria-label="t('assignment.move_down')" :disabled="j === l.layout.sections.length - 1" @click="move(l.layout.sections, j, 1)" />
          <Button icon="pi pi-trash" text severity="danger" size="small" :aria-label="t('workflow.remove')" @click="l.layout.sections.splice(j, 1)" />
        </div>
      </div>
      <div class="flex">
        <Button icon="pi pi-plus" :label="t('print.add_section')" size="small" outlined @click="l.layout.sections.push({ type: 'fields', fields: [] })" />
        <span class="flex-1" />
        <Button icon="pi pi-trash" :label="t('print.remove')" text severity="danger" size="small" @click="remove(i)" />
      </div>
    </section>
    <ErrorList :errors="doc.errors.value" />
  </div>
</template>
