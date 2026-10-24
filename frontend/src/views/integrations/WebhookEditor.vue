<script setup lang="ts">
import Button from 'primevue/button'
import Checkbox from 'primevue/checkbox'
import InputNumber from 'primevue/inputnumber'
import InputText from 'primevue/inputtext'
import Select from 'primevue/select'
import { useConfirm } from 'primevue/useconfirm'
import { useToast } from 'primevue/usetoast'
import { computed, onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { onBeforeRouteLeave, useRoute, useRouter } from 'vue-router'
import FormPicker from '@/builder/FormPicker.vue'
import I18nInput from '@/builder/I18nInput.vue'
import ConfigField from '@/components/config/ConfigField.vue'
import ConfigSaveBar from '@/components/config/ConfigSaveBar.vue'
import ConfigSection from '@/components/config/ConfigSection.vue'
import SettingSwitch from '@/components/config/SettingSwitch.vue'
import { provideConfigScreen } from '@/components/config/configScreen'
import { ApiError } from '@/api/http'
import { useSession } from '@/stores/session'
import { errorText } from '../admin/building/shared'
import DeliveryLog from './DeliveryLog.vue'
import SecretDialog from './SecretDialog.vue'
import { WEBHOOK_EVENTS, webhooksApi, type HttpMethod, type WebhookDoc, type WebhookEvent } from './api'
import type { DeliveryStatus } from './api'

/**
 * An outgoing webhook (specification §4.22): which record events of which
 * form it sends, to which allowlisted address, with which extra headers,
 * time limit and retries. The signing secret is shown once; header values
 * are never sent back (an empty value keeps the saved one). The delivery log
 * sits below.
 */
const route = useRoute()
const router = useRouter()
const { t } = useI18n()
const toast = useToast()
const confirm = useConfirm()
const session = useSession()
provideConfigScreen()

const uuid = ref(String(route.params.webhook))
const isNew = computed(() => uuid.value === 'new')
const doc = ref<WebhookDoc | null>(null)
const saved = ref('')
const saving = ref(false)
const errors = ref<Record<string, string>>({})
const secret = ref<string | null>(null)
const log = ref<InstanceType<typeof DeliveryLog> | null>(null)
const dirty = computed(() => doc.value !== null && JSON.stringify(doc.value) !== saved.value)
const dl = computed(() => session.boot?.default_locale ?? 'en')
const methods: HttpMethod[] = ['POST', 'PUT', 'PATCH', 'DELETE', 'GET']

function empty(): WebhookDoc {
  return { key: '', name: {}, form: null, url: 'https://', method: 'POST', events: ['record.created'], headers: [], timeout_seconds: 10, max_retries: 3, is_active: true }
}
function apply(d: WebhookDoc): void {
  doc.value = {
    key: d.key,
    name: { ...d.name },
    form: d.form,
    url: d.url,
    method: d.method,
    events: [...d.events],
    headers: (d.header_names ?? []).map((name) => ({ name, value: '' })),
    timeout_seconds: d.timeout_seconds,
    max_retries: d.max_retries,
    is_active: d.is_active,
  }
  saved.value = JSON.stringify(doc.value)
}
async function load(): Promise<void> {
  if (isNew.value) {
    doc.value = empty()
    saved.value = JSON.stringify(doc.value)
    return
  }
  try {
    apply(await webhooksApi.get(uuid.value))
  } catch (e) {
    toast.add({ severity: 'error', summary: errorText(e, t('intg.load_failed')), life: 6000 })
  }
}
onMounted(load)

function toggleEvent(e: WebhookEvent, on: boolean): void {
  if (!doc.value) return
  doc.value.events = on ? [...new Set([...doc.value.events, e])] : doc.value.events.filter((x) => x !== e)
}

async function save(): Promise<void> {
  if (!doc.value) return
  saving.value = true
  errors.value = {}
  try {
    if (isNew.value) {
      const made = await webhooksApi.create(doc.value)
      apply(made)
      secret.value = made.secret
      uuid.value = made.uuid!
      await router.replace({ name: 'admin.integrations.webhook', params: { webhook: made.uuid } })
    } else {
      apply(await webhooksApi.update(uuid.value, doc.value))
      toast.add({ severity: 'success', summary: t('intg.saved'), life: 3000 })
    }
  } catch (e) {
    errors.value = e instanceof ApiError ? e.fieldErrors : {}
    toast.add({ severity: 'error', summary: errorText(e, t('intg.save_failed')), life: 6000 })
  } finally {
    saving.value = false
  }
}
function discard(): void {
  if (saved.value) doc.value = JSON.parse(saved.value) as WebhookDoc
  errors.value = {}
}
onBeforeRouteLeave(() => !dirty.value || window.confirm(t('records.leave_unsaved')))
const err = (path: string) => errors.value[path]
const loadLog = (page: number, status: DeliveryStatus | null) => webhooksApi.deliveries(uuid.value, page, status)

const testing = ref(false)
async function test(): Promise<void> {
  testing.value = true
  try {
    const d = await webhooksApi.test(uuid.value)
    toast.add({ severity: d.status === 'succeeded' ? 'success' : 'warn', summary: d.status === 'succeeded' ? t('intg.test_ok') : t('intg.test_failed', { error: d.error ?? '' }), life: 6000 })
    await log.value?.refresh()
  } catch (e) {
    toast.add({ severity: 'error', summary: errorText(e, t('intg.test_failed', { error: '' })), life: 6000 })
  } finally {
    testing.value = false
  }
}
function rotate(): void {
  confirm.require({
    header: t('intg.rotate_title'),
    message: t('intg.rotate_text'),
    acceptLabel: t('intg.rotate'),
    rejectLabel: t('common.cancel'),
    acceptProps: { severity: 'danger' },
    accept: async () => {
      try {
        secret.value = await webhooksApi.rotate(uuid.value)
      } catch (e) {
        toast.add({ severity: 'error', summary: errorText(e, t('intg.rotate_failed')), life: 6000 })
      }
    },
  })
}
function remove(): void {
  confirm.require({
    header: t('intg.webhook_delete_title'),
    message: t('intg.webhook_delete_text'),
    acceptLabel: t('intg.delete'),
    rejectLabel: t('common.cancel'),
    acceptProps: { severity: 'danger' },
    accept: async () => {
      try {
        await webhooksApi.remove(uuid.value)
        saved.value = JSON.stringify(doc.value)
        await router.push({ name: 'admin.integrations' })
      } catch (e) {
        toast.add({ severity: 'error', summary: errorText(e, t('intg.delete_failed')), life: 6000 })
      }
    },
  })
}
</script>

<template>
  <div class="flex flex-col gap-6 max-w-[56rem]" data-testid="webhook-editor">
    <header class="page-head">
      <div class="flex-1 min-w-0">
        <RouterLink :to="{ name: 'admin.integrations' }" class="text-sm text-primary inline-flex items-center gap-1 mb-1">
          <i class="pi pi-arrow-left rtl:rotate-180" aria-hidden="true" />{{ t('admin.area.integrations') }}
        </RouterLink>
        <h1 class="page-title !mb-1">{{ isNew ? t('intg.webhook_new') : t('intg.webhook_edit') }}</h1>
        <p class="m-0 text-sm text-muted-color">{{ t('intg.webhook_subtitle') }}</p>
      </div>
      <template v-if="!isNew">
        <Button icon="pi pi-send" :label="t('intg.send_test')" size="small" outlined :loading="testing" :disabled="dirty" data-testid="webhook-test" @click="test" />
        <Button icon="pi pi-trash" :label="t('intg.delete')" size="small" severity="danger" text data-testid="webhook-delete" @click="remove" />
      </template>
    </header>
    <SecretDialog :secret="secret" :title="t('intg.secret_title')" :text="t('intg.secret_text')" @close="secret = null" />

    <template v-if="doc">
      <ConfigSection id="webhook-basics" :title="t('intg.section.basics')">
        <I18nInput v-model="doc.name" class="w-field-md" :label="t('intg.name')" :maxlength="255" :invalid="!!err(`name.${dl}`)" data-testid="webhook-name" />
        <div class="cfg-row">
          <ConfigField :label="t('intg.key')" for="webhook-key" width="sm" :hint="t('intg.key_hint')" :error="err('key')" required>
            <InputText id="webhook-key" v-model="doc.key" dir="ltr" class="ltr-value" size="small" maxlength="48" data-testid="webhook-key" />
          </ConfigField>
          <ConfigField :label="t('intg.form')" for="webhook-form" width="md" :hint="t('intg.webhook_form_hint')" :error="err('form')">
            <FormPicker v-model="doc.form" input-id="webhook-form" />
          </ConfigField>
        </div>
        <ConfigField :label="t('intg.events')" for="webhook-event-record.created" width="full" :error="err('events')">
          <div class="flex flex-wrap gap-x-6 gap-y-2">
            <label v-for="e in WEBHOOK_EVENTS" :key="e" class="inline-flex items-center gap-2 text-sm">
              <Checkbox :input-id="`webhook-event-${e}`" :model-value="doc.events.includes(e)" binary :data-testid="`webhook-event-${e}`" @update:model-value="(v: boolean) => toggleEvent(e, v)" />
              {{ t(`intg.event_name.${e}`) }}
            </label>
          </div>
        </ConfigField>
        <SettingSwitch id="webhook-active" v-model="doc.is_active" :label="t('intg.active')" :description="t('intg.webhook_active_desc')" />
      </ConfigSection>

      <ConfigSection id="webhook-target" :title="t('intg.section.target')" :description="t('intg.target_hint')">
        <div class="cfg-row">
          <ConfigField :label="t('intg.method')" for="webhook-method" width="xs">
            <Select v-model="doc.method" input-id="webhook-method" :options="methods" size="small" />
          </ConfigField>
          <ConfigField :label="t('intg.url')" for="webhook-url" width="lg" :error="err('url')" required>
            <InputText id="webhook-url" v-model="doc.url" dir="ltr" class="ltr-value" size="small" maxlength="2048" data-testid="webhook-url" />
          </ConfigField>
        </div>
        <div class="cfg-row">
          <ConfigField :label="t('intg.timeout')" for="webhook-timeout" width="xs" :hint="t('intg.timeout_hint')" :error="err('timeout_seconds')">
            <InputNumber v-model="doc.timeout_seconds" input-id="webhook-timeout" :min="1" :max="30" size="small" fluid />
          </ConfigField>
          <ConfigField :label="t('intg.retries')" for="webhook-retries" width="xs" :hint="t('intg.retries_hint')" :error="err('max_retries')">
            <InputNumber v-model="doc.max_retries" input-id="webhook-retries" :min="0" :max="5" size="small" fluid />
          </ConfigField>
        </div>
      </ConfigSection>

      <ConfigSection id="webhook-headers" :title="t('intg.section.headers')" :description="t('intg.headers_hint')" :default-open="doc.headers.length > 0" :count="doc.headers.length">
        <div v-for="(h, i) in doc.headers" :key="i" class="cfg-row items-end">
          <ConfigField :label="t('intg.header_name')" :for="`webhook-header-${i}`" width="sm" :error="err(`headers.${i}.name`)">
            <InputText :id="`webhook-header-${i}`" v-model="h.name" dir="ltr" class="ltr-value" size="small" maxlength="64" />
          </ConfigField>
          <ConfigField :label="t('intg.header_value')" :for="`webhook-header-value-${i}`" width="md" :error="err(`headers.${i}.value`)">
            <InputText
              :id="`webhook-header-value-${i}`"
              v-model="h.value"
              type="password"
              dir="ltr"
              class="ltr-value"
              size="small"
              maxlength="1000"
              :placeholder="isNew ? '' : t('intg.header_kept')"
              autocomplete="off"
            />
          </ConfigField>
          <Button icon="pi pi-times" text rounded severity="secondary" size="small" :aria-label="t('intg.header_remove')" @click="doc.headers.splice(i, 1)" />
        </div>
        <div>
          <Button icon="pi pi-plus" :label="t('intg.header_add')" size="small" text :disabled="doc.headers.length >= 20" @click="doc.headers.push({ name: '', value: '' })" />
        </div>
      </ConfigSection>

      <ConfigSection v-if="!isNew" id="webhook-signing" :title="t('intg.section.signing')" :description="t('intg.signing_hint')" :default-open="false">
        <div>
          <Button icon="pi pi-refresh" :label="t('intg.rotate')" size="small" outlined severity="danger" data-testid="webhook-rotate" @click="rotate" />
        </div>
      </ConfigSection>

      <ConfigSection v-if="!isNew" id="webhook-log" :title="t('intg.section.deliveries')" :description="t('intg.deliveries_hint')">
        <DeliveryLog ref="log" direction="outgoing" :load="loadLog" />
      </ConfigSection>

      <ConfigSaveBar :dirty="dirty || isNew" :saving="saving" testid="webhook" @save="save" @discard="discard" />
    </template>
  </div>
</template>
