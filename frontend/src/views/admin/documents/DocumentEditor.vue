<script setup lang="ts">
import Button from 'primevue/button'
import Checkbox from 'primevue/checkbox'
import Dialog from 'primevue/dialog'
import InputNumber from 'primevue/inputnumber'
import InputText from 'primevue/inputtext'
import Message from 'primevue/message'
import Select from 'primevue/select'
import SelectButton from 'primevue/selectbutton'
import { useToast } from 'primevue/usetoast'
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { onBeforeRouteLeave, useRoute, useRouter } from 'vue-router'
import { ApiError, get } from '@/api/http'
import FormPicker from '@/builder/FormPicker.vue'
import I18nInput from '@/builder/I18nInput.vue'
import ConfigField from '@/components/config/ConfigField.vue'
import ConfigSaveBar from '@/components/config/ConfigSaveBar.vue'
import ConfigSection from '@/components/config/ConfigSection.vue'
import SettingSwitch from '@/components/config/SettingSwitch.vue'
import { provideConfigScreen } from '@/components/config/configScreen'
import BlockListEditor from '@/components/templates/BlockListEditor.vue'
import PlaceholderList from '@/components/templates/PlaceholderList.vue'
import TemplateText from '@/components/templates/TemplateText.vue'
import { useSession } from '@/stores/session'
import { errorText } from '../building/shared'
import ErrorList from '../formconfig/ErrorList.vue'
import type { BlockType, Placeholders } from '../notifications/api'
import { documentBlueprintsApi, documentsApi, emptyDesign, fullDesign, type DocFormat, type DocumentDoc, type Paper, type UploadResult } from './api'

/**
 * A document template (specification §4.17). Either a Word file per language
 * with {{ placeholders }} — a table row whose placeholders name a repeater's
 * columns repeats once per row — or a design of blocks laid out on a chosen
 * paper. Either produces Word and, where chosen, PDF. The design's preview
 * shows it for a real record in a chosen language; any saved template can be
 * generated for a record to check the file itself.
 */
const route = useRoute()
const router = useRouter()
const { t } = useI18n()
const toast = useToast()
const session = useSession()
provideConfigScreen()

const uuid = ref(String(route.params.template))
const isNew = computed(() => uuid.value === 'new')
const doc = ref<DocumentDoc | null>(null)
const saved = ref('')
const hash = ref('')
const saving = ref(false)
const errors = ref<Record<string, string>>({})
const placeholders = ref<Placeholders>({ system: [], fields: [], repeaters: [] })
const pdfFromWord = ref(true)
const uploads = ref<Record<string, UploadResult>>({})
const uploading = ref<string | null>(null)
const dirty = computed(() => doc.value !== null && JSON.stringify(doc.value) !== saved.value)
const dl = computed(() => session.boot?.default_locale ?? 'en')
const locales = computed(() => (session.boot?.locales ?? []).map((l) => ({ value: l.code, label: l.native_name })))
const blockTypes: BlockType[] = ['heading', 'text', 'fields', 'table', 'related', 'signature', 'code', 'divider', 'spacer', 'page_break']
const pageNumberOptions = computed(() => (['none', 'page', 'page_of'] as const).map((v) => ({ value: v, label: t(`doc.page_numbers.${v}`) })))
const kinds = computed(() => [
  { value: 'html', label: t('doc.kind_html') },
  { value: 'docx', label: t('doc.kind_docx') },
])
const sizes = ['a4', 'a3', 'a5', 'letter', 'legal'].map((v) => ({ value: v, label: v.length === 2 ? v.toUpperCase() : v }))
const orientations = computed(() => [
  { value: 'portrait', label: t('doc.portrait') },
  { value: 'landscape', label: t('doc.landscape') },
])
const sides = ['top', 'right', 'bottom', 'left'] as const

