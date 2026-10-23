<script setup lang="ts">
import Button from 'primevue/button'
import Column from 'primevue/column'
import DataTable from 'primevue/datatable'
import Dialog from 'primevue/dialog'
import InputText from 'primevue/inputtext'
import MultiSelect from 'primevue/multiselect'
import Select from 'primevue/select'
import SelectButton from 'primevue/selectbutton'
import Tag from 'primevue/tag'
import { useConfirm } from 'primevue/useconfirm'
import { useToast } from 'primevue/usetoast'
import { computed, onMounted, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { get } from '@/api/http'
import I18nInput from '@/builder/I18nInput.vue'
import ConfigField from '@/components/config/ConfigField.vue'
import ConfigSection from '@/components/config/ConfigSection.vue'
import { provideConfigScreen } from '@/components/config/configScreen'
import EmptyState from '@/components/config/EmptyState.vue'
import SettingSwitch from '@/components/config/SettingSwitch.vue'
import { helpAdminApi, type HelpTarget } from '@/runtime/help'
import { humanize } from '@/runtime/i18nText'
import { TOUR_ANCHORS, TOUR_PLACES, toursAdminApi, type TourDoc } from '@/runtime/tours'
import { errorText, fieldErrors } from '../admin/building/shared'
import HelpTabs from './HelpTabs.vue'

/**
 * Guided tours (specification §4.37): steps that point at a form's fields and
 * groups or at parts of the screen, shown the first time someone opens the
 * form or place, or when they ask from the Help panel.
 */
provideConfigScreen()
const { t, n } = useI18n()
const toast = useToast()
const confirm = useConfirm()
const list = ref<TourDoc[]>([])
const loading = ref(true)
const forms = ref<HelpTarget[]>([])
const areas = ref<HelpTarget[]>([])
const roles = ref<{ uuid: string; name: string }[]>([])
const editing = ref<TourDoc | null>(null)
const errors = ref<Record<string, string>>({})
const saving = ref(false)
const formParts = ref<{ fields: HelpTarget[]; groups: HelpTarget[] }>({ fields: [], groups: [] })
/** Whether the tour runs on a form's screens or in a place. */
const scope = ref<'form' | 'place'>('form')

async function load(): Promise<void> {
  loading.value = true
  try {
    const [l, tg, r] = await Promise.all([toursAdminApi.list(), helpAdminApi.targets(), get<{ data: { uuid: string; name: string }[] }>('/role-options').then((x) => x.data)])
    list.value = l
    forms.value = tg.forms
    areas.value = tg.admin_areas
    roles.value = r
  } catch (e) {
    toast.add({ severity: 'error', summary: errorText(e, t('tour.load_failed')), life: 6000 })
  } finally {
    loading.value = false
  }
}
onMounted(load)

const formOptions = computed(() => forms.value.map((f) => ({ value: f.uuid, label: f.label || humanize(f.key) })).sort((a, b) => a.label.localeCompare(b.label)))
const placeOptions = computed(() => [
  ...TOUR_PLACES.map((p) => ({ value: p as string, label: t(`tour.place.${p}`) })),
  ...areas.value.map((a) => ({ value: `area.${a.key}`, label: `${t('help.target.admin_area')} · ${t(`admin.area.${a.key}`)}` })),
])
const scopes = computed(() => [
  { value: 'form', label: t('tour.scope.form') },
  { value: 'place', label: t('tour.scope.place') },
])
const triggers = computed(() => (['first_use', 'manual'] as const).map((v) => ({ value: v, label: t(`tour.trigger.${v}`) })))
const placements = computed(() => (['auto', 'top', 'bottom', 'start', 'end'] as const).map((v) => ({ value: v, label: t(`tour.placement.${v}`) })))
/** What a step can point at here: nothing, the form's fields and groups, or a named part of the screen. */
const targetOptions = computed(() => [
  { value: null as string | null, label: t('tour.target_none') },
  ...(scope.value === 'form'
    ? [
        ...formParts.value.fields.map((f) => ({ value: `[data-field="${f.key}"]`, label: `${t('help.target.field')} · ${f.label || humanize(f.key)}` })),
        ...formParts.value.groups.map((g) => ({ value: `[data-group="${g.key}"]`, label: `${t('help.target.group')} · ${g.label || humanize(g.key)}` })),
      ]
    : []),
  ...TOUR_ANCHORS.map((a) => ({ value: `[data-tour="${a}"]`, label: t(`tour.anchor.${a}`) })),
])

watch(
  () => (scope.value === 'form' ? editing.value?.form : null),
  async (form) => {
    formParts.value = { fields: [], groups: [] }
    if (!form) return
    try {
      formParts.value = await helpAdminApi.formTargets(form)
    } catch (e) {
      toast.add({ severity: 'error', summary: errorText(e, t('tour.load_failed')), life: 6000 })
    }
  },
)
function where(tr: TourDoc): string {
  if (tr.form) return formOptions.value.find((f) => f.value === tr.form)?.label ?? t('help.target_missing')
  return placeOptions.value.find((p) => p.value === tr.place)?.label ?? t('help.target_missing')
}

const newStep = (i: number): TourDoc['steps'][number] => ({ key: `step_${i}`, target: null, placement: 'auto', i18n: { title: {}, body: {} } })
function create(): void {
  errors.value = {}
  scope.value = 'form'
  editing.value = { form: null, place: null, trigger: 'first_use', roles: [], active: true, i18n: { name: {} }, steps: [newStep(1)] }
}
function edit(tr: TourDoc): void {
  errors.value = {}
  scope.value = tr.form ? 'form' : 'place'
  editing.value = JSON.parse(JSON.stringify(tr)) as TourDoc
}
function addStep(): void {
  const e = editing.value
  if (!e) return
  let i = e.steps.length + 1
  while (e.steps.some((s) => s.key === `step_${i}`)) i++
  e.steps.push(newStep(i))
}
function moveStep(i: number, d: number): void {
  const steps = editing.value?.steps
  if (!steps || i + d < 0 || i + d >= steps.length) return
  ;[steps[i], steps[i + d]] = [steps[i + d]!, steps[i]!]
}

async function save(): Promise<void> {
  const e = editing.value
  if (!e) return
  const doc = { ...e, form: scope.value === 'form' ? e.form : null, place: scope.value === 'place' ? e.place : null }
  saving.value = true
  errors.value = {}
  try {
    if (doc.uuid) await toursAdminApi.save(doc.uuid, doc, doc.hash!)
    else await toursAdminApi.create(doc)
    editing.value = null
    toast.add({ severity: 'success', summary: t('tour.saved'), life: 3000 })
    await load()
  } catch (err) {
    errors.value = fieldErrors(err)
    toast.add({ severity: 'error', summary: errorText(err, t('tour.save_failed')), life: 8000 })
  } finally {
    saving.value = false
  }
}
function remove(tr: TourDoc): void {
  confirm.require({
    header: t('tour.delete_title'),
    message: t('tour.delete_text'),
    acceptProps: { label: t('help.delete'), severity: 'danger' },
    rejectProps: { label: t('help.cancel'), text: true },
    accept: async () => {
      try {
        await toursAdminApi.remove(tr.uuid!)
        editing.value = null
        await load()
      } catch (err) {
        toast.add({ severity: 'error', summary: errorText(err, t('tour.delete_failed')), life: 6000 })
      }
    },
  })
}
function showAgain(tr: TourDoc): void {
  confirm.require({
    header: t('tour.show_again_title'),
    message: tr.roles.length ? t('tour.show_again_roles') : t('tour.show_again_all'),
    acceptProps: { label: t('tour.show_again') },
    rejectProps: { label: t('help.cancel'), text: true },
    accept: async () => {
      try {
        const r = await toursAdminApi.reset(tr.uuid!, tr.roles)
        toast.add({ severity: 'success', summary: t('tour.shown_again', { n: n(r.people) }), life: 4000 })
        await load()
      } catch (err) {
        toast.add({ severity: 'error', summary: errorText(err, t('tour.save_failed')), life: 6000 })
      }
    },
  })
}
</script>

<template>
  <div class="flex flex-col gap-4" data-testid="tours-admin">
    <header class="page-head">
      <div class="flex-1 min-w-0">
        <h1 class="page-title !mb-1">{{ t('admin.area.help_content') }}</h1>
        <p class="m-0 text-sm text-muted-color max-w-[var(--measure)]">{{ t('tour.subtitle') }}</p>
      </div>
      <Button icon="pi pi-plus" :label="t('tour.new')" size="small" data-testid="tour-new" @click="create" />
    </header>
    <HelpTabs current="tours" />
    <EmptyState v-if="!loading && !list.length" icon="pi pi-directions" :title="t('tour.empty_title')" :description="t('tour.empty_text')" testid="tours-empty">
      <Button icon="pi pi-plus" :label="t('tour.new')" size="small" @click="create" />
    </EmptyState>
    <DataTable v-else :value="list" data-key="uuid" :loading="loading" size="small" class="records-table" row-hover @row-click="(e) => edit(e.data as TourDoc)">
      <Column :header="t('tour.name')">
        <template #body="{ data }"
          ><span class="font-medium" dir="auto">{{ data.label }}</span></template
        >
      </Column>
      <Column :header="t('tour.where')">
        <template #body="{ data }"
          ><span dir="auto">{{ where(data) }}</span></template
        >
      </Column>
      <Column :header="t('tour.starts')">
        <template #body="{ data }">{{ t(`tour.trigger.${data.trigger}`) }}</template>
      </Column>
      <Column :header="t('tour.steps')">
        <template #body="{ data }">{{ n(data.steps.length) }}</template>
      </Column>
      <Column :header="t('tour.completed')">
        <template #body="{ data }">{{ n(data.completed ?? 0) }}</template>
      </Column>
      <Column :header="t('help.state')">
        <template #body="{ data }"><Tag :severity="data.active ? 'success' : 'secondary'" :value="data.active ? t('tour.active') : t('tour.inactive')" /></template>
      </Column>
    </DataTable>
  </div>

  <Dialog :visible="!!editing" modal :header="editing?.uuid ? t('tour.edit') : t('tour.new')" class="w-[52rem] max-w-[95vw]" @update:visible="(v: boolean) => !v && (editing = null)">
    <form v-if="editing" class="flex flex-col gap-4" data-testid="tour-form" @submit.prevent="save">
      <ConfigSection id="tour-basics" :title="t('tour.section.where')">
        <I18nInput v-model="editing.i18n.name" class="w-field-md" :label="t('tour.name')" :maxlength="255" :invalid="!!errors['i18n.name']" />
        <small v-if="errors['i18n.name']" class="text-danger">{{ errors['i18n.name'] }}</small>
        <SelectButton v-model="scope" :options="scopes" option-label="label" option-value="value" :allow-empty="false" size="small" data-testid="tour-scope" />
        <ConfigField v-if="scope === 'form'" :label="t('help.target.form')" for="tour-form-pick" width="md" :error="errors.form" required>
          <Select
            v-model="editing.form"
            input-id="tour-form-pick"
            :options="formOptions"
            option-label="label"
            option-value="value"
            filter
            size="small"
            :placeholder="t('help.choose')"
            data-testid="tour-form-pick"
          />
        </ConfigField>
        <ConfigField v-else :label="t('tour.where')" for="tour-place" width="md" :error="errors.place" required>
          <Select
            v-model="editing.place"
            input-id="tour-place"
            :options="placeOptions"
            option-label="label"
            option-value="value"
            filter
            size="small"
            :placeholder="t('help.choose')"
            data-testid="tour-place"
          />
        </ConfigField>
        <div class="cfg-row">
          <ConfigField :label="t('tour.starts')" for="tour-trigger" width="sm" :hint="t(`tour.trigger_hint.${editing.trigger}`)" :error="errors.trigger">
            <Select v-model="editing.trigger" input-id="tour-trigger" :options="triggers" option-label="label" option-value="value" size="small" />
          </ConfigField>
          <ConfigField :label="t('tour.audience')" for="tour-roles" width="md" :hint="t('tour.audience_hint')" :error="errors.roles">
            <MultiSelect v-model="editing.roles" input-id="tour-roles" :options="roles" option-label="name" option-value="uuid" filter display="chip" size="small" :placeholder="t('xp.everyone')" />
          </ConfigField>
        </div>
        <SettingSwitch id="tour-active" v-model="editing.active" :label="t('tour.active')" :description="t('tour.active_desc')" />
      </ConfigSection>

      <ConfigSection id="tour-steps" :title="t('tour.steps')" :description="t('tour.steps_hint')">
        <small v-if="errors.steps" class="text-danger">{{ errors.steps }}</small>
        <div v-for="(s, i) in editing.steps" :key="i" class="rounded-lg border border-line p-3 flex flex-col gap-3" :data-testid="`tour-step-${i}`">
          <div class="flex items-center gap-2">
            <span class="text-sm font-semibold flex-1">{{ t('tour.step_n', { n: n(i + 1) }) }}</span>
            <Button type="button" icon="pi pi-arrow-up" text rounded size="small" :aria-label="t('tour.move_up')" :disabled="i === 0" @click="moveStep(i, -1)" />
            <Button type="button" icon="pi pi-arrow-down" text rounded size="small" :aria-label="t('tour.move_down')" :disabled="i === editing.steps.length - 1" @click="moveStep(i, 1)" />
            <Button
              type="button"
              icon="pi pi-trash"
              text
              rounded
              size="small"
              severity="danger"
              :aria-label="t('tour.remove_step')"
              :disabled="editing.steps.length === 1"
              @click="editing.steps.splice(i, 1)"
            />
          </div>
          <div class="cfg-row">
            <ConfigField :label="t('tour.points_at')" :for="`tour-target-${i}`" width="md" :error="errors[`steps.${i}.target`]">
              <Select v-model="s.target" :input-id="`tour-target-${i}`" :options="targetOptions" option-label="label" option-value="value" filter size="small" :data-testid="`tour-target-${i}`" />
            </ConfigField>
            <ConfigField :label="t('tour.placement_label')" :for="`tour-placement-${i}`" width="sm">
              <Select v-model="s.placement" :input-id="`tour-placement-${i}`" :options="placements" option-label="label" option-value="value" size="small" :disabled="!s.target" />
            </ConfigField>
            <ConfigField :label="t('tour.step_key')" :for="`tour-key-${i}`" width="sm" :hint="t('tour.step_key_hint')" :error="errors[`steps.${i}.key`]">
              <InputText :id="`tour-key-${i}`" v-model="s.key" dir="ltr" class="ltr-value" size="small" maxlength="48" />
            </ConfigField>
          </div>
          <I18nInput v-model="s.i18n.title" :label="t('tour.step_title')" :maxlength="255" />
          <I18nInput v-model="s.i18n.body" :label="t('tour.step_text')" multiline :maxlength="1000" :invalid="!!errors[`steps.${i}.body`]" />
          <small v-if="errors[`steps.${i}.body`]" class="text-danger">{{ errors[`steps.${i}.body`] }}</small>
        </div>
        <Button type="button" icon="pi pi-plus" :label="t('tour.add_step')" size="small" severity="secondary" outlined class="self-start" data-testid="tour-add-step" @click="addStep" />
      </ConfigSection>
      <div class="flex flex-wrap justify-end gap-2">
        <Button v-if="editing.uuid" type="button" severity="danger" text icon="pi pi-trash" :label="t('help.delete')" class="me-auto" @click="remove(editing)" />
        <Button v-if="editing.uuid" type="button" severity="secondary" outlined icon="pi pi-replay" :label="t('tour.show_again')" @click="showAgain(editing)" />
        <Button type="button" severity="secondary" :label="t('help.cancel')" @click="editing = null" />
        <Button type="submit" :label="t('help.save')" :loading="saving" data-testid="tour-save" />
      </div>
    </form>
  </Dialog>
</template>
