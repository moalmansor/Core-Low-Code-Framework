<script setup lang="ts">
import Button from 'primevue/button'
import Checkbox from 'primevue/checkbox'
import InputNumber from 'primevue/inputnumber'
import InputOtp from 'primevue/inputotp'
import InputText from 'primevue/inputtext'
import Message from 'primevue/message'
import Password from 'primevue/password'
import RadioButton from 'primevue/radiobutton'
import Select from 'primevue/select'
import { computed, onMounted, reactive, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRouter } from 'vue-router'
import { ApiError, ensureCsrf, http } from '@/api/http'
import BrandMark from '@/components/BrandMark.vue'
import LanguageSwitcher from '@/components/LanguageSwitcher.vue'
import PasswordFields from '@/components/PasswordFields.vue'
import { useSession } from '@/stores/session'

interface Status {
  locales: { code: string; native_name: string; direction: string; is_enabled: boolean; is_default: boolean }[]
  defaults: Record<string, Record<string, unknown>>
  password_policy: Record<string, unknown>
}

const { t } = useI18n()
const router = useRouter()
const session = useSession()

const steps = ['token', 'branding', 'languages', 'regional', 'mail', 'admin', 'done'] as const
type Step = (typeof steps)[number]
const step = ref<Step>('token')
const stepIndex = computed(() => steps.indexOf(step.value))
const status = ref<Status | null>(null)
const busy = ref(false)
const error = ref('')
const errors = ref<Record<string, string>>({})

const token = ref('')
const form = reactive({
  system_name: {} as Record<string, string>,
  logo_file: null as string | null,
  favicon_file: null as string | null,
  default_locale: 'en',
  enabled_locales: [] as string[],
  formats: { timezone: Intl.DateTimeFormat().resolvedOptions().timeZone || 'UTC', date_format: 'yyyy-MM-dd', time_format: '24h', first_day_of_week: 0, digits: 'locale' } as Record<string, unknown>,
  calendar: { system: 'gregorian' },
  tenancy: { mode: 'single' },
  mail: { host: '', port: 587, encryption: 'tls', username: '', password: '', from_address: '', from_name: '' },
  admin: { name: '', email: '', password: '', password_confirmation: '' },
})
const skipMail = ref(false)
const testTo = ref('')
const mailResult = ref<{ ok: boolean; text: string } | null>(null)
const qr = ref('')
const secret = ref('')
const code = ref('')
const recoveryCodes = ref<string[]>([])

const timezones = (Intl as unknown as { supportedValuesOf?: (k: string) => string[] }).supportedValuesOf?.('timeZone') ?? ['UTC']
const weekdays = computed(() => [0, 1, 2, 3, 4, 5, 6].map((d) => ({ value: d, label: t(`setup.weekday.${d}`) })))

onMounted(async () => {
  const { data } = await http.get<{ data: Status }>('/setup/status')
  status.value = data.data
  form.enabled_locales = data.data.locales.filter((l) => l.is_enabled).map((l) => l.code)
  form.default_locale = data.data.locales.find((l) => l.is_default)?.code ?? 'en'
  Object.assign(form.formats, data.data.defaults.formats ?? {}, { timezone: form.formats.timezone })
  form.calendar.system = String(data.data.defaults.calendar?.system ?? 'gregorian')
})

function headers() {
  return { 'X-Setup-Token': token.value.trim() }
}

function fail(e: unknown): void {
  if (e instanceof ApiError) {
    errors.value = e.fieldErrors
    error.value = e.status === 422 ? (Object.values(e.fieldErrors)[0] ?? e.message) : e.message
    if (e.code === 'invalid_setup_token') step.value = 'token'
  } else {
    error.value = t('errors.unexpected')
  }
}

async function run(fn: () => Promise<void>): Promise<void> {
  busy.value = true
  error.value = ''
  errors.value = {}
  try {
    await ensureCsrf()
    await fn()
  } catch (e) {
    fail(e)
  } finally {
    busy.value = false
  }
}

const next = () => (step.value = steps[stepIndex.value + 1]!)
const back = () => (step.value = steps[stepIndex.value - 1]!)

const verifyToken = () =>
  run(async () => {
    await http.post('/setup/token', { token: token.value.trim() })
    next()
  })

async function upload(kind: 'logo' | 'favicon', event: Event): Promise<void> {
  const file = (event.target as HTMLInputElement).files?.[0]
  if (!file) return
  await run(async () => {
    const body = new FormData()
    body.append('file', file)
    const { data } = await http.post<{ data: { uuid: string } }>(`/setup/branding/${kind}`, body, { headers: headers() })
    form[`${kind}_file`] = data.data.uuid
  })
}

