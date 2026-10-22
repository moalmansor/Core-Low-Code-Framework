<script setup lang="ts">
import Button from 'primevue/button'
import Column from 'primevue/column'
import DataTable from 'primevue/datatable'
import DatePicker from 'primevue/datepicker'
import Dialog from 'primevue/dialog'
import MultiSelect from 'primevue/multiselect'
import Select from 'primevue/select'
import Tag from 'primevue/tag'
import { useConfirm } from 'primevue/useconfirm'
import { useToast } from 'primevue/usetoast'
import { computed, onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { get } from '@/api/http'
import I18nInput from '@/builder/I18nInput.vue'
import ConfigField from '@/components/config/ConfigField.vue'
import ConfigSection from '@/components/config/ConfigSection.vue'
import { provideConfigScreen } from '@/components/config/configScreen'
import EmptyState from '@/components/config/EmptyState.vue'
import EmptyValue from '@/components/EmptyValue.vue'
import SettingSwitch from '@/components/config/SettingSwitch.vue'
import RichTextInput from '@/runtime/fields/RichTextInput.vue'
import { humanize } from '@/runtime/i18nText'
import { useSession } from '@/stores/session'
import { errorText, fieldErrors, formatDateTime } from '../admin/building/shared'
import { announcementsApi, type AnnouncementDoc, type Placement, type Severity } from './api'

/** Announcements and banners (specification §4.29): what is said, to whom, where, and when. */
provideConfigScreen()
const { t, locale } = useI18n()
const toast = useToast()
const confirm = useConfirm()
const session = useSession()
const list = ref<AnnouncementDoc[]>([])
const loading = ref(true)
const roles = ref<{ uuid: string; name: string }[]>([])
const apps = ref<{ uuid: string | null; label: string }[]>([])
const locales = computed(() => session.boot?.locales ?? [])

type Editing = { doc: AnnouncementDoc; starts: Date | null; ends: Date | null }
const editing = ref<Editing | null>(null)
const errors = ref<Record<string, string>>({})
const saving = ref(false)

async function load(): Promise<void> {
  loading.value = true
  try {
    const [l, r, a] = await Promise.all([
      announcementsApi.list(),
      get<{ data: { uuid: string; name: string }[] }>('/role-options').then((x) => x.data),
      get<{ data: { uuid: string; key: string; name: string }[] }>('/applications').then((x) => x.data.map((o) => ({ uuid: o.uuid as string | null, label: o.name || humanize(o.key) }))),
    ])
    list.value = l
    roles.value = r
    apps.value = [{ uuid: null, label: t('xp.everywhere') }, ...a]
  } catch (e) {
    toast.add({ severity: 'error', summary: errorText(e, t('xp.load_failed')), life: 6000 })
  } finally {
    loading.value = false
  }
}
onMounted(load)

const severities = computed(() => (['info', 'success', 'warning', 'danger'] as Severity[]).map((v) => ({ value: v, label: t(`xp.severity.${v}`) })))
const placements = computed(() => (['banner', 'home', 'bell'] as Placement[]).map((v) => ({ value: v, label: t(`xp.placement.${v}`) })))
const severityTag = (s: Severity) => ({ info: 'info', success: 'success', warning: 'warn', danger: 'danger' })[s]

function create(): void {
  errors.value = {}
  editing.value = {
    doc: { application: null, severity: 'info', placement: 'banner', roles: [], starts_at: null, ends_at: null, dismissible: true, published: false, i18n: { title: {}, body: {} } },
    starts: new Date(),
    ends: null,
  }
}
function edit(a: AnnouncementDoc): void {
  errors.value = {}
  editing.value = { doc: JSON.parse(JSON.stringify(a)) as AnnouncementDoc, starts: a.starts_at ? new Date(a.starts_at) : null, ends: a.ends_at ? new Date(a.ends_at) : null }
}
function setBody(code: string, html: unknown): void {
  const e = editing.value
  if (!e) return
  const next = { ...e.doc.i18n.body }
  if (typeof html === 'string' && html.trim() !== '') next[code] = html
  else delete next[code]
  e.doc.i18n.body = next
}
const bodyOf = (code: string): string => editing.value?.doc.i18n.body[code] ?? ''

async function save(): Promise<void> {
  const e = editing.value
  if (!e) return
  saving.value = true
  errors.value = {}
  const doc = { ...e.doc, starts_at: e.starts ? e.starts.toISOString() : null, ends_at: e.ends ? e.ends.toISOString() : null }
  try {
    if (doc.uuid) await announcementsApi.save(doc.uuid, doc, doc.hash!)
    else await announcementsApi.create(doc)
    editing.value = null
    toast.add({ severity: 'success', summary: t('xp.saved'), life: 3000 })
    await load()
  } catch (err) {
    errors.value = fieldErrors(err)
    toast.add({ severity: 'error', summary: errorText(err, t('xp.save_failed')), life: 8000 })
  } finally {
    saving.value = false
  }
}
function remove(a: AnnouncementDoc): void {
  confirm.require({
    header: t('xp.delete_announcement_title'),
    message: t('xp.delete_announcement_text'),
    acceptProps: { label: t('xp.delete'), severity: 'danger' },
    rejectProps: { label: t('xp.cancel'), text: true },
    accept: async () => {
      try {
        await announcementsApi.remove(a.uuid!)
        editing.value = null
        await load()
      } catch (err) {
        toast.add({ severity: 'error', summary: errorText(err, t('xp.delete_failed')), life: 6000 })
      }
    },
  })
}
</script>

<template>
  <div class="flex flex-col gap-4" data-testid="announcements-admin">
    <header class="page-head">
      <div class="flex-1 min-w-0">
        <h1 class="page-title !mb-1">{{ t('admin.area.announcements') }}</h1>
        <p class="m-0 text-sm text-muted-color max-w-[var(--measure)]">{{ t('xp.announcements_subtitle') }}</p>
      </div>
      <Button icon="pi pi-plus" :label="t('xp.new_announcement')" size="small" data-testid="announcement-new" @click="create" />
    </header>
    <EmptyState v-if="!loading && !list.length" icon="pi pi-megaphone" :title="t('xp.announcements_empty_title')" :description="t('xp.announcements_empty_text')" testid="announcements-empty">
      <Button icon="pi pi-plus" :label="t('xp.new_announcement')" size="small" @click="create" />
    </EmptyState>
    <DataTable v-else :value="list" data-key="uuid" :loading="loading" size="small" class="records-table" row-hover @row-click="(e) => edit(e.data as AnnouncementDoc)">
      <Column :header="t('xp.announcement_title')">
        <template #body="{ data }"
          ><span class="font-medium" dir="auto">{{ data.label }}</span></template
        >
      </Column>
      <Column :header="t('xp.severity_label')">
        <template #body="{ data }"><Tag :severity="severityTag(data.severity)" :value="t(`xp.severity.${data.severity}`)" /></template>
      </Column>
      <Column :header="t('xp.placement_label')">
        <template #body="{ data }">{{ t(`xp.placement.${data.placement}`) }}</template>
      </Column>
      <Column :header="t('xp.shown_from')">
        <template #body="{ data }">{{ formatDateTime(data.starts_at, locale) }}</template>
      </Column>
      <Column :header="t('xp.shown_until')">
        <template #body="{ data }"
          ><template v-if="data.ends_at">{{ formatDateTime(data.ends_at, locale) }}</template
          ><EmptyValue v-else
        /></template>
      </Column>
      <Column :header="t('xp.state')">
        <template #body="{ data }"><Tag :severity="data.published ? 'success' : 'secondary'" :value="data.published ? t('xp.published') : t('xp.draft')" /></template>
      </Column>
    </DataTable>
  </div>

  <Dialog
    :visible="!!editing"
    modal
    :header="editing?.doc.uuid ? t('xp.edit_announcement') : t('xp.new_announcement')"
    class="w-[44rem] max-w-[95vw]"
    @update:visible="(v: boolean) => !v && (editing = null)"
  >
    <form v-if="editing" class="flex flex-col gap-4" data-testid="announcement-form" @submit.prevent="save">
      <ConfigSection id="ann-message" :title="t('xp.section.message')">
        <I18nInput v-model="editing.doc.i18n.title" :label="t('xp.announcement_title')" :maxlength="255" :invalid="!!errors['i18n.title']" />
        <small v-if="errors['i18n.title']" class="text-danger">{{ errors['i18n.title'] }}</small>
        <div v-for="l in locales" :key="l.code" class="field">
          <label :for="`ann-body-${l.code}`">{{ t('xp.content_in', { language: l.native_name }) }}</label>
          <RichTextInput :input-id="`ann-body-${l.code}`" :model-value="bodyOf(l.code)" :dir="l.direction" @update:model-value="setBody(l.code, $event)" />
        </div>
      </ConfigSection>
      <ConfigSection id="ann-where" :title="t('xp.section.where_when')">
        <div class="cfg-row">
          <ConfigField :label="t('xp.severity_label')" for="ann-severity" width="sm">
            <Select v-model="editing.doc.severity" input-id="ann-severity" :options="severities" option-label="label" option-value="value" size="small" />
          </ConfigField>
          <ConfigField :label="t('xp.placement_label')" for="ann-placement" width="sm" :hint="t('xp.placement_hint')">
            <Select v-model="editing.doc.placement" input-id="ann-placement" :options="placements" option-label="label" option-value="value" size="small" data-testid="announcement-placement" />
          </ConfigField>
        </div>
        <div class="cfg-row">
          <ConfigField :label="t('xp.shown_from')" for="ann-starts" width="sm" :error="errors.starts_at">
            <DatePicker v-model="editing.starts" input-id="ann-starts" show-time hour-format="24" show-button-bar />
          </ConfigField>
          <ConfigField :label="t('xp.shown_until')" for="ann-ends" width="sm" :hint="t('xp.shown_until_hint')" :error="errors.ends_at">
            <DatePicker v-model="editing.ends" input-id="ann-ends" show-time hour-format="24" show-button-bar />
          </ConfigField>
        </div>
        <div class="cfg-row">
          <ConfigField :label="t('xp.where')" for="ann-app" width="md" :error="errors.application">
            <Select v-model="editing.doc.application" input-id="ann-app" :options="apps" option-label="label" option-value="uuid" size="small" />
          </ConfigField>
          <ConfigField :label="t('xp.audience')" for="ann-roles" width="md" :hint="t('xp.audience_hint')" :error="errors.roles">
            <MultiSelect v-model="editing.doc.roles" input-id="ann-roles" :options="roles" option-label="name" option-value="uuid" filter display="chip" size="small" :placeholder="t('xp.everyone')" />
          </ConfigField>
        </div>
      </ConfigSection>
      <ConfigSection id="ann-visibility" :title="t('xp.section.visibility')">
        <SettingSwitch id="ann-dismissible" v-model="editing.doc.dismissible" :label="t('xp.dismissible')" :description="t('xp.dismissible_desc')" />
        <SettingSwitch id="ann-published" v-model="editing.doc.published" :label="t('xp.published')" :description="t('xp.announcement_published_desc')" />
      </ConfigSection>
      <div class="flex flex-wrap justify-end gap-2">
        <Button v-if="editing.doc.uuid" type="button" severity="danger" text icon="pi pi-trash" :label="t('xp.delete')" class="me-auto" @click="remove(editing.doc)" />
        <Button type="button" severity="secondary" :label="t('xp.cancel')" @click="editing = null" />
        <Button type="submit" :label="t('xp.save')" :loading="saving" data-testid="announcement-save" />
      </div>
    </form>
  </Dialog>
</template>
