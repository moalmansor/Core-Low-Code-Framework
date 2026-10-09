<script setup lang="ts">
import Button from 'primevue/button'
import Menu from 'primevue/menu'
import Message from 'primevue/message'
import ProgressSpinner from 'primevue/progressspinner'
import Tab from 'primevue/tab'
import TabList from 'primevue/tablist'
import TabPanel from 'primevue/tabpanel'
import TabPanels from 'primevue/tabpanels'
import Tabs from 'primevue/tabs'
import Tag from 'primevue/tag'
import { useConfirm } from 'primevue/useconfirm'
import { useToast } from 'primevue/usetoast'
import { computed, provide, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRoute, useRouter } from 'vue-router'
import { ApiError, ensureCsrf, get, http, send } from '@/api/http'
import { newUuid } from '@/runtime/api'
import { RECORD_FILES, RECORD_UUID } from '@/runtime/context'
import { FormIndex } from '@/runtime/formIndex'
import { formatDatetime, formatLoose, formatValue } from '@/runtime/format'
import FormRenderer from '@/runtime/FormRenderer.vue'
import { pickText } from '@/runtime/i18nText'
import JustificationDialog from '@/runtime/JustificationDialog.vue'
import type { ClientDefinition, FileMeta, RecordPayload } from '@/runtime/types'
import { useJustification } from '@/runtime/useJustification'
import RecordPanels from '@/runtime/workflow/RecordPanels.vue'
import StatusBadge from '@/runtime/workflow/StatusBadge.vue'
import StatusTimeline from '@/runtime/workflow/StatusTimeline.vue'
import WorkflowPanel from '@/runtime/workflow/WorkflowPanel.vue'
import { isEmpty } from '@/runtime/values'
import { useSession } from '@/stores/session'
import RecordAttachments from './RecordAttachments.vue'
import RecordComments from './RecordComments.vue'

/**
 * Record details: header (number, title, status, created/updated by and at),
 * the workflow (transitions, approval, assignment, SLA), edit, delete,
 * restore and print (HTML or PDF, per print layout) as the record's
 * permissions allow; the View Mode panels (or the form when none are set
 * up); and tabs for the status timeline, history, comments and attachments.
 */
interface HistoryEntry {
  event: string
  at: string
  by: string | null
  on_behalf_of?: string | null
  changes: { field_key: string; old: unknown; new: unknown }[]
  justification?: { reason_text?: string | null; reason_code?: { code: string | null; label: string | null } | null; restricted?: boolean } | null
}

const route = useRoute()
const router = useRouter()
const session = useSession()
const { t, locale } = useI18n()
const toast = useToast()
const confirm = useConfirm()
const justification = useJustification()

const formUuid = computed(() => String(route.params.form).toLowerCase())
const recordUuid = computed(() => String(route.params.record).toLowerCase())
const definition = ref<ClientDefinition | null>(null)
const record = ref<RecordPayload | null>(null)
const loadError = ref('')
const files = ref<Record<string, FileMeta>>({})
provide(RECORD_FILES, files)
provide(RECORD_UUID, recordUuid)
const tab = ref('details')
const panelCount = ref<number | null>(null)
const attachmentCount = ref(0)

const index = computed(() => (definition.value ? new FormIndex(definition.value) : null))
const name = computed(() => (definition.value ? (pickText(definition.value.names, locale.value) ?? definition.value.name ?? definition.value.form.key) : ''))
const perms = computed(() => record.value?.permissions ?? { edit: false, delete: false, restore: false, print: false, view_log: false })
const deleted = computed(() => !!record.value?.system.deleted_at)
const allowComments = computed(() => definition.value?.form.settings.allowComments !== false && !deleted.value)

async function load(): Promise<void> {
  loadError.value = ''
  history.value = null
  try {
    // Field access depends on the record's status, so the definition is asked for this record.
    const [d, r] = await Promise.all([
      get<{ data: ClientDefinition }>(`/r/${formUuid.value}/definition`, { mode: 'view', record: recordUuid.value }),
      get<{ data: RecordPayload }>(`/r/${formUuid.value}/${recordUuid.value}`),
    ])
    definition.value = d.data
    record.value = r.data
    files.value = { ...(r.data.files ?? {}) }
    document.title = [r.data.title ?? r.data.system.record_number ?? name.value, session.systemName].filter(Boolean).join(' · ')
  } catch (e) {
    loadError.value = e instanceof ApiError && e.status !== 0 ? e.message : t('records.load_failed')
  }
}
const when = (iso: string | null | undefined) => (iso ? formatDatetime(iso, locale.value) : '—')

