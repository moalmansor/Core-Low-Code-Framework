<script setup lang="ts">
import Button from 'primevue/button'
import Select from 'primevue/select'
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import ExpressionInput from '../conditions/ExpressionInput.vue'
import FormulaEditor from '../conditions/FormulaEditor.vue'
import type { ExpressionScope } from '../conditions/scope'
import FieldSelect from '../FieldSelect.vue'
import I18nInput from '../I18nInput.vue'
import type { EventStep, EventStepType, FieldDef } from '../types'
import { useBuilder } from '../useBuilder'

/**
 * Field events (architecture §14.8): on change, focus or blur, optionally
 * when a condition holds, set another field, reload its options, or show a
 * notification. Running actions and calling webhooks arrive with the actions
 * module and are not offered here.
 */
defineProps<{ scope: ExpressionScope }>()
const field = defineModel<FieldDef>('field', { required: true })
const { t } = useI18n()
const builder = useBuilder()

const events = computed(() => field.value.events!)
const triggers = computed(() => (['change', 'focus', 'blur'] as const).map((x) => ({ value: x, label: t(`builder.events.on_${x}`) })))
const STEP_TYPES: EventStepType[] = ['set_field', 'reload_options', 'notify']
const stepTypes = computed(() => STEP_TYPES.map((x) => ({ value: x, label: t(`builder.events.do_${x}`) })))
const severities = computed(() => (['info', 'success', 'warning', 'error'] as const).map((s) => ({ value: s, label: t(`builder.severity.${s}`) })))

function setType(step: EventStep, type: EventStepType): void {
  step.type = type
  if (type === 'notify') {
    delete step.target
    delete step.value
    step.severity = step.severity ?? 'info'
    step.message = step.message ?? {}
  } else {
    delete step.severity
    delete step.message
    step.target = step.target ?? null
    if (type === 'set_field') step.value = step.value ?? { k: 'lit', t: 'null' }
    else delete step.value
  }
}

function addEvent(): void {
  events.value.push({ on: 'change', when: null, do: [{ type: 'notify', severity: 'info', message: {} }] })
}
const optionFields = (f: FieldDef) => !!builder.typeInfo(f.type)?.options
</script>

<template>
  <div class="flex flex-col gap-3">
    <article v-for="(e, i) in events" :key="i" class="rounded-lg border border-line p-2 flex flex-col gap-2">
      <div class="flex items-center gap-2">
        <label class="text-sm">{{ t('builder.events.when_field') }}</label>
        <Select v-model="e.on" :options="triggers" option-label="label" option-value="value" size="small" class="w-36" />
        <span class="flex-1" />
        <Button size="small" text severity="danger" icon="pi pi-trash" :aria-label="t('builder.remove')" @click="events.splice(i, 1)" />
      </div>
      <ExpressionInput :model-value="e.when ?? null" :scope="scope" expected="boolean" :label="t('builder.events.only_if')" @update:model-value="(a) => (e.when = a ?? null)" />
      <div v-for="(step, j) in e.do" :key="j" class="flex flex-col gap-1 rounded bg-subtle p-2">
        <div class="flex flex-wrap items-center gap-1">
          <Select :model-value="step.type" :options="stepTypes" option-label="label" option-value="value" size="small" class="w-44" @update:model-value="(x: EventStepType) => setType(step, x)" />
          <FieldSelect
            v-if="step.type === 'set_field' || step.type === 'reload_options'"
            v-model="step.target"
            :filter="step.type === 'reload_options' ? optionFields : undefined"
            class="flex-1 min-w-32"
          />
          <Select v-if="step.type === 'notify'" v-model="step.severity" :options="severities" option-label="label" option-value="value" size="small" class="w-32" />
          <span class="flex-1" />
          <Button size="small" text severity="danger" icon="pi pi-times" :disabled="e.do.length === 1" :aria-label="t('builder.remove')" @click="e.do.splice(j, 1)" />
        </div>
        <FormulaEditor v-if="step.type === 'set_field'" v-model="step.value" :scope="scope" compact :label="t('builder.events.value')" />
        <I18nInput v-if="step.type === 'notify'" v-model="step.message" :label="t('builder.events.message')" />
      </div>
      <Button
        v-if="e.do.length < 10"
        size="small"
        text
        icon="pi pi-plus"
        :label="t('builder.events.add_step')"
        class="self-start"
        @click="e.do.push({ type: 'notify', severity: 'info', message: {} })"
      />
    </article>
    <Button v-if="events.length < 20" size="small" severity="secondary" icon="pi pi-plus" :label="t('builder.events.add')" class="self-start" @click="addEvent" />
  </div>
</template>
