<script setup lang="ts">
import Button from 'primevue/button'
import Column from 'primevue/column'
import DataTable, { type DataTablePageEvent } from 'primevue/datatable'
import Dialog from 'primevue/dialog'
import IconField from 'primevue/iconfield'
import InputIcon from 'primevue/inputicon'
import InputText from 'primevue/inputtext'
import Menu from 'primevue/menu'
import Message from 'primevue/message'
import Select from 'primevue/select'
import SelectButton from 'primevue/selectbutton'
import Tag from 'primevue/tag'
import ToggleSwitch from 'primevue/toggleswitch'
import { useConfirm } from 'primevue/useconfirm'
import { useToast } from 'primevue/usetoast'
import { computed, onMounted, reactive, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRoute, useRouter } from 'vue-router'
import { get, send } from '@/api/http'
import { useSession } from '@/stores/session'
import LocaleFields from './LocaleFields.vue'
import { INCLUDE_MODES, errorText, fieldErrors, filledLocales, formatDateTime, type FormSummary, type IncludeMode } from './shared'
import { humanize } from '@/runtime/i18nText'

interface AppOption {
  uuid: string
  key: string
  name: string
  status: string
}
interface BindableTable {
  name: string
  columns: { name: string; type: string; nullable: boolean; logical: string | null }[]
}

const { t } = useI18n()
const toast = useToast()
const confirm = useConfirm()
const route = useRoute()
const router = useRouter()
const session = useSession()

const rows = ref<FormSummary[]>([])
const total = ref(0)
const loading = ref(false)
const apps = ref<AppOption[]>([])
const filters = reactive({
  search: typeof route.query.search === 'string' ? route.query.search : '',
  application: typeof route.query.application === 'string' ? route.query.application : null,
  kind: typeof route.query.kind === 'string' ? route.query.kind : null,
  state: typeof route.query.state === 'string' ? route.query.state : null,
  page: 1,
  per_page: 25,
})
const STATES = ['draft', 'published', 'unpublished', 'archived', 'schema_inconsistent'] as const
const kindOptions = computed(() => (['form', 'collection'] as const).map((v) => ({ value: v, label: t(`building.kind.${v}`) })))
const stateOptions = computed(() => STATES.map((v) => ({ value: v, label: t(`building.form_state.${v}`) })))
const appOptions = computed(() => apps.value.map((a) => ({ value: a.uuid, label: a.name })))
const appName = (uuid: string | undefined) => apps.value.find((a) => a.uuid === uuid)?.name ?? ''

async function load(): Promise<void> {
  loading.value = true
  try {
    const params: Record<string, unknown> = { page: filters.page, per_page: filters.per_page }
    if (filters.search.trim()) params.search = filters.search.trim()
    if (filters.application) params.application = filters.application
    if (filters.kind) params.kind = filters.kind
    if (filters.state) params.state = filters.state
    const res = await get<{ data: FormSummary[]; meta: { total: number } }>('/forms', params)
    rows.value = res.data
    total.value = res.meta.total
  } catch (e) {
    toast.add({ severity: 'error', summary: errorText(e, t('building.load_failed')), life: 6000 })
  } finally {
    loading.value = false
  }
}
let searchTimer: ReturnType<typeof setTimeout> | undefined
watch(
  () => [filters.search, filters.application, filters.kind, filters.state],
  () => {
    clearTimeout(searchTimer)
    searchTimer = setTimeout(() => {
      filters.page = 1
      router.replace({
        query: Object.fromEntries(
          Object.entries({ search: filters.search || undefined, application: filters.application ?? undefined, kind: filters.kind ?? undefined, state: filters.state ?? undefined }).filter(
            ([, v]) => v !== undefined,
          ),
        ),
      })
      load()
    }, 300)
  },
)
function onPage(e: DataTablePageEvent): void {
  filters.page = e.page + 1
  filters.per_page = e.rows
  load()
}
onMounted(async () => {
  apps.value = (await get<{ data: AppOption[] }>('/applications')).data
  await load()
})

const stateSeverity: Record<string, string> = { draft: 'secondary', published: 'success', unpublished: 'warn', archived: 'contrast', schema_inconsistent: 'danger' }

