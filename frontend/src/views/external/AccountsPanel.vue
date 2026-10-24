<script setup lang="ts">
import Button from 'primevue/button'
import Column from 'primevue/column'
import DataTable from 'primevue/datatable'
import Dialog from 'primevue/dialog'
import InputText from 'primevue/inputtext'
import Paginator from 'primevue/paginator'
import Select from 'primevue/select'
import Tag from 'primevue/tag'
import { useConfirm } from 'primevue/useconfirm'
import { useToast } from 'primevue/usetoast'
import { computed, onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import I18nInput from '@/builder/I18nInput.vue'
import ConfigField from '@/components/config/ConfigField.vue'
import ConfigSection from '@/components/config/ConfigSection.vue'
import SettingSwitch from '@/components/config/SettingSwitch.vue'
import EmptyValue from '@/components/EmptyValue.vue'
import { formatDatetime } from '@/runtime/format'
import { errorText, fieldErrors } from '../admin/building/shared'
import { accountsApi, type AccountStatus, type ExternalAccount, type ExternalRole, type I18nMap, type PortalSettings } from './api'

/**
 * The external portal (specification §4.32, ADR-0058): whether people may
 * register and which external role they get, the external roles, and the
 * accounts — approve or reject a registration, suspend or reactivate an
 * account, change its role, or delete it (it is anonymized; its records
 * stay).
 */
type Decision = 'approve' | 'reject' | 'suspend' | 'reactivate'
const { t, locale } = useI18n()
const toast = useToast()
const confirm = useConfirm()
const settings = ref<PortalSettings>({ registration_open: false, registration_role: null, registration_requires_approval: true })
const savedSettings = ref('')
const settingsErrors = ref<Record<string, string>>({})
const roles = ref<ExternalRole[]>([])
const accounts = ref<ExternalAccount[]>([])
const total = ref(0)
const page = ref(1)
const status = ref<AccountStatus | null>('pending_approval')
const search = ref('')
const loading = ref(true)
const roleDialog = ref(false)
const roleDraft = ref<{ uuid: string | null; key: string; name: I18nMap }>({ uuid: null, key: '', name: {} })
const roleErrors = ref<Record<string, string>>({})

onMounted(async () => {
  try {
    const [s, r] = await Promise.all([accountsApi.settings(), accountsApi.roles()])
    settings.value = s
    savedSettings.value = JSON.stringify(s)
    roles.value = r
    await loadAccounts()
  } catch (e) {
    toast.add({ severity: 'error', summary: errorText(e, t('xa.load_failed')), life: 6000 })
  } finally {
    loading.value = false
  }
})
async function loadAccounts(): Promise<void> {
  const out = await accountsApi.list(status.value, search.value.trim(), page.value)
  accounts.value = out.data
  total.value = out.meta.total
}
const settingsDirty = computed(() => JSON.stringify(settings.value) !== savedSettings.value)
async function saveSettings(): Promise<void> {
  settingsErrors.value = {}
  try {
    settings.value = await accountsApi.saveSettings(settings.value)
    savedSettings.value = JSON.stringify(settings.value)
    toast.add({ severity: 'success', summary: t('xa.saved'), life: 3000 })
  } catch (e) {
    settingsErrors.value = fieldErrors(e)
    toast.add({ severity: 'error', summary: errorText(e, t('xa.save_failed')), life: 8000 })
  }
}

function newRole(): void {
  roleDraft.value = { uuid: null, key: '', name: {} }
  roleErrors.value = {}
  roleDialog.value = true
}
function editRole(r: ExternalRole): void {
  roleDraft.value = { uuid: r.uuid, key: r.key, name: Array.isArray(r.name) ? {} : { ...r.name } }
  roleErrors.value = {}
  roleDialog.value = true
}
async function saveRole(): Promise<void> {
  roleErrors.value = {}
  try {
    if (roleDraft.value.uuid) await accountsApi.renameRole(roleDraft.value.uuid, roleDraft.value.name)
    else await accountsApi.createRole(roleDraft.value.key.trim(), roleDraft.value.name)
    roles.value = await accountsApi.roles()
    roleDialog.value = false
  } catch (e) {
    roleErrors.value = fieldErrors(e)
    toast.add({ severity: 'error', summary: errorText(e, t('xa.save_failed')), life: 8000 })
  }
}
function removeRole(r: ExternalRole): void {
  confirm.require({
    header: t('xa.role_delete_title'),
    message: t('xa.role_delete_text', { name: r.label }),
    acceptProps: { label: t('xa.delete'), severity: 'danger' },
    rejectProps: { label: t('xa.cancel'), text: true },
    accept: async () => {
      try {
        await accountsApi.removeRole(r.uuid)
        roles.value = await accountsApi.roles()
      } catch (e) {
        toast.add({ severity: 'error', summary: errorText(e, t('xa.delete_failed')), life: 8000 })
      }
    },
  })
}

const statusOptions = computed(() => [
  { value: null as AccountStatus | null, label: t('xa.all') },
  ...(['pending_approval', 'approved', 'pending_verification', 'rejected', 'suspended'] as AccountStatus[]).map((v) => ({ value: v as AccountStatus | null, label: t(`xa.account_status.${v}`) })),
])
const severity = (s: AccountStatus) => (s === 'approved' ? 'success' : s === 'pending_approval' ? 'warn' : s === 'pending_verification' ? 'info' : 'secondary')
const decisions = (s: AccountStatus): Decision[] =>
  s === 'pending_approval' ? ['approve', 'reject'] : s === 'rejected' ? ['approve'] : s === 'approved' ? ['suspend'] : s === 'suspended' ? ['reactivate'] : []
async function decide(a: ExternalAccount, d: Decision): Promise<void> {
  try {
    await accountsApi.decide(a.uuid, d)
    toast.add({ severity: 'success', summary: t(`xa.decided.${d}`, { name: a.name }), life: 3000 })
    await loadAccounts()
  } catch (e) {
    toast.add({ severity: 'error', summary: errorText(e, t('xa.save_failed')), life: 8000 })
  }
}
async function setRole(a: ExternalAccount, role: string): Promise<void> {
  try {
    await accountsApi.setRole(a.uuid, role)
    await loadAccounts()
  } catch (e) {
    toast.add({ severity: 'error', summary: errorText(e, t('xa.save_failed')), life: 8000 })
  }
}
function removeAccount(a: ExternalAccount): void {
  confirm.require({
    header: t('xa.account_delete_title'),
    message: t('xa.account_delete_text', { name: a.name }),
    acceptProps: { label: t('xa.delete'), severity: 'danger' },
    rejectProps: { label: t('xa.cancel'), text: true },
    accept: async () => {
      try {
        await accountsApi.remove(a.uuid)
        await loadAccounts()
      } catch (e) {
        toast.add({ severity: 'error', summary: errorText(e, t('xa.delete_failed')), life: 8000 })
      }
    },
  })
}
function filter(): void {
  page.value = 1
  void loadAccounts()
}
</script>

<template>
  <div class="flex flex-col gap-6 max-w-[64rem]" data-testid="xa-accounts">
    <ConfigSection id="xa-registration" :title="t('xa.section.registration')" :description="t('xa.section.registration_hint')">
      <p v-if="settings.portal_url" class="m-0 text-sm">
        {{ t('xa.portal_url') }} <a :href="settings.portal_url" target="_blank" rel="noopener" class="text-link ltr-value">{{ settings.portal_url }}</a>
      </p>
      <SettingSwitch id="xa-registration-open" v-model="settings.registration_open" :label="t('xa.registration_open')" :description="t('xa.registration_open_desc')" />
      <ConfigField :label="t('xa.registration_role')" for="xa-registration-role" width="md" :hint="t('xa.registration_role_hint')" :error="settingsErrors.registration_role">
        <Select
          v-model="settings.registration_role"
          input-id="xa-registration-role"
          :options="roles"
          option-label="label"
          option-value="uuid"
          show-clear
          size="small"
          :empty-message="t('xa.no_roles')"
          data-testid="xa-registration-role"
        />
      </ConfigField>
      <SettingSwitch id="xa-registration-approval" v-model="settings.registration_requires_approval" :label="t('xa.requires_approval')" :description="t('xa.requires_approval_desc')" />
      <div>
        <Button icon="pi pi-check" :label="t('xa.save')" size="small" :disabled="!settingsDirty" data-testid="xa-settings-save" @click="saveSettings" />
      </div>
    </ConfigSection>

    <ConfigSection id="xa-roles" :title="t('xa.section.roles')" :description="t('xa.section.roles_hint')" :count="roles.length">
      <DataTable :value="roles" data-key="uuid" size="small" class="records-table" data-testid="xa-roles-table">
        <template #empty>{{ t('xa.no_roles') }}</template>
        <Column :header="t('xa.role')">
          <template #body="{ data }"
            ><span dir="auto">{{ data.label }}</span></template
          >
        </Column>
        <Column :header="t('xa.accounts_count')">
          <template #body="{ data }">{{ data.accounts }}</template>
        </Column>
        <Column :header="t('xa.actions')" class="w-0 whitespace-nowrap">
          <template #body="{ data }">
            <Button icon="pi pi-pencil" text size="small" :aria-label="t('xa.rename')" @click="editRole(data)" />
            <Button icon="pi pi-trash" text size="small" severity="danger" :aria-label="t('xa.delete')" @click="removeRole(data)" />
          </template>
        </Column>
      </DataTable>
      <div><Button icon="pi pi-plus" :label="t('xa.new_role')" size="small" outlined data-testid="xa-new-role" @click="newRole" /></div>
    </ConfigSection>

    <ConfigSection id="xa-account-list" :title="t('xa.section.accounts')" :description="t('xa.section.accounts_hint')" :count="total">
      <div class="flex flex-wrap items-end gap-2">
        <Select
          v-model="status"
          :options="statusOptions"
          option-label="label"
          option-value="value"
          size="small"
          :aria-label="t('xa.filter_status')"
          data-testid="xa-account-status"
          @update:model-value="filter"
        />
        <InputText v-model="search" size="small" :placeholder="t('xa.search_accounts')" :aria-label="t('xa.search_accounts')" @keyup.enter="filter" />
        <Button icon="pi pi-search" size="small" text :aria-label="t('xa.search_accounts')" @click="filter" />
      </div>
      <DataTable :value="accounts" data-key="uuid" :loading="loading" size="small" class="records-table" data-testid="xa-accounts-table">
        <template #empty>{{ t('xa.no_accounts') }}</template>
        <Column :header="t('xa.person')">
          <template #body="{ data }">
            <div class="flex flex-col">
              <span dir="auto">{{ data.name }}</span>
              <span class="text-sm text-muted-color ltr-value">{{ data.email }}</span>
            </div>
          </template>
        </Column>
        <Column :header="t('xa.role')">
          <template #body="{ data }">
            <Select :model-value="data.role" :options="roles" option-label="label" option-value="uuid" size="small" :aria-label="t('xa.role')" @update:model-value="(v: string) => setRole(data, v)" />
          </template>
        </Column>
        <Column :header="t('xa.state')">
          <template #body="{ data }"><Tag :severity="severity(data.status)" :value="t(`xa.account_status.${data.status}`)" :data-testid="`xa-account-state-${data.email}`" /></template>
        </Column>
        <Column :header="t('xa.registered')">
          <template #body="{ data }"
            ><span v-if="data.created_at">{{ formatDatetime(data.created_at, locale) }}</span
            ><EmptyValue v-else
          /></template>
        </Column>
        <Column :header="t('xa.actions')" class="w-0 whitespace-nowrap">
          <template #body="{ data }">
            <div class="flex flex-wrap gap-1">
              <Button
                v-for="d in decisions(data.status)"
                :key="d"
                :label="t(`xa.decision.${d}`)"
                size="small"
                :severity="d === 'approve' || d === 'reactivate' ? undefined : 'secondary'"
                outlined
                :data-testid="`xa-${d}-${data.email}`"
                @click="decide(data, d)"
              />
              <Button icon="pi pi-trash" text size="small" severity="danger" :aria-label="t('xa.delete')" @click="removeAccount(data)" />
            </div>
          </template>
        </Column>
      </DataTable>
      <Paginator
        v-if="total > 25"
        :rows="25"
        :total-records="total"
        :first="(page - 1) * 25"
        @page="
          (e) => {
            page = e.page + 1
            void loadAccounts()
          }
        "
      />
    </ConfigSection>

    <Dialog v-model:visible="roleDialog" modal :header="roleDraft.uuid ? t('xa.rename_role') : t('xa.new_role')" class="w-full max-w-lg" data-testid="xa-role-dialog">
      <div class="flex flex-col gap-4">
        <ConfigField v-if="!roleDraft.uuid" :label="t('xa.role_key')" for="xa-role-key" width="md" :hint="t('xa.role_key_hint')" :error="roleErrors.key">
          <InputText id="xa-role-key" v-model="roleDraft.key" class="ltr-value" size="small" maxlength="48" data-testid="xa-role-key" />
        </ConfigField>
        <I18nInput v-model="roleDraft.name" :label="t('xa.role_name')" :maxlength="255" :invalid="!!roleErrors.name" />
        <small v-if="roleErrors.name" class="text-danger">{{ roleErrors.name }}</small>
      </div>
      <template #footer>
        <Button :label="t('xa.cancel')" text severity="secondary" size="small" @click="roleDialog = false" />
        <Button icon="pi pi-check" :label="t('xa.save')" size="small" data-testid="xa-role-save" @click="saveRole" />
      </template>
    </Dialog>
  </div>
</template>
