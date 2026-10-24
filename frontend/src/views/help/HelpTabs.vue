<script setup lang="ts">
import { useI18n } from 'vue-i18n'

/** Switches between the two halves of Help content: written help, and guided tours. */
defineProps<{ current: 'help' | 'tours' }>()
const { t } = useI18n()
const tabs = [
  { key: 'help', route: 'admin.help_content', label: 'help.tab.help' },
  { key: 'tours', route: 'admin.help_tours', label: 'help.tab.tours' },
] as const
</script>

<template>
  <nav class="flex gap-1 border-b border-line" :aria-label="t('admin.area.help_content')">
    <RouterLink
      v-for="tab in tabs"
      :key="tab.key"
      :to="{ name: tab.route }"
      class="px-3 py-2 -mb-px border-b-2 text-sm"
      :class="tab.key === current ? 'border-primary text-primary font-semibold' : 'border-transparent text-muted-color'"
      :aria-current="tab.key === current ? 'page' : undefined"
      :data-testid="`help-tab-${tab.key}`"
      >{{ t(tab.label) }}</RouterLink
    >
  </nav>
</template>
