<script setup lang="ts">
import Password from 'primevue/password'
import { useI18n } from 'vue-i18n'

defineProps<{ errors?: Record<string, string> }>()
const password = defineModel<string>('password', { required: true })
const confirmation = defineModel<string>('confirmation', { required: true })
const { t } = useI18n()
</script>

<template>
  <div class="field">
    <label for="new-password">{{ t('auth.new_password') }}</label>
    <Password v-model="password" input-id="new-password" toggle-mask autocomplete="new-password" fluid :prompt-label="t('auth.password_prompt')" :weak-label="t('auth.password_weak')" :medium-label="t('auth.password_medium')" :strong-label="t('auth.password_strong')" data-testid="new-password" />
    <small class="text-muted-color">{{ t('auth.password_rules') }}</small>
    <span v-if="errors?.password" class="field-error">{{ errors.password }}</span>
  </div>
  <div class="field">
    <label for="new-password-confirm">{{ t('auth.confirm_password') }}</label>
    <Password v-model="confirmation" input-id="new-password-confirm" :feedback="false" toggle-mask autocomplete="new-password" fluid data-testid="new-password-confirm" />
  </div>
</template>
