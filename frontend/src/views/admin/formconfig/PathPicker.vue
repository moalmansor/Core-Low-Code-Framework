<script setup lang="ts">
import TreeSelect from 'primevue/treeselect'
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { labelOf, type Path, type PathNode } from './api'

/**
 * Picks a relation path from a form's field tree: one of its own fields, a
 * system column (status, record number, dates) or a field of a form it
 * links to, followed through lookups.
 */
const props = withDefaults(defineProps<{ tree: PathNode[]; inputId?: string; invalid?: boolean; leafOnly?: boolean; defaultLocale?: string }>(), {
  inputId: undefined,
  leafOnly: false,
  defaultLocale: 'en',
})
const model = defineModel<Path | null | undefined>()
const { locale } = useI18n()

interface Node {
  key: string
  label: string
  selectable: boolean
  children: Node[]
}

const byKey = computed(() => {
  const out = new Map<string, PathNode>()
  const walk = (nodes: PathNode[]) => nodes.forEach((n) => (out.set(n.path.join('.'), n), walk(n.children)))
  walk(props.tree)
  return out
})
const nodes = computed<Node[]>(() => {
  const map = (n: PathNode): Node => ({
    key: n.path.join('.'),
    label: labelOf(n.label, locale.value, n.key, props.defaultLocale),
    selectable: !props.leafOnly || n.children.length === 0,
    children: n.children.map(map),
  })
  return props.tree.map(map)
})
const selected = computed({
  get: () => (model.value?.length ? { [model.value.join('.')]: true } : null),
  set: (v: Record<string, boolean> | null) => {
    const key = v ? Object.keys(v).find((k) => v[k]) : undefined
    model.value = key ? (byKey.value.get(key)?.path ?? null) : null
  },
})
</script>

<template>
  <TreeSelect v-model="selected" :options="nodes" selection-mode="single" :input-id="inputId" :invalid="invalid" size="small" filter fluid class="min-w-0" />
</template>
