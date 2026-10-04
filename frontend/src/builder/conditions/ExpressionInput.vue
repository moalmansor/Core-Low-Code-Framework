<script setup lang="ts">
import Button from 'primevue/button'
import SelectButton from 'primevue/selectbutton'
import { computed, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import type { Ast } from '../types'
import FormulaEditor from './FormulaEditor.vue'
import RuleBuilder from './RuleBuilder.vue'
import type { ExpressionScope } from './scope'

/**
 * An expression property: conditions (boolean) can be edited with the visual
 * rule builder or as a formula; other expressions are formulas. Both produce
 * the same stored AST.
 */
const props = withDefaults(defineProps<{ scope: ExpressionScope; expected?: string | null; label?: string; allowEmpty?: boolean; visual?: boolean }>(), {
  expected: null,
  label: undefined,
  allowEmpty: true,
  visual: undefined,
})
const model = defineModel<Ast | null | undefined>()
const { t } = useI18n()
const canVisual = computed(() => props.visual ?? props.expected === 'boolean')
const mode = ref<'visual' | 'text'>(canVisual.value ? 'visual' : 'text')
const modes = computed(() => [
  { value: 'visual', label: t('builder.expression.visual') },
  { value: 'text', label: t('builder.expression.formula') },
])
</script>

<template>
  <div class="flex flex-col gap-1 min-w-0">
    <div v-if="label || canVisual || (allowEmpty && model)" class="flex flex-wrap items-center gap-2">
      <span v-if="label" class="text-sm font-medium flex-1">{{ label }}</span>
      <SelectButton v-if="canVisual" v-model="mode" :options="modes" option-label="label" option-value="value" :allow-empty="false" size="small" />
      <Button v-if="allowEmpty && model" size="small" text severity="secondary" icon="pi pi-times" :label="t('builder.expression.clear')" @click="model = null" />
    </div>
    <RuleBuilder v-if="canVisual && mode === 'visual'" v-model="model" :scope="scope" />
    <FormulaEditor v-else v-model="model" :scope="scope" :expected="expected" :allow-empty="allowEmpty" />
  </div>
</template>
