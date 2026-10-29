<script setup lang="ts">
/** An information icon whose tooltip carries help an administrator wrote (specification §4.37). */
defineProps<{ text: string }>()
</script>

<template>
  <i v-tooltip.top="text" class="pi pi-info-circle ms-1 text-muted-color text-sm align-middle" tabindex="0" role="img" :aria-label="text" data-testid="help-tip" />
</template>
