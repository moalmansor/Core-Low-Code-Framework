<script setup lang="ts">
import Button from 'primevue/button'
import InputText from 'primevue/inputtext'
import { useI18n } from 'vue-i18n'
import I18nInput from '@/builder/I18nInput.vue'
import ConfigField from '@/components/config/ConfigField.vue'
import type { I18nMap } from './api'

/** One legal link on the sign-in page: an address and its label per language. */
const link = defineModel<{ url: string; label: I18nMap }>({ required: true })
defineProps<{ index: number; error?: string }>()
defineEmits<{ remove: [] }>()
const { t } = useI18n()
</script>

<template>
  <div class="flex flex-wrap items-start gap-3" :data-testid="`theme-link-${index}`">
    <ConfigField :label="t('xp.url')" :for="`theme-link-url-${index}`" width="md" :error="error">
      <InputText :id="`theme-link-url-${index}`" v-model="link.url" class="ltr-value" size="small" placeholder="https://" />
    </ConfigField>
    <I18nInput v-model="link.label" class="w-field-md" :label="t('xp.label')" :maxlength="120" />
    <Button icon="pi pi-trash" text severity="danger" size="small" class="mt-6" :aria-label="t('xp.delete')" @click="$emit('remove')" />
  </div>
</template>