// Create
interface Creating {
  kind: 'form' | 'collection'
  collection_type: 'table' | 'key_value'
  key: string
  names: Record<string, string>
  descriptions: Record<string, string>
  application: string | null
  binding_mode: 'managed' | 'bound'
  bound_table: string | null
}
const creating = ref<Creating | null>(null)
const createErrors = ref<Record<string, string>>({})
const bindable = ref<BindableTable[] | null>(null)
const busy = ref(false)
function openCreate(): void {
  createErrors.value = {}
  creating.value = {
    kind: 'form',
    collection_type: 'table',
    key: '',
    names: {},
    descriptions: {},
    application: filters.application ?? apps.value.find((a) => a.status === 'active')?.uuid ?? null,
    binding_mode: 'managed',
    bound_table: null,
  }
}
watch(
  () => creating.value?.binding_mode,
  async (mode) => {
    if (mode === 'bound' && bindable.value === null) {
      try {
        bindable.value = (await get<{ data: BindableTable[] }>('/schema/bindable-tables')).data
      } catch (e) {
        bindable.value = []
        toast.add({ severity: 'error', summary: errorText(e, t('building.load_failed')), life: 6000 })
      }
    }
  },
)
const boundColumns = computed(() => bindable.value?.find((b) => b.name === creating.value?.bound_table)?.columns ?? [])
async function submitCreate(): Promise<void> {
  const c = creating.value!
  busy.value = true
  createErrors.value = {}
  try {
    const res = await send<{ data: { uuid: string } }>('post', '/forms', {
      kind: c.kind,
      key: c.key,
      application: c.application,
      name: filledLocales(c.names),
      description: filledLocales(c.descriptions),
      binding_mode: c.binding_mode,
      ...(c.binding_mode === 'bound' ? { bound_table: c.bound_table } : {}),
      ...(c.kind === 'collection' ? { collection_type: c.collection_type } : {}),
    })
    creating.value = null
    await router.push({ name: 'admin.forms.builder', params: { form: res.data.uuid } })
  } catch (e) {
    createErrors.value = fieldErrors(e)
    toast.add({ severity: 'error', summary: errorText(e, t('building.save_failed')), life: 6000 })
  } finally {
    busy.value = false
  }
}

// Duplicate
const duplicating = ref<{ form: FormSummary; key: string; names: Record<string, string>; include: IncludeMode } | null>(null)
const dupErrors = ref<Record<string, string>>({})
const includeOptions = computed(() => INCLUDE_MODES.map((v) => ({ value: v, label: t(`building.include.${v}`), disabled: v !== 'structure' && !session.can('system.manage_permissions') })))
function openDuplicate(f: FormSummary): void {
  dupErrors.value = {}
  duplicating.value = { form: f, key: `${f.key}_copy`.slice(0, 40), names: { [session.boot?.default_locale ?? 'en']: `${f.name} (${t('building.copy_suffix')})` }, include: 'structure' }
}
async function submitDuplicate(): Promise<void> {
  const d = duplicating.value!
  busy.value = true
  dupErrors.value = {}
  try {
    await send('post', `/forms/${d.form.uuid}/duplicate`, { key: d.key, name: filledLocales(d.names), include: d.include })
    duplicating.value = null
    toast.add({ severity: 'success', summary: t('building.forms.duplicated'), life: 3000 })
    await load()
  } catch (e) {
    dupErrors.value = fieldErrors(e)
    toast.add({ severity: 'error', summary: errorText(e, t('building.save_failed')), life: 6000 })
  } finally {
    busy.value = false
  }
}

