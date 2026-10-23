<script setup lang="ts">
import Button from 'primevue/button'
import Checkbox from 'primevue/checkbox'
import Message from 'primevue/message'
import ProgressSpinner from 'primevue/progressspinner'
import Textarea from 'primevue/textarea'
import { computed, onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRoute } from 'vue-router'
import { ApiError } from '@/api/http'
import EmptyState from '@/components/config/EmptyState.vue'
import { formatDatetime } from '@/runtime/format'
import { publicApi, type SignPage } from '../api'
import SignaturePad from './SignaturePad.vue'

/**
 * Signing a document (specification §4.32, ADR-0058): the signer downloads
 * and reads the PDF that was sent, confirms having read it, draws a
 * signature and signs — or declines with a reason. The link works once.
 */
const { t, locale } = useI18n()
const route = useRoute()
const token = computed(() => String(route.params.token))
const loading = ref(true)
const failure = ref<string | null>(null)
const page = ref<SignPage | null>(null)
const read = ref(false)
const signature = ref<string | null>(null)
const declining = ref(false)
const reason = ref('')
const busy = ref(false)
const error = ref('')
const outcome = ref<'signed' | 'declined' | null>(null)

onMounted(async () => {
  try {
    page.value = await publicApi.signature(token.value)
  } catch (e) {
    failure.value = e instanceof ApiError ? e.message : t('xp_form.unavailable')
  } finally {
    loading.value = false
  }
})
async function act(kind: 'sign' | 'decline'): Promise<void> {
  busy.value = true
  error.value = ''
  try {
    if (kind === 'sign') await publicApi.sign(token.value, signature.value ?? '')
    else await publicApi.decline(token.value, reason.value.trim())
    outcome.value = kind === 'sign' ? 'signed' : 'declined'
  } catch (e) {
    error.value = e instanceof ApiError ? (Object.values(e.fieldErrors)[0] ?? e.message) : t('xp_form.unavailable')
  } finally {
    busy.value = false
  }
}
</script>

<template>
  <div class="flex flex-col gap-4" data-testid="x-sign-page">
    <div v-if="loading" class="flex justify-center p-8"><ProgressSpinner /></div>
    <EmptyState v-else-if="failure" icon="pi pi-file" :title="t('xp_sign.unavailable_title')" :description="failure" testid="x-sign-unavailable" />
    <template v-else-if="page">
      <header class="flex flex-col gap-1">
        <h1 class="page-title !mb-0">{{ t('xp_sign.title') }}</h1>
        <p class="m-0 text-sm text-muted-color">{{ t('xp_sign.asked_by', { name: page.sender ?? '' }) }}</p>
        <p class="m-0 text-xs text-muted-color">{{ t('xp_link.expires', { date: formatDatetime(page.expires_at, locale) }) }}</p>
      </header>
      <Message v-if="outcome" :severity="outcome === 'signed' ? 'success' : 'info'" data-testid="x-sign-done">{{ t(`xp_sign.${outcome}`) }}</Message>
      <EmptyState v-else-if="page.state" icon="pi pi-file" :title="t('xp_sign.unavailable_title')" :description="t(`xp_sign.state.${page.state}`)" testid="x-sign-closed" />
      <template v-else>
        <section class="flex flex-col gap-2">
          <h2 class="text-base font-semibold m-0">{{ t('xp_sign.step_read') }}</h2>
          <div>
            <a :href="publicApi.documentUrl(token)" class="p-button p-button-sm p-button-outlined no-underline" data-testid="x-sign-document">
              <i class="pi pi-download me-2" aria-hidden="true" /><span dir="auto">{{ page.document ?? t('xp_sign.document') }}</span>
            </a>
          </div>
          <label class="flex items-center gap-2 text-sm"><Checkbox v-model="read" binary input-id="x-sign-read" data-testid="x-sign-read" />{{ t('xp_sign.have_read') }}</label>
        </section>
        <section v-if="!declining" class="flex flex-col gap-2">
          <h2 class="text-base font-semibold m-0">{{ t('xp_sign.step_sign') }}</h2>
          <SignaturePad @change="(v) => (signature = v)" />
          <Message v-if="error" severity="error" data-testid="x-sign-error">{{ error }}</Message>
          <div class="flex flex-wrap gap-2">
            <Button icon="pi pi-pencil" :label="t('xp_sign.sign')" :disabled="!read || !signature" :loading="busy" data-testid="x-sign-submit" @click="act('sign')" />
            <Button :label="t('xp_sign.decline')" text severity="secondary" @click="declining = true" />
          </div>
        </section>
        <section v-else class="flex flex-col gap-2">
          <label for="x-sign-reason" class="font-medium">{{ t('xp_sign.reason') }}</label>
          <Textarea id="x-sign-reason" v-model="reason" rows="3" maxlength="2000" class="w-full" />
          <Message v-if="error" severity="error">{{ error }}</Message>
          <div class="flex flex-wrap gap-2">
            <Button :label="t('xp_sign.decline')" severity="danger" :disabled="reason.trim() === ''" :loading="busy" data-testid="x-sign-decline" @click="act('decline')" />
            <Button :label="t('xa.cancel')" text severity="secondary" @click="declining = false" />
          </div>
        </section>
      </template>
    </template>
  </div>
</template>
