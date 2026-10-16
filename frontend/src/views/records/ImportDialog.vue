<script setup lang="ts">
import Button from 'primevue/button'
import Column from 'primevue/column'
import DataTable from 'primevue/datatable'
import Dialog from 'primevue/dialog'
import InputText from 'primevue/inputtext'
import Message from 'primevue/message'
import ProgressBar from 'primevue/progressbar'
import RadioButton from 'primevue/radiobutton'
import Select from 'primevue/select'
import Tag from 'primevue/tag'
import { computed, onBeforeUnmount, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { ApiError, ensureCsrf, get, http, send } from '@/api/http'
import ConfigField from '@/components/config/ConfigField.vue'
import EmptyState from '@/components/config/EmptyState.vue'
import JustificationDialog from '@/runtime/JustificationDialog.vue'
import { joinList } from '@/runtime/lists'
import { downloadBlob } from '@/runtime/api'
import { useJustification } from '@/runtime/useJustification'

/**
 * Import of records from Excel or CSV as a background job (specification
 * §4.15): choose a file, map its columns to fields (suggested from the
 * headers, or taken from a saved mapping), choose whether rows add records,
 * update them or both, check the first rows, then run the import or a dry
 * run and follow its progress. A finished import offers its error report.
 */
const props = defineProps<{ form: string; formKey: string }>()
const emit = defineEmits<{ close: []; imported: [] }>()
const { t, locale } = useI18n()

type Mode = 'insert' | 'update' | 'upsert'
interface ColumnMap {
  column: number
  header: string
  field: string | null
  system: 'id' | 'version' | null
  transform: 'none' | 'trim' | 'upper' | 'lower'
  samples: (string | null)[]
}
interface Target {
  uuid: string
  label: string
  required: boolean
  key_candidate: boolean
}
interface SavedMapping {
  uuid: string
  name: string
  mode: Mode
  key_field: string | null
  mapping: { column_header: string; field_uuid: string | null; system: 'id' | 'version' | null; transform: ColumnMap['transform'] }[]
  mine: boolean
}
interface Inspection {
  file: string
  name: string
  total_rows: number
  max_rows: number
  columns: ColumnMap[]
  fields: Target[]
  mappings: SavedMapping[]
}
interface Problem {
  column: string | null
  messages: string[]
}
interface Preview {
  rows: { row: number; action: 'create' | 'update'; values: (string | null)[]; errors: Problem[] }[]
  valid: number
  invalid: number
}
interface Job {
  uuid: string
  status: 'queued' | 'validating' | 'running' | 'completed' | 'completed_with_errors' | 'failed' | 'cancelled'
  dry_run: boolean
  total_rows: number
  processed_rows: number
  progress: number
  created: number
  updated: number
  errors: number
  has_report: boolean
  error: string | null
}

const visible = ref(true)
const step = ref<'file' | 'columns' | 'check' | 'run'>('file')
const busy = ref(false)
const error = ref('')
const input = ref<HTMLInputElement | null>(null)
const inspection = ref<Inspection | null>(null)
const columns = ref<ColumnMap[]>([])
const mode = ref<Mode>('insert')
const keyField = ref<string | null>(null)
const savedMapping = ref<string | null>(null)
const saveAs = ref('')
const preview = ref<Preview | null>(null)
const job = ref<Job | null>(null)
const justification = useJustification()
let timer: ReturnType<typeof setTimeout> | null = null

const steps = computed(() => [
  { key: 'file', label: t('records.imp.step_file') },
  { key: 'columns', label: t('records.imp.step_columns') },
  { key: 'check', label: t('records.imp.step_check') },
  { key: 'run', label: t('records.imp.step_run') },
])
const stepIndex = computed(() => steps.value.findIndex((s) => s.key === step.value))
const running = computed(() => !!job.value && ['queued', 'validating', 'running'].includes(job.value.status))
const modes = computed(() => (['insert', 'update', 'upsert'] as const).map((m) => ({ value: m, label: t(`records.imp.mode.${m}`), help: t(`records.imp.mode_help.${m}`) })))
const transforms = computed(() => (['none', 'trim', 'upper', 'lower'] as const).map((v) => ({ value: v, label: t(`records.imp.transform.${v}`) })))
/** What a column can fill: nothing, a field, or (when updating) the record ID or version. */
const targets = computed(() => {
  const fields = (inspection.value?.fields ?? []).map((f) => ({ value: `f:${f.uuid}`, label: f.required ? t('records.imp.required_field', { name: f.label }) : f.label }))
  const system =
    mode.value === 'insert'
      ? []
      : [
          { value: 's:id', label: t('records.imp.system.id') },
          { value: 's:version', label: t('records.imp.system.version') },
        ]
  return [{ value: '', label: t('records.imp.skip_column') }, ...system, ...fields]
})
const keyOptions = computed(() => [
  { value: null, label: t('records.imp.key_by_id') },
  ...(inspection.value?.fields ?? []).filter((f) => f.key_candidate).map((f) => ({ value: f.uuid as string | null, label: f.label })),
])
const mapped = computed(() => columns.value.filter((c) => c.field || c.system))
const missingRequired = computed(() =>
  mode.value === 'update' ? [] : (inspection.value?.fields ?? []).filter((f) => f.required && !columns.value.some((c) => c.field === f.uuid)).map((f) => f.label),
)
const tooMany = computed(() => !!inspection.value && inspection.value.total_rows > inspection.value.max_rows)
const canCheck = computed(() => mapped.value.some((c) => c.field) && !tooMany.value && (mode.value === 'insert' || !!keyField.value || columns.value.some((c) => c.system === 'id')))
const savedOptions = computed(() => [{ value: null, label: t('records.imp.no_saved_mapping') }, ...(inspection.value?.mappings ?? []).map((m) => ({ value: m.uuid as string | null, label: m.name }))])

function targetOf(c: ColumnMap): string {
  return c.field ? `f:${c.field}` : c.system ? `s:${c.system}` : ''
}
function setTarget(c: ColumnMap, value: string): void {
  c.field = value.startsWith('f:') ? value.slice(2) : null
  c.system = value.startsWith('s:') ? (value.slice(2) as 'id' | 'version') : null
}

watch(mode, (m) => {
  if (m === 'insert') {
    keyField.value = null
    for (const c of columns.value) c.system = null
  }
})

/** Applies a saved mapping by column header; columns it does not name are left as they are. */
watch(savedMapping, (uuid) => {
  const saved = inspection.value?.mappings.find((m) => m.uuid === uuid)
  if (!saved) return
  mode.value = saved.mode
  const byHeader = new Map(saved.mapping.map((m) => [m.column_header.trim().toLowerCase(), m]))
  for (const c of columns.value) {
    const m = byHeader.get(c.header.trim().toLowerCase())
    if (m) {
      c.field = m.field_uuid
      c.system = m.system
      c.transform = m.transform ?? 'none'
    }
  }
  keyField.value = saved.key_field
  saveAs.value = saved.name
})

function message(e: unknown, fallback: string): string {
  if (e instanceof ApiError) return Object.values(e.fieldErrors)[0] ?? e.message
  return fallback
}

async function template(): Promise<void> {
  try {
    await downloadBlob(`/r/${props.form}/import/template`, {}, `${props.formKey}-import-template.xlsx`)
  } catch {
    error.value = t('records.imp.template_failed')
  }
}

async function pick(e: Event): Promise<void> {
  const file = (e.target as HTMLInputElement).files?.[0]
  if (!file) return
  busy.value = true
  error.value = ''
  try {
    await ensureCsrf()
    const body = new FormData()
    body.append('file', file)
    inspection.value = (await http.post<{ data: Inspection }>(`/r/${props.form}/imports/inspect`, body, { timeout: 300000 })).data.data
    columns.value = inspection.value.columns.map((c) => ({ ...c }))
    savedMapping.value = null
    step.value = 'columns'
  } catch (err) {
    error.value = message(err, t('records.imp.inspect_failed'))
  } finally {
    busy.value = false
    if (input.value) input.value.value = ''
  }
}

function payload(): Record<string, unknown> {
  return {
    file: inspection.value?.file,
    mode: mode.value,
    key_field: mode.value === 'insert' ? null : keyField.value,
    mapping: mapped.value.map((c) => ({ column: c.column, field: c.field, system: c.system, transform: c.transform })),
  }
}

async function check(): Promise<void> {
  busy.value = true
  error.value = ''
  try {
    preview.value = (await send<{ data: Preview }>('post', `/r/${props.form}/imports/preview`, payload())).data
    step.value = 'check'
  } catch (e) {
    error.value = message(e, t('records.imp.preview_failed'))
  } finally {
    busy.value = false
  }
}

async function start(dryRun: boolean): Promise<void> {
  busy.value = true
  error.value = ''
  try {
    const started = await justification.run((j) =>
      send<{ data: Job }>('post', `/r/${props.form}/imports`, {
        ...payload(),
        dry_run: dryRun,
        import_mapping: savedMapping.value,
        save_as: !dryRun && saveAs.value.trim() ? saveAs.value.trim() : null,
        ...(j ? { justification: j } : {}),
      }),
    )
    if (!started) return
    job.value = started.data
    step.value = 'run'
    poll()
  } catch (e) {
    error.value = message(e, t('records.imp.start_failed'))
  } finally {
    busy.value = false
  }
}

function poll(): void {
  if (timer) clearTimeout(timer)
  if (!job.value || !running.value) {
    if (job.value && !job.value.dry_run && (job.value.created || job.value.updated)) emit('imported')
    return
  }
  timer = setTimeout(async () => {
    try {
      job.value = (await get<{ data: Job }>(`/imports/${job.value!.uuid}`)).data
    } catch {
      // A missed poll is retried on the next tick.
    }
    poll()
  }, 1500)
}

async function cancel(): Promise<void> {
  if (!job.value) return
  try {
    job.value = (await send<{ data: Job }>('post', `/imports/${job.value.uuid}/cancel`, {})).data
  } catch (e) {
    error.value = message(e, t('records.imp.cancel_failed'))
  }
}

async function report(): Promise<void> {
  if (!job.value) return
  try {
    const url = (await get<{ data: { url: string } }>(`/imports/${job.value.uuid}/report`)).data.url
    window.location.assign(url)
  } catch (e) {
    error.value = message(e, t('records.imp.report_failed'))
  }
}

function again(): void {
  job.value = null
  step.value = 'check'
}

function close(): void {
  if (timer) clearTimeout(timer)
  visible.value = false
  emit('close')
}
onBeforeUnmount(() => timer && clearTimeout(timer))

const errorRows = computed(() =>
  (preview.value?.rows ?? []).flatMap((r) => r.errors.map((e, i) => ({ key: `${r.row}-${i}`, row: r.row, column: e.column ?? t('records.imp.whole_row'), messages: e.messages.join(' ') }))),
)
const outcome = computed(() => {
  const j = job.value
  if (!j) return ''
  const n = { created: j.created, updated: j.updated, errors: j.errors }
  if (j.status === 'failed') return j.error ?? t('records.imp.outcome.failed')
  return t(`records.imp.outcome.${j.dry_run ? 'dry_' : ''}${j.status}`, n)
})
</script>

<template>
  <Dialog
    :visible="visible"
    modal
    :header="t('records.imp.title')"
    :style="{ width: '60rem' }"
    :breakpoints="{ '960px': '95vw' }"
    data-testid="import-dialog"
    @update:visible="(v: boolean) => !v && close()"
  >
    <ol class="imp-steps" :aria-label="t('records.imp.steps')">
      <li v-for="(s, i) in steps" :key="s.key" :class="{ 'is-current': i === stepIndex, 'is-done': i < stepIndex }" :aria-current="i === stepIndex ? 'step' : undefined">
        <span class="imp-step-n">{{ i + 1 }}</span
        >{{ s.label }}
      </li>
    </ol>

    <Message v-if="error" severity="error" class="mb-4" data-testid="import-error">{{ error }}</Message>

    <!-- 1. File -->
    <section v-if="step === 'file'" class="dlg-form">
      <p class="text-muted-color">{{ t('records.imp.file_help') }}</p>
      <div class="flex flex-wrap gap-2">
        <input ref="input" type="file" accept=".xlsx,.csv,text/csv,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet" class="sr-only" data-testid="import-file" @change="pick" />
        <Button :label="t('records.imp.choose')" icon="pi pi-paperclip" :loading="busy" data-testid="import-choose" @click="input?.click()" />
        <Button :label="t('records.imp.download_template')" icon="pi pi-file-excel" severity="secondary" outlined @click="template" />
      </div>
    </section>

    <!-- 2. Columns -->
    <section v-else-if="step === 'columns' && inspection" class="dlg-form" data-testid="import-columns">
      <p class="text-sm">
        <span dir="auto" class="font-medium">{{ inspection.name }}</span>
        <span class="text-muted-color"> · {{ t('records.imp.rows', { n: inspection.total_rows }) }}</span>
      </p>
      <Message v-if="tooMany" severity="error">{{ t('records.imp.too_many', { max: inspection.max_rows }) }}</Message>

      <div class="dlg-group">
        <h3 class="dlg-heading">{{ t('records.imp.what_rows_do') }}</h3>
        <div class="flex flex-col gap-2">
          <label v-for="m in modes" :key="m.value" class="flex items-start gap-2">
            <RadioButton v-model="mode" :value="m.value" :input-id="`imp-mode-${m.value}`" :data-testid="`import-mode-${m.value}`" />
            <span
              ><span class="font-medium">{{ m.label }}</span
              ><span class="block text-sm text-muted-color">{{ m.help }}</span></span
            >
          </label>
        </div>
        <ConfigField v-if="mode !== 'insert'" class="mt-3" :label="t('records.imp.key_field')" for="imp-key" width="md" :hint="t('records.imp.key_help')">
          <Select v-model="keyField" input-id="imp-key" :options="keyOptions" option-label="label" option-value="value" size="small" data-testid="import-key" />
        </ConfigField>
      </div>

      <div class="dlg-group">
        <div class="flex flex-wrap items-end justify-between gap-2">
          <h3 class="dlg-heading">{{ t('records.imp.columns') }}</h3>
          <ConfigField v-if="inspection.mappings.length" :label="t('records.imp.saved_mapping')" for="imp-saved" width="sm">
            <Select v-model="savedMapping" input-id="imp-saved" :options="savedOptions" option-label="label" option-value="value" size="small" />
          </ConfigField>
        </div>
        <DataTable :value="columns" data-key="column" size="small" scrollable scroll-height="22rem">
          <template #empty><EmptyState icon="pi pi-table" :title="t('records.imp.no_columns_title')" :description="t('records.imp.no_columns_text')" /></template>
          <Column :header="t('records.imp.file_column')" style="min-width: 12rem">
            <template #body="{ data }">
              <span class="font-medium" dir="auto">{{ data.header }}</span>
              <span class="block text-xs text-muted-color truncate max-w-[16rem]" dir="auto">{{
                data.samples
                  .filter((s: string | null) => s)
                  .slice(0, 3)
                  .join(' · ')
              }}</span>
            </template>
          </Column>
          <Column :header="t('records.imp.fills')" style="min-width: 14rem">
            <template #body="{ data }">
              <Select
                :model-value="targetOf(data)"
                :options="targets"
                option-label="label"
                option-value="value"
                filter
                class="w-full"
                :aria-label="t('records.imp.fills_for', { column: data.header })"
                :data-testid="`import-target-${data.column}`"
                @update:model-value="(v: string) => setTarget(data, v)"
              />
            </template>
          </Column>
          <Column :header="t('records.imp.cleanup')" style="width: 11rem">
            <template #body="{ data }">
              <Select
                v-model="data.transform"
                :options="transforms"
                option-label="label"
                option-value="value"
                class="w-full"
                :disabled="!data.field"
                :aria-label="t('records.imp.cleanup_for', { column: data.header })"
              />
            </template>
          </Column>
        </DataTable>
        <Message v-if="missingRequired.length" severity="warn" class="mt-2">{{ t('records.imp.missing_required', { names: joinList(missingRequired, locale) }) }}</Message>
      </div>
    </section>

    <!-- 3. Check -->
    <section v-else-if="step === 'check' && preview" class="dlg-form" data-testid="import-preview">
      <p class="text-muted-color">{{ t('records.imp.check_help', { n: preview.rows.length }) }}</p>
      <div class="flex flex-wrap gap-2">
        <Tag severity="success" :value="t('records.imp.valid', { n: preview.valid })" />
        <Tag v-if="preview.invalid" severity="danger" :value="t('records.imp.invalid', { n: preview.invalid })" />
      </div>
      <DataTable v-if="errorRows.length" :value="errorRows" data-key="key" size="small" scrollable scroll-height="16rem">
        <template #empty><EmptyState icon="pi pi-check-circle" :title="t('records.imp.preview_clean')" /></template>
        <Column field="row" :header="t('records.imp.row')" style="width: 5rem" />
        <Column :header="t('records.imp.column')">
          <template #body="{ data }"
            ><span dir="auto">{{ data.column }}</span></template
          >
        </Column>
        <Column field="messages" :header="t('records.imp.problems')" />
      </DataTable>
      <p v-else class="text-sm">{{ t('records.imp.preview_clean') }}</p>
      <ConfigField :label="t('records.imp.save_as')" for="imp-save-as" width="md" :hint="t('records.imp.save_as_help')">
        <InputText id="imp-save-as" v-model="saveAs" size="small" maxlength="255" />
      </ConfigField>
    </section>

    <!-- 4. Run -->
    <section v-else-if="step === 'run' && job" class="dlg-form" data-testid="import-progress">
      <p class="font-medium">{{ job.dry_run ? t('records.imp.dry_running') : t('records.imp.running') }}</p>
      <ProgressBar :value="job.progress" :show-value="true" :aria-label="t('records.imp.progress')" />
      <p class="text-sm text-muted-color">{{ t('records.imp.processed', { done: job.processed_rows, total: job.total_rows }) }}</p>
      <div class="flex flex-wrap gap-2">
        <Tag severity="success" :value="t(job.dry_run ? 'records.imp.would_create' : 'records.imp.created', { n: job.created })" />
        <Tag severity="info" :value="t(job.dry_run ? 'records.imp.would_update' : 'records.imp.updated', { n: job.updated })" />
        <Tag v-if="job.errors" severity="danger" :value="t('records.imp.errors', { n: job.errors })" />
      </div>
      <Message v-if="!running" :severity="job.status === 'completed' ? 'success' : job.status === 'failed' ? 'error' : 'warn'" data-testid="import-outcome">{{ outcome }}</Message>
      <p v-if="running" class="text-sm text-muted-color">{{ t('records.imp.can_close') }}</p>
    </section>

    <template #footer>
      <div class="action-bar w-full">
        <Button v-if="step === 'columns'" :label="t('records.imp.back')" severity="secondary" text @click="step = 'file'" />
        <Button v-if="step === 'check'" :label="t('records.imp.back')" severity="secondary" text @click="step = 'columns'" />
        <span class="flex-1" />
        <Button :label="running || step !== 'run' ? t('common.cancel') : t('records.close')" severity="secondary" @click="close" />
        <Button v-if="step === 'columns'" :label="t('records.imp.check')" icon="pi pi-check-square" :disabled="!canCheck" :loading="busy" data-testid="import-check" @click="check" />
        <template v-if="step === 'check'">
          <Button :label="t('records.imp.dry_run')" severity="secondary" outlined :loading="busy" data-testid="import-dry-run" @click="start(true)" />
          <Button :label="t('records.imp.start')" icon="pi pi-upload" :loading="busy" data-testid="import-start" @click="start(false)" />
        </template>
        <template v-if="step === 'run' && job">
          <Button v-if="running" :label="t('records.imp.cancel_import')" severity="danger" outlined data-testid="import-cancel" @click="cancel" />
          <Button v-if="job.has_report" :label="t('records.imp.download_report')" icon="pi pi-download" severity="secondary" data-testid="import-report" @click="report" />
          <Button v-if="!running && job.dry_run" :label="t('records.imp.back_to_check')" @click="again" />
        </template>
      </div>
    </template>
    <JustificationDialog :prompt="justification.prompt.value" :errors="justification.errors.value" :busy="justification.busy.value" @submit="justification.submit" @cancel="justification.cancel" />
  </Dialog>
</template>

<style scoped>
.imp-steps {
  display: flex;
  flex-wrap: wrap;
  gap: 0.5rem 1.25rem;
  margin-block-end: 1.25rem;
  font-size: 0.875rem;
  color: var(--text-muted);
}
.imp-steps li {
  display: flex;
  align-items: center;
  gap: 0.4rem;
}
.imp-step-n {
  display: inline-grid;
  place-items: center;
  width: 1.4rem;
  height: 1.4rem;
  border-radius: 999px;
  border: 1px solid var(--border);
  font-size: 0.75rem;
}
.imp-steps .is-current {
  color: var(--text);
  font-weight: 600;
}
.imp-steps .is-current .imp-step-n,
.imp-steps .is-done .imp-step-n {
  background: var(--primary);
  border-color: var(--primary);
  color: var(--on-primary);
}
</style>
