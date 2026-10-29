<script setup lang="ts">
import Button from 'primevue/button'
import InputNumber from 'primevue/inputnumber'
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
import EmptyState from '@/components/config/EmptyState.vue'
import SettingSwitch from '@/components/config/SettingSwitch.vue'
import { provideConfigScreen } from '@/components/config/configScreen'
import SubjectPicker from '@/components/SubjectPicker.vue'
import { humanize } from '@/runtime/i18nText'
import { useSession } from '@/stores/session'
import { errorText, fetchFormOptions, fieldErrors, type FormOption } from '../admin/building/shared'
import { uid } from '../reports/api'
import { HOME_WIDGETS, homeApi, type HomeScreenDoc, type HomeWidgetDoc, type HomeWidgetType } from './api'
import HomeWidgetEditor from './HomeWidgetEditor.vue'

/**
 * The home screen builder (specification §4.29): who the screen is for,
 * where it appears, and its widgets on a twelve-column grid. Drag a widget
 * or use its move buttons; on a phone every widget takes the full width.
 */
provideConfigScreen()
const { t } = useI18n()
const toast = useToast()
const confirm = useConfirm()
const route = useRoute()
const router = useRouter()
const session = useSession()
const isNew = computed(() => route.params.screen === 'new')
const blank = (): HomeScreenDoc => ({ application: null, target_type: 'default', target: null, priority: 0, active: true, i18n: { name: {} }, widgets: [] })
const doc = ref<HomeScreenDoc>(blank())
const loaded = ref(false)
const saved = ref('')
const saving = ref(false)
const errors = ref<Record<string, string>>({})
const forms = ref<FormOption[]>([])
type Option = { uuid: string; label: string }
const reports = ref<Option[] | null>(null)
const apps = ref<Option[]>([])
const selected = ref<string | null>(null)
const dragging = ref<number | null>(null)

onMounted(async () => {
  try {
    const canReports = session.can('system.manage_reports')
    const [d, f, r, a] = await Promise.all([
      isNew.value ? Promise.resolve(blank()) : homeApi.load(String(route.params.screen)),
      fetchFormOptions(),
      canReports ? get<{ data: Option[] }>('/reports').then((x) => x.data.map((o) => ({ uuid: o.uuid, label: o.label }))) : Promise.resolve(null),
      get<{ data: { uuid: string; key: string; name: string }[] }>('/applications').then((x) => x.data.map((o) => ({ uuid: o.uuid, label: o.name || humanize(o.key) }))),
    ])
    doc.value = d
    forms.value = f
    reports.value = r
    apps.value = a
    saved.value = JSON.stringify(d)
    loaded.value = true
  } catch (e) {
    toast.add({ severity: 'error', summary: errorText(e, t('xp.load_failed')), life: 6000 })
  }
})
const dirty = computed(() => loaded.value && JSON.stringify(doc.value) !== saved.value)
onBeforeRouteLeave(() => !dirty.value || window.confirm(t('records.leave_unsaved')))
const err = (k: string) => errors.value[k]

const appOptions = computed(() => [{ uuid: null as string | null, label: t('xp.main_home') }, ...apps.value])
const formOptions = computed(() => forms.value.map((f) => ({ uuid: f.uuid, label: `${f.name} (${f.key})` })).sort((a, b) => a.label.localeCompare(b.label)))
const widgetTypes = computed(() => HOME_WIDGETS.map((v) => ({ value: v, label: t(`xp.widget.${v}`) })))

/** Who the screen is for, as the subject picker sees it ("everyone" is the default screen). */
const subject = computed<{ type: string; uuid: string | null } | null>({
  get: () => (doc.value.target_type === 'default' ? { type: 'everyone', uuid: null } : { type: doc.value.target_type, uuid: doc.value.target }),
  set: (v) => {
    doc.value.target_type = !v || v.type === 'everyone' ? 'default' : (v.type as HomeScreenDoc['target_type'])
    doc.value.target = doc.value.target_type === 'default' ? null : (v?.uuid ?? null)
  },
})

const current = computed(() => doc.value.widgets.find((w) => w.uuid === selected.value) ?? null)
const currentIndex = computed(() => doc.value.widgets.findIndex((w) => w.uuid === selected.value))
const widgetLabel = (w: HomeWidgetDoc) => w.i18n.title[session.locale] || Object.values(w.i18n.title).find(Boolean) || t(`xp.widget.${w.type}`)

