<script setup lang="ts">
import Button from 'primevue/button'
import InputText from 'primevue/inputtext'
import Message from 'primevue/message'
import { ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { send } from '@/api/http'

const { t } = useI18n()
const email = ref('')
const done = ref(false)
const busy = ref(false)

async function submit(): Promise<void> {
  busy.value = true
  try {
    await send('post', '/auth/forgot-password', { email: email.value })
  } catch {
    // Same answer either way: the form never reveals whether an account exists.
  } finally {
    done.value = true
    busy.value = false
  }
}
</script>

<template>
  <h1 class="page-title">{{ t('auth.forgot_title') }}</h1>
  <Message v-if="done" severity="success" class="mb-4">{{ t('auth.forgot_sent') }}</Message>
  <form v-else class="flex flex-col gap-4" @submit.prevent="submit">
    <p class="text-muted-color">{{ t('auth.forgot_hint') }}</p>
    <div class="field">
      <label for="email">{{ t('auth.email') }}</label>
      <InputText id="email" v-model="email" type="email" autocomplete="email" required class="ltr-value" />
    </div>
    <Button type="submit" :label="t('auth.send_link')" :loading="busy" />
  </form>
  <RouterLink :to="{ name: 'login' }" class="text-sm text-primary mt-4 inline-block">{{ t('auth.back_to_sign_in') }}</RouterLink>
</template>
