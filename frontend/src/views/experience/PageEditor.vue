<script setup lang="ts">
import Button from 'primevue/button'
import InputText from 'primevue/inputtext'
import Message from 'primevue/message'
import Select from 'primevue/select'
import { useConfirm } from 'primevue/useconfirm'
import { useToast } from 'primevue/usetoast'
import { computed, onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { onBeforeRouteLeave, useRoute, useRouter } from 'vue-router'
import { get } from '@/api/http'
import I18nInput from '@/builder/I18nInput.vue'
import ConfigField from '@/components/config/ConfigField.vue'
import ConfigSaveBar from '@/components/config/ConfigSaveBar.vue'
import ConfigSection from '@/components/config/ConfigSection.vue'
import SettingSwitch from '@/components/config/SettingSwitch.vue'
import { provideConfigScreen } from '@/components/config/configScreen'
import RichTextInput from '@/runtime/fields/RichTextInput.vue'
import { useSession } from '@/stores/session'
import { errorText, fetchFormOptions, fieldErrors, type FormOption } from '../admin/building/shared'
import { PAGE_TYPES, pagesApi, type PageDoc } from './api'

/**
 * The page editor (specification §4.29): what the page shows, its name and
 * content in each language, and whether it is published. Who may open it is
 * granted on the page's own permission in Roles & Permissions.
 */
provideConfigScreen()
const { t } = useI18n()
const toast = useToast()
const confirm = useConfirm()
const route = useRoute()
const router = useRouter()
const session = useSession()
const app = computed(() => String(route.params.application))
const isNew = computed(() => route.params.page === 'new')
const blank = (): PageDoc => ({ key: '', type: 'content', application: app.value, url: null, dashboard: null, report: null, form: null, i18n: { name: {}, content: {} }, published: false })
const doc = ref<PageDoc>(blank())
const loaded = ref(false)
const saved = ref('')
const saving = ref(false)
const errors = ref<Record<string, string>>({})
const forms = ref<FormOption[]>([])
type Option = { uuid: string; label: string }
/** Null when the person may not list reports and dashboards. */
const reports = ref<Option[] | null>(null)
const dashboards = ref<Option[] | null>(null)
const locales = computed(() => session.boot?.locales ?? [])

onMounted(async () => {
  try {
    const canReports = session.can('system.manage_reports')
    const list = (path: string) => get<{ data: Option[] }>(path).then((r) => r.data.map((x) => ({ uuid: x.uuid, label: x.label })))
    const [d, f, r, ds] = await Promise.all([
      isNew.value ? Promise.resolve(blank()) : pagesApi.load(String(route.params.page)),
      fetchFormOptions(),
      canReports ? list('/reports') : Promise.resolve(null),
      canReports ? list('/dashboards') : Promise.resolve(null),
    ])
    doc.value = d
    forms.value = f
    reports.value = r
    dashboards.value = ds
    saved.value = JSON.stringify(d)
    loaded.value = true
  } catch (e) {
    toast.add({ severity: 'error', summary: errorText(e, t('xp.load_failed')), life: 6000 })
  }
})
const dirty = computed(() => loaded.value && JSON.stringify(doc.value) !== saved.value)
onBeforeRouteLeave(() => !dirty.value || window.confirm(t('records.leave_unsaved')))
const err = (k: string) => errors.value[k]
const types = computed(() => PAGE_TYPES.map((v) => ({ value: v, label: t(`xp.type.${v}`) })))
/** The dashboard or report the page shows, whichever its type is. */
const objectOptions = computed(() => (doc.value.type === 'dashboard' ? dashboards.value : reports.value))
const objectTarget = computed<string | null>({
  get: () => (doc.value.type === 'dashboard' ? doc.value.dashboard : doc.value.type === 'report' ? doc.value.report : null),
  set: (v) => {
    if (doc.value.type === 'dashboard') doc.value.dashboard = v
    else if (doc.value.type === 'report') doc.value.report = v
  },
})
const formOptions = computed(() => forms.value.map((f) => ({ uuid: f.uuid, label: `${f.name} (${f.key})` })).sort((a, b) => a.label.localeCompare(b.label)))

async function save(): Promise<void> {
  saving.value = true
  errors.value = {}
  try {
    const out = isNew.value ? await pagesApi.create(doc.value) : await pagesApi.save(doc.value.uuid!, doc.value, doc.value.hash!)
    doc.value = out
    saved.value = JSON.stringify(out)
    toast.add({ severity: 'success', summary: t('xp.saved'), life: 3000 })
    if (isNew.value) await router.replace({ name: 'admin.applications.page', params: { application: app.value, page: out.uuid } })
  } catch (e) {
    errors.value = fieldErrors(e)
    toast.add({ severity: 'error', summary: errorText(e, t('xp.save_failed')), life: 8000 })
  } finally {
    saving.value = false
  }
}
function discard(): void {
  doc.value = JSON.parse(saved.value) as PageDoc
  errors.value = {}
}
function remove(): void {
  confirm.require({
    header: t('xp.delete_page_title'),
    message: t('xp.delete_page_text'),
    acceptProps: { label: t('xp.delete'), severity: 'danger' },
    rejectProps: { label: t('xp.cancel'), text: true },
    accept: async () => {
      try {
        await pagesApi.remove(doc.value.uuid!)
        saved.value = JSON.stringify(doc.value)
        await router.push({ name: 'admin.applications.pages', params: { application: app.value } })
      } catch (e) {
        toast.add({ severity: 'error', summary: errorText(e, t('xp.delete_failed')), life: 6000 })
      }
    },
  })
}
const contentOf = (code: string): string => doc.value.i18n.content[code] ?? ''
function setContent(code: string, html: unknown): void {
  const next = { ...doc.value.i18n.content }
  if (typeof html === 'string' && html.trim() !== '') next[code] = html
  else delete next[code]
  doc.value.i18n.content = next
}
</script>

<template>
  <div class="flex flex-col gap-6 max-w-[72rem]" data-testid="page-editor">
    <header class="page-head">
      <div class="flex-1 min-w-0">
        <RouterLink :to="{ name: 'admin.applications.pages', params: { application: app } }" class="text-sm text-primary inline-flex items-center gap-1 mb-1">
          <i class="pi pi-arrow-left rtl:rotate-180" aria-hidden="true" />{{ t('xp.pages') }}
        </RouterLink>
        <h1 class="page-title !mb-1" dir="auto">{{ isNew ? t('xp.new_page') : (doc.label ?? t('xp.page')) }}</h1>
        <p class="m-0 text-sm text-muted-color">{{ t('xp.page_subtitle') }}</p>
      </div>
      <div class="flex items-center gap-2">
        <Button v-if="!isNew && doc.published" icon="pi pi-eye" :label="t('xp.open')" size="small" text @click="router.push({ name: 'pages.view', params: { page: doc.uuid } })" />
        <Button v-if="!isNew" icon="pi pi-trash" :label="t('xp.delete')" size="small" severity="danger" text data-testid="page-delete" @click="remove" />
      </div>
    </header>

    <template v-if="loaded">
      <ConfigSection id="page-basics" :title="t('xp.section.basics')">
        <div class="cfg-row">
          <I18nInput v-model="doc.i18n.name" class="w-field-md" :label="t('xp.name')" :maxlength="255" :invalid="!!err('i18n.name')" />
          <ConfigField :label="t('xp.key')" for="page-key" width="sm" :hint="t('xp.key_hint')" :error="err('key')" required>
            <InputText id="page-key" v-model="doc.key" dir="ltr" class="ltr-value" size="small" maxlength="48" :disabled="!isNew" data-testid="page-key" />
          </ConfigField>
        </div>
        <small v-if="err('i18n.name')" class="text-danger">{{ err('i18n.name') }}</small>
        <SettingSwitch id="page-published" v-model="doc.published" :label="t('xp.published')" :description="t('xp.published_desc')" />
      </ConfigSection>

      <ConfigSection id="page-shows" :title="t('xp.section.shows')">
        <ConfigField :label="t('xp.shows')" for="page-type" width="md" :error="err('type')">
          <Select v-model="doc.type" input-id="page-type" :options="types" option-label="label" option-value="value" size="small" data-testid="page-type" />
        </ConfigField>
        <template v-if="doc.type === 'content'">
          <div v-for="l in locales" :key="l.code" class="field" :data-testid="`page-content-${l.code}`">
            <label :for="`page-content-${l.code}`">{{ t('xp.content_in', { language: l.native_name }) }}</label>
            <RichTextInput :input-id="`page-content-${l.code}`" :model-value="contentOf(l.code)" :dir="l.direction" @update:model-value="setContent(l.code, $event)" />
          </div>
          <small v-if="err('i18n.content')" class="text-danger">{{ err('i18n.content') }}</small>
          <small class="text-muted-color">{{ t('xp.content_hint') }}</small>
        </template>
        <ConfigField v-if="doc.type === 'link'" :label="t('xp.url')" for="page-url" width="lg" :hint="t('xp.url_hint')" :error="err('url')" required>
          <InputText id="page-url" v-model="doc.url" dir="ltr" class="ltr-value" size="small" placeholder="https://… /…" data-testid="page-url" />
        </ConfigField>
        <template v-if="doc.type === 'dashboard' || doc.type === 'report'">
          <Message v-if="objectOptions === null" severity="secondary" size="small" :closable="false">{{ t('xp.reports_unavailable') }}</Message>
          <ConfigField v-else :label="t(`xp.type.${doc.type}`)" :for="`page-${doc.type}`" width="md" :error="err(doc.type)" required>
            <Select
              v-model="objectTarget"
              :input-id="`page-${doc.type}`"
              :options="objectOptions"
              option-label="label"
              option-value="uuid"
              filter
              size="small"
              :placeholder="t('xp.choose')"
              :data-testid="`page-${doc.type}`"
            />
          </ConfigField>
        </template>
        <ConfigField v-else-if="doc.type === 'form_host'" :label="t('xp.type.form_host')" for="page-form" width="md" :hint="t('xp.form_host_hint')" :error="err('form')" required>
          <Select v-model="doc.form" input-id="page-form" :options="formOptions" option-label="label" option-value="uuid" filter size="small" :placeholder="t('xp.choose')" data-testid="page-form" />
        </ConfigField>
      </ConfigSection>

      <p v-if="!isNew && doc.permission" class="m-0 text-xs text-muted-color">
        {{ t('xp.page_access_hint') }} <span class="ltr-value" dir="ltr">{{ doc.permission }}</span>
      </p>
      <ConfigSaveBar :dirty="dirty || isNew" :saving="saving" testid="page" @save="save" @discard="discard" />
    </template>
  </div>
</template>
