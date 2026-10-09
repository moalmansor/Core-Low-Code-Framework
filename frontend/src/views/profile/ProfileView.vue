<script setup lang="ts">
import Button from 'primevue/button'
import Column from 'primevue/column'
import DataTable from 'primevue/datatable'
import InputOtp from 'primevue/inputotp'
import InputText from 'primevue/inputtext'
import Message from 'primevue/message'
import Password from 'primevue/password'
import Select from 'primevue/select'
import Tab from 'primevue/tab'
import TabList from 'primevue/tablist'
import TabPanel from 'primevue/tabpanel'
import TabPanels from 'primevue/tabpanels'
import Tabs from 'primevue/tabs'
import Tag from 'primevue/tag'
import { useToast } from 'primevue/usetoast'
import { computed, onMounted, reactive, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRoute } from 'vue-router'
import { ApiError, get, send } from '@/api/http'
import PasswordFields from '@/components/PasswordFields.vue'
import { useSession, type Me } from '@/stores/session'

interface SessionRow {
  handle: string
  ip_address: string | null
  user_agent: string | null
  last_activity: string
  current: boolean
}

const { t } = useI18n()
const toast = useToast()
const route = useRoute()
const session = useSession()
const tab = ref(String(route.query.tab ?? 'details'))
watch(
  () => route.query.tab,
  (v) => v && (tab.value = String(v)),
)

const details = reactive({ name: session.me?.name ?? '', phone: '' })
const prefs = reactive<Me['preferences']>({ locale: null, timezone: null, calendar: null, digits: null, date_format: null, density: null, theme_mode: 'system', ...(session.me?.preferences ?? {}) })
const pw = reactive({ current: '', password: '', confirmation: '' })
const pwErrors = ref<Record<string, string>>({})
const sessions = ref<SessionRow[]>([])

// Two-factor enrollment state
const confirmPassword = ref('')
const enrolling = ref(false)
const qr = ref('')
const secretKey = ref('')
const code = ref('')
const recoveryCodes = ref<string[]>([])
const twoFactorError = ref('')
const isLocal = computed(() => session.me?.auth_source === 'local')
const timezones = (Intl as unknown as { supportedValuesOf?: (k: string) => string[] }).supportedValuesOf?.('timeZone') ?? ['UTC']

onMounted(loadSessions)

async function loadSessions(): Promise<void> {
  sessions.value = (await get<{ data: SessionRow[] }>('/me/sessions')).data
}

async function saveDetails(): Promise<void> {
  await send('patch', '/me', details)
  await session.loadMe()
  toast.add({ severity: 'success', summary: t('common.saved'), life: 3000 })
}

async function savePrefs(): Promise<void> {
  await send('patch', '/me/preferences', prefs)
  if (prefs.locale && prefs.locale !== session.locale) await session.switchLocale(prefs.locale, false)
  session.applyTheme(prefs.theme_mode)
  await session.loadMe()
  toast.add({ severity: 'success', summary: t('common.saved'), life: 3000 })
}

async function changePassword(): Promise<void> {
  pwErrors.value = {}
  try {
    await send('put', '/auth/user/password', { current_password: pw.current, password: pw.password, password_confirmation: pw.confirmation })
    Object.assign(pw, { current: '', password: '', confirmation: '' })
    await session.loadMe()
    toast.add({ severity: 'success', summary: t('profile.password_changed'), life: 4000 })
  } catch (e) {
    if (e instanceof ApiError) pwErrors.value = e.fieldErrors
  }
}

async function startEnrollment(): Promise<void> {
  twoFactorError.value = ''
  try {
    await send('post', '/auth/user/confirm-password', { password: confirmPassword.value })
    await send('post', '/auth/user/two-factor-authentication')
    const svg = (await get<{ svg: string }>('/auth/user/two-factor-qr-code')).svg
    qr.value = `data:image/svg+xml;base64,${btoa(svg)}`
    secretKey.value = (await get<{ secretKey: string }>('/auth/user/two-factor-secret-key')).secretKey
    enrolling.value = true
  } catch (e) {
    twoFactorError.value = e instanceof ApiError ? (Object.values(e.fieldErrors)[0] ?? e.message) : t('errors.unexpected')
  } finally {
    confirmPassword.value = ''
  }
}

async function confirmEnrollment(): Promise<void> {
  twoFactorError.value = ''
  try {
    await send('post', '/auth/user/confirmed-two-factor-authentication', { code: code.value })
    recoveryCodes.value = await get<string[]>('/auth/user/two-factor-recovery-codes')
    enrolling.value = false
    await session.loadMe()
  } catch {
    twoFactorError.value = t('auth.two_factor_invalid')
    code.value = ''
  }
}

