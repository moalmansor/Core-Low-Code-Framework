<script setup lang="ts">
import Checkbox from 'primevue/checkbox'
import InputText from 'primevue/inputtext'
import Select from 'primevue/select'
import { useI18n } from 'vue-i18n'

/**
 * One field of a sync job's mapping: where an import reads its value from
 * (a mapped column of the source) or the property an export writes it to,
 * and, for an import, whether it identifies the record.
 */
const value = defineModel<string>('value', { required: true })
const match = defineModel<boolean>('match', { required: true })
defineProps<{ fieldKey: string; label: string; direction: 'import' | 'export'; options: { value: string; label: string }[]; matchable: boolean; error?: string }>()
const { t } = useI18n()
</script>

<template>
  <div class="sync-row" :data-testid="`sync-field-${fieldKey}`">
    <span class="text-sm font-medium min-w-0" dir="auto">{{ label }}</span>
    <Select
      v-if="direction === 'import'"
      v-model="value"
      :options="[{ value: '', label: t('sync.not_mapped') }, ...options]"
      option-label="label"
      option-value="value"
      size="small"
      :aria-label="t('sync.read_from', { field: label })"
      :invalid="!!error"
    />
    <InputText v-else v-model="value" size="small" class="ltr-value" :placeholder="t('sync.not_sent')" :aria-label="t('sync.send_as', { field: label })" :invalid="!!error" />
    <label v-if="direction === 'import'" class="flex items-center gap-2 text-sm" :class="{ 'opacity-50': !matchable || !value }">
      <Checkbox v-model="match" binary :disabled="!matchable || !value" :input-id="`sync-match-${fieldKey}`" />
      {{ t('sync.identifies') }}
    </label>
    <span v-else />
    <small v-if="error" class="field-error col-span-full">{{ error }}</small>
  </div>
</template>

<style scoped>
.sync-row {
  display: grid;
  grid-template-columns: minmax(8rem, 14rem) minmax(0, 18rem) auto;
  gap: 0.75rem;
  align-items: center;
}
@media (max-width: 640px) {
  .sync-row {
    grid-template-columns: minmax(0, 1fr);
  }
}
</style>