function add(type: HomeWidgetType): void {
  const w: HomeWidgetDoc = { uuid: uid(), type, i18n: { title: {}, text: {} }, report: null, form: null, limit: 5, links: [], condition: null, layout: { w: type === 'html' ? 12 : 6, h: 2 } }
  doc.value.widgets.push(w)
  selected.value = w.uuid
}
function move(i: number, to: number): void {
  const list = doc.value.widgets
  if (to < 0 || to >= list.length || to === i) return
  const [w] = list.splice(i, 1)
  list.splice(to, 0, w!)
}
function drop(to: number): void {
  if (dragging.value !== null) move(dragging.value, to)
  dragging.value = null
}
function removeWidget(i: number): void {
  doc.value.widgets.splice(i, 1)
  selected.value = null
}
async function save(): Promise<void> {
  saving.value = true
  errors.value = {}
  try {
    const out = isNew.value ? await homeApi.create(doc.value) : await homeApi.save(doc.value.uuid!, doc.value, doc.value.hash!)
    doc.value = out
    saved.value = JSON.stringify(out)
    toast.add({ severity: 'success', summary: t('xp.saved'), life: 3000 })
    if (isNew.value) await router.replace({ name: 'admin.home_screens.screen', params: { screen: out.uuid } })
  } catch (e) {
    errors.value = fieldErrors(e)
    toast.add({ severity: 'error', summary: errorText(e, t('xp.save_failed')), life: 8000 })
  } finally {
    saving.value = false
  }
}
function discard(): void {
  doc.value = JSON.parse(saved.value) as HomeScreenDoc
  errors.value = {}
}
function remove(): void {
  confirm.require({
    header: t('xp.delete_home_title'),
    message: t('xp.delete_home_text'),
    acceptProps: { label: t('xp.delete'), severity: 'danger' },
    rejectProps: { label: t('xp.cancel'), text: true },
    accept: async () => {
      try {
        await homeApi.remove(doc.value.uuid!)
        saved.value = JSON.stringify(doc.value)
        await router.push({ name: 'admin.home_screens' })
      } catch (e) {
        toast.add({ severity: 'error', summary: errorText(e, t('xp.delete_failed')), life: 6000 })
      }
    },
  })
}
const widgetError = (i: number) => Object.entries(errors.value).find(([k]) => k.startsWith(`widgets.${i}.`))?.[1]
</script>

