<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted } from 'vue'
import { buildScope } from '@/builder/conditions/scope'
import { createBuilder, provideBuilder } from '@/builder/useBuilder'

/**
 * Loads a form's draft (read-only) for the expression editors inside it, so
 * conditions written outside the form configuration screen know the form's
 * fields; without a form the slot gets no scope. Key it by form so it loads
 * again when the form changes.
 */
const props = defineProps<{ form: string | null }>()
const builder = props.form ? createBuilder(props.form) : null
if (builder) provideBuilder(builder)
onMounted(() => builder?.load().catch(() => undefined))
onBeforeUnmount(() => builder?.cancelTimers())
const scope = computed(() => (builder?.doc ? buildScope(builder.doc, builder.catalog.fields) : null))
</script>

<template>
  <slot :scope="scope" />
</template>
