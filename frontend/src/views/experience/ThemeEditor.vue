<script setup lang="ts">
import Button from 'primevue/button'
import Checkbox from 'primevue/checkbox'
import InputText from 'primevue/inputtext'
import Select from 'primevue/select'
import Tag from 'primevue/tag'
import Textarea from 'primevue/textarea'
import { useConfirm } from 'primevue/useconfirm'
import { useToast } from 'primevue/usetoast'
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { onBeforeRouteLeave, useRoute, useRouter } from 'vue-router'
import { ensureCsrf, get, http } from '@/api/http'
import I18nInput from '@/builder/I18nInput.vue'
import ConfigField from '@/components/config/ConfigField.vue'
import ConfigSaveBar from '@/components/config/ConfigSaveBar.vue'
import ConfigSection from '@/components/config/ConfigSection.vue'
import EmptyState from '@/components/config/EmptyState.vue'
import { provideConfigScreen } from '@/components/config/configScreen'
import { humanize } from '@/runtime/i18nText'
import { useSession } from '@/stores/session'
import { PICKER_FALLBACK } from '@/theme/color'
import { errorText, fieldErrors } from '../admin/building/shared'
import { THEME_ASSETS, THEME_CHOICES, THEME_COLORS, themesApi, type ContrastRow, type ThemeAsset, type ThemeChoice, type ThemeDoc, type ThemePreset } from './api'
import ThemeColorRow from './ThemeColorRow.vue'
import ThemeLinkRow from './ThemeLinkRow.vue'
import ThemePreview from './ThemePreview.vue'

/**
 * The theme editor (specification §4.28, ADR-0055): start from a preset,
 * change colours for light and dark mode, shape, density, shadow and fonts,
 * see both modes before saving, and fix what the contrast check reports. The
 * server runs the same check on save: errors are refused, warnings must be
 * acknowledged. Artwork can be added once the theme is saved.
 */
provideConfigScreen()
const { t } = useI18n()
const toast = useToast()
const confirm = useConfirm()
const route = useRoute()
const router = useRouter()
const session = useSession()
const isNew = computed(() => route.params.theme === 'new')
const blank = (): ThemeDoc => ({
  key: '',
  application: null,
  custom_css: null,
  tokens: { colors: {}, legal_links: [] },
  i18n: { name: {}, login_welcome: {}, support_contact: {}, browser_title: {} },
})
const doc = ref<ThemeDoc>(blank())
const loaded = ref(false)
const saved = ref('')
const saving = ref(false)
const errors = ref<Record<string, string>>({})
const presets = ref<ThemePreset[]>([])
const defaults = ref<Record<'light' | 'dark', Record<string, string>>>({ light: {}, dark: {} })
const apps = ref<{ uuid: string | null; label: string }[]>([])
const report = ref<ContrastRow[]>([])
const acknowledged = ref(false)

onMounted(async () => {
  try {
    const [d, p, a] = await Promise.all([
      isNew.value ? Promise.resolve(blank()) : themesApi.load(String(route.params.theme)),
      themesApi.presets(),
      get<{ data: { uuid: string; key: string; name: string }[] }>('/applications').then((x) => x.data.map((o) => ({ uuid: o.uuid as string | null, label: o.name || humanize(o.key) }))),
    ])
    accept(d)
    presets.value = p.data
    defaults.value = p.meta.defaults
    apps.value = a
    loaded.value = true
  } catch (e) {
    toast.add({ severity: 'error', summary: errorText(e, t('xp.load_failed')), life: 6000 })
  }
})

/** Only the document the server keeps: what counts as a change, and what is sent. */
const editable = (d: ThemeDoc): ThemeDoc => ({ uuid: d.uuid, key: d.key, application: d.application, custom_css: d.custom_css, tokens: d.tokens, i18n: d.i18n, hash: d.hash })
function accept(d: ThemeDoc): void {
  d.tokens = { ...d.tokens, colors: Array.isArray(d.tokens.colors) ? {} : (d.tokens.colors ?? {}), legal_links: d.tokens.legal_links ?? [] }
  for (const k of ['name', 'login_welcome', 'support_contact', 'browser_title'] as const) if (Array.isArray(d.i18n[k]) || !d.i18n[k]) d.i18n[k] = {}
  doc.value = d
  report.value = d.contrast ?? []
  saved.value = JSON.stringify(editable(d))
  acknowledged.value = false
}
const dirty = computed(() => loaded.value && JSON.stringify(editable(doc.value)) !== saved.value)
onBeforeRouteLeave(() => !dirty.value || window.confirm(t('records.leave_unsaved')))
const err = (k: string) => errors.value[k]

