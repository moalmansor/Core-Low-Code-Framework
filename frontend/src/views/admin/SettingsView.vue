<script setup lang="ts">
import Button from 'primevue/button'
import InputText from 'primevue/inputtext'
import Message from 'primevue/message'
import Tab from 'primevue/tab'
import TabList from 'primevue/tablist'
import TabPanel from 'primevue/tabpanel'
import TabPanels from 'primevue/tabpanels'
import Tabs from 'primevue/tabs'
import { useToast } from 'primevue/usetoast'
import { computed, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRoute, useRouter } from 'vue-router'
import { ApiError, ensureCsrf, get, http, send } from '@/api/http'
import SettingsForm from '@/components/SettingsForm.vue'
import { useSession } from '@/stores/session'
import EgressAllowlist from './EgressAllowlist.vue'
import LocalesManager from './LocalesManager.vue'
import { settingsSchema } from './settingsSchema'
import SsoProvidersEditor, { type Provider } from './SsoProvidersEditor.vue'

const { t } = useI18n()
const toast = useToast()
const route = useRoute()
const router = useRouter()
const session = useSession()

const tabs = computed(() => {
  const list: string[] = []
  if (session.can('system.manage_branding')) list.push('branding')
  if (session.can('system.manage_settings')) list.push('formats', 'calendar', 'mail', 'files', 'records', 'schema', 'clamav', 'security', 'sso', 'ldap', 'monitoring', 'egress')
  if (session.can('system.manage_translations')) list.push('locales')
  return list
})
const tab = ref(String(route.params.group || tabs.value[0] || 'branding'))
watch(tab, (g) => router.replace({ name: 'admin.settings', params: { group: g } }))

const values = ref<Record<string, unknown>>({})
const errors = ref<Record<string, string>>({})
const loading = ref(false)
const testTo = ref('')
const ssoProviders = ref<Provider[]>([])
const ssoSecrets = ref<Record<string, string>>({})

async function load(group: string): Promise<void> {
  errors.value = {}
  if (['egress', 'locales'].includes(group)) return
  loading.value = true
  try {
    const data = (await get<{ data: Record<string, unknown> }>(`/settings/${group}`)).data
    // Secrets come back as {is_set}; the form edits a blank value instead.
    for (const [k, v] of Object.entries(data)) {
      if (v && typeof v === 'object' && 'is_set' in (v as object)) {
        data[`__${k}_set`] = (v as { is_set: boolean }).is_set
        data[k] = k === 'client_secrets' ? {} : ''
      }
    }
    values.value = data
    if (group === 'sso') {
      ssoProviders.value = (data.providers as Provider[]) ?? []
      ssoSecrets.value = {}
    }
  } finally {
    loading.value = false
  }
}
watch(tab, load, { immediate: true })

async function save(): Promise<void> {
  errors.value = {}
  let body: Record<string, unknown>
  if (tab.value === 'sso') {
    body = { providers: ssoProviders.value, ...(Object.values(ssoSecrets.value).some(Boolean) ? { client_secrets: ssoSecrets.value } : {}) }
  } else if (tab.value === 'branding') {
    body = { system_name: values.value.system_name }
  } else {
    body = Object.fromEntries(Object.entries(values.value).filter(([k]) => !k.startsWith('__') && settingsSchema[tab.value]?.some((f) => f.key === k)))
  }
  try {
    await send('patch', `/settings/${tab.value}`, body)
    toast.add({ severity: 'success', summary: t('common.saved'), life: 3000 })
    await load(tab.value)
    if (['branding', 'formats', 'calendar'].includes(tab.value)) await session.loadBootstrap()
  } catch (e) {
    if (e instanceof ApiError) {
      errors.value = e.fieldErrors
      toast.add({ severity: 'error', summary: Object.values(e.fieldErrors)[0] ?? e.message, life: 6000 })
    }
  }
}

async function upload(kind: 'logo' | 'favicon', event: Event): Promise<void> {
  const file = (event.target as HTMLInputElement).files?.[0]
  if (!file) return
  const body = new FormData()
  body.append('file', file)
  try {
    await ensureCsrf()
    await http.post(`/settings/branding/${kind}`, body)
    await session.loadBootstrap()
    toast.add({ severity: 'success', summary: t('setup.uploaded'), life: 3000 })
  } catch (e) {
    if (e instanceof ApiError) toast.add({ severity: 'error', summary: Object.values(e.fieldErrors)[0] ?? e.message, life: 6000 })
  }
}

async function sendTest(): Promise<void> {
  try {
    await send('post', '/settings/mail/test', { to: testTo.value })
    toast.add({ severity: 'success', summary: t('setup.mail_test_ok'), life: 4000 })
  } catch (e) {
    if (e instanceof ApiError) toast.add({ severity: 'error', summary: e.message, detail: (e.body as { detail?: string }).detail, life: 10000 })
  }
}
</script>

<template>
  <h1 class="page-title">{{ t('admin.area.system_settings') }}</h1>
  <Tabs v-model:value="tab" scrollable>
    <TabList>
      <Tab v-for="g in tabs" :key="g" :value="g" :data-testid="`settings-tab-${g}`">{{ t(`settings.group.${g}`) }}</Tab>
    </TabList>
    <TabPanels>
      <TabPanel v-for="g in tabs" :key="g" :value="g">
        <template v-if="g === tab">
          <EgressAllowlist v-if="g === 'egress'" />
          <LocalesManager v-else-if="g === 'locales'" />
          <form v-else-if="!loading" class="flex flex-col gap-4 max-w-4xl" @submit.prevent="save">
            <template v-if="g === 'branding'">
              <div class="form-grid">
                <div v-for="l in session.boot?.locales ?? []" :key="l.code" class="field">
                  <label :for="`sn-${l.code}`">{{ t('setup.system_name') }} ({{ l.native_name }})</label>
                  <InputText :id="`sn-${l.code}`" v-model="(values.system_name as Record<string, string>)[l.code]" :dir="l.direction" />
                </div>
              </div>
              <div class="form-grid">
                <div class="field">
                  <label for="bl">{{ t('setup.logo') }}</label
                  ><input id="bl" type="file" accept=".png,.jpg,.jpeg,.webp" @change="(e) => upload('logo', e)" />
                </div>
                <div class="field">
                  <label for="bf">{{ t('setup.favicon') }}</label
                  ><input id="bf" type="file" accept=".png,.ico" @change="(e) => upload('favicon', e)" />
                </div>
              </div>
            </template>
            <SsoProvidersEditor v-else-if="g === 'sso'" v-model="ssoProviders" v-model:secrets="ssoSecrets" :errors="errors" />
            <SettingsForm v-else v-model="values" :group="g" :fields="settingsSchema[g] ?? []" :errors="errors" />
            <Message v-if="g === 'security'" severity="secondary" size="small">{{ t('settings.security_hint') }}</Message>
            <div class="flex justify-end"><Button type="submit" :label="t('common.save')" icon="pi pi-check" :data-testid="`settings-save-${g}`" /></div>
          </form>
          <div v-if="g === 'mail'" class="flex flex-wrap items-end gap-2 mt-6 max-w-4xl">
            <div class="field grow">
              <label for="tt">{{ t('settings.mail.test_to') }}</label
              ><InputText id="tt" v-model="testTo" type="email" class="ltr-value" />
            </div>
            <Button severity="secondary" icon="pi pi-send" :label="t('settings.mail.send_test')" :disabled="!testTo" @click="sendTest" />
          </div>
        </template>
      </TabPanel>
    </TabPanels>
  </Tabs>
</template>
