<script setup lang="ts">
import Button from 'primevue/button'
import { useI18n } from 'vue-i18n'

/** Add button and save state of a configuration document. */
defineProps<{ dirty: boolean; saving: boolean; addLabel?: string; addDisabled?: boolean; testid: string }>()
const emit = defineEmits<{ add: []; save: [] }>()
const { t } = useI18n()
</script>

<template>
  <div class="flex flex-wrap items-center gap-2">
    <Button v-if="addLabel" icon="pi pi-plus" :label="addLabel" size="small" :disabled="addDisabled" :data-testid="`${testid}-add`" @click="emit('add')" />
    <slot />
    <span class="flex-1" />
    <span v-if="dirty" class="text-sm text-muted-color">{{ t('workflow.unsaved') }}</span>
    <Button icon="pi pi-save" :label="t('workflow.save')" size="small" :loading="saving" :disabled="!dirty" :data-testid="`${testid}-save`" @click="emit('save')" />
  </div>
</template>
