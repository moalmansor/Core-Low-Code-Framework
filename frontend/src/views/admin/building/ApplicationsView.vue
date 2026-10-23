<script setup lang="ts">
import { maintenanceApi } from '@/views/experience/api'
import Button from 'primevue/button'
import Column from 'primevue/column'
import DataTable from 'primevue/datatable'
import DatePicker from 'primevue/datepicker'
import Dialog from 'primevue/dialog'
import InputNumber from 'primevue/inputnumber'
import InputText from 'primevue/inputtext'
import Menu from 'primevue/menu'
import Message from 'primevue/message'
import Select from 'primevue/select'
import Tag from 'primevue/tag'
import ToggleSwitch from 'primevue/toggleswitch'
import { useConfirm } from 'primevue/useconfirm'
import { useToast } from 'primevue/usetoast'
import { computed, onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRouter } from 'vue-router'
import { get, send } from '@/api/http'
import { useSession } from '@/stores/session'
import { labelColor, PICKER_FALLBACK } from '@/theme/color'
import LocaleFields from './LocaleFields.vue'
import { INCLUDE_MODES, errorText, fieldErrors, filledLocales, formatDateTime, type IncludeMode } from './shared'

interface AppRow {
  uuid: string
  key: string
  name: string
  description: string | null
  icon: string | null
  color: string | null
  status: 'active' | 'archived' | 'retired'
  data_sharing_default: 'shared' | 'isolated'
  maintenance_mode: boolean
  maintenance_until: string | null
  sort_order: number
  forms: number | null
}
interface AppDetail extends AppRow {
  names: Record<string, string>
  descriptions: Record<string, string>
  maintenance_messages: Record<string, string>
}

const { t } = useI18n()
const toast = useToast()
const confirm = useConfirm()
const router = useRouter()
const session = useSession()

const rows = ref<AppRow[]>([])
const loading = ref(false)
const search = ref('')
const filtered = computed(() => {
  const q = search.value.trim().toLowerCase()
  return q ? rows.value.filter((a) => `${a.name} ${a.key}`.toLowerCase().includes(q)) : rows.value
})

async function load(): Promise<void> {
  loading.value = true
  try {
    rows.value = (await get<{ data: AppRow[] }>('/applications')).data
  } finally {
    loading.value = false
  }
}
onMounted(load)

const statusSeverity = (s: string) => (s === 'active' ? 'success' : s === 'archived' ? 'warn' : 'secondary')
const sharingOptions = computed(() => (['shared', 'isolated'] as const).map((v) => ({ value: v, label: t(`building.app.sharing.${v}`) })))

// Create and edit
interface Editing {
  uuid?: string
  key: string
  names: Record<string, string>
  descriptions: Record<string, string>
  icon: string
  color: string
  data_sharing_default: 'shared' | 'isolated'
  sort_order: number
}
const editing = ref<Editing | null>(null)
const errors = ref<Record<string, string>>({})
const saving = ref(false)

function create(): void {
  errors.value = {}
  editing.value = { key: '', names: {}, descriptions: {}, icon: '', color: '', data_sharing_default: 'shared', sort_order: (rows.value.at(-1)?.sort_order ?? 0) + 10 }
}
async function edit(a: AppRow): Promise<void> {
  errors.value = {}
  try {
    const d = (await get<{ data: AppDetail }>(`/applications/${a.uuid}`)).data
    editing.value = {
      uuid: d.uuid,
      key: d.key,
      names: { ...d.names },
      descriptions: { ...d.descriptions },
      icon: d.icon ?? '',
      color: d.color ?? '',
      data_sharing_default: d.data_sharing_default,
      sort_order: d.sort_order,
    }
  } catch (e) {
    toast.add({ severity: 'error', summary: errorText(e, t('building.load_failed')), life: 6000 })
  }
}
async function save(): Promise<void> {
  const e = editing.value!
  const body = {
    name: filledLocales(e.names),
    description: filledLocales(e.descriptions),
    icon: e.icon.trim() || null,
    color: e.color.trim() || null,
    data_sharing_default: e.data_sharing_default,
    sort_order: e.sort_order,
    ...(e.uuid ? {} : { key: e.key }),
  }
  saving.value = true
  errors.value = {}
  try {
    if (e.uuid) await send('patch', `/applications/${e.uuid}`, body)
    else await send('post', '/applications', body)
    editing.value = null
    toast.add({ severity: 'success', summary: t('common.saved'), life: 3000 })
    await load()
  } catch (err) {
    errors.value = fieldErrors(err)
    toast.add({ severity: 'error', summary: errorText(err, t('building.save_failed')), life: 6000 })
  } finally {
    saving.value = false
  }
}

