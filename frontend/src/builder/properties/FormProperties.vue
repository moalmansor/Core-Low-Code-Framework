<script setup lang="ts">
import InputNumber from 'primevue/inputnumber'
import InputText from 'primevue/inputtext'
import Select from 'primevue/select'
import Tab from 'primevue/tab'
import TabList from 'primevue/tablist'
import TabPanel from 'primevue/tabpanel'
import TabPanels from 'primevue/tabpanels'
import Tabs from 'primevue/tabs'
import ToggleSwitch from 'primevue/toggleswitch'
import { computed, onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { referenceApi } from '../api'
import ConditionsEditor from '../conditions/ConditionsEditor.vue'
import FormulaEditor from '../conditions/FormulaEditor.vue'
import { buildScope } from '../conditions/scope'
import FieldSelect from '../FieldSelect.vue'
import I18nInput from '../I18nInput.vue'
import type { CollectionSettings, FormSettings } from '../types'
import { useBuilder } from '../useBuilder'
import IssueList from './IssueList.vue'

/** Form-level settings (architecture §14.2) and, for collections, the collection settings. */
const { t } = useI18n()
const builder = useBuilder()
const tab = ref('general')

const form = computed(() => builder.doc!.form)
const i18n = computed(() => form.value.i18n!)
const settings = computed<FormSettings>(() => form.value.settings!)
const scope = computed(() => buildScope(builder.doc!, builder.catalog.fields))
const isCollection = computed(() => form.value.kind === 'collection')
const collection = computed<CollectionSettings>(() => builder.doc!.collection!)

const applications = ref<{ value: string; label: string }[]>([])
const sequences = ref<{ value: string; label: string }[]>([])
const calendars = ref<{ value: string; label: string }[]>([])
onMounted(async () => {
  const [apps, seqs, cals] = await Promise.all([referenceApi.applications().catch(() => []), referenceApi.sequences().catch(() => []), referenceApi.calendars().catch(() => [])])
  applications.value = apps.map((a) => ({ value: a.uuid, label: `${a.name} (${a.key})` }))
  sequences.value = seqs.map((s) => ({ value: s.uuid, label: `${s.key}${s.next_preview ? ` — ${s.next_preview}` : ''}` }))
  calendars.value = cals.map((c) => ({ value: c.uuid, label: c.name ?? c.key }))
})

function setSetting<K extends keyof FormSettings>(key: K, value: FormSettings[K]): void {
  settings.value[key] = value
}
function setMode(mode: 'create' | 'edit' | 'view' | 'print', on: boolean): void {
  settings.value.modes = { ...(settings.value.modes ?? {}), [mode]: on }
}
function setAttachment(key: 'maxCount' | 'maxSizeKb', value: number | null): void {
  const rules = { ...(settings.value.attachmentRules ?? {}) }
  if (value === null) delete rules[key]
  else rules[key] = value
  settings.value.attachmentRules = rules
}
const attachmentTypes = computed({
  get: () => (settings.value.attachmentRules?.types ?? []).join(', '),
  set: (s: string) => {
    settings.value.attachmentRules = {
      ...(settings.value.attachmentRules ?? {}),
      types: s
        .split(',')
        .map((x) => x.trim().toLowerCase())
        .filter((x) => /^[a-z0-9]{1,10}$/.test(x)),
    }
  },
})
const iconInvalid = computed(() => !!form.value.icon && !/^[a-z0-9 -]{0,64}$/.test(form.value.icon))
</script>

<template>
  <div class="flex flex-col gap-2" data-testid="form-properties">
    <header class="flex items-center gap-2">
      <i :class="isCollection ? 'pi pi-table' : 'pi pi-file'" aria-hidden="true" />
      <h2 class="font-semibold flex-1">{{ isCollection ? t('builder.form_props.collection_title') : t('builder.form_props.title') }}</h2>
    </header>
    <IssueList :uuid="null" />
    <Tabs v-model:value="tab" scrollable>
      <TabList>
        <Tab value="general">{{ t('builder.tab.general') }}</Tab>
        <Tab value="behavior">{{ t('builder.tab.behavior') }}</Tab>
        <Tab v-if="isCollection" value="collection">{{ t('builder.tab.collection') }}</Tab>
        <Tab value="rules">{{ t('builder.tab.rules') }}</Tab>
      </TabList>
      <TabPanels class="!px-0">
        <TabPanel value="general" class="flex flex-col gap-3">
          <div class="field">
            <span class="text-sm font-medium">{{ t('builder.key.label') }}</span>
            <span class="ltr-value font-mono text-sm">{{ form.key }}</span>
            <span class="text-xs text-muted-color">{{ t('builder.form_props.key_hint') }}</span>
          </div>
          <I18nInput v-model="i18n.name" :label="t('builder.form_props.name')" />
          <I18nInput v-model="i18n.description" :label="t('builder.form_props.description')" multiline :rows="2" />
          <I18nInput v-model="i18n.submitButtonLabel" :label="t('builder.form_props.submit_label')" />
          <div class="grid grid-cols-2 gap-2">
            <label class="field"
              ><span>{{ t('builder.icon') }}</span>
              <InputText
                :model-value="form.icon ?? ''"
                size="small"
                class="ltr-value"
                :invalid="iconInvalid"
                placeholder="pi pi-file"
                @update:model-value="(v: string | undefined) => (form.icon = v ? v : null)"
              />
            </label>
            <label class="field"
              ><span>{{ t('builder.form_props.application') }}</span>
              <Select :model-value="form.application ?? null" :options="applications" option-label="label" option-value="value" size="small" @update:model-value="(v) => (form.application = v)" />
            </label>
            <label class="field"
              ><span>{{ t('builder.form_props.data_sharing') }}</span>
              <Select
                :model-value="form.dataSharing ?? 'shared'"
                :options="[
                  { value: 'shared', label: t('builder.form_props.shared') },
                  { value: 'isolated', label: t('builder.form_props.isolated') },
                ]"
                option-label="label"
                option-value="value"
                size="small"
                @update:model-value="(v) => (form.dataSharing = v)"
              />
            </label>
            <label class="field"
              ><span>{{ t('builder.form_props.numbering') }}</span>
              <Select
                :model-value="form.numbering ?? null"
                :options="sequences"
                option-label="label"
                option-value="value"
                show-clear
                size="small"
                @update:model-value="(v) => (form.numbering = v ?? null)"
              />
            </label>
            <label class="field col-span-2"
              ><span>{{ t('builder.form_props.calendar') }}</span>
              <Select
                :model-value="form.calendar ?? null"
                :options="calendars"
                option-label="label"
                option-value="value"
                show-clear
                size="small"
                @update:model-value="(v) => (form.calendar = v ?? null)"
              />
              <span class="text-xs text-muted-color">{{ t('builder.form_props.calendar_hint') }}</span>
            </label>
          </div>
          <FormulaEditor :model-value="form.titleTemplate ?? null" :scope="scope" :label="t('builder.form_props.title_template')" @update:model-value="(a) => (form.titleTemplate = a ?? null)" />
          <p class="text-xs text-muted-color">{{ t('builder.form_props.title_template_hint') }}</p>
        </TabPanel>

        <TabPanel value="behavior" class="flex flex-col gap-3">
          <fieldset class="flex flex-col gap-2">
            <legend class="text-sm font-medium mb-1">{{ t('builder.form_props.modes') }}</legend>
            <label v-for="m in ['create', 'edit', 'view', 'print'] as const" :key="m" class="flex items-center gap-2 text-sm"
              ><ToggleSwitch :model-value="settings.modes?.[m] ?? true" @update:model-value="(v: boolean) => setMode(m, v)" />{{ t(`builder.mode.${m}`) }}</label
            >
          </fieldset>
          <label class="field"
            ><span>{{ t('builder.form_props.after_submit') }}</span>
            <Select
              :model-value="settings.afterSubmit?.redirect ?? 'view'"
              :options="(['view', 'list', 'new'] as const).map((v) => ({ value: v, label: t(`builder.form_props.after_${v}`) }))"
              option-label="label"
              option-value="value"
              size="small"
              @update:model-value="(v) => setSetting('afterSubmit', { redirect: v })"
            />
          </label>
          <label class="flex items-center gap-2 text-sm"
            ><ToggleSwitch :model-value="settings.autosaveDrafts ?? false" @update:model-value="(v: boolean) => setSetting('autosaveDrafts', v)" />{{ t('builder.form_props.autosave_drafts') }}</label
          >
          <label class="flex items-center gap-2 text-sm"
            ><ToggleSwitch :model-value="settings.allowComments ?? false" @update:model-value="(v: boolean) => setSetting('allowComments', v)" />{{ t('builder.form_props.allow_comments') }}</label
          >
          <label class="flex items-center gap-2 text-sm"
            ><ToggleSwitch :model-value="settings.allowAttachments ?? false" @update:model-value="(v: boolean) => setSetting('allowAttachments', v)" />{{
              t('builder.form_props.allow_attachments')
            }}</label
          >
          <div v-if="settings.allowAttachments" class="grid grid-cols-2 gap-2">
            <label class="field"
              ><span>{{ t('builder.validation.max_count') }}</span>
              <InputNumber :model-value="settings.attachmentRules?.maxCount ?? null" :min="0" :max="100" size="small" @update:model-value="(v) => setAttachment('maxCount', v ?? null)" />
            </label>
            <label class="field"
              ><span>{{ t('builder.validation.max_size_kb') }}</span>
              <InputNumber :model-value="settings.attachmentRules?.maxSizeKb ?? null" :min="1" :max="1048576" size="small" @update:model-value="(v) => setAttachment('maxSizeKb', v ?? null)" />
            </label>
            <label class="field col-span-2"
              ><span>{{ t('builder.validation.file_types') }}</span>
              <InputText v-model.lazy="attachmentTypes" size="small" class="ltr-value" placeholder="pdf, png" />
            </label>
          </div>
          <div class="field">
            <span class="text-sm font-medium">{{ t('builder.form_props.search_fields') }}</span>
            <FieldSelect v-model:values="settings.searchFields" multiple />
          </div>
          <p class="text-xs text-muted-color">{{ t('builder.form_props.conflict_resolution') }}</p>
        </TabPanel>

        <TabPanel v-if="isCollection" value="collection" class="flex flex-col gap-3">
          <label class="field"
            ><span>{{ t('builder.collection.type') }}</span>
            <Select
              v-model="collection.type"
              :options="[
                { value: 'key_value', label: t('builder.collection.key_value') },
                { value: 'table', label: t('builder.collection.table') },
              ]"
              option-label="label"
              option-value="value"
              size="small"
            />
          </label>
          <label class="flex items-center gap-2 text-sm"
            ><ToggleSwitch :model-value="collection.sharedReference ?? false" @update:model-value="(v: boolean) => (collection.sharedReference = v)" />{{
              t('builder.collection.shared_reference')
            }}</label
          >
          <label class="field"
            ><span>{{ t('builder.collection.owner_application') }}</span>
            <Select
              :model-value="collection.ownerApplication ?? null"
              :options="applications"
              option-label="label"
              option-value="value"
              show-clear
              size="small"
              @update:model-value="(v) => (collection.ownerApplication = v ?? null)"
            />
          </label>
          <label class="field"
            ><span>{{ t('builder.collection.value_field') }}</span
            ><FieldSelect v-model="collection.valueField"
          /></label>
          <label class="field"
            ><span>{{ t('builder.collection.label_field') }}</span
            ><FieldSelect v-model="collection.labelField"
          /></label>
          <label class="field"
            ><span>{{ t('builder.collection.parent_field') }}</span
            ><FieldSelect v-model="collection.parentField"
          /></label>
        </TabPanel>

        <TabPanel value="rules">
          <ConditionsEditor :owner="{ type: 'form', uuid: form.uuid }" :scope="scope" />
        </TabPanel>
      </TabPanels>
    </Tabs>
  </div>
</template>
