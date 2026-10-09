<script setup lang="ts">
import AutoComplete from 'primevue/autocomplete'
import Button from 'primevue/button'
import Dialog from 'primevue/dialog'
import InputText from 'primevue/inputtext'
import Message from 'primevue/message'
import Select from 'primevue/select'
import Tab from 'primevue/tab'
import TabList from 'primevue/tablist'
import TabPanel from 'primevue/tabpanel'
import TabPanels from 'primevue/tabpanels'
import Tabs from 'primevue/tabs'
import Tag from 'primevue/tag'
import Textarea from 'primevue/textarea'
import ToggleSwitch from 'primevue/toggleswitch'
import { useConfirm } from 'primevue/useconfirm'
import { useToast } from 'primevue/usetoast'
import { computed, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRoute, useRouter } from 'vue-router'
import { get, http, send } from '@/api/http'
import FormPicker from '@/builder/FormPicker.vue'
import { useSession } from '@/stores/session'
import LocaleFields from './LocaleFields.vue'
import { INCLUDE_MODES, errorText, fieldErrors, filledLocales, formatDateTime, saveBlob, type FormSummary, type IncludeMode } from './shared'

interface Version {
  uuid: string
  version: number
  include_mode: IncludeMode
  changelog: string | null
  created_at: string | null
}
interface Instance {
  uuid: string
  detached: boolean
  include_mode: IncludeMode
  version: number | null
  form: { uuid: string; key: string; name: string | null; state: string } | null
}
interface Blueprint {
  uuid: string
  kind: string
  category: string | null
  tags: string[]
  is_library: boolean
  name: string | null
  description: string | null
  version: number | null
  include_mode: IncludeMode | null
  instance_count: number
  names: Record<string, string>
  descriptions: Record<string, string>
  source: { uuid: string; key: string } | null
  versions: Version[]
  instances: Instance[]
}
interface Change {
  kind: string
  uuid: string | null
  label: string
  action: 'add' | 'update' | 'remove'
  status: 'apply' | 'skip' | 'conflict'
  reason: string | null
}
interface PreviewEntry {
  instance: string
  form: { uuid: string; key: string; name: string | null; state: string }
  from_version: number
  changes: Change[]
}
interface PropagationResult {
  instance: string
  form: string
  status: 'applied' | 'failed'
  applied?: number
  conflicts?: Change[]
  errors?: Record<string, string[]> | unknown[]
}

const { t } = useI18n()
const toast = useToast()
const confirm = useConfirm()
const route = useRoute()
const router = useRouter()
const session = useSession()
const uuid = computed(() => String(route.params.blueprint))
const bp = ref<Blueprint | null>(null)
const tab = ref('overview')
const includeOptions = computed(() => INCLUDE_MODES.map((v) => ({ value: v, label: t(`building.include.${v}`), disabled: v !== 'structure' && !session.can('system.manage_permissions') })))
const canForms = session.can('system.manage_forms')

async function load(): Promise<void> {
  try {
    bp.value = (await get<{ data: Blueprint }>(`/blueprints/${uuid.value}`)).data
  } catch (e) {
    toast.add({ severity: 'error', summary: errorText(e, t('building.load_failed')), life: 6000 })
  }
}
watch(uuid, load, { immediate: true })

// Metadata
const meta = ref<{ names: Record<string, string>; descriptions: Record<string, string>; category: string; tags: string; is_library: boolean } | null>(null)
const metaErrors = ref<Record<string, string>>({})
function editMeta(): void {
  const b = bp.value!
  metaErrors.value = {}
  meta.value = { names: { ...b.names }, descriptions: { ...b.descriptions }, category: b.category ?? '', tags: b.tags.join(', '), is_library: b.is_library }
}
async function saveMeta(): Promise<void> {
  const m = meta.value!
  metaErrors.value = {}
  try {
    await send('patch', `/blueprints/${uuid.value}`, {
      name: filledLocales(m.names),
      description: filledLocales(m.descriptions),
      category: m.category.trim() || null,
      tags: m.tags
        .split(',')
        .map((x) => x.trim())
        .filter(Boolean),
      is_library: m.is_library,
    })
    meta.value = null
    toast.add({ severity: 'success', summary: t('common.saved'), life: 3000 })
    await load()
  } catch (e) {
    metaErrors.value = fieldErrors(e)
    toast.add({ severity: 'error', summary: errorText(e, t('building.save_failed')), life: 6000 })
  }
}