// Delete / restore (both may ask for a justification).
function remove(): void {
  const r = record.value
  if (!r) return
  confirm.require({
    message: t('records.delete_confirm', { title: r.title ?? r.system.record_number ?? '' }),
    header: t('common.confirm'),
    acceptProps: { label: t('common.delete'), severity: 'danger' },
    rejectProps: { label: t('common.cancel'), severity: 'secondary' },
    accept: async () => {
      const key = newUuid()
      try {
        await ensureCsrf()
        const done = await justification.run((j) =>
          http.delete(`/r/${formUuid.value}/${r.uuid}`, { data: { row_version: r.row_version, ...(j ? { justification: j } : {}) }, headers: { 'Idempotency-Key': key } }),
        )
        if (done === null) return
        toast.add({ severity: 'success', summary: t('records.deleted'), life: 4000 })
        await router.push({ name: 'records.list', params: { form: formUuid.value } })
      } catch (e) {
        if (!(e instanceof ApiError)) return
        // `conflict`: changed since it was opened; `referenced`: other records still point to it.
        toast.add({ severity: 'error', summary: e.code === 'conflict' ? t('records.delete_conflict') : e.message, life: 8000 })
        if (e.code === 'conflict') await load()
      }
    },
  })
}
async function restore(): Promise<void> {
  try {
    const done = await justification.run((j) => send('post', `/r/${formUuid.value}/${recordUuid.value}/restore`, j ? { justification: j } : {}))
    if (done === null) return
    toast.add({ severity: 'success', summary: t('records.restored'), life: 4000 })
    await load()
  } catch (e) {
    if (e instanceof ApiError) toast.add({ severity: 'error', summary: e.message, life: 6000 })
  }
}

// Print: the server renders the record with a print layout, as a page or a PDF.
const printMenu = ref<InstanceType<typeof Menu> | null>(null)
const printing = ref(false)
async function print(format: 'html' | 'pdf', layout: string | null): Promise<void> {
  printing.value = true
  try {
    const res = await http.get<Blob>(`/r/${formUuid.value}/${recordUuid.value}/print`, { params: { format, ...(layout ? { layout } : {}) }, responseType: 'blob', timeout: 120000 })
    const url = URL.createObjectURL(res.data)
    window.open(url, '_blank', 'noopener')
    setTimeout(() => URL.revokeObjectURL(url), 60000)
  } catch (e) {
    toast.add({ severity: 'error', summary: e instanceof ApiError ? e.message : t('records.load_failed'), life: 6000 })
  } finally {
    printing.value = false
  }
}
const printItems = computed(() => {
  const layouts = definition.value?.print_layouts ?? []
  const entries = layouts.length ? layouts.map((l) => ({ key: l.key as string | null, name: l.name })) : [{ key: null, name: t('records.print_default') }]
  return entries.flatMap((l) => [
    { label: `${l.name} · ${t('records.print_page')}`, icon: 'pi pi-print', command: () => print('html', l.key) },
    { label: `${l.name} · PDF`, icon: 'pi pi-file-pdf', command: () => print('pdf', l.key) },
  ])
})

// Required fields a transition needs that the record has not filled.
function missing(keys: string[]): string[] {
  return keys.filter((k) => isEmpty(record.value?.values[k]))
}

