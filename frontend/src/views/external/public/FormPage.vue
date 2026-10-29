<script setup lang="ts">
import Button from 'primevue/button'
import InputText from 'primevue/inputtext'
import Message from 'primevue/message'
import Password from 'primevue/password'
import ProgressSpinner from 'primevue/progressspinner'
import { computed, onBeforeUnmount, onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRoute } from 'vue-router'
import { ApiError } from '@/api/http'
import EmptyState from '@/components/config/EmptyState.vue'
import { newUuid } from '@/runtime/api'
import FormRenderer from '@/runtime/FormRenderer.vue'
import { submissionValues } from '@/runtime/submission'
import type { Values } from '@/runtime/types'
import { useSession } from '@/stores/session'
import { publicApi, type PublicFormPage } from '../api'
import { solve } from '../pow'

/**
 * A form published outside (specification §4.32, ADR-0058): the page's
 * texts and theme, the shown fields, then — as the form asks — a password,
 * signing in to the portal, a code sent to an email address or mobile
 * number, and a short proof-of-work check before the submission is sent.
 */
const { t } = useI18n()
const route = useRoute()
const session = useSession()
const slug = computed(() => String(route.params.slug))
const page = ref<PublicFormPage | null>(null)
const failure = ref<string | null>(null)
const loading = ref(true)
const values = ref<Values>({})
const serverErrors = ref<Record<string, string[]>>({})
const banner = ref('')
const renderer = ref<InstanceType<typeof FormRenderer> | null>(null)
const password = ref('')
const passwordError = ref('')
const to = ref('')
const code = ref('')
const codeSent = ref(false)
const codeError = ref('')
const sending = ref(false)
const working = ref<'idle' | 'checking' | 'sending'>('idle')
const done = ref<{ reference: string | null; message: string | null } | null>(null)
// One submission per page: sending it again after a lost answer creates nothing new.
let submission = newUuid()

onMounted(load)
onBeforeUnmount(() => session.useTheme(null, true))
async function load(): Promise<void> {
  loading.value = true
  failure.value = null
  try {
    page.value = await publicApi.form(slug.value)
    if (page.value.theme) session.useTheme(page.value.theme, true)
    document.title = page.value.title
  } catch (e) {
    failure.value = e instanceof ApiError ? e.message : t('xp_form.unavailable')
  } finally {
    loading.value = false
  }
}
async function unlock(): Promise<void> {
  passwordError.value = ''
  try {
    page.value = await publicApi.unlock(slug.value, password.value)
  } catch (e) {
    passwordError.value = e instanceof ApiError ? e.message : t('xp_form.unavailable')
  }
}
async function sendCode(): Promise<void> {
  codeError.value = ''
  sending.value = true
  try {
    await publicApi.code(slug.value, to.value.trim())
    codeSent.value = true
  } catch (e) {
    codeError.value = e instanceof ApiError ? (e.fieldErrors.to ?? e.message) : t('xp_form.unavailable')
  } finally {
    sending.value = false
  }
}
const needsCode = computed(() => page.value?.verification !== 'none')
const signInLink = computed(() => ({ name: 'x.portal', query: { next: route.fullPath } }))

async function submit(): Promise<void> {
  if (!page.value?.definition || !renderer.value || working.value !== 'idle') return
  banner.value = ''
  const clientErrors = renderer.value.validate()
  if (renderer.value.blocked) {
    banner.value = t('records.submit_blocked')
    return
  }
  if (Object.keys(clientErrors).length) {
    banner.value = t('records.fix_errors')
    return
  }
  if (needsCode.value && (!codeSent.value || code.value.trim() === '')) {
    codeError.value = t('xp_form.code_needed')
    return
  }
  try {
    let captcha
    if (page.value.captcha) {
      working.value = 'checking'
      const answer = await solve(await publicApi.challenge(slug.value))
      if (answer === null) throw new Error(t('xp_form.check_failed'))
      captcha = answer
    }
    working.value = 'sending'
    done.value = await publicApi.submit(slug.value, {
      submission,
      values: submissionValues(renderer.value.index, renderer.value.state, 'create', renderer.value.values as Values, {}),
      ...(page.value.password ? { password: password.value } : {}),
      ...(captcha ? { captcha } : {}),
      ...(needsCode.value ? { verification: { to: to.value.trim(), code: code.value.trim() } } : {}),
    })
  } catch (e) {
    if (e instanceof ApiError) {
      serverErrors.value = (e.body.errors as Record<string, string[]> | undefined) ?? {}
      if (e.code === 'verification') codeError.value = e.message
      banner.value = e.message
      // Anything but a refused check means the submission was not taken; a new one may be sent.
      if (e.status !== 422 || e.code !== 'invalid') submission = newUuid()
    } else {
      banner.value = e instanceof Error ? e.message : t('xp_form.unavailable')
    }
  } finally {
    working.value = 'idle'
  }
}
function again(): void {
  done.value = null
  values.value = {}
  code.value = ''
  codeSent.value = false
  submission = newUuid()
}
</script>