// The contrast check runs on the server as the colours change (the same check the save applies).
let timer: ReturnType<typeof setTimeout> | undefined
async function runCheck(): Promise<void> {
  try {
    report.value = await themesApi.check(doc.value.tokens)
    delete errors.value.tokens
  } catch (e) {
    errors.value = { ...errors.value, ...fieldErrors(e) }
  }
}
watch(
  () => (loaded.value ? JSON.stringify(doc.value.tokens.colors) : ''),
  (now, before) => {
    if (!now) return
    if (before) acknowledged.value = false
    clearTimeout(timer)
    timer = setTimeout(runCheck, 300)
  },
  { immediate: true },
)
onBeforeUnmount(() => clearTimeout(timer))
const problems = computed(() => report.value.filter((r) => r.level !== 'ok'))
const failing = computed(() => problems.value.filter((r) => r.level === 'error'))
const warnings = computed(() => problems.value.filter((r) => r.level === 'warning'))
function useSuggestion(row: ContrastRow): void {
  const name = row.check as (typeof THEME_COLORS)[number]
  doc.value.tokens.colors = { ...doc.value.tokens.colors, [name]: { ...(doc.value.tokens.colors[name] ?? {}), [row.mode]: row.suggestion } }
}

const appOptions = computed(() => [{ uuid: null as string | null, label: t('xp.theme_no_application') }, ...apps.value])
const choiceOptions = (k: ThemeChoice) => [
  { value: null as string | null, label: t('xp.theme_product_value') },
  ...THEME_CHOICES[k].map((v) => ({ value: v as string | null, label: t(`xp.theme_choice.${k}.${v}`) })),
]
const presetOptions = computed(() => presets.value.map((p) => ({ value: p.key, label: t(`xp.theme_preset.${p.key}`) })))
function applyPreset(key: string): void {
  const p = presets.value.find((x) => x.key === key)
  if (!p) return
  const { colors, ...rest } = JSON.parse(JSON.stringify(p.tokens)) as ThemePreset['tokens']
  doc.value.tokens = { colors: Array.isArray(colors) ? {} : colors, radius: null, density: null, shadow: null, font_latin: null, font_arabic: null, ...rest, legal_links: doc.value.tokens.legal_links }
}
const pairOf = (c: string) => ({ light: defaults.value.light[c] ?? PICKER_FALLBACK, dark: defaults.value.dark[c] ?? PICKER_FALLBACK })

function addLink(): void {
  doc.value.tokens.legal_links.push({ url: '', label: {} })
}

async function save(): Promise<void> {
  saving.value = true
  errors.value = {}
  try {
    const body = editable(doc.value)
    const out = isNew.value ? await themesApi.create(body, acknowledged.value) : await themesApi.save(doc.value.uuid!, body, doc.value.hash!, acknowledged.value)
    accept(out)
    toast.add({ severity: 'success', summary: t('xp.saved'), life: 3000 })
    if (out.default || out.application) await refreshShell()
    if (isNew.value) await router.replace({ name: 'admin.themes.theme', params: { theme: out.uuid } })
  } catch (e) {
    errors.value = fieldErrors(e)
    toast.add({ severity: 'error', summary: errorText(e, t('xp.save_failed')), life: 8000 })
  } finally {
    saving.value = false
  }
}
function discard(): void {
  const keep = { ...doc.value }
  accept({ ...JSON.parse(saved.value), label: keep.label, version: keep.version, default: keep.default, assets: keep.assets } as ThemeDoc)
  errors.value = {}
  void runCheck()
}
/** The organization theme lives in the bootstrap; an application's arrives with the navigation. */
async function refreshShell(): Promise<void> {
  await session.loadBootstrap()
}

