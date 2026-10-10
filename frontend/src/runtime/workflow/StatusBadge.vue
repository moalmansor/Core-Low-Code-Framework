<script setup lang="ts">
import type { StatusPayload } from './types'

/** A record's status as a badge in the colour the administrator chose for it. */
defineProps<{ status: StatusPayload | null; size?: 'sm' | 'md' }>()
</script>

<template>
  <span
    v-if="status"
    class="inline-flex items-center gap-1 rounded-full px-2.5 py-0.5 font-medium text-on-status"
    :class="size === 'sm' ? 'text-xs' : 'text-sm'"
    :style="{ backgroundColor: status.color }"
    data-testid="status-badge"
  >
    <i v-if="status.icon" :class="status.icon" aria-hidden="true" />{{ status.name }}
  </span>
</template>
