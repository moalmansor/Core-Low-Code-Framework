<script setup lang="ts">
/** The heading of a configuration tab with quiet help text under it (design system §5.5). */
defineProps<{ title: string; text?: string }>()
</script>

<template>
  <header class="flex flex-col gap-1">
    <h2 class="m-0 text-base font-semibold">{{ title }}</h2>
    <p v-if="text" class="m-0 cfg-intro" data-testid="tab-intro">{{ text }}</p>
    <slot />
  </header>
</template>

<style scoped>
.cfg-intro {
  font-size: var(--text-size-sm);
  color: var(--text-muted);
  max-width: var(--measure);
}
</style>
