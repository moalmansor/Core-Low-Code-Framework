<script setup lang="ts">
import InputNumber from 'primevue/inputnumber'
import InputText from 'primevue/inputtext'
import Select from 'primevue/select'
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { printExpression } from './print'
import type { Operand } from './ruleModel'
import { CONTEXT_ATTRIBUTES, RECORD_ATTRIBUTES, USER_ATTRIBUTES, pick, type ExpressionScope } from './scope'
import FormulaEditor from './FormulaEditor.vue'
import { useBuilder } from '../useBuilder'
import type { Ast } from '../types'

/**
 * One operand of a rule row: a field (current, previous value, or repeater
 * row), a user / context / record attribute, a literal value, a date
 * relative to today, now, or a custom expression.
 */
const props = withDefaults(defineProps<{ scope: ExpressionScope; valueType?: string; options?: { value: string; label: Record<string, string> }[]; allowRefs?: boolean }>(), {
  valueType: 'any',
  options: () => [],
  allowRefs: true,
})
const model = defineModel<Operand>({ required: true })
const { t } = useI18n()
const builder = useBuilder()

type Kind = 'field' | 'old' | 'user' | 'context' | 'system' | 'value' | 'today' | 'now' | 'expr'

const kind = computed<Kind>(() => {
  const o = model.value
  if (o.kind === 'ref') {
    if (o.scope === 'old') return 'old'
    if (o.scope === 'user') return 'user'
    if (o.scope === 'context') return 'context'
    if (o.scope === 'record' && RECORD_ATTRIBUTES[o.path[0] ?? ''] && !props.scope.fields.some((f) => f.key === o.path[0])) return 'system'
    return 'field'
  }
  if (o.kind === 'lit') return 'value'
  return o.kind
})

const kindOptions = computed(() =>
  (['value', 'field', 'old', 'user', 'context', 'system', 'today', 'now', 'expr'] as Kind[]).filter((k) => props.allowRefs || ['value', 'today', 'now'].includes(k)).map((k) => ({ value: k, label: t(`builder.operand.${k}`) })),
)

function literalType(): 'text' | 'number' | 'boolean' | 'date' | 'datetime' | 'time' {
  const v = props.valueType
  if (v === 'number' || v === 'boolean' || v === 'date' || v === 'datetime' || v === 'time') return v
  return 'text'
}

function setKind(k: Kind): void {
  const first = props.scope.fields.find((f) => (props.scope.rows ? f.repeater === props.scope.rows || f.repeater === null : f.repeater === null))
  switch (k) {
    case 'field':
      model.value = { kind: 'ref', scope: 'record', path: [first?.key ?? ''] }
      break
    case 'old':
      model.value = { kind: 'ref', scope: 'old', path: [first?.key ?? ''] }
      break
    case 'user':
      model.value = { kind: 'ref', scope: 'user', path: ['department'] }
      break
    case 'context':
      model.value = { kind: 'ref', scope: 'context', path: ['mode'] }
      break
    case 'system':
      model.value = { kind: 'ref', scope: 'record', path: ['status'] }
      break
    case 'value': {
      const lt = literalType()
      model.value = { kind: 'lit', t: lt, v: lt === 'boolean' ? true : lt === 'number' ? '0' : '' }
      break
    }
    case 'today':
      model.value = { kind: 'today', offset: 0 }
      break
    case 'now':
      model.value = { kind: 'now' }
      break
    case 'expr':
      model.value = { kind: 'expr', ast: { k: 'lit', t: 'null' } }
      break
  }
}

const fieldOptions = computed(() =>
  props.scope.fields
    .filter((f) => f.repeater === null || f.repeater === props.scope.rows)
    .map((f) => ({ value: f.key, label: `${pick(f.label, builder.locale, f.key)} (${f.key})` }))
    .concat(props.scope.repeaters.map((r) => ({ value: r.key, label: `${pick(r.label, builder.locale, r.key)} (${r.key})` }))),
)

const refPath = computed(() => (model.value.kind === 'ref' ? model.value.path : []))

function setPath(path: string[]): void {
  const o = model.value
  if (o.kind !== 'ref') return
  model.value = { kind: 'ref', scope: o.scope, path: path.filter((p) => p !== '') }
}

