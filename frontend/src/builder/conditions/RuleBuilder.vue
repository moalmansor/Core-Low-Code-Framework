<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { canonicalJson } from '@/expressions'
import type { Ast } from '../types'
import RuleGroupEditor from './RuleGroupEditor.vue'
import { astToRule, isComplete, ruleToAst, type RuleGroup } from './ruleModel'
import type { ExpressionScope } from './scope'

/**
 * Visual condition builder bound to an expression AST (v-model). Only complete
 * conditions are written to the model: while a row still needs a field or a
 * value, the row says so and the last complete condition stays saved.
 */
defineProps<{ scope: ExpressionScope }>()
const model = defineModel<Ast | null | undefined>()
const { t } = useI18n()

const root = ref<RuleGroup>(astToRule(model.value ?? null))
let last = canonicalJson(ruleToAst(root.value))
const complete = computed(() => isComplete(root.value))

watch(
  root,
  (r) => {
    if (!isComplete(r)) return
    const ast = ruleToAst(r)
    const json = canonicalJson(ast)
    if (json === last) return
    last = json
    model.value = ast
  },
  { deep: true },
)

watch(model, (ast) => {
  const json = ast ? canonicalJson(ast) : canonicalJson(ruleToAst(astToRule(null)))
  if (json === last) return
  last = json
  root.value = astToRule(ast ?? null)
})
</script>

<template>
  <div class="flex flex-col gap-1">
    <RuleGroupEditor v-model:group="root" :scope="scope" />
    <p v-if="!complete" class="text-xs text-muted-color" role="status" data-testid="rule-incomplete"><i class="pi pi-info-circle" aria-hidden="true" /> {{ t('builder.rule.incomplete') }}</p>
  </div>
</template>
