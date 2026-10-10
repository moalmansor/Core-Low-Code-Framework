<script setup lang="ts">
import Button from 'primevue/button'
import { useI18n } from 'vue-i18n'

/**
 * One entry of a configuration list (a rule, a panel, a layout): a header
 * with its summary and the move and remove actions, and its settings below,
 * which fold away so a long list stays readable.
 */
const props = withDefaults(defineProps<{ title: string; subtitle?: string; index?: number; count?: number; movable?: boolean; testid?: string; invalid?: boolean }>(), {
  subtitle: undefined,
  index: 0,
  count: 1,
  movable: false,
  testid: undefined,
  invalid: false,
})
const emit = defineEmits<{ move: [delta: -1 | 1]; remove: [] }>()
const open = defineModel<boolean>('open', { default: true })
const { t } = useI18n()
</script>

<template>
  <article class="cfg-item" :class="{ 'is-invalid': props.invalid }" :data-testid="testid">
    <header class="cfg-item-head">
      <button type="button" class="cfg-item-toggle" :aria-expanded="open" @click="open = !open">
        <i class="pi pi-chevron-right chevron" :class="{ 'is-open': open }" aria-hidden="true" />
        <span class="min-w-0 flex flex-col text-start">
          <span class="font-medium truncate">{{ title }}</span>
          <span v-if="subtitle" class="text-sm text-muted-color truncate">{{ subtitle }}</span>
        </span>
      </button>
      <slot name="badges" />
      <div class="flex shrink-0">
        <template v-if="movable">
          <Button icon="pi pi-arrow-up" text rounded size="small" severity="secondary" :aria-label="t('assignment.move_up')" :disabled="index === 0" @click="emit('move', -1)" />
          <Button icon="pi pi-arrow-down" text rounded size="small" severity="secondary" :aria-label="t('assignment.move_down')" :disabled="index === count - 1" @click="emit('move', 1)" />
        </template>
        <Button icon="pi pi-trash" text rounded size="small" severity="danger" :aria-label="t('workflow.remove')" @click="emit('remove')" />
      </div>
    </header>
    <div v-show="open" class="cfg-item-body">
      <slot />
    </div>
  </article>
</template>

<style scoped>
.cfg-item {
  border: 1px solid var(--border);
  border-radius: var(--radius-card);
  background: var(--bg-surface);
}
.cfg-item.is-invalid {
  border-color: var(--danger);
}
.cfg-item-head {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  padding: 0.5rem 0.5rem 0.5rem 0.75rem;
}
.cfg-item-toggle {
  display: flex;
  align-items: center;
  gap: 0.625rem;
  flex: 1;
  min-width: 0;
  padding: 0.25rem 0;
  background: none;
  border: 0;
  color: var(--text);
  cursor: pointer;
}
.cfg-item-body {
  display: flex;
  flex-direction: column;
  gap: 1rem;
  padding: 0.75rem 1rem 1rem;
  border-block-start: 1px solid var(--border);
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
</style>