function setFieldKey(key: string): void {
  const o = model.value
  if (o.kind !== 'ref') return
  const inRows = props.scope.rows !== null && props.scope.fields.some((f) => f.key === key && f.repeater === props.scope.rows)
  model.value = { kind: 'ref', scope: o.scope === 'old' ? 'old' : inRows ? 'row' : 'record', path: [key] }
}

const userAttr = computed(() => (refPath.value[0] === 'attributes' ? 'attributes' : (refPath.value[0] ?? 'department')))
const userOptions = computed(() => [...Object.keys(USER_ATTRIBUTES), 'attributes'].map((k) => ({ value: k, label: t(`builder.user_attr.${k}`) })))
const contextAttr = computed(() => (refPath.value[0] === 'param' ? 'param' : (refPath.value[0] ?? 'mode')))
const contextOptions = computed(() => [...Object.keys(CONTEXT_ATTRIBUTES), 'param'].map((k) => ({ value: k, label: t(`builder.context_attr.${k}`) })))
const systemOptions = computed(() => Object.keys(RECORD_ATTRIBUTES).map((k) => ({ value: k, label: t(`builder.record_attr.${k}`) })))

const literalTypes = computed(() => ['text', 'number', 'boolean', 'date', 'datetime', 'time', 'null'].map((v) => ({ value: v, label: t(`builder.value_type.${v}`) })))

const lit = computed(() => (model.value.kind === 'lit' ? model.value : null))

function setLiteral(v: string | boolean | null): void {
  const o = model.value
  if (o.kind !== 'lit') return
  model.value = { kind: 'lit', t: o.t, v }
}

function setLiteralType(type: 'text' | 'number' | 'boolean' | 'date' | 'datetime' | 'time' | 'null'): void {
  model.value = { kind: 'lit', t: type, v: type === 'boolean' ? true : type === 'null' ? null : type === 'number' ? '0' : '' }
}

/** datetime literals are UTC instants; the native input works in minutes. */
function datetimeInput(v: string | boolean | null): string {
  return typeof v === 'string' ? v.replace(/:\d\dZ$/, '').replace(/Z$/, '') : ''
}

const numberInvalid = computed(() => lit.value?.t === 'number' && !/^-?[0-9]{1,34}(\.[0-9]{1,34})?$/.test(String(lit.value.v ?? '')))

const exprText = computed(() => (model.value.kind === 'expr' ? printExpression(model.value.ast) : ''))
function setExpr(ast: Ast | null | undefined): void {
  model.value = { kind: 'expr', ast: ast ?? { k: 'lit', t: 'null' } }
}
const optionChoices = computed(() => props.options.map((o) => ({ value: o.value, label: `${pick(o.label, builder.locale, o.value)} (${o.value})` })))
</script>