// History
const history = ref<HistoryEntry[] | null>(null)
const historyError = ref('')
async function loadHistory(): Promise<void> {
  historyError.value = ''
  try {
    history.value = (await get<{ data: HistoryEntry[] }>(`/r/${formUuid.value}/${recordUuid.value}/history`)).data
  } catch (e) {
    historyError.value = e instanceof ApiError ? e.message : t('records.load_failed')
  }
}
const EVENTS = ['record.created', 'record.updated', 'record.deleted', 'record.restored', 'record.comment_added', 'record.comment_deleted', 'record.transitioned', 'record.assigned', 'record.reassigned', 'record.claimed', 'record.released']
function eventLabel(event: string): string {
  return EVENTS.includes(event) ? t(`records.event.${event.slice(7)}`) : event
}
function fieldLabel(key: string): string {
  const f = index.value?.fieldByKey(key)
  if (f) return pickText(f.i18n.label, locale.value) ?? key
  const rep = index.value?.repeaterKeys.get(key)
  return (rep && pickText(index.value?.groups.get(rep)?.i18n?.title, locale.value)) || key
}
function changeValue(key: string, v: unknown): string {
  const f = index.value?.fieldByKey(key)
  if (v === '«masked»') return t('records.masked')
  const text =
    f && index.value ? formatValue(index.value, f, v, { locale: locale.value, references: record.value?.references, files: files.value, yes: t('runtime.yes'), no: t('runtime.no') }) : formatLoose(v)
  return text === '' ? '—' : text
}

watch(tab, (v) => {
  if (v === 'history' && history.value === null) void loadHistory()
})
watch([formUuid, recordUuid], () => {
  definition.value = null
  record.value = null
  panelCount.value = null
  tab.value = 'details'
  void load()
}, { immediate: true })
</script>