const defaultPaper = (): Paper => ({ size: 'a4', orientation: 'portrait', margins: { top: 20, right: 20, bottom: 20, left: 20 } })
function empty(): DocumentDoc {
  return { key: '', type: 'html', form: null, name: {}, design: emptyDesign(), files: {}, formats: ['pdf'], paper: defaultPaper(), active: true }
}
function apply(d: DocumentDoc): void {
  doc.value = {
    key: d.key,
    type: d.type,
    form: d.form,
    name: { ...d.name },
    design: fullDesign(d.design),
    files: { ...d.files },
    formats: [...d.formats],
    paper: d.paper,
    active: d.active,
  }
  issues.value = d.issues ?? []
  hash.value = d.hash ?? ''
  saved.value = JSON.stringify(doc.value)
}
const issues = ref<string[]>([])
async function load(): Promise<void> {
  try {
    pdfFromWord.value = (await documentsApi.list()).pdfFromWord
    if (isNew.value) {
      apply(empty())
      saved.value = ''
    } else {
      apply(await documentsApi.load(uuid.value))
    }
  } catch (e) {
    toast.add({ severity: 'error', summary: errorText(e, t('doc.load_failed')), life: 6000 })
  }
}
onMounted(load)
watch(
  () => doc.value?.form,
  async (form) => {
    placeholders.value = await documentsApi.placeholders(form ?? null).catch(() => ({ system: [], fields: [], repeaters: [] }))
  },
)

function toggleFormat(f: DocFormat, on: boolean): void {
  if (!doc.value) return
  doc.value.formats = on ? [...new Set([...doc.value.formats, f])] : doc.value.formats.filter((x) => x !== f)
}
const pdfBlocked = computed(() => doc.value?.type === 'docx' && !pdfFromWord.value)

async function upload(locale: string, e: Event): Promise<void> {
  const input = e.target as HTMLInputElement
  const file = input.files?.[0]
  input.value = ''
  if (!file || !doc.value) return
  uploading.value = locale
  try {
    const r = await documentsApi.upload(file, doc.value.form)
    uploads.value = { ...uploads.value, [locale]: r }
    doc.value.files = { ...doc.value.files, [locale]: { uuid: r.uuid, name: r.name, size: r.size } }
  } catch (err) {
    toast.add({
      severity: 'error',
      summary: err instanceof ApiError ? (Object.values(err.fieldErrors)[0] ?? errorText(err, t('doc.upload_failed'))) : errorText(err, t('doc.upload_failed')),
      life: 8000,
    })
  } finally {
    uploading.value = null
  }
}
function removeFile(locale: string): void {
  if (!doc.value) return
  const files = { ...doc.value.files }
  delete files[locale]
  doc.value.files = files
  const u = { ...uploads.value }
  delete u[locale]
  uploads.value = u
}
const tokenText = (token: string) => `{{ ${token} }}`
const kb = (n: number) => Math.max(1, Math.round(n / 1024))

async function save(): Promise<void> {
  if (!doc.value) return
  saving.value = true
  errors.value = {}
  try {
    const result = isNew.value ? await documentsApi.create(doc.value) : await documentsApi.save(uuid.value, doc.value, hash.value)
    apply(result)
    uploads.value = {}
    toast.add({ severity: 'success', summary: t('workflow.saved'), life: 3000 })
    if (isNew.value && result.uuid) {
      uuid.value = result.uuid
      await router.replace({ name: 'admin.documents.template', params: { template: result.uuid } })
    }
  } catch (e) {
    if (e instanceof ApiError && e.status === 409) {
      toast.add({ severity: 'warn', summary: t('workflow.conflict'), life: 8000 })
      await load()
    } else {
      errors.value = e instanceof ApiError ? e.fieldErrors : {}
      toast.add({ severity: 'error', summary: errorText(e, t('workflow.save_failed')), life: 6000 })
    }
  } finally {
    saving.value = false
  }
}
function discard(): void {
  if (saved.value) doc.value = JSON.parse(saved.value) as DocumentDoc
  errors.value = {}
  uploads.value = {}
}
onBeforeRouteLeave(() => !dirty.value || window.confirm(t('records.leave_unsaved')))
const err = (path: string) => errors.value[path]

