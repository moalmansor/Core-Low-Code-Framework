<script setup lang="ts">
import { computed } from 'vue'
import { matches, usePanel } from './panel'

/**
 * A collapsible section of a properties panel. It shows in its tab; while the
 * panel is searched it shows, open, wherever its title or settings match.
 */
const props = withDefaults(defineProps<{ id: string; tab: string; title: string; terms?: string[]; hint?: string; defaultOpen?: boolean }>(), { terms: () => [], hint: undefined, defaultOpen: true })
const panel = usePanel()
const searching = computed(() => panel.query.value.trim() !== '')
const visible = computed(() => (searching.value ? matches(panel.query.value, [props.title, ...props.terms]) : panel.tab.value === props.tab))
const open = computed(() => searching.value || panel.isOpen(props.id, props.defaultOpen))
const bodyId = computed(() => `section-${props.id}`)
</script>

<template>
  <section v-if="visible" class="panel-section" :data-testid="`section-${id}`" :data-open="open">
    <h3 class="m-0">
      <button type="button" class="panel-section-head" :aria-expanded="open" :aria-controls="bodyId" @click="panel.toggle(id, defaultOpen)">
        <i class="pi pi-chevron-right chevron" :class="{ 'is-open': open }" aria-hidden="true" />
        <span class="flex-1 text-start">{{ title }}</span>
        <span v-if="hint" class="text-xs font-normal text-muted-color truncate max-w-[50%]">{{ hint }}</span>
      </button>
    </h3>
    <div v-show="open" :id="bodyId" class="panel-section-body">
      <slot />
    </div>
  </section>
</template>

<style scoped>
.panel-section {
  border-block-end: 1px solid var(--border);
}
.panel-section-head {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  width: 100%;
  padding: 0.625rem 0.25rem;
  font-size: var(--text-size-sm);
  font-weight: 600;
  color: var(--text);
  background: none;
  border: 0;
  cursor: pointer;
}
.panel-section-head:hover {
  color: var(--primary);
}
.chevron {
  font-size: 0.6875rem;
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
.panel-section-body {
  display: flex;
  flex-direction: column;
  gap: 0.75rem;
  padding: 0.25rem 0.25rem 0.875rem;
}
</style>
