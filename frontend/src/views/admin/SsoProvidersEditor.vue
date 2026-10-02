<script setup lang="ts">
import Button from 'primevue/button'
import InputText from 'primevue/inputtext'
import Password from 'primevue/password'
import Textarea from 'primevue/textarea'
import ToggleSwitch from 'primevue/toggleswitch'
import { useI18n } from 'vue-i18n'
import { useSession } from '@/stores/session'

export interface Provider {
  key: string
  name: Record<string, string>
  issuer: string
  client_id: string
  scopes: string[]
  role_claim: string | null
  role_map: Record<string, string>
  jit_provisioning: boolean
  link_by_email: boolean
  enabled: boolean
}

const providers = defineModel<Provider[]>({ required: true })
const secrets = defineModel<Record<string, string>>('secrets', { required: true })
defineProps<{ errors: Record<string, string> }>()
const { t } = useI18n()
const session = useSession()

function add(): void {
  providers.value.push({ key: '', name: {}, issuer: 'https://', client_id: '', scopes: [], role_claim: null, role_map: {}, jit_provisioning: false, link_by_email: false, enabled: true })
}
function roleMapText(p: Provider): string {
  return Object.entries(p.role_map ?? {}).map(([a, b]) => `${a} = ${b}`).join('\n')
}
function setRoleMap(p: Provider, text: string): void {
  p.role_map = Object.fromEntries(
    text.split(/\r?\n/).map((l) => [l.slice(0, l.lastIndexOf('=')).trim(), l.slice(l.lastIndexOf('=') + 1).trim()]).filter(([a, b]) => a && b),
  )
}
</script>

<template>
  <p class="text-muted-color mb-3">{{ t('sso.hint') }}</p>
  <div v-for="(p, i) in providers" :key="i" class="rounded-lg border border-surface-200 dark:border-surface-700 p-4 mb-4">
    <div class="form-grid">
      <div class="field"><label :for="`sk-${i}`">{{ t('sso.key') }}</label><InputText :id="`sk-${i}`" v-model="p.key" class="ltr-value" /><span v-if="errors[`providers.${i}.key`]" class="field-error">{{ errors[`providers.${i}.key`] }}</span></div>
      <div v-for="l in session.boot?.locales ?? []" :key="l.code" class="field"><label>{{ t('sso.display_name') }} ({{ l.native_name }})</label><InputText v-model="p.name[l.code]" :dir="l.direction" /></div>
      <div class="field"><label :for="`si-${i}`">{{ t('sso.issuer') }}</label><InputText :id="`si-${i}`" v-model="p.issuer" class="ltr-value" /><span v-if="errors[`providers.${i}.issuer`]" class="field-error">{{ errors[`providers.${i}.issuer`] }}</span></div>
      <div class="field"><label :for="`sc-${i}`">{{ t('sso.client_id') }}</label><InputText :id="`sc-${i}`" v-model="p.client_id" class="ltr-value" /></div>
      <div class="field"><label :for="`ss-${i}`">{{ t('sso.client_secret') }}</label><Password v-model="secrets[p.key]" :input-id="`ss-${i}`" :feedback="false" toggle-mask autocomplete="new-password" fluid :placeholder="t('settings.secret_hint')" /></div>
      <div class="field"><label :for="`sr-${i}`">{{ t('sso.role_claim') }}</label><InputText :id="`sr-${i}`" v-model="p.role_claim as string" class="ltr-value" /></div>
      <div class="field col-span-full"><label :for="`sm-${i}`">{{ t('sso.role_map') }}</label><Textarea :id="`sm-${i}`" :model-value="roleMapText(p)" rows="3" class="ltr-value" :placeholder="t('settings.map_hint')" @change="(e: Event) => setRoleMap(p, (e.target as HTMLTextAreaElement).value)" /></div>
      <label class="flex items-center gap-2"><ToggleSwitch v-model="p.enabled" />{{ t('common.enabled') }}</label>
      <label class="flex items-center gap-2"><ToggleSwitch v-model="p.jit_provisioning" />{{ t('sso.jit') }}</label>
      <label class="flex items-center gap-2"><ToggleSwitch v-model="p.link_by_email" />{{ t('sso.link_by_email') }}</label>
    </div>
    <p class="text-xs text-muted-color mt-2 ltr-value">{{ t('sso.callback') }}: {{ `${location.origin}/auth/sso/${p.key || '…'}/callback` }}</p>
    <div class="flex justify-end"><Button severity="danger" text icon="pi pi-trash" :label="t('common.remove')" @click="providers.splice(i, 1)" /></div>
  </div>
  <Button severity="secondary" icon="pi pi-plus" :label="t('sso.add')" @click="add" />
</template>

<script lang="ts">
const location = window.location
</script>
