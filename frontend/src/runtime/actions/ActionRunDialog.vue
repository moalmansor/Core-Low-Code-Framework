<script setup lang="ts">
import Button from 'primevue/button'
import Dialog from 'primevue/dialog'
import InputText from 'primevue/inputtext'
import Message from 'primevue/message'
import ProgressBar from 'primevue/progressbar'
import Select from 'primevue/select'
import ToggleSwitch from 'primevue/toggleswitch'
import { useToast } from 'primevue/usetoast'
import { computed, onBeforeUnmount, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRouter } from 'vue-router'
import { ApiError, get, http } from '@/api/http'
import { errorText } from '@/views/admin/building/shared'
import JustificationDialog from '../JustificationDialog.vue'
import { useJustification } from '../useJustification'
import { actionsRuntimeApi, finished, type ActionRun, type ActionTarget, type AvailableAction, type Preparation, type RunOutput } from './api'

/**
 * Runs an action (specification §4.15 "Execution"): asks for confirmation
 * and the inputs a built-in needs (the fields and values of a bulk update,
 * the transition of a status change), then for one justification for the
 * whole run when one is needed; follows a background run's progress; and
 * reports what happened — records it could not change and why, documents
 * to download, records to open.
 */
const emit = defineEmits<{ done: [run: ActionRun] }>()
const { t, n } = useI18n()
const toast = useToast()
const router = useRouter()
const justification = useJustification()

const visible = ref(false)
const form = ref('')
const action = ref<AvailableAction | null>(null)
const target = ref<ActionTarget>({})
const prep = ref<Preparation | null>(null)
const run = ref<ActionRun | null>(null)
const busy = ref(false)
const error = ref('')
// Inputs of built-ins.
const fieldKey = ref<string | null>(null)
const fieldValue = ref<unknown>(null)
const transition = ref<string | null>(null)
const bulkFields = computed(() => prep.value?.inputs?.fields ?? [])
const chosenField = computed(() => bulkFields.value.find((f) => f.key === fieldKey.value) ?? null)
const fieldKind = computed(() => {
  const type = chosenField.value?.type ?? ''
  if (chosenField.value?.options.length) return 'options'
  if (['checkbox', 'boolean', 'switch'].includes(type)) return 'boolean'
  if (['date'].includes(type)) return 'date'
  if (['number', 'decimal', 'currency', 'percent'].includes(type)) return 'number'
  return 'text'
})
const inputs = computed<Record<string, unknown>>(() => {
  if (action.value?.builtin === 'bulk_update') return fieldKey.value ? { values: { [fieldKey.value]: fieldValue.value === '' ? null : fieldValue.value } } : {}
  if (action.value?.builtin === 'bulk_status') return transition.value ? { transition: transition.value } : {}
  return {}
})
const needsInputs = computed(() => action.value?.builtin === 'bulk_update' || action.value?.builtin === 'bulk_status')
const ready = computed(() => !needsInputs.value || Object.keys(inputs.value).length > 0)
let poll: ReturnType<typeof setTimeout> | null = null
onBeforeUnmount(() => poll && clearTimeout(poll))

/** Opens the action for some records; runs it at once when there is nothing to ask. */
async function start(formUuid: string, a: AvailableAction, on: ActionTarget): Promise<void> {
  form.value = formUuid
  action.value = a
  target.value = on
  prep.value = null
  run.value = null
  error.value = ''
  fieldKey.value = null
  fieldValue.value = null
  transition.value = null
  // Print opens the record's print page with the default layout, as the record page's own Print does.
  if (a.builtin === 'print') {
    await print(formUuid, on.items?.[0]?.uuid ?? '')
    return
  }
  try {
    prep.value = await actionsRuntimeApi.prepare(formUuid, a.uuid, on)
  } catch (e) {
    toast.add({ severity: 'error', summary: errorText(e, t('act.run.failed')), life: 8000 })
    return
  }
  transition.value = prep.value.inputs?.transitions?.[0]?.uuid ?? null
  const single = (on.items?.length ?? 0) === 1
  if (!a.confirm && !needsInputs.value && single && !prep.value.queued) {
    await go()
    return
  }
  visible.value = true
}

