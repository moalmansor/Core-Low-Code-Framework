<script setup lang="ts">
import Button from 'primevue/button'
import InputOtp from 'primevue/inputotp'
import InputText from 'primevue/inputtext'
import Message from 'primevue/message'
import { ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRoute, useRouter } from 'vue-router'
import { ApiError, send } from '@/api/http'
import { useSession } from '@/stores/session'

const { t } = useI18n()
const route = useRoute()
const router = useRouter()
const session = useSession()
const useRecovery = ref(false)
const code = ref('')
const recovery = ref('')
const busy = ref(false)
const error = ref('')

async function submit(): Promise<void> {
  busy.value = true
  error.value = ''
  try {
    await send('post', '/auth/two-factor-challenge', useRecovery.value ? { recovery_code: recovery.value.trim() } : { code: code.value })
    await session.loadMe()
    await router.push(typeof route.query.redirect === 'string' && route.query.redirect.startsWith('/') ? route.query.redirect : { name: 'home' })
  } catch (e) {
    error.value = e instanceof ApiError && e.status === 422 ? t('auth.two_factor_invalid') : t('errors.unexpected')
    code.value = ''
  } finally {
    busy.value = false
  }
}
</script>

<template>
  <h1 class="page-title">{{ t('auth.two_factor_title') }}</h1>
  <p class="mb-4 text-muted-color">{{ useRecovery ? t('auth.two_factor_recovery_hint') : t('auth.two_factor_hint') }}</p>
  <Message v-if="error" severity="error" class="mb-4">{{ error }}</Message>
  <form class="flex flex-col gap-4" @submit.prevent="submit">
    <div v-if="!useRecovery" class="flex justify-center ltr-value">
      <InputOtp v-model="code" :length="6" integer-only data-testid="otp" />
    </div>
    <InputText v-else v-model="recovery" autocomplete="one-time-code" class="ltr-value" :aria-label="t('auth.recovery_code')" />
    <Button type="submit" :label="t('auth.verify')" :loading="busy" :disabled="!useRecovery && code.length !== 6" data-testid="otp-submit" />
    <Button type="button" link :label="useRecovery ? t('auth.use_authenticator') : t('auth.use_recovery_code')" @click="useRecovery = !useRecovery" />
  </form>
</template>
