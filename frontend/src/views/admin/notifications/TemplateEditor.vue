<script setup lang="ts">
import Button from 'primevue/button'
import InputText from 'primevue/inputtext'
import Message from 'primevue/message'
import MultiSelect from 'primevue/multiselect'
import Select from 'primevue/select'
import { useToast } from 'primevue/usetoast'
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { onBeforeRouteLeave, useRoute, useRouter } from 'vue-router'
import { ApiError, get } from '@/api/http'
import ExpressionInput from '@/builder/conditions/ExpressionInput.vue'
import FormPicker from '@/builder/FormPicker.vue'
import I18nInput from '@/builder/I18nInput.vue'
import ConfigField from '@/components/config/ConfigField.vue'
import ConfigItem from '@/components/config/ConfigItem.vue'
import ConfigSaveBar from '@/components/config/ConfigSaveBar.vue'
import ConfigSection from '@/components/config/ConfigSection.vue'
import EmptyState from '@/components/config/EmptyState.vue'
import SettingSwitch from '@/components/config/SettingSwitch.vue'
import { provideConfigScreen } from '@/components/config/configScreen'
import UserPicker from '@/components/UserPicker.vue'
import { useSession } from '@/stores/session'
import { errorText } from '../building/shared'
import ErrorList from '../formconfig/ErrorList.vue'
import { newUuid } from '../formconfig/api'
import { templatesApi, type BlockType, type Placeholders, type TemplateBlock, type TemplateDoc } from './api'
import FormScope from './FormScope.vue'

/**
 * The email template designer (specification §4.16): basics, subject and
 * preheader per language, the look (accent colour, logo), and the content as
 * blocks — heading, text, button, field list, table of a repeater, divider,
 * spacer — each shown always or only when a condition holds. Placeholders
 * such as {{ customer.name }} insert values. The preview on the side renders
 * the template for a real record, as a chosen recipient, in a chosen
 * language, exactly as the server will send it.
 */
const route = useRoute()
const router = useRouter()
const { t } = useI18n()
const toast = useToast()
const session = useSession()
provideConfigScreen()

const uuid = ref(String(route.params.template))
const isNew = computed(() => uuid.value === 'new')
const doc = ref<TemplateDoc | null>(null)
const saved = ref('')
const hash = ref('')
const saving = ref(false)
const errors = ref<Record<string, string>>({})
const placeholders = ref<Placeholders>({ system: [], fields: [], repeaters: [] })
const dirty = computed(() => doc.value !== null && JSON.stringify(doc.value) !== saved.value)
const dl = computed(() => session.boot?.default_locale ?? 'en')
const locales = computed(() => (session.boot?.locales ?? []).map((l) => ({ value: l.code, label: l.native_name })))

const blockTypes = computed(() => (['heading', 'text', 'button', 'fields', 'table', 'divider', 'spacer'] as const).map((v) => ({ value: v, label: t(`tpl.block.${v}`) })))
const newBlockType = ref<BlockType>('text')
const fieldTokens = computed(() => [...placeholders.value.fields, ...placeholders.value.system.filter((s) => ['@record_number', '@status', '@created_at', '@updated_at'].includes(s.token))])
const allTokens = computed(() => [...placeholders.value.system, ...placeholders.value.fields])
const repeaterOptions = computed(() => placeholders.value.repeaters.map((r) => ({ value: r.key, label: r.label })))
const columnsOf = (group: string | undefined) => placeholders.value.repeaters.find((r) => r.key === group)?.columns.map((c) => ({ value: c.key, label: c.label })) ?? []

