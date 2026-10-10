<script setup lang="ts">
/**
 * A labelled setting sized to what it holds (design system §5.5): `xs` for
 * numbers, `sm` for keys and codes, `md` for names and pickers, `lg` for
 * longer text and paths, `full` for expressions and rich text. The label
 * sits above the input; help and errors below it.
 */
withDefaults(defineProps<{ label: string; for?: string; width?: 'xs' | 'sm' | 'md' | 'lg' | 'full'; hint?: string; error?: string; required?: boolean }>(), {
  for: undefined,
  width: 'md',
  hint: undefined,
  error: undefined,
  required: false,
})
</script>

<template>
  <div class="cfg-field" :class="`w-field-${width}`">
    <label v-if="$props.for" :for="$props.for" class="cfg-field-label">{{ label }}<span v-if="required" class="text-danger ms-1" aria-hidden="true">*</span></label>
    <span v-else class="cfg-field-label">{{ label }}<span v-if="required" class="text-danger ms-1" aria-hidden="true">*</span></span>
    <slot />
    <small v-if="hint" class="cfg-field-hint">{{ hint }}</small>
    <small v-if="error" class="field-error" role="alert">{{ error }}</small>
  </div>
</template>

<style scoped>
.cfg-field {
  display: flex;
  flex-direction: column;
  gap: 0.375rem;
  min-width: 0;
  width: 100%;
}
.cfg-field-label {
  font-size: var(--text-size-sm);
  font-weight: 500;
}
.cfg-field-hint {
  font-size: var(--text-size-sm);
  color: var(--text-muted);
}
.cfg-field :deep(.p-inputtext),
.cfg-field :deep(.p-select),
.cfg-field :deep(.p-multiselect),
.cfg-field :deep(.p-inputnumber),
.cfg-field :deep(.p-treeselect),
.cfg-field :deep(.p-autocomplete) {
  width: 100%;
}
</style>