<template>
  <div class="flex flex-col gap-4" data-testid="x-form-page">
    <div v-if="loading" class="flex justify-center p-8"><ProgressSpinner /></div>
    <EmptyState v-else-if="failure" icon="pi pi-lock" :title="t('xp_form.unavailable_title')" :description="failure" testid="x-form-unavailable" />
    <template v-else-if="page">
      <header>
        <h1 class="page-title !mb-1" dir="auto" data-testid="x-form-title">{{ page.title }}</h1>
        <p v-if="page.intro" class="m-0 text-muted-color whitespace-pre-line" dir="auto">{{ page.intro }}</p>
      </header>

      <div v-if="done" class="flex flex-col gap-3" data-testid="x-form-done">
        <Message severity="success">{{ done.message || t('xp_form.thanks') }}</Message>
        <p v-if="done.reference" class="m-0">
          {{ t('xp_form.reference') }} <strong class="ltr-value" data-testid="x-form-reference">{{ done.reference }}</strong>
        </p>
        <div><Button :label="t('xp_form.again')" size="small" outlined @click="again" /></div>
      </div>

      <EmptyState v-else-if="!page.admitted" icon="pi pi-user" :title="t('xp_form.sign_in_title')" :description="t('xp_form.sign_in_text')" testid="x-form-sign-in">
        <RouterLink :to="signInLink" class="p-button p-button-sm no-underline">{{ t('xp_portal.sign_in') }}</RouterLink>
      </EmptyState>

      <form v-else-if="!page.definition" class="flex flex-col gap-3 max-w-sm" data-testid="x-form-locked" @submit.prevent="unlock">
        <label for="x-password" class="font-medium">{{ t('xp_form.password') }}</label>
        <Password v-model="password" input-id="x-password" :feedback="false" toggle-mask fluid :invalid="!!passwordError" data-testid="x-password" />
        <small v-if="passwordError" class="text-danger" role="alert">{{ passwordError }}</small>
        <div><Button type="submit" :label="t('xp_form.open')" size="small" data-testid="x-unlock" /></div>
      </form>

      <template v-else>
        <Message v-if="banner" severity="error" data-testid="x-form-banner">{{ banner }}</Message>
        <FormRenderer ref="renderer" v-model="values" :definition="page.definition" mode="create" :errors="serverErrors" />

        <section v-if="needsCode" class="flex flex-col gap-2 border-t border-surface pt-4" data-testid="x-verification">
          <h2 class="text-base font-semibold m-0">{{ t(`xp_form.verify_${page.verification}`) }}</h2>
          <p class="m-0 text-sm text-muted-color">{{ t('xp_form.verify_hint') }}</p>
          <div class="flex flex-wrap items-end gap-2">
            <div class="flex flex-col gap-1">
              <label for="x-to" class="text-sm">{{ t(`xp_form.to_${page.verification}`) }}</label>
              <InputText id="x-to" v-model="to" class="ltr-value" :type="page.verification === 'email' ? 'email' : 'tel'" size="small" data-testid="x-to" />
            </div>
            <Button :label="codeSent ? t('xp_form.resend') : t('xp_form.send_code')" size="small" outlined :loading="sending" data-testid="x-send-code" @click="sendCode" />
            <div v-if="codeSent" class="flex flex-col gap-1">
              <label for="x-code" class="text-sm">{{ t('xp_form.code') }}</label>
              <InputText id="x-code" v-model="code" class="ltr-value w-32" inputmode="numeric" maxlength="6" size="small" autocomplete="one-time-code" data-testid="x-code" />
            </div>
          </div>
          <small v-if="codeSent && !codeError" class="text-muted-color">{{ t('xp_form.code_sent') }}</small>
          <small v-if="codeError" class="text-danger" role="alert" data-testid="x-code-error">{{ codeError }}</small>
        </section>

        <div class="flex flex-wrap items-center gap-3">
          <Button icon="pi pi-send" :label="t('xp_form.submit')" :loading="working !== 'idle'" data-testid="x-submit" @click="submit" />
          <span v-if="working === 'checking'" class="text-sm text-muted-color" role="status">{{ t('xp_form.checking') }}</span>
          <span v-if="page.captcha && working === 'idle'" class="text-xs text-muted-color">{{ t('xp_form.captcha_note') }}</span>
        </div>
      </template>
    </template>
  </div>
</template>
