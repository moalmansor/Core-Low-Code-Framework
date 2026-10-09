<script setup lang="ts">
import AutoComplete from 'primevue/autocomplete'
import Button from 'primevue/button'
import Dialog from 'primevue/dialog'
import IconField from 'primevue/iconfield'
import InputIcon from 'primevue/inputicon'
import InputText from 'primevue/inputtext'
import Message from 'primevue/message'
import Select from 'primevue/select'
import SelectButton from 'primevue/selectbutton'
import Tag from 'primevue/tag'
import ToggleSwitch from 'primevue/toggleswitch'
import { useToast } from 'primevue/usetoast'
import { computed, onMounted, reactive, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRouter } from 'vue-router'
import { ensureCsrf, get, http, send } from '@/api/http'
import { useSession } from '@/stores/session'
import LocaleFields from './LocaleFields.vue'
import { INCLUDE_MODES, errorText, fieldErrors, filledLocales, formatDateTime, type FormSummary, type IncludeMode } from './shared'

interface BlueprintRow {
  uuid: string
  kind: string
  category: string | null
  tags: string[]
  is_library: boolean
  name: string | null
  description: string | null
  version: number | null
  include_mode: IncludeMode | null
  updated_at: string | null
  instance_count: number
}

const { t } = useI18n()
const toast = useToast()
const router = useRouter()
const session = useSession()

const KINDS = ['form', 'collection', 'workflow', 'view', 'action', 'notification', 'dashboard', 'application'] as const
const rows = ref<BlueprintRow[]>([])
const loading = ref(false)
const filters = reactive({ search: '', kind: null as string | null, category: null as string | null, library: 'all' as 'all' | 'library' | 'other' })
const kindOptions = computed(() => KINDS.map((k) => ({ value: k, label: t(`building.blueprints.kind.${k}`) })))
const libraryOptions = computed(() => (['all', 'library', 'other'] as const).map((v) => ({ value: v, label: t(`building.blueprints.library_filter.${v}`) })))
const categories = ref<string[]>([])

async function load(): Promise<void> {
  loading.value = true
  try {
    const params: Record<string, unknown> = {}
    if (filters.search.trim()) params.search = filters.search.trim()
    if (filters.kind) params.kind = filters.kind
    if (filters.category) params.category = filters.category
    if (filters.library !== 'all') params.library = filters.library === 'library' ? 1 : 0
    rows.value = (await get<{ data: BlueprintRow[] }>('/blueprints', params)).data
    for (const r of rows.value) if (r.category && !categories.value.includes(r.category)) categories.value.push(r.category)
    categories.value.sort()
  } catch (e) {
    toast.add({ severity: 'error', summary: errorText(e, t('building.load_failed')), life: 6000 })
  } finally {
    loading.value = false
  }
}
let timer: ReturnType<typeof setTimeout> | undefined
watch(
  () => [filters.search, filters.kind, filters.category, filters.library],
  () => {
    clearTimeout(timer)
    timer = setTimeout(load, 300)
  },
)
onMounted(load)

// Import
const importing = ref(false)
async function importFile(event: Event): Promise<void> {
  const input = event.target as HTMLInputElement
  const file = input.files?.[0]
  input.value = ''
  if (!file) return
  if (file.size > 5 * 1024 * 1024) {
    toast.add({ severity: 'error', summary: t('building.blueprints.file_too_large'), life: 6000 })
    return
  }
  const body = new FormData()
  body.append('file', file)
  importing.value = true
  try {
    await ensureCsrf()
    const res = (await http.post<{ data: { uuid: string; versions_added: number } }>('/blueprints/import', body)).data
    toast.add({ severity: 'success', summary: t('building.blueprints.imported', { n: res.data.versions_added }), life: 5000 })
    await router.push({ name: 'admin.blueprints.detail', params: { blueprint: res.data.uuid } })
  } catch (e) {
    toast.add({ severity: 'error', summary: errorText(e, t('building.blueprints.import_failed')), life: 8000 })
  } finally {
    importing.value = false
  }
}