// New version
const newVersion = ref<{ source: FormSummary | { uuid: string; key: string; name: string } | null; include_mode: IncludeMode; changelog: string } | null>(null)
const versionErrors = ref<Record<string, string>>({})
const formSuggestions = ref<FormSummary[]>([])
async function searchForms(e: { query: string }): Promise<void> {
  formSuggestions.value = (await get<{ data: FormSummary[] }>('/forms', { search: e.query, per_page: 20 })).data
}
function openNewVersion(): void {
  const b = bp.value!
  versionErrors.value = {}
  newVersion.value = { source: b.source ? { uuid: b.source.uuid, key: b.source.key, name: b.source.key } : null, include_mode: b.include_mode ?? 'structure', changelog: '' }
}
async function submitVersion(): Promise<void> {
  const v = newVersion.value!
  versionErrors.value = {}
  try {
    const isView = bp.value?.kind === 'view'
    const res = await send<{ data: { version: number } }>('post', `/blueprints/${uuid.value}/versions`, {
      source: isView ? null : (v.source?.uuid ?? null),
      ...(isView ? {} : { include_mode: v.include_mode }),
      changelog: v.changelog.trim() || null,
    })
    newVersion.value = null
    toast.add({ severity: 'success', summary: t('building.blueprints.version_added', { n: res.data.version }), life: 4000 })
    await load()
    preview.value = null
  } catch (e) {
    versionErrors.value = fieldErrors(e)
    toast.add({ severity: 'error', summary: errorText(e, t('building.save_failed')), life: 6000 })
  }
}

// Instantiate
const apps = ref<{ uuid: string; name: string; status: string }[]>([])
const inst = ref<{ application: string | null; form: string | null; key: string; names: Record<string, string>; include_mode: IncludeMode; version: number | null } | null>(null)
const instErrors = ref<Record<string, string>>({})
async function openInstantiate(): Promise<void> {
  instErrors.value = {}
  const b = bp.value!
  if (b.kind !== 'view' && !apps.value.length) apps.value = (await get<{ data: { uuid: string; name: string; status: string }[] }>('/applications')).data
  inst.value = {
    application: apps.value.find((a) => a.status === 'active')?.uuid ?? null,
    form: null,
    key: '',
    names: { ...b.names },
    include_mode: b.include_mode && includeOptions.value.find((o) => o.value === b.include_mode && !o.disabled) ? b.include_mode : 'structure',
    version: b.version,
  }
}
async function submitInstantiate(): Promise<void> {
  const i = inst.value!
  instErrors.value = {}
  try {
    const isView = bp.value?.kind === 'view'
    const res = await send<{ data: { uuid: string; key: string; skipped?: unknown[] } }>('post', `/blueprints/${uuid.value}/instantiate`, {
      ...(isView ? { form: i.form } : { application: i.application, include_mode: i.include_mode }),
      key: i.key,
      name: filledLocales(i.names),
      ...(i.version ? { version: i.version } : {}),
    })
    if (isView) {
      inst.value = null
      toast.add({ severity: 'success', summary: t('building.blueprints.created_view', { key: res.data.key }), life: 6000 })
      await load()
      tab.value = 'instances'
      return
    }
    res.data.skipped ??= []
    inst.value = null
    toast.add({
      severity: res.data.skipped.length ? 'warn' : 'success',
      summary: res.data.skipped.length ? t('building.blueprints.created_with_skips', { key: res.data.key, n: res.data.skipped.length }) : t('building.blueprints.created_form', { key: res.data.key }),
      life: 6000,
    })
    await load()
    tab.value = 'instances'
  } catch (e) {
    instErrors.value = fieldErrors(e)
    toast.add({ severity: 'error', summary: errorText(e, t('building.save_failed')), life: 6000 })
  }
}

