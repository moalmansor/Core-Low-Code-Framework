<script setup lang="ts">
import { computed } from 'vue'
import { useSession } from '@/stores/session'

defineProps<{ size?: 'sm' | 'lg' }>()
const session = useSession()
// The theme's logo (with its dark-mode variant) wins over the Appearance & Branding logo.
const logo = computed(() => session.theme?.assets.logo ?? session.boot?.branding.logo ?? null)
const logoDark = computed(() => (session.theme?.assets.logo ? (session.theme.assets.logo_dark ?? null) : null))
</script>

<template>
  <div class="flex items-center gap-2" data-testid="brand-mark">
    <template v-if="logo">
      <img :src="logo" alt="" :class="[size === 'lg' ? 'h-12' : 'h-8', logoDark ? 'dark:hidden' : '']" />
      <img v-if="logoDark" :src="logoDark" alt="" class="hidden dark:block" :class="size === 'lg' ? 'h-12' : 'h-8'" />
    </template>
    <i v-else class="pi pi-th-large text-primary" :class="size === 'lg' ? 'text-3xl' : 'text-xl'" aria-hidden="true" />
    <span class="font-semibold" :class="size === 'lg' ? 'text-xl' : 'text-base'">{{ session.systemName }}</span>
  </div>
</template>
