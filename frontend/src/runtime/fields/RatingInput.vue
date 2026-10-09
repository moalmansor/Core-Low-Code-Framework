<script setup lang="ts">
import Rating from 'primevue/rating'
import { computed } from 'vue'
import { useRenderer } from '../context'
import { pickText } from '../i18nText'
import { prop, type InputProps } from './props'

/** Star rating; the number of stars comes from the maximum value rule or `ui.props.stars` (default 5). */
const props = defineProps<InputProps>()
const emit = defineEmits<{ 'update:modelValue': [value: unknown]; focus: []; blur: [] }>()
const ctx = useRenderer()
const stars = computed(() => Number(props.field.validation.number?.max ?? prop(props.field, 'stars', 5)) || 5)
const value = computed(() => (props.modelValue === null || props.modelValue === undefined ? 0 : Number(props.modelValue)))
</script>

<template>
  <Rating
    :id="inputId"
    :model-value="value"
    :stars="stars"
    :disabled="disabled"
    :aria-label="pickText(field.i18n.label, ctx.locale.value) ?? field.key"
    @update:model-value="(v: number) => emit('update:modelValue', v ? String(v) : null)"
    @focus="emit('focus')"
    @blur="emit('blur')"
  />
</template>
