<script setup lang="ts">
import { computed } from 'vue'
import { sectionOpen, toggleSection } from './configScreen'

/**
 * A collapsible section of a configuration screen (design system §5.5): a
 * plain heading, an optional one-line description, and its settings. Common
 * sections start open, advanced ones folded; the person's choice is
 * remembered in this browser.
 */
const props = withDefaults(defineProps<{ id: string; title: string; description?: string; defaultOpen?: boolean; count?: number | null }>(), {
  description: undefined,
  defaultOpen: true,
  count: null,
})
const open = computed(() => sectionOpen(props.id, props.defaultOpen))
const bodyId = computed(() => `cfg-section-${props.id}`)
</script>

<template>
  <section class="cfg-section" :data-testid="`cfg-section-${id}`" :data-open="open">
    <h3 class="m-0">
      <button type="button" class="cfg-section-head" :aria-expanded="open" :aria-controls="bodyId" @click="toggleSection(id, defaultOpen)">
        <i class="pi pi-chevron-right chevron" :class="{ 'is-open': open }" aria-hidden="true" />
        <span class="flex-1 text-start">{{ title }}</span>
        <span v-if="count !== null" class="cfg-count tabular-nums">{{ count }}</span>
      </button>
    </h3>
    <div v-show="open" :id="bodyId" class="cfg-section-body">
      <p v-if="description" class="cfg-help m-0">{{ description }}</p>
      <slot />
    </div>
  </section>
</template>

<style scoped>
.cfg-section {
  border-block-end: 1px solid var(--border);
}
.cfg-section:last-child {
  border-block-end: 0;
}
.cfg-section-head {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  width: 100%;
  padding: 0.75rem 0;
  font-size: var(--text-size-md);
  font-weight: 600;
  color: var(--text);
  background: none;
  border: 0;
  cursor: pointer;
}
.cfg-section-head:hover {
  color: var(--primary);
}
.cfg-count {
  font-size: var(--text-size-xs);
  font-weight: 500;
  color: var(--text-muted);
}
.chevron {
  font-size: 0.75rem;
  color: var(--text-muted);
  transition: transform 0.15s;
}
[dir='rtl'] .chevron {
  transform: scaleX(-1);
}
.chevron.is-open,
[dir='rtl'] .chevron.is-open {
  transform: rotate(90deg);
}
.cfg-section-body {
  display: flex;
  flex-direction: column;
  gap: 1rem;
  padding: 0 0 1.25rem 1.25rem;
}
[dir='rtl'] .cfg-section-body {
  padding: 0 1.25rem 1.25rem 0;
}
.cfg-help {
  font-size: var(--text-size-sm);
  color: var(--text-muted);
  max-width: var(--measure);
}
</style>
