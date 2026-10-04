<script setup lang="ts">
import Button from 'primevue/button'
import InputText from 'primevue/inputtext'
import Select from 'primevue/select'
import ToggleSwitch from 'primevue/toggleswitch'
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import type { ConditionDef, OwnerType, TargetType } from '../types'
import { useBuilder } from '../useBuilder'
import { newUuid } from '../uuid'
import EffectsEditor from './EffectsEditor.vue'
import ExpressionInput from './ExpressionInput.vue'
import type { ExpressionScope } from './scope'

/**
 * The rules owned by one element (specification §4.7): IF a condition THEN
 * effects, ELSE other effects; when they are evaluated, and whether they also
 * run in the browser or on the server only.
 */
const props = defineProps<{ owner: { type: OwnerType; uuid: string }; scope: ExpressionScope }>()
const { t } = useI18n()
const builder = useBuilder()

const rules = computed(() =>
  (builder.doc?.conditions ?? []).filter((c) => c.owner.type === props.owner.type && c.owner.uuid === props.owner.uuid).sort((a, b) => (a.order ?? 0) - (b.order ?? 0)),
)
const defaultTarget = computed<{ type: TargetType; uuid: string } | null>(() => (props.owner.type === 'form' ? null : { type: props.owner.type as TargetType, uuid: props.owner.uuid }))
const evaluateOptions = computed(() => [
  { value: 'always', label: t('builder.condition.evaluate_always') },
  { value: 'change', label: t('builder.condition.evaluate_change') },
])
const runtimeOptions = computed(() => [
  { value: 'client_and_server', label: t('builder.condition.runtime_both') },
  { value: 'server_only', label: t('builder.condition.runtime_server') },
])

function add(): void {
  const d = builder.doc
  if (!d) return
  const order = rules.value.reduce((m, c) => Math.max(m, (c.order ?? 0) + 1), 0)
  const rule: ConditionDef = {
    uuid: newUuid(),
    owner: { ...props.owner },
    name: null,
    when: { k: 'lit', t: 'boolean', v: true },
    effects: defaultTarget.value ? [{ effect: 'show', target: { ...defaultTarget.value } }] : [],
    else: defaultTarget.value ? [{ effect: 'hide', target: { ...defaultTarget.value } }] : [],
    evaluateOn: 'always',
    runtime: 'client_and_server',
    order,
    active: true,
  }
  builder.mutate((doc) => doc.conditions.push(rule))
}

function remove(rule: ConditionDef): void {
  builder.mutate((doc) => {
    doc.conditions = doc.conditions.filter((c) => c.uuid !== rule.uuid)
    for (const f of doc.fields) for (const o of f.options?.static ?? []) if (o.condition === rule.uuid) o.condition = null
  })
}

function move(rule: ConditionDef, delta: -1 | 1): void {
  const list = rules.value
  const i = list.indexOf(rule)
  const j = i + delta
  if (j < 0 || j >= list.length) return
  builder.mutate(() => {
    const reordered = [...list]
    reordered.splice(i, 1)
    reordered.splice(j, 0, rule)
    reordered.forEach((c, n) => (c.order = n))
  })
}
</script>

<template>
  <div class="flex flex-col gap-3">
    <p class="text-xs text-muted-color">{{ t('builder.condition.hint') }}</p>
    <article v-for="(rule, i) in rules" :key="rule.uuid" class="rounded-lg border border-surface-200 dark:border-surface-700 p-2 flex flex-col gap-2" :data-testid="`rule-${i}`">
      <div class="flex flex-wrap items-center gap-2">
        <InputText v-model="rule.name" size="small" class="flex-1 min-w-32" :placeholder="t('builder.condition.name')" :aria-label="t('builder.condition.name')" maxlength="255" />
        <label class="flex items-center gap-1 text-sm"><ToggleSwitch :model-value="rule.active ?? true" @update:model-value="(v: boolean) => (rule.active = v)" />{{ t('builder.condition.active') }}</label>
        <Button size="small" text icon="pi pi-arrow-up" :disabled="i === 0" :aria-label="t('builder.move_up')" @click="move(rule, -1)" />
        <Button size="small" text icon="pi pi-arrow-down" :disabled="i === rules.length - 1" :aria-label="t('builder.move_down')" @click="move(rule, 1)" />
        <Button size="small" text severity="danger" icon="pi pi-trash" :aria-label="t('builder.condition.remove')" @click="remove(rule)" />
      </div>
      <ExpressionInput :model-value="rule.when" :scope="scope" expected="boolean" :allow-empty="false" :label="t('builder.condition.if')" @update:model-value="(a) => a && (rule.when = a)" />
      <EffectsEditor v-model="rule.effects" :scope="scope" :label="t('builder.condition.then')" :default-target="defaultTarget" />
      <EffectsEditor :model-value="rule.else ?? []" :scope="scope" :label="t('builder.condition.else')" :default-target="defaultTarget" @update:model-value="(v) => (rule.else = v)" />
      <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
        <label class="field"
          ><span class="text-sm">{{ t('builder.condition.evaluate_on') }}</span>
          <Select :model-value="rule.evaluateOn ?? 'always'" :options="evaluateOptions" option-label="label" option-value="value" size="small" @update:model-value="(v) => (rule.evaluateOn = v)"
        /></label>
        <label class="field"
          ><span class="text-sm">{{ t('builder.condition.runtime') }}</span>
          <Select :model-value="rule.runtime ?? 'client_and_server'" :options="runtimeOptions" option-label="label" option-value="value" size="small" @update:model-value="(v) => (rule.runtime = v)"
        /></label>
      </div>
    </article>
    <Button size="small" icon="pi pi-plus" :label="t('builder.condition.add')" severity="secondary" class="self-start" data-testid="add-rule" @click="add" />
  </div>
</template>
