<script setup lang="ts">
import Button from 'primevue/button'
import InputNumber from 'primevue/inputnumber'
import Select from 'primevue/select'
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import ExpressionInput from '@/builder/conditions/ExpressionInput.vue'
import type { ExpressionScope } from '@/builder/conditions/scope'
import I18nInput from '@/builder/I18nInput.vue'
import ConfigField from '@/components/config/ConfigField.vue'
import ConfigSection from '@/components/config/ConfigSection.vue'
import RichTextInput from '@/runtime/fields/RichTextInput.vue'
import { useSession } from '@/stores/session'
import HomeLinkRow from './HomeLinkRow.vue'
import type { HomeWidgetDoc } from './api'

/** The settings of one home screen widget; nested properties are changed in place. */
const props = defineProps<{ index: number; errors: Record<string, string>; reports: { uuid: string; label: string }[] | null; formOptions: { uuid: string; label: string }[] }>()
/** The widget being edited; nested properties are changed in place. */
const widget = defineModel<HomeWidgetDoc>('widget', { required: true })
const emit = defineEmits<{ remove: [] }>()
const { t } = useI18n()
const session = useSession()
const locales = computed(() => session.boot?.locales ?? [])
const err = (k: string) => props.errors[k]
/** Widget rules refer to the person only. */
const personScope: ExpressionScope = { fields: [], repeaters: [], rows: null }
const widths = computed(() => [3, 4, 6, 8, 12].map((w) => ({ value: w, label: t(`rep.width.${w}`) })))
const heights = computed(() => [1, 2, 3, 4].map((h) => ({ value: h, label: t('rep.height_n', { n: h }) })))
function addLink(): void {
  const w = widget.value
  w.links.push(w.type === 'shortcuts' ? { form: undefined, action: 'new', i18n: { label: {} } } : { url: '', i18n: { label: {} } })
}
function removeLink(j: number): void {
  widget.value.links.splice(j, 1)
}
function setText(code: string, html: unknown): void {
  const w = widget.value
  const next = { ...w.i18n.text }
  if (typeof html === 'string' && html.trim() !== '') next[code] = html
  else delete next[code]
  w.i18n.text = next
}
</script>

<template>
  <ConfigSection id="home-widget" :title="t('xp.section.widget', { n: index + 1 })">
    <I18nInput v-model="widget.i18n.title" class="w-field-md" :label="t('xp.widget_title')" :maxlength="255" />
    <ConfigField v-if="widget.type === 'report'" :label="t('xp.type.report')" for="widget-report" width="md" :error="err(`widgets.${index}.report`)" required>
      <Select v-if="reports" v-model="widget.report" input-id="widget-report" :options="reports" option-label="label" option-value="uuid" filter size="small" :placeholder="t('xp.choose')" />
      <small v-else class="text-muted-color">{{ t('xp.reports_unavailable') }}</small>
    </ConfigField>
    <ConfigField v-if="widget.type === 'my_records' || widget.type === 'embedded_table'" :label="t('xp.form')" for="widget-form" width="md" :error="err(`widgets.${index}.form`)" required>
      <Select
        v-model="widget.form"
        input-id="widget-form"
        :options="formOptions"
        option-label="label"
        option-value="uuid"
        filter
        size="small"
        :placeholder="t('xp.choose')"
        data-testid="widget-form"
      />
    </ConfigField>
    <ConfigField v-if="['my_work', 'my_records', 'embedded_table', 'recent_activity', 'announcements'].includes(widget.type)" :label="t('xp.limit')" for="widget-limit" width="xs">
      <InputNumber v-model="widget.limit" input-id="widget-limit" :min="1" :max="50" size="small" />
    </ConfigField>
    <div v-if="widget.type === 'shortcuts' || widget.type === 'pinned_links'" class="flex flex-col gap-3">
      <HomeLinkRow
        v-for="(_l, j) in widget.links"
        :key="j"
        v-model:link="widget.links[j]!"
        :shortcut="widget.type === 'shortcuts'"
        :id-suffix="String(j)"
        :error="err(`widgets.${index}.links.${j}`)"
        :form-options="formOptions"
        @remove="removeLink(j)"
      />
      <small v-if="err(`widgets.${index}.links`)" class="text-danger">{{ err(`widgets.${index}.links`) }}</small>
      <div><Button icon="pi pi-plus" :label="t('xp.add_link')" size="small" outlined @click="addLink()" /></div>
    </div>
    <template v-if="widget.type === 'html'">
      <div v-for="l in locales" :key="l.code" class="field">
        <label :for="`widget-text-${l.code}`">{{ t('xp.content_in', { language: l.native_name }) }}</label>
        <RichTextInput :input-id="`widget-text-${l.code}`" :model-value="widget.i18n.text[l.code] ?? ''" :dir="l.direction" @update:model-value="setText(l.code, $event)" />
      </div>
      <small v-if="err(`widgets.${index}.text`)" class="text-danger">{{ err(`widgets.${index}.text`) }}</small>
    </template>
    <div class="cfg-row">
      <ConfigField :label="t('rep.width_label')" for="widget-w" width="sm" :error="err(`widgets.${index}.layout`)">
        <Select v-model="widget.layout.w" input-id="widget-w" :options="widths" option-label="label" option-value="value" size="small" />
      </ConfigField>
      <ConfigField :label="t('rep.height_label')" for="widget-h" width="sm">
        <Select v-model="widget.layout.h" input-id="widget-h" :options="heights" option-label="label" option-value="value" size="small" />
      </ConfigField>
    </div>
    <div class="field">
      <ExpressionInput v-model="widget.condition" :scope="personScope" expected="boolean" :label="t('xp.widget_rule')" />
      <small class="text-muted-color">{{ t('xp.widget_rule_hint') }}</small>
      <small v-if="err(`widgets.${index}.condition`)" class="text-danger">{{ err(`widgets.${index}.condition`) }}</small>
    </div>
    <div><Button icon="pi pi-trash" :label="t('xp.remove_widget')" size="small" severity="danger" text @click="emit('remove')" /></div>
  </ConfigSection>
</template>
