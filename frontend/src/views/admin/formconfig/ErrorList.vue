<script setup lang="ts">
import Message from 'primevue/message'

/** Server validation messages of a configuration document, by path. */
defineProps<{ errors: Record<string, string> }>()
</script>

<template>
  <Message v-for="(m, k) in errors" :key="k" severity="error" :closable="false" class="text-sm" data-testid="config-error"
    ><span class="ltr-value">{{ k }}</span
    >: {{ m }}</Message
  >
</template>
