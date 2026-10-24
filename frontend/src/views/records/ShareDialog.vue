<script setup lang="ts">
import Button from 'primevue/button'
import Column from 'primevue/column'
import DataTable from 'primevue/datatable'
import Dialog from 'primevue/dialog'
import InputNumber from 'primevue/inputnumber'
import InputText from 'primevue/inputtext'
import Message from 'primevue/message'
import Select from 'primevue/select'
import Tab from 'primevue/tab'
import TabList from 'primevue/tablist'
import TabPanel from 'primevue/tabpanel'
import TabPanels from 'primevue/tabpanels'
import Tabs from 'primevue/tabs'
import Tag from 'primevue/tag'
import { useToast } from 'primevue/usetoast'
import { computed, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import EmptyValue from '@/components/EmptyValue.vue'
import { formatDatetime } from '@/runtime/format'
import { errorText, fieldErrors } from '../admin/building/shared'
import { sharingApi, type LinkAction, type NewLink, type NewSignature, type Sharing } from '../external/api'

/**
 * Sharing a record outside (specification §4.32, ADR-0058): a link that
 * shows it, approves or rejects it through a step, or lets someone complete
 * one section — acting as you, once or a few times, until it expires — and
 * signature requests for its documents. Links are shown once; only their
 * fingerprint is kept.
 */
const props = defineProps<{ form: string; record: string }>()
const visible = defineModel<boolean>('visible', { required: true })
const { t, locale } = useI18n()
const toast = useToast()
const tab = ref<'links' | 'signatures'>('links')
const sharing = ref<Sharing | null>(null)
const link = ref<NewLink>({ action: 'view', transition: null, section: null, recipient: '', days: 7, uses: 1 })
const signature = ref<NewSignature>({ template: null, signer_type: 'email', signer: '', days: 14 })
const errors = ref<Record<string, string>>({})
const created = ref<string | null>(null)
const busy = ref(false)

async function load(): Promise<void> {
  try {
    sharing.value = await sharingApi.load(props.form, props.record)
    const c = sharing.value.choices
    if (!c.actions.includes(link.value.action)) link.value.action = 'view'
    signature.value.template ??= sharing.value.templates[0]?.uuid ?? null
  } catch (e) {
    toast.add({ severity: 'error', summary: errorText(e, t('share.load_failed')), life: 6000 })
  }
}
const actionOptions = computed(() => (sharing.value?.choices.actions ?? []).map((a) => ({ value: a as LinkAction, label: t(`share.action.${a}`) })))
async function createLink(): Promise<void> {
  busy.value = true
  errors.value = {}
  try {
    const body = {
      ...link.value,
      transition: ['approve', 'reject'].includes(link.value.action) ? link.value.transition : null,
      section: link.value.action === 'complete_section' ? link.value.section : null,
    }
    const out = await sharingApi.link(props.form, props.record, body)
    created.value = out.url ?? null
    link.value.recipient = ''
    await load()
  } catch (e) {
    errors.value = fieldErrors(e)
    toast.add({ severity: 'error', summary: errorText(e, t('share.failed')), life: 8000 })
  } finally {
    busy.value = false
  }
}
async function copy(): Promise<void> {
  if (!created.value) return
  try {
    await navigator.clipboard.writeText(created.value)
    toast.add({ severity: 'success', summary: t('share.copied'), life: 2500 })
  } catch {
    toast.add({ severity: 'warn', summary: t('share.copy_manually'), life: 4000 })
  }
}
async function revoke(uuid: string): Promise<void> {
  try {
    await sharingApi.revoke(uuid)
    await load()
  } catch (e) {
    toast.add({ severity: 'error', summary: errorText(e, t('share.failed')), life: 8000 })
  }
}
async function requestSignature(): Promise<void> {
  busy.value = true
  errors.value = {}
  try {
    await sharingApi.sign(props.form, props.record, signature.value)
    signature.value.signer = ''
    toast.add({ severity: 'success', summary: t('share.signature_sent'), life: 3000 })
    await load()
  } catch (e) {
    errors.value = fieldErrors(e)
    toast.add({ severity: 'error', summary: errorText(e, t('share.failed')), life: 8000 })
  } finally {
    busy.value = false
  }
}
async function cancel(uuid: string): Promise<void> {
  try {
    await sharingApi.cancel(uuid)
    await load()
  } catch (e) {
    toast.add({ severity: 'error', summary: errorText(e, t('share.failed')), life: 8000 })
  }
}
const linkSeverity = (s: string) => (s === 'active' ? 'success' : 'secondary')
const signSeverity = (s: string) => (s === 'signed' ? 'success' : s === 'declined' ? 'danger' : s === 'pending' || s === 'viewed' ? 'info' : 'secondary')
</script>

<template>
  <Dialog v-model:visible="visible" modal :header="t('share.title')" class="w-full max-w-3xl" data-testid="share-dialog" @show="load" @hide="created = null">
    <Tabs v-model:value="tab">
      <TabList>
        <Tab value="links" data-testid="share-tab-links">{{ t('share.links') }}</Tab>
        <Tab value="signatures" data-testid="share-tab-signatures">{{ t('share.signatures') }}</Tab>
      </TabList>
      <TabPanels class="!px-0">
        <TabPanel value="links">
          <div class="flex flex-col gap-4">
            <p class="m-0 text-sm text-muted-color">{{ t('share.links_hint') }}</p>
            <div class="grid gap-3 sm:grid-cols-2">
              <div class="flex flex-col gap-1">
                <label for="share-action" class="text-sm">{{ t('share.what') }}</label>
                <Select v-model="link.action" input-id="share-action" :options="actionOptions" option-label="label" option-value="value" size="small" data-testid="share-action" />
              </div>
              <div v-if="link.action === 'approve' || link.action === 'reject'" class="flex flex-col gap-1">
                <label for="share-transition" class="text-sm">{{ t('share.step') }}</label>
                <Select
                  v-model="link.transition"
                  input-id="share-transition"
                  :options="sharing?.choices.transitions ?? []"
                  option-label="label"
                  option-value="uuid"
                  size="small"
                  :invalid="!!errors.transition"
                  data-testid="share-transition"
                />
                <small v-if="errors.transition" class="text-danger">{{ errors.transition }}</small>
              </div>
              <div v-if="link.action === 'complete_section'" class="flex flex-col gap-1">
                <label for="share-section" class="text-sm">{{ t('share.section') }}</label>
                <Select v-model="link.section" input-id="share-section" :options="sharing?.choices.sections ?? []" option-label="label" option-value="key" size="small" :invalid="!!errors.section" />
                <small v-if="errors.section" class="text-danger">{{ errors.section }}</small>
              </div>
              <div class="flex flex-col gap-1">
                <label for="share-recipient" class="text-sm">{{ t('share.recipient') }}</label>
                <InputText id="share-recipient" v-model="link.recipient" type="email" class="ltr-value" size="small" :invalid="!!errors.recipient" data-testid="share-recipient" />
                <small class="text-muted-color">{{ errors.recipient ?? t('share.recipient_hint') }}</small>
              </div>
              <div class="flex gap-3">
                <div class="flex flex-col gap-1">
                  <label for="share-days" class="text-sm">{{ t('share.days') }}</label>
                  <InputNumber v-model="link.days" input-id="share-days" :min="1" :max="30" size="small" class="w-24" />
                </div>
                <div class="flex flex-col gap-1">
                  <label for="share-uses" class="text-sm">{{ t('share.uses') }}</label>
                  <InputNumber v-model="link.uses" input-id="share-uses" :min="1" :max="100" size="small" class="w-24" />
                </div>
              </div>
            </div>
            <div><Button icon="pi pi-link" :label="t('share.create_link')" size="small" :loading="busy" data-testid="share-create" @click="createLink" /></div>
            <Message v-if="created" severity="success" data-testid="share-created">
              <div class="flex flex-col gap-2">
                <span>{{ t('share.shown_once') }}</span>
                <span class="ltr-value break-all" data-testid="share-url">{{ created }}</span>
                <div><Button icon="pi pi-copy" :label="t('share.copy')" size="small" outlined @click="copy" /></div>
              </div>
            </Message>
            <DataTable :value="sharing?.links ?? []" data-key="uuid" size="small" class="records-table" data-testid="share-links">
              <template #empty>{{ t('share.no_links') }}</template>
              <Column :header="t('share.what')">
                <template #body="{ data }"
                  >{{ t(`share.action.${data.action}`) }}<span v-if="data.transition" dir="auto"> · {{ data.transition }}</span></template
                >
              </Column>
              <Column :header="t('share.recipient')">
                <template #body="{ data }"
                  ><span v-if="data.recipient" class="ltr-value">{{ data.recipient }}</span
                  ><EmptyValue v-else
                /></template>
              </Column>
              <Column :header="t('share.used')">
                <template #body="{ data }">{{ data.uses }} / {{ data.max_uses }}</template>
              </Column>
              <Column :header="t('share.until')">
                <template #body="{ data }">{{ formatDatetime(data.expires_at, locale) }}</template>
              </Column>
              <Column :header="t('share.state')">
                <template #body="{ data }"><Tag :severity="linkSeverity(data.state)" :value="t(`share.link_state.${data.state}`)" /></template>
              </Column>
              <Column class="w-0">
                <template #body="{ data }">
                  <Button v-if="data.can_revoke && data.state === 'active'" :label="t('share.revoke')" size="small" text severity="danger" data-testid="share-revoke" @click="revoke(data.uuid)" />
                </template>
              </Column>
            </DataTable>
          </div>
        </TabPanel>
        <TabPanel value="signatures">
          <div class="flex flex-col gap-4">
            <p class="m-0 text-sm text-muted-color">{{ t('share.signatures_hint') }}</p>
            <p v-if="sharing && !sharing.templates.length" class="m-0 text-sm text-muted-color">{{ t('share.no_templates') }}</p>
            <div v-else class="grid gap-3 sm:grid-cols-2">
              <div class="flex flex-col gap-1">
                <label for="share-template" class="text-sm">{{ t('share.document') }}</label>
                <Select v-model="signature.template" input-id="share-template" :options="sharing?.templates ?? []" option-label="name" option-value="uuid" size="small" data-testid="share-template" />
              </div>
              <div class="flex flex-col gap-1">
                <label for="share-signer" class="text-sm">{{ t('share.signer') }}</label>
                <InputText id="share-signer" v-model="signature.signer" type="email" class="ltr-value" size="small" :invalid="!!errors.signer" data-testid="share-signer" />
                <small v-if="errors.signer" class="text-danger">{{ errors.signer }}</small>
              </div>
              <div class="flex flex-col gap-1">
                <label for="share-sign-days" class="text-sm">{{ t('share.days') }}</label>
                <InputNumber v-model="signature.days" input-id="share-sign-days" :min="1" :max="60" size="small" class="w-24" />
              </div>
              <div class="flex items-end">
                <Button icon="pi pi-pencil" :label="t('share.request_signature')" size="small" :loading="busy" data-testid="share-request-signature" @click="requestSignature" />
              </div>
            </div>
            <DataTable :value="sharing?.signatures ?? []" data-key="uuid" size="small" class="records-table" data-testid="share-signatures">
              <template #empty>{{ t('share.no_signatures') }}</template>
              <Column :header="t('share.document')">
                <template #body="{ data }"
                  ><span v-if="data.template" dir="auto">{{ data.template }}</span
                  ><EmptyValue v-else
                /></template>
              </Column>
              <Column :header="t('share.signer')">
                <template #body="{ data }"
                  ><span class="ltr-value">{{ data.signer }}</span></template
                >
              </Column>
              <Column :header="t('share.state')">
                <template #body="{ data }">
                  <div class="flex flex-col gap-1">
                    <Tag :severity="signSeverity(data.status)" :value="t(`share.sign_state.${data.status}`)" />
                    <small v-if="data.decline_reason" class="text-muted-color" dir="auto">{{ data.decline_reason }}</small>
                  </div>
                </template>
              </Column>
              <Column class="w-0 whitespace-nowrap">
                <template #body="{ data }">
                  <a v-if="data.has_signed_file" :href="sharingApi.signedUrl(data.uuid)" class="text-link text-sm" data-testid="share-signed-file">{{ t('share.signed_pdf') }}</a>
                  <Button
                    v-else-if="data.can_cancel && ['pending', 'viewed'].includes(data.status)"
                    :label="t('share.cancel_request')"
                    size="small"
                    text
                    severity="danger"
                    @click="cancel(data.uuid)"
                  />
                </template>
              </Column>
            </DataTable>
          </div>
        </TabPanel>
      </TabPanels>
    </Tabs>
  </Dialog>
</template>
