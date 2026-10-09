<script setup lang="ts">
import Button from 'primevue/button'
import Message from 'primevue/message'
import ProgressSpinner from 'primevue/progressspinner'
import Tab from 'primevue/tab'
import TabList from 'primevue/tablist'
import TabPanel from 'primevue/tabpanel'
import TabPanels from 'primevue/tabpanels'
import Tabs from 'primevue/tabs'
import Tag from 'primevue/tag'
import Textarea from 'primevue/textarea'
import { useConfirm } from 'primevue/useconfirm'
import { useToast } from 'primevue/usetoast'
import { computed, nextTick, onBeforeUnmount, provide, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRoute, useRouter } from 'vue-router'
import { ApiError, ensureCsrf, get, http, send } from '@/api/http'
import { newUuid, openFile } from '@/runtime/api'
import { RECORD_FILES, RECORD_UUID } from '@/runtime/context'
import { FormIndex } from '@/runtime/formIndex'
import { formatDatetime, formatLoose, formatValue } from '@/runtime/format'
import FormRenderer from '@/runtime/FormRenderer.vue'
import { pickText } from '@/runtime/i18nText'
import SafeHtml from '@/runtime/SafeHtml'
import type { ClientDefinition, FileMeta, RecordPayload } from '@/runtime/types'
import { useSession } from '@/stores/session'

/**
 * Record details: header (number, title, created/updated by and at), edit,
 * delete, restore and print as the record's permissions allow, and tabs for
 * the history (audit log), comments and attachments.
 */
interface HistoryEntry {
  event: string
  at: string
  by: string | null
  changes: { field_key: string; old: unknown; new: unknown }[]
}
interface Comment {
  uuid: string
  parent: string | null
  body: string
  author: { uuid: string; name: string | null }
  created_at: string
  mine: boolean
}

const route = useRoute()
const router = useRouter()
const session = useSession()
const { t, locale } = useI18n()
const toast = useToast()
const confirm = useConfirm()

const formUuid = computed(() => String(route.params.form).toLowerCase())
const recordUuid = computed(() => String(route.params.record).toLowerCase())
const definition = ref<ClientDefinition | null>(null)
const printDefinition = ref<ClientDefinition | null>(null)
const record = ref<RecordPayload | null>(null)
const loadError = ref('')
const files = ref<Record<string, FileMeta>>({})
provide(RECORD_FILES, files)
provide(RECORD_UUID, recordUuid)
const tab = ref('details')
const printing = ref(false)

const index = computed(() => (definition.value ? new FormIndex(definition.value) : null))
const name = computed(() => (definition.value ? (pickText(definition.value.names, locale.value) ?? definition.value.name ?? definition.value.form.key) : ''))
const perms = computed(() => record.value?.permissions ?? { edit: false, delete: false, restore: false, print: false, view_log: false })
const deleted = computed(() => !!record.value?.system.deleted_at)
const allowComments = computed(() => definition.value?.form.settings.allowComments !== false && !deleted.value)
const attachments = computed(() => Object.values(record.value?.files ?? {}))

async function load(): Promise<void> {
  loadError.value = ''
  definition.value = null
  record.value = null
  history.value = null
  comments.value = null
  tab.value = 'details'
  try {
    const [d, r] = await Promise.all([get<{ data: ClientDefinition }>(`/r/${formUuid.value}/definition`, { mode: 'view' }), get<{ data: RecordPayload }>(`/r/${formUuid.value}/${recordUuid.value}`)])
    definition.value = d.data
    record.value = r.data
    files.value = { ...(r.data.files ?? {}) }
    document.title = [r.data.title ?? r.data.system.record_number ?? name.value, session.systemName].filter(Boolean).join(' · ')
  } catch (e) {
    loadError.value = e instanceof ApiError && e.status !== 0 ? e.message : t('records.load_failed')
  }
}
const when = (iso: string | null | undefined) => (iso ? formatDatetime(iso, locale.value) : '—')

