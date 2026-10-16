<script setup lang="ts">
import { useI18n } from 'vue-i18n'

/**
 * A value that is not there (design system §5.6): in a table cell or a
 * compact place a muted dash that screen readers read as "Not filled in";
 * elsewhere the words themselves, small, italic and muted.
 */
withDefaults(defineProps<{ compact?: boolean; text?: string }>(), { compact: true, text: undefined })
const { t } = useI18n()
</script>

<template>
  <span v-if="compact" class="empty-value" :title="text ?? t('runtime.empty_value')"
    ><span aria-hidden="true">—</span><span class="sr-only">{{ text ?? t('runtime.empty_value') }}</span></span
  >
  <span v-else class="empty-value empty-value-words">{{ text ?? t('runtime.empty_value') }}</span>
</template>

<style scoped>
.empty-value {
  color: var(--text-muted);
}
.empty-value-words {
  font-size: var(--text-size-sm);
  font-style: italic;
}
</style>
