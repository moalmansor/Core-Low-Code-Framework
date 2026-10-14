<script setup lang="ts">
import Button from 'primevue/button'
import InputText from 'primevue/inputtext'
import Message from 'primevue/message'
import Select from 'primevue/select'
import { useToast } from 'primevue/usetoast'
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { onBeforeRouteLeave, useRoute, useRouter } from 'vue-router'
import { ApiError, get } from '@/api/http'
import FormPicker from '@/builder/FormPicker.vue'
import ConfigField from '@/components/config/ConfigField.vue'
import ConfigSaveBar from '@/components/config/ConfigSaveBar.vue'
import ConfigSection from '@/components/config/ConfigSection.vue'
import SettingSwitch from '@/components/config/SettingSwitch.vue'
import BlockListEditor from '@/components/templates/BlockListEditor.vue'
import TemplateText from '@/components/templates/TemplateText.vue'
import PlaceholderList from '@/components/templates/PlaceholderList.vue'
import { provideConfigScreen } from '@/components/config/configScreen'
import UserPicker from '@/components/UserPicker.vue'
import { useSession } from '@/stores/session'
import { errorText } from '../building/shared'
import ErrorList from '../formconfig/ErrorList.vue'
import { templatesApi, type BlockType, type Placeholders, type TemplateDoc } from './api'

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

const blockTypes: BlockType[] = ['heading', 'text', 'button', 'fields', 'table', 'divider', 'spacer']

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
          <TemplateText
            v-model="doc.subject"
            class="w-field-lg"
            :label="t('tpl.subject')"
            :form="doc.form"
            :system="placeholders.system"
            :maxlength="255"
            :invalid="!!err(`subject.${dl}`)"
            testid="tpl-subject"
          />
          <TemplateText v-model="doc.preheader" class="w-field-lg" :label="t('tpl.preheader')" :form="doc.form" :system="placeholders.system" :maxlength="255" testid="tpl-preheader" />
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
          <BlockListEditor v-model="doc.design.blocks" :types="blockTypes" :form="doc.form" :placeholders="placeholders" :errors="errors" />
        </ConfigSection>

        <ConfigSection id="tpl-placeholders" :title="t('tpl.section.placeholders')" :description="t('tpl.placeholders_hint')" :default-open="false">
          <PlaceholderList :placeholders="placeholders" />
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
              <UserPicker v-model="previewUser" input-id="tpl-as" />
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
</style>
