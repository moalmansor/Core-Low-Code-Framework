<script setup lang="ts">
import Button from 'primevue/button'
import InputText from 'primevue/inputtext'
import MultiSelect from 'primevue/multiselect'
import Select from 'primevue/select'
import SelectButton from 'primevue/selectbutton'
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
import { errorText, fieldErrors } from '../admin/building/shared'
import { dashboardsApi, reportsApi, uid, type DashboardDoc, type ReportSummary, type WidgetDoc } from './api'

/**
 * The dashboard builder (specification §4.18): widgets on a twelve-column
 * grid. Drag a widget to reorder it, or use its move buttons; set its width
 * and height; limit it to some roles. On a phone every widget is full width.
 */
provideConfigScreen()
const { t } = useI18n()
const toast = useToast()
const confirm = useConfirm()
const route = useRoute()
const router = useRouter()
const isNew = computed(() => route.params.dashboard === 'new')
const doc = ref<DashboardDoc | null>(null)
const saved = ref('')
const saving = ref(false)
const errors = ref<Record<string, string>>({})
const reports = ref<ReportSummary[]>([])
const roles = ref<{ uuid: string; name: string }[]>([])
const selected = ref<string | null>(null)
const dragging = ref<number | null>(null)

onMounted(async () => {
  try {
    const [d, r, ro] = await Promise.all([
      isNew.value ? Promise.resolve<DashboardDoc>({ key: '', application: null, active: true, i18n: { name: {}, description: {} }, widgets: [] }) : dashboardsApi.load(String(route.params.dashboard)),
      reportsApi.list(),
      get<{ data: { uuid: string; name: string }[] }>('/role-options').then((x) => x.data),
    ])
    doc.value = d
    reports.value = r
    roles.value = ro
    saved.value = JSON.stringify(d)
  } catch (e) {
    toast.add({ severity: 'error', summary: errorText(e, t('rep.load_failed')), life: 6000 })
  }
})
const dirty = computed(() => doc.value !== null && JSON.stringify(doc.value) !== saved.value)
onBeforeRouteLeave(() => !dirty.value || window.confirm(t('records.leave_unsaved')))
const err = (k: string) => errors.value[k]
const types = computed(() => [
  { value: 'report', label: t('rep.widget.report') },
  { value: 'text', label: t('rep.widget.text') },
])
const widths = computed(() => [3, 4, 6, 8, 12].map((w) => ({ value: w, label: t(`rep.width.${w}`) })))
const heights = computed(() => [1, 2, 3, 4].map((h) => ({ value: h, label: t('rep.height_n', { n: h }) })))
const reportLabel = (uuid: string | null) => reports.value.find((r) => r.uuid === uuid)?.label ?? t('rep.choose_report')
const current = computed(() => doc.value?.widgets.find((w) => w.uuid === selected.value) ?? null)
const currentIndex = computed(() => doc.value?.widgets.findIndex((w) => w.uuid === selected.value) ?? -1)

function add(type: 'report' | 'text'): void {
  const w: WidgetDoc = { uuid: uid(), type, report: null, i18n: { title: {}, text: {} }, roles: [], layout: { w: type === 'text' ? 12 : 6, h: type === 'text' ? 1 : 2 } }
  doc.value!.widgets.push(w)
  selected.value = w.uuid
}
function move(i: number, to: number): void {
  const list = doc.value!.widgets
  if (to < 0 || to >= list.length || to === i) return
  const [w] = list.splice(i, 1)
  list.splice(to, 0, w!)
}
function drop(to: number): void {
  if (dragging.value !== null) move(dragging.value, to)
  dragging.value = null
}
function removeWidget(i: number): void {
  doc.value!.widgets.splice(i, 1)
  selected.value = null
}

async function save(): Promise<void> {
  if (!doc.value) return
  saving.value = true
  errors.value = {}
  try {
    const out = isNew.value ? await dashboardsApi.create(doc.value) : await dashboardsApi.save(doc.value.uuid!, doc.value, doc.value.hash!)
    doc.value = out
    saved.value = JSON.stringify(out)
    toast.add({ severity: 'success', summary: t('rep.saved'), life: 3000 })
    if (isNew.value) await router.replace({ name: 'admin.reports.dashboard', params: { dashboard: out.uuid } })
  } catch (e) {
    errors.value = fieldErrors(e)
    toast.add({ severity: 'error', summary: errorText(e, t('rep.save_failed')), life: 8000 })
  } finally {
    saving.value = false
  }
}
function discard(): void {
  doc.value = JSON.parse(saved.value) as DashboardDoc
  errors.value = {}
}
function remove(): void {
  confirm.require({
    header: t('rep.delete_dashboard_title'),
    message: t('rep.delete_dashboard_text'),
    acceptProps: { label: t('rep.delete'), severity: 'danger' },
    rejectProps: { label: t('rep.cancel'), text: true },
    accept: async () => {
      try {
        await dashboardsApi.remove(doc.value!.uuid!)
        saved.value = JSON.stringify(doc.value)
        await router.push({ name: 'admin.reports', params: { tab: 'dashboards' } })
      } catch (e) {
        toast.add({ severity: 'error', summary: errorText(e, t('rep.delete_failed')), life: 6000 })
      }
    },
  })
}
const widgetError = (i: number) => err(`widgets.${i}.report`) ?? err(`widgets.${i}.text`) ?? err(`widgets.${i}.layout`) ?? err(`widgets.${i}.roles`)
</script>