// Status
function changeStatus(a: AppRow, action: 'activate' | 'archive' | 'retire'): void {
  confirm.require({
    message: t(`building.app.confirm_${action}`, { name: a.name }),
    header: t('common.confirm'),
    acceptProps: { label: t(`building.app.action_${action}`), severity: action === 'activate' ? 'primary' : 'danger' },
    rejectProps: { label: t('common.cancel'), severity: 'secondary' },
    accept: async () => {
      try {
        await send('post', `/applications/${a.uuid}/${action}`)
        toast.add({ severity: 'success', summary: t('common.saved'), life: 3000 })
        await load()
      } catch (e) {
        toast.add({ severity: 'error', summary: errorText(e, t('building.save_failed')), life: 6000 })
      }
    },
  })
}

// Maintenance
/** For one application, or for the whole system when `app` is null. */
const maintenance = ref<{ app: AppRow | null; enabled: boolean; until: Date | null; messages: Record<string, string> } | null>(null)
const maintenanceErrors = ref<Record<string, string>>({})
async function openMaintenance(a: AppRow): Promise<void> {
  maintenanceErrors.value = {}
  try {
    const d = (await get<{ data: AppDetail }>(`/applications/${a.uuid}`)).data
    maintenance.value = { app: a, enabled: d.maintenance_mode, until: d.maintenance_until ? new Date(d.maintenance_until) : null, messages: { ...d.maintenance_messages } }
  } catch (e) {
    toast.add({ severity: 'error', summary: errorText(e, t('building.load_failed')), life: 6000 })
  }
}
async function openSystemMaintenance(): Promise<void> {
  maintenanceErrors.value = {}
  try {
    const d = await maintenanceApi.get()
    maintenance.value = { app: null, enabled: d.active, until: d.until ? new Date(d.until) : null, messages: { ...(d.messages ?? {}) } }
  } catch (e) {
    toast.add({ severity: 'error', summary: errorText(e, t('building.load_failed')), life: 6000 })
  }
}
async function saveMaintenance(): Promise<void> {
  const m = maintenance.value!
  maintenanceErrors.value = {}
  try {
    const until = m.enabled && m.until ? m.until.toISOString() : null
    if (m.app === null) await maintenanceApi.save(m.enabled, until, filledLocales(m.messages))
    else await send('post', `/applications/${m.app.uuid}/maintenance`, { enabled: m.enabled, until, message: filledLocales(m.messages) })
    maintenance.value = null
    toast.add({ severity: 'success', summary: t('common.saved'), life: 3000 })
    await load()
  } catch (e) {
    maintenanceErrors.value = fieldErrors(e)
    toast.add({ severity: 'error', summary: errorText(e, t('building.save_failed')), life: 6000 })
  }
}

// Make a copy (specification §4.30): forms published in the new application, links remapped, records never copied.
const cloning = ref<{ app: AppRow; key: string; names: Record<string, string>; include_mode: IncludeMode } | null>(null)
const cloneErrors = ref<Record<string, string>>({})
const cloneBusy = ref(false)
const cloneNotes = ref<string[]>([])
const includeOptions = computed(() => INCLUDE_MODES.map((v) => ({ value: v, label: t(`building.include.${v}`), disabled: v !== 'structure' && !session.can('system.manage_permissions') })))
function openClone(a: AppRow): void {
  cloneErrors.value = {}
  cloning.value = { app: a, key: '', names: {}, include_mode: 'structure' }
}
async function submitClone(): Promise<void> {
  const c = cloning.value!
  cloneErrors.value = {}
  cloneBusy.value = true
  try {
    const name = filledLocales(c.names)
    const res = await send<{ data: { uuid: string; forms: unknown[]; notes: string[] } }>('post', `/applications/${c.app.uuid}/clone`, { key: c.key, name, include_mode: c.include_mode })
    cloning.value = null
    cloneNotes.value = res.data.notes
    toast.add({
      severity: res.data.notes.length ? 'warn' : 'success',
      summary: t('building.app.cloned', { name: name[session.locale] ?? Object.values(name)[0] ?? '', n: res.data.forms.length }),
      life: 6000,
    })
    await load()
  } catch (e) {
    cloneErrors.value = fieldErrors(e)
    toast.add({ severity: 'error', summary: errorText(e, t('building.save_failed')), life: 8000 })
  } finally {
    cloneBusy.value = false
  }
}

