<script setup lang="ts">
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { useSession } from '@/stores/session'
import { previewVariables, type ThemeLook } from '@/theme/theme'

/**
 * A theme shown in light and dark mode before it is saved. Each pane sets the
 * theme's variables on itself (through the CSSOM), so it does not inherit the
 * theme the page is wearing.
 */
const props = defineProps<{ look: ThemeLook }>()
const { t } = useI18n()
const session = useSession()
const style = computed(() => previewVariables(props.look, { primary: session.boot?.branding.primary_color, primaryDark: session.boot?.branding.primary_color_dark }))
</script>

<template>
  <div class="grid gap-3 md:grid-cols-2" data-testid="theme-preview">
    <div v-for="mode in ['light', 'dark'] as const" :key="mode" :class="['theme-pane', mode === 'dark' ? 'app-dark' : 'app-light']" :style="style" :data-testid="`theme-preview-${mode}`">
      <span class="eyebrow">{{ t(`xp.theme_mode.${mode}`) }}</span>
      <div class="theme-card">
        <strong class="text-sm">{{ t('xp.theme_sample_title') }}</strong>
        <span class="text-sm theme-muted">{{ t('xp.theme_sample_text') }}</span>
        <div class="flex flex-wrap items-center gap-2">
          <span class="theme-button"><i class="pi pi-check" aria-hidden="true" />{{ t('xp.theme_sample_button') }}</span>
          <span class="theme-input">{{ t('xp.theme_sample_input') }}</span>
          <span class="theme-link">{{ t('xp.theme_sample_link') }}</span>
        </div>
        <div class="flex flex-wrap gap-2 text-xs">
          <span class="theme-status theme-success">{{ t('xp.theme_sample_success') }}</span>
          <span class="theme-status theme-warning">{{ t('xp.theme_sample_warning') }}</span>
          <span class="theme-status theme-danger">{{ t('xp.theme_sample_danger') }}</span>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
/* Plain elements reading the tokens: PrimeVue resolves its variables at the page root, so it would not show a pane's colours. */
.theme-pane {
  display: flex;
  flex-direction: column;
  gap: 0.75rem;
  padding: 1rem;
  border: 1px solid var(--border);
  border-radius: var(--radius-card);
  background: var(--bg-page);
  color: var(--text);
  font-family: var(--font-ui);
}
.theme-card {
  display: flex;
  flex-direction: column;
  gap: 0.75rem;
  padding: 1rem;
  background: var(--bg-surface);
  border: 1px solid var(--border);
  border-radius: var(--radius-card);
  box-shadow: 0 1px 3px var(--shadow-color);
}
.theme-muted {
  color: var(--text-muted);
}
.theme-button,
.theme-input {
  display: inline-flex;
  align-items: center;
  gap: 0.375rem;
  min-height: var(--control-height);
  padding-inline: 0.75rem;
  border-radius: var(--radius-control);
  font-size: 0.875rem;
}
.theme-button {
  background: var(--primary);
  color: var(--on-primary);
  font-weight: 500;
}
.theme-input {
  background: var(--bg-subtle);
  border: 1px solid var(--border);
  color: var(--text-muted);
}
.theme-link {
  color: var(--link);
  text-decoration: underline;
  font-size: 0.875rem;
}
.theme-status {
  font-weight: 600;
}
.theme-success {
  color: var(--success);
}
.theme-warning {
  color: var(--warning);
}
.theme-danger {
  color: var(--danger);
}
</style>