// Instances
function detach(i: Instance): void {
  confirm.require({
    message: t('building.blueprints.detach_confirm', { name: i.form?.name ?? i.form?.key ?? '' }),
    header: t('common.confirm'),
    acceptProps: { label: t('building.blueprints.detach'), severity: 'warn' },
    rejectProps: { label: t('common.cancel'), severity: 'secondary' },
    accept: async () => {
      try {
        await send('post', `/blueprint-instances/${i.uuid}/detach`)
        await load()
      } catch (e) {
        toast.add({ severity: 'error', summary: errorText(e, t('building.save_failed')), life: 6000 })
      }
    },
  })
}

// Propagation
const preview = ref<PreviewEntry[] | null>(null)
const results = ref<PropagationResult[] | null>(null)
const loadingPreview = ref(false)
async function loadPreview(): Promise<void> {
  loadingPreview.value = true
  results.value = null
  try {
    preview.value = (await get<{ data: PreviewEntry[] }>(`/blueprints/${uuid.value}/propagation-preview`)).data
  } catch (e) {
    toast.add({ severity: 'error', summary: errorText(e, t('building.load_failed')), life: 6000 })
  } finally {
    loadingPreview.value = false
  }
}
watch(tab, (v) => {
  if (v === 'propagation' && preview.value === null) loadPreview()
})
const counts = (changes: Change[]) => ({
  apply: changes.filter((c) => c.status === 'apply').length,
  skip: changes.filter((c) => c.status === 'skip').length,
  conflict: changes.filter((c) => c.status === 'conflict').length,
})
function propagate(instances?: string[]): void {
  confirm.require({
    message: instances ? t('building.blueprints.propagate_one_confirm') : t('building.blueprints.propagate_all_confirm', { n: preview.value?.length ?? 0 }),
    header: t('building.blueprints.propagate'),
    acceptProps: { label: t('building.blueprints.propagate') },
    rejectProps: { label: t('common.cancel'), severity: 'secondary' },
    accept: async () => {
      try {
        const res = (await send<{ data: PropagationResult[] }>('post', `/blueprints/${uuid.value}/propagate`, instances ? { instances } : {})).data
        await loadPreview()
        results.value = res
        await load()
      } catch (e) {
        toast.add({ severity: 'error', summary: errorText(e, t('building.save_failed')), life: 8000 })
      }
    },
  })
}
const formKeyOf = (formUuid: string) => bp.value?.instances.find((i) => i.form?.uuid === formUuid)?.form?.key ?? formUuid
const errorLines = (r: PropagationResult) =>
  Array.isArray(r.errors) ? r.errors.map((x) => (typeof x === 'string' ? x : JSON.stringify(x))) : Object.entries(r.errors ?? {}).map(([k, v]) => `${k}: ${(v as string[]).join(' ')}`)

// Export and delete
async function exportFile(): Promise<void> {
  try {
    const res = await http.get(`/blueprints/${uuid.value}/export`, { responseType: 'blob' })
    const disposition = String(res.headers['content-disposition'] ?? '')
    const name = /filename="([^"]+)"/.exec(disposition)?.[1] ?? 'blueprint.blueprint.json'
    saveBlob(res.data as Blob, name)
  } catch (e) {
    toast.add({ severity: 'error', summary: errorText(e, t('building.load_failed')), life: 6000 })
  }
}
function destroy(): void {
  confirm.require({
    message: t('building.blueprints.delete_confirm', { name: bp.value?.name ?? '' }),
    header: t('common.confirm'),
    acceptProps: { label: t('common.delete'), severity: 'danger' },
    rejectProps: { label: t('common.cancel'), severity: 'secondary' },
    accept: async () => {
      try {
        await send('delete', `/blueprints/${uuid.value}`)
        await router.push({ name: 'admin.blueprints' })
      } catch (e) {
        toast.add({ severity: 'error', summary: errorText(e, t('building.save_failed')), life: 6000 })
      }
    },
  })
}
const changeSeverity: Record<string, string> = { apply: 'success', skip: 'secondary', conflict: 'danger' }
</script>

