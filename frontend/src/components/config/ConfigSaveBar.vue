<script setup lang="ts">
import Button from 'primevue/button'
import { useI18n } from 'vue-i18n'

/**
 * The action bar of a configuration document (design system §5.5): pinned to
 * the bottom of the content area, the save state on one side, Discard and
 * Save on the other. The same on every tab.
 */
defineProps<{ dirty: boolean; saving: boolean; testid: string; disabled?: boolean }>()
const emit = defineEmits<{ save: []; discard: [] }>()
const { t } = useI18n()
</script>

<template>
  <div class="action-bar" role="region" :aria-label="t('formconfig.actions')" :data-testid="`${testid}-bar`">
    <span class="text-sm flex items-center gap-2" :class="dirty ? '' : 'text-muted-color'" aria-live="polite">
      <i :class="dirty ? 'pi pi-circle-fill text-warning text-[0.5rem]' : 'pi pi-check'" aria-hidden="true" />
      {{ dirty ? t('workflow.unsaved') : t('formconfig.all_saved') }}
    </span>
    <span class="flex-1" />
    <Button v-if="dirty" :label="t('formconfig.discard')" text severity="secondary" size="small" :data-testid="`${testid}-discard`" @click="emit('discard')" />
    <Button icon="pi pi-check" :label="t('workflow.save')" size="small" :loading="saving" :disabled="!dirty || disabled" :data-testid="`${testid}-save`" @click="emit('save')" />
  </div>
</template>
