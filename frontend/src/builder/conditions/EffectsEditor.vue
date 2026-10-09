<script setup lang="ts">
import Button from 'primevue/button'
import Select from 'primevue/select'
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import I18nInput from '../I18nInput.vue'
import type { Effect, EffectKind, TargetType } from '../types'
import { useBuilder } from '../useBuilder'
import FormulaEditor from './FormulaEditor.vue'
import { pick, type ExpressionScope } from './scope'

/** The effects of a rule (architecture §14.3); actions arrive with the actions module. */
const props = defineProps<{ scope: ExpressionScope; label: string; defaultTarget: { type: TargetType; uuid: string } | null }>()
const effects = defineModel<Effect[]>({ required: true })
const { t } = useI18n()
const builder = useBuilder()

const KINDS: EffectKind[] = ['show', 'hide', 'enable', 'disable', 'read_only', 'require', 'set_value', 'clear_value', 'reload_options', 'show_message', 'block_submit']
const TARGETED: EffectKind[] = ['show', 'hide', 'enable', 'disable', 'read_only', 'require', 'set_value', 'clear_value', 'reload_options']
const kindOptions = computed(() => KINDS.map((k) => ({ value: k, label: t(`builder.effect.${k}`) })))
const severities = computed(() => (['info', 'success', 'warning', 'error'] as const).map((s) => ({ value: s, label: t(`builder.severity.${s}`) })))

const targets = computed(() => {
  const d = builder.doc
  if (!d) return []
  const out: { value: string; label: string; type: TargetType; options: boolean }[] = []
  for (const g of d.groups) out.push({ value: `group:${g.uuid}`, label: `${t(`builder.group.${g.type}`)}: ${pick(g.i18n?.title, builder.locale, g.key)}`, type: 'group', options: false })
  for (const f of d.fields) {
    const name = pick(f.i18n?.label, builder.locale, f.key)
    out.push({ value: `field:${f.uuid}`, label: `${name} (${f.key})`, type: 'field', options: !!builder.typeInfo(f.type)?.options })
    for (const o of f.options?.static ?? []) out.push({ value: `option:${o.uuid}`, label: `${name} › ${pick(o.i18n?.label, builder.locale, o.value)}`, type: 'option', options: false })
  }
  return out
})

function targetsFor(e: Effect) {
  if (e.effect === 'reload_options') return targets.value.filter((x) => x.type === 'field' && x.options)
  if (e.effect === 'set_value' || e.effect === 'clear_value' || e.effect === 'require' || e.effect === 'read_only') return targets.value.filter((x) => x.type === 'field')
  return targets.value
}

function setKind(e: Effect, kind: EffectKind): void {
  e.effect = kind
  if (TARGETED.includes(kind)) {
    e.target = e.target ?? (props.defaultTarget ? { ...props.defaultTarget } : undefined)
    if (e.target && !targetsFor(e).some((x) => x.value === `${e.target!.type}:${e.target!.uuid}`)) e.target = undefined
  } else delete e.target
  if (kind === 'set_value') e.value = e.value ?? { k: 'lit', t: 'null' }
  else delete e.value
  if (kind === 'show_message' || kind === 'block_submit') e.message = e.message ?? {}
  else delete e.message
  if (kind === 'show_message') e.severity = e.severity ?? 'info'
  else delete e.severity
}

function setTarget(e: Effect, value: string | null): void {
  if (!value) {
    delete e.target
    return
  }
  const [type, uuid] = value.split(':') as [TargetType, string]
  e.target = { type, uuid }
}

function add(): void {
  effects.value.push(props.defaultTarget ? { effect: 'show', target: { ...props.defaultTarget } } : { effect: 'show_message', severity: 'info', message: {} })
}
</script>

<template>
  <div class="flex flex-col gap-2">
    <div class="text-sm font-medium">{{ label }}</div>
    <div v-for="(e, i) in effects" :key="i" class="flex flex-col gap-1 rounded border border-surface-200 dark:border-surface-700 p-2">
      <div class="flex flex-wrap items-center gap-1">
        <Select
          :model-value="e.effect"
          :options="kindOptions"
          option-label="label"
          option-value="value"
          size="small"
          class="w-44"
          :aria-label="t('builder.effect.kind')"
          @update:model-value="(k: EffectKind) => setKind(e, k)"
        />
        <Select
          v-if="TARGETED.includes(e.effect)"
          :model-value="e.target ? `${e.target.type}:${e.target.uuid}` : null"
          :options="targetsFor(e)"
          option-label="label"
          option-value="value"
          filter
          size="small"
          class="flex-1 min-w-48"
          :invalid="!e.target"
          :placeholder="t('builder.effect.pick_target')"
          :aria-label="t('builder.effect.target')"
          @update:model-value="(v: string | null) => setTarget(e, v)"
        />
        <Select
          v-if="e.effect === 'show_message'"
          v-model="e.severity"
          :options="severities"
          option-label="label"
          option-value="value"
          size="small"
          class="w-32"
          :aria-label="t('builder.effect.severity')"
        />
        <span class="flex-1" />
        <Button size="small" text severity="danger" icon="pi pi-times" :aria-label="t('builder.effect.remove')" @click="effects.splice(i, 1)" />
      </div>
      <FormulaEditor
        v-if="e.effect === 'set_value'"
        :model-value="e.value"
        :scope="scope"
        :label="t('builder.effect.value')"
        :allow-empty="false"
        compact
        @update:model-value="(a) => a && (e.value = a)"
      />
      <I18nInput v-if="e.effect === 'show_message' || e.effect === 'block_submit'" v-model="e.message" :label="t('builder.effect.message')" />
    </div>
    <Button size="small" text icon="pi pi-plus" :label="t('builder.effect.add')" class="self-start" @click="add" />
  </div>
</template>
