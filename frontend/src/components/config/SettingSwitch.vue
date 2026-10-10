<script setup lang="ts">
import ToggleSwitch from 'primevue/toggleswitch'

/**
 * One on/off setting per line (design system §5.5): its name, a short
 * description of what it does, and the switch at the end of the line.
 */
const props = withDefaults(defineProps<{ id: string; label: string; description?: string; disabled?: boolean }>(), { description: undefined, disabled: false })
const model = defineModel<boolean>({ required: true })
</script>

<template>
  <div class="cfg-switch" :data-testid="`switch-${props.id}`">
    <div class="min-w-0">
      <label :for="id" class="cfg-switch-label">{{ label }}</label>
      <p v-if="description" :id="`${id}-desc`" class="cfg-switch-desc">{{ description }}</p>
    </div>
    <ToggleSwitch v-model="model" :input-id="id" :disabled="disabled" :aria-describedby="description ? `${id}-desc` : undefined" />
  </div>
</template>

<style scoped>
.cfg-switch {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 1rem;
  max-width: var(--field-lg);
}
.cfg-switch-label {
  display: block;
  font-size: var(--text-size-base);
  font-weight: 500;
  cursor: pointer;
}
.cfg-switch-desc {
  margin: 0.125rem 0 0;
  font-size: var(--text-size-sm);
  color: var(--text-muted);
}
</style>