<template>
  <div class="flex flex-wrap items-center gap-3 mb-4">
    <RouterLink :to="{ name: 'admin.blueprints' }" class="text-sm"><i class="pi pi-arrow-left rtl:rotate-180 me-1" />{{ t('admin.area.blueprints') }}</RouterLink>
    <h1 class="page-title !mb-0 flex-1">{{ bp?.name ?? t('building.blueprints.untitled') }}</h1>
    <template v-if="bp">
      <Button icon="pi pi-download" severity="secondary" :label="t('building.blueprints.export')" data-testid="bp-export" @click="exportFile" />
      <Button v-if="canForms" icon="pi pi-plus-circle" :label="t('building.blueprints.instantiate')" :disabled="!bp.version" data-testid="bp-instantiate" @click="openInstantiate" />
      <Button icon="pi pi-trash" severity="danger" text :aria-label="t('common.delete')" @click="destroy" />
    </template>
  </div>

  <Tabs v-if="bp" v-model:value="tab">
    <TabList>
      <Tab value="overview">{{ t('building.blueprints.tab_overview') }}</Tab>
      <Tab value="versions">{{ t('building.blueprints.tab_versions') }}</Tab>
      <Tab value="instances">{{ t('building.blueprints.tab_instances') }} ({{ bp.instance_count }})</Tab>
      <Tab value="propagation" data-testid="bp-tab-propagation">{{ t('building.blueprints.tab_propagation') }}</Tab>
    </TabList>
    <TabPanels>
      <TabPanel value="overview">
        <div class="flex flex-col gap-3 max-w-3xl">
          <div class="flex flex-wrap gap-1">
            <Tag :value="t(`building.blueprints.kind.${bp.kind}`)" />
            <Tag v-if="bp.category" severity="info" :value="bp.category" />
            <Tag v-if="bp.is_library" severity="success" :value="t('building.blueprints.library')" />
            <Tag v-for="tag in bp.tags" :key="tag" severity="secondary" :value="tag" />
            <Tag v-if="bp.version" severity="secondary" :value="`v${bp.version}`" />
          </div>
          <dl class="grid grid-cols-[max-content_1fr] gap-x-4 gap-y-2 text-sm">
            <template v-for="l in session.boot?.locales ?? []" :key="l.code">
              <dt class="text-muted-color">{{ t('building.name') }} ({{ l.native_name }})</dt>
              <dd :dir="l.direction">{{ bp.names[l.code] || '—' }}</dd>
              <dt class="text-muted-color">{{ t('building.description') }} ({{ l.native_name }})</dt>
              <dd :dir="l.direction" class="whitespace-pre-wrap">{{ bp.descriptions[l.code] || '—' }}</dd>
            </template>
            <dt class="text-muted-color">{{ t('building.blueprints.source') }}</dt>
            <dd>
              <span v-if="bp.source" class="ltr-value">{{ bp.source.key }}</span
              ><span v-else>{{ t('building.blueprints.no_source') }}</span>
            </dd>
            <dt class="text-muted-color">{{ t('building.include_label') }}</dt>
            <dd>{{ bp.include_mode ? t(`building.include.${bp.include_mode}`) : '—' }}</dd>
          </dl>
          <div><Button icon="pi pi-pencil" severity="secondary" :label="t('common.edit')" data-testid="bp-edit" @click="editMeta" /></div>
        </div>
      </TabPanel>

      <TabPanel value="versions">
        <div class="flex justify-end mb-3">
          <Button icon="pi pi-plus" :label="t('building.blueprints.new_version')" data-testid="bp-new-version" @click="openNewVersion" />
        </div>
        <ol class="flex flex-col gap-2">
          <li v-for="v in bp.versions" :key="v.uuid" class="rounded-lg border border-line p-3">
            <div class="flex flex-wrap items-center gap-2">
              <span class="font-semibold">v{{ v.version }}</span>
              <Tag v-if="v.version === bp.version" severity="success" :value="t('building.blueprints.current')" />
              <Tag severity="secondary" :value="t(`building.include.${v.include_mode}`)" />
              <span class="text-xs text-muted-color ms-auto">{{ formatDateTime(v.created_at, session.locale) }}</span>
            </div>
            <p class="text-sm mt-1 whitespace-pre-wrap">{{ v.changelog || t('building.blueprints.no_changelog') }}</p>
          </li>
        </ol>
      </TabPanel>

      <TabPanel value="instances">
        <p v-if="!bp.instances.length" class="text-muted-color">{{ t('building.blueprints.no_instances') }}</p>
        <div v-else class="rounded-lg border border-line divide-y divide-line">
          <div v-for="i in bp.instances" :key="i.uuid" class="flex flex-wrap items-center gap-3 p-2" :data-testid="`bp-instance-${i.form?.key}`">
            <div class="flex-1 min-w-48">
              <div class="font-medium">{{ i.form?.name ?? i.form?.key ?? t('building.blueprints.form_removed') }}</div>
              <div class="text-xs text-muted-color ltr-value">{{ i.form?.key }}</div>
            </div>
            <Tag v-if="i.form" severity="secondary" :value="t(`building.form_state.${i.form.state}`)" />
            <span class="text-sm">{{ t('building.blueprints.based_on', { n: i.version ?? '—' }) }}</span>
            <Tag v-if="i.version !== null && bp.version !== null && i.version < bp.version && !i.detached" severity="warn" :value="t('building.blueprints.outdated')" />
            <Tag v-if="i.detached" severity="contrast" :value="t('building.blueprints.detached')" />
            <Button
              v-if="i.form && canForms"
              size="small"
              text
              icon="pi pi-pencil"
              :label="t('building.forms.open_builder')"
              @click="router.push({ name: 'admin.forms.builder', params: { form: i.form.uuid } })"
            />
            <Button v-if="!i.detached" size="small" severity="warn" text icon="pi pi-link" :label="t('building.blueprints.detach')" @click="detach(i)" />
          </div>
        </div>
      </TabPanel>

      <TabPanel value="propagation">
        <p class="text-sm text-muted-color mb-3">{{ t('building.blueprints.propagation_hint') }}</p>
        <div class="flex flex-wrap gap-2 mb-3">
          <Button icon="pi pi-refresh" severity="secondary" :label="t('building.refresh')" :loading="loadingPreview" @click="loadPreview" />
          <Button v-if="canForms && preview?.length" icon="pi pi-send" :label="t('building.blueprints.propagate_all')" data-testid="bp-propagate-all" @click="propagate()" />
        </div>
        <Message v-if="results" :severity="results.some((r) => r.status === 'failed') ? 'warn' : 'success'" class="mb-3" data-testid="bp-results">
          <div class="font-semibold mb-1">{{ t('building.blueprints.propagation_results') }}</div>
          <ul class="text-sm list-disc ps-5">
            <li v-for="r in results" :key="r.instance">
              <span class="ltr-value">{{ formKeyOf(r.form) }}</span
              >:
              <template v-if="r.status === 'applied'">{{ t('building.blueprints.result_applied', { n: r.applied ?? 0, c: r.conflicts?.length ?? 0 }) }}</template>
              <template v-else>
                {{ t('building.blueprints.result_failed') }}
                <ul class="list-[circle] ps-5">
                  <li v-for="(line, j) in errorLines(r)" :key="j" class="ltr-value">{{ line }}</li>
                </ul>
              </template>
            </li>
          </ul>
        </Message>
        <p v-if="preview && !preview.length" class="text-muted-color">{{ t('building.blueprints.up_to_date') }}</p>
        <div v-for="p in preview ?? []" :key="p.instance" class="rounded-xl border border-line p-3 mb-3" :data-testid="`bp-preview-${p.form.key}`">
          <div class="flex flex-wrap items-center gap-2 mb-2">
            <span class="font-semibold">{{ p.form.name ?? p.form.key }}</span>
            <span class="text-xs text-muted-color ltr-value">{{ p.form.key }}</span>
            <span class="text-sm">v{{ p.from_version }} → v{{ bp.version }}</span>
            <Tag severity="success" :value="t('building.blueprints.change_counts.apply', { n: counts(p.changes).apply })" />
            <Tag severity="secondary" :value="t('building.blueprints.change_counts.skip', { n: counts(p.changes).skip })" />
            <Tag severity="danger" :value="t('building.blueprints.change_counts.conflict', { n: counts(p.changes).conflict })" />
            <Button v-if="canForms" size="small" class="ms-auto" icon="pi pi-send" :label="t('building.blueprints.propagate')" @click="propagate([p.instance])" />
          </div>
          <p v-if="!p.changes.length" class="text-sm text-muted-color">{{ t('building.blueprints.no_changes') }}</p>
          <table v-else class="min-w-full text-sm">
            <thead>
              <tr>
                <th class="p-1 text-start">{{ t('building.blueprints.change_element') }}</th>
                <th class="p-1 text-start">{{ t('building.blueprints.change_action') }}</th>
                <th class="p-1 text-start">{{ t('building.blueprints.change_outcome') }}</th>
                <th class="p-1 text-start">{{ t('building.blueprints.change_reason') }}</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="(c, i) in p.changes" :key="i" class="border-t border-line">
                <td class="p-1">
                  {{ t(`building.blueprints.element.${c.kind}`) }} <span class="ltr-value text-muted-color">{{ c.label }}</span>
                </td>
                <td class="p-1">{{ t(`building.blueprints.action.${c.action}`) }}</td>
                <td class="p-1"><Tag :severity="changeSeverity[c.status]" :value="t(`building.blueprints.outcome.${c.status}`)" /></td>
                <td class="p-1 text-muted-color">{{ c.reason ? t(`building.blueprints.reason.${c.reason}`) : '—' }}</td>
              </tr>
            </tbody>
          </table>
        </div>
      </TabPanel>
    </TabPanels>
  </Tabs>

  <Dialog :visible="!!meta" modal :header="t('building.blueprints.edit')" :style="{ width: '40rem' }" @update:visible="(v: boolean) => !v && (meta = null)">
    <form v-if="meta" class="flex flex-col gap-3" @submit.prevent="saveMeta">
      <div class="form-grid">
        <LocaleFields v-model="meta.names" :label="t('building.name')" field="name" :errors="metaErrors" id-prefix="bpm-name" />
      </div>
      <div class="form-grid">
        <LocaleFields v-model="meta.descriptions" :label="t('building.description')" field="description" :errors="metaErrors" id-prefix="bpm-desc" multiline />
      </div>
      <div class="form-grid">
        <div class="field">
          <label for="bpm-cat">{{ t('building.blueprints.category') }}</label>
          <InputText id="bpm-cat" v-model="meta.category" class="ltr-value" />
          <span v-if="metaErrors.category" class="field-error">{{ metaErrors.category }}</span>
        </div>
        <div class="field">
          <label for="bpm-tags">{{ t('building.blueprints.tags') }}</label>
          <InputText id="bpm-tags" v-model="meta.tags" />
          <small class="text-muted-color">{{ t('building.blueprints.tags_hint') }}</small>
        </div>
      </div>
      <label class="flex items-center gap-2"><ToggleSwitch v-model="meta.is_library" />{{ t('building.blueprints.in_library') }}</label>
      <div class="flex justify-end gap-2">
        <Button type="button" severity="secondary" :label="t('common.cancel')" @click="meta = null" />
        <Button type="submit" :label="t('common.save')" data-testid="bpm-save" />
      </div>
    </form>
  </Dialog>

  <Dialog :visible="!!newVersion" modal :header="t('building.blueprints.new_version')" :style="{ width: '36rem' }" @update:visible="(v: boolean) => !v && (newVersion = null)">
    <form v-if="newVersion" class="flex flex-col gap-3" @submit.prevent="submitVersion">
      <p v-if="bp?.kind === 'view'" class="text-sm text-muted-color">{{ t('building.blueprints.view_version_hint') }}</p>
      <div v-else class="field">
        <label for="nv-source">{{ t('building.blueprints.source') }}</label>
        <AutoComplete v-if="canForms" v-model="newVersion.source" input-id="nv-source" :suggestions="formSuggestions" option-label="key" force-selection dropdown @complete="searchForms">
          <template #option="{ option }">
            <span>{{ option.name }}</span> <span class="text-xs text-muted-color ltr-value ms-2">{{ option.key }}</span>
          </template>
        </AutoComplete>
        <InputText v-else id="nv-source" :model-value="newVersion.source?.key ?? t('building.blueprints.no_source')" disabled class="ltr-value" />
        <small class="text-muted-color">{{ t('building.blueprints.version_source_hint') }}</small>
        <span v-if="versionErrors.source" class="field-error">{{ versionErrors.source }}</span>
      </div>
      <div v-if="bp?.kind !== 'view'" class="field">
        <label for="nv-include">{{ t('building.include_label') }}</label>
        <Select v-model="newVersion.include_mode" input-id="nv-include" :options="includeOptions" option-label="label" option-value="value" option-disabled="disabled" />
      </div>
      <div class="field">
        <label for="nv-log">{{ t('building.blueprints.changelog') }}</label>
        <Textarea id="nv-log" v-model="newVersion.changelog" rows="4" auto-resize />
        <span v-if="versionErrors.changelog" class="field-error">{{ versionErrors.changelog }}</span>
      </div>
      <div class="flex justify-end gap-2">
        <Button type="button" severity="secondary" :label="t('common.cancel')" @click="newVersion = null" />
        <Button type="submit" :label="t('building.blueprints.publish_version')" data-testid="nv-submit" />
      </div>
    </form>
  </Dialog>

  <Dialog :visible="!!inst" modal :header="t('building.blueprints.instantiate')" :style="{ width: '40rem' }" @update:visible="(v: boolean) => !v && (inst = null)">
    <form v-if="inst && bp" class="flex flex-col gap-3" @submit.prevent="submitInstantiate">
      <div class="form-grid">
        <div v-if="bp.kind === 'view'" class="field">
          <label for="in-form">{{ t('building.blueprints.target_form') }}</label>
          <FormPicker v-model="inst.form" kind="form" input-id="in-form" />
          <span v-if="instErrors.form" class="field-error">{{ instErrors.form }}</span>
        </div>
        <div v-else class="field">
          <label for="in-app">{{ t('building.application') }}</label>
          <Select v-model="inst.application" input-id="in-app" :options="apps" option-label="name" option-value="uuid" data-testid="in-app" />
          <span v-if="instErrors.application" class="field-error">{{ instErrors.application }}</span>
        </div>
        <div class="field">
          <label for="in-key">{{ t('building.key') }}</label>
          <InputText id="in-key" v-model="inst.key" class="ltr-value" data-testid="in-key" />
          <small class="text-muted-color">{{ t('building.forms.key_hint') }}</small>
          <span v-if="instErrors.key" class="field-error">{{ instErrors.key }}</span>
        </div>
        <div class="field">
          <label for="in-version">{{ t('building.blueprints.version') }}</label>
          <Select v-model="inst.version" input-id="in-version" :options="bp.versions" :option-label="(v: Version) => `v${v.version}`" option-value="version" />
        </div>
        <div v-if="bp.kind !== 'view'" class="field">
          <label for="in-include">{{ t('building.include_label') }}</label>
          <Select v-model="inst.include_mode" input-id="in-include" :options="includeOptions" option-label="label" option-value="value" option-disabled="disabled" />
        </div>
      </div>
      <small v-if="bp.kind !== 'view'" class="text-muted-color">{{ t(`building.include_hint.${inst.include_mode}`) }}</small>
      <div class="form-grid">
        <LocaleFields v-model="inst.names" :label="t('building.name')" field="name" :errors="instErrors" id-prefix="in-name" />
      </div>
      <div class="flex justify-end gap-2">
        <Button type="button" severity="secondary" :label="t('common.cancel')" @click="inst = null" />
        <Button type="submit" :label="bp.kind === 'view' ? t('building.blueprints.create_view') : t('building.blueprints.create_form')" data-testid="in-submit" />
      </div>
    </form>
  </Dialog>
</template>