const brandingValid = computed(() => !!form.system_name[form.default_locale]?.trim() || !!Object.values(form.system_name).some((v) => v?.trim()))
const languagesValid = computed(() => form.enabled_locales.includes(form.default_locale))

const testMail = () =>
  run(async () => {
    mailResult.value = null
    try {
      await http.post('/setup/mail-test', { mail: form.mail, to: testTo.value }, { headers: headers() })
      mailResult.value = { ok: true, text: t('setup.mail_test_ok') }
    } catch (e) {
      mailResult.value = { ok: false, text: e instanceof ApiError ? `${e.message} ${(e.body as { detail?: string }).detail ?? ''}` : t('errors.unexpected') }
    }
  })

const startTwoFactor = () =>
  run(async () => {
    const { data } = await http.post<{ data: { secret: string; qr: string } }>(
      '/setup/two-factor',
      { email: form.admin.email, issuer: Object.values(form.system_name).find(Boolean) },
      { headers: headers() },
    )
    secret.value = data.data.secret
    qr.value = data.data.qr
  })

const complete = () =>
  run(async () => {
    const payload = {
      ...form,
      system_name: Object.fromEntries(Object.entries(form.system_name).filter(([, v]) => v?.trim())),
      mail: skipMail.value ? null : form.mail,
      two_factor_code: code.value,
    }
    const { data } = await http.post<{ data: { recovery_codes: string[] } }>('/setup/complete', payload, { headers: headers() })
    recoveryCodes.value = data.data.recovery_codes
    step.value = 'done'
  })

async function finish(): Promise<void> {
  await session.loadBootstrap()
  await session.loadMe()
  await router.push({ name: 'admin' })
}
</script>