// Create from a form or collection
const canPickForms = session.can('system.manage_forms')
const creating = ref<{
  source: FormSummary | null
  names: Record<string, string>
  descriptions: Record<string, string>
  category: string
  tags: string
  include_mode: IncludeMode
  is_library: boolean
} | null>(null)
const createErrors = ref<Record<string, string>>({})
const formSuggestions = ref<FormSummary[]>([])
const includeOptions = computed(() => INCLUDE_MODES.map((v) => ({ value: v, label: t(`building.include.${v}`), disabled: v !== 'structure' && !session.can('system.manage_permissions') })))
async function searchForms(e: { query: string }): Promise<void> {
  formSuggestions.value = (await get<{ data: FormSummary[] }>('/forms', { search: e.query, per_page: 20 })).data
}
function openCreate(): void {
  createErrors.value = {}
  creating.value = { source: null, names: {}, descriptions: {}, category: filters.category ?? '', tags: '', include_mode: 'structure', is_library: true }
}
watch(
  () => creating.value?.source,
  (f) => {
    const c = creating.value
    if (c && f && !Object.values(c.names).some(Boolean)) c.names = { [session.boot?.default_locale ?? 'en']: f.name }
  },
)
async function submitCreate(): Promise<void> {
  const c = creating.value!
  createErrors.value = {}
  try {
    const res = await send<{ data: { uuid: string } }>('post', '/blueprints', {
      source: c.source?.uuid,
      include_mode: c.include_mode,
      name: filledLocales(c.names),
      description: filledLocales(c.descriptions),
      category: c.category.trim() || null,
      tags: c.tags
        .split(',')
        .map((x) => x.trim())
        .filter(Boolean),
      is_library: c.is_library,
    })
    creating.value = null
    await router.push({ name: 'admin.blueprints.detail', params: { blueprint: res.data.uuid } })
  } catch (e) {
    createErrors.value = fieldErrors(e)
    toast.add({ severity: 'error', summary: errorText(e, t('building.save_failed')), life: 6000 })
  }
}
</script>