async function act(run: () => Promise<ThemeDoc | void>, done: string): Promise<void> {
  try {
    const out = await run()
    if (out) accept(out)
    await refreshShell()
    toast.add({ severity: 'success', summary: t(done), life: 3000 })
  } catch (e) {
    toast.add({ severity: 'error', summary: errorText(e, t('xp.save_failed')), life: 8000 })
  }
}
const makeDefault = () => act(() => themesApi.makeDefault(doc.value.uuid!), 'xp.theme_now_default')
function reset(): void {
  confirm.require({
    header: t('xp.theme_reset_title'),
    message: t('xp.theme_reset_text'),
    acceptProps: { label: t('xp.theme_reset'), severity: 'danger' },
    rejectProps: { label: t('xp.cancel'), text: true },
    accept: () => act(() => themesApi.reset(doc.value.uuid!), 'xp.theme_was_reset'),
  })
}
async function exportTheme(): Promise<void> {
  try {
    const data = await themesApi.exportDoc(doc.value.uuid!)
    const url = URL.createObjectURL(new Blob([JSON.stringify(data, null, 2)], { type: 'application/json' }))
    const a = document.createElement('a')
    a.href = url
    a.download = `theme-${doc.value.key}.json`
    a.click()
    URL.revokeObjectURL(url)
  } catch (e) {
    toast.add({ severity: 'error', summary: errorText(e, t('xp.load_failed')), life: 6000 })
  }
}
function remove(): void {
  confirm.require({
    header: t('xp.theme_delete_title'),
    message: t('xp.theme_delete_text'),
    acceptProps: { label: t('xp.delete'), severity: 'danger' },
    rejectProps: { label: t('xp.cancel'), text: true },
    accept: async () => {
      try {
        await themesApi.remove(doc.value.uuid!)
        saved.value = JSON.stringify(editable(doc.value))
        await refreshShell()
        await router.push({ name: 'admin.themes' })
      } catch (e) {
        toast.add({ severity: 'error', summary: errorText(e, t('xp.delete_failed')), life: 6000 })
      }
    },
  })
}

async function upload(kind: ThemeAsset, event: Event): Promise<void> {
  const input = event.target as HTMLInputElement
  const file = input.files?.[0]
  input.value = ''
  if (!file) return
  const body = new FormData()
  body.append('file', file)
  try {
    await ensureCsrf()
    const out = (await http.post<{ data: ThemeDoc }>(`/themes/${doc.value.uuid}/assets/${kind}`, body)).data.data
    doc.value = { ...doc.value, assets: out.assets, version: out.version }
    await refreshShell()
    toast.add({ severity: 'success', summary: t('xp.theme_artwork_saved'), life: 3000 })
  } catch (e) {
    toast.add({ severity: 'error', summary: errorText(e, t('xp.save_failed')), life: 8000 })
  }
}
async function removeAsset(kind: ThemeAsset): Promise<void> {
  try {
    const out = await themesApi.removeAsset(doc.value.uuid!, kind)
    doc.value = { ...doc.value, assets: out.assets, version: out.version }
    await refreshShell()
  } catch (e) {
    toast.add({ severity: 'error', summary: errorText(e, t('xp.save_failed')), life: 8000 })
  }
}
</script>

