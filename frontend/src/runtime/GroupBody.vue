<script setup lang="ts">
import { computed } from 'vue'
import { useRenderer } from './context'
import FieldNode from './FieldNode.vue'
import GroupNode from './GroupNode.vue'
import { isGroupHidden, type RowRef } from './rules'
import type { Breakpoints, ClientField, ClientGroup } from './types'

/**
 * The content of a group (or of the form root) on a 12-column grid: child
 * groups and fields in order, each spanning the columns set per breakpoint
 * (or an even share of the group's column count).
 */
const props = defineProps<{ group: ClientGroup | null; row: RowRef | null; exclude?: string[] }>()
const ctx = useRenderer()

type Node = { kind: 'group'; group: ClientGroup; order: number } | { kind: 'field'; field: ClientField; order: number }

const nodes = computed<Node[]>(() => {
  const id = props.group?.uuid ?? null
  const groups = (ctx.index.value.children.get(id) ?? []).filter((g) => !(props.exclude ?? []).includes(g.type))
  const fields = ctx.index.value.groupFields.get(id) ?? []
  return [...groups.map((g) => ({ kind: 'group' as const, group: g, order: g.order })), ...fields.map((f) => ({ kind: 'field' as const, field: f, order: f.order }))].sort((a, b) => a.order - b.order)
})

const BPS = ['xs', 'sm', 'md', 'lg', 'xl'] as const

/** Default spans from the group's column count per breakpoint (rows split evenly between their columns). */
const defaults = computed<Breakpoints>(() => {
  const g = props.group
  if (g?.type === 'row') {
    const count = Math.max(1, nodes.value.length)
    return { xs: 12, md: Math.max(1, Math.floor(12 / count)) }
  }
  const out: Breakpoints = { xs: 12 }
  for (const bp of BPS) {
    const cols = g?.layout?.columns?.[bp]
    if (cols) out[bp] = Math.max(1, Math.floor(12 / cols))
  }
  return out
})

function cellStyle(width: Breakpoints | undefined): Record<string, string> {
  const merged: Breakpoints = { ...defaults.value, ...(width ?? {}) }
  const style: Record<string, string> = {}
  for (const bp of BPS) if (merged[bp]) style[`--lcf-${bp}`] = String(merged[bp])
  return style
}

const gap = computed(() => ({ none: '0', sm: '0.5rem', md: '1rem', lg: '1.5rem' })[props.group?.layout?.spacing ?? 'md'])

function visible(node: Node): boolean {
  if (node.kind === 'group') return !isGroupHidden(ctx.index.value, node.group.uuid, ctx.state.value, props.row)
  return node.field.type !== 'hidden'
}
</script>

<template>
  <div class="lcf-grid" :style="{ '--lcf-gap': gap }">
    <template v-for="n in nodes" :key="n.kind === 'group' ? n.group.uuid : n.field.uuid">
      <div v-if="visible(n) && n.kind === 'group'" class="lcf-cell" :style="cellStyle(n.group.layout?.span)">
        <GroupNode :group="n.group" :row="row" />
      </div>
      <FieldNode v-else-if="visible(n) && n.kind === 'field'" class="lcf-cell" :style="cellStyle(n.field.ui?.width)" :field="n.field" :row="row" />
    </template>
  </div>
</template>
