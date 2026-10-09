<script setup lang="ts">
import Button from 'primevue/button'
import Dialog from 'primevue/dialog'
import InputText from 'primevue/inputtext'
import Listbox from 'primevue/listbox'
import Message from 'primevue/message'
import Select from 'primevue/select'
import SelectButton from 'primevue/selectbutton'
import Tab from 'primevue/tab'
import TabList from 'primevue/tablist'
import TabPanel from 'primevue/tabpanel'
import TabPanels from 'primevue/tabpanels'
import Tabs from 'primevue/tabs'
import Tag from 'primevue/tag'
import ToggleSwitch from 'primevue/toggleswitch'
import TreeSelect from 'primevue/treeselect'
import { useConfirm } from 'primevue/useconfirm'
import { useToast } from 'primevue/usetoast'
import { computed, onMounted, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { ApiError, get, send } from '@/api/http'
import { withStepUp } from '@/api/stepup'
import StepUpDialog from '@/components/StepUpDialog.vue'
import UserPicker from '@/components/UserPicker.vue'
import { useSession } from '@/stores/session'
import { departmentNodes, type DepartmentApi, type DepartmentNode } from './departments'

interface Role {
  uuid: string
  key: string
  name: string
  names: Record<string, string>
  descriptions: Record<string, string>
  is_system: boolean
  requires_2fa: boolean
  is_admin_role: boolean
  users_count: number | null
}
interface Permission {
  key: string
  category: string
  is_dangerous: boolean
  label: string
}
interface Grant {
  permission: string
  effect: Effect
  include_descendants: boolean
}
type Effect = 'allow' | 'deny' | 'hard_deny' | null
type SubjectType = 'role' | 'user' | 'department'

const { t } = useI18n()
const toast = useToast()
const confirm = useConfirm()
const session = useSession()
const stepUp = ref<InstanceType<typeof StepUpDialog> | null>(null)

const roles = ref<Role[]>([])
const catalog = ref<Permission[]>([])
const departments = ref<DepartmentNode[]>([])
const subjectType = ref<SubjectType>('role')
const selectedRole = ref<Role | null>(null)
const selectedUser = ref<{ uuid: string; name: string; email: string } | null>(null)
const selectedDept = ref<Record<string, boolean> | null>(null)
const original = ref<Record<string, Grant>>({})
const draft = ref<Record<string, Grant>>({})
const tab = ref('grants')
const effectOptions = computed(() => [
  { value: null, label: t('access.effect.inherit') },
  { value: 'allow', label: t('access.effect.allow') },
  { value: 'deny', label: t('access.effect.deny') },
  { value: 'hard_deny', label: t('access.effect.hard_deny') },
])
const subjectOptions = computed(() => (['role', 'user', 'department'] as const).map((v) => ({ value: v, label: t(`access.subject.${v}`) })))

const subjectUuid = computed(() => {
  if (subjectType.value === 'role') return selectedRole.value?.uuid
  if (subjectType.value === 'user') return selectedUser.value?.uuid
  return selectedDept.value ? Object.keys(selectedDept.value)[0] : undefined
})

const grouped = computed(() => {
  const g = new Map<string, Permission[]>()
  for (const p of catalog.value) g.set(p.category, [...(g.get(p.category) ?? []), p])
  return [...g.entries()]
})
const changes = computed(() =>
  catalog.value
    .map((p) => p.key)
    .filter((k) => (draft.value[k]?.effect ?? null) !== (original.value[k]?.effect ?? null) || (draft.value[k]?.include_descendants ?? false) !== (original.value[k]?.include_descendants ?? false)),
)

async function loadRoles(): Promise<void> {
  roles.value = (await get<{ data: Role[] }>('/roles')).data
  if (!selectedRole.value && roles.value.length) selectedRole.value = roles.value[0]!
}

onMounted(async () => {
  await loadRoles()
  catalog.value = (await get<{ data: Permission[] }>('/permissions')).data
  if (session.can('system.manage_users')) departments.value = departmentNodes((await get<{ data: DepartmentApi[] }>('/departments/tree')).data)
})

async function loadGrants(): Promise<void> {
  original.value = {}
  draft.value = {}
  if (!subjectUuid.value) return
  const list = (await get<{ data: Grant[] }>('/permission-assignments', { subject_type: subjectType.value, subject: subjectUuid.value })).data
  for (const g of list) original.value[g.permission] = { ...g }
  draft.value = JSON.parse(JSON.stringify(original.value))
}
watch([subjectType, subjectUuid], loadGrants, { immediate: true })

function setEffect(key: string, effect: Effect): void {
  draft.value[key] = { permission: key, effect, include_descendants: draft.value[key]?.include_descendants ?? false }
}

async function saveGrants(): Promise<void> {
  const grants = changes.value.map((k) => ({ permission: k, effect: draft.value[k]?.effect ?? null, include_descendants: draft.value[k]?.include_descendants ?? false }))
  try {
    const done = await withStepUp(stepUp, (code) => send('put', '/permission-assignments', { subject_type: subjectType.value, subject: subjectUuid.value, grants, confirmation_code: code }))
    if (done === null) return
    toast.add({ severity: 'success', summary: t('common.saved'), life: 3000 })
    await loadGrants()
    await session.loadMe()
  } catch (e) {
    if (e instanceof ApiError) toast.add({ severity: 'error', summary: Object.values(e.fieldErrors)[0] ?? e.message, life: 8000 })
  }
}

// Role editing
const roleDialog = ref<{ uuid?: string; key: string; names: Record<string, string>; descriptions: Record<string, string>; requires_2fa: boolean; is_admin_role: boolean; is_system?: boolean } | null>(
  null,
)
const roleErrors = ref<Record<string, string>>({})
function newRole(): void {
  roleErrors.value = {}
  roleDialog.value = { key: '', names: {}, descriptions: {}, requires_2fa: false, is_admin_role: false }
}
function editRole(r: Role): void {
  roleErrors.value = {}
  roleDialog.value = { uuid: r.uuid, key: r.key, names: { ...r.names }, descriptions: { ...r.descriptions }, requires_2fa: r.requires_2fa, is_admin_role: r.is_admin_role, is_system: r.is_system }
}
async function saveRole(): Promise<void> {
  const r = roleDialog.value!
  const body = { name: r.names, description: r.descriptions, requires_2fa: r.requires_2fa, is_admin_role: r.is_admin_role, ...(r.uuid ? {} : { key: r.key }) }
  try {
    if (r.uuid) await send('patch', `/roles/${r.uuid}`, body)
    else await send('post', '/roles', body)
    roleDialog.value = null
    await loadRoles()
  } catch (e) {
    if (e instanceof ApiError) roleErrors.value = e.fieldErrors
  }
}
function deleteRole(r: Role): void {
  confirm.require({
    message: t('access.delete_role_confirm', { name: r.name }),
    header: t('common.confirm'),
    acceptProps: { label: t('common.delete'), severity: 'danger' },
    rejectProps: { label: t('common.cancel'), severity: 'secondary' },
    accept: async () => {
      try {
        await send('delete', `/roles/${r.uuid}`)
        selectedRole.value = null
        await loadRoles()
      } catch (e) {
        if (e instanceof ApiError) toast.add({ severity: 'error', summary: Object.values(e.fieldErrors)[0] ?? e.message, life: 6000 })
      }
    },
  })
}

// Copy, export, import
const copyFrom = ref<string | null>(null)
async function copyPermissions(): Promise<void> {
  if (!selectedRole.value || !copyFrom.value) return
  try {
    await withStepUp(stepUp, (code) => send('post', `/roles/${selectedRole.value!.uuid}/copy-permissions`, { from_role: copyFrom.value, confirmation_code: code }))
    copyFrom.value = null
    await loadGrants()
  } catch (e) {
    if (e instanceof ApiError) toast.add({ severity: 'error', summary: Object.values(e.fieldErrors)[0] ?? e.message, life: 6000 })
  }
}
async function exportSet(): Promise<void> {
  const set = (await get<{ data: unknown }>('/access/export', { subject_type: subjectType.value, subject: subjectUuid.value })).data
  const blob = new Blob([JSON.stringify(set, null, 2)], { type: 'application/json' })
  const a = document.createElement('a')
  a.href = URL.createObjectURL(blob)
  a.download = `permissions-${subjectType.value}.json`
  a.click()
  URL.revokeObjectURL(a.href)
}
async function importSet(event: Event): Promise<void> {
  const file = (event.target as HTMLInputElement).files?.[0]
  ;(event.target as HTMLInputElement).value = ''
  if (!file || file.size > 1_000_000) return
  try {
    const set = JSON.parse(await file.text())
    await withStepUp(stepUp, (code) => send('post', '/access/import', { subject_type: subjectType.value, subject: subjectUuid.value, set, confirmation_code: code }))
    toast.add({ severity: 'success', summary: t('access.imported'), life: 3000 })
    await loadGrants()
  } catch (e) {
    toast.add({ severity: 'error', summary: e instanceof ApiError ? (Object.values(e.fieldErrors)[0] ?? e.message) : t('access.invalid_file'), life: 6000 })
  }
}

// View as user / explain
const viewAsUser = ref<{ uuid: string; name: string; email: string } | null>(null)
const viewAs = ref<{ permissions: { key: string; label: string; category: string; granted: boolean; decided_by: string }[] } | null>(null)
watch(viewAsUser, async (u) => {
  viewAs.value = u ? (await get<{ data: typeof viewAs.value }>(`/access/view-as/${u.uuid}`)).data : null
})
const explainPermission = ref<string | null>(null)
const explanation = ref<{ granted: boolean; decided_by: string; steps: { tier: string; effects: string[]; value_after: boolean }[] } | null>(null)
async function explain(key: string): Promise<void> {
  if (!viewAsUser.value) return
  explainPermission.value = key
  explanation.value = (await get<{ data: typeof explanation.value }>('/access/explain', { user: viewAsUser.value.uuid, permission: key })).data
}
</script>

<template>
  <h1 class="page-title">{{ t('admin.area.roles_permissions') }}</h1>
  <Tabs v-model:value="tab">
    <TabList>
      <Tab value="grants">{{ t('access.tab.grants') }}</Tab>
      <Tab value="view_as">{{ t('access.tab.view_as') }}</Tab>
    </TabList>
    <TabPanels>
      <TabPanel value="grants">
        <div class="flex flex-col lg:flex-row gap-4">
          <aside class="lg:w-72 shrink-0 flex flex-col gap-3">
            <SelectButton v-model="subjectType" :options="subjectOptions" option-label="label" option-value="value" :allow-empty="false" data-testid="subject-type" />
            <template v-if="subjectType === 'role'">
              <Listbox v-model="selectedRole" :options="roles" option-label="name" class="w-full" data-testid="role-list">
                <template #option="{ option }">
                  <div class="flex items-center gap-2 w-full">
                    <span class="flex-1">{{ option.name }}</span>
                    <Tag v-if="option.is_system" :value="t('access.system')" severity="secondary" />
                    <span class="text-xs text-muted-color">{{ option.users_count }}</span>
                  </div>
                </template>
              </Listbox>
              <div class="flex gap-2">
                <Button size="small" icon="pi pi-plus" :label="t('access.new_role')" data-testid="new-role" @click="newRole" />
                <Button v-if="selectedRole" size="small" severity="secondary" icon="pi pi-pencil" :aria-label="t('common.edit')" @click="editRole(selectedRole)" />
                <Button v-if="selectedRole && !selectedRole.is_system" size="small" severity="danger" icon="pi pi-trash" :aria-label="t('common.delete')" @click="deleteRole(selectedRole)" />
              </div>
            </template>
            <UserPicker v-else-if="subjectType === 'user'" v-model="selectedUser" />
            <TreeSelect v-else v-model="selectedDept" :options="departments" selection-mode="single" :placeholder="t('access.pick_department')" />
          </aside>
          <section class="flex-1 min-w-0">
            <Message v-if="!subjectUuid" severity="info">{{ t('access.pick_subject') }}</Message>
            <template v-else>
              <div class="flex flex-wrap items-center gap-2 mb-3">
                <Message severity="secondary" class="flex-1" size="small">{{ t('access.precedence_hint') }}</Message>
                <Button icon="pi pi-download" severity="secondary" :label="t('access.export')" @click="exportSet" />
                <label class="p-button p-button-secondary cursor-pointer"
                  ><i class="pi pi-upload me-2" />{{ t('access.import') }}<input type="file" accept="application/json" class="hidden" @change="importSet"
                /></label>
              </div>
              <div v-if="subjectType === 'role'" class="flex flex-wrap items-center gap-2 mb-3">
                <Select v-model="copyFrom" :options="roles.filter((r) => r.uuid !== selectedRole?.uuid)" option-label="name" option-value="uuid" :placeholder="t('access.copy_from')" show-clear />
                <Button severity="secondary" :label="t('access.copy')" :disabled="!copyFrom" @click="copyPermissions" />
              </div>
              <div v-for="[category, perms] in grouped" :key="category" class="mb-4">
                <h3 class="font-semibold mb-2">{{ t(`access.category.${category}`) }}</h3>
                <div class="rounded-lg border border-line divide-y divide-line">
                  <div v-for="p in perms" :key="p.key" class="flex flex-wrap items-center gap-3 p-2" :data-testid="`perm-${p.key}`">
                    <div class="flex-1 min-w-48">
                      <div>{{ p.label }} <Tag v-if="p.is_dangerous" severity="danger" :value="t('access.dangerous')" /></div>
                      <div class="text-xs text-muted-color ltr-value">{{ p.key }}</div>
                    </div>
                    <label v-if="subjectType === 'department' && draft[p.key]?.effect" class="flex items-center gap-1 text-sm">
                      <ToggleSwitch v-model="draft[p.key]!.include_descendants" />{{ t('access.include_sub_departments') }}
                    </label>
                    <SelectButton
                      :model-value="draft[p.key]?.effect ?? null"
                      :options="effectOptions"
                      option-label="label"
                      option-value="value"
                      :allow-empty="false"
                      size="small"
                      @update:model-value="(v: Effect) => setEffect(p.key, v)"
                    />
                  </div>
                </div>
              </div>
              <div class="sticky bottom-0 py-3 bg-subtle flex justify-end gap-2">
                <span v-if="changes.length" class="self-center text-sm">{{ t('access.pending_changes', { n: changes.length }) }}</span>
                <Button severity="secondary" :label="t('common.reset')" :disabled="!changes.length" @click="loadGrants" />
                <Button :label="t('common.save')" icon="pi pi-check" :disabled="!changes.length" data-testid="save-grants" @click="saveGrants" />
              </div>
            </template>
          </section>
        </div>
      </TabPanel>
      <TabPanel value="view_as">
        <p class="text-muted-color mb-3">{{ t('access.view_as_hint') }}</p>
        <UserPicker v-model="viewAsUser" />
        <div v-if="viewAs" class="mt-4 rounded-lg border border-line divide-y divide-line" data-testid="view-as-result">
          <div v-for="p in viewAs.permissions" :key="p.key" class="flex items-center gap-3 p-2">
            <i :class="p.granted ? 'pi pi-check-circle text-success' : 'pi pi-times-circle text-muted-color'" />
            <span class="flex-1">{{ p.label }}</span>
            <span class="text-xs text-muted-color">{{ t(`access.decided_by.${p.decided_by}`) }}</span>
            <Button size="small" text :label="t('access.explain')" @click="explain(p.key)" />
          </div>
        </div>
      </TabPanel>
    </TabPanels>
  </Tabs>

  <Dialog
    :visible="!!explanation"
    modal
    :header="t('access.explain_title', { permission: explainPermission })"
    :style="{ width: '32rem' }"
    @update:visible="(v: boolean) => !v && (explanation = null)"
  >
    <div v-if="explanation" class="flex flex-col gap-2">
      <ol class="list-decimal ps-5">
        <li v-for="(s, i) in explanation.steps" :key="i">
          {{ t(`access.tier.${s.tier}`) }}: {{ s.effects.map((e) => t(`access.effect.${e}`)).join(', ') }} → {{ s.value_after ? t('access.granted') : t('access.denied') }}
        </li>
        <li v-if="!explanation.steps.length">{{ t('access.decided_by.default_deny') }}</li>
      </ol>
      <Message :severity="explanation.granted ? 'success' : 'warn'"
        >{{ explanation.granted ? t('access.granted') : t('access.denied') }} — {{ t(`access.decided_by.${explanation.decided_by}`) }}</Message
      >
    </div>
  </Dialog>

  <Dialog
    :visible="!!roleDialog"
    modal
    :header="roleDialog?.uuid ? t('access.edit_role') : t('access.new_role')"
    :style="{ width: '34rem' }"
    @update:visible="(v: boolean) => !v && (roleDialog = null)"
  >
    <form v-if="roleDialog" class="flex flex-col gap-3" @submit.prevent="saveRole">
      <div class="field">
        <label for="rk">{{ t('access.role_key') }}</label
        ><InputText id="rk" v-model="roleDialog.key" :disabled="!!roleDialog.uuid" class="ltr-value" data-testid="role-key" /><span v-if="roleErrors.key" class="field-error">{{
          roleErrors.key
        }}</span>
      </div>
      <div v-for="l in session.boot?.locales ?? []" :key="l.code" class="field">
        <label :for="`rn-${l.code}`">{{ t('access.role_name') }} ({{ l.native_name }})</label>
        <InputText :id="`rn-${l.code}`" v-model="roleDialog.names[l.code]" :dir="l.direction" :data-testid="`role-name-${l.code}`" />
        <span v-if="roleErrors[`name.${l.code}`]" class="field-error">{{ roleErrors[`name.${l.code}`] }}</span>
      </div>
      <label class="flex items-center gap-2"><ToggleSwitch v-model="roleDialog.requires_2fa" />{{ t('access.requires_2fa') }}</label>
      <label class="flex items-center gap-2"><ToggleSwitch v-model="roleDialog.is_admin_role" />{{ t('access.is_admin_role') }}</label>
      <div class="flex justify-end gap-2">
        <Button type="button" severity="secondary" :label="t('common.cancel')" @click="roleDialog = null" />
        <Button type="submit" :label="t('common.save')" data-testid="role-save" />
      </div>
    </form>
  </Dialog>
  <StepUpDialog ref="stepUp" />
</template>