<template>
  <div class="flex flex-wrap items-center gap-3 mb-4">
    <h1 class="page-title !mb-0 flex-1">{{ t('admin.area.blueprints') }}</h1>
    <label class="p-button p-button-secondary cursor-pointer" :class="{ 'opacity-60 pointer-events-none': importing }" data-testid="bp-import">
      <i class="pi pi-upload me-2" />{{ t('building.blueprints.import') }}
      <input type="file" accept=".json,application/json" class="hidden" @change="importFile" />
    </label>
    <Button v-if="canPickForms" icon="pi pi-plus" :label="t('building.blueprints.new')" data-testid="bp-new" @click="openCreate" />
  </div>
  <p class="text-sm text-muted-color mb-3">{{ t('building.blueprints.hint') }}</p>

  <div class="flex flex-wrap gap-2 mb-4">
    <IconField>
      <InputIcon class="pi pi-search" />
      <InputText v-model="filters.search" :placeholder="t('common.search')" data-testid="bp-search" />
    </IconField>
    <Select v-model="filters.kind" :options="kindOptions" option-label="label" option-value="value" show-clear :placeholder="t('building.kind_label')" class="w-44" />
    <Select v-model="filters.category" :options="categories" show-clear :placeholder="t('building.blueprints.category')" class="w-44" />
    <SelectButton v-model="filters.library" :options="libraryOptions" option-label="label" option-value="value" :allow-empty="false" />
  </div>

  <p v-if="loading" class="text-muted-color"><i class="pi pi-spin pi-spinner me-1" />{{ t('building.loading') }}</p>
  <Message v-else-if="!rows.length" severity="info">{{ t('building.blueprints.empty') }}</Message>
  <div v-else class="grid gap-3 grid-cols-[repeat(auto-fill,minmax(18rem,1fr))]" data-testid="bp-list">
    <RouterLink
      v-for="b in rows"
      :key="b.uuid"
      :to="{ name: 'admin.blueprints.detail', params: { blueprint: b.uuid } }"
      class="p-4 rounded-xl bg-card shadow-sm hover:shadow flex flex-col gap-2 no-underline text-color"
    >
      <div class="flex items-center gap-2">
        <i :class="b.kind === 'collection' ? 'pi pi-table' : 'pi pi-clone'" class="text-primary text-xl" />
        <span class="font-medium flex-1">{{ b.name ?? t('building.blueprints.untitled') }}</span>
        <Tag v-if="b.version" severity="secondary" :value="`v${b.version}`" />
      </div>
      <p v-if="b.description" class="text-sm text-muted-color line-clamp-3">{{ b.description }}</p>
      <div class="flex flex-wrap gap-1 text-xs">
        <Tag :value="t(`building.blueprints.kind.${b.kind}`)" />
        <Tag v-if="b.category" severity="info" :value="b.category" />
        <Tag v-if="b.is_library" severity="success" :value="t('building.blueprints.library')" />
        <Tag v-for="tag in b.tags" :key="tag" severity="secondary" :value="tag" />
      </div>
      <div class="text-xs text-muted-color flex flex-wrap gap-3">
        <span>{{ t('building.blueprints.instances', { n: b.instance_count }) }}</span>
        <span v-if="b.include_mode">{{ t(`building.include.${b.include_mode}`) }}</span>
        <span>{{ formatDateTime(b.updated_at, session.locale) }}</span>
      </div>
    </RouterLink>
  </div>

  <Dialog :visible="!!creating" modal :header="t('building.blueprints.new')" :style="{ width: '42rem' }" @update:visible="(v: boolean) => !v && (creating = null)">
    <form v-if="creating" class="flex flex-col gap-3" @submit.prevent="submitCreate">
      <div class="field">
        <label for="bpn-source">{{ t('building.blueprints.source') }}</label>
        <AutoComplete
          v-model="creating.source"
          input-id="bpn-source"
          :suggestions="formSuggestions"
          option-label="name"
          force-selection
          dropdown
          :placeholder="t('building.blueprints.pick_source')"
          @complete="searchForms"
        >
          <template #option="{ option }">
            <span>{{ option.name }}</span> <span class="text-xs text-muted-color ltr-value ms-2">{{ option.key }}</span>
            <span class="text-xs text-muted-color ms-2">{{ t(`building.kind.${option.kind}`) }}</span>
          </template>
        </AutoComplete>
        <span v-if="createErrors.source" class="field-error">{{ createErrors.source }}</span>
      </div>
      <div class="form-grid">
        <LocaleFields v-model="creating.names" :label="t('building.name')" field="name" :errors="createErrors" id-prefix="bpn-name" />
      </div>
      <div class="form-grid">
        <LocaleFields v-model="creating.descriptions" :label="t('building.description')" field="description" :errors="createErrors" id-prefix="bpn-desc" multiline />
      </div>
      <div class="form-grid">
        <div class="field">
          <label for="bpn-cat">{{ t('building.blueprints.category') }}</label>
          <InputText id="bpn-cat" v-model="creating.category" class="ltr-value" />
          <small class="text-muted-color">{{ t('building.blueprints.category_hint') }}</small>
          <span v-if="createErrors.category" class="field-error">{{ createErrors.category }}</span>
        </div>
        <div class="field">
          <label for="bpn-tags">{{ t('building.blueprints.tags') }}</label>
          <InputText id="bpn-tags" v-model="creating.tags" />
          <small class="text-muted-color">{{ t('building.blueprints.tags_hint') }}</small>
        </div>
        <div class="field">
          <label for="bpn-include">{{ t('building.include_label') }}</label>
          <Select v-model="creating.include_mode" input-id="bpn-include" :options="includeOptions" option-label="label" option-value="value" option-disabled="disabled" />
          <small class="text-muted-color">{{ t(`building.include_hint.${creating.include_mode}`) }}</small>
        </div>
      </div>
      <label class="flex items-center gap-2"><ToggleSwitch v-model="creating.is_library" />{{ t('building.blueprints.in_library') }}</label>
      <div class="flex justify-end gap-2">
        <Button type="button" severity="secondary" :label="t('common.cancel')" @click="creating = null" />
        <Button type="submit" :label="t('building.blueprints.create')" :disabled="!creating.source" data-testid="bp-create" />
      </div>
    </form>
  </Dialog>
</template>
