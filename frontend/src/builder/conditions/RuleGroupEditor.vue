<script setup lang="ts">
import Button from 'primevue/button'
import Checkbox from 'primevue/checkbox'
import Select from 'primevue/select'
import SelectButton from 'primevue/selectbutton'
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import OperandInput from './OperandInput.vue'
import FormulaEditor from './FormulaEditor.vue'
import { emptyGroup, newRule, OPERATOR_ARITY, SUBJECTLESS, type Operand, type Operator, type RuleGroup, type RuleLeaf } from './ruleModel'
import { CONTEXT_ATTRIBUTES, RECORD_ATTRIBUTES, USER_ATTRIBUTES, type ExpressionScope } from './scope'
import type { Ast } from '../types'

/** One AND/OR group of the visual rule builder, with nested groups (recursive). */
const props = withDefaults(defineProps<{ scope: ExpressionScope; depth?: number }>(), { depth: 0 })
const group = defineModel<RuleGroup>('group', { required: true })
const emit = defineEmits<{ remove: [] }>()
const { t } = useI18n()

const joinOptions = computed(() => [
  { value: 'and', label: t('builder.rule.all') },
  { value: 'or', label: t('builder.rule.any') },
])

function operandType(o: Operand | null): string {
  if (!o) return 'any'
  switch (o.kind) {
    case 'ref': {
      const first = o.path[0] ?? ''
      if (o.scope === 'user') return o.path.length === 1 ? (USER_ATTRIBUTES[first] ?? 'any') : 'any'
      if (o.scope === 'context') return CONTEXT_ATTRIBUTES[first] ?? 'text'
      const f = props.scope.fields.find((x) => x.key === first)
      if (f) return o.path.length === 1 ? f.valueType : 'any'
      if (props.scope.repeaters.some((r) => r.key === first)) return 'list<record>'
      if (o.scope === 'record' && RECORD_ATTRIBUTES[first]) return RECORD_ATTRIBUTES[first]!
      return 'any'
    }
    case 'lit':
      return o.t
    case 'today':
      return 'date'
    case 'now':
      return 'datetime'
    default:
      return 'any'
  }
}

const ORDERED: Operator[] = ['eq', 'neq', 'gt', 'gte', 'lt', 'lte', 'between', 'is_empty', 'is_not_empty', 'in_list', 'not_in_list', 'changed', 'changed_from_to']
const TEXTUAL: Operator[] = ['eq', 'neq', 'contains', 'not_contains', 'starts_with', 'ends_with', 'matches', 'is_empty', 'is_not_empty', 'in_list', 'not_in_list', 'changed', 'changed_from_to']

function operatorsFor(leaf: RuleLeaf): { value: Operator; label: string }[] {
  const type = operandType(leaf.left)
  let ops: Operator[]
  if (type === 'text') ops = TEXTUAL
  else if (['number', 'date', 'datetime', 'time', 'duration'].includes(type)) ops = ORDERED
  else if (type === 'boolean') ops = ['eq', 'neq', 'is_empty', 'is_not_empty', 'changed']
  else if (type.startsWith('list<')) ops = ['is_empty', 'is_not_empty', 'eq', 'neq', 'changed']
  else ops = [...new Set([...ORDERED, ...TEXTUAL])]
  return [...ops, ...SUBJECTLESS].map((value) => ({ value, label: t(`builder.operator.${value}`) }))
}

function rightCount(leaf: RuleLeaf): number {
  const n = OPERATOR_ARITY[leaf.operator]
  return n === 'list' ? leaf.right.length : n
}

function defaultValue(leaf: RuleLeaf): Operand {
  const type = operandType(leaf.left)
  if (type === 'number') return { kind: 'lit', t: 'number', v: '0' }
  if (type === 'boolean') return { kind: 'lit', t: 'boolean', v: true }
  if (type === 'date') return { kind: 'today', offset: 0 }
  if (type === 'datetime') return { kind: 'now' }
  if (type === 'time') return { kind: 'lit', t: 'time', v: '09:00:00' }
  return { kind: 'lit', t: 'text', v: '' }
}

function setOperator(leaf: RuleLeaf, op: Operator): void {
  leaf.operator = op
  if (op === 'expr') {
    leaf.ast = leaf.ast ?? { k: 'lit', t: 'boolean', v: true }
    return
  }
  if (op === 'has_role') {
    leaf.left = null
    leaf.right = [{ kind: 'lit', t: 'text', v: '' }]
    return
  }
  if (op === 'in_department') {
    leaf.left = null
    leaf.right = [
      { kind: 'lit', t: 'text', v: '' },
      { kind: 'lit', t: 'boolean', v: false },
    ]
    return
  }
  if (!leaf.left) leaf.left = { kind: 'ref', scope: 'record', path: [props.scope.fields[0]?.key ?? ''] }
  const n = OPERATOR_ARITY[op]
  const want = n === 'list' ? Math.max(1, leaf.right.length) : n
  const next = leaf.right.slice(0, want)
  while (next.length < want) next.push(defaultValue(leaf))
  leaf.right = next
}

function setLeft(leaf: RuleLeaf, o: Operand): void {
  const before = operandType(leaf.left)
  leaf.left = o
  if (operandType(o) !== before) {
    const allowed = operatorsFor(leaf).map((x) => x.value)
    if (!allowed.includes(leaf.operator)) leaf.operator = 'eq'
    setOperator(leaf, leaf.operator)
    leaf.right = leaf.right.map(() => defaultValue(leaf))
  }
}

