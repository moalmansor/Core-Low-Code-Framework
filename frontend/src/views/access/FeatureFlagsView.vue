<script setup lang="ts">
import Button from 'primevue/button'
import Column from 'primevue/column'
import DataTable from 'primevue/datatable'
import Dialog from 'primevue/dialog'
import InputText from 'primevue/inputtext'
import Select from 'primevue/select'
import Tag from 'primevue/tag'
import { useConfirm } from 'primevue/useconfirm'
import { useToast } from 'primevue/usetoast'
import { computed, onMounted, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { get } from '@/api/http'
import FormPicker from '@/builder/FormPicker.vue'
import I18nInput from '@/builder/I18nInput.vue'
import ConfigField from '@/components/config/ConfigField.vue'
import ConfigSection from '@/components/config/ConfigSection.vue'
import { provideConfigScreen } from '@/components/config/configScreen'
import EmptyState from '@/components/config/EmptyState.vue'
import SettingSwitch from '@/components/config/SettingSwitch.vue'
import SubjectPicker from '@/components/SubjectPicker.vue'
import { humanize } from '@/runtime/i18nText'
import { errorText, fieldErrors } from '../admin/building/shared'
import { flagsApi, type Capability, type FlagDoc, type FlagType } from './api'

/**
 * Feature flags (specification §4.36, ADR-0062): release a form, an action,
 * a page or a capability to a pilot group first. While a flag is on only its
 * audience sees what it releases (everyone when the audience is empty); while
 * it is off, no one does.
 */
provideConfigScreen()
const { t } = useI18n()
const toast = useToast()
const confirm = useConfirm()
const list = ref<FlagDoc[]>([])
const capabilities = ref<Capability[]>([])
const loading = ref(true)
const types = computed(() => (['form', 'action', 'page', 'capability'] as FlagType[]).map((v) => ({ value: v, label: t(`ff.type.${v}`) })))
const pages = ref<{ uuid: string; label: string }[]>([])
const actions = ref<{ uuid: string; label: string }[]>([])

type Subject = { type: string; uuid: string | null } | null
type Editing = { doc: FlagDoc; form: string | null; audience: Subject[] }
const editing = ref<Editing | null>(null)
const errors = ref<Record<string, string>>({})
const saving = ref(false)

async function load(): Promise<void> {
  loading.value = true
  try {
    const r = await flagsApi.list()
    list.value = r.data
    capabilities.value = r.capabilities
  } catch (e) {
    toast.add({ severity: 'error', summary: errorText(e, t('ff.load_failed')), life: 6000 })
  } finally {
    loading.value = false
  }
}
onMounted(load)

function audienceRows(doc: FlagDoc): Subject[] {
  return [
    ...doc.audience.roles.map((uuid) => ({ type: 'role', uuid })),
    ...doc.audience.departments.map((uuid) => ({ type: 'department', uuid })),
    ...doc.audience.users.map((uuid) => ({ type: 'user', uuid })),
  ]
}
function create(): void {
  errors.value = {}
  editing.value = {
    doc: { key: '', type: 'form', target: null, enabled: true, application: null, audience: { roles: [], departments: [], users: [] }, i18n: { description: {} } },
    form: null,
    audience: [],
  }
}
function edit(f: FlagDoc): void {
  errors.value = {}
  const doc = JSON.parse(JSON.stringify(f)) as FlagDoc
  editing.value = { doc, form: f.type === 'form' ? f.target : null, audience: audienceRows(doc) }
}

// Pages are listed once; an action is picked from its form's actions.
watch(
  () => editing.value?.doc.type,
  async (type) => {
    if (type === 'page' && !pages.value.length) {
      const r = await get<{ data: { uuid: string; label?: string; key: string }[] }>('/pages')
      pages.value = r.data.map((p) => ({ uuid: p.uuid, label: p.label ?? humanize(p.key) }))
    }
  },
)
watch(
  () => [editing.value?.doc.type, editing.value?.form] as const,
  async ([type, form]) => {
    actions.value = []
    if (type !== 'action' || !form) return
    const r = await get<{ data: { actions: { uuid: string; key: string; i18n?: { label?: Record<string, string> }; label?: string }[] } }>(`/forms/${form}/actions`)
    actions.value = (r.data.actions ?? []).map((a) => ({ uuid: a.uuid, label: a.label ?? Object.values(a.i18n?.label ?? {})[0] ?? humanize(a.key) }))
  },
)

async function save(): Promise<void> {
  const e = editing.value
  if (!e) return
  saving.value = true
  errors.value = {}
  const pick = (type: string) => e.audience.filter((s): s is { type: string; uuid: string } => !!s && s.type === type && !!s.uuid).map((s) => s.uuid)
  const doc: FlagDoc = {
    ...e.doc,
    target: e.doc.type === 'form' ? e.form : e.doc.target,
    // A capability's flag is known by the capability it releases.
    key: e.doc.type === 'capability' && e.doc.target ? e.doc.target : e.doc.key,
    audience: { roles: pick('role'), departments: pick('department'), users: pick('user') },
  }
  try {
    if (doc.uuid) await flagsApi.save(doc.uuid, doc, doc.hash!)
    else await flagsApi.create(doc)
    editing.value = null
    toast.add({ severity: 'success', summary: t('ff.saved'), life: 3000 })
    await load()
  } catch (err) {
    errors.value = fieldErrors(err)
    toast.add({ severity: 'error', summary: errorText(err, t('ff.save_failed')), life: 8000 })
  } finally {
    saving.value = false
  }
}
function remove(f: FlagDoc): void {
  confirm.require({
    header: t('ff.delete_title'),
    message: t('ff.delete_text'),
    acceptProps: { label: t('ff.delete'), severity: 'danger' },
    rejectProps: { label: t('ff.cancel'), text: true },
    accept: async () => {
      try {
        await flagsApi.remove(f.uuid!)
        editing.value = null
        await load()
      } catch (err) {
        toast.add({ severity: 'error', summary: errorText(err, t('ff.delete_failed')), life: 6000 })
      }
    },
  })
}
const audienceSize = (f: FlagDoc) => f.audience.roles.length + f.audience.departments.length + f.audience.users.length
</script>

<template>
  <div class="flex flex-col gap-4" data-testid="feature-flags">
    <header class="page-head">
      <div class="flex-1 min-w-0">
        <h1 class="page-title !mb-1">{{ t('admin.area.feature_flags') }}</h1>
        <p class="m-0 text-sm text-muted-color max-w-[var(--measure)]">{{ t('ff.subtitle') }}</p>
      </div>
      <Button icon="pi pi-plus" :label="t('ff.new')" size="small" data-testid="flag-new" @click="create" />
    </header>
    <EmptyState v-if="!loading && !list.length" icon="pi pi-flag" :title="t('ff.empty_title')" :description="t('ff.empty_text')" testid="flags-empty">
      <Button icon="pi pi-plus" :label="t('ff.new')" size="small" @click="create" />
    </EmptyState>
    <DataTable v-else :value="list" data-key="uuid" :loading="loading" size="small" class="records-table" row-hover @row-click="(e) => edit(e.data as FlagDoc)">
      <Column :header="t('ff.releases')">
        <template #body="{ data }">
          <div class="font-medium" dir="auto">{{ data.label }}</div>
          <div class="text-xs text-muted-color">{{ t(`ff.type.${data.type}`) }}</div>
        </template>
      </Column>
      <Column :header="t('ff.audience')">
        <template #body="{ data }">{{ audienceSize(data) ? t('ff.audience_n', { n: audienceSize(data) }) : t('ff.everyone') }}</template>
      </Column>
      <Column :header="t('ff.state')">
        <template #body="{ data }"><Tag :severity="data.enabled ? 'success' : 'secondary'" :value="data.enabled ? t('ff.on') : t('ff.off')" /></template>
      </Column>
    </DataTable>
  </div>

  <Dialog :visible="!!editing" modal :header="editing?.doc.uuid ? t('ff.edit') : t('ff.new')" class="w-[42rem] max-w-[95vw]" @update:visible="(v: boolean) => !v && (editing = null)">
    <form v-if="editing" class="flex flex-col gap-4" data-testid="flag-form" @submit.prevent="save">
      <ConfigSection id="ff-what" :title="t('ff.section.what')">
        <div class="cfg-row">
          <ConfigField :label="t('ff.type_label')" for="ff-type" width="sm" :error="errors.type">
            <Select v-model="editing.doc.type" input-id="ff-type" :options="types" option-label="label" option-value="value" size="small" :disabled="!!editing.doc.uuid" data-testid="flag-type" />
          </ConfigField>
          <ConfigField v-if="editing.doc.type !== 'capability'" :label="t('ff.key')" for="ff-key" width="sm" :hint="t('ff.key_hint')" :error="errors.key">
            <InputText id="ff-key" v-model="editing.doc.key" class="ltr-value" size="small" data-testid="flag-key" />
          </ConfigField>
        </div>
        <ConfigField
          v-if="editing.doc.type === 'form' || editing.doc.type === 'action'"
          :label="t('ff.form')"
          for="ff-form"
          width="lg"
          :error="editing.doc.type === 'form' ? errors.target : undefined"
        >
          <FormPicker v-model="editing.form" input-id="ff-form" data-testid="flag-form-picker" />
        </ConfigField>
        <ConfigField v-if="editing.doc.type === 'action'" :label="t('ff.action')" for="ff-action" width="lg" :error="errors.target">
          <Select v-model="editing.doc.target" input-id="ff-action" :options="actions" option-label="label" option-value="uuid" size="small" :disabled="!editing.form" />
        </ConfigField>
        <ConfigField v-if="editing.doc.type === 'page'" :label="t('ff.page')" for="ff-page" width="lg" :error="errors.target">
          <Select v-model="editing.doc.target" input-id="ff-page" :options="pages" option-label="label" option-value="uuid" filter size="small" />
        </ConfigField>
        <ConfigField v-if="editing.doc.type === 'capability'" :label="t('ff.capability')" for="ff-capability" width="lg" :error="errors.target">
          <Select v-model="editing.doc.target" input-id="ff-capability" :options="capabilities" option-label="label" option-value="key" size="small" data-testid="flag-capability" />
        </ConfigField>
        <I18nInput v-model="editing.doc.i18n.description" :label="t('ff.description')" :maxlength="500" />
      </ConfigSection>
      <ConfigSection id="ff-who" :title="t('ff.section.who')">
        <p class="m-0 text-sm text-muted-color">{{ t('ff.audience_hint') }}</p>
        <div v-for="(_, i) in editing.audience" :key="i" class="flex items-center gap-2">
          <SubjectPicker v-model="editing.audience[i]" :types="['role', 'department', 'user']" :input-id="`ff-subject-${i}`" class="flex-1" />
          <Button type="button" icon="pi pi-times" text severity="secondary" :aria-label="t('ff.remove_subject')" @click="editing.audience.splice(i, 1)" />
        </div>
        <small v-if="errors['audience.roles'] || errors['audience.departments'] || errors['audience.users']" class="text-danger">
          {{ errors['audience.roles'] ?? errors['audience.departments'] ?? errors['audience.users'] }}
        </small>
        <Button
          type="button"
          icon="pi pi-plus"
          :label="t('ff.add_subject')"
          text
          size="small"
          class="self-start"
          data-testid="flag-add-subject"
          @click="editing.audience.push({ type: 'role', uuid: null })"
        />
        <SettingSwitch id="ff-enabled" v-model="editing.doc.enabled" :label="t('ff.enabled')" :description="t('ff.enabled_desc')" />
      </ConfigSection>
      <div class="flex flex-wrap justify-end gap-2">
        <Button v-if="editing.doc.uuid" type="button" severity="danger" text icon="pi pi-trash" :label="t('ff.delete')" class="me-auto" @click="remove(editing.doc)" />
        <Button type="button" severity="secondary" :label="t('ff.cancel')" @click="editing = null" />
        <Button type="submit" :label="t('ff.save')" :loading="saving" data-testid="flag-save" />
      </div>
    </form>
  </Dialog>
</template>