<template>
  <div class="flex flex-col gap-6 max-w-[72rem]" data-testid="dashboard-editor">
    <header class="page-head">
      <div class="flex-1 min-w-0">
        <RouterLink :to="{ name: 'admin.reports', params: { tab: 'dashboards' } }" class="text-sm text-primary inline-flex items-center gap-1 mb-1">
          <i class="pi pi-arrow-left rtl:rotate-180" aria-hidden="true" />{{ t('admin.area.reports') }}
        </RouterLink>
        <h1 class="page-title !mb-1">{{ isNew ? t('rep.new_dashboard') : (doc?.label ?? t('rep.dashboard')) }}</h1>
        <p class="m-0 text-sm text-muted-color">{{ t('rep.dashboard_subtitle') }}</p>
      </div>
      <div class="flex items-center gap-2">
        <Button v-if="!isNew && doc" icon="pi pi-eye" :label="t('rep.open')" size="small" text @click="router.push({ name: 'dashboards.view', params: { dashboard: doc.uuid } })" />
        <Button v-if="!isNew" icon="pi pi-trash" :label="t('rep.delete')" size="small" severity="danger" text @click="remove" />
      </div>
    </header>

    <template v-if="doc">
      <ConfigSection id="dashboard-basics" :title="t('rep.section.basics')">
        <div class="cfg-row">
          <I18nInput v-model="doc.i18n.name" class="w-field-md" :label="t('rep.name')" :maxlength="255" :invalid="!!err('i18n.name')" />
          <ConfigField :label="t('rep.key')" for="dashboard-key" width="sm" :hint="t('rep.key_hint')" :error="err('key')" required>
            <InputText id="dashboard-key" v-model="doc.key" dir="ltr" class="ltr-value" size="small" maxlength="48" :disabled="!isNew" data-testid="dashboard-key" />
          </ConfigField>
        </div>
        <I18nInput v-model="doc.i18n.description" class="w-field-lg" :label="t('rep.description')" multiline :rows="2" :maxlength="255" />
        <SettingSwitch id="dashboard-active" v-model="doc.active" :label="t('rep.active')" :description="t('rep.active_desc')" />
      </ConfigSection>

      <ConfigSection id="dashboard-layout" :title="t('rep.section.layout')" :description="t('rep.layout_hint')" :count="doc.widgets.length">
        <div class="flex flex-wrap gap-2">
          <Button icon="pi pi-chart-bar" :label="t('rep.add_report_widget')" size="small" outlined data-testid="dashboard-add-report" @click="add('report')" />
          <Button icon="pi pi-align-left" :label="t('rep.add_text_widget')" size="small" outlined data-testid="dashboard-add-text" @click="add('text')" />
        </div>
        <small v-if="err('widgets')" class="text-danger">{{ err('widgets') }}</small>
        <EmptyState v-if="!doc.widgets.length" icon="pi pi-th-large" :title="t('rep.no_widgets_title')" :description="t('rep.no_widgets_text')" testid="dashboard-empty" />
        <ol v-else class="dash-grid" data-testid="dashboard-grid">
          <li
            v-for="(w, i) in doc.widgets"
            :key="w.uuid"
            class="dash-cell"
            :class="[`cols-${w.layout.w}`, `rows-${w.layout.h}`, { 'is-selected': w.uuid === selected, 'is-invalid': !!widgetError(i) }]"
            draggable="true"
            :data-testid="`dashboard-widget-${i}`"
            @dragstart="dragging = i"
            @dragover.prevent
            @drop.prevent="drop(i)"
          >
            <button type="button" class="dash-pick" :aria-pressed="w.uuid === selected" @click="selected = w.uuid">
              <i :class="[w.type === 'text' ? 'pi pi-align-left' : 'pi pi-chart-bar', 'text-muted-color']" aria-hidden="true" />
              <span class="text-sm font-medium truncate" dir="auto">{{ w.type === 'text' ? t('rep.widget.text') : reportLabel(w.report) }}</span>
              <span class="text-xs text-muted-color">{{ t('rep.size_of', { w: w.layout.w, h: w.layout.h }) }}</span>
              <span v-if="w.roles.length" class="text-xs text-muted-color">{{ t('rep.roles_count', { n: w.roles.length }) }}</span>
              <small v-if="widgetError(i)" class="text-danger">{{ widgetError(i) }}</small>
            </button>
            <div class="dash-move">
              <Button icon="pi pi-arrow-left rtl:rotate-180" size="small" text rounded :aria-label="t('rep.move_earlier')" :disabled="i === 0" @click="move(i, i - 1)" />
              <Button icon="pi pi-arrow-right rtl:rotate-180" size="small" text rounded :aria-label="t('rep.move_later')" :disabled="i === doc.widgets.length - 1" @click="move(i, i + 1)" />
            </div>
          </li>
        </ol>
      </ConfigSection>

      <ConfigSection v-if="current" id="dashboard-widget" :title="t('rep.section.widget', { n: currentIndex + 1 })">
        <div class="cfg-row">
          <ConfigField :label="t('rep.widget_type')" for="widget-type" width="sm">
            <SelectButton id="widget-type" v-model="current.type" :options="types" option-label="label" option-value="value" :allow-empty="false" size="small" />
          </ConfigField>
          <ConfigField v-if="current.type === 'report'" :label="t('rep.report')" for="widget-report" width="md" :error="err(`widgets.${currentIndex}.report`)" required>
            <Select
              v-model="current.report"
              input-id="widget-report"
              :options="reports"
              option-label="label"
              option-value="uuid"
              filter
              size="small"
              :placeholder="t('rep.choose_report')"
              data-testid="widget-report"
            />
          </ConfigField>
        </div>
        <I18nInput v-model="current.i18n.title" class="w-field-md" :label="t('rep.widget_title')" :maxlength="255" />
        <I18nInput
          v-if="current.type === 'text'"
          v-model="current.i18n.text"
          class="w-field-lg"
          :label="t('rep.widget_text')"
          multiline
          :rows="3"
          :maxlength="4000"
          :invalid="!!err(`widgets.${currentIndex}.text`)"
        />
        <div class="cfg-row">
          <ConfigField :label="t('rep.width_label')" for="widget-w" width="sm" :error="err(`widgets.${currentIndex}.layout`)">
            <Select v-model="current.layout.w" input-id="widget-w" :options="widths" option-label="label" option-value="value" size="small" />
          </ConfigField>
          <ConfigField :label="t('rep.height_label')" for="widget-h" width="sm">
            <Select v-model="current.layout.h" input-id="widget-h" :options="heights" option-label="label" option-value="value" size="small" />
          </ConfigField>
        </div>
        <ConfigField :label="t('rep.widget_roles')" for="widget-roles" width="lg" :hint="t('rep.widget_roles_hint')">
          <MultiSelect v-model="current.roles" input-id="widget-roles" :options="roles" option-label="name" option-value="uuid" filter display="chip" size="small" :placeholder="t('rep.everyone')" />
        </ConfigField>
        <div><Button icon="pi pi-trash" :label="t('rep.remove_widget')" size="small" severity="danger" text @click="removeWidget(currentIndex)" /></div>
      </ConfigSection>
      <p v-if="!isNew" class="m-0 text-xs text-muted-color">{{ t('rep.dashboard_access_hint') }}</p>

      <ConfigSaveBar :dirty="dirty || isNew" :saving="saving" testid="dashboard" @save="save" @discard="discard" />
    </template>
  </div>
</template>

<style scoped>
.dash-grid {
  list-style: none;
  margin: 0;
  padding: 0;
  display: grid;
  grid-template-columns: repeat(12, minmax(0, 1fr));
  grid-auto-rows: 4.5rem;
  gap: 0.5rem;
}
.dash-cell {
  position: relative;
  border: 1px dashed var(--border-strong);
  border-radius: var(--radius-card);
  background: var(--bg-subtle);
  cursor: grab;
  min-width: 0;
}
.dash-cell.is-selected {
  border-style: solid;
  border-color: var(--primary);
}
.dash-cell.is-invalid {
  border-color: var(--danger);
}
.dash-pick {
  all: unset;
  box-sizing: border-box;
  display: flex;
  flex-direction: column;
  gap: 0.125rem;
  width: 100%;
  height: 100%;
  padding: 0.5rem 0.75rem;
  cursor: pointer;
  min-width: 0;
}
.dash-pick:focus-visible {
  outline: 2px solid var(--focus-ring);
  outline-offset: 2px;
}
.dash-move {
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
@media (max-width: 640px) {
  .dash-grid {
    grid-template-columns: minmax(0, 1fr);
  }
  .dash-cell {
    grid-column: span 1;
  }
}
</style>
