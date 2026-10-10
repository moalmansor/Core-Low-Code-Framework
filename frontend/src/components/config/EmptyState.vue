<script setup lang="ts">
/**
 * What a list holds when it is empty (design system §5.5): what the thing
 * is, what happens when none is added, and the action that adds the first.
 */
withDefaults(defineProps<{ title: string; description?: string; icon?: string; testid?: string }>(), { description: undefined, icon: 'pi pi-inbox', testid: undefined })
</script>

<template>
  <div class="cfg-empty" :data-testid="testid">
    <i :class="icon" class="cfg-empty-icon" aria-hidden="true" />
    <div class="flex flex-col gap-1 min-w-0">
      <p class="m-0 font-medium">{{ title }}</p>
      <p v-if="description" class="m-0 cfg-empty-desc">{{ description }}</p>
      <div v-if="$slots.default" class="mt-2 flex flex-wrap gap-2"><slot /></div>
    </div>
  </div>
</template>

<style scoped>
.cfg-empty {
  display: flex;
  align-items: flex-start;
  gap: 0.75rem;
  padding: 1rem;
  border: 1px dashed var(--border);
  border-radius: var(--radius-card);
  max-width: var(--measure);
}
.cfg-empty-icon {
  font-size: 1.25rem;
  color: var(--text-muted);
  margin-top: 0.125rem;
}
.cfg-empty-desc {
  font-size: var(--text-size-sm);
  color: var(--text-muted);
}
</style>