<template>
  <div class="flex flex-col gap-6 max-w-[72rem]" data-testid="home-editor">
    <header class="page-head">
      <div class="flex-1 min-w-0">
        <RouterLink :to="{ name: 'admin.home_screens' }" class="text-sm text-primary inline-flex items-center gap-1 mb-1">
          <i class="pi pi-arrow-left rtl:rotate-180" aria-hidden="true" />{{ t('admin.area.home_screens') }}
        </RouterLink>
        <h1 class="page-title !mb-1" dir="auto">{{ isNew ? t('xp.new_home') : (doc.label ?? t('xp.home_screen')) }}</h1>
        <p class="m-0 text-sm text-muted-color">{{ t('xp.home_editor_subtitle') }}</p>
      </div>
      <Button v-if="!isNew" icon="pi pi-trash" :label="t('xp.delete')" size="small" severity="danger" text data-testid="home-delete" @click="remove" />
    </header>

    <template v-if="loaded">
      <ConfigSection id="home-basics" :title="t('xp.section.basics')">
        <I18nInput v-model="doc.i18n.name" class="w-field-md" :label="t('xp.name')" :maxlength="255" :invalid="!!err('i18n.name')" />
        <small v-if="err('i18n.name')" class="text-danger">{{ err('i18n.name') }}</small>
        <div class="cfg-row">
          <ConfigField :label="t('xp.for_whom')" for="home-target" width="lg" :hint="t('xp.for_whom_hint')" :error="err('target') ?? err('target_type')">
            <SubjectPicker v-model="subject" input-id="home-target" data-testid="home-target" />
          </ConfigField>
        </div>
        <div class="cfg-row">
          <ConfigField :label="t('xp.where')" for="home-app" width="md" :hint="t('xp.where_hint')" :error="err('application')">
            <Select v-model="doc.application" input-id="home-app" :options="appOptions" option-label="label" option-value="uuid" size="small" data-testid="home-app" />
          </ConfigField>
          <ConfigField :label="t('xp.priority')" for="home-priority" width="sm" :hint="t('xp.priority_hint')" :error="err('priority')">
            <InputNumber v-model="doc.priority" input-id="home-priority" :min="0" :max="1000" size="small" />
          </ConfigField>
        </div>
        <SettingSwitch id="home-active" v-model="doc.active" :label="t('xp.active')" :description="t('xp.home_active_desc')" />
      </ConfigSection>

      <ConfigSection id="home-layout" :title="t('xp.section.widgets')" :description="t('xp.widgets_hint')" :count="doc.widgets.length">
        <div class="flex flex-wrap items-end gap-2">
          <Select :options="widgetTypes" option-label="label" option-value="value" size="small" :placeholder="t('xp.add_widget')" data-testid="home-add-widget" @update:model-value="add" />
        </div>
        <small v-if="err('widgets')" class="text-danger">{{ err('widgets') }}</small>
        <EmptyState v-if="!doc.widgets.length" icon="pi pi-th-large" :title="t('xp.no_widgets_title')" :description="t('xp.no_widgets_text')" testid="home-empty" />
        <ol v-else class="home-grid" data-testid="home-grid">
          <li
            v-for="(w, i) in doc.widgets"
            :key="w.uuid"
            class="home-cell"
            :class="[`cols-${w.layout.w}`, `rows-${w.layout.h}`, { 'is-selected': w.uuid === selected, 'is-invalid': !!widgetError(i) }]"
            draggable="true"
            :data-testid="`home-widget-${i}`"
            @dragstart="dragging = i"
            @dragover.prevent
            @drop.prevent="drop(i)"
          >
            <button type="button" class="home-pick" :aria-pressed="w.uuid === selected" @click="selected = w.uuid">
              <span class="text-sm font-medium truncate" dir="auto">{{ widgetLabel(w) }}</span>
              <span class="text-xs text-muted-color">{{ t(`xp.widget.${w.type}`) }} · {{ t('rep.size_of', { w: w.layout.w, h: w.layout.h }) }}</span>
              <span v-if="w.condition" class="text-xs text-muted-color">{{ t('xp.has_rule') }}</span>
              <small v-if="widgetError(i)" class="text-danger">{{ widgetError(i) }}</small>
            </button>
            <div class="home-move">
              <Button icon="pi pi-arrow-left rtl:rotate-180" size="small" text rounded :aria-label="t('rep.move_earlier')" :disabled="i === 0" @click="move(i, i - 1)" />
              <Button icon="pi pi-arrow-right rtl:rotate-180" size="small" text rounded :aria-label="t('rep.move_later')" :disabled="i === doc.widgets.length - 1" @click="move(i, i + 1)" />
            </div>
          </li>
        </ol>
      </ConfigSection>

      <HomeWidgetEditor
        v-if="current"
        :key="current.uuid"
        v-model:widget="doc.widgets[currentIndex]!"
        :index="currentIndex"
        :errors="errors"
        :reports="reports"
        :form-options="formOptions"
        @remove="removeWidget(currentIndex)"
      />

      <ConfigSaveBar :dirty="dirty || isNew" :saving="saving" testid="home" @save="save" @discard="discard" />
    </template>
  </div>
</template>

<style scoped>
.home-grid {
  list-style: none;
  margin: 0;
  padding: 0;
  display: grid;
  grid-template-columns: repeat(12, minmax(0, 1fr));
  grid-auto-rows: 4.5rem;
  gap: 0.5rem;
}
.home-cell {
  position: relative;
  border: 1px dashed var(--border-strong);
  border-radius: var(--radius-card);
  background: var(--bg-subtle);
  cursor: grab;
  min-width: 0;
}
.home-cell.is-selected {
  border-style: solid;
  border-color: var(--primary);
}
.home-cell.is-invalid {
  border-color: var(--danger);
}
.home-pick {
  display: flex;
  flex-direction: column;
  align-items: flex-start;
  gap: 0.125rem;
  width: 100%;
  height: 100%;
  padding: 0.5rem 0.75rem;
  text-align: start;
  background: none;
  border: 0;
  color: inherit;
  cursor: pointer;
  min-width: 0;
}
.home-move {
  position: absolute;
  inset-block-end: 0.125rem;
  inset-inline-end: 0.125rem;
  display: flex;
}
.cols-3 {
  grid-column: span 3;
}
.cols-4 {
  grid-column: span 4;
}
.cols-6 {
  grid-column: span 6;
}
.cols-8 {
  grid-column: span 8;
}
.cols-12 {
  grid-column: span 12;
}
.rows-1 {
  grid-row: span 1;
}
.rows-2 {
  grid-row: span 2;
}
.rows-3 {
  grid-row: span 3;
}
.rows-4 {
  grid-row: span 4;
}
@media (max-width: 900px) {
  .home-cell {
    grid-column: span 12;
  }
}
</style>