async function print(formUuid: string, record: string): Promise<void> {
  try {
    const res = await http.get<Blob>(`/r/${formUuid}/${record}/print`, { params: { format: 'html' }, responseType: 'blob', timeout: 120000 })
    const url = URL.createObjectURL(res.data)
    window.open(url, '_blank', 'noopener')
    setTimeout(() => URL.revokeObjectURL(url), 60000)
  } catch (e) {
    toast.add({ severity: 'error', summary: errorText(e, t('act.run.failed')), life: 6000 })
  }
}

async function go(): Promise<void> {
  if (!action.value) return
  busy.value = true
  error.value = ''
  try {
    const started = await justification.run((j) => actionsRuntimeApi.run(form.value, action.value!.uuid, target.value, inputs.value, j))
    if (started === null) return
    run.value = started
    visible.value = true
    if (!finished(started)) follow(started.uuid)
    else done(started)
  } catch (e) {
    error.value = e instanceof ApiError ? (Object.values(e.fieldErrors)[0] ?? e.message) : errorText(e, t('act.run.failed'))
    visible.value = true
  } finally {
    busy.value = false
  }
}

function follow(uuid: string): void {
  poll = setTimeout(async () => {
    try {
      run.value = await actionsRuntimeApi.status(uuid)
    } catch {
      // A missed poll is retried.
    }
    if (run.value && finished(run.value)) done(run.value)
    else follow(uuid)
  }, 1500)
}

function done(r: ActionRun): void {
  emit('done', r)
  const opened = r.outputs.find((o) => o.type === 'open')
  if (r.status === 'completed' && r.outputs.filter((o) => o.type !== 'warning').length <= 1 && !r.outputs.some((o) => o.type === 'warning')) {
    toast.add({ severity: 'success', summary: r.message || t('act.run.done', { n: n(r.affected) }), life: 5000 })
    visible.value = false
    const download = r.outputs.find((o) => o.type === 'download')
    if (download) void openOutput(download)
    else if (opened) void openOutput(opened)
  }
}

async function cancel(): Promise<void> {
  if (run.value && !finished(run.value)) run.value = await actionsRuntimeApi.cancel(run.value.uuid).catch(() => run.value)
}

async function openOutput(o: RunOutput): Promise<void> {
  if (o.type === 'open' && o.url) {
    visible.value = false
    await router.push(o.url)
  } else if (o.type === 'download' && o.file) {
    try {
      const { data } = await get<{ data: { url: string } }>(`/files/${o.file}/url`)
      window.location.assign(data.url)
    } catch (e) {
      toast.add({ severity: 'error', summary: errorText(e, t('act.run.download_failed')), life: 6000 })
    }
  }
}

defineExpose({ start })
</script>