<template>
  <div class="min-h-full flex flex-col items-center p-4 gap-6">
    <div class="w-full max-w-3xl flex items-center justify-between">
      <BrandMark size="lg" />
      <LanguageSwitcher />
    </div>
    <main class="w-full max-w-3xl bg-card rounded-xl shadow p-6" data-testid="setup-wizard">
      <h1 class="page-title">{{ t('setup.title') }}</h1>
      <ol class="flex flex-wrap gap-2 mb-6 text-sm" :aria-label="t('setup.progress')">
        <li
          v-for="(s, i) in steps"
          :key="s"
          class="px-3 py-1 rounded-full"
          :class="i === stepIndex ? 'bg-primary text-primary-contrast' : i < stepIndex ? 'bg-primary-subtle text-on-primary-subtle' : 'bg-subtle'"
          :aria-current="i === stepIndex ? 'step' : undefined"
        >
          {{ i + 1 }}. {{ t(`setup.step.${s}`) }}
        </li>
      </ol>
      <Message v-if="error" severity="error" class="mb-4" data-testid="setup-error">{{ error }}</Message>

      <section v-if="step === 'token'" class="flex flex-col gap-4">
        <p>{{ t('setup.token_hint') }}</p>
        <code class="ltr-value block p-3 rounded bg-subtle">php artisan setup:token</code>
        <div class="field">
          <label for="token">{{ t('setup.token') }}</label>
          <InputText id="token" v-model="token" autocomplete="off" class="ltr-value" data-testid="setup-token" />
        </div>
        <div class="flex justify-end"><Button :label="t('common.continue')" :loading="busy" :disabled="!token.trim()" data-testid="setup-next" @click="verifyToken" /></div>
      </section>

      <section v-else-if="step === 'branding'" class="flex flex-col gap-4">
        <div class="form-grid">
          <div v-for="l in status?.locales ?? []" :key="l.code" class="field">
            <label :for="`name-${l.code}`">{{ t('setup.system_name') }} ({{ l.native_name }})</label>
            <InputText :id="`name-${l.code}`" v-model="form.system_name[l.code]" :dir="l.direction" maxlength="120" :data-testid="`system-name-${l.code}`" />
          </div>
        </div>
        <div class="form-grid">
          <div class="field">
            <label for="logo">{{ t('setup.logo') }}</label>
            <input id="logo" type="file" accept=".png,.jpg,.jpeg,.webp" @change="(e) => upload('logo', e)" />
            <small v-if="form.logo_file" class="text-success">{{ t('setup.uploaded') }}</small>
          </div>
          <div class="field">
            <label for="favicon">{{ t('setup.favicon') }}</label>
            <input id="favicon" type="file" accept=".png,.ico" @change="(e) => upload('favicon', e)" />
            <small v-if="form.favicon_file" class="text-success">{{ t('setup.uploaded') }}</small>
          </div>
        </div>
        <div class="flex justify-between">
          <Button severity="secondary" :label="t('common.back')" @click="back" /><Button :label="t('common.continue')" :disabled="!brandingValid" data-testid="setup-next" @click="next" />
        </div>
      </section>

      <section v-else-if="step === 'languages'" class="flex flex-col gap-4">
        <p class="text-muted-color">{{ t('setup.languages_hint') }}</p>
        <div v-for="l in status?.locales ?? []" :key="l.code" class="flex items-center gap-6">
          <label class="flex items-center gap-2"><Checkbox v-model="form.enabled_locales" :value="l.code" :input-id="`en-${l.code}`" />{{ l.native_name }}</label>
          <label class="flex items-center gap-2 text-sm"><RadioButton v-model="form.default_locale" :value="l.code" :input-id="`def-${l.code}`" />{{ t('setup.default_language') }}</label>
        </div>
        <Message v-if="!languagesValid" severity="warn">{{ t('setup.default_must_be_enabled') }}</Message>
        <div class="field">
          <label>{{ t('setup.tenancy_mode') }}</label>
          <label class="flex items-center gap-2"><RadioButton v-model="form.tenancy.mode" value="single" />{{ t('setup.tenancy.single') }}</label>
          <label class="flex items-center gap-2"><RadioButton v-model="form.tenancy.mode" value="multi" />{{ t('setup.tenancy.multi') }}</label>
          <small class="text-muted-color">{{ t('setup.tenancy_fixed') }}</small>
        </div>
        <div class="flex justify-between">
          <Button severity="secondary" :label="t('common.back')" @click="back" /><Button :label="t('common.continue')" :disabled="!languagesValid" data-testid="setup-next" @click="next" />
        </div>
      </section>

      <section v-else-if="step === 'regional'" class="flex flex-col gap-4">
        <div class="form-grid">
          <div class="field">
            <label for="tz">{{ t('settings.formats.timezone') }}</label
            ><Select v-model="form.formats.timezone" input-id="tz" :options="timezones" filter />
          </div>
          <div class="field">
            <label for="df">{{ t('settings.formats.date_format') }}</label
            ><InputText id="df" v-model="form.formats.date_format as string" class="ltr-value" />
          </div>
          <div class="field">
            <label for="tf">{{ t('settings.formats.time_format') }}</label
            ><Select v-model="form.formats.time_format" input-id="tf" :options="['24h', '12h']" />
          </div>
          <div class="field">
            <label for="fd">{{ t('settings.formats.first_day_of_week') }}</label
            ><Select v-model="form.formats.first_day_of_week" input-id="fd" :options="weekdays" option-label="label" option-value="value" />
          </div>
          <div class="field">
            <label for="dg">{{ t('settings.formats.digits') }}</label
            ><Select v-model="form.formats.digits" input-id="dg" :options="['locale', 'western', 'arabic_indic'].map((v) => ({ v, l: t(`settings.digits.${v}`) }))" option-label="l" option-value="v" />
          </div>
          <div class="field">
            <label for="cal">{{ t('settings.calendar.system') }}</label
            ><Select
              v-model="form.calendar.system"
              input-id="cal"
              :options="['gregorian', 'hijri', 'both'].map((v) => ({ v, l: t(`settings.calendar_system.${v}`) }))"
              option-label="l"
              option-value="v"
            />
          </div>
        </div>
        <div class="flex justify-between"><Button severity="secondary" :label="t('common.back')" @click="back" /><Button :label="t('common.continue')" data-testid="setup-next" @click="next" /></div>
      </section>

      <section v-else-if="step === 'mail'" class="flex flex-col gap-4">
        <label class="flex items-center gap-2"><Checkbox v-model="skipMail" binary input-id="skip-mail" />{{ t('setup.skip_mail') }}</label>
        <template v-if="!skipMail">
          <div class="form-grid">
            <div class="field">
              <label for="mh">{{ t('settings.mail.host') }}</label
              ><InputText id="mh" v-model="form.mail.host" class="ltr-value" />
            </div>
            <div class="field">
              <label for="mp">{{ t('settings.mail.port') }}</label
              ><InputNumber v-model="form.mail.port" input-id="mp" :use-grouping="false" :min="1" :max="65535" />
            </div>
            <div class="field">
              <label for="me">{{ t('settings.mail.encryption') }}</label
              ><Select v-model="form.mail.encryption" input-id="me" :options="['tls', 'ssl', 'none']" />
            </div>
            <div class="field">
              <label for="mu">{{ t('settings.mail.username') }}</label
              ><InputText id="mu" v-model="form.mail.username" class="ltr-value" autocomplete="off" />
            </div>
            <div class="field">
              <label for="mpw">{{ t('settings.mail.password') }}</label
              ><Password v-model="form.mail.password" input-id="mpw" :feedback="false" toggle-mask autocomplete="new-password" fluid />
            </div>
            <div class="field">
              <label for="mfa">{{ t('settings.mail.from_address') }}</label
              ><InputText id="mfa" v-model="form.mail.from_address" type="email" class="ltr-value" />
            </div>
            <div class="field">
              <label for="mfn">{{ t('settings.mail.from_name') }}</label
              ><InputText id="mfn" v-model="form.mail.from_name" />
            </div>
          </div>
          <div class="flex flex-wrap items-end gap-2">
            <div class="field grow">
              <label for="mt">{{ t('settings.mail.test_to') }}</label
              ><InputText id="mt" v-model="testTo" type="email" class="ltr-value" />
            </div>
            <Button severity="secondary" icon="pi pi-send" :label="t('settings.mail.send_test')" :loading="busy" :disabled="!testTo || !form.mail.host" @click="testMail" />
          </div>
          <Message v-if="mailResult" :severity="mailResult.ok ? 'success' : 'error'">{{ mailResult.text }}</Message>
        </template>
        <div class="flex justify-between">
          <Button severity="secondary" :label="t('common.back')" @click="back" /><Button
            :label="t('common.continue')"
            :disabled="!skipMail && (!form.mail.host || !form.mail.from_address)"
            data-testid="setup-next"
            @click="next"
          />
        </div>
      </section>

      <section v-else-if="step === 'admin'" class="flex flex-col gap-4">
        <p class="text-muted-color">{{ t('setup.admin_hint') }}</p>
        <div class="form-grid">
          <div class="field">
            <label for="an">{{ t('users.name') }}</label
            ><InputText id="an" v-model="form.admin.name" autocomplete="name" data-testid="admin-name" />
          </div>
          <div class="field">
            <label for="ae">{{ t('users.email') }}</label
            ><InputText id="ae" v-model="form.admin.email" type="email" autocomplete="email" class="ltr-value" data-testid="admin-email" /><span v-if="errors['admin.email']" class="field-error">{{
              errors['admin.email']
            }}</span>
          </div>
        </div>
        <PasswordFields v-model:password="form.admin.password" v-model:confirmation="form.admin.password_confirmation" :errors="errors" />
        <div class="border-t border-line pt-4 flex flex-col gap-3">
          <h2 class="font-semibold">{{ t('profile.two_factor') }}</h2>
          <p class="text-sm text-muted-color">{{ t('setup.two_factor_hint') }}</p>
          <Button v-if="!qr" severity="secondary" icon="pi pi-qrcode" :label="t('setup.show_qr')" :disabled="!form.admin.email" :loading="busy" data-testid="show-qr" @click="startTwoFactor" />
          <template v-else>
            <img :src="qr" :alt="t('profile.qr_alt')" class="w-48 h-48 bg-paper p-2 rounded" />
            <p class="text-sm">
              {{ t('profile.manual_key') }} <code class="ltr-value" data-testid="totp-secret">{{ secret }}</code>
            </p>
            <div class="flex flex-col gap-2 ltr-value items-start">
              <label for="setup-otp">{{ t('auth.authenticator_code') }}</label>
              <InputOtp id="setup-otp" v-model="code" :length="6" integer-only data-testid="setup-otp" />
              <span v-if="errors.two_factor_code" class="field-error">{{ errors.two_factor_code }}</span>
            </div>
          </template>
        </div>
        <div class="flex justify-between">
          <Button severity="secondary" :label="t('common.back')" @click="back" /><Button
            :label="t('setup.finish')"
            icon="pi pi-check"
            :loading="busy"
            :disabled="code.length !== 6 || !form.admin.password"
            data-testid="setup-complete"
            @click="complete"
          />
        </div>
      </section>

      <section v-else class="flex flex-col gap-4">
        <Message severity="success">{{ t('setup.done') }}</Message>
        <p>{{ t('profile.recovery_codes_hint') }}</p>
        <ul class="grid grid-cols-2 gap-2 ltr-value font-mono" data-testid="recovery-codes">
          <li v-for="c in recoveryCodes" :key="c" class="p-2 rounded bg-subtle">{{ c }}</li>
        </ul>
        <div class="flex justify-end"><Button :label="t('setup.open_console')" icon="pi pi-arrow-right" data-testid="open-console" @click="finish" /></div>
      </section>
    </main>
  </div>
</template>