// Delete / restore
function remove(): void {
  const r = record.value
  if (!r) return
  confirm.require({
    message: t('records.delete_confirm', { title: r.title ?? r.system.record_number ?? '' }),
    header: t('common.confirm'),
    acceptProps: { label: t('common.delete'), severity: 'danger' },
    rejectProps: { label: t('common.cancel'), severity: 'secondary' },
    accept: async () => {
      try {
        await ensureCsrf()
        await http.delete(`/r/${formUuid.value}/${r.uuid}`, { data: { row_version: r.row_version }, headers: { 'Idempotency-Key': newUuid() } })
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
    await send('post', `/r/${formUuid.value}/${recordUuid.value}/restore`)
    toast.add({ severity: 'success', summary: t('records.restored'), life: 4000 })
    await load()
  } catch (e) {
    if (e instanceof ApiError) toast.add({ severity: 'error', summary: e.message, life: 6000 })
  }
}

// Print: the print-mode definition (print access rules), then the browser's print dialog.
async function print(): Promise<void> {
  try {
    printDefinition.value ??= (await get<{ data: ClientDefinition }>(`/r/${formUuid.value}/definition`, { mode: 'print' })).data
  } catch (e) {
    toast.add({ severity: 'error', summary: e instanceof ApiError ? e.message : t('records.load_failed'), life: 6000 })
    return
  }
  tab.value = 'details'
  printing.value = true
  await nextTick()
  window.print()
}
const afterPrint = () => (printing.value = false)
window.addEventListener('afterprint', afterPrint)
onBeforeUnmount(() => window.removeEventListener('afterprint', afterPrint))

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
const EVENTS = ['record.created', 'record.updated', 'record.deleted', 'record.restored', 'record.comment_added', 'record.comment_deleted']
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

// Comments
const comments = ref<Comment[] | null>(null)
const commentBody = ref('')
const posting = ref(false)
const commentError = ref('')
async function loadComments(): Promise<void> {
  commentError.value = ''
  try {
    comments.value = (await get<{ data: Comment[] }>(`/r/${formUuid.value}/${recordUuid.value}/comments`)).data
  } catch (e) {
    commentError.value = e instanceof ApiError ? e.message : t('records.load_failed')
  }
}
function escapeHtml(text: string): string {
  return text.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/\n/g, '<br>')
}
async function addComment(): Promise<void> {
  const body = commentBody.value.trim()
  if (!body) return
  posting.value = true
  try {
    await send('post', `/r/${formUuid.value}/${recordUuid.value}/comments`, { body: escapeHtml(body) })
    commentBody.value = ''
    await loadComments()
  } catch (e) {
    commentError.value = e instanceof ApiError ? (Object.values(e.fieldErrors)[0] ?? e.message) : t('records.comment_failed')
  } finally {
    posting.value = false
  }
}
function deleteComment(c: Comment): void {
  confirm.require({
    message: t('records.comment_delete_confirm'),
    header: t('common.confirm'),
    acceptProps: { label: t('common.delete'), severity: 'danger' },
    rejectProps: { label: t('common.cancel'), severity: 'secondary' },
    accept: async () => {
      try {
        await send('delete', `/r/${formUuid.value}/${recordUuid.value}/comments/${c.uuid}`)
        await loadComments()
      } catch (e) {
        if (e instanceof ApiError) toast.add({ severity: 'error', summary: e.message, life: 6000 })
      }
    },
  })
}

watch(tab, (v) => {
  if (v === 'history' && history.value === null) void loadHistory()
  if (v === 'comments' && comments.value === null) void loadComments()
})

function size(bytes: number): string {
  return bytes < 1024 * 1024 ? `${Math.ceil(bytes / 1024)} KB` : `${(bytes / 1024 / 1024).toFixed(1)} MB`
}
watch([formUuid, recordUuid], load, { immediate: true })

async function open(uuid: string): Promise<void> {
  try {
    await openFile(uuid)
  } catch (e) {
    toast.add({ severity: 'error', summary: e instanceof ApiError ? e.message : t('runtime.file_unavailable'), life: 6000 })
  }
}
</script>

<template>
  <Message v-if="loadError" severity="error" data-testid="record-view-error">{{ loadError }}</Message>
  <div v-else-if="!definition || !record" class="flex justify-center p-10"><ProgressSpinner style="width: 2.5rem; height: 2.5rem" /></div>
  <div v-else class="max-w-6xl" data-testid="record-view">
    <div class="flex flex-wrap items-center gap-3 mb-2 lcf-no-print">
      <RouterLink :to="{ name: 'records.list', params: { form: formUuid } }" class="text-sm text-primary flex items-center gap-1"> <i class="pi pi-arrow-left rtl:rotate-180" />{{ name }} </RouterLink>
    </div>
    <header class="flex flex-wrap items-start gap-3 mb-4">
      <div class="flex-1 min-w-0">
        <h1 class="page-title !mb-1 flex flex-wrap items-center gap-2" data-testid="record-title">
          <span dir="auto">{{ record.title ?? record.system.record_number ?? name }}</span>
          <Tag v-if="record.system.record_number" severity="secondary" :value="record.system.record_number" class="ltr-value" />
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
      <div class="flex flex-wrap gap-2 lcf-no-print">
        <RouterLink v-if="perms.edit" v-slot="{ navigate }" :to="{ name: 'records.edit', params: { form: formUuid, record: recordUuid } }" custom>
          <Button icon="pi pi-pencil" :label="t('common.edit')" data-testid="record-edit" @click="navigate" />
        </RouterLink>
        <Button v-if="perms.print" icon="pi pi-print" :label="t('records.print')" severity="secondary" outlined data-testid="record-print" @click="print" />
        <Button v-if="perms.restore" icon="pi pi-replay" :label="t('records.restore')" severity="secondary" outlined data-testid="record-restore" @click="restore" />
        <Button v-if="perms.delete" icon="pi pi-trash" :label="t('common.delete')" severity="danger" outlined data-testid="record-delete" @click="remove" />
      </div>
    </header>

    <div v-if="printing && printDefinition" class="rounded-xl bg-surface-0 p-4">
      <FormRenderer :definition="printDefinition" :model-value="record.values" mode="print" :form-uuid="formUuid" :references="record.references" />
    </div>
    <Tabs v-else v-model:value="tab">
      <TabList>
        <Tab value="details" data-testid="tab-details">{{ t('records.tab_details') }}</Tab>
        <Tab v-if="perms.view_log" value="history" data-testid="tab-history">{{ t('records.tab_history') }}</Tab>
        <Tab v-if="allowComments" value="comments" data-testid="tab-comments">{{ t('records.tab_comments') }}</Tab>
        <Tab value="attachments" data-testid="tab-attachments">{{ t('records.tab_attachments') }} ({{ attachments.length }})</Tab>
      </TabList>
      <TabPanels>
        <TabPanel value="details">
          <FormRenderer :definition="definition" :model-value="record.values" mode="view" :form-uuid="formUuid" :references="record.references" />
        </TabPanel>

        <TabPanel v-if="perms.view_log" value="history">
          <Message v-if="historyError" severity="error">{{ historyError }}</Message>
          <p v-else-if="history === null" class="text-muted-color">{{ t('records.loading') }}</p>
          <p v-else-if="history.length === 0" class="text-muted-color">{{ t('records.history_empty') }}</p>
          <ol v-else class="flex flex-col gap-3" data-testid="history">
            <li v-for="(h, i) in history" :key="i" class="rounded-lg border border-surface-200 dark:border-surface-700 p-3">
              <div class="flex flex-wrap items-center gap-2 mb-1">
                <Tag :value="eventLabel(h.event)" severity="secondary" />
                <span class="text-sm">{{ h.by ?? t('records.system_actor') }}</span>
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
            </li>
          </ol>
        </TabPanel>

        <TabPanel v-if="allowComments" value="comments">
          <Message v-if="commentError" severity="error" class="mb-3">{{ commentError }}</Message>
          <p v-if="comments === null && !commentError" class="text-muted-color">{{ t('records.loading') }}</p>
          <ul v-else-if="comments" class="flex flex-col gap-3 mb-4" data-testid="comments">
            <li v-if="comments.length === 0" class="text-muted-color">{{ t('records.comments_empty') }}</li>
            <li v-for="c in comments" :key="c.uuid" class="rounded-lg border border-surface-200 dark:border-surface-700 p-3" :class="{ 'ms-8': c.parent }">
              <div class="flex items-center gap-2 mb-1">
                <span class="font-medium">{{ c.author.name ?? '—' }}</span>
                <span class="text-sm text-muted-color flex-1">{{ when(c.created_at) }}</span>
                <Button v-if="c.mine" icon="pi pi-trash" text rounded size="small" severity="danger" :aria-label="t('common.delete')" @click="deleteComment(c)" />
              </div>
              <SafeHtml :html="c.body" />
            </li>
          </ul>
          <form class="flex flex-col gap-2" @submit.prevent="addComment">
            <label for="new-comment" class="text-sm font-medium">{{ t('records.add_comment') }}</label>
            <Textarea id="new-comment" v-model="commentBody" rows="3" auto-resize maxlength="20000" data-testid="comment-body" />
            <div>
              <Button type="submit" :label="t('records.post_comment')" icon="pi pi-send" :loading="posting" :disabled="!commentBody.trim()" data-testid="comment-post" />
            </div>
          </form>
        </TabPanel>

        <TabPanel value="attachments">
          <p v-if="attachments.length === 0" class="text-muted-color">{{ t('records.attachments_empty') }}</p>
          <ul v-else class="flex flex-col gap-2" data-testid="attachments">
            <li v-for="f in attachments" :key="f.uuid" class="flex items-center gap-3 rounded-md border border-surface-200 dark:border-surface-700 p-2">
              <i :class="f.mime.startsWith('image/') ? 'pi pi-image' : 'pi pi-file'" class="text-xl text-muted-color" />
              <span class="flex-1 truncate" dir="auto">{{ f.name }}</span>
              <span class="text-xs text-muted-color ltr-value">{{ size(f.size) }}</span>
              <Button icon="pi pi-download" :label="t('runtime.open_file')" text size="small" @click="open(f.uuid)" />
            </li>
          </ul>
        </TabPanel>
      </TabPanels>
    </Tabs>
  </div>
</template>
