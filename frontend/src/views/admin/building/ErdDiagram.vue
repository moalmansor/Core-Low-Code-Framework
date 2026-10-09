<script setup lang="ts">
import { Handle, MarkerType, Position, VueFlow, useVueFlow, type Edge, type Node } from '@vue-flow/core'
import '@vue-flow/core/dist/style.css'
import '@vue-flow/core/dist/theme-default.css'
import Button from 'primevue/button'
import { computed, nextTick, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useSession } from '@/stores/session'
import { NODE_WIDTH, layoutErd, type ErdEdgeApi, type ErdNodeApi } from './erdLayout'

/**
 * Entity-relationship diagram of record tables (specification §4.8): one
 * node per table listing its columns, one edge per foreign key labelled with
 * the column and on-delete rule. Layout is computed locally (erdLayout.ts).
 */
const props = defineProps<{ nodes: ErdNodeApi[]; edges: ErdEdgeApi[]; showSystem: boolean }>()
const emit = defineEmits<{ open: [table: string] }>()
const { t } = useI18n()
const session = useSession()
const rtl = computed(() => session.direction === 'rtl')
const flow = useVueFlow('schema-erd')

/** Tables referenced by a foreign key but not part of the selection (for example another form's table). */
const allNodes = computed<ErdNodeApi[]>(() => {
  const known = new Set(props.nodes.map((n) => n.id))
  const extra = new Set<string>()
  for (const e of props.edges) for (const id of [e.from, e.to]) if (!known.has(id)) extra.add(id)
  return [...props.nodes, ...[...extra].map((id) => ({ id, role: 'external', form: '', kind: '', label: id, columns: [] }))]
})
const visibleColumns = (n: ErdNodeApi) => (props.showSystem ? n.columns : n.columns.filter((c) => !c.system || c.name === 'id'))

const flowNodes = computed<Node[]>(() => {
  const boxes = layoutErd(
    allNodes.value.map((n) => ({ id: n.id, columns: visibleColumns(n).length })),
    props.edges,
    rtl.value ? 'rtl' : 'ltr',
  )
  return allNodes.value.map((n) => {
    const b = boxes.get(n.id)!
    return { id: n.id, type: 'table', position: { x: b.x, y: b.y }, data: { ...n, columns: visibleColumns(n) }, style: { width: `${NODE_WIDTH}px` } }
  })
})
const flowEdges = computed<Edge[]>(() =>
  props.edges
    .filter((e) => e.from !== e.to)
    .map((e, i) => ({
      id: `e${i}-${e.from}-${e.column}`,
      source: e.from,
      target: e.to,
      label: `${e.column} · ${e.on_delete}`,
      type: 'smoothstep',
      markerEnd: MarkerType.ArrowClosed,
      labelBgPadding: [4, 2] as [number, number],
    })),
)
const fromSide = computed(() => (rtl.value ? Position.Right : Position.Left))
const toSide = computed(() => (rtl.value ? Position.Left : Position.Right))

watch([flowNodes, flowEdges], async () => {
  await nextTick()
  flow.fitView({ padding: 0.15 })
})
</script>

<template>
  <div class="relative h-[70vh] min-h-96 rounded-lg border border-line bg-card" dir="ltr" data-testid="erd">
    <VueFlow
      id="schema-erd"
      :nodes="flowNodes"
      :edges="flowEdges"
      :nodes-connectable="false"
      :elements-selectable="true"
      :min-zoom="0.1"
      :max-zoom="2"
      fit-view-on-init
      @node-double-click="({ node }) => emit('open', node.id)"
    >
      <template #node-table="{ data }">
        <div class="rounded-lg border border-line-strong bg-card shadow-sm text-xs overflow-hidden">
          <Handle type="source" :position="fromSide" />
          <Handle type="target" :position="toSide" />
          <div
            class="px-2 py-1.5 font-semibold flex items-center gap-1"
            :class="data.role === 'external' ? 'bg-subtle' : data.role === 'main' ? 'bg-primary text-primary-contrast' : 'bg-primary-subtle'"
            :dir="session.direction"
          >
            <i :class="data.kind === 'collection' ? 'pi pi-table' : data.role === 'external' ? 'pi pi-external-link' : 'pi pi-database'" />
            <span class="truncate flex-1">{{ data.label }}</span>
          </div>
          <div class="px-2 py-0.5 text-[10px] text-muted-color ltr-value truncate">{{ data.id }}</div>
          <ul>
            <li v-for="c in data.columns" :key="c.name" class="flex items-center gap-2 px-2 h-[22px] border-t border-line">
              <span class="flex-1 truncate" :class="c.system ? 'text-muted-color' : ''">{{ c.name }}</span>
              <span class="text-muted-color">{{ c.type }}{{ c.nullable ? '?' : '' }}</span>
            </li>
          </ul>
        </div>
      </template>
    </VueFlow>
    <div class="absolute top-2 end-2 flex gap-1 z-10">
      <Button icon="pi pi-search-plus" severity="secondary" size="small" :aria-label="t('building.schema.zoom_in')" @click="flow.zoomIn()" />
      <Button icon="pi pi-search-minus" severity="secondary" size="small" :aria-label="t('building.schema.zoom_out')" @click="flow.zoomOut()" />
      <Button icon="pi pi-expand" severity="secondary" size="small" :aria-label="t('building.schema.fit')" @click="flow.fitView({ padding: 0.15 })" />
    </div>
  </div>
</template>
