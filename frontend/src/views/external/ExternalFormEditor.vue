<script setup lang="ts">
import Button from 'primevue/button'
import Checkbox from 'primevue/checkbox'
import DatePicker from 'primevue/datepicker'
import InputNumber from 'primevue/inputnumber'
import InputText from 'primevue/inputtext'
import MultiSelect from 'primevue/multiselect'
import Password from 'primevue/password'
import Select from 'primevue/select'
import SelectButton from 'primevue/selectbutton'
import Tag from 'primevue/tag'
import { useConfirm } from 'primevue/useconfirm'
import { useToast } from 'primevue/usetoast'
import { computed, onMounted, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { onBeforeRouteLeave, useRoute, useRouter } from 'vue-router'
import FormPicker from '@/builder/FormPicker.vue'
import I18nInput from '@/builder/I18nInput.vue'
import ConfigField from '@/components/config/ConfigField.vue'
import ConfigSaveBar from '@/components/config/ConfigSaveBar.vue'
import ConfigSection from '@/components/config/ConfigSection.vue'
import EmptyState from '@/components/config/EmptyState.vue'
import SettingSwitch from '@/components/config/SettingSwitch.vue'
import { provideConfigScreen } from '@/components/config/configScreen'
import UserPicker from '@/components/UserPicker.vue'
import { errorText, fieldErrors } from '../admin/building/shared'
import { themesApi, type ThemeSummary } from '../experience/api'
import { accountsApi, externalFormsApi, type Audience, type ExposableField, type ExternalFormDoc, type ExternalRole, type StatusChoice, type Verification } from './api'

/**
 * One external form (specification §4.32, ADR-0058): its address, texts and
 * theme; the fields it shows (only those that need no internal data) and the
 * status a submission starts in; who may open it and who the records are
 * created as; and its protections — password, CAPTCHA, limits, opening
 * times, a cap and verification of an email address or mobile number.
 */
provideConfigScreen()
const { t } = useI18n()
const toast = useToast()
const confirm = useConfirm()
const route = useRoute()
const router = useRouter()
const isNew = computed(() => route.params.form === 'new')
const blank = (): ExternalFormDoc => ({
  form: null,
  slug: '',
  title: {},
  intro: {},
  success_message: {},
  theme: null,
  initial_status: null,
  fields: [],
  captcha: true,
  rate_limit_per_ip: 20,
  opens_at: null,
  closes_at: null,
  daily_window: null,
  submission_cap: null,
  verification: 'none',
  run_as: null,
  active: true,
  audience: 'public',
  roles: [],
})
const doc = ref<ExternalFormDoc>(blank())
const loaded = ref(false)
const saved = ref('')
const saving = ref(false)
const errors = ref<Record<string, string>>({})
const exposable = ref<ExposableField[]>([])
const statuses = ref<StatusChoice[]>([])
const themes = ref<ThemeSummary[]>([])
const roles = ref<ExternalRole[]>([])
const runAsName = ref<string | null>(null)
const password = ref('')
const clearPassword = ref(false)
const windowOn = ref(false)
const windowFrom = ref('08:00')
const windowTo = ref('16:00')
const windowDays = ref<number[]>([0, 1, 2, 3, 4])

onMounted(async () => {
  try {
    const [d, th, r] = await Promise.all([isNew.value ? Promise.resolve(blank()) : externalFormsApi.load(String(route.params.form)), themesApi.list(), accountsApi.roles()])
    themes.value = th
    roles.value = r
    accept(d)
    loaded.value = true
  } catch (e) {
    toast.add({ severity: 'error', summary: errorText(e, t('xa.load_failed')), life: 6000 })
  }
})
/** What the server keeps; the password is write-only. */
const editable = (d: ExternalFormDoc) => ({
  form: d.form,
  slug: d.slug,
  title: d.title,
  intro: d.intro,
  success_message: d.success_message,
  theme: d.theme,
  initial_status: d.initial_status,
  fields: d.fields,
  captcha: d.captcha,
  rate_limit_per_ip: d.rate_limit_per_ip,
  opens_at: d.opens_at,
  closes_at: d.closes_at,
  daily_window: d.daily_window,
  submission_cap: d.submission_cap,
  verification: d.verification,
  run_as: d.run_as,
  active: d.active,
  audience: d.audience,
  roles: d.roles,
})
function accept(d: ExternalFormDoc): void {
  for (const k of ['title', 'intro', 'success_message'] as const) if (Array.isArray(d[k])) d[k] = {}
  doc.value = d
  runAsName.value = d.run_as_name ?? null
  windowOn.value = d.daily_window !== null
  if (d.daily_window) {
    windowFrom.value = d.daily_window.from
    windowTo.value = d.daily_window.to
    windowDays.value = [...d.daily_window.days]
  }
  password.value = ''
  clearPassword.value = false
  saved.value = JSON.stringify(editable(d))
}
// The daily window is edited in its own controls.
watch([windowOn, windowFrom, windowTo, windowDays], () => {
  doc.value.daily_window = windowOn.value ? { from: windowFrom.value, to: windowTo.value, days: [...windowDays.value].sort() } : null
})
const dirty = computed(() => loaded.value && (JSON.stringify(editable(doc.value)) !== saved.value || password.value !== '' || clearPassword.value))
onBeforeRouteLeave(() => !dirty.value || window.confirm(t('records.leave_unsaved')))
const err = (k: string) => errors.value[k]

// What the chosen form can show.
watch(
  () => doc.value.form,
  async (form, before) => {
    exposable.value = []
    statuses.value = []
    if (!form) return
    try {
      const out = await externalFormsApi.fields(form)
      exposable.value = out.fields
      statuses.value = out.statuses
      if (before !== undefined && before !== null && before !== form) {
        doc.value.fields = []
        doc.value.initial_status = null
      }
    } catch (e) {
      toast.add({ severity: 'error', summary: errorText(e, t('xa.load_failed')), life: 6000 })
    }
  },
)
const inputs = computed(() => exposable.value.filter((f) => !f.display))
const displays = computed(() => exposable.value.filter((f) => f.display))
const statusOptions = computed(() => [{ uuid: null as string | null, label: t('xa.status_initial') }, ...statuses.value.map((s) => ({ uuid: s.uuid as string | null, label: s.label }))])
const themeOptions = computed(() => [{ uuid: null as string | null, label: t('xa.theme_default') }, ...themes.value.map((th) => ({ uuid: th.uuid as string | null, label: th.label }))])
const audienceOptions = computed(() => (['public', 'accounts'] as Audience[]).map((v) => ({ value: v, label: t(`xa.audience_${v}`) })))
const verificationOptions = computed(() => (['none', 'email', 'sms'] as Verification[]).map((v) => ({ value: v, label: t(`xa.verification_${v}`) })))
const dayOptions = computed(() => [0, 1, 2, 3, 4, 5, 6].map((d) => ({ value: d, label: t(`xa.day.${d}`) })))
const opens = computed({ get: () => (doc.value.opens_at ? new Date(doc.value.opens_at) : null), set: (v: Date | null) => (doc.value.opens_at = v ? v.toISOString() : null) })
const closes = computed({ get: () => (doc.value.closes_at ? new Date(doc.value.closes_at) : null), set: (v: Date | null) => (doc.value.closes_at = v ? v.toISOString() : null) })
const url = computed(() => `${window.location.origin}/x/f/${doc.value.slug || '…'}`)
function setRunAs(u: { uuid: string; name: string } | null): void {
  if (!u) return
  doc.value.run_as = u.uuid.toLowerCase()
  runAsName.value = u.name
}

async function save(): Promise<void> {
  saving.value = true
  errors.value = {}
  try {
    const body = { ...editable(doc.value), ...(password.value !== '' ? { password: password.value } : {}), ...(clearPassword.value ? { clear_password: true } : {}) } as ExternalFormDoc
    const out = isNew.value ? await externalFormsApi.create(body) : await externalFormsApi.save(doc.value.uuid!, body, doc.value.hash!)
    accept(out)
    toast.add({ severity: 'success', summary: t('xa.saved'), life: 3000 })
    if (isNew.value) await router.replace({ name: 'admin.external.form', params: { form: out.uuid } })
  } catch (e) {
    errors.value = fieldErrors(e)
    toast.add({ severity: 'error', summary: errorText(e, t('xa.save_failed')), life: 8000 })
  } finally {
    saving.value = false
  }
}
function discard(): void {
  accept({ ...doc.value, ...(JSON.parse(saved.value) as Partial<ExternalFormDoc>) })
  errors.value = {}
}
function remove(): void {
  confirm.require({
    header: t('xa.delete_title'),
    message: t('xa.delete_text'),
    acceptProps: { label: t('xa.delete'), severity: 'danger' },
    rejectProps: { label: t('xa.cancel'), text: true },
    accept: async () => {
      try {
        await externalFormsApi.remove(doc.value.uuid!)
        saved.value = JSON.stringify(editable(doc.value))
        password.value = ''
        clearPassword.value = false
        await router.push({ name: 'admin.external' })
      } catch (e) {
        toast.add({ severity: 'error', summary: errorText(e, t('xa.delete_failed')), life: 8000 })
      }
    },
  })
}
</script>

<template>
  <div class="flex flex-col gap-6 max-w-[64rem]" data-testid="xa-form-editor">
    <header class="page-head">
      <div class="flex-1 min-w-0">
        <RouterLink :to="{ name: 'admin.external' }" class="text-sm text-primary inline-flex items-center gap-1 mb-1">
          <i class="pi pi-arrow-left rtl:rotate-180" aria-hidden="true" />{{ t('admin.area.external_access') }}
        </RouterLink>
        <h1 class="page-title !mb-1" dir="auto">{{ isNew ? t('xa.new_form') : (doc.label ?? t('xa.form_page')) }}</h1>
        <p class="m-0 text-sm text-muted-color">{{ t('xa.editor_subtitle') }}</p>
      </div>
      <a v-if="!isNew && loaded" :href="doc.url" target="_blank" rel="noopener" class="p-button p-button-sm p-button-outlined no-underline" data-testid="xa-open-page">
        <i class="pi pi-external-link me-2" aria-hidden="true" />{{ t('xa.open_page') }}
      </a>
      <Button v-if="!isNew && loaded" icon="pi pi-trash" :label="t('xa.delete')" size="small" severity="danger" text data-testid="xa-delete" @click="remove" />
    </header>

    <template v-if="loaded">
      <ConfigSection id="xa-page" :title="t('xa.section.page')" :description="t('xa.section.page_hint')">
        <I18nInput v-model="doc.title" class="w-field-md" :label="t('xa.title')" :maxlength="255" :invalid="!!err('title')" />
        <small v-if="err('title')" class="text-danger">{{ err('title') }}</small>
        <div class="cfg-row">
          <ConfigField :label="t('xa.slug')" for="xa-slug" width="md" :hint="t('xa.slug_hint', { url })" :error="err('slug')">
            <InputText id="xa-slug" v-model="doc.slug" class="ltr-value" size="small" maxlength="64" data-testid="xa-slug" />
          </ConfigField>
          <ConfigField :label="t('xa.form')" for="xa-form-pick" width="md" :error="err('form')">
            <FormPicker v-model="doc.form" kind="form" input-id="xa-form-pick" />
          </ConfigField>
        </div>
        <I18nInput v-model="doc.intro" multiline :rows="3" :label="t('xa.intro')" :maxlength="4000" />
        <I18nInput v-model="doc.success_message" multiline :rows="2" :label="t('xa.success_message')" :maxlength="4000" />
        <ConfigField :label="t('xa.theme')" for="xa-theme" width="md" :error="err('theme')">
          <Select v-model="doc.theme" input-id="xa-theme" :options="themeOptions" option-label="label" option-value="uuid" size="small" data-testid="xa-theme" />
        </ConfigField>
        <SettingSwitch id="xa-active" v-model="doc.active" :label="t('xa.active')" :description="t('xa.active_desc')" />
      </ConfigSection>

      <ConfigSection id="xa-fields" :title="t('xa.section.fields')" :description="t('xa.section.fields_hint')" :count="doc.fields.length">
        <EmptyState v-if="!doc.form" icon="pi pi-list" :title="t('xa.pick_form_first')" :description="t('xa.pick_form_first_text')" testid="xa-fields-empty" />
        <template v-else>
          <small v-if="err('fields')" class="text-danger" data-testid="xa-fields-error">{{ err('fields') }}</small>
          <fieldset class="flex flex-col gap-2 border-0 p-0 m-0">
            <legend class="text-sm font-medium mb-1">{{ t('xa.fields_inputs') }}</legend>
            <label v-for="f in inputs" :key="f.uuid" class="flex items-center gap-2 text-sm" :data-testid="`xa-field-${f.key}`">
              <Checkbox v-model="doc.fields" :value="f.uuid" :input-id="`xa-f-${f.key}`" />
              <span dir="auto">{{ f.label }}</span>
              <Tag v-if="f.required" severity="warn" :value="t('xa.required')" />
            </label>
          </fieldset>
          <fieldset v-if="displays.length" class="flex flex-col gap-2 border-0 p-0 m-0">
            <legend class="text-sm font-medium mb-1">{{ t('xa.fields_display') }}</legend>
            <label v-for="f in displays" :key="f.uuid" class="flex items-center gap-2 text-sm">
              <Checkbox v-model="doc.fields" :value="f.uuid" :input-id="`xa-f-${f.key}`" />
              <span dir="auto">{{ f.label }}</span>
            </label>
          </fieldset>
          <p class="m-0 text-xs text-muted-color">{{ t('xa.fields_excluded') }}</p>
          <ConfigField :label="t('xa.initial_status')" for="xa-status" width="md" :hint="t('xa.initial_status_hint')" :error="err('initial_status')">
            <Select v-model="doc.initial_status" input-id="xa-status" :options="statusOptions" option-label="label" option-value="uuid" size="small" data-testid="xa-status" />
          </ConfigField>
        </template>
      </ConfigSection>

      <ConfigSection id="xa-access" :title="t('xa.section.access')" :description="t('xa.section.access_hint')">
        <ConfigField :label="t('xa.audience')" for="xa-audience" width="lg">
          <SelectButton v-model="doc.audience" :options="audienceOptions" option-label="label" option-value="value" :allow-empty="false" size="small" data-testid="xa-audience" />
        </ConfigField>
        <ConfigField v-if="doc.audience === 'accounts'" :label="t('xa.roles')" for="xa-roles" width="md" :hint="t('xa.roles_hint')" :error="err('roles')">
          <MultiSelect v-model="doc.roles" input-id="xa-roles" :options="roles" option-label="label" option-value="uuid" display="chip" size="small" :empty-message="t('xa.no_roles')" />
        </ConfigField>
        <ConfigField :label="t('xa.run_as')" for="xa-run-as" width="md" :hint="t('xa.run_as_hint')" :error="err('run_as')">
          <div class="flex flex-wrap items-center gap-2">
            <span v-if="runAsName" class="text-sm" data-testid="xa-run-as-name">{{ runAsName }}</span>
            <UserPicker input-id="xa-run-as" :model-value="null" @update:model-value="setRunAs" />
          </div>
        </ConfigField>
        <ConfigField :label="t('xa.password')" for="xa-password" width="md" :hint="t('xa.password_hint')" :error="err('password')">
          <Password
            v-model="password"
            input-id="xa-password"
            :feedback="false"
            toggle-mask
            size="small"
            :placeholder="doc.has_password ? t('xa.password_kept') : ''"
            :disabled="clearPassword"
            data-testid="xa-password"
          />
        </ConfigField>
        <label v-if="doc.has_password" class="flex items-center gap-2 text-sm"><Checkbox v-model="clearPassword" binary input-id="xa-clear-password" />{{ t('xa.password_clear') }}</label>
      </ConfigSection>

      <ConfigSection id="xa-protection" :title="t('xa.section.protection')" :description="t('xa.section.protection_hint')">
        <SettingSwitch id="xa-captcha" v-model="doc.captcha" :label="t('xa.captcha')" :description="t('xa.captcha_desc')" />
        <div class="cfg-row">
          <ConfigField :label="t('xa.rate')" for="xa-rate" width="sm" :hint="t('xa.rate_hint')" :error="err('rate_limit_per_ip')">
            <InputNumber v-model="doc.rate_limit_per_ip" input-id="xa-rate" :min="1" :max="10000" size="small" data-testid="xa-rate" />
          </ConfigField>
          <ConfigField :label="t('xa.cap')" for="xa-cap" width="sm" :hint="t('xa.cap_hint')" :error="err('submission_cap')">
            <InputNumber v-model="doc.submission_cap" input-id="xa-cap" :min="1" size="small" data-testid="xa-cap" />
          </ConfigField>
        </div>
        <div class="cfg-row">
          <ConfigField :label="t('xa.opens_at')" for="xa-opens" width="md" :error="err('opens_at')">
            <DatePicker v-model="opens" input-id="xa-opens" show-time hour-format="24" show-button-bar size="small" />
          </ConfigField>
          <ConfigField :label="t('xa.closes_at')" for="xa-closes" width="md" :error="err('closes_at')">
            <DatePicker v-model="closes" input-id="xa-closes" show-time hour-format="24" show-button-bar size="small" />
          </ConfigField>
        </div>
        <SettingSwitch id="xa-window" v-model="windowOn" :label="t('xa.window')" :description="t('xa.window_desc')" />
        <template v-if="windowOn">
          <div class="cfg-row">
            <ConfigField :label="t('xa.window_from')" for="xa-window-from" width="xs" :error="err('daily_window')">
              <InputText id="xa-window-from" v-model="windowFrom" type="time" size="small" class="ltr-value" />
            </ConfigField>
            <ConfigField :label="t('xa.window_to')" for="xa-window-to" width="xs">
              <InputText id="xa-window-to" v-model="windowTo" type="time" size="small" class="ltr-value" />
            </ConfigField>
          </div>
          <ConfigField :label="t('xa.window_days')" for="xa-window-days" width="lg">
            <MultiSelect v-model="windowDays" input-id="xa-window-days" :options="dayOptions" option-label="label" option-value="value" display="chip" size="small" />
          </ConfigField>
        </template>
        <ConfigField :label="t('xa.verification')" for="xa-verification" width="md" :hint="t('xa.verification_hint')" :error="err('verification')">
          <Select v-model="doc.verification" input-id="xa-verification" :options="verificationOptions" option-label="label" option-value="value" size="small" data-testid="xa-verification" />
        </ConfigField>
      </ConfigSection>

      <ConfigSaveBar :dirty="dirty || isNew" :saving="saving" testid="xa-form" @save="save" @discard="discard" />
    </template>
  </div>
</template>