// Save as blueprint
const blueprinting = ref<{
  form: FormSummary
  names: Record<string, string>
  descriptions: Record<string, string>
  category: string
  tags: string
  include_mode: IncludeMode
  is_library: boolean
} | null>(null)
const bpErrors = ref<Record<string, string>>({})
function openBlueprint(f: FormSummary): void {
  bpErrors.value = {}
  blueprinting.value = { form: f, names: { [session.boot?.default_locale ?? 'en']: f.name }, descriptions: {}, category: '', tags: '', include_mode: 'structure', is_library: true }
}
async function submitBlueprint(): Promise<void> {
  const b = blueprinting.value!
  busy.value = true
  bpErrors.value = {}
  try {
    const res = await send<{ data: { uuid: string } }>('post', '/blueprints', {
      source: b.form.uuid,
      include_mode: b.include_mode,
      name: filledLocales(b.names),
      description: filledLocales(b.descriptions),
      category: b.category.trim() || null,
      tags: b.tags
        .split(',')
        .map((x) => x.trim())
        .filter(Boolean),
      is_library: b.is_library,
    })
    blueprinting.value = null
    await router.push({ name: 'admin.blueprints.detail', params: { blueprint: res.data.uuid } })
  } catch (e) {
    bpErrors.value = fieldErrors(e)
    toast.add({ severity: 'error', summary: errorText(e, t('building.save_failed')), life: 6000 })
  } finally {
    busy.value = false
  }
}

// State changes and delete
function changeState(f: FormSummary, action: 'unpublish' | 'archive' | 'republish'): void {
  confirm.require({
    message: t(`building.forms.confirm_${action}`, { name: f.name }),
    header: t('common.confirm'),
    acceptProps: { label: t(`building.forms.action_${action}`), severity: action === 'republish' ? 'primary' : 'warn' },
    rejectProps: { label: t('common.cancel'), severity: 'secondary' },
    accept: async () => {
      try {
        await send('post', `/forms/${f.uuid}/${action}`)
        toast.add({ severity: 'success', summary: t('common.saved'), life: 3000 })
        await load()
      } catch (e) {
        toast.add({ severity: 'error', summary: errorText(e, t('building.save_failed')), life: 6000 })
      }
    },
  })
}
function destroy(f: FormSummary): void {
  confirm.require({
    message: t('building.forms.confirm_delete', { name: f.name }),
    header: t('common.confirm'),
    acceptProps: { label: t('common.delete'), severity: 'danger' },
    rejectProps: { label: t('common.cancel'), severity: 'secondary' },
    accept: async () => {
      try {
        await send('delete', `/forms/${f.uuid}`)
        toast.add({ severity: 'success', summary: t('building.forms.deleted'), life: 3000 })
        await load()
      } catch (e) {
        toast.add({ severity: 'error', summary: errorText(e, t('building.save_failed')), life: 6000 })
      }
    },
  })
}

// Row actions
const rowMenu = ref<InstanceType<typeof Menu> | null>(null)
const menuFor = ref<FormSummary | null>(null)
const openBuilder = (f: FormSummary) => router.push({ name: 'admin.forms.builder', params: { form: f.uuid } })
const menuItems = computed(() => {
  const f = menuFor.value
  if (!f) return []
  const items: { label: string; icon: string; command: () => void; class?: string }[] = [
    { label: t('building.forms.open_builder'), icon: 'pi pi-pencil', command: () => openBuilder(f) },
    { label: t('building.forms.versions'), icon: 'pi pi-history', command: () => router.push({ name: 'admin.forms.versions', params: { form: f.uuid } }) },
    { label: t('formconfig.open'), icon: 'pi pi-sitemap', command: () => router.push({ name: 'admin.forms.configure', params: { form: f.uuid } }) },
  ]
  if (f.version !== null && f.state !== 'schema_inconsistent')
    items.push({ label: t('building.forms.open_records'), icon: 'pi pi-list', command: () => router.push({ name: 'records.list', params: { form: f.uuid } }) })
  if (session.can('system.manage_permissions'))
    items.push({ label: t('building.forms.access'), icon: 'pi pi-lock', command: () => router.push({ name: 'admin.forms.access', params: { form: f.uuid } }) })
  if (f.state === 'schema_inconsistent') items.push({ label: t('building.forms.repair'), icon: 'pi pi-wrench', command: () => router.push({ name: 'admin.schema.plans', query: { form: f.uuid } }) })
  else items.push({ label: t('building.forms.migration_plans'), icon: 'pi pi-database', command: () => router.push({ name: 'admin.schema.plans', query: { form: f.uuid } }) })
  items.push({ label: t('building.forms.duplicate'), icon: 'pi pi-copy', command: () => openDuplicate(f) })
  if (session.can('system.manage_blueprints')) items.push({ label: t('building.forms.save_blueprint'), icon: 'pi pi-clone', command: () => openBlueprint(f) })
  if (f.state === 'published') items.push({ label: t('building.forms.action_unpublish'), icon: 'pi pi-eye-slash', command: () => changeState(f, 'unpublish') })
  if (f.state === 'published' || f.state === 'unpublished') items.push({ label: t('building.forms.action_archive'), icon: 'pi pi-inbox', command: () => changeState(f, 'archive') })
  if (f.state === 'unpublished' || f.state === 'archived') items.push({ label: t('building.forms.action_republish'), icon: 'pi pi-eye', command: () => changeState(f, 'republish') })
  if (f.version === null) items.push({ label: t('building.forms.delete_draft'), icon: 'pi pi-trash', class: 'text-danger', command: () => destroy(f) })
  return items
})
function openMenu(event: Event, f: FormSummary): void {
  menuFor.value = f
  rowMenu.value?.toggle(event)
}
</script>

