<script setup lang="ts">
import Button from 'primevue/button'
import Message from 'primevue/message'
import { ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRoute, useRouter } from 'vue-router'
import { ApiError, send } from '@/api/http'
import PasswordFields from '@/components/PasswordFields.vue'

const { t } = useI18n()
const route = useRoute()
const router = useRouter()
const password = ref('')
const confirmation = ref('')
const errors = ref<Record<string, string>>({})
const busy = ref(false)

async function submit(): Promise<void> {
  busy.value = true
  errors.value = {}
  try {
    await send('post', '/auth/reset-password', {
      token: route.params.token,
      email: route.query.email,
      password: password.value,
      password_confirmation: confirmation.value,
    })
    await router.push({ name: 'login', query: { reset: '1' } })
  } catch (e) {
    errors.value = e instanceof ApiError ? e.fieldErrors : { password: t('errors.unexpected') }
  } finally {
    busy.value = false
  }
}
</script>

<template>
  <h1 class="page-title">{{ t('auth.reset_title') }}</h1>
  <Message v-if="errors.email" severity="error" class="mb-4">{{ errors.email }}</Message>
  <form class="flex flex-col gap-4" @submit.prevent="submit">
    <p class="text-muted-color ltr-value">{{ route.query.email }}</p>
    <PasswordFields v-model:password="password" v-model:confirmation="confirmation" :errors="errors" />
    <Button type="submit" :label="t('auth.reset_submit')" :loading="busy" />
  </form>
</template>
