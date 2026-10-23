<script setup lang="ts">
import Button from 'primevue/button'
import InputText from 'primevue/inputtext'
import Password from 'primevue/password'
import { useI18n } from 'vue-i18n'

/** One name and value pair (a query parameter, a header); a secret value is write-only. */
const pair = defineModel<{ name: string; value: string | null; set?: boolean }>({ required: true })
defineProps<{ secret?: boolean; nameLabel: string; valueLabel: string; error?: string; testid?: string }>()
defineEmits<{ remove: [] }>()
const { t } = useI18n()
</script>

<template>
  <div class="flex flex-col gap-1" :data-testid="testid">
    <div class="flex flex-wrap items-center gap-2">
      <InputText v-model="pair.name" size="small" class="ltr-value w-field-sm" :aria-label="nameLabel" :placeholder="nameLabel" :invalid="!!error" />
      <Password
        v-if="secret"
        :model-value="pair.value ?? ''"
        :feedback="false"
        toggle-mask
        size="small"
        class="w-field-md"
        input-class="ltr-value"
        fluid
        :aria-label="valueLabel"
        :placeholder="pair.set ? t('xds.kept') : valueLabel"
        @update:model-value="(v) => (pair.value = v ?? '')"
      />
      <InputText
        v-else
        :model-value="pair.value ?? ''"
        size="small"
        class="ltr-value w-field-md"
        :aria-label="valueLabel"
        :placeholder="valueLabel"
        @update:model-value="(v) => (pair.value = v ?? '')"
      />
      <Button icon="pi pi-times" text rounded size="small" :aria-label="t('xds.remove')" @click="$emit('remove')" />
    </div>
    <small v-if="error" class="field-error">{{ error }}</small>
  </div>
</template>