<template>
  <Dialog v-model:visible="visible" modal :header="action?.label" class="w-full max-w-lg" :closable="!busy" data-testid="action-dialog">
    <div class="flex flex-col gap-4">
      <!-- Before running -->
      <template v-if="!run && prep">
        <p class="m-0 text-sm">{{ t('act.run.count', { n: n(prep.count) }) }}</p>
        <Message v-if="prep.queued" severity="info" :closable="false">{{ t('act.run.background') }}</Message>
        <p v-if="action?.confirmation" class="m-0 whitespace-pre-line" dir="auto">{{ action.confirmation }}</p>
        <template v-if="action?.builtin === 'bulk_update'">
          <div class="field">
            <label for="act-run-field" class="text-sm font-medium">{{ t('act.run.field') }}</label>
            <Select
              v-model="fieldKey"
              input-id="act-run-field"
              :options="bulkFields"
              option-label="label"
              option-value="key"
              filter
              size="small"
              class="w-full"
              data-testid="action-field"
              @update:model-value="fieldValue = null"
            />
          </div>
          <div v-if="chosenField" class="field">
            <label for="act-run-value" class="text-sm font-medium">{{ t('act.run.value') }}</label>
            <Select
              v-if="fieldKind === 'options'"
              v-model="fieldValue"
              input-id="act-run-value"
              :options="chosenField.options"
              option-label="label"
              option-value="value"
              show-clear
              size="small"
              class="w-full"
              data-testid="action-value"
            />
            <ToggleSwitch v-else-if="fieldKind === 'boolean'" v-model="fieldValue as boolean" input-id="act-run-value" />
            <InputText
              v-else
              :id="'act-run-value'"
              v-model="fieldValue as string"
              :type="fieldKind === 'date' ? 'date' : 'text'"
              :inputmode="fieldKind === 'number' ? 'decimal' : undefined"
              size="small"
              class="w-full"
              data-testid="action-value"
            />
            <p class="m-0 mt-1 text-xs text-muted-color">{{ t('act.run.value_hint') }}</p>
          </div>
        </template>
        <div v-if="action?.builtin === 'bulk_status'" class="field">
          <label for="act-run-tr" class="text-sm font-medium">{{ t('act.run.transition') }}</label>
          <Select v-model="transition" input-id="act-run-tr" :options="prep.inputs?.transitions ?? []" option-label="label" option-value="uuid" size="small" class="w-full" />
          <p v-if="!(prep.inputs?.transitions ?? []).length" class="m-0 mt-1 text-xs text-muted-color">{{ t('act.run.no_transitions') }}</p>
        </div>
        <Message v-if="error" severity="error" :closable="false">{{ error }}</Message>
      </template>

      <!-- Running or done -->
      <template v-if="run">
        <template v-if="!['completed', 'completed_with_errors', 'failed', 'cancelled'].includes(run.status)">
          <p class="m-0 text-sm">{{ t('act.run.running', { n: n(run.count) }) }}</p>
          <ProgressBar :value="run.progress" data-testid="action-progress" />
          <p class="m-0 text-xs text-muted-color">{{ t('act.run.leave_open') }}</p>
        </template>
        <template v-else>
          <Message :severity="run.status === 'completed' ? 'success' : run.status === 'failed' ? 'error' : 'warn'" :closable="false" data-testid="action-result">
            {{ run.error ?? (run.status === 'cancelled' ? t('act.run.cancelled', { n: n(run.affected) }) : run.message || t('act.run.done', { n: n(run.affected) })) }}
          </Message>
          <div v-if="run.errors.length" class="flex flex-col gap-1">
            <p class="m-0 text-sm font-medium">{{ t('act.run.not_changed', { n: n(run.failed) }) }}</p>
            <ul class="m-0 ps-5 text-sm max-h-48 overflow-auto">
              <li v-for="e in run.errors" :key="e.uuid">
                <RouterLink :to="`/app/${form}/${e.uuid}`" class="text-primary">{{ t('act.run.record') }}</RouterLink
                >: {{ e.error }}
              </li>
            </ul>
          </div>
          <ul v-if="run.outputs.length" class="m-0 ps-0 list-none flex flex-col gap-1 text-sm">
            <li v-for="(o, i) in run.outputs" :key="i">
              <Button v-if="o.type === 'download'" icon="pi pi-download" :label="o.name" link size="small" class="!p-0" @click="openOutput(o)" />
              <Button v-else-if="o.type === 'open'" icon="pi pi-external-link" :label="t('act.run.open')" link size="small" class="!p-0" @click="openOutput(o)" />
              <span v-else class="text-warning">{{ o.message }}</span>
            </li>
          </ul>
        </template>
      </template>
    </div>
    <template #footer>
      <template v-if="!run">
        <Button :label="t('common.cancel')" text :disabled="busy" @click="visible = false" />
        <Button
          :label="action?.label"
          :icon="action?.icon ? `pi ${action.icon}` : undefined"
          :severity="action?.color === 'danger' ? 'danger' : undefined"
          :loading="busy"
          :disabled="!ready"
          data-testid="action-run"
          @click="go"
        />
      </template>
      <template v-else-if="!['completed', 'completed_with_errors', 'failed', 'cancelled'].includes(run.status)">
        <Button :label="t('act.run.stop')" severity="secondary" outlined @click="cancel" />
      </template>
      <Button v-else :label="t('common.close')" data-testid="action-close" @click="visible = false" />
    </template>
  </Dialog>
  <JustificationDialog :prompt="justification.prompt.value" :errors="justification.errors.value" :busy="justification.busy.value" @submit="justification.submit" @cancel="justification.cancel" />
</template>
