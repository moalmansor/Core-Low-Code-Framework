<script setup lang="ts">
import Button from 'primevue/button'
import Column from 'primevue/column'
import DataTable from 'primevue/datatable'
import Dialog from 'primevue/dialog'
import InputNumber from 'primevue/inputnumber'
import Select from 'primevue/select'
import Tag from 'primevue/tag'
import Textarea from 'primevue/textarea'
import { useConfirm } from 'primevue/useconfirm'
import { useToast } from 'primevue/usetoast'
import { computed, onMounted, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import I18nInput from '@/builder/I18nInput.vue'
import ConfigField from '@/components/config/ConfigField.vue'
import ConfigSection from '@/components/config/ConfigSection.vue'
import { provideConfigScreen } from '@/components/config/configScreen'
import EmptyState from '@/components/config/EmptyState.vue'
import SettingSwitch from '@/components/config/SettingSwitch.vue'
import RichTextInput from '@/runtime/fields/RichTextInput.vue'
import { forgetHelp, HELP_TARGETS, helpAdminApi, type HelpDisplay, type HelpDoc, type HelpTarget, type HelpTargetType } from '@/runtime/help'
import { humanize } from '@/runtime/i18nText'
import { useSession } from '@/stores/session'
import { errorText, fieldErrors } from '../admin/building/shared'
import HelpTabs from './HelpTabs.vue'

/**
 * Help content (specification §4.37): help for a form, one of its fields or
 * groups, a page, an application or an admin area, in every language, shown
 * as a tooltip, in the Help side panel, or as a help page.
 */
provideConfigScreen()
const { t } = useI18n()
const toast = useToast()
const confirm = useConfirm()
const session = useSession()
const locales = computed(() => session.boot?.locales ?? [])
const list = ref<HelpDoc[]>([])
const loading = ref(true)
const targets = ref<{ forms: HelpTarget[]; applications: HelpTarget[]; pages: HelpTarget[]; admin_areas: HelpTarget[] }>({ forms: [], applications: [], pages: [], admin_areas: [] })
const editing = ref<HelpDoc | null>(null)
const errors = ref<Record<string, string>>({})
const saving = ref(false)
/** The form whose fields or groups are offered, for field and group help. */
const helpForm = ref<string | null>(null)
const formParts = ref<{ fields: HelpTarget[]; groups: HelpTarget[] }>({ fields: [], groups: [] })

async function load(): Promise<void> {
  loading.value = true
  try {
    const [l, tg] = await Promise.all([helpAdminApi.list(), helpAdminApi.targets()])
    list.value = l
    targets.value = tg
  } catch (e) {
    toast.add({ severity: 'error', summary: errorText(e, t('help.load_failed')), life: 6000 })
  } finally {
    loading.value = false
  }
}
onMounted(load)

const nameOf = (o: HelpTarget, type: HelpTargetType): string => (type === 'admin_area' ? t(`admin.area.${o.key}`) : o.label || humanize(o.key))
const options = (items: HelpTarget[], type: HelpTargetType) => items.map((o) => ({ value: o.uuid, label: nameOf(o, type) })).sort((a, b) => a.label.localeCompare(b.label))
const formOptions = computed(() => options(targets.value.forms, 'form'))
const targetTypes = computed(() => HELP_TARGETS.map((v) => ({ value: v, label: t(`help.target.${v}`) })))
const displays = computed(() =>
  (['tooltip', 'side_panel', 'help_page'] as HelpDisplay[])
    .filter((d) => d !== 'tooltip' || (editing.value && ['field', 'group'].includes(editing.value.target_type)))
    .map((v) => ({ value: v, label: t(`help.display.${v}`) })),
)
const targetOptions = computed(() => {
  const e = editing.value
  if (!e) return []
  switch (e.target_type) {
    case 'form':
      return formOptions.value
    case 'field':
      return options(formParts.value.fields, 'field')
    case 'group':
      return options(formParts.value.groups, 'group')
    case 'page':
      return options(targets.value.pages, 'page')
    case 'application':
      return options(targets.value.applications, 'application')
    default:
      return options(targets.value.admin_areas, 'admin_area')
  }
})

watch(helpForm, async (form) => {
  formParts.value = { fields: [], groups: [] }
  if (!form) return
  try {
    formParts.value = await helpAdminApi.formTargets(form)
  } catch (e) {
    toast.add({ severity: 'error', summary: errorText(e, t('help.load_failed')), life: 6000 })
  }
})
function setType(type: HelpTargetType): void {
  const e = editing.value
  if (!e) return
  e.target_type = type
  e.target = null
  if (e.display === 'tooltip' && !['field', 'group'].includes(type)) e.display = 'side_panel'
}

/** What a help entry is about, for the list. */
function about(h: HelpDoc): string {
  const find = (items: HelpTarget[], type: HelpTargetType) => {
    const o = items.find((x) => x.uuid === h.target)
    return o ? nameOf(o, type) : t('help.target_missing')
  }
  switch (h.target_type) {
    case 'form':
      return find(targets.value.forms, 'form')
    case 'field':
    case 'group': {
      const form = targets.value.forms.find((x) => x.uuid === h.form)
      return `${t(`help.target.${h.target_type}`)} · ${form ? nameOf(form, 'form') : t('help.target_missing')}`
    }
    case 'page':
      return find(targets.value.pages, 'page')
    case 'application':
      return find(targets.value.applications, 'application')
    default:
      return find(targets.value.admin_areas, 'admin_area')
  }
}

function create(): void {
  errors.value = {}
  helpForm.value = null
  editing.value = { target_type: 'form', target: null, display: 'side_panel', published: false, sort_order: 0, i18n: { title: {}, body: {} } }
}
function edit(h: HelpDoc): void {
  errors.value = {}
  helpForm.value = h.target_type === 'field' || h.target_type === 'group' ? (h.form ?? null) : null
  editing.value = JSON.parse(JSON.stringify(h)) as HelpDoc
}
const bodyOf = (code: string): string => editing.value?.i18n.body[code] ?? ''
function setBody(code: string, value: unknown): void {
  const e = editing.value
  if (!e) return
  const next = { ...e.i18n.body }
  if (typeof value === 'string' && value.trim() !== '') next[code] = value
  else delete next[code]
  e.i18n.body = next
}

async function save(): Promise<void> {
  const e = editing.value
  if (!e) return
  saving.value = true
  errors.value = {}
  try {
    if (e.uuid) await helpAdminApi.save(e.uuid, e, e.hash!)
    else await helpAdminApi.create(e)
    editing.value = null
    forgetHelp()
    toast.add({ severity: 'success', summary: t('help.saved'), life: 3000 })
    await load()
  } catch (err) {
    errors.value = fieldErrors(err)
    toast.add({ severity: 'error', summary: errorText(err, t('help.save_failed')), life: 8000 })
  } finally {
    saving.value = false
  }
}
function remove(h: HelpDoc): void {
  confirm.require({
    header: t('help.delete_title'),
    message: t('help.delete_text'),
    acceptProps: { label: t('help.delete'), severity: 'danger' },
    rejectProps: { label: t('help.cancel'), text: true },
    accept: async () => {
      try {
        await helpAdminApi.remove(h.uuid!)
        editing.value = null
        forgetHelp()
        await load()
      } catch (err) {
        toast.add({ severity: 'error', summary: errorText(err, t('help.delete_failed')), life: 6000 })
      }
    },
  })
}
</script>

<template>
  <div class="flex flex-col gap-4" data-testid="help-admin">
    <header class="page-head">
      <div class="flex-1 min-w-0">
        <h1 class="page-title !mb-1">{{ t('admin.area.help_content') }}</h1>
        <p class="m-0 text-sm text-muted-color max-w-[var(--measure)]">{{ t('help.subtitle') }}</p>
      </div>
      <Button icon="pi pi-plus" :label="t('help.new')" size="small" data-testid="help-new" @click="create" />
    </header>
    <HelpTabs current="help" />
    <EmptyState v-if="!loading && !list.length" icon="pi pi-question-circle" :title="t('help.empty_title')" :description="t('help.empty_text')" testid="help-empty">
      <Button icon="pi pi-plus" :label="t('help.new')" size="small" @click="create" />
    </EmptyState>
    <DataTable v-else :value="list" data-key="uuid" :loading="loading" size="small" class="records-table" row-hover @row-click="(e) => edit(e.data as HelpDoc)">
      <Column :header="t('help.entry_title')">
        <template #body="{ data }"
          ><span class="font-medium" dir="auto">{{ data.label }}</span></template
        >
      </Column>
      <Column :header="t('help.about')">
        <template #body="{ data }"
          ><span dir="auto">{{ about(data) }}</span></template
        >
      </Column>
      <Column :header="t('help.display_label')">
        <template #body="{ data }">{{ t(`help.display.${data.display}`) }}</template>
      </Column>
      <Column :header="t('help.state')">
        <template #body="{ data }"><Tag :severity="data.published ? 'success' : 'secondary'" :value="data.published ? t('help.published') : t('help.draft')" /></template>
      </Column>
    </DataTable>
  </div>

  <Dialog :visible="!!editing" modal :header="editing?.uuid ? t('help.edit') : t('help.new')" class="w-[44rem] max-w-[95vw]" @update:visible="(v: boolean) => !v && (editing = null)">
    <form v-if="editing" class="flex flex-col gap-4" data-testid="help-form" @submit.prevent="save">
      <ConfigSection id="help-about" :title="t('help.section.about')">
        <div class="cfg-row">
          <ConfigField :label="t('help.about')" for="help-type" width="sm" :error="errors.target_type">
            <Select
              :model-value="editing.target_type"
              input-id="help-type"
              :options="targetTypes"
              option-label="label"
              option-value="value"
              size="small"
              data-testid="help-type"
              @update:model-value="setType"
            />
          </ConfigField>
          <ConfigField v-if="editing.target_type === 'field' || editing.target_type === 'group'" :label="t('help.target.form')" for="help-form" width="md">
            <Select
              v-model="helpForm"
              input-id="help-form"
              :options="formOptions"
              option-label="label"
              option-value="value"
              filter
              size="small"
              :placeholder="t('help.choose')"
              data-testid="help-form-pick"
            />
          </ConfigField>
        </div>
        <ConfigField :label="t(`help.target.${editing.target_type}`)" for="help-target" width="md" :error="errors.target" required>
          <Select
            v-model="editing.target"
            input-id="help-target"
            :options="targetOptions"
            option-label="label"
            option-value="value"
            filter
            size="small"
            :placeholder="t('help.choose')"
            data-testid="help-target"
          />
        </ConfigField>
        <ConfigField :label="t('help.display_label')" for="help-display" width="sm" :hint="t(`help.display_hint.${editing.display}`)" :error="errors.display">
          <Select v-model="editing.display" input-id="help-display" :options="displays" option-label="label" option-value="value" size="small" data-testid="help-display" />
        </ConfigField>
      </ConfigSection>
      <ConfigSection id="help-text" :title="t('help.section.text')">
        <I18nInput v-model="editing.i18n.title" :label="t('help.entry_title')" :maxlength="255" :invalid="!!errors['i18n.title']" />
        <small v-if="errors['i18n.title']" class="text-danger">{{ errors['i18n.title'] }}</small>
        <div v-for="l in locales" :key="l.code" class="field">
          <label :for="`help-body-${l.code}`">{{ t('help.text_in', { language: l.native_name }) }}</label>
          <Textarea
            v-if="editing.display === 'tooltip'"
            :id="`help-body-${l.code}`"
            :model-value="bodyOf(l.code)"
            :dir="l.direction"
            rows="3"
            maxlength="500"
            class="w-full"
            :data-testid="`help-body-${l.code}`"
            @update:model-value="setBody(l.code, $event)"
          />
          <RichTextInput v-else :input-id="`help-body-${l.code}`" :model-value="bodyOf(l.code)" :dir="l.direction" @update:model-value="setBody(l.code, $event)" />
        </div>
        <small v-if="errors['i18n.body']" class="text-danger">{{ errors['i18n.body'] }}</small>
      </ConfigSection>
      <ConfigSection id="help-visibility" :title="t('help.section.visibility')">
        <ConfigField :label="t('help.order')" for="help-order" width="xs" :hint="t('help.order_hint')">
          <InputNumber v-model="editing.sort_order" input-id="help-order" :min="0" :max="100000" size="small" />
        </ConfigField>
        <SettingSwitch id="help-published" v-model="editing.published" :label="t('help.published')" :description="t('help.published_desc')" />
      </ConfigSection>
      <div class="flex flex-wrap justify-end gap-2">
        <Button v-if="editing.uuid" type="button" severity="danger" text icon="pi pi-trash" :label="t('help.delete')" class="me-auto" @click="remove(editing)" />
        <Button type="button" severity="secondary" :label="t('help.cancel')" @click="editing = null" />
        <Button type="submit" :label="t('help.save')" :loading="saving" data-testid="help-save" />
      </div>
    </form>
  </Dialog>
</template>