<template>
  <div class="flex flex-wrap items-center gap-3 mb-4">
    <h1 class="page-title !mb-0 flex-1">{{ t('admin.area.forms') }}</h1>
    <Button icon="pi pi-plus" :label="t('building.forms.new')" data-testid="form-new" @click="openCreate" />
  </div>
  <div class="flex flex-wrap gap-2 mb-3">
    <IconField>
      <InputIcon class="pi pi-search" />
      <InputText v-model="filters.search" :placeholder="t('common.search')" data-testid="forms-search" />
    </IconField>
    <Select v-model="filters.application" :options="appOptions" option-label="label" option-value="value" :placeholder="t('building.application')" show-clear class="w-52" />
    <Select v-model="filters.kind" :options="kindOptions" option-label="label" option-value="value" :placeholder="t('building.kind_label')" show-clear class="w-40" />
    <Select v-model="filters.state" :options="stateOptions" option-label="label" option-value="value" :placeholder="t('building.status')" show-clear class="w-48" />
  </div>

  <DataTable
    :value="rows"
    lazy
    paginator
    :rows="filters.per_page"
    :rows-per-page-options="[25, 50, 100]"
    :total-records="total"
    :loading="loading"
    data-key="uuid"
    size="small"
    striped-rows
    data-testid="forms-table"
    @page="onPage"
  >
    <template #empty>{{ t('building.forms.empty') }}</template>
    <Column :header="t('building.name')">
      <template #body="{ data }">
        <button type="button" class="text-start flex items-center gap-2 cursor-pointer" @click="openBuilder(data)">
          <i :class="data.icon || (data.kind === 'collection' ? 'pi pi-table' : 'pi pi-file')" class="text-muted-color" />
          <span>
            <span class="font-medium block">{{ data.name }}</span>
            <span class="text-xs text-muted-color ltr-value">{{ data.key }}</span>
          </span>
        </button>
      </template>
    </Column>
    <Column :header="t('building.kind_label')">
      <template #body="{ data }">{{ t(`building.kind.${data.kind}`) }}</template>
    </Column>
    <Column :header="t('building.application')">
      <template #body="{ data }">{{ appName(data.application?.uuid) || (data.application ? humanize(data.application.key) : '') || '—' }}</template>
    </Column>
    <Column :header="t('building.status')">
      <template #body="{ data }">
        <Tag :severity="stateSeverity[data.state]" :value="t(`building.form_state.${data.state}`)" />
      </template>
    </Column>
    <Column :header="t('building.forms.version')">
      <template #body="{ data }">
        <span v-if="data.version">v{{ data.version }}</span
        ><span v-else class="text-muted-color">—</span>
        <span v-if="data.next_version && data.next_version !== data.version" class="text-xs text-muted-color ms-1">{{ t('building.forms.draft_of', { n: data.next_version }) }}</span>
      </template>
    </Column>
    <Column :header="t('building.forms.storage')">
      <template #body="{ data }">
        <span class="ltr-value text-sm">{{ data.table_name ?? '—' }}</span>
        <Tag v-if="data.binding_mode === 'bound'" class="ms-1" severity="info" :value="t('building.forms.bound')" />
      </template>
    </Column>
    <Column :header="t('building.forms.records')" field="record_count" />
    <Column :header="t('building.updated')">
      <template #body="{ data }"
        ><span class="text-sm">{{ formatDateTime(data.updated_at, session.locale) }}</span></template
      >
    </Column>
    <Column class="w-16">
      <template #body="{ data }">
        <Button icon="pi pi-ellipsis-v" text rounded :aria-label="t('common.actions')" :data-testid="`form-actions-${data.key}`" @click="openMenu($event, data)" />
      </template>
    </Column>
  </DataTable>
  <Menu ref="rowMenu" :model="menuItems" popup />

  <Dialog :visible="!!creating" modal :header="t('building.forms.new')" :style="{ width: '44rem' }" @update:visible="(v: boolean) => !v && (creating = null)">
    <form v-if="creating" class="flex flex-col gap-3" @submit.prevent="submitCreate">
      <SelectButton v-model="creating.kind" :options="kindOptions" option-label="label" option-value="value" :allow-empty="false" data-testid="create-kind" />
      <div class="form-grid">
        <div class="field">
          <label for="cf-key">{{ t('building.key') }}</label>
          <InputText id="cf-key" v-model="creating.key" class="ltr-value" data-testid="create-key" />
          <small class="text-muted-color">{{ t('building.forms.key_hint') }}</small>
          <span v-if="createErrors.key" class="field-error">{{ createErrors.key }}</span>
        </div>
        <div class="field">
          <label for="cf-app">{{ t('building.application') }}</label>
          <Select v-model="creating.application" input-id="cf-app" :options="appOptions" option-label="label" option-value="value" data-testid="create-app" />
          <span v-if="createErrors.application" class="field-error">{{ createErrors.application }}</span>
        </div>
        <div v-if="creating.kind === 'collection'" class="field">
          <label for="cf-ctype">{{ t('building.forms.collection_type') }}</label>
          <Select
            v-model="creating.collection_type"
            input-id="cf-ctype"
            :options="[
              { value: 'table', label: t('building.forms.collection_table') },
              { value: 'key_value', label: t('building.forms.collection_key_value') },
            ]"
            option-label="label"
            option-value="value"
          />
        </div>
      </div>
      <div class="form-grid">
        <LocaleFields v-model="creating.names" :label="t('building.name')" field="name" :errors="createErrors" id-prefix="cf-name" />
      </div>
      <div class="form-grid">
        <LocaleFields v-model="creating.descriptions" :label="t('building.description')" field="description" :errors="createErrors" id-prefix="cf-desc" multiline />
      </div>
      <div class="field">
        <span class="text-sm font-medium">{{ t('building.forms.storage') }}</span>
        <SelectButton
          v-model="creating.binding_mode"
          :options="[
            { value: 'managed', label: t('building.forms.managed') },
            { value: 'bound', label: t('building.forms.bound_existing') },
          ]"
          option-label="label"
          option-value="value"
          :allow-empty="false"
        />
        <small class="text-muted-color">{{ creating.binding_mode === 'managed' ? t('building.forms.managed_hint') : t('building.forms.bound_hint') }}</small>
      </div>
      <template v-if="creating.binding_mode === 'bound'">
        <div class="field">
          <label for="cf-table">{{ t('building.forms.bound_table') }}</label>
          <Select v-model="creating.bound_table" input-id="cf-table" :options="bindable ?? []" option-label="name" option-value="name" filter :loading="bindable === null" class="ltr-value" />
          <span v-if="createErrors.bound_table" class="field-error">{{ createErrors.bound_table }}</span>
          <small v-if="bindable && !bindable.length" class="text-muted-color">{{ t('building.forms.no_bindable') }}</small>
        </div>
        <div v-if="boundColumns.length" class="rounded border border-line max-h-48 overflow-auto text-sm">
          <table class="w-full">
            <thead>
              <tr class="text-start">
                <th class="p-1 text-start">{{ t('building.schema.column') }}</th>
                <th class="p-1 text-start">{{ t('building.schema.type') }}</th>
                <th class="p-1 text-start">{{ t('building.schema.logical') }}</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="c in boundColumns" :key="c.name" class="border-t border-line">
                <td class="p-1 ltr-value">{{ c.name }}</td>
                <td class="p-1 ltr-value">{{ c.type }}{{ c.nullable ? ' NULL' : '' }}</td>
                <td class="p-1">{{ c.logical ?? t('building.forms.unsupported_type') }}</td>
              </tr>
            </tbody>
          </table>
        </div>
      </template>
      <div class="flex justify-end gap-2">
        <Button type="button" severity="secondary" :label="t('common.cancel')" @click="creating = null" />
        <Button type="submit" :label="t('building.forms.create_and_build')" :loading="busy" data-testid="create-submit" />
      </div>
    </form>
  </Dialog>

  <Dialog :visible="!!duplicating" modal :header="t('building.forms.duplicate')" :style="{ width: '36rem' }" @update:visible="(v: boolean) => !v && (duplicating = null)">
    <form v-if="duplicating" class="flex flex-col gap-3" @submit.prevent="submitDuplicate">
      <p class="text-sm text-muted-color">{{ t('building.forms.duplicate_hint', { name: duplicating.form.name }) }}</p>
      <div class="field">
        <label for="dup-key">{{ t('building.key') }}</label>
        <InputText id="dup-key" v-model="duplicating.key" class="ltr-value" />
        <span v-if="dupErrors.key" class="field-error">{{ dupErrors.key }}</span>
      </div>
      <LocaleFields v-model="duplicating.names" :label="t('building.name')" field="name" :errors="dupErrors" id-prefix="dup-name" />
      <div class="field">
        <label for="dup-include">{{ t('building.include_label') }}</label>
        <Select v-model="duplicating.include" input-id="dup-include" :options="includeOptions" option-label="label" option-value="value" option-disabled="disabled" />
        <small class="text-muted-color">{{ t(`building.include_hint.${duplicating.include}`) }}</small>
      </div>
      <div class="flex justify-end gap-2">
        <Button type="button" severity="secondary" :label="t('common.cancel')" @click="duplicating = null" />
        <Button type="submit" :label="t('building.forms.duplicate')" :loading="busy" />
      </div>
    </form>
  </Dialog>

  <Dialog :visible="!!blueprinting" modal :header="t('building.forms.save_blueprint')" :style="{ width: '40rem' }" @update:visible="(v: boolean) => !v && (blueprinting = null)">
    <form v-if="blueprinting" class="flex flex-col gap-3" @submit.prevent="submitBlueprint">
      <Message severity="secondary" size="small">{{ t('building.blueprints.from_form_hint', { name: blueprinting.form.name }) }}</Message>
      <div class="form-grid">
        <LocaleFields v-model="blueprinting.names" :label="t('building.name')" field="name" :errors="bpErrors" id-prefix="bp-name" />
      </div>
      <div class="form-grid">
        <LocaleFields v-model="blueprinting.descriptions" :label="t('building.description')" field="description" :errors="bpErrors" id-prefix="bp-desc" multiline />
      </div>
      <div class="form-grid">
        <div class="field">
          <label for="bp-cat">{{ t('building.blueprints.category') }}</label>
          <InputText id="bp-cat" v-model="blueprinting.category" class="ltr-value" />
          <small class="text-muted-color">{{ t('building.blueprints.category_hint') }}</small>
          <span v-if="bpErrors.category" class="field-error">{{ bpErrors.category }}</span>
        </div>
        <div class="field">
          <label for="bp-tags">{{ t('building.blueprints.tags') }}</label>
          <InputText id="bp-tags" v-model="blueprinting.tags" />
          <small class="text-muted-color">{{ t('building.blueprints.tags_hint') }}</small>
        </div>
        <div class="field">
          <label for="bp-include">{{ t('building.include_label') }}</label>
          <Select v-model="blueprinting.include_mode" input-id="bp-include" :options="includeOptions" option-label="label" option-value="value" option-disabled="disabled" />
        </div>
      </div>
      <label class="flex items-center gap-2"><ToggleSwitch v-model="blueprinting.is_library" />{{ t('building.blueprints.in_library') }}</label>
      <div class="flex justify-end gap-2">
        <Button type="button" severity="secondary" :label="t('common.cancel')" @click="blueprinting = null" />
        <Button type="submit" :label="t('building.forms.save_blueprint')" :loading="busy" />
      </div>
    </form>
  </Dialog>
</template>
