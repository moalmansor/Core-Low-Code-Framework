<script setup lang="ts">
import { ref, watch } from 'vue'
import { canonicalJson } from '@/expressions'
import type { Ast } from '../types'
import RuleGroupEditor from './RuleGroupEditor.vue'
import { astToRule, ruleToAst, type RuleGroup } from './ruleModel'
import type { ExpressionScope } from './scope'

/** Visual condition builder bound to an expression AST (v-model). */
defineProps<{ scope: ExpressionScope }>()
const model = defineModel<Ast | null | undefined>()

const root = ref<RuleGroup>(astToRule(model.value ?? null))
let last = canonicalJson(ruleToAst(root.value))

watch(
  root,
  (r) => {
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
  <RuleGroupEditor v-model:group="root" :scope="scope" />
</template>