function empty(): TemplateDoc {
  return { key: '', form: null, design: { style: { accent: null, logo: true }, blocks: [] }, subject: {}, preheader: {}, active: true }
}
function apply(d: TemplateDoc): void {
  doc.value = { key: d.key, form: d.form, design: d.design, subject: { ...d.subject }, preheader: { ...d.preheader }, active: d.active }
  hash.value = d.hash ?? ''
  saved.value = JSON.stringify(doc.value)
}
async function load(): Promise<void> {
  try {
    if (isNew.value) {
      apply(empty())
      saved.value = ''
    } else {
      apply(await templatesApi.load(uuid.value))
    }
  } catch (e) {
    toast.add({ severity: 'error', summary: errorText(e, t('tpl.load_failed')), life: 6000 })
  }
}
onMounted(load)
watch(
  () => doc.value?.form,
  async (form) => {
    placeholders.value = await templatesApi.placeholders(form ?? null).catch(() => ({ system: [], fields: [], repeaters: [] }))
  },
)

function addBlock(): void {
  const type = newBlockType.value
  const b: TemplateBlock = { uuid: newUuid(), type, condition: null }
  if (type === 'heading' || type === 'text') b.text = {}
  if (type === 'button') Object.assign(b, { label: {}, link: doc.value?.form ? 'record' : 'url', url: '' })
  if (type === 'fields') b.fields = []
  if (type === 'table') Object.assign(b, { group: placeholders.value.repeaters[0]?.key ?? '', columns: [] })
  doc.value?.design.blocks.push(b)
}
function move(i: number, d: -1 | 1): void {
  const list = doc.value!.design.blocks
  const j = i + d
  if (j < 0 || j >= list.length) return
  ;[list[i], list[j]] = [list[j]!, list[i]!]
}
async function copyToken(token: string): Promise<void> {
  try {
    await navigator.clipboard.writeText(`{{ ${token} }}`)
    toast.add({ severity: 'info', summary: t('tpl.copied'), life: 2500 })
  } catch {
    toast.add({ severity: 'warn', summary: t('tpl.copy_manually', { text: `{{ ${token} }}` }), life: 6000 })
  }
}