// Row menu
const rowMenu = ref<InstanceType<typeof Menu> | null>(null)
const menuFor = ref<AppRow | null>(null)
const menuItems = computed(() => {
  const a = menuFor.value
  if (!a) return []
  const items: { label: string; icon: string; command: () => void }[] = [{ label: t('common.edit'), icon: 'pi pi-pencil', command: () => edit(a) }]
  if (session.can('system.manage_pages_menus'))
    items.push(
      { label: t('building.app.edit_menu'), icon: 'pi pi-sitemap', command: () => router.push({ name: 'admin.applications.menu', params: { application: a.uuid } }) },
      { label: t('xp.pages'), icon: 'pi pi-book', command: () => router.push({ name: 'admin.applications.pages', params: { application: a.uuid } }) },
    )
  if (session.can('system.manage_forms')) items.push({ label: t('building.app.clone'), icon: 'pi pi-copy', command: () => openClone(a) })
  if (session.can('system.enable_maintenance_mode')) items.push({ label: t('building.app.maintenance'), icon: 'pi pi-wrench', command: () => openMaintenance(a) })
  if (a.status !== 'active') items.push({ label: t('building.app.action_activate'), icon: 'pi pi-play', command: () => changeStatus(a, 'activate') })
  if (a.status === 'active') items.push({ label: t('building.app.action_archive'), icon: 'pi pi-inbox', command: () => changeStatus(a, 'archive') })
  if (a.status !== 'retired') items.push({ label: t('building.app.action_retire'), icon: 'pi pi-ban', command: () => changeStatus(a, 'retire') })
  return items
})
function openMenu(event: Event, a: AppRow): void {
  menuFor.value = a
  rowMenu.value?.toggle(event)
}
</script>