// The record the preview and the test document use.
const previewLocale = ref<string>(dl.value)
const previewRecord = ref<string | null>(null)
const records = ref<{ value: string; label: string }[]>([])
const previewUrl = ref('')
const previewError = ref('')
let timer: ReturnType<typeof setTimeout> | null = null
async function searchRecords(q = ''): Promise<void> {
  if (!doc.value?.form) return
  const rows = (await get<{ data: { uuid: string; title: string | null }[] }>(`/r/${doc.value.form}`, { search: q || undefined, per_page: 10 }).catch(() => ({ data: [] }))).data
  records.value = rows.map((r) => ({ value: r.uuid, label: r.title ?? t('runtime.untitled_record') }))
}
watch(
  () => doc.value?.form,
  () => {
    previewRecord.value = null
    void searchRecords()
  },
)
watch(
  [doc, previewLocale, previewRecord],
  () => {
    if (timer) clearTimeout(timer)
    if (doc.value?.type === 'html') timer = setTimeout(renderPreview, 600)
  },
  { deep: true },
)
onBeforeUnmount(() => timer && clearTimeout(timer))
async function renderPreview(): Promise<void> {
  if (!doc.value || doc.value.type !== 'html') return
  try {
    previewUrl.value = await documentsApi.preview(doc.value, previewRecord.value, previewLocale.value)
    previewError.value = ''
  } catch (e) {
    previewError.value = e instanceof ApiError && e.status === 422 ? (Object.values(e.fieldErrors)[0] ?? t('doc.preview_invalid')) : errorText(e, t('doc.preview_failed'))
  }
}
const generateFormat = ref<DocFormat>('pdf')
watch(
  () => doc.value?.formats,
  (f) => {
    if (f?.length && !f.includes(generateFormat.value)) generateFormat.value = f[0]!
  },
  { deep: true },
)
const formatOptions = computed(() => (doc.value?.formats ?? []).map((f) => ({ value: f, label: t(`doc.format_${f}`) })))
const generating = ref(false)
async function generate(): Promise<void> {
  generating.value = true
  try {
    const r = await documentsApi.generate(uuid.value, previewRecord.value, generateFormat.value, previewLocale.value)
    window.location.assign(r.url)
  } catch (e) {
    toast.add({ severity: 'error', summary: errorText(e, t('doc.generate_failed')), life: 8000 })
  } finally {
    generating.value = false
  }
}
const brandAccent = computed(() => session.boot?.branding.primary_color || getComputedStyle(document.documentElement).getPropertyValue('--primary').trim())
// Saving the template as a blueprint, to make templates from it for other forms (specification §4.30).
const saving_bp = ref(false)
const bpOpen = ref(false)
const bpName = ref<Record<string, string>>({})
const bpDescription = ref<Record<string, string>>({})
function openBlueprint(): void {
  bpName.value = { ...(doc.value?.name ?? {}) }
  bpDescription.value = {}
  bpOpen.value = true
}
async function saveBlueprint(): Promise<void> {
  saving_bp.value = true
  try {
    await documentBlueprintsApi.save(uuid.value, bpName.value, bpDescription.value)
    bpOpen.value = false
    toast.add({ severity: 'success', summary: t('doc.blueprint_saved'), life: 4000 })
  } catch (e) {
    toast.add({ severity: 'error', summary: errorText(e, t('doc.blueprint_failed')), life: 6000 })
  } finally {
    saving_bp.value = false
  }
}
function setAccent(v: string): void {
  if (doc.value?.design) doc.value.design.style.accent = v
}
</script>

