<script setup lang="ts">
import Button from 'primevue/button'
import Divider from 'primevue/divider'
import InputText from 'primevue/inputtext'
import Message from 'primevue/message'
import Password from 'primevue/password'
import { onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRoute, useRouter } from 'vue-router'
import { ApiError, get, send } from '@/api/http'
import { useSession } from '@/stores/session'

const { t } = useI18n()
const route = useRoute()
const router = useRouter()
const session = useSession()

const email = ref('')
const password = ref('')
const busy = ref(false)
const error = ref('')
const providers = ref<{ key: string; name: string }[]>([])

onMounted(async () => {
  providers.value = (await get<{ data: { key: string; name: string }[] }>('/auth/sso/providers')).data
  if (route.query.sso_error) error.value = t(`auth.sso_error.${String(route.query.sso_error)}`)
})

async function submit(): Promise<void> {
  busy.value = true
  error.value = ''
  try {
    const res = await send<{ two_factor: boolean }>('post', '/auth/login', { email: email.value, password: password.value })
    if (res.two_factor) {
      await router.push({ name: 'two-factor', query: route.query })
      return
    }
    await session.loadMe()
    await router.push(typeof route.query.redirect === 'string' && route.query.redirect.startsWith('/') ? route.query.redirect : { name: 'home' })
  } catch (e) {
    error.value = e instanceof ApiError ? (Object.values(e.fieldErrors)[0] ?? e.message) : t('errors.unexpected')
  } finally {
    busy.value = false
    password.value = ''
  }
}
</script>

<template>
  <h1 class="page-title">{{ t('auth.sign_in') }}</h1>
  <Message v-if="route.query.expired" severity="info" class="mb-4">{{ t('auth.session_expired') }}</Message>
  <Message v-if="route.query.reset" severity="success" class="mb-4">{{ t('auth.password_reset_done') }}</Message>
  <Message v-if="error" severity="error" class="mb-4" data-testid="login-error">{{ error }}</Message>
  <form class="flex flex-col gap-4" @submit.prevent="submit">
    <div class="field">
      <label for="email">{{ t('auth.email_or_username') }}</label>
      <InputText id="email" v-model="email" autocomplete="username" required class="ltr-value" data-testid="login-email" />
    </div>
    <div class="field">
      <label for="password">{{ t('auth.password') }}</label>
      <Password v-model="password" input-id="password" :feedback="false" toggle-mask autocomplete="current-password" required fluid data-testid="login-password" />
    </div>
    <Button type="submit" :label="t('auth.sign_in')" :loading="busy" data-testid="login-submit" />
    <RouterLink :to="{ name: 'forgot' }" class="text-sm text-primary">{{ t('auth.forgot_link') }}</RouterLink>
  </form>
  <template v-if="providers.length">
    <Divider align="center"
      ><span class="text-sm text-muted-color">{{ t('auth.or') }}</span></Divider
    >
    <div class="flex flex-col gap-2">
      <a v-for="p in providers" :key="p.key" :href="`/auth/sso/${p.key}/redirect`" class="p-button p-button-outlined justify-center no-underline">
        {{ t('auth.sso_with', { name: p.name }) }}
      </a>
    </div>
  </template>
</template>
