<script setup lang="ts">
import Button from 'primevue/button'
import InputText from 'primevue/inputtext'
import Select from 'primevue/select'
import SelectButton from 'primevue/selectbutton'
import { useConfirm } from 'primevue/useconfirm'
import { useToast } from 'primevue/usetoast'
import { computed, onMounted, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { onBeforeRouteLeave, useRoute, useRouter } from 'vue-router'
import FormPicker from '@/builder/FormPicker.vue'
import ConfigField from '@/components/config/ConfigField.vue'
import ConfigSaveBar from '@/components/config/ConfigSaveBar.vue'
import ConfigSection from '@/components/config/ConfigSection.vue'
import EmptyState from '@/components/config/EmptyState.vue'
import SettingSwitch from '@/components/config/SettingSwitch.vue'
import UserPicker from '@/components/UserPicker.vue'
import { provideConfigScreen } from '@/components/config/configScreen'
import { ApiError } from '@/api/http'
import { errorText } from '../admin/building/shared'
import DeliveryLog from './DeliveryLog.vue'
import SecretDialog from './SecretDialog.vue'
import { inboundApi, inboundFieldsApi, type InboundDoc, type InboundKind, type InboundOperation, type MappableField } from './api'
import type { DeliveryStatus } from './api'

/**
 * An incoming endpoint (specification §4.33): the address other systems
 * call, the form it fills, whether it creates, updates or does either, the
 * field that finds the record, and where in the JSON each field's value is.
 * A webhook endpoint checks a signature and acts as a chosen user; an API
 * endpoint takes a personal API token and acts as its owner. The call log
 * sits below.
 */
const route = useRoute()
const router = useRouter()
const { t } = useI18n()
const toast = useToast()
const confirm = useConfirm()
provideConfigScreen()

const uuid = ref(String(route.params.endpoint))
const isNew = computed(() => uuid.value === 'new')
const doc = ref<InboundDoc | null>(null)
const saved = ref('')
const saving = ref(false)
const errors = ref<Record<string, string>>({})
const secret = ref<string | null>(null)
const url = ref('')
const runAsName = ref<string | null>(null)
const fields = ref<MappableField[]>([])
const dirty = computed(() => doc.value !== null && JSON.stringify(doc.value) !== saved.value)

const kinds = computed(() => (['webhook', 'api'] as InboundKind[]).map((v) => ({ value: v, label: t(`intg.kind.${v}`) })))
const operations = computed(() => (['create', 'update', 'upsert'] as InboundOperation[]).map((v) => ({ value: v, label: t(`intg.operation_name.${v}`) })))
const algorithms = [
  { value: 'hmac_sha256', label: 'HMAC-SHA256' },
  { value: 'hmac_sha512', label: 'HMAC-SHA512' },
]
const matchable = computed(() => fields.value.filter((f) => f.matchable).map((f) => ({ value: f.uuid, label: f.label })))

function empty(): InboundDoc {
  return {
    slug: '',
    kind: 'webhook',
    operation: 'create',
    form: null,
    match_field: null,
    mapping: {},
    signature_header: 'X-Signature',
    signature_algorithm: 'hmac_sha256',
    run_as_user: null,
    is_active: true,
  }
}
function apply(d: InboundDoc): void {
  doc.value = {
    slug: d.slug,
    kind: d.kind,
    operation: d.operation,
    form: d.form,
    match_field: d.match_field,
    mapping: { ...d.mapping },
    signature_header: d.signature_header ?? 'X-Signature',
    signature_algorithm: d.signature_algorithm ?? 'hmac_sha256',
    run_as_user: d.run_as_user,
    is_active: d.is_active,
  }
  url.value = d.url ?? ''
  runAsName.value = d.run_as_user_name ?? null
  saved.value = JSON.stringify(doc.value)
}
async function load(): Promise<void> {
  if (isNew.value) {
    doc.value = empty()
    saved.value = JSON.stringify(doc.value)
    return
  }
  try {
    apply(await inboundApi.get(uuid.value))
  } catch (e) {
    toast.add({ severity: 'error', summary: errorText(e, t('intg.load_failed')), life: 6000 })
  }
}
onMounted(load)
watch(
  () => doc.value?.form,
  async (form, before) => {
    fields.value = []
    if (!form || !doc.value) return
    if (before !== undefined && before !== null && before !== form) {
      doc.value.mapping = {}
      doc.value.match_field = null
    }
    fields.value = await inboundFieldsApi.list(form).catch(() => [])
  },
)

function setPath(key: string, path: string): void {
  if (!doc.value) return
  const next = { ...doc.value.mapping }
  if (path.trim() === '') delete next[key]
  else next[key] = path.trim()
  doc.value.mapping = next
}
function setRunAs(u: { uuid: string; name: string } | null): void {
  if (!doc.value || !u) return
  doc.value.run_as_user = u.uuid.toLowerCase()
  runAsName.value = u.name
}

async function save(): Promise<void> {
  if (!doc.value) return
  saving.value = true
  errors.value = {}
  try {
    const result = isNew.value ? await inboundApi.create(doc.value) : await inboundApi.update(uuid.value, doc.value)
    apply(result)
    if (result.secret) secret.value = result.secret
    else toast.add({ severity: 'success', summary: t('intg.saved'), life: 3000 })
    if (isNew.value && result.uuid) {
      uuid.value = result.uuid
      await router.replace({ name: 'admin.integrations.inbound', params: { endpoint: result.uuid } })
    }
  } catch (e) {
    errors.value = e instanceof ApiError ? e.fieldErrors : {}
    toast.add({ severity: 'error', summary: errorText(e, t('intg.save_failed')), life: 6000 })
  } finally {
    saving.value = false
  }
}
function discard(): void {
  if (saved.value) doc.value = JSON.parse(saved.value) as InboundDoc
  errors.value = {}
}
onBeforeRouteLeave(() => !dirty.value || window.confirm(t('records.leave_unsaved')))
const err = (path: string) => errors.value[path]
const loadLog = (page: number, status: DeliveryStatus | null) => inboundApi.deliveries(uuid.value, page, status)

function rotate(): void {
  confirm.require({
    header: t('intg.rotate_title'),
    message: t('intg.rotate_inbound_text'),
    acceptLabel: t('intg.rotate'),
    rejectLabel: t('common.cancel'),
    acceptProps: { severity: 'danger' },
    accept: async () => {
      try {
        secret.value = await inboundApi.rotate(uuid.value)
      } catch (e) {
        toast.add({ severity: 'error', summary: errorText(e, t('intg.rotate_failed')), life: 6000 })
      }
    },
  })
}
function remove(): void {
  confirm.require({
    header: t('intg.inbound_delete_title'),
    message: t('intg.inbound_delete_text'),
    acceptLabel: t('intg.delete'),
    rejectLabel: t('common.cancel'),
    acceptProps: { severity: 'danger' },
    accept: async () => {
      try {
        await inboundApi.remove(uuid.value)
        saved.value = JSON.stringify(doc.value)
        await router.push({ name: 'admin.integrations', params: { tab: 'inbound' } })
      } catch (e) {
        toast.add({ severity: 'error', summary: errorText(e, t('intg.delete_failed')), life: 6000 })
      }
    },
  })
}
</script>

<template>
  <div class="flex flex-col gap-6 max-w-[56rem]" data-testid="inbound-editor">
    <header class="page-head">
      <div class="flex-1 min-w-0">
        <RouterLink :to="{ name: 'admin.integrations', params: { tab: 'inbound' } }" class="text-sm text-primary inline-flex items-center gap-1 mb-1">
          <i class="pi pi-arrow-left rtl:rotate-180" aria-hidden="true" />{{ t('admin.area.integrations') }}
        </RouterLink>
        <h1 class="page-title !mb-1">{{ isNew ? t('intg.inbound_new') : t('intg.inbound_edit') }}</h1>
        <p class="m-0 text-sm text-muted-color">{{ t('intg.inbound_subtitle') }}</p>
      </div>
      <Button v-if="!isNew" icon="pi pi-trash" :label="t('intg.delete')" size="small" severity="danger" text data-testid="inbound-delete" @click="remove" />
    </header>
    <SecretDialog :secret="secret" :title="t('intg.secret_title')" :text="t('intg.inbound_secret_text')" @close="secret = null" />

    <template v-if="doc">
      <ConfigSection id="inbound-basics" :title="t('intg.section.basics')">
        <div class="cfg-row">
          <ConfigField :label="t('intg.slug')" for="inbound-slug" width="sm" :hint="t('intg.slug_hint')" :error="err('slug')" required>
            <InputText id="inbound-slug" v-model="doc.slug" dir="ltr" class="ltr-value" size="small" maxlength="64" data-testid="inbound-slug" />
          </ConfigField>
          <ConfigField :label="t('intg.form')" for="inbound-form" width="md" :error="err('form')" required>
            <FormPicker v-model="doc.form" input-id="inbound-form" kind="form" />
          </ConfigField>
        </div>
        <p v-if="url" class="m-0 text-sm">
          {{ t('intg.address') }}: <span class="ltr-value font-medium" data-testid="inbound-url">POST {{ url }}</span>
        </p>
        <ConfigField :label="t('intg.inbound_kind')" for="inbound-kind" width="lg" :hint="t(`intg.kind_hint.${doc.kind}`)" :error="err('kind')">
          <SelectButton v-model="doc.kind" :options="kinds" option-label="label" option-value="value" :allow-empty="false" data-testid="inbound-kind" />
        </ConfigField>
        <SettingSwitch id="inbound-active" v-model="doc.is_active" :label="t('intg.active')" :description="t('intg.inbound_active_desc')" />
      </ConfigSection>

      <ConfigSection v-if="doc.kind === 'webhook'" id="inbound-signature" :title="t('intg.section.signature')" :description="t('intg.signature_hint')">
        <div class="cfg-row">
          <ConfigField :label="t('intg.signature_header')" for="inbound-sig-header" width="sm" :error="err('signature_header')">
            <InputText id="inbound-sig-header" v-model="doc.signature_header" dir="ltr" class="ltr-value" size="small" maxlength="64" />
          </ConfigField>
          <ConfigField :label="t('intg.signature_algorithm')" for="inbound-sig-algo" width="sm" :error="err('signature_algorithm')">
            <Select v-model="doc.signature_algorithm" input-id="inbound-sig-algo" :options="algorithms" option-label="label" option-value="value" size="small" />
          </ConfigField>
        </div>
        <ConfigField :label="t('intg.run_as')" for="inbound-run-as" width="md" :hint="t('intg.run_as_hint')" :error="err('run_as_user')">
          <div class="flex flex-col gap-1">
            <span v-if="doc.run_as_user" class="text-sm" data-testid="inbound-run-as-name">{{ runAsName }}</span>
            <UserPicker input-id="inbound-run-as" :model-value="null" @update:model-value="setRunAs" />
          </div>
        </ConfigField>
        <div v-if="!isNew">
          <Button icon="pi pi-refresh" :label="t('intg.rotate')" size="small" outlined severity="danger" data-testid="inbound-rotate" @click="rotate" />
        </div>
      </ConfigSection>

      <ConfigSection id="inbound-records" :title="t('intg.section.records')" :description="t('intg.records_hint')">
        <div class="cfg-row">
          <ConfigField :label="t('intg.operation')" for="inbound-operation" width="md" :error="err('operation')">
            <Select v-model="doc.operation" input-id="inbound-operation" :options="operations" option-label="label" option-value="value" size="small" data-testid="inbound-operation" />
          </ConfigField>
          <ConfigField v-if="doc.operation !== 'create'" :label="t('intg.match_field')" for="inbound-match" width="md" :hint="t('intg.match_hint')" :error="err('match_field')">
            <Select v-model="doc.match_field" input-id="inbound-match" :options="matchable" option-label="label" option-value="value" size="small" :placeholder="t('intg.choose_field')" />
          </ConfigField>
        </div>
      </ConfigSection>

      <ConfigSection id="inbound-mapping" :title="t('intg.section.mapping')" :description="t('intg.mapping_hint')" :count="Object.keys(doc.mapping).length">
        <EmptyState v-if="!doc.form" icon="pi pi-sitemap" :title="t('intg.mapping_no_form_title')" :description="t('intg.mapping_no_form_text')" />
        <p v-else-if="err('mapping')" class="m-0 text-sm text-danger">{{ err('mapping') }}</p>
        <div v-for="f in fields" :key="f.uuid" class="cfg-row items-center">
          <label :for="`inbound-map-${f.key}`" class="w-field-md text-sm font-medium" dir="auto">
            {{ f.label }}
            <span v-if="f.rows" class="text-xs text-muted-color">· {{ t('intg.rows_list') }}</span>
          </label>
          <ConfigField :label="t('intg.json_path')" :for="`inbound-map-${f.key}`" width="md" :error="err(`mapping.${f.key}`)">
            <InputText
              :id="`inbound-map-${f.key}`"
              :model-value="doc.mapping[f.key] ?? ''"
              dir="ltr"
              class="ltr-value"
              size="small"
              maxlength="200"
              :placeholder="t('intg.not_mapped')"
              :data-testid="`inbound-map-${f.key}`"
              @update:model-value="(v: string | undefined) => setPath(f.key, v ?? '')"
            />
          </ConfigField>
        </div>
      </ConfigSection>

      <ConfigSection v-if="!isNew" id="inbound-log" :title="t('intg.section.calls')" :description="t('intg.calls_hint')">
        <DeliveryLog direction="incoming" :load="loadLog" />
      </ConfigSection>

      <ConfigSaveBar :dirty="dirty || isNew" :saving="saving" testid="inbound" @save="save" @discard="discard" />
    </template>
  </div>
</template>
