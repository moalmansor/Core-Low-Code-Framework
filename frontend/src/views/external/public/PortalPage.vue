<script setup lang="ts">
import Button from 'primevue/button'
import Column from 'primevue/column'
import DataTable from 'primevue/datatable'
import InputText from 'primevue/inputtext'
import Message from 'primevue/message'
import Password from 'primevue/password'
import ProgressSpinner from 'primevue/progressspinner'
import SelectButton from 'primevue/selectbutton'
import Tag from 'primevue/tag'
import { computed, onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRoute, useRouter } from 'vue-router'
import { ApiError } from '@/api/http'
import EmptyState from '@/components/config/EmptyState.vue'
import EmptyValue from '@/components/EmptyValue.vue'
import { formatDatetime } from '@/runtime/format'
import { publicApi, type PortalRecordRow } from '../api'

/**
 * The external portal (specification §4.32, ADR-0058): customers, suppliers
 * and applicants sign in or register (with a code sent to their email and,
 * when the organization asks, an approval), then see the forms open to
 * them and what they have submitted, with its status.
 */
type Mode = 'sign_in' | 'register' | 'verify'
const { t, locale } = useI18n()
const route = useRoute()
const router = useRouter()
const loading = ref(true)
const registrationOpen = ref(false)
const me = ref<{ name: string; email: string } | null>(null)
const mode = ref<Mode>('sign_in')
const email = ref('')
const password = ref('')
const name = ref('')
const confirmation = ref('')
const code = ref('')
const errors = ref<Record<string, string>>({})
const notice = ref('')
const busy = ref(false)
const forms = ref<{ slug: string; title: string; intro: string | null }[]>([])
const records = ref<PortalRecordRow[]>([])

onMounted(load)
async function load(): Promise<void> {
  loading.value = true
  try {
    const p = await publicApi.portal()
    registrationOpen.value = p.registration_open
    me.value = p.me
    if (me.value) {
      ;[forms.value, records.value] = await Promise.all([publicApi.myForms(), publicApi.myRecords()])
    }
  } finally {
    loading.value = false
  }
}
const modes = computed(() => [
  { value: 'sign_in' as Mode, label: t('xp_portal.sign_in') },
  ...(registrationOpen.value ? [{ value: 'register' as Mode, label: t('xp_portal.register') }] : []),
  { value: 'verify' as Mode, label: t('xp_portal.verify') },
])
function failed(e: unknown): void {
  errors.value = e instanceof ApiError ? e.fieldErrors : {}
  notice.value = e instanceof ApiError && Object.keys(errors.value).length === 0 ? e.message : ''
}
async function signIn(): Promise<void> {
  busy.value = true
  errors.value = {}
  notice.value = ''
  try {
    await publicApi.login(email.value.trim(), password.value)
    password.value = ''
    const next = typeof route.query.next === 'string' && route.query.next.startsWith('/x/') ? route.query.next : null
    if (next) await router.push(next)
    else await load()
  } catch (e) {
    failed(e)
  } finally {
    busy.value = false
  }
}
async function register(): Promise<void> {
  busy.value = true
  errors.value = {}
  notice.value = ''
  try {
    await publicApi.register({ name: name.value.trim(), email: email.value.trim(), password: password.value, password_confirmation: confirmation.value })
    password.value = ''
    confirmation.value = ''
    mode.value = 'verify'
    notice.value = t('xp_portal.code_sent')
  } catch (e) {
    failed(e)
  } finally {
    busy.value = false
  }
}
async function verify(): Promise<void> {
  busy.value = true
  errors.value = {}
  notice.value = ''
  try {
    const out = await publicApi.verify(email.value.trim(), code.value.trim())
    code.value = ''
    mode.value = 'sign_in'
    notice.value = out.status === 'approved' ? t('xp_portal.verified_ready') : t('xp_portal.verified_waiting')
  } catch (e) {
    failed(e)
  } finally {
    busy.value = false
  }
}
async function resend(): Promise<void> {
  await publicApi.resend(email.value.trim())
  notice.value = t('xp_portal.code_resent')
}
async function signOut(): Promise<void> {
  await publicApi.logout()
  me.value = null
  forms.value = []
  records.value = []
}
</script>