async function save(): Promise<void> {
  if (!doc.value) return
  saving.value = true
  errors.value = {}
  try {
    const result = isNew.value ? await templatesApi.create(doc.value) : await templatesApi.save(uuid.value, doc.value, hash.value)
    apply(result)
    toast.add({ severity: 'success', summary: t('workflow.saved'), life: 3000 })
    if (isNew.value && result.uuid) {
      uuid.value = result.uuid
      await router.replace({ name: 'admin.notifications.template', params: { template: result.uuid } })
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
  if (saved.value) doc.value = JSON.parse(saved.value) as TemplateDoc
  errors.value = {}
}
onBeforeRouteLeave(() => !dirty.value || window.confirm(t('records.leave_unsaved')))
const err = (path: string) => errors.value[path]

// Preview: a record of the form, as a recipient, in a language.
const previewLocale = ref<string>(dl.value)
const previewRecord = ref<string | null>(null)
const previewUser = ref<{ uuid: string; name: string; email: string } | null>(null)
const records = ref<{ value: string; label: string }[]>([])
const preview = ref<{ subject: string; html: string; url: string } | null>(null)
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
  [doc, previewLocale, previewRecord, previewUser],
  () => {
    if (timer) clearTimeout(timer)
    timer = setTimeout(renderPreview, 600)
  },
  { deep: true },
)
onBeforeUnmount(() => timer && clearTimeout(timer))
async function renderPreview(): Promise<void> {
  if (!doc.value) return
  try {
    preview.value = await templatesApi.preview(doc.value, previewRecord.value, previewUser.value?.uuid ?? null, previewLocale.value)
    previewError.value = ''
  } catch (e) {
    previewError.value = e instanceof ApiError && e.status === 422 ? (Object.values(e.fieldErrors)[0] ?? t('tpl.preview_invalid')) : errorText(e, t('tpl.preview_failed'))
  }
}
async function testSend(): Promise<void> {
  try {
    const r = await templatesApi.test(uuid.value, previewRecord.value, previewLocale.value)
    toast.add({ severity: 'success', summary: t('tpl.test_sent', { to: r.to }), life: 5000 })
  } catch (e) {
    toast.add({ severity: 'error', summary: errorText(e, t('tpl.test_failed')), life: 6000 })
  }
}
/** What the email uses without its own accent: the brand's primary colour (§4.16). */
const brandAccent = computed(() => session.boot?.branding.primary_color || getComputedStyle(document.documentElement).getPropertyValue('--primary').trim())
function setAccent(v: string): void {
  if (doc.value) doc.value.design.style.accent = v
}
const urlPlaceholder = 'https://'
const linkOptions = computed(() => [
  { value: 'record', label: t('tpl.link_record') },
  { value: 'url', label: t('tpl.link_url') },
])
const tokenText = (token: string) => `{{ ${token} }}`
const blockTitle = (b: TemplateBlock, i: number) => `${i + 1}. ${t(`tpl.block.${b.type}`)}`
const blockSummary = (b: TemplateBlock) => (b.condition ? t('tpl.conditional') : undefined)
</script>

<template>
  <div class="flex flex-col gap-6" data-testid="template-editor">
    <header class="flex flex-wrap items-start gap-3">
      <div class="flex-1 min-w-0">
        <RouterLink :to="{ name: 'admin.notifications' }" class="text-sm text-primary inline-flex items-center gap-1 mb-1">
          <i class="pi pi-arrow-left rtl:rotate-180" aria-hidden="true" />{{ t('admin.area.notifications') }}
        </RouterLink>
        <h1 class="page-title !mb-1">{{ isNew ? t('tpl.new') : t('tpl.edit') }}</h1>
        <p class="m-0 text-sm text-muted-color">{{ t('tpl.editor_subtitle') }}</p>
      </div>
      <Button v-if="!isNew" icon="pi pi-send" :label="t('tpl.test')" size="small" outlined :disabled="dirty" data-testid="tpl-test" @click="testSend" />
    </header>

    <div v-if="doc" class="tpl-grid">
      <div class="flex flex-col gap-6 min-w-0">
        <ConfigSection id="tpl-basics" :title="t('tpl.section.basics')">
          <div class="cfg-row">
            <ConfigField :label="t('tpl.key')" for="tpl-key" width="sm" :hint="t('tpl.key_hint')" :error="err('key')" required>
              <InputText id="tpl-key" v-model="doc.key" dir="ltr" class="ltr-value" size="small" maxlength="48" data-testid="tpl-key" />
            </ConfigField>
            <ConfigField :label="t('tpl.form')" for="tpl-form" width="md" :hint="t('tpl.form_hint')" :error="err('form')">
              <FormPicker v-model="doc.form" input-id="tpl-form" />
            </ConfigField>
          </div>
          <SettingSwitch id="tpl-active" v-model="doc.active" :label="t('tpl.active')" :description="t('tpl.active_desc')" />
        </ConfigSection>

        <ConfigSection id="tpl-subject" :title="t('tpl.section.subject')">
          <I18nInput v-model="doc.subject" class="w-field-lg" :label="t('tpl.subject')" :maxlength="255" :invalid="!!err(`subject.${dl}`)" data-testid="tpl-subject" />
          <I18nInput v-model="doc.preheader" class="w-field-lg" :label="t('tpl.preheader')" :maxlength="255" />
          <p class="m-0 text-xs text-muted-color">{{ t('tpl.preheader_hint') }}</p>
        </ConfigSection>

        <ConfigSection id="tpl-look" :title="t('tpl.section.look')" :default-open="false">
          <ConfigField :label="t('tpl.accent')" for="tpl-accent" width="xs" :hint="t('tpl.accent_hint')" :error="err('design.style.accent')">
            <div class="flex items-center gap-2">
              <input
                id="tpl-accent"
                type="color"
                :value="doc.design.style.accent ?? brandAccent"
                class="h-9 w-12 rounded border border-line"
                @input="(e: Event) => setAccent((e.target as HTMLInputElement).value)"
              />
              <Button v-if="doc.design.style.accent" :label="t('tpl.use_brand')" text size="small" @click="doc.design.style.accent = null" />
            </div>
          </ConfigField>
          <SettingSwitch id="tpl-logo" v-model="doc.design.style.logo" :label="t('tpl.logo')" :description="t('tpl.logo_desc')" />
        </ConfigSection>

        <ConfigSection id="tpl-blocks" :title="t('tpl.section.content')" :count="doc.design.blocks.length">
          <EmptyState v-if="!doc.design.blocks.length" icon="pi pi-align-left" :title="t('tpl.blocks_empty_title')" :description="t('tpl.blocks_empty_text')" />
          <FormScope :key="doc.form ?? 'none'" v-slot="{ scope }" :form="doc.form">
            <ConfigItem
              v-for="(b, i) in doc.design.blocks"
              :key="b.uuid"
              :title="blockTitle(b, i)"
              :subtitle="blockSummary(b)"
              :index="i"
              :count="doc.design.blocks.length"
              movable
              :invalid="Object.keys(errors).some((k) => k.startsWith(`design.blocks.${i}.`))"
              :testid="`tpl-block-${i}`"
              @move="(d: -1 | 1) => move(i, d)"
              @remove="doc!.design.blocks.splice(i, 1)"
            >
              <div class="flex flex-col gap-4">
                <I18nInput v-if="b.type === 'heading'" v-model="b.text" class="w-field-lg" :label="t('tpl.heading_text')" :maxlength="255" :invalid="!!err(`design.blocks.${i}.text.${dl}`)" />
                <I18nInput
                  v-if="b.type === 'text'"
                  v-model="b.text"
                  class="w-field-lg"
                  multiline
                  :rows="5"
                  :label="t('tpl.text')"
                  :maxlength="5000"
                  :invalid="!!err(`design.blocks.${i}.text.${dl}`)"
                />
                <p v-if="b.type === 'text'" class="m-0 text-xs text-muted-color">{{ t('tpl.text_hint') }}</p>
                <template v-if="b.type === 'button'">
                  <I18nInput v-model="b.label" class="w-field-md" :label="t('tpl.button_label')" :maxlength="120" />
                  <ConfigField :label="t('tpl.button_link')" :for="`tpl-link-${i}`" width="sm">
                    <Select v-model="b.link" :input-id="`tpl-link-${i}`" :options="linkOptions" option-label="label" option-value="value" size="small" />
                  </ConfigField>
                  <ConfigField v-if="b.link === 'url'" :label="t('tpl.url')" :for="`tpl-url-${i}`" width="lg" :error="err(`design.blocks.${i}.url`)">
                    <InputText :id="`tpl-url-${i}`" v-model="b.url as never" dir="ltr" size="small" :placeholder="urlPlaceholder" />
                  </ConfigField>
                </template>
                <ConfigField v-if="b.type === 'fields'" :label="t('tpl.fields')" :for="`tpl-fields-${i}`" width="lg" :hint="t('tpl.fields_hint')">
                  <MultiSelect v-model="b.fields" :input-id="`tpl-fields-${i}`" :options="fieldTokens" option-label="label" option-value="token" filter display="chip" size="small" />
                </ConfigField>
                <template v-if="b.type === 'table'">
                  <ConfigField :label="t('tpl.table')" :for="`tpl-group-${i}`" width="md" :error="err(`design.blocks.${i}.group`)">
                    <Select v-model="b.group" :input-id="`tpl-group-${i}`" :options="repeaterOptions" option-label="label" option-value="value" size="small" @update:model-value="b.columns = []" />
                  </ConfigField>
                  <ConfigField :label="t('tpl.columns')" :for="`tpl-cols-${i}`" width="lg">
                    <MultiSelect v-model="b.columns" :input-id="`tpl-cols-${i}`" :options="columnsOf(b.group)" option-label="label" option-value="value" display="chip" size="small" />
                  </ConfigField>
                </template>
                <div v-if="doc!.form && scope" class="flex flex-col gap-1">
                  <h4 class="m-0 text-sm font-semibold">{{ t('tpl.show_when') }}</h4>
                  <ExpressionInput v-model="b.condition" :scope="scope" expected="boolean" allow-empty :label="t('tpl.show_when')" />
                  <p class="m-0 text-xs text-muted-color">{{ t('tpl.show_when_hint') }}</p>
                </div>
              </div>
            </ConfigItem>
          </FormScope>
          <div class="flex flex-wrap items-center gap-2">
            <Select v-model="newBlockType" :options="blockTypes" option-label="label" option-value="value" size="small" class="w-field-sm" :aria-label="t('tpl.block_type')" />
            <Button icon="pi pi-plus" :label="t('tpl.add_block')" size="small" outlined data-testid="tpl-add-block" @click="addBlock" />
          </div>
        </ConfigSection>

        <ConfigSection id="tpl-placeholders" :title="t('tpl.section.placeholders')" :description="t('tpl.placeholders_hint')" :default-open="false">
          <ul class="tpl-tokens">
            <li v-for="p in allTokens" :key="p.token">
              <button type="button" class="tpl-token" :title="t('tpl.copy')" @click="copyToken(p.token)">
                <span>{{ p.label }}</span
                ><code class="ltr-value">{{ tokenText(p.token) }}</code>
              </button>
            </li>
          </ul>
        </ConfigSection>

        <ErrorList :errors="errors" />
        <ConfigSaveBar :dirty="dirty || isNew" :saving="saving" testid="tpl" @save="save" @discard="discard" />
      </div>

      <aside class="tpl-preview" :aria-label="t('tpl.preview')">
        <h2 class="m-0 text-base font-semibold">{{ t('tpl.preview') }}</h2>
        <div class="flex flex-col gap-3">
          <ConfigField v-if="doc.form" :label="t('tpl.preview_record')" for="tpl-rec" width="md">
            <Select v-model="previewRecord" input-id="tpl-rec" :options="records" option-label="label" option-value="value" filter show-clear size="small" @filter="(e) => searchRecords(e.value)" />
          </ConfigField>
          <div class="cfg-row">
            <ConfigField :label="t('tpl.preview_as')" for="tpl-as" width="md" :hint="t('tpl.preview_as_hint')">
              <UserPicker v-model="previewUser" />
            </ConfigField>
            <ConfigField :label="t('tpl.preview_locale')" for="tpl-loc" width="xs">
              <Select v-model="previewLocale" input-id="tpl-loc" :options="locales" option-label="label" option-value="value" size="small" />
            </ConfigField>
          </div>
        </div>
        <Message v-if="previewError" severity="warn">{{ previewError }}</Message>
        <template v-if="preview">
          <p class="m-0 text-sm">
            <span class="text-muted-color">{{ t('tpl.subject') }}:</span> <span dir="auto" data-testid="tpl-preview-subject">{{ preview.subject }}</span>
          </p>
          <iframe class="tpl-frame" sandbox="" :src="preview.url" :title="t('tpl.preview')" data-testid="tpl-preview" />
        </template>
      </aside>
    </div>
  </div>
</template>

<style scoped>
.tpl-grid {
  display: grid;
  gap: 1.5rem;
  grid-template-columns: minmax(0, 1fr);
}
@media (min-width: 1200px) {
  .tpl-grid {
    grid-template-columns: minmax(0, 40rem) minmax(0, 1fr);
  }
  .tpl-preview {
    position: sticky;
    top: 0;
    align-self: start;
  }
}
.tpl-preview {
  display: flex;
  flex-direction: column;
  gap: 0.75rem;
}
.tpl-frame {
  width: 100%;
  min-height: 34rem;
  border: 1px solid var(--border);
  border-radius: 8px;
  background: var(--paper);
}
.tpl-tokens {
  display: grid;
  gap: 0.25rem;
  grid-template-columns: repeat(auto-fill, minmax(15rem, 1fr));
}
.tpl-token {
  display: flex;
  flex-direction: column;
  align-items: flex-start;
  width: 100%;
  padding: 0.35rem 0.5rem;
  border: 1px solid var(--border);
  border-radius: 6px;
  background: var(--bg-surface);
  text-align: start;
  font-size: 0.8125rem;
  cursor: pointer;
}
.tpl-token:hover {
  border-color: var(--primary);
}
.tpl-token code {
  color: var(--text-muted);
  font-size: 0.75rem;
}
</style>