async function regenerateCodes(): Promise<void> {
  try {
    await send('post', '/auth/user/confirm-password', { password: confirmPassword.value })
    await send('post', '/auth/user/two-factor-recovery-codes')
    recoveryCodes.value = await get<string[]>('/auth/user/two-factor-recovery-codes')
  } catch (e) {
    twoFactorError.value = e instanceof ApiError ? (Object.values(e.fieldErrors)[0] ?? e.message) : t('errors.unexpected')
  } finally {
    confirmPassword.value = ''
  }
}

async function disableTwoFactor(): Promise<void> {
  try {
    await send('post', '/auth/user/confirm-password', { password: confirmPassword.value })
    await send('delete', '/auth/user/two-factor-authentication')
    await session.loadMe()
  } catch (e) {
    twoFactorError.value = e instanceof ApiError ? (Object.values(e.fieldErrors)[0] ?? e.message) : t('errors.unexpected')
  } finally {
    confirmPassword.value = ''
  }
}

async function revoke(handle: string): Promise<void> {
  await send('delete', `/me/sessions/${handle}`)
  await loadSessions()
}

async function revokeOthers(): Promise<void> {
  await send('delete', '/me/sessions')
  await loadSessions()
}
</script>

<template>
  <h1 class="page-title">{{ t('profile.title') }}</h1>
  <Message v-if="session.gate === 'two_factor'" severity="warn" class="mb-4" data-testid="enroll-required">{{ t('profile.two_factor_required_notice') }}</Message>
  <Message v-if="session.gate === 'password_expired'" severity="warn" class="mb-4">{{ t('profile.password_expired_notice') }}</Message>
  <Tabs v-model:value="tab">
    <TabList>
      <Tab value="details">{{ t('profile.details') }}</Tab>
      <Tab value="preferences">{{ t('profile.preferences') }}</Tab>
      <Tab v-if="isLocal" value="password">{{ t('profile.password') }}</Tab>
      <Tab value="security">{{ t('profile.two_factor') }}</Tab>
      <Tab value="sessions">{{ t('profile.sessions') }}</Tab>
    </TabList>
    <TabPanels>
      <TabPanel value="details">
        <form class="form-grid max-w-2xl" @submit.prevent="saveDetails">
          <div class="field">
            <label for="pn">{{ t('users.name') }}</label
            ><InputText id="pn" v-model="details.name" />
          </div>
          <div class="field">
            <label for="pe">{{ t('users.email') }}</label
            ><InputText id="pe" :model-value="session.me?.email" disabled class="ltr-value" />
          </div>
          <div class="field">
            <label for="pp">{{ t('users.phone') }}</label
            ><InputText id="pp" v-model="details.phone" class="ltr-value" />
          </div>
          <div class="flex items-end"><Button type="submit" :label="t('common.save')" /></div>
        </form>
      </TabPanel>
      <TabPanel value="preferences">
        <form class="form-grid max-w-2xl" @submit.prevent="savePrefs">
          <div class="field">
            <label for="pl">{{ t('shell.language') }}</label
            ><Select v-model="prefs.locale" input-id="pl" :options="session.boot?.locales ?? []" option-label="native_name" option-value="code" show-clear />
          </div>
          <div class="field">
            <label for="pt">{{ t('profile.theme') }}</label
            ><Select v-model="prefs.theme_mode" input-id="pt" :options="['system', 'light', 'dark'].map((v) => ({ v, l: t(`profile.theme_mode.${v}`) }))" option-label="l" option-value="v" />
          </div>
          <div class="field">
            <label for="ptz">{{ t('settings.formats.timezone') }}</label
            ><Select v-model="prefs.timezone" input-id="ptz" :options="timezones" filter show-clear />
          </div>
          <div class="field">
            <label for="pc">{{ t('settings.calendar.system') }}</label
            ><Select
              v-model="prefs.calendar"
              input-id="pc"
              :options="['gregorian', 'hijri', 'both'].map((v) => ({ v, l: t(`settings.calendar_system.${v}`) }))"
              option-label="l"
              option-value="v"
              show-clear
            />
          </div>
          <div class="field">
            <label for="pd">{{ t('settings.formats.digits') }}</label
            ><Select v-model="prefs.digits" input-id="pd" :options="['western', 'arabic_indic'].map((v) => ({ v, l: t(`settings.digits.${v}`) }))" option-label="l" option-value="v" show-clear />
          </div>
          <div class="flex items-end"><Button type="submit" :label="t('common.save')" data-testid="save-preferences" /></div>
        </form>
      </TabPanel>
      <TabPanel v-if="isLocal" value="password">
        <form class="flex flex-col gap-4 max-w-md" @submit.prevent="changePassword">
          <div class="field">
            <label for="cur">{{ t('auth.current_password') }}</label>
            <Password v-model="pw.current" input-id="cur" :feedback="false" toggle-mask autocomplete="current-password" fluid />
            <span v-if="pwErrors.current_password" class="field-error">{{ pwErrors.current_password }}</span>
          </div>
          <PasswordFields v-model:password="pw.password" v-model:confirmation="pw.confirmation" :errors="pwErrors" />
          <Button type="submit" :label="t('profile.change_password')" />
        </form>
      </TabPanel>
      <TabPanel value="security">
        <div class="flex flex-col gap-4 max-w-xl">
          <div class="flex items-center gap-2">
            <span>{{ t('profile.two_factor_status') }}</span>
            <Tag :severity="session.me?.two_factor.enabled ? 'success' : 'warn'" :value="session.me?.two_factor.enabled ? t('common.enabled') : t('common.disabled')" />
            <Tag v-if="session.me?.two_factor.required" severity="info" :value="t('profile.required_for_role')" />
          </div>
          <Message v-if="twoFactorError" severity="error">{{ twoFactorError }}</Message>
          <template v-if="enrolling">
            <p>{{ t('profile.scan_qr') }}</p>
            <img :src="qr" :alt="t('profile.qr_alt')" class="w-48 h-48 bg-paper p-2 rounded" />
            <p class="text-sm">
              {{ t('profile.manual_key') }} <code class="ltr-value">{{ secretKey }}</code>
            </p>
            <div class="ltr-value"><InputOtp v-model="code" :length="6" integer-only data-testid="enroll-otp" /></div>
            <Button :label="t('auth.verify')" :disabled="code.length !== 6" data-testid="enroll-confirm" @click="confirmEnrollment" />
          </template>
          <template v-else>
            <div class="field">
              <label for="cp">{{ t('profile.confirm_with_password') }}</label>
              <Password v-model="confirmPassword" input-id="cp" :feedback="false" toggle-mask autocomplete="current-password" fluid data-testid="confirm-password" />
            </div>
            <div class="flex flex-wrap gap-2">
              <Button
                v-if="!session.me?.two_factor.enabled"
                :label="t('profile.enable_two_factor')"
                icon="pi pi-shield"
                :disabled="!confirmPassword"
                data-testid="enable-2fa"
                @click="startEnrollment"
              />
              <template v-else>
                <Button severity="secondary" :label="t('profile.regenerate_codes')" :disabled="!confirmPassword" @click="regenerateCodes" />
                <Button v-if="!session.me?.two_factor.required" severity="danger" :label="t('profile.disable_two_factor')" :disabled="!confirmPassword" @click="disableTwoFactor" />
              </template>
            </div>
          </template>
          <template v-if="recoveryCodes.length">
            <Message severity="info">{{ t('profile.recovery_codes_hint') }}</Message>
            <ul class="grid grid-cols-2 gap-2 ltr-value font-mono" data-testid="profile-recovery-codes">
              <li v-for="c in recoveryCodes" :key="c" class="p-2 rounded bg-subtle">{{ c }}</li>
            </ul>
          </template>
        </div>
      </TabPanel>
      <TabPanel value="sessions">
        <div class="flex justify-end mb-2"><Button severity="secondary" :label="t('profile.revoke_others')" icon="pi pi-sign-out" @click="revokeOthers" /></div>
        <DataTable :value="sessions" data-key="handle" size="small">
          <Column :header="t('profile.device')"
            ><template #body="{ data }"
              ><span class="text-sm">{{ data.user_agent }}</span></template
            ></Column
          >
          <Column field="ip_address" :header="t('profile.ip')"
            ><template #body="{ data }"
              ><span class="ltr-value">{{ data.ip_address }}</span></template
            ></Column
          >
          <Column :header="t('profile.last_active')"
            ><template #body="{ data }">{{ new Date(data.last_activity).toLocaleString(session.locale) }}</template></Column
          >
          <Column>
            <template #body="{ data }">
              <Tag v-if="data.current" :value="t('profile.this_device')" severity="success" />
              <Button v-else size="small" severity="danger" text :label="t('profile.revoke')" @click="revoke(data.handle)" />
            </template>
          </Column>
        </DataTable>
      </TabPanel>
    </TabPanels>
  </Tabs>
</template>
