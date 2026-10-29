<script setup lang="ts">
import Button from 'primevue/button'
import InputText from 'primevue/inputtext'
import Select from 'primevue/select'
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import I18nInput from '@/builder/I18nInput.vue'
import ConfigField from '@/components/config/ConfigField.vue'
import type { HomeLink } from './api'

/** One link of a shortcuts or pinned-links widget. */
defineProps<{ shortcut: boolean; idSuffix: string; error?: string; formOptions: { uuid: string; label: string }[] }>()
const link = defineModel<HomeLink>('link', { required: true })
const emit = defineEmits<{ remove: [] }>()
const { t } = useI18n()
const actions = computed(() => [
  { value: 'new', label: t('xp.shortcut_new') },
  { value: 'list', label: t('xp.shortcut_list') },
])
</script>

<template>
  <div class="cfg-row items-end">
    <I18nInput v-model="link.i18n.label" class="w-field-sm" :label="t('xp.link_label')" :maxlength="255" />
    <template v-if="shortcut">
      <ConfigField :label="t('xp.form')" :for="`link-form-${idSuffix}`" width="sm">
        <Select v-model="link.form" :input-id="`link-form-${idSuffix}`" :options="formOptions" option-label="label" option-value="uuid" filter size="small" :placeholder="t('xp.choose')" />
      </ConfigField>
      <ConfigField :label="t('xp.shortcut_action')" :for="`link-action-${idSuffix}`" width="sm">
        <Select v-model="link.action" :input-id="`link-action-${idSuffix}`" :options="actions" option-label="label" option-value="value" size="small" />
      </ConfigField>
    </template>
    <ConfigField v-else :label="t('xp.url')" :for="`link-url-${idSuffix}`" width="md" :error="error">
      <InputText :id="`link-url-${idSuffix}`" v-model="link.url" dir="ltr" class="ltr-value" size="small" placeholder="https://… /…" />
    </ConfigField>
    <Button icon="pi pi-times" text size="small" severity="secondary" :aria-label="t('xp.remove_link')" @click="emit('remove')" />
  </div>
</template>
