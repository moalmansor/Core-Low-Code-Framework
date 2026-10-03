<script setup lang="ts">
import { useSession } from '@/stores/session'

defineProps<{ size?: 'sm' | 'lg' }>()
const session = useSession()
</script>

<template>
  <div class="flex items-center gap-2">
    <img v-if="session.boot?.branding.logo" :src="session.boot.branding.logo" alt="" :class="size === 'lg' ? 'h-12' : 'h-8'" />
    <i v-else class="pi pi-th-large text-primary" :class="size === 'lg' ? 'text-3xl' : 'text-xl'" aria-hidden="true" />
    <span class="font-semibold" :class="size === 'lg' ? 'text-xl' : 'text-base'">{{ session.systemName }}</span>
  </div>
</template>