<template>
  <div class="flex flex-col gap-6" data-testid="x-portal">
    <div v-if="loading" class="flex justify-center p-8"><ProgressSpinner /></div>

    <template v-else-if="me">
      <header class="flex flex-wrap items-start gap-3">
        <div class="flex-1 min-w-0">
          <h1 class="page-title !mb-1" dir="auto">{{ t('xp_portal.welcome', { name: me.name }) }}</h1>
          <p class="m-0 text-sm text-muted-color ltr-value">{{ me.email }}</p>
        </div>
        <Button icon="pi pi-sign-out" :label="t('xp_portal.sign_out')" size="small" text data-testid="x-sign-out" @click="signOut" />
      </header>

      <section class="flex flex-col gap-2">
        <h2 class="text-lg font-semibold m-0">{{ t('xp_portal.forms') }}</h2>
        <EmptyState v-if="!forms.length" icon="pi pi-file" :title="t('xp_portal.no_forms')" :description="t('xp_portal.no_forms_text')" testid="x-no-forms" />
        <ul v-else class="list-none m-0 p-0 flex flex-col gap-2" data-testid="x-my-forms">
          <li v-for="f in forms" :key="f.slug" class="border border-surface rounded-lg p-3">
            <RouterLink :to="{ name: 'x.form', params: { slug: f.slug } }" class="font-medium text-link" dir="auto">{{ f.title }}</RouterLink>
            <p v-if="f.intro" class="m-0 mt-1 text-sm text-muted-color line-clamp-2" dir="auto">{{ f.intro }}</p>
          </li>
        </ul>
      </section>

      <section class="flex flex-col gap-2">
        <h2 class="text-lg font-semibold m-0">{{ t('xp_portal.submissions') }}</h2>
        <DataTable :value="records" data-key="uuid" size="small" class="records-table" data-testid="x-my-records">
          <template #empty>{{ t('xp_portal.no_submissions') }}</template>
          <Column :header="t('xp_portal.form')">
            <template #body="{ data }">
              <RouterLink :to="{ name: 'x.record', params: { slug: data.slug, record: data.uuid } }" class="text-link" dir="auto">{{ data.form }}</RouterLink>
            </template>
          </Column>
          <Column :header="t('xp_form.reference')">
            <template #body="{ data }"
              ><span v-if="data.reference" class="ltr-value">{{ data.reference }}</span
              ><EmptyValue v-else
            /></template>
          </Column>
          <Column :header="t('xp_portal.status')">
            <template #body="{ data }"><Tag v-if="data.status" :value="data.status.name" severity="secondary" /><EmptyValue v-else /></template>
          </Column>
          <Column :header="t('xp_portal.submitted')">
            <template #body="{ data }">{{ formatDatetime(data.submitted_at, locale) }}</template>
          </Column>
        </DataTable>
      </section>
    </template>

    <template v-else>
      <header>
        <h1 class="page-title !mb-1">{{ t('xp_portal.portal') }}</h1>
        <p class="m-0 text-sm text-muted-color">{{ t('xp_portal.intro') }}</p>
      </header>
      <SelectButton v-model="mode" :options="modes" option-label="label" option-value="value" :allow-empty="false" size="small" data-testid="x-portal-mode" />
      <Message v-if="notice" severity="success" data-testid="x-portal-notice">{{ notice }}</Message>

      <form class="flex flex-col gap-3 max-w-sm" novalidate @submit.prevent="mode === 'sign_in' ? signIn() : mode === 'register' ? register() : verify()">
        <div v-if="mode === 'register'" class="flex flex-col gap-1">
          <label for="xp-name">{{ t('xp_portal.name') }}</label>
          <InputText id="xp-name" v-model="name" autocomplete="name" :invalid="!!errors.name" data-testid="xp-name" />
          <small v-if="errors.name" class="text-danger">{{ errors.name }}</small>
        </div>
        <div class="flex flex-col gap-1">
          <label for="xp-email">{{ t('xp_portal.email') }}</label>
          <InputText id="xp-email" v-model="email" type="email" autocomplete="email" class="ltr-value" :invalid="!!errors.email" data-testid="xp-email" />
          <small v-if="errors.email" class="text-danger" role="alert" data-testid="xp-email-error">{{ errors.email }}</small>
        </div>
        <div v-if="mode !== 'verify'" class="flex flex-col gap-1">
          <label for="xp-password">{{ t('xp_portal.password') }}</label>
          <Password
            v-model="password"
            input-id="xp-password"
            :feedback="false"
            toggle-mask
            fluid
            :autocomplete="mode === 'register' ? 'new-password' : 'current-password'"
            :invalid="!!errors.password"
            data-testid="xp-password"
          />
          <small v-if="errors.password" class="text-danger">{{ errors.password }}</small>
        </div>
        <div v-if="mode === 'register'" class="flex flex-col gap-1">
          <label for="xp-confirm">{{ t('xp_portal.password_confirm') }}</label>
          <Password v-model="confirmation" input-id="xp-confirm" :feedback="false" toggle-mask fluid autocomplete="new-password" data-testid="xp-confirm" />
        </div>
        <div v-if="mode === 'verify'" class="flex flex-col gap-1">
          <label for="xp-code">{{ t('xp_form.code') }}</label>
          <InputText id="xp-code" v-model="code" class="ltr-value w-32" inputmode="numeric" maxlength="6" autocomplete="one-time-code" :invalid="!!errors.code" data-testid="xp-code" />
          <small v-if="errors.code" class="text-danger">{{ errors.code }}</small>
        </div>
        <div class="flex flex-wrap items-center gap-2">
          <Button type="submit" :label="t(`xp_portal.do_${mode}`)" :loading="busy" data-testid="xp-submit" />
          <Button v-if="mode === 'verify'" type="button" :label="t('xp_form.resend')" text size="small" @click="resend" />
        </div>
      </form>
    </template>
  </div>
</template>