<template>
  <div class="flex flex-wrap items-start gap-1 min-w-0">
    <Select :model-value="kind" :options="kindOptions" option-label="label" option-value="value" size="small" class="w-32" :aria-label="t('builder.operand.kind')" @update:model-value="setKind" />

    <template v-if="kind === 'field' || kind === 'old'">
      <Select
        :model-value="refPath[0]"
        :options="fieldOptions"
        option-label="label"
        option-value="value"
        filter
        editable
        size="small"
        class="w-48"
        :aria-label="t('builder.operand.field')"
        @update:model-value="(v: string) => setFieldKey(v)"
      />
      <InputText
        :model-value="refPath.slice(1).join('.')"
        size="small"
        class="w-32 ltr-value"
        :placeholder="t('builder.operand.path_more')"
        :aria-label="t('builder.operand.path_more')"
        @update:model-value="(v: string | undefined) => setPath([refPath[0] ?? '', ...(v ?? '').split('.')])"
      />
    </template>

    <template v-else-if="kind === 'user'">
      <Select
        :model-value="userAttr"
        :options="userOptions"
        option-label="label"
        option-value="value"
        size="small"
        class="w-44"
        :aria-label="t('builder.operand.user')"
        @update:model-value="(v: string) => setPath(v === 'attributes' ? ['attributes', refPath[1] ?? ''] : [v])"
      />
      <InputText
        v-if="userAttr === 'attributes'"
        :model-value="refPath[1] ?? ''"
        size="small"
        class="w-32 ltr-value"
        :placeholder="t('builder.operand.attribute_key')"
        :aria-label="t('builder.operand.attribute_key')"
        @update:model-value="(v: string | undefined) => setPath(['attributes', v ?? ''])"
      />
    </template>

    <template v-else-if="kind === 'context'">
      <Select
        :model-value="contextAttr"
        :options="contextOptions"
        option-label="label"
        option-value="value"
        size="small"
        class="w-40"
        :aria-label="t('builder.operand.context')"
        @update:model-value="(v: string) => setPath(v === 'param' ? ['param', refPath[1] ?? ''] : [v])"
      />
      <InputText
        v-if="contextAttr === 'param'"
        :model-value="refPath[1] ?? ''"
        size="small"
        class="w-32 ltr-value"
        :placeholder="t('builder.operand.param_key')"
        :aria-label="t('builder.operand.param_key')"
        @update:model-value="(v: string | undefined) => setPath(['param', v ?? ''])"
      />
    </template>

    <Select
      v-else-if="kind === 'system'"
      :model-value="refPath[0]"
      :options="systemOptions"
      option-label="label"
      option-value="value"
      size="small"
      class="w-40"
      :aria-label="t('builder.operand.system')"
      @update:model-value="(v: string) => setPath([v])"
    />

    <template v-else-if="kind === 'value' && lit">
      <Select :model-value="lit.t" :options="literalTypes" option-label="label" option-value="value" size="small" class="w-28" :aria-label="t('builder.operand.value_type')" @update:model-value="setLiteralType" />
      <Select
        v-if="lit.t === 'boolean'"
        :model-value="lit.v"
        :options="[
          { value: true, label: t('builder.true') },
          { value: false, label: t('builder.false') },
        ]"
        option-label="label"
        option-value="value"
        size="small"
        class="w-28"
        :aria-label="t('builder.operand.value')"
        @update:model-value="setLiteral"
      />
      <Select
        v-else-if="lit.t === 'text' && optionChoices.length"
        :model-value="lit.v"
        :options="optionChoices"
        option-label="label"
        option-value="value"
        editable
        size="small"
        class="w-44"
        :aria-label="t('builder.operand.value')"
        @update:model-value="setLiteral"
      />
      <InputText
        v-else-if="lit.t === 'date' || lit.t === 'time'"
        :type="lit.t"
        :step="lit.t === 'time' ? 1 : undefined"
        :model-value="String(lit.v ?? '')"
        size="small"
        class="w-40 ltr-value"
        :aria-label="t('builder.operand.value')"
        @update:model-value="(v: string | undefined) => setLiteral(lit!.t === 'time' && v && v.length === 5 ? `${v}:00` : (v ?? ''))"
      />
      <InputText
        v-else-if="lit.t === 'datetime'"
        type="datetime-local"
        :model-value="datetimeInput(lit.v)"
        size="small"
        class="w-52 ltr-value"
        :aria-label="t('builder.operand.value_utc')"
        @update:model-value="(v: string | undefined) => setLiteral(v ? `${v.length === 16 ? `${v}:00` : v}Z` : '')"
      />
      <InputText
        v-else-if="lit.t !== 'null'"
        :model-value="String(lit.v ?? '')"
        :invalid="numberInvalid"
        size="small"
        :class="['w-44', lit.t === 'number' ? 'ltr-value' : '']"
        :inputmode="lit.t === 'number' ? 'decimal' : undefined"
        :aria-label="t('builder.operand.value')"
        @update:model-value="(v: string | undefined) => setLiteral(v ?? '')"
      />
    </template>

    <label v-else-if="kind === 'today' && model.kind === 'today'" class="flex items-center gap-1 text-sm">
      {{ t('builder.operand.today_plus') }}
      <InputNumber :model-value="model.offset" show-buttons :min="-36500" :max="36500" size="small" input-class="w-20" @update:model-value="(v: number | null) => (model = { kind: 'today', offset: v ?? 0 })" />
      {{ t('builder.operand.days') }}
    </label>

    <div v-else-if="kind === 'expr'" class="w-full">
      <FormulaEditor :model-value="model.kind === 'expr' ? model.ast : null" :scope="scope" :initial-text="exprText" compact @update:model-value="setExpr" />
    </div>
  </div>
</template>