function leftOptions(leaf: RuleLeaf) {
  const first = leaf.left?.kind === 'ref' ? leaf.left.path[0] : undefined
  return props.scope.fields.find((f) => f.key === first)?.options ?? []
}

function addRule(): void {
  group.value.children.push(newRule({ kind: 'ref', scope: 'record', path: [props.scope.fields.find((f) => f.repeater === null)?.key ?? ''] }))
}
function addGroup(): void {
  const g = emptyGroup(group.value.op === 'and' ? 'or' : 'and')
  g.children.push(newRule({ kind: 'ref', scope: 'record', path: [props.scope.fields.find((f) => f.repeater === null)?.key ?? ''] }))
  group.value.children.push(g)
}
function removeAt(i: number): void {
  group.value.children.splice(i, 1)
}
function setLeafAst(leaf: RuleLeaf, ast: Ast | null): void {
  leaf.ast = ast ?? { k: 'lit', t: 'boolean', v: true }
}
</script>

<template>
  <div
    :class="[
      'rounded-md border p-2 flex flex-col gap-2',
      depth % 2 === 0 ? 'border-surface-300 dark:border-surface-600' : 'border-primary-200 dark:border-primary-800 bg-surface-50 dark:bg-surface-900',
    ]"
  >
    <div class="flex flex-wrap items-center gap-2">
      <label class="flex items-center gap-1 text-sm"><Checkbox v-model="group.negate" binary />{{ t('builder.rule.not') }}</label>
      <SelectButton v-model="group.op" :options="joinOptions" option-label="label" option-value="value" :allow-empty="false" size="small" :aria-label="t('builder.rule.join')" />
      <span class="text-xs text-muted-color">{{ t('builder.rule.of_these') }}</span>
      <span class="flex-1" />
      <Button v-if="depth > 0" size="small" text severity="danger" icon="pi pi-trash" :aria-label="t('builder.rule.remove_group')" @click="emit('remove')" />
    </div>
    <p v-if="group.children.length === 0" class="text-xs text-muted-color">{{ t('builder.rule.always') }}</p>
    <template v-for="(child, i) in group.children" :key="child.id">
      <RuleGroupEditor v-if="child.kind === 'group'" :group="child" :scope="scope" :depth="depth + 1" @remove="removeAt(i)" />
      <div v-else class="flex flex-wrap items-start gap-1 rounded bg-surface-0 dark:bg-surface-950 p-1">
        <span v-if="i > 0" class="text-xs font-semibold uppercase text-primary w-10 pt-2">{{ group.op === 'and' ? t('builder.rule.and') : t('builder.rule.or') }}</span>
        <OperandInput
          v-if="!SUBJECTLESS.includes(child.operator)"
          :model-value="child.left ?? { kind: 'lit', t: 'null', v: null }"
          :scope="scope"
          @update:model-value="(o: Operand) => setLeft(child, o)"
        />
        <Select
          :model-value="child.operator"
          :options="operatorsFor(child)"
          option-label="label"
          option-value="value"
          size="small"
          class="w-44"
          :aria-label="t('builder.rule.operator')"
          @update:model-value="(op: Operator) => setOperator(child, op)"
        />
        <template v-if="child.operator === 'expr'">
          <div class="w-full"><FormulaEditor :model-value="child.ast" :scope="scope" expected="boolean" compact :allow-empty="false" @update:model-value="(a) => setLeafAst(child, a ?? null)" /></div>
        </template>
        <template v-else>
          <template v-for="n in rightCount(child)" :key="n">
            <span v-if="n > 1" class="text-xs text-muted-color pt-2">{{
              child.operator === 'between' ? t('builder.rule.and') : child.operator === 'changed_from_to' ? t('builder.rule.to') : ''
            }}</span>
            <OperandInput
              :model-value="child.right[n - 1]!"
              :scope="scope"
              :value-type="child.operator === 'has_role' || child.operator === 'in_department' ? (n === 2 ? 'boolean' : 'text') : child.operator === 'matches' ? 'text' : operandType(child.left)"
              :options="leftOptions(child)"
              @update:model-value="(o: Operand) => (child.right[n - 1] = o)"
            />
            <Button
              v-if="OPERATOR_ARITY[child.operator] === 'list' && child.right.length > 1"
              size="small"
              text
              icon="pi pi-minus"
              :aria-label="t('builder.rule.remove_value')"
              @click="child.right.splice(n - 1, 1)"
            />
          </template>
          <Button v-if="OPERATOR_ARITY[child.operator] === 'list'" size="small" text icon="pi pi-plus" :label="t('builder.rule.add_value')" @click="child.right.push(defaultValue(child))" />
        </template>
        <span class="flex-1" />
        <Button size="small" text severity="danger" icon="pi pi-times" :aria-label="t('builder.rule.remove_rule')" @click="removeAt(i)" />
      </div>
    </template>
    <div class="flex gap-2">
      <Button size="small" text icon="pi pi-plus" :label="t('builder.rule.add_rule')" @click="addRule" />
      <Button size="small" text icon="pi pi-sitemap" :label="t('builder.rule.add_group')" :disabled="depth >= 6" @click="addGroup" />
    </div>
  </div>
</template>