<template>
  <div class="flex flex-col gap-6 max-w-[72rem]" data-testid="theme-editor">
    <header class="page-head">
      <div class="flex-1 min-w-0">
        <RouterLink :to="{ name: 'admin.themes' }" class="text-sm text-primary inline-flex items-center gap-1 mb-1">
          <i class="pi pi-arrow-left rtl:rotate-180" aria-hidden="true" />{{ t('admin.area.themes') }}
        </RouterLink>
        <h1 class="page-title !mb-1 flex flex-wrap items-center gap-2">
          <span dir="auto">{{ isNew ? t('xp.new_theme') : (doc.label ?? t('xp.theme')) }}</span>
          <Tag v-if="doc.default" severity="success" :value="t('xp.theme_default')" />
        </h1>
        <p class="m-0 text-sm text-muted-color">{{ t('xp.theme_editor_subtitle') }}</p>
      </div>
      <div v-if="!isNew && loaded" class="flex flex-wrap gap-2">
        <Button
          v-if="!doc.default && !doc.application"
          icon="pi pi-star"
          :label="t('xp.theme_make_default')"
          size="small"
          outlined
          :disabled="dirty"
          data-testid="theme-make-default"
          @click="makeDefault"
        />
        <Button icon="pi pi-download" :label="t('xp.theme_export')" size="small" severity="secondary" text data-testid="theme-export" @click="exportTheme" />
        <Button icon="pi pi-replay" :label="t('xp.theme_reset')" size="small" severity="secondary" text data-testid="theme-reset" @click="reset" />
        <Button icon="pi pi-trash" :label="t('xp.delete')" size="small" severity="danger" text data-testid="theme-delete" @click="remove" />
      </div>
    </header>

    <template v-if="loaded">
      <ConfigSection id="theme-basics" :title="t('xp.section.basics')">
        <I18nInput v-model="doc.i18n.name" class="w-field-md" :label="t('xp.name')" :maxlength="255" :invalid="!!err('i18n.name')" />
        <small v-if="err('i18n.name')" class="text-danger">{{ err('i18n.name') }}</small>
        <div class="cfg-row">
          <ConfigField :label="t('xp.key')" for="theme-key" width="md" :hint="t('xp.theme_key_hint')" :error="err('key')">
            <InputText id="theme-key" v-model="doc.key" class="ltr-value" size="small" maxlength="48" data-testid="theme-key" />
          </ConfigField>
          <ConfigField :label="t('xp.theme_application')" for="theme-app" width="md" :hint="t('xp.theme_application_hint')" :error="err('application')">
            <Select v-model="doc.application" input-id="theme-app" :options="appOptions" option-label="label" option-value="uuid" size="small" data-testid="theme-app" />
          </ConfigField>
        </div>
        <div class="cfg-row">
          <ConfigField :label="t('xp.theme_start_from')" for="theme-preset" width="md" :hint="t('xp.theme_start_from_hint')">
            <Select
              input-id="theme-preset"
              :options="presetOptions"
              option-label="label"
              option-value="value"
              size="small"
              :placeholder="t('xp.theme_pick_preset')"
              data-testid="theme-preset"
              @update:model-value="applyPreset"
            />
          </ConfigField>
        </div>
      </ConfigSection>

      <ConfigSection id="theme-colours" :title="t('xp.section.colours')" :description="t('xp.theme_colours_hint')">
        <div class="theme-color-head text-xs uppercase tracking-wide text-muted-color" aria-hidden="true">
          <span />
          <span>{{ t('xp.theme_mode.light') }}</span>
          <span>{{ t('xp.theme_mode.dark') }}</span>
        </div>
        <ThemeColorRow v-for="c in THEME_COLORS" :key="c" v-model="doc.tokens.colors[c]" :name="c" :label="t(`xp.theme_color.${c}`)" :defaults="pairOf(c)" :errors="errors" />
      </ConfigSection>

      <ConfigSection id="theme-shape" :title="t('xp.section.shape')" :description="t('xp.theme_shape_hint')">
        <div class="cfg-row">
          <ConfigField v-for="k in ['radius', 'density', 'shadow'] as const" :key="k" :label="t(`xp.theme_choice.${k}.label`)" :for="`theme-${k}`" width="sm" :error="err(`tokens.${k}`)">
            <Select v-model="doc.tokens[k]" :input-id="`theme-${k}`" :options="choiceOptions(k)" option-label="label" option-value="value" size="small" :data-testid="`theme-${k}`" />
          </ConfigField>
        </div>
        <div class="cfg-row">
          <ConfigField v-for="k in ['font_latin', 'font_arabic'] as const" :key="k" :label="t(`xp.theme_choice.${k}.label`)" :for="`theme-${k}`" width="sm" :error="err(`tokens.${k}`)">
            <Select v-model="doc.tokens[k]" :input-id="`theme-${k}`" :options="choiceOptions(k)" option-label="label" option-value="value" size="small" :data-testid="`theme-${k}`" />
          </ConfigField>
        </div>
      </ConfigSection>

      <ConfigSection id="theme-check" :title="t('xp.section.contrast')" :description="t('xp.theme_contrast_hint')" :count="problems.length">
        <ThemePreview :look="doc.tokens" />
        <p v-if="!problems.length" class="m-0 text-sm text-success" data-testid="theme-contrast-ok"><i class="pi pi-check me-1" aria-hidden="true" />{{ t('xp.theme_contrast_all_ok') }}</p>
        <ul v-else class="flex flex-col gap-2" data-testid="theme-contrast">
          <li v-for="r in problems" :key="`${r.check}-${r.mode}`" class="flex flex-wrap items-center gap-2 text-sm" :data-testid="`theme-contrast-${r.check}-${r.mode}`">
            <Tag :severity="r.level === 'error' ? 'danger' : 'warn'" :value="r.level === 'error' ? t('xp.theme_level_error') : t('xp.theme_level_warning')" />
            <span class="flex-1 min-w-0">{{
              t('xp.theme_contrast_row', { what: t(`xp.theme_check.${r.check}`), mode: t(`xp.theme_mode.${r.mode}`), ratio: r.ratio.toFixed(2), required: r.required })
            }}</span>
            <Button v-if="r.suggestion" size="small" outlined :label="t('xp.theme_use_colour', { colour: r.suggestion })" :data-testid="`theme-use-${r.check}-${r.mode}`" @click="useSuggestion(r)" />
          </li>
        </ul>
        <small v-if="err('tokens')" class="text-danger">{{ err('tokens') }}</small>
        <label v-if="warnings.length && !failing.length" class="flex items-start gap-2 text-sm" data-testid="theme-acknowledge">
          <Checkbox v-model="acknowledged" binary input-id="theme-ack" />
          <span>{{ t('xp.theme_acknowledge') }}</span>
        </label>
        <small v-if="err('acknowledge_warnings')" class="text-danger">{{ err('acknowledge_warnings') }}</small>
      </ConfigSection>

      <ConfigSection id="theme-signin" :title="t('xp.section.sign_in')" :description="t('xp.theme_signin_hint')">
        <I18nInput v-model="doc.i18n.login_welcome" class="w-field-lg" :label="t('xp.theme_welcome')" multiline :rows="2" :maxlength="500" />
        <I18nInput v-model="doc.i18n.support_contact" class="w-field-md" :label="t('xp.theme_support')" :maxlength="255" />
        <I18nInput v-model="doc.i18n.browser_title" class="w-field-md" :label="t('xp.theme_browser_title')" :maxlength="120" />
        <div class="flex flex-col gap-3">
          <h3 class="text-sm font-semibold m-0">{{ t('xp.theme_legal_links') }}</h3>
          <ThemeLinkRow
            v-for="(_, i) in doc.tokens.legal_links"
            :key="i"
            v-model="doc.tokens.legal_links[i]!"
            :index="i"
            :error="err(`tokens.legal_links.${i}`)"
            @remove="doc.tokens.legal_links.splice(i, 1)"
          />
          <div>
            <Button v-if="doc.tokens.legal_links.length < 8" icon="pi pi-plus" :label="t('xp.theme_add_link')" size="small" text data-testid="theme-add-link" @click="addLink" />
          </div>
        </div>
      </ConfigSection>

      <ConfigSection id="theme-artwork" :title="t('xp.section.artwork')" :description="t('xp.theme_artwork_hint')">
        <EmptyState v-if="isNew" icon="pi pi-image" :title="t('xp.theme_artwork_after_save')" :description="t('xp.theme_artwork_after_save_text')" testid="theme-artwork-later" />
        <div v-else class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
          <div v-for="kind in THEME_ASSETS" :key="kind" class="flex flex-col gap-2 rounded-lg border border-line p-3" :data-testid="`theme-asset-${kind}`">
            <span class="text-sm font-medium">{{ t(`xp.theme_asset.${kind}`) }}</span>
            <div class="h-16 flex items-center justify-center rounded-md bg-subtle overflow-hidden">
              <img v-if="doc.assets?.[kind]" :src="doc.assets[kind]!" alt="" class="max-h-16 max-w-full object-contain" />
              <span v-else class="text-xs text-muted-color">{{ t('xp.theme_no_artwork') }}</span>
            </div>
            <div class="flex flex-wrap items-center gap-2">
              <label class="p-button p-button-sm p-button-outlined cursor-pointer" :for="`theme-file-${kind}`">{{ t('xp.theme_upload') }}</label>
              <input :id="`theme-file-${kind}`" type="file" accept=".png,.jpg,.jpeg,.webp,.ico" class="sr-only" :data-testid="`theme-file-${kind}`" @change="(e) => upload(kind, e)" />
              <Button v-if="doc.assets?.[kind]" :label="t('xp.theme_remove_artwork')" size="small" text severity="danger" @click="removeAsset(kind)" />
            </div>
          </div>
        </div>
      </ConfigSection>

      <ConfigSection id="theme-css" :title="t('xp.section.custom_css')" :description="t('xp.theme_css_hint')">
        <ConfigField :label="t('xp.theme_css')" for="theme-css-input" width="full" :error="err('custom_css')">
          <Textarea id="theme-css-input" v-model="doc.custom_css" class="ltr-value font-mono text-sm w-full" rows="8" spellcheck="false" :invalid="!!err('custom_css')" data-testid="theme-css" />
        </ConfigField>
      </ConfigSection>

      <ConfigSaveBar :dirty="dirty || isNew" :saving="saving" testid="theme" @save="save" @discard="discard" />
    </template>
  </div>
</template>

<style scoped>
.theme-color-head {
  display: grid;
  grid-template-columns: minmax(7rem, 10rem) minmax(0, 1fr) minmax(0, 1fr);
  gap: 0.75rem;
}
@media (max-width: 640px) {
  .theme-color-head {
    display: none;
  }
}
</style>
