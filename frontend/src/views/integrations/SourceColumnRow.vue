<script setup lang="ts">
import Button from 'primevue/button'
import InputText from 'primevue/inputtext'
import { useI18n } from 'vue-i18n'
import I18nInput from '@/builder/I18nInput.vue'
import type { SourceColumn } from './api'

/** An extra column of a data source: its key, where it is read from, and its label per language. */
const column = defineModel<SourceColumn>({ required: true })
defineProps<{ index: number; error?: string }>()
defineEmits<{ remove: [] }>()
const { t } = useI18n()
</script>

<template>
  <div class="flex flex-col gap-2 rounded-lg border border-line p-3" :data-testid="`source-column-${index}`">
    <div class="flex flex-wrap items-end gap-3">
      <div class="field">
        <label :for="`source-col-key-${index}`">{{ t('xds.column_key') }}</label>
        <InputText :id="`source-col-key-${index}`" v-model="column.key" size="small" class="ltr-value w-field-sm" maxlength="48" />
      </div>
      <div class="field">
        <label :for="`source-col-path-${index}`">{{ t('xds.column_path') }}</label>
        <InputText :id="`source-col-path-${index}`" v-model="column.path" size="small" class="ltr-value w-field-sm" />
      </div>
      <I18nInput v-model="column.label" class="w-field-md" :label="t('xds.column_label')" :maxlength="120" />
      <Button icon="pi pi-trash" text severity="danger" size="small" :aria-label="t('xds.remove')" @click="$emit('remove')" />
    </div>
    <small v-if="error" class="field-error">{{ error }}</small>
  </div>
</template>