<template>
  <div class="flex flex-col gap-6" data-testid="document-editor">
    <header class="page-head">
      <div class="flex-1 min-w-0">
        <RouterLink :to="{ name: 'admin.documents' }" class="text-sm text-primary inline-flex items-center gap-1 mb-1">
          <i class="pi pi-arrow-left rtl:rotate-180" aria-hidden="true" />{{ t('admin.area.documents') }}
        </RouterLink>
        <h1 class="page-title !mb-1">{{ isNew ? t('doc.new') : t('doc.edit') }}</h1>
        <p class="m-0 text-sm text-muted-color">{{ t('doc.editor_subtitle') }}</p>
      </div>
      <Button
        v-if="!isNew && session.can('system.manage_blueprints')"
        icon="pi pi-clone"
        :label="t('doc.save_blueprint')"
        size="small"
        outlined
        :disabled="dirty"
        data-testid="doc-save-blueprint"
        @click="openBlueprint"
      />
    </header>
    <Dialog v-model:visible="bpOpen" modal :header="t('doc.save_blueprint')" :style="{ width: '32rem', maxWidth: 'calc(100vw - 2rem)' }" data-testid="doc-blueprint-dialog">
      <div class="flex flex-col gap-4">
        <p class="m-0 text-sm text-muted-color">{{ t('doc.blueprint_hint') }}</p>
        <I18nInput v-model="bpName" :label="t('doc.blueprint_name')" :maxlength="255" />
        <I18nInput v-model="bpDescription" :label="t('doc.blueprint_description')" multiline :rows="2" :maxlength="1000" />
      </div>
      <template #footer>
        <Button :label="t('common.cancel')" text @click="bpOpen = false" />
        <Button :label="t('doc.save_blueprint')" icon="pi pi-check" :loading="saving_bp" :disabled="!bpName[dl]" data-testid="doc-blueprint-save" @click="saveBlueprint" />
      </template>
    </Dialog>

    <div v-if="doc" class="doc-grid">
      <div class="flex flex-col gap-6 min-w-0">
        <Message v-for="(issue, i) in issues" :key="i" severity="warn" data-testid="doc-issue">{{ issue }}</Message>

        <ConfigSection id="doc-basics" :title="t('doc.section.basics')">
          <I18nInput v-model="doc.name" class="w-field-md" :label="t('doc.name')" :maxlength="255" :invalid="!!err(`name.${dl}`)" data-testid="doc-name" />
          <div class="cfg-row">
            <ConfigField :label="t('doc.key')" for="doc-key" width="sm" :hint="t('doc.key_hint')" :error="err('key')" required>
              <InputText id="doc-key" v-model="doc.key" dir="ltr" class="ltr-value" size="small" maxlength="48" data-testid="doc-key" />
            </ConfigField>
            <ConfigField :label="t('doc.form')" for="doc-form" width="md" :hint="t('doc.form_hint')" :error="err('form')">
              <FormPicker v-model="doc.form" input-id="doc-form" />
            </ConfigField>
          </div>
          <ConfigField :label="t('doc.kind')" for="doc-kind" width="lg" :hint="isNew ? t('doc.kind_hint') : t('doc.kind_fixed')" :error="err('type')">
            <SelectButton v-model="doc.type" :options="kinds" option-label="label" option-value="value" :allow-empty="false" :disabled="!isNew" data-testid="doc-kind" />
          </ConfigField>
          <ConfigField :label="t('doc.formats')" for="doc-format-pdf" width="lg" :error="err('formats')">
            <div class="flex flex-wrap gap-4">
              <label v-for="f in ['pdf', 'docx'] as DocFormat[]" :key="f" class="inline-flex items-center gap-2 text-sm">
                <Checkbox
                  :input-id="`doc-format-${f}`"
                  :model-value="doc.formats.includes(f)"
                  binary
                  :disabled="f === 'pdf' && pdfBlocked && !doc.formats.includes('pdf')"
                  :data-testid="`doc-format-${f}`"
                  @update:model-value="(v: boolean) => toggleFormat(f, v)"
                />
                {{ t(`doc.format_${f}`) }}
              </label>
            </div>
            <p v-if="pdfBlocked" class="m-0 mt-1 text-xs text-muted-color">{{ t('doc.no_converter') }}</p>
          </ConfigField>
          <SettingSwitch id="doc-active" v-model="doc.active" :label="t('doc.active')" :description="t('doc.active_desc')" />
        </ConfigSection>

        <template v-if="doc.type === 'docx'">
          <ConfigSection id="doc-files" :title="t('doc.section.files')" :description="t('doc.files_hint', { example: tokenText('customer'), row: tokenText('lines.quantity') })">
            <div v-for="l in locales" :key="l.value" class="doc-file" :data-testid="`doc-file-${l.value}`">
              <div class="flex flex-wrap items-center gap-3">
                <span class="font-medium w-28"> {{ l.label }}<span v-if="l.value === dl" class="text-danger" aria-hidden="true"> *</span> </span>
                <span v-if="doc.files[l.value]" class="text-sm" dir="auto">
                  <i class="pi pi-file-word me-1" aria-hidden="true" />{{ doc.files[l.value]!.name }}
                  <span class="text-muted-color">· {{ t('doc.kb', { n: kb(doc.files[l.value]!.size) }) }}</span>
                </span>
                <span v-else class="text-sm text-muted-color">{{ l.value === dl ? t('doc.file_needed') : t('doc.file_fallback') }}</span>
                <label class="p-button p-button-sm p-button-outlined doc-upload" :class="{ 'p-disabled': uploading !== null }">
                  <i class="pi pi-upload" aria-hidden="true" />
                  <span>{{ uploading === l.value ? t('doc.uploading') : doc.files[l.value] ? t('doc.replace') : t('doc.upload') }}</span>
                  <input
                    type="file"
                    accept=".docx,application/vnd.openxmlformats-officedocument.wordprocessingml.document"
                    class="sr-only"
                    :data-testid="`doc-upload-${l.value}`"
                    @change="(e) => upload(l.value, e)"
                  />
                </label>
                <Button v-if="doc.files[l.value] && l.value !== dl" icon="pi pi-times" text rounded size="small" :aria-label="t('doc.remove_file')" @click="removeFile(l.value)" />
              </div>
              <p v-if="err(`files.${l.value}`)" class="m-0 text-xs text-danger">{{ err(`files.${l.value}`) }}</p>
              <template v-if="uploads[l.value]">
                <p class="m-0 text-xs text-muted-color" data-testid="doc-found">
                  {{ t('doc.found', { n: uploads[l.value]!.placeholders.length }) }}
                </p>
                <Message v-if="uploads[l.value]!.unknown.length" severity="warn" :closable="false">
                  {{ t('doc.unknown') }}
                  <code v-for="u in uploads[l.value]!.unknown" :key="u" class="ltr-value ms-1">{{ tokenText(u) }}</code>
                </Message>
              </template>
            </div>
          </ConfigSection>
          <ConfigSection id="doc-placeholders" :title="t('tpl.section.placeholders')" :description="t('doc.word_placeholders_hint')">
            <PlaceholderList :placeholders="placeholders" repeater-columns />
          </ConfigSection>
        </template>

        <template v-else-if="doc.design">
          <ConfigSection id="doc-paper" :title="t('doc.section.paper')">
            <div class="cfg-row">
              <ConfigField :label="t('doc.size')" for="doc-size" width="xs">
                <Select v-model="doc.paper.size" input-id="doc-size" :options="sizes" option-label="label" option-value="value" size="small" />
              </ConfigField>
              <ConfigField :label="t('doc.orientation')" for="doc-orientation" width="sm">
                <Select v-model="doc.paper.orientation" input-id="doc-orientation" :options="orientations" option-label="label" option-value="value" size="small" />
              </ConfigField>
            </div>
            <div class="cfg-row">
              <ConfigField v-for="s in sides" :key="s" :label="t(`doc.margin_${s}`)" :for="`doc-m-${s}`" width="xs" :error="err(`paper.margins.${s}`)">
                <InputNumber v-model="doc.paper.margins[s]" :input-id="`doc-m-${s}`" :min="0" :max="60" :suffix="` ${t('doc.mm')}`" size="small" fluid />
              </ConfigField>
            </div>
          </ConfigSection>
          <ConfigSection id="doc-page" :title="t('doc.section.page')" :description="t('doc.page_hint')">
            <TemplateText v-model="doc.design.title" class="w-field-lg" :label="t('doc.title')" :form="doc.form" :system="placeholders.system" :maxlength="255" testid="doc-title" />
            <SettingSwitch id="doc-header" v-model="doc.design.header.enabled" :label="t('doc.header')" :description="t('doc.header_desc')" data-testid="doc-header" />
            <div v-if="doc.design.header.enabled" class="doc-sub flex flex-col gap-4">
              <SettingSwitch id="doc-header-logo" v-model="doc.design.header.logo" :label="t('doc.header_logo')" :description="t('doc.header_logo_desc')" />
              <TemplateText
                v-model="doc.design.header.text"
                class="w-field-lg"
                :label="t('doc.header_text')"
                :form="doc.form"
                :system="placeholders.system"
                :maxlength="255"
                testid="doc-header-text"
              />
            </div>
            <SettingSwitch id="doc-footer" v-model="doc.design.footer.enabled" :label="t('doc.footer')" :description="t('doc.footer_desc')" data-testid="doc-footer" />
            <div v-if="doc.design.footer.enabled" class="doc-sub flex flex-col gap-4">
              <TemplateText
                v-model="doc.design.footer.text"
                class="w-field-lg"
                :label="t('doc.footer_text')"
                :form="doc.form"
                :system="placeholders.system"
                :maxlength="255"
                testid="doc-footer-text"
              />
              <ConfigField :label="t('doc.page_numbers_label')" for="doc-page-numbers" width="sm">
                <Select v-model="doc.design.footer.pageNumbers" input-id="doc-page-numbers" :options="pageNumberOptions" option-label="label" option-value="value" size="small" />
              </ConfigField>
            </div>
          </ConfigSection>
          <ConfigSection id="doc-look" :title="t('tpl.section.look')" :default-open="false">
            <ConfigField :label="t('tpl.accent')" for="doc-accent" width="xs" :hint="t('doc.accent_hint')" :error="err('design.style.accent')">
              <div class="flex items-center gap-2">
                <input
                  id="doc-accent"
                  type="color"
                  :value="doc.design.style.accent ?? brandAccent"
                  class="h-9 w-12 rounded border border-line"
                  @input="(e: Event) => setAccent((e.target as HTMLInputElement).value)"
                />
                <Button v-if="doc.design.style.accent" :label="t('tpl.use_brand')" text size="small" @click="doc.design.style.accent = null" />
              </div>
            </ConfigField>
          </ConfigSection>
          <ConfigSection id="doc-blocks" :title="t('tpl.section.content')" :count="doc.design.blocks.length">
            <BlockListEditor v-model="doc.design.blocks" :types="blockTypes" :form="doc.form" :placeholders="placeholders" :errors="errors" :empty-text="t('doc.blocks_empty_text')" />
          </ConfigSection>
        </template>

        <ErrorList :errors="errors" />
        <ConfigSaveBar :dirty="dirty || isNew" :saving="saving" testid="doc" @save="save" @discard="discard" />
      </div>

      <aside class="doc-side" :aria-label="t('doc.try')">
        <h2 class="m-0 text-base font-semibold">{{ doc.type === 'html' ? t('tpl.preview') : t('doc.try') }}</h2>
        <div class="flex flex-col gap-3">
          <ConfigField v-if="doc.form" :label="t('tpl.preview_record')" for="doc-rec" width="md">
            <Select v-model="previewRecord" input-id="doc-rec" :options="records" option-label="label" option-value="value" filter show-clear size="small" @filter="(e) => searchRecords(e.value)" />
          </ConfigField>
          <div class="cfg-row">
            <ConfigField :label="t('tpl.preview_locale')" for="doc-loc" width="xs">
              <Select v-model="previewLocale" input-id="doc-loc" :options="locales" option-label="label" option-value="value" size="small" />
            </ConfigField>
            <ConfigField v-if="!isNew" :label="t('doc.format')" for="doc-gen-format" width="xs">
              <Select v-model="generateFormat" input-id="doc-gen-format" :options="formatOptions" option-label="label" option-value="value" size="small" />
            </ConfigField>
          </div>
          <div v-if="!isNew">
            <Button
              icon="pi pi-download"
              :label="t('doc.generate')"
              size="small"
              outlined
              :loading="generating"
              :disabled="dirty || !doc.formats.length"
              data-testid="doc-generate"
              @click="generate"
            />
            <p class="m-0 mt-1 text-xs text-muted-color">{{ dirty ? t('doc.generate_save_first') : t('doc.generate_hint') }}</p>
          </div>
        </div>
        <template v-if="doc.type === 'html'">
          <Message v-if="previewError" severity="warn">{{ previewError }}</Message>
          <iframe v-if="previewUrl" class="doc-frame" sandbox="" :src="previewUrl" :title="t('tpl.preview')" data-testid="doc-preview" />
        </template>
      </aside>
    </div>
  </div>
</template>

<style scoped>
.doc-grid {
  display: grid;
  gap: 1.5rem;
  grid-template-columns: minmax(0, 1fr);
}
@media (min-width: 1200px) {
  .doc-grid {
    grid-template-columns: minmax(0, 40rem) minmax(0, 1fr);
  }
  .doc-side {
    position: sticky;
    top: 0;
    align-self: start;
  }
}
.doc-side {
  display: flex;
  flex-direction: column;
  gap: 0.75rem;
}
.doc-frame {
  width: 100%;
  min-height: 40rem;
  border: 1px solid var(--border);
  border-radius: 8px;
  background: var(--bg-subtle);
}
.doc-sub {
  padding-inline-start: 1rem;
  border-inline-start: 2px solid var(--border);
}
.doc-file {
  display: flex;
  flex-direction: column;
  gap: 0.375rem;
  padding: 0.625rem 0;
  border-bottom: 1px solid var(--border);
}
.doc-file:last-child {
  border-bottom: 0;
}
.doc-upload {
  display: inline-flex;
  align-items: center;
  gap: 0.375rem;
  cursor: pointer;
}
</style>