<template>
  <div class="flex flex-wrap items-center gap-3 mb-4">
    <h1 class="page-title !mb-0 flex-1">{{ t('admin.area.applications') }}</h1>
    <InputText v-model="search" :placeholder="t('common.search')" class="w-60" data-testid="app-search" />
    <Button
      v-if="session.can('system.enable_maintenance_mode')"
      icon="pi pi-wrench"
      :label="t('xp.system_maintenance')"
      severity="secondary"
      data-testid="system-maintenance"
      @click="openSystemMaintenance"
    />
    <Button icon="pi pi-plus" :label="t('building.app.new')" data-testid="app-new" @click="create" />
  </div>
  <p class="text-sm text-muted-color mb-3">{{ t('building.app.hint') }}</p>

  <Message v-if="cloneNotes.length" severity="warn" closable class="mb-3" data-testid="clone-notes" @close="cloneNotes = []">
    <div class="font-semibold mb-1">{{ t('building.app.clone_notes') }}</div>
    <ul class="text-sm list-disc ps-5 m-0">
      <li v-for="(n, i) in cloneNotes" :key="i">{{ n }}</li>
    </ul>
  </Message>

  <DataTable :value="filtered" :loading="loading" data-key="uuid" size="small" striped-rows data-testid="app-table">
    <template #empty>{{ t('building.app.empty') }}</template>
    <Column :header="t('building.name')">
      <template #body="{ data }">
        <div class="flex items-center gap-2">
          <span class="inline-flex items-center justify-center w-8 h-8 rounded-lg border border-line" :style="data.color ? { background: data.color, color: labelColor(data.color) } : undefined">
            <i :class="data.icon || 'pi pi-th-large'" />
          </span>
          <div>
            <div class="font-medium">{{ data.name }}</div>
            <div class="text-xs text-muted-color ltr-value">{{ data.key }}</div>
          </div>
        </div>
      </template>
    </Column>
    <Column :header="t('building.status')">
      <template #body="{ data }">
        <div class="flex flex-wrap gap-1">
          <Tag :severity="statusSeverity(data.status)" :value="t(`building.app.status.${data.status}`)" />
          <Tag v-if="data.maintenance_mode" severity="warn" icon="pi pi-wrench" :value="t('building.app.in_maintenance')" />
        </div>
        <div v-if="data.maintenance_mode && data.maintenance_until" class="text-xs text-muted-color mt-1">
          {{ t('building.app.maintenance_until_short', { at: formatDateTime(data.maintenance_until, session.locale) }) }}
        </div>
      </template>
    </Column>
    <Column :header="t('building.app.data_sharing')">
      <template #body="{ data }">{{ t(`building.app.sharing.${data.data_sharing_default}`) }}</template>
    </Column>
    <Column :header="t('building.app.forms_count')" field="forms" />
    <Column :header="t('common.sort_order')" field="sort_order" />
    <Column class="w-16">
      <template #body="{ data }">
        <Button icon="pi pi-ellipsis-v" text rounded :aria-label="t('common.actions')" :data-testid="`app-actions-${data.key}`" @click="openMenu($event, data)" />
      </template>
    </Column>
  </DataTable>
  <Menu ref="rowMenu" :model="menuItems" popup />

  <Dialog :visible="!!editing" modal :header="editing?.uuid ? t('building.app.edit') : t('building.app.new')" :style="{ width: '40rem' }" @update:visible="(v: boolean) => !v && (editing = null)">
    <form v-if="editing" class="flex flex-col gap-3" @submit.prevent="save">
      <div class="field">
        <label for="app-key">{{ t('building.key') }}</label>
        <InputText id="app-key" v-model="editing.key" :disabled="!!editing.uuid" class="ltr-value" data-testid="app-key" />
        <small class="text-muted-color">{{ t('building.key_hint') }}</small>
        <span v-if="errors.key" class="field-error">{{ errors.key }}</span>
      </div>
      <div class="form-grid">
        <LocaleFields v-model="editing.names" :label="t('building.name')" field="name" :errors="errors" id-prefix="app-name" />
      </div>
      <div class="form-grid">
        <LocaleFields v-model="editing.descriptions" :label="t('building.description')" field="description" :errors="errors" id-prefix="app-desc" multiline />
      </div>
      <div class="form-grid">
        <div class="field">
          <label for="app-icon">{{ t('building.icon') }}</label>
          <div class="flex items-center gap-2">
            <InputText id="app-icon" v-model="editing.icon" class="ltr-value flex-1" placeholder="pi pi-briefcase" />
            <i :class="editing.icon || 'pi pi-th-large'" class="text-xl" aria-hidden="true" />
          </div>
          <small class="text-muted-color">{{ t('building.icon_hint') }}</small>
          <span v-if="errors.icon" class="field-error">{{ errors.icon }}</span>
        </div>
        <div class="field">
          <label for="app-color">{{ t('building.color') }}</label>
          <div class="flex items-center gap-2">
            <input
              id="app-color-picker"
              type="color"
              :value="editing.color || PICKER_FALLBACK"
              class="w-10 h-9 rounded border border-line-input"
              :aria-label="t('building.color')"
              @input="editing.color = ($event.target as HTMLInputElement).value"
            />
            <InputText id="app-color" v-model="editing.color" class="ltr-value flex-1" placeholder="#RRGGBB" />
            <Button type="button" icon="pi pi-times" text :aria-label="t('building.clear')" @click="editing.color = ''" />
          </div>
          <span v-if="errors.color" class="field-error">{{ errors.color }}</span>
        </div>
        <div class="field">
          <label for="app-sharing">{{ t('building.app.data_sharing') }}</label>
          <Select v-model="editing.data_sharing_default" input-id="app-sharing" :options="sharingOptions" option-label="label" option-value="value" />
          <small class="text-muted-color">{{ t('building.app.data_sharing_hint') }}</small>
        </div>
        <div class="field">
          <label for="app-order">{{ t('common.sort_order') }}</label>
          <InputNumber v-model="editing.sort_order" input-id="app-order" :min="0" :max="100000" :use-grouping="false" />
        </div>
      </div>
      <div class="flex justify-end gap-2">
        <Button type="button" severity="secondary" :label="t('common.cancel')" @click="editing = null" />
        <Button type="submit" :label="t('common.save')" :loading="saving" data-testid="app-save" />
      </div>
    </form>
  </Dialog>

  <Dialog
    :visible="!!cloning"
    modal
    :header="cloning ? t('building.app.clone_title', { name: cloning.app.name }) : ''"
    :style="{ width: '40rem' }"
    @update:visible="(v: boolean) => !v && (cloning = null)"
  >
    <form v-if="cloning" class="flex flex-col gap-3" @submit.prevent="submitClone">
      <p class="m-0 text-sm text-muted-color">{{ t('building.app.clone_hint') }}</p>
      <div class="form-grid">
        <div class="field">
          <label for="clone-key">{{ t('building.key') }}</label>
          <InputText id="clone-key" v-model="cloning.key" class="ltr-value" data-testid="clone-key" />
          <small class="text-muted-color">{{ t('building.key_hint') }}</small>
          <span v-if="cloneErrors.key" class="field-error">{{ cloneErrors.key }}</span>
        </div>
        <div class="field">
          <label for="clone-include">{{ t('building.include_label') }}</label>
          <Select v-model="cloning.include_mode" input-id="clone-include" :options="includeOptions" option-label="label" option-value="value" option-disabled="disabled" />
          <small class="text-muted-color">{{ t(`building.include_hint.${cloning.include_mode}`) }}</small>
        </div>
      </div>
      <div class="form-grid">
        <LocaleFields v-model="cloning.names" :label="t('building.name')" field="name" :errors="cloneErrors" id-prefix="clone-name" />
      </div>
      <div class="flex justify-end gap-2">
        <Button type="button" severity="secondary" :label="t('common.cancel')" @click="cloning = null" />
        <Button type="submit" :label="t('building.app.clone_submit')" :loading="cloneBusy" data-testid="clone-submit" />
      </div>
    </form>
  </Dialog>

  <Dialog
    :visible="!!maintenance"
    modal
    :header="maintenance?.app ? t('building.app.maintenance') : t('xp.system_maintenance')"
    :style="{ width: '36rem' }"
    @update:visible="(v: boolean) => !v && (maintenance = null)"
  >
    <form v-if="maintenance" class="flex flex-col gap-3" @submit.prevent="saveMaintenance">
      <Message severity="secondary" size="small">{{ maintenance.app ? t('building.app.maintenance_hint', { name: maintenance.app.name }) : t('xp.system_maintenance_hint') }}</Message>
      <label class="flex items-center gap-2"><ToggleSwitch v-model="maintenance.enabled" data-testid="maintenance-enabled" />{{ t('building.app.maintenance_enabled') }}</label>
      <div class="field">
        <label for="mt-until">{{ t('building.app.maintenance_until') }}</label>
        <DatePicker v-model="maintenance.until" input-id="mt-until" show-time hour-format="24" :min-date="new Date()" show-button-bar :disabled="!maintenance.enabled" />
        <small class="text-muted-color">{{ t('building.app.maintenance_until_hint') }}</small>
        <span v-if="maintenanceErrors.until" class="field-error">{{ maintenanceErrors.until }}</span>
      </div>
      <div class="form-grid">
        <LocaleFields v-model="maintenance.messages" :label="t('building.app.maintenance_message')" field="message" :errors="maintenanceErrors" id-prefix="mt-msg" multiline />
      </div>
      <div class="flex justify-end gap-2">
        <Button type="button" severity="secondary" :label="t('common.cancel')" @click="maintenance = null" />
        <Button type="submit" :label="t('common.save')" data-testid="maintenance-save" />
      </div>
    </form>
  </Dialog>
</template>
