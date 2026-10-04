<script setup lang="ts">
import Button from 'primevue/button'
import Checkbox from 'primevue/checkbox'
import Column from 'primevue/column'
import DataTable from 'primevue/datatable'
import Dialog from 'primevue/dialog'
import Message from 'primevue/message'
import Tag from 'primevue/tag'
import { computed, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { ApiError, ensureCsrf, http } from '@/api/http'
import { downloadBlob } from '@/runtime/api'

/**
 * Import of records from Excel or CSV: download the template, validate the
 * file (nothing is written), review the report per row, then commit, with
 * the option to skip invalid rows.
 */
const props = defineProps<{ form: string; formKey: string }>()
const emit = defineEmits<{ close: []; imported: [] }>()
const { t } = useI18n()

interface ImportReport {
  format: string
  columns: { header: string; field: string | null; system: string | null }[]
  ignored: string[]
  total: number
  valid: number
  invalid: number
  committed: boolean
  created: number
  updated: number
  unchanged: number
  already_imported: number
  failed: number
  rows: { row: number; action: 'create' | 'update'; errors: { column: string; field: string; messages: string[] }[] }[]
}

const visible = ref(true)
const file = ref<File | null>(null)
const skipInvalid = ref(false)
const busy = ref(false)
const report = ref<ImportReport | null>(null)
const error = ref('')
const input = ref<HTMLInputElement | null>(null)

const errorRows = computed(() =>
  (report.value?.rows ?? []).flatMap((r) => r.errors.map((e, i) => ({ key: `${r.row}-${i}`, row: r.row, action: r.action, column: e.column, messages: e.messages.join(' ') }))),
)
const canCommit = computed(() => !!report.value && !report.value.committed && report.value.total > 0 && (report.value.invalid === 0 || (skipInvalid.value && report.value.valid > 0)))

function close(): void {
  visible.value = false
  emit('close')
}

async function template(): Promise<void> {
  try {
    await downloadBlob(`/r/${props.form}/import/template`, {}, `${props.formKey}-import-template.xlsx`)
  } catch {
    error.value = t('records.import_template_failed')
  }
}

function pick(e: Event): void {
  file.value = (e.target as HTMLInputElement).files?.[0] ?? null
  report.value = null
  error.value = ''
}

async function run(commit: boolean): Promise<void> {
  if (!file.value) return
  busy.value = true
  error.value = ''
  try {
    await ensureCsrf()
    const body = new FormData()
    body.append('file', file.value)
    body.append('commit', commit ? '1' : '0')
    body.append('skip_invalid', skipInvalid.value ? '1' : '0')
    report.value = (await http.post<{ data: ImportReport }>(`/r/${props.form}/import`, body, { timeout: 600000 })).data.data
    if (commit && report.value.committed) emit('imported')
  } catch (e) {
    error.value = e instanceof ApiError ? (Object.values(e.fieldErrors)[0] ?? e.message) : t('records.import_failed')
  } finally {
    busy.value = false
  }
}
</script>

<template>
  <Dialog :visible="visible" modal :header="t('records.import_title')" :style="{ width: '56rem' }" :breakpoints="{ '960px': '95vw' }" @update:visible="(v: boolean) => !v && close()">
    <ol class="flex flex-col gap-4">
      <li>
        <p class="mb-2">{{ t('records.import_step_template') }}</p>
        <Button :label="t('records.import_download_template')" icon="pi pi-file-excel" severity="secondary" outlined size="small" @click="template" />
      </li>
      <li>
        <p class="mb-2">{{ t('records.import_step_file') }}</p>
        <div class="flex flex-wrap items-center gap-3">
          <input ref="input" type="file" accept=".xlsx,.csv,text/csv,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet" class="sr-only" data-testid="import-file" @change="pick" />
          <Button :label="t('records.import_choose')" icon="pi pi-paperclip" size="small" outlined @click="input?.click()" />
          <span v-if="file" class="text-sm" dir="auto">{{ file.name }}</span>
          <label class="flex items-center gap-2 text-sm">
            <Checkbox v-model="skipInvalid" binary input-id="skip-invalid" />
            <span>{{ t('records.import_skip_invalid') }}</span>
          </label>
          <Button :label="t('records.import_validate')" icon="pi pi-check-square" size="small" :disabled="!file" :loading="busy && !report" data-testid="import-validate" @click="run(false)" />
        </div>
      </li>
    </ol>

    <Message v-if="error" severity="error" class="mt-4">{{ error }}</Message>

    <div v-if="report" class="mt-4 flex flex-col gap-3" data-testid="import-report">
      <div class="flex flex-wrap gap-2">
        <Tag severity="secondary" :value="t('records.import_total', { n: report.total })" />
        <Tag severity="success" :value="t('records.import_valid', { n: report.valid })" />
        <Tag v-if="report.invalid" severity="danger" :value="t('records.import_invalid', { n: report.invalid })" />
        <template v-if="report.committed">
          <Tag severity="success" :value="t('records.import_created', { n: report.created })" />
          <Tag severity="info" :value="t('records.import_updated', { n: report.updated })" />
          <Tag severity="secondary" :value="t('records.import_unchanged', { n: report.unchanged })" />
          <Tag v-if="report.already_imported" severity="secondary" :value="t('records.import_already', { n: report.already_imported })" />
          <Tag v-if="report.failed" severity="danger" :value="t('records.import_failed_rows', { n: report.failed })" />
        </template>
      </div>
      <Message v-if="report.committed" severity="success">{{ t('records.import_done') }}</Message>
      <Message v-else-if="report.invalid && !skipInvalid" severity="warn">{{ t('records.import_fix_or_skip') }}</Message>
      <p v-if="report.ignored.length" class="text-sm text-muted-color">{{ t('records.import_ignored', { columns: report.ignored.join(', ') }) }}</p>
      <details>
        <summary class="cursor-pointer text-sm">{{ t('records.import_mapping') }}</summary>
        <ul class="text-sm mt-2 grid sm:grid-cols-2 gap-1">
          <li v-for="c in report.columns" :key="c.header" dir="auto">
            {{ c.header }} → <span class="ltr-value">{{ c.field ?? c.system ?? '—' }}</span>
          </li>
        </ul>
      </details>
      <DataTable v-if="errorRows.length" :value="errorRows" data-key="key" size="small" scrollable scroll-height="18rem">
        <Column field="row" :header="t('records.import_row')" style="width: 5rem" />
        <Column :header="t('records.import_action')" style="width: 7rem">
          <template #body="{ data }">{{ data.action === 'create' ? t('records.import_action_create') : t('records.import_action_update') }}</template>
        </Column>
        <Column field="column" :header="t('records.import_column')" />
        <Column field="messages" :header="t('records.import_errors')" />
      </DataTable>
    </div>

    <template #footer>
      <Button :label="report?.committed ? t('records.close') : t('common.cancel')" severity="secondary" @click="close" />
      <Button v-if="!report?.committed" :label="t('records.import_commit')" icon="pi pi-upload" :disabled="!canCommit" :loading="busy && !!report" data-testid="import-commit" @click="run(true)" />
    </template>
  </Dialog>
</template>
