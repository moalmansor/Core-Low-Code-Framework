<script setup lang="ts">
import Button from 'primevue/button'
import Column from 'primevue/column'
import DataTable, { type DataTablePageEvent } from 'primevue/datatable'
import Dialog from 'primevue/dialog'
import IconField from 'primevue/iconfield'
import InputIcon from 'primevue/inputicon'
import InputText from 'primevue/inputtext'
import Menu from 'primevue/menu'
import MultiSelect from 'primevue/multiselect'
import Select from 'primevue/select'
import Tag from 'primevue/tag'
import TreeSelect from 'primevue/treeselect'
import { useConfirm } from 'primevue/useconfirm'
import { useToast } from 'primevue/usetoast'
import { computed, onMounted, reactive, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { ApiError, get, send } from '@/api/http'
import { withStepUp } from '@/api/stepup'
import StepUpDialog from '@/components/StepUpDialog.vue'
import { departmentNodes, type DepartmentNode } from '@/views/admin/departments'

interface UserRow {
  uuid: string
  name: string
  email: string
  username: string | null
  job_title: string | null
  phone: string | null
  status: string
  auth_source: string
  locked: boolean
  two_factor_enabled: boolean
  last_login_at: string | null
  department: { uuid: string; name: string } | null
  manager: { uuid: string; name: string } | null
  roles: { uuid: string; key: string; name: string }[]
}

const { t } = useI18n()
const toast = useToast()
const confirm = useConfirm()
const stepUp = ref<InstanceType<typeof StepUpDialog> | null>(null)

const rows = ref<UserRow[]>([])
const total = ref(0)
const loading = ref(false)
const filters = reactive({ search: '', status: null as string | null, page: 1, per_page: 25 })
const roles = ref<{ uuid: string; name: string }[]>([])
const tree = ref<DepartmentNode[]>([])
const editing = ref<Partial<UserRow> & { roleUuids?: string[]; departmentKey?: Record<string, boolean> | null } | null>(null)
const errors = ref<Record<string, string>>({})
const actionsMenu = ref<InstanceType<typeof Menu> | null>(null)
const actionsFor = ref<UserRow | null>(null)
const statuses = computed(() => ['active', 'suspended', 'disabled', 'pending'].map((v) => ({ v, l: t(`users.status.${v}`) })))

async function load(): Promise<void> {
  loading.value = true
  try {
    const res = await get<{ data: UserRow[]; total: number }>('/users', { ...filters, search: filters.search || undefined, status: filters.status || undefined })
    rows.value = res.data
    total.value = res.total
  } finally {
    loading.value = false
  }
}

onMounted(async () => {
  await load()
  roles.value = (await get<{ data: { uuid: string; name: string }[] }>('/role-options')).data
  tree.value = departmentNodes((await get<{ data: never[] }>('/departments/tree')).data)
})

function onPage(e: DataTablePageEvent): void {
  filters.page = e.page + 1
  filters.per_page = e.rows
  load()
}

function openCreate(): void {
  errors.value = {}
  editing.value = { name: '', email: '', username: '', job_title: '', phone: '', roleUuids: [], departmentKey: null }
}

function openEdit(u: UserRow): void {
  errors.value = {}
  editing.value = { ...u, roleUuids: u.roles.map((r) => r.uuid), departmentKey: u.department ? { [u.department.uuid]: true } : null }
}

async function save(): Promise<void> {
  const e = editing.value!
  const body = {
    name: e.name,
    email: e.email,
    username: e.username || null,
    job_title: e.job_title || null,
    phone: e.phone || null,
    department: e.departmentKey ? Object.keys(e.departmentKey)[0] : null,
    roles: e.roleUuids,
  }
  errors.value = {}
  try {
    const done = await withStepUp(stepUp, (code) =>
      e.uuid ? send('patch', `/users/${e.uuid}`, { ...body, confirmation_code: code }) : send('post', '/users', { ...body, confirmation_code: code }),
    )
    if (done === null) return
    editing.value = null
    toast.add({ severity: 'success', summary: e.uuid ? t('common.saved') : t('users.created'), life: 4000 })
    await load()
  } catch (err) {
    if (err instanceof ApiError) {
      errors.value = err.fieldErrors
      if (err.status === 403) toast.add({ severity: 'error', summary: err.message, life: 6000 })
    }
  }
}

async function act(fn: () => Promise<unknown>, message: string): Promise<void> {
  try {
    await fn()
    toast.add({ severity: 'success', summary: message, life: 4000 })
    await load()
  } catch (err) {
    if (err instanceof ApiError && err.status < 500) toast.add({ severity: 'error', summary: err.message, life: 6000 })
  }
}

const menuItems = computed(() => {
  const u = actionsFor.value
  if (!u) return []
  return [
    { label: t('common.edit'), icon: 'pi pi-pencil', command: () => openEdit(u) },
    u.status === 'active'
      ? { label: t('users.suspend'), icon: 'pi pi-pause', command: () => act(() => send('post', `/users/${u.uuid}/status`, { status: 'suspended' }), t('users.suspended')) }
      : { label: t('users.activate'), icon: 'pi pi-play', command: () => act(() => send('post', `/users/${u.uuid}/status`, { status: 'active' }), t('users.activated')) },
    { label: t('users.disable'), icon: 'pi pi-ban', visible: u.status !== 'disabled', command: () => act(() => send('post', `/users/${u.uuid}/status`, { status: 'disabled' }), t('users.disabled_msg')) },
    { label: t('users.unlock'), icon: 'pi pi-lock-open', visible: u.locked, command: () => act(() => send('post', `/users/${u.uuid}/unlock`), t('users.unlocked')) },
    { label: t('users.send_password_link'), icon: 'pi pi-envelope', visible: u.auth_source === 'local', command: () => act(() => send('post', `/users/${u.uuid}/password-link`), t('users.link_sent')) },
    { label: t('users.reset_2fa'), icon: 'pi pi-shield', visible: u.two_factor_enabled, command: () => act(() => send('post', `/users/${u.uuid}/reset-2fa`), t('users.two_factor_reset')) },
    { label: t('users.revoke_sessions'), icon: 'pi pi-sign-out', command: () => act(() => send('delete', `/users/${u.uuid}/sessions`), t('users.sessions_revoked')) },
    { separator: true },
    {
      label: t('common.delete'),
      icon: 'pi pi-trash',
      class: 'text-red-600',
      command: () =>
        confirm.require({
          message: t('users.delete_confirm', { name: u.name }),
          header: t('common.confirm'),
          acceptProps: { label: t('common.delete'), severity: 'danger' },
          rejectProps: { label: t('common.cancel'), severity: 'secondary' },
          accept: () => act(() => send('delete', `/users/${u.uuid}`), t('users.deleted')),
        }),
    },
  ]
})

function openActions(event: Event, u: UserRow): void {
  actionsFor.value = u
  actionsMenu.value?.toggle(event)
}

const statusSeverity = (s: string) => ({ active: 'success', suspended: 'warn', disabled: 'danger', pending: 'info' })[s] ?? 'secondary'
</script>

<template>
  <div class="flex flex-wrap items-center gap-3 mb-4">
    <h1 class="page-title !mb-0 flex-1">{{ t('admin.area.users') }}</h1>
    <Button icon="pi pi-plus" :label="t('users.new')" data-testid="new-user" @click="openCreate" />
  </div>
  <div class="flex flex-wrap gap-2 mb-3">
    <IconField>
      <InputIcon class="pi pi-search" />
      <InputText v-model="filters.search" :placeholder="t('common.search')" @keyup.enter="load" />
    </IconField>
    <Select v-model="filters.status" :options="statuses" option-label="l" option-value="v" :placeholder="t('users.all_statuses')" show-clear @change="load" />
  </div>
  <DataTable :value="rows" lazy paginator :rows="filters.per_page" :total-records="total" :loading="loading" data-key="uuid" size="small" striped-rows @page="onPage">
    <template #empty>{{ t('common.no_results') }}</template>
    <Column :header="t('users.name')">
      <template #body="{ data }">
        <div class="font-medium">{{ data.name }}</div>
        <div class="text-sm text-muted-color ltr-value">{{ data.email }}</div>
      </template>
    </Column>
    <Column :header="t('users.department')"><template #body="{ data }">{{ data.department?.name ?? '—' }}</template></Column>
    <Column :header="t('users.roles')"><template #body="{ data }"><Tag v-for="r in data.roles" :key="r.uuid" :value="r.name" class="me-1" severity="secondary" /></template></Column>
    <Column :header="t('users.status_label')">
      <template #body="{ data }">
        <Tag :severity="statusSeverity(data.status)" :value="t(`users.status.${data.status}`)" />
        <i v-if="data.locked" v-tooltip="t('users.locked')" class="pi pi-lock ms-2 text-orange-500" />
        <i v-if="data.two_factor_enabled" v-tooltip="t('users.two_factor_on')" class="pi pi-shield ms-2 text-green-600" />
      </template>
    </Column>
    <Column style="width: 4rem">
      <template #body="{ data }"><Button icon="pi pi-ellipsis-v" text rounded :aria-label="t('common.actions')" @click="(e: Event) => openActions(e, data)" /></template>
    </Column>
  </DataTable>
  <Menu ref="actionsMenu" :model="menuItems" popup />

  <Dialog :visible="!!editing" modal :header="editing?.uuid ? t('users.edit') : t('users.new')" :style="{ width: '40rem' }" @update:visible="(v: boolean) => !v && (editing = null)">
    <form v-if="editing" class="form-grid" @submit.prevent="save">
      <div class="field"><label for="un">{{ t('users.name') }}</label><InputText id="un" v-model="editing.name" data-testid="user-name" /><span v-if="errors.name" class="field-error">{{ errors.name }}</span></div>
      <div class="field"><label for="ue">{{ t('users.email') }}</label><InputText id="ue" v-model="editing.email" type="email" class="ltr-value" data-testid="user-email" /><span v-if="errors.email" class="field-error">{{ errors.email }}</span></div>
      <div class="field"><label for="uu">{{ t('users.username') }}</label><InputText id="uu" v-model="editing.username as string" class="ltr-value" /><span v-if="errors.username" class="field-error">{{ errors.username }}</span></div>
      <div class="field"><label for="uj">{{ t('users.job_title') }}</label><InputText id="uj" v-model="editing.job_title as string" /></div>
      <div class="field"><label for="up">{{ t('users.phone') }}</label><InputText id="up" v-model="editing.phone as string" class="ltr-value" /><span v-if="errors.phone" class="field-error">{{ errors.phone }}</span></div>
      <div class="field"><label for="ud">{{ t('users.department') }}</label><TreeSelect v-model="editing.departmentKey" input-id="ud" :options="tree" selection-mode="single" show-clear :placeholder="t('common.none')" /></div>
      <div class="field col-span-full"><label for="ur">{{ t('users.roles') }}</label><MultiSelect v-model="editing.roleUuids" input-id="ur" :options="roles" option-label="name" option-value="uuid" display="chip" data-testid="user-roles" /><span v-if="errors.roles" class="field-error">{{ errors.roles }}</span></div>
      <p v-if="!editing.uuid" class="col-span-full text-sm text-muted-color">{{ t('users.invite_hint') }}</p>
      <div class="col-span-full flex justify-end gap-2">
        <Button type="button" severity="secondary" :label="t('common.cancel')" @click="editing = null" />
        <Button type="submit" :label="t('common.save')" data-testid="user-save" />
      </div>
    </form>
  </Dialog>
  <StepUpDialog ref="stepUp" />
</template>