<template>
  <Message v-if="loadError" severity="error" data-testid="record-view-error">{{ loadError }}</Message>
  <div v-else-if="!definition || !record" class="flex justify-center p-10"><ProgressSpinner style="width: 2.5rem; height: 2.5rem" /></div>
  <div v-else class="max-w-6xl flex flex-col gap-4" data-testid="record-view">
    <div class="flex flex-wrap items-center gap-3">
      <RouterLink :to="{ name: 'records.list', params: { form: formUuid } }" class="text-sm text-primary flex items-center gap-1"> <i class="pi pi-arrow-left rtl:rotate-180" />{{ name }} </RouterLink>
    </div>
    <header class="flex flex-wrap items-start gap-3">
      <div class="flex-1 min-w-0">
        <h1 class="page-title !mb-1 flex flex-wrap items-center gap-2" data-testid="record-title">
          <span dir="auto">{{ record.title ?? record.system.record_number ?? name }}</span>
          <Tag v-if="record.system.record_number" severity="secondary" :value="record.system.record_number" class="ltr-value" />
          <StatusBadge :status="record.system.status ?? null" />
          <Tag v-if="deleted" severity="danger" :value="t('records.in_trash')" />
        </h1>
        <dl class="text-sm text-muted-color flex flex-wrap gap-x-6 gap-y-1">
          <div>
            <dt class="inline">{{ t('records.created_label') }}:</dt>
            <dd class="inline ms-1">{{ when(record.system.created_at) }} · {{ record.system.created_by ?? '—' }}</dd>
          </div>
          <div>
            <dt class="inline">{{ t('records.updated_label') }}:</dt>
            <dd class="inline ms-1">{{ when(record.system.updated_at) }} · {{ record.system.updated_by ?? '—' }}</dd>
          </div>
          <div v-if="record.system.version">
            <dt class="inline">{{ t('records.form_version') }}:</dt>
            <dd class="inline ms-1">{{ record.system.version }}</dd>
          </div>
        </dl>
      </div>
      <div class="flex flex-wrap gap-2">
        <RouterLink v-if="perms.edit" v-slot="{ navigate }" :to="{ name: 'records.edit', params: { form: formUuid, record: recordUuid } }" custom>
          <Button icon="pi pi-pencil" :label="t('common.edit')" data-testid="record-edit" @click="navigate" />
        </RouterLink>
        <template v-if="perms.print">
          <Button icon="pi pi-print" :label="t('records.print')" severity="secondary" outlined :loading="printing" aria-haspopup="true" data-testid="record-print" @click="(e: Event) => printMenu?.toggle(e)" />
          <Menu ref="printMenu" :model="printItems" popup />
        </template>
        <Button v-if="perms.restore" icon="pi pi-replay" :label="t('records.restore')" severity="secondary" outlined data-testid="record-restore" @click="restore" />
        <Button v-if="perms.delete" icon="pi pi-trash" :label="t('common.delete')" severity="danger" outlined data-testid="record-delete" @click="remove" />
      </div>
    </header>

    <WorkflowPanel :form="formUuid" :record="recordUuid" :row-version="record.row_version" :field-label="fieldLabel" :missing="missing" @changed="load" />

    <Tabs v-model:value="tab">
      <TabList>
        <Tab value="details" data-testid="tab-details">{{ t('records.tab_details') }}</Tab>
        <Tab value="timeline" data-testid="tab-timeline">{{ t('records.tab_timeline') }}</Tab>
        <Tab v-if="perms.view_log" value="history" data-testid="tab-history">{{ t('records.tab_history') }}</Tab>
        <Tab v-if="allowComments" value="comments" data-testid="tab-comments">{{ t('records.tab_comments') }}</Tab>
        <Tab value="attachments" data-testid="tab-attachments">{{ t('records.tab_attachments') }} ({{ attachmentCount }})</Tab>
      </TabList>
      <TabPanels>
        <TabPanel value="details">
          <RecordPanels :form="formUuid" :record="record" :definition="definition" @loaded="(n) => (panelCount = n)">
            <template #comments><RecordComments :form="formUuid" :record="recordUuid" :readonly="!allowComments" /></template>
            <template #attachments><RecordAttachments :form="formUuid" :record="recordUuid" :files="Object.values(files)" /></template>
          </RecordPanels>
          <FormRenderer v-if="panelCount === 0" :definition="definition" :model-value="record.values" mode="view" :form-uuid="formUuid" :references="record.references" />
        </TabPanel>

        <TabPanel value="timeline">
          <StatusTimeline v-if="tab === 'timeline'" :form="formUuid" :record="recordUuid" :version="record.row_version" />
        </TabPanel>

        <TabPanel v-if="perms.view_log" value="history">
          <Message v-if="historyError" severity="error">{{ historyError }}</Message>
          <p v-else-if="history === null" class="text-muted-color">{{ t('records.loading') }}</p>
          <p v-else-if="history.length === 0" class="text-muted-color">{{ t('records.history_empty') }}</p>
          <ol v-else class="flex flex-col gap-3" data-testid="history">
            <li v-for="(h, i) in history" :key="i" class="rounded-lg border border-line p-3">
              <div class="flex flex-wrap items-center gap-2 mb-1">
                <Tag :value="eventLabel(h.event)" severity="secondary" />
                <span class="text-sm">{{ h.by ?? t('records.system_actor') }}<template v-if="h.on_behalf_of"> {{ t('workflow_run.for', { name: h.on_behalf_of }) }}</template></span>
                <span class="text-sm text-muted-color">{{ when(h.at) }}</span>
              </div>
              <table v-if="h.changes.length" class="w-full text-sm">
                <tbody>
                  <tr v-for="c in h.changes" :key="c.field_key" class="align-top">
                    <td class="py-1 pe-3 font-medium w-1/4">{{ fieldLabel(c.field_key) }}</td>
                    <td class="py-1 pe-3 text-muted-color line-through" dir="auto">{{ changeValue(c.field_key, c.old) }}</td>
                    <td class="py-1" dir="auto">{{ changeValue(c.field_key, c.new) }}</td>
                  </tr>
                </tbody>
              </table>
              <p v-if="h.justification" class="text-sm mt-1 rounded-md bg-primary-subtle p-2">
                <span v-if="h.justification.restricted" class="text-muted-color">{{ t('workflow_run.justification_restricted') }}</span>
                <template v-else>
                  <span class="font-medium">{{ t('workflow_run.justification') }}:</span>
                  <span v-if="h.justification.reason_code"> [{{ h.justification.reason_code.label ?? h.justification.reason_code.code }}]</span>
                  <span dir="auto"> {{ h.justification.reason_text }}</span>
                </template>
              </p>
            </li>
          </ol>
        </TabPanel>

        <TabPanel v-if="allowComments" value="comments">
          <RecordComments v-if="tab === 'comments'" :form="formUuid" :record="recordUuid" />
        </TabPanel>

        <TabPanel value="attachments" :force-render="true">
          <RecordAttachments :form="formUuid" :record="recordUuid" :files="Object.values(files)" @count="(n) => (attachmentCount = n)" />
        </TabPanel>
      </TabPanels>
    </Tabs>
    <JustificationDialog :prompt="justification.prompt.value" :errors="justification.errors.value" :busy="justification.busy.value" @submit="justification.submit" @cancel="justification.cancel" />
  </div>
</template>
