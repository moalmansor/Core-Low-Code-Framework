<script setup lang="ts">
import Button from 'primevue/button'
import Column from 'primevue/column'
import DataTable from 'primevue/datatable'
import InputNumber from 'primevue/inputnumber'
import InputText from 'primevue/inputtext'
import Password from 'primevue/password'
import Select from 'primevue/select'
import Tag from 'primevue/tag'
import Textarea from 'primevue/textarea'
import { useConfirm } from 'primevue/useconfirm'
import { useToast } from 'primevue/usetoast'
import { computed, onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { onBeforeRouteLeave, useRoute, useRouter } from 'vue-router'
import I18nInput from '@/builder/I18nInput.vue'
import ConfigField from '@/components/config/ConfigField.vue'
import ConfigSaveBar from '@/components/config/ConfigSaveBar.vue'
import ConfigSection from '@/components/config/ConfigSection.vue'
import EmptyState from '@/components/config/EmptyState.vue'
import EmptyValue from '@/components/EmptyValue.vue'
import SettingSwitch from '@/components/config/SettingSwitch.vue'
import { provideConfigScreen } from '@/components/config/configScreen'
import { formatDatetime } from '@/runtime/format'
import { errorText, fieldErrors } from '../building/shared'
import { channelsApi, type ChannelDelivery, type ChannelDoc, type ChannelType, type ProviderFields } from './api'

/**
 * One SMS or messaging channel (specification §4.33, ADR-0056): its
 * provider and settings (secrets are write-only: left empty, the saved one
 * stays), a per-minute limit, a test message, and the delivery log with a
 * way to send a failed message again.
 */
provideConfigScreen()
const { t, locale } = useI18n()
const toast = useToast()
const confirm = useConfirm()
const route = useRoute()
const router = useRouter()
const isNew = computed(() => route.params.channel === 'new')
const blank = (): ChannelDoc => ({ key: '', type: 'sms', provider: 'twilio', name: {}, config: {}, rate_limit_per_minute: null, active: true })
const doc = ref<ChannelDoc>(blank())
const providers = ref<ProviderFields>({ sms: {}, messaging: {} })
const loaded = ref(false)
const saved = ref('')
const saving = ref(false)
const errors = ref<Record<string, string>>({})
const testTo = ref('')
const testing = ref(false)
const deliveries = ref<ChannelDelivery[]>([])
const deliveryTotal = ref(0)
const deliveryPage = ref(1)
const deliveryStatus = ref<string | null>(null)

onMounted(async () => {
  try {
    const [list, d] = await Promise.all([channelsApi.list(), isNew.value ? Promise.resolve(blank()) : channelsApi.load(String(route.params.channel))])
    providers.value = list.meta.providers
    accept(d)
    loaded.value = true
    if (!isNew.value) await loadDeliveries()
  } catch (e) {
    toast.add({ severity: 'error', summary: errorText(e, t('nch.load_failed')), life: 6000 })
  }
})
/** What the server keeps; secrets typed in are sent, never received. */
const editable = (d: ChannelDoc) => ({ key: d.key, type: d.type, provider: d.provider, name: d.name, config: d.config, rate_limit_per_minute: d.rate_limit_per_minute, active: d.active })
function accept(d: ChannelDoc): void {
  if (Array.isArray(d.name)) d.name = {}
  doc.value = d
  saved.value = JSON.stringify(editable(d))
}
const dirty = computed(() => loaded.value && JSON.stringify(editable(doc.value)) !== saved.value)
onBeforeRouteLeave(() => !dirty.value || window.confirm(t('records.leave_unsaved')))
const err = (k: string) => errors.value[k]

const typeOptions = computed(() => (['sms', 'messaging'] as ChannelType[]).map((v) => ({ value: v, label: t(`nch.type.${v}`) })))
const providerOptions = computed(() => Object.keys(providers.value[doc.value.type] ?? {}).map((v) => ({ value: v, label: t(`nch.provider.${v}`) })))
const fields = computed(() => Object.entries(providers.value[doc.value.type]?.[doc.value.provider] ?? {}).map(([name, f]) => ({ name, ...f })))
function setType(v: ChannelType): void {
  doc.value.type = v
  doc.value.provider = Object.keys(providers.value[v] ?? {})[0] ?? ''
  doc.value.config = {}
}
function setProvider(v: string): void {
  doc.value.provider = v
  doc.value.config = {}
}
const configValue = (name: string) => (doc.value.config[name] ?? '') as string
const setConfig = (name: string, v: string | undefined) => (doc.value.config = { ...doc.value.config, [name]: v ?? '' })

async function save(): Promise<void> {
  saving.value = true
  errors.value = {}
  try {
    const body = editable(doc.value) as ChannelDoc
    const out = isNew.value ? await channelsApi.create(body) : await channelsApi.save(doc.value.uuid!, body, doc.value.hash!)
    accept(out)
    toast.add({ severity: 'success', summary: t('nch.saved'), life: 3000 })
    if (isNew.value) await router.replace({ name: 'admin.notification_channels.channel', params: { channel: out.uuid } })
  } catch (e) {
    errors.value = fieldErrors(e)
    toast.add({ severity: 'error', summary: errorText(e, t('nch.save_failed')), life: 8000 })
  } finally {
    saving.value = false
  }
}
function discard(): void {
  accept({ ...doc.value, ...(JSON.parse(saved.value) as Partial<ChannelDoc>) })
  errors.value = {}
}
function remove(): void {
  confirm.require({
    header: t('nch.delete_title'),
    message: t('nch.delete_text'),
    acceptProps: { label: t('nch.delete'), severity: 'danger' },
    rejectProps: { label: t('nch.cancel'), text: true },
    accept: async () => {
      try {
        await channelsApi.remove(doc.value.uuid!)
        saved.value = JSON.stringify(editable(doc.value))
        await router.push({ name: 'admin.notification_channels' })
      } catch (e) {
        toast.add({ severity: 'error', summary: errorText(e, t('nch.delete_failed')), life: 8000 })
      }
    },
  })
}
async function sendTest(): Promise<void> {
  testing.value = true
  try {
    const out = await channelsApi.test(doc.value.uuid!, doc.value.type === 'sms' ? testTo.value.trim() : null)
    toast.add({ severity: out.ok ? 'success' : 'error', summary: out.ok ? t('nch.test_ok') : t('nch.test_failed'), detail: out.ok ? undefined : out.answer, life: out.ok ? 4000 : 10000 })
  } catch (e) {
    toast.add({ severity: 'error', summary: errorText(e, t('nch.test_failed')), life: 8000 })
  } finally {
    testing.value = false
  }
}
async function loadDeliveries(): Promise<void> {
  const out = await channelsApi.deliveries(doc.value.uuid!, deliveryPage.value, deliveryStatus.value)
  deliveries.value = out.data
  deliveryTotal.value = out.meta.total
}
async function retry(id: number): Promise<void> {
  try {
    await channelsApi.retry(doc.value.uuid!, id)
    await loadDeliveries()
  } catch (e) {
    toast.add({ severity: 'error', summary: errorText(e, t('nch.save_failed')), life: 6000 })
  }
}
const statusOptions = computed(() => [{ value: null, label: t('nch.all') }, ...['sent', 'failed', 'pending', 'cancelled'].map((v) => ({ value: v as string | null, label: t(`nch.status.${v}`) }))])
const severity = (s: string) => (s === 'sent' ? 'success' : s === 'failed' ? 'danger' : 'secondary')
</script>

<template>
  <div class="flex flex-col gap-6 max-w-[64rem]" data-testid="channel-editor">
    <header class="page-head">
      <div class="flex-1 min-w-0">
        <RouterLink :to="{ name: 'admin.notification_channels' }" class="text-sm text-primary inline-flex items-center gap-1 mb-1">
          <i class="pi pi-arrow-left rtl:rotate-180" aria-hidden="true" />{{ t('admin.area.notification_channels') }}
        </RouterLink>
        <h1 class="page-title !mb-1" dir="auto">{{ isNew ? t('nch.new') : (doc.label ?? t('nch.channel')) }}</h1>
        <p class="m-0 text-sm text-muted-color">{{ t('nch.editor_subtitle') }}</p>
      </div>
      <Button v-if="!isNew && loaded" icon="pi pi-trash" :label="t('nch.delete')" size="small" severity="danger" text data-testid="channel-delete" @click="remove" />
    </header>

    <template v-if="loaded">
      <ConfigSection id="channel-basics" :title="t('nch.section.basics')">
        <I18nInput v-model="doc.name" class="w-field-md" :label="t('nch.name')" :maxlength="255" :invalid="!!err('name')" />
        <small v-if="err('name')" class="text-danger">{{ err('name') }}</small>
        <div class="cfg-row">
          <ConfigField :label="t('nch.key')" for="channel-key" width="md" :hint="t('nch.key_hint')" :error="err('key')">
            <InputText id="channel-key" v-model="doc.key" class="ltr-value" size="small" maxlength="48" data-testid="channel-key" />
          </ConfigField>
          <ConfigField :label="t('nch.kind')" for="channel-type" width="sm" :error="err('type')">
            <Select
              :model-value="doc.type"
              input-id="channel-type"
              :options="typeOptions"
              option-label="label"
              option-value="value"
              size="small"
              :disabled="!isNew"
              data-testid="channel-type"
              @update:model-value="setType"
            />
          </ConfigField>
        </div>
        <SettingSwitch id="channel-active" v-model="doc.active" :label="t('nch.active')" :description="t('nch.active_desc')" />
      </ConfigSection>

      <ConfigSection id="channel-provider" :title="t('nch.section.provider')" :description="t(`nch.provider_hint.${doc.type}`)">
        <div class="cfg-row">
          <ConfigField :label="t('nch.provider_label')" for="channel-provider" width="md" :error="err('provider')">
            <Select
              :model-value="doc.provider"
              input-id="channel-provider"
              :options="providerOptions"
              option-label="label"
              option-value="value"
              size="small"
              data-testid="channel-provider"
              @update:model-value="setProvider"
            />
          </ConfigField>
        </div>
        <ConfigField
          v-for="f in fields"
          :key="f.name"
          :label="t(`nch.field.${f.name}`)"
          :for="`channel-${f.name}`"
          :width="f.name === 'body' ? 'lg' : 'md'"
          :hint="t(`nch.field_hint.${f.name}`)"
          :required="f.required"
          :error="err(`config.${f.name}`)"
        >
          <Password
            v-if="f.secret"
            :model-value="configValue(f.name)"
            :input-id="`channel-${f.name}`"
            :feedback="false"
            toggle-mask
            size="small"
            fluid
            input-class="ltr-value"
            :placeholder="doc.config[`${f.name}_set`] ? t('nch.kept') : ''"
            :data-testid="`channel-${f.name}`"
            @update:model-value="(v) => setConfig(f.name, v)"
          />
          <Textarea
            v-else-if="f.name === 'body'"
            :id="`channel-${f.name}`"
            :model-value="configValue(f.name)"
            rows="4"
            class="ltr-value font-mono text-sm w-full"
            spellcheck="false"
            :data-testid="`channel-${f.name}`"
            @update:model-value="(v) => setConfig(f.name, v)"
          />
          <Select
            v-else-if="f.name === 'method'"
            :model-value="configValue(f.name) || 'POST'"
            :input-id="`channel-${f.name}`"
            :options="['POST', 'PUT']"
            size="small"
            @update:model-value="(v) => setConfig(f.name, v)"
          />
          <InputText
            v-else
            :id="`channel-${f.name}`"
            :model-value="configValue(f.name)"
            class="ltr-value"
            size="small"
            :data-testid="`channel-${f.name}`"
            @update:model-value="(v) => setConfig(f.name, v)"
          />
        </ConfigField>
        <ConfigField :label="t('nch.rate_limit')" for="channel-limit" width="sm" :hint="t('nch.rate_limit_hint')" :error="err('rate_limit_per_minute')">
          <InputNumber v-model="doc.rate_limit_per_minute" input-id="channel-limit" :min="1" :max="6000" size="small" />
        </ConfigField>
      </ConfigSection>

      <ConfigSection id="channel-test" :title="t('nch.section.test')" :description="t('nch.test_hint')">
        <EmptyState v-if="isNew" icon="pi pi-send" :title="t('nch.test_after_save')" :description="t('nch.test_after_save_text')" testid="channel-test-later" />
        <div v-else class="flex flex-wrap items-end gap-3">
          <ConfigField v-if="doc.type === 'sms'" :label="t('nch.test_to')" for="channel-test-to" width="md" :hint="t('nch.test_to_hint')">
            <InputText id="channel-test-to" v-model="testTo" class="ltr-value" size="small" placeholder="+9665…" data-testid="channel-test-to" />
          </ConfigField>
          <Button icon="pi pi-send" :label="t('nch.send_test')" size="small" outlined :loading="testing" :disabled="dirty" data-testid="channel-send-test" @click="sendTest" />
        </div>
      </ConfigSection>

      <ConfigSection v-if="!isNew" id="channel-log" :title="t('nch.section.log')" :description="t('nch.log_hint')" :count="deliveryTotal">
        <div class="flex flex-wrap items-end gap-2">
          <Select
            v-model="deliveryStatus"
            :options="statusOptions"
            option-label="label"
            option-value="value"
            size="small"
            :aria-label="t('nch.filter_status')"
            data-testid="channel-log-status"
            @update:model-value="
              () => {
                deliveryPage = 1
                void loadDeliveries()
              }
            "
          />
        </div>
        <DataTable :value="deliveries" data-key="id" size="small" class="records-table" data-testid="channel-log">
          <template #empty>{{ t('nch.log_empty') }}</template>
          <Column :header="t('nch.when')">
            <template #body="{ data }">{{ formatDatetime(data.created_at, locale) }}</template>
          </Column>
          <Column :header="t('nch.recipient')">
            <template #body="{ data }"
              ><span class="ltr-value">{{ data.recipient }}</span></template
            >
          </Column>
          <Column :header="t('nch.message')">
            <template #body="{ data }"
              ><span v-if="data.title" dir="auto">{{ data.title }}</span
              ><EmptyValue v-else
            /></template>
          </Column>
          <Column :header="t('nch.state')">
            <template #body="{ data }">
              <div class="flex flex-col gap-1">
                <Tag :severity="severity(data.status)" :value="t(`nch.status.${data.status}`)" />
                <small v-if="data.error" class="text-danger break-words">{{ data.error }}</small>
              </div>
            </template>
          </Column>
          <Column :header="t('nch.attempts')">
            <template #body="{ data }">{{ data.attempts }}</template>
          </Column>
          <Column>
            <template #body="{ data }">
              <Button v-if="data.status === 'failed' || data.status === 'cancelled'" :label="t('nch.send_again')" size="small" text @click="retry(data.id)" />
            </template>
          </Column>
        </DataTable>
        <div v-if="deliveryTotal > 25" class="flex items-center gap-2">
          <Button
            icon="pi pi-angle-left rtl:rotate-180"
            text
            size="small"
            :disabled="deliveryPage <= 1"
            :aria-label="t('nch.previous')"
            @click="
              () => {
                deliveryPage--
                void loadDeliveries()
              }
            "
          />
          <span class="text-sm">{{ t('nch.page', { page: deliveryPage, pages: Math.ceil(deliveryTotal / 25) }) }}</span>
          <Button
            icon="pi pi-angle-right rtl:rotate-180"
            text
            size="small"
            :disabled="deliveryPage * 25 >= deliveryTotal"
            :aria-label="t('nch.next')"
            @click="
              () => {
                deliveryPage++
                void loadDeliveries()
              }
            "
          />
        </div>
      </ConfigSection>

      <ConfigSaveBar :dirty="dirty || isNew" :saving="saving" testid="channel" @save="save" @discard="discard" />
    </template>
  </div>
</template>
