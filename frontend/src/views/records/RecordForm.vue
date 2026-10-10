<script setup lang="ts">
import Button from 'primevue/button'
import Message from 'primevue/message'
import ProgressSpinner from 'primevue/progressspinner'
import { useToast } from 'primevue/usetoast'
import { computed, nextTick, provide, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { onBeforeRouteLeave, useRoute, useRouter } from 'vue-router'
import { ApiError, ensureCsrf, get, http } from '@/api/http'
import { newUuid } from '@/runtime/api'
import { changedValues, conflictRows, isConflictPayload, mergeAfterResolution, resolvedSubmission, type Choice, type ConflictPayload } from '@/runtime/conflict'
import { RECORD_FILES, RECORD_UUID } from '@/runtime/context'
import FormRenderer from '@/runtime/FormRenderer.vue'
import { pickText } from '@/runtime/i18nText'
import { submissionValues } from '@/runtime/submission'
import type { ClientDefinition, FileMeta, RecordPayload, References, Values } from '@/runtime/types'
import { same } from '@/runtime/values'
import { useSession } from '@/stores/session'
import JustificationDialog from '@/runtime/JustificationDialog.vue'
import { useJustification } from '@/runtime/useJustification'
import ConflictDialog from './ConflictDialog.vue'
import { scrollBehavior } from '@/theme/motion'

/**
 * Create and edit pages of a record. Saves carry an Idempotency-Key (one per
 * submission attempt, reused when the same submission is retried) and, on
 * update, the row version the user loaded; 422 errors land under their
 * fields and a 409 conflict opens the conflict screen. Nothing is ever
 * overwritten silently.
 */
const route = useRoute()
const router = useRouter()
const session = useSession()
const { t, locale } = useI18n()
const toast = useToast()
const justification = useJustification()

const formUuid = computed(() => String(route.params.form).toLowerCase())
const recordUuid = computed(() => (route.params.record ? String(route.params.record).toLowerCase() : null))
const mode = computed<'create' | 'edit'>(() => (recordUuid.value ? 'edit' : 'create'))

const definition = ref<ClientDefinition | null>(null)
const values = ref<Values>({})
const loaded = ref<Values>({})
const rowVersion = ref(0)
const recordTitle = ref<string | null>(null)
const references = ref<References>({})
const files = ref<Record<string, FileMeta>>({})
provide(RECORD_FILES, files)
provide(RECORD_UUID, recordUuid)
const serverErrors = ref<Record<string, string[]>>({})
const loadError = ref('')
const saving = ref(false)
const saved = ref(false)
const banner = ref('')
const renderer = ref<InstanceType<typeof FormRenderer> | null>(null)
const renderKey = ref(0)

const name = computed(() => (definition.value ? (pickText(definition.value.names, locale.value) ?? definition.value.name ?? definition.value.form.key) : ''))
const submitLabel = computed(() => pickText(definition.value?.form.i18n.submitButtonLabel, locale.value) ?? t('common.save'))
const heading = computed(() => (mode.value === 'create' ? t('records.new_in', { name: name.value }) : t('records.edit_title', { title: recordTitle.value ?? name.value })))

async function load(): Promise<void> {
  definition.value = null
  loadError.value = ''
  serverErrors.value = {}
  banner.value = ''
  saved.value = false
  try {
    // Field access depends on the status of the record being edited.
    const def = (await get<{ data: ClientDefinition }>(`/r/${formUuid.value}/definition`, { mode: mode.value, ...(recordUuid.value ? { record: recordUuid.value } : {}) })).data
    if (mode.value === 'edit') {
      const rec = (await get<{ data: RecordPayload }>(`/r/${formUuid.value}/${recordUuid.value}`)).data
      if (rec.permissions && !rec.permissions.edit) {
        loadError.value = t('records.not_editable')
        return
      }
      applyRecord(rec)
    } else {
      values.value = {}
      loaded.value = {}
      rowVersion.value = 0
      references.value = {}
      files.value = {}
    }
    definition.value = def
    renderKey.value++
    document.title = [heading.value, session.systemName].filter(Boolean).join(' · ')
  } catch (e) {
    loadError.value = e instanceof ApiError && e.status !== 0 ? e.message : t('records.load_failed')
  }
}

function applyRecord(rec: RecordPayload): void {
  values.value = JSON.parse(JSON.stringify(rec.values)) as Values
  loaded.value = JSON.parse(JSON.stringify(rec.values)) as Values
  rowVersion.value = rec.row_version
  recordTitle.value = rec.title ?? rec.system.record_number ?? null
  references.value = rec.references ?? {}
  files.value = { ...(rec.files ?? {}) }
}

watch([formUuid, recordUuid], load, { immediate: true })

// One Idempotency-Key per submission attempt: a retry of the same body reuses it.
let attempt: { key: string; body: string } | null = null
function keyFor(body: unknown): string {
  const text = JSON.stringify(body)
  if (attempt === null || attempt.body !== text) attempt = { key: newUuid(), body: text }
  return attempt.key
}

const dirty = computed(
  () =>
    !saved.value &&
    definition.value !== null &&
    (mode.value === 'create'
      ? Object.values(values.value).some((v) => v !== null && v !== '' && !(Array.isArray(v) && !v.length)) && !same(values.value, loaded.value)
      : !same(changedValues(loaded.value, values.value), {})),
)

function focusFirstError(): void {
  void nextTick(() => {
    const el = document.querySelector<HTMLElement>('[data-testid="form-renderer"] [role="alert"], [data-testid="form-error"]')
    el?.closest('[data-field]')?.scrollIntoView({ behavior: scrollBehavior(), block: 'center' })
    el?.closest('[data-field]')?.querySelector<HTMLElement>('input, textarea, [tabindex="0"], button')?.focus()
  })
}

// Conflict screen
const conflict = ref<ConflictPayload | null>(null)
const conflictSubmitted = ref<Values>({})

async function save(override?: Values): Promise<void> {
  if (!definition.value || !renderer.value || saving.value) return
  const clientErrors = renderer.value.validate()
  if (renderer.value.blocked) {
    banner.value = t('records.submit_blocked')
    focusFirstError()
    return
  }
  if (Object.keys(clientErrors).length) {
    banner.value = t('records.fix_errors')
    focusFirstError()
    return
  }
  const current = renderer.value.values as Values
  const body: Record<string, unknown> =
    mode.value === 'create'
      ? {
          values: submissionValues(renderer.value.index, renderer.value.state, 'create', current, {}),
          params: Object.fromEntries(Object.entries(route.query).filter(([, v]) => typeof v === 'string')),
        }
      : { values: override ?? submissionValues(renderer.value.index, renderer.value.state, 'edit', current, loaded.value), row_version: rowVersion.value }
  if (mode.value === 'edit' && Object.keys(body.values as Values).length === 0) {
    toast.add({ severity: 'info', summary: t('records.no_changes'), life: 4000 })
    return
  }
  saving.value = true
  banner.value = ''
  try {
    await ensureCsrf()
    // A change that needs a justification is answered 422 with the prompt; it is sent again with the user's justification.
    const res = await justification.run((j) => {
      const sent = j ? { ...body, justification: j } : body
      const headers = { 'Idempotency-Key': keyFor(sent) }
      return mode.value === 'create'
        ? http.post<{ data: RecordPayload }>(`/r/${formUuid.value}`, sent, { headers })
        : http.patch<{ data: RecordPayload }>(`/r/${formUuid.value}/${recordUuid.value}`, sent, { headers })
    })
    if (res === null) return
    attempt = null
    saved.value = true
    serverErrors.value = {}
    const rec = res.data.data
    toast.add({ severity: 'success', summary: mode.value === 'create' ? t('records.created') : t('records.saved'), life: 4000 })
    const redirect = definition.value.form.settings.afterSubmit?.redirect ?? 'view'
    if (mode.value === 'create' && redirect === 'new') {
      await load()
      return
    }
    if (redirect === 'list') await router.push({ name: 'records.list', params: { form: formUuid.value } })
    else await router.push({ name: 'records.view', params: { form: formUuid.value, record: rec.uuid } })
  } catch (e) {
    if (!(e instanceof ApiError)) {
      banner.value = t('records.save_failed')
      return
    }
    if (e.status === 422) {
      attempt = null
      serverErrors.value = e.body.errors ?? {}
      banner.value = e.message || t('records.fix_errors')
      focusFirstError()
    } else if (e.status === 409 && isConflictPayload(e.body)) {
      attempt = null
      conflict.value = e.body
      conflictSubmitted.value = (body.values as Values) ?? {}
    } else if (e.status === 0 || e.status >= 500) {
      // The same body retried keeps its Idempotency-Key, so a lost response is never applied twice.
      banner.value = t('records.save_retry')
    } else {
      attempt = null
      banner.value = e.message
    }
  } finally {
    saving.value = false
  }
}

async function reloadLatest(): Promise<RecordPayload | null> {
  try {
    return (await get<{ data: RecordPayload }>(`/r/${formUuid.value}/${recordUuid.value}`)).data
  } catch (e) {
    banner.value = e instanceof ApiError ? e.message : t('records.load_failed')
    return null
  }
}

async function onConflictReload(): Promise<void> {
  conflict.value = null
  const rec = await reloadLatest()
  if (!rec) return
  applyRecord(rec)
  serverErrors.value = {}
  renderKey.value++
  toast.add({ severity: 'info', summary: t('records.reloaded'), life: 4000 })
}

async function onConflictResolve(choices: Record<string, Choice>): Promise<void> {
  const payload = conflict.value
  conflict.value = null
  if (!payload) return
  const rec = await reloadLatest()
  if (!rec) return
  const submission = resolvedSubmission(conflictSubmitted.value, conflictRows(payload, conflictSubmitted.value), choices)
  references.value = { ...(rec.references ?? {}), ...references.value }
  files.value = { ...files.value, ...(rec.files ?? {}) }
  loaded.value = JSON.parse(JSON.stringify(rec.values)) as Values
  values.value = mergeAfterResolution(rec.values, submission)
  rowVersion.value = rec.row_version
  recordTitle.value = rec.title ?? rec.system.record_number ?? null
  renderKey.value++
  await nextTick()
  if (Object.keys(submission).length) await save(submission)
  else {
    saved.value = true
    toast.add({ severity: 'info', summary: t('records.kept_theirs'), life: 4000 })
    await router.push({ name: 'records.view', params: { form: formUuid.value, record: recordUuid.value! } })
  }
}

function cancel(): void {
  if (mode.value === 'edit') void router.push({ name: 'records.view', params: { form: formUuid.value, record: recordUuid.value! } })
  else void router.push({ name: 'records.list', params: { form: formUuid.value } })
}

onBeforeRouteLeave(() => (dirty.value ? window.confirm(t('records.leave_unsaved')) : true))
</script>

<template>
  <Message v-if="loadError" severity="error" data-testid="record-form-error">{{ loadError }}</Message>
  <div v-else-if="!definition" class="flex justify-center p-10"><ProgressSpinner style="width: 2.5rem; height: 2.5rem" /></div>
  <form v-else class="max-w-6xl flex flex-col gap-4" novalidate data-testid="record-form" @submit.prevent="save()">
    <header class="flex flex-col gap-1">
      <RouterLink :to="{ name: 'records.list', params: { form: formUuid } }" class="text-sm text-primary inline-flex items-center gap-1 self-start">
        <i class="pi pi-arrow-left rtl:rotate-180" aria-hidden="true" />{{ name }}
      </RouterLink>
      <h1 class="page-title !m-0" dir="auto">{{ heading }}</h1>
    </header>
    <Message v-if="banner" severity="error" data-testid="record-form-banner">{{ banner }}</Message>
    <div class="rounded-xl bg-card border border-line p-4 lg:p-6">
      <FormRenderer :key="renderKey" ref="renderer" v-model="values" :definition="definition" :mode="mode" :errors="serverErrors" :form-uuid="formUuid" :references="references" />
    </div>
    <div class="action-bar !mt-0" role="region" :aria-label="t('formconfig.actions')" data-testid="record-form-bar">
      <span class="text-sm flex items-center gap-2" :class="dirty ? '' : 'text-muted-color'" aria-live="polite">
        <i :class="dirty ? 'pi pi-circle-fill text-warning text-[0.5rem]' : 'pi pi-check'" aria-hidden="true" />
        {{ dirty ? t('workflow.unsaved') : mode === 'create' ? t('records.nothing_entered') : t('records.no_changes') }}
      </span>
      <span class="flex-1" />
      <Button type="button" :label="t('common.cancel')" severity="secondary" text @click="cancel" />
      <Button type="submit" :label="submitLabel" icon="pi pi-check" :loading="saving" data-testid="record-save" />
    </div>
    <JustificationDialog :prompt="justification.prompt.value" :errors="justification.errors.value" :busy="justification.busy.value" @submit="justification.submit" @cancel="justification.cancel" />
    <ConflictDialog
      v-if="conflict && definition"
      :payload="conflict"
      :submitted="conflictSubmitted"
      :definition="definition"
      :references="references"
      @reload="onConflictReload"
      @resolve="onConflictResolve"
      @cancel="conflict = null"
    />
  </form>
</template>
