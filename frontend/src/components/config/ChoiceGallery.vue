<script setup lang="ts" generic="T extends string">
/**
 * A choice of what to add, as described cards in plain groups (design system
 * §5.7): each says what it is and what it does, so an admin picks by purpose
 * rather than by a name in a dropdown.
 */
defineProps<{
  groups: { title: string; items: { value: T; icon: string; title: string; description: string }[] }[]
  testid?: string
}>()
const emit = defineEmits<{ choose: [value: T] }>()
</script>

<template>
  <div class="flex flex-col gap-5" :data-testid="testid">
    <section v-for="g in groups" :key="g.title" class="flex flex-col gap-2">
      <h3 class="m-0 text-sm font-semibold text-muted-color">{{ g.title }}</h3>
      <div class="choice-grid">
        <button v-for="c in g.items" :key="c.value" type="button" class="choice" :data-testid="testid ? `${testid}-${c.value}` : undefined" @click="emit('choose', c.value)">
          <i :class="['pi', c.icon, 'choice-icon']" aria-hidden="true" />
          <span class="flex flex-col gap-0.5 min-w-0 text-start">
            <span class="font-medium">{{ c.title }}</span>
            <span class="choice-desc">{{ c.description }}</span>
          </span>
        </button>
      </div>
    </section>
  </div>
</template>

<style scoped>
.choice-grid {
  display: grid;
  gap: 0.5rem;
  grid-template-columns: repeat(auto-fill, minmax(min(100%, 16rem), 1fr));
}
.choice {
  display: flex;
  align-items: flex-start;
  gap: 0.75rem;
  padding: 0.75rem;
  border: 1px solid var(--border);
  border-radius: var(--radius-card);
  background: var(--bg-surface);
  color: var(--text);
  cursor: pointer;
  font: inherit;
}
.choice:hover {
  border-color: var(--primary);
  background: var(--bg-subtle);
}
.choice:focus-visible {
  outline: 2px solid var(--focus-ring);
  outline-offset: 2px;
}
.choice-icon {
  font-size: 1.125rem;
  color: var(--primary);
  margin-top: 0.125rem;
}
.choice-desc {
  font-size: var(--text-size-sm);
  color: var(--text-muted);
}
</style>
