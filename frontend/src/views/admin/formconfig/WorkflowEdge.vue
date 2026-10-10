<script setup lang="ts">
import { BaseEdge, EdgeLabelRenderer, getSmoothStepPath, type EdgeProps } from '@vue-flow/core'
import { computed, inject, onBeforeUnmount, watchEffect } from 'vue'
import { EDGE_LABELS } from './edgeLabels'

/**
 * A transition on the designer canvas: a step path whose label is placed
 * where it does not cover another transition's label (the designer nudges
 * labels apart), highlighted when selected. Clicking the label selects the
 * transition, like clicking its line.
 */
const props = defineProps<EdgeProps<{ label: string; offset: number; selected: boolean; problem: boolean }>>()
const labels = inject(EDGE_LABELS)!

const geometry = computed(() =>
  getSmoothStepPath({
    sourceX: props.sourceX,
    sourceY: props.sourceY,
    sourcePosition: props.sourcePosition,
    targetX: props.targetX,
    targetY: props.targetY,
    targetPosition: props.targetPosition,
    offset: props.data?.offset ?? 20,
    borderRadius: 8,
  }),
)
const width = computed(() => Math.min(220, 16 + (props.data?.label.length ?? 0) * 7))
watchEffect(() => labels.report(props.id, { x: geometry.value[1], y: geometry.value[2], w: width.value }))
onBeforeUnmount(() => labels.forget(props.id))
const dy = computed(() => labels.offsets.value.get(props.id) ?? 0)
</script>

<template>
  <BaseEdge :id="id" :path="geometry[0]" :marker-end="markerEnd" :class="['wf-edge', { 'is-selected': data?.selected, 'is-problem': data?.problem }]" :interaction-width="18" />
  <EdgeLabelRenderer>
    <button
      type="button"
      class="wf-edge-label nodrag nopan"
      :class="{ 'is-selected': data?.selected, 'is-problem': data?.problem }"
      :style="{ transform: `translate(-50%, -50%) translate(${geometry[1]}px, ${geometry[2] + dy}px)`, maxWidth: `${width}px` }"
      :data-testid="`wf-edge-label-${id}`"
      @click="labels.select(id)"
    >
      {{ data?.label }}
    </button>
  </EdgeLabelRenderer>
</template>

<style scoped>
.wf-edge-label {
  position: absolute;
  pointer-events: all;
  padding: 1px 6px;
  font-size: 11px;
  line-height: 16px;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
  color: var(--text);
  background: var(--bg-surface);
  border: 1px solid var(--border);
  border-radius: 999px;
  cursor: pointer;
}
.wf-edge-label.is-selected {
  border-color: var(--primary);
  color: var(--primary);
  font-weight: 600;
}
.wf-edge-label.is-problem {
  border-color: var(--danger);
}
:deep(.wf-edge.is-selected) {
  stroke: var(--primary) !important;
  stroke-width: 2.5 !important;
}
:deep(.wf-edge.is-problem) {
  stroke: var(--danger) !important;
}
</style>
