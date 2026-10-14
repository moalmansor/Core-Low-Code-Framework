<script setup lang="ts">
import Button from 'primevue/button'
import Dialog from 'primevue/dialog'
import InputNumber from 'primevue/inputnumber'
import InputText from 'primevue/inputtext'
import Message from 'primevue/message'
import ProgressBar from 'primevue/progressbar'
import RadioButton from 'primevue/radiobutton'
import Select from 'primevue/select'
import SelectButton from 'primevue/selectbutton'
import { useToast } from 'primevue/usetoast'
import { computed, onBeforeUnmount, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { ApiError, get } from '@/api/http'
import SubjectPicker from '@/components/SubjectPicker.vue'
import { errorText } from '@/views/admin/building/shared'
import { downloadsApi, finishedJob, type AvailableProfile, type DlFormat, type DownloadJob } from '@/views/downloads/api'
import { joinList } from '@/runtime/lists'

/**
 * Downloading through a profile (specification §4.23 "Usage"): the format,
 * which records (everything the table shows, or only the selection, when
 * the profile allows it), the parameters to fill, then the run — at once
 * for small downloads, followed with a progress bar for large ones — and
 * the file. Columns the user may not see are left out, and the dialog says
 * which.
 */
const { t, locale } = useI18n()
const toast = useToast()
const visible = ref(false)
const form = ref('')
const profile = ref<AvailableProfile | null>(null)
const format = ref<DlFormat>('xlsx')
const params = ref<Record<string, unknown>>({})
const scope = ref<'all' | 'selected'>('all')
const context = ref<{ filters?: Record<string, unknown> | null; selected?: string[]; record?: string }>({})
const job = ref<DownloadJob | null>(null)
const busy = ref(false)
const errors = ref<Record<string, string>>({})
const error = ref('')
let poll: ReturnType<typeof setTimeout> | null = null
onBeforeUnmount(() => poll && clearTimeout(poll))

const formats = computed(() => (profile.value?.formats ?? []).map((f) => ({ value: f, label: t(`dl.format.${f}`) })))
const selectedCount = computed(() => context.value.selected?.length ?? 0)

/** Opens a profile for the current table (filters, selection) or one record. */
function open(formUuid: string, p: AvailableProfile, ctx: { filters?: Record<string, unknown> | null; selected?: string[]; record?: string }): void {
  form.value = formUuid
  profile.value = p
  context.value = ctx
  format.value = p.formats[0] ?? 'xlsx'
  params.value = Object.fromEntries(p.parameters.map((x) => [x.key, x.type === 'date_range' ? { from: null, to: null } : (x.default ?? null)]))
  scope.value = p.allowSelected && selectedCount.value > 0 ? 'selected' : 'all'
  job.value = null
  errors.value = {}
  error.value = ''
  visible.value = true
}

/** Shows a run started earlier (from a notification link). */
async function follow(formUuid: string, uuid: string): Promise<void> {
  form.value = formUuid
  profile.value = null
  errors.value = {}
  error.value = ''
  try {
    job.value = await downloadsApi.status(uuid)
    visible.value = true
    if (!finishedJob(job.value)) watchJob(uuid)
  } catch (e) {
    toast.add({ severity: 'error', summary: errorText(e, t('dl.run_failed')), life: 6000 })
  }
}

async function run(): Promise<void> {
  if (!profile.value) return
  busy.value = true
  errors.value = {}
  error.value = ''
  try {
    const body: Parameters<typeof downloadsApi.run>[2] = { format: format.value, parameters: params.value }
    if (context.value.record) body.record = context.value.record
    else if (scope.value === 'selected') body.uuids = context.value.selected ?? []
    else if (profile.value.applyUserFilters) body.filters = context.value.filters ?? null
    job.value = await downloadsApi.run(form.value, profile.value.uuid, body)
    if (finishedJob(job.value)) await finished(job.value)
    else watchJob(job.value.uuid)
  } catch (e) {
    if (e instanceof ApiError && e.status === 422) {
      errors.value = Object.fromEntries(Object.entries(e.fieldErrors).map(([k, v]) => [k.replace(/^parameters\./, ''), v]))
      error.value = Object.keys(errors.value).length ? '' : e.message
    } else {
      error.value = errorText(e, t('dl.run_failed'))
    }
  } finally {
    busy.value = false
  }
}

function watchJob(uuid: string): void {
  poll = setTimeout(async () => {
    try {
      job.value = await downloadsApi.status(uuid)
    } catch {
      // A missed poll is retried.
    }
    if (job.value && finishedJob(job.value)) await finished(job.value)
    else watchJob(uuid)
  }, 1500)
}

async function finished(j: DownloadJob): Promise<void> {
  if (j.status === 'completed' && j.file) await fetchFile(j.file)
}

async function fetchFile(file: string): Promise<void> {
  try {
    const { data } = await get<{ data: { url: string } }>(`/files/${file}/url`)
    window.location.assign(data.url)
  } catch (e) {
    toast.add({ severity: 'error', summary: errorText(e, t('dl.file_failed')), life: 6000 })
  }
}

async function cancel(): Promise<void> {
  if (job.value && !finishedJob(job.value)) job.value = await downloadsApi.cancel(job.value.uuid).catch(() => job.value)
}
async function retry(): Promise<void> {
  if (!job.value) return
  job.value = await downloadsApi.retry(job.value.uuid)
  if (!finishedJob(job.value)) watchJob(job.value.uuid)
  else await finished(job.value)
}
function close(): void {
  if (poll) clearTimeout(poll)
  visible.value = false
}

const numberParam = (k: string): number | null => (typeof params.value[k] === 'number' ? (params.value[k] as number) : null)
const rangeValue = (k: string, side: 'from' | 'to') => ((params.value[k] as { from: string | null; to: string | null } | null)?.[side] ?? '') as string
function setRange(k: string, side: 'from' | 'to', v: string): void {
  const now = { ...((params.value[k] as Record<string, string | null> | null) ?? { from: null, to: null }) }
  now[side] = v || null
  params.value = { ...params.value, [k]: now }
}

defineExpose({ open, follow })
</script>

<template>
  <Dialog v-model:visible="visible" modal :header="profile?.name ?? t('dl.download')" class="w-full max-w-lg" :closable="!busy" data-testid="download-dialog" @hide="close">
    <div class="flex flex-col gap-4">
      <template v-if="!job && profile">
        <p v-if="profile.description" class="m-0 text-sm text-muted-color" dir="auto">{{ profile.description }}</p>
        <div class="field">
          <span class="text-sm font-medium">{{ t('dl.format_label') }}</span>
          <SelectButton v-model="format" :options="formats" option-label="label" option-value="value" :allow-empty="false" size="small" data-testid="download-format" />
        </div>
        <fieldset v-if="!context.record && profile.allowSelected && selectedCount > 0" class="field border-0 p-0 m-0">
          <legend class="text-sm font-medium mb-1">{{ t('dl.which_records') }}</legend>
          <label class="inline-flex items-center gap-2 text-sm">
            <RadioButton v-model="scope" input-id="dl-scope-all" value="all" />{{ profile.applyUserFilters ? t('dl.scope_filtered') : t('dl.scope_all') }}
          </label>
          <label class="inline-flex items-center gap-2 text-sm">
            <RadioButton v-model="scope" input-id="dl-scope-selected" value="selected" />{{ t('dl.scope_selected', { n: selectedCount }) }}
          </label>
        </fieldset>
        <p v-else-if="!context.record && profile.applyUserFilters" class="m-0 text-xs text-muted-color">{{ t('dl.follows_filters') }}</p>
        <div v-for="p in profile.parameters" :key="p.key" class="field">
          <label :for="`dlp-${p.key}`" class="text-sm font-medium">{{ p.label }}<span v-if="p.required" class="text-danger" aria-hidden="true"> *</span></label>
          <Select v-if="p.type === 'choice'" v-model="params[p.key]" :input-id="`dlp-${p.key}`" :options="p.options" option-label="label" option-value="value" show-clear size="small" class="w-full" />
          <InputNumber
            v-else-if="p.type === 'number'"
            :model-value="numberParam(p.key)"
            :input-id="`dlp-${p.key}`"
            size="small"
            fluid
            @update:model-value="(v: number | null) => (params[p.key] = v)"
          />
          <InputText v-else-if="p.type === 'date'" :id="`dlp-${p.key}`" v-model="params[p.key] as string" type="date" size="small" class="w-full" :data-testid="`download-param-${p.key}`" />
          <div v-else-if="p.type === 'date_range'" class="flex gap-2">
            <InputText
              :id="`dlp-${p.key}`"
              :model-value="rangeValue(p.key, 'from')"
              type="date"
              size="small"
              class="flex-1"
              :aria-label="t('dl.from')"
              @update:model-value="(v: string | undefined) => setRange(p.key, 'from', v ?? '')"
            />
            <InputText
              :model-value="rangeValue(p.key, 'to')"
              type="date"
              size="small"
              class="flex-1"
              :aria-label="t('dl.to')"
              @update:model-value="(v: string | undefined) => setRange(p.key, 'to', v ?? '')"
            />
          </div>
          <SubjectPicker
            v-else-if="p.type === 'user' || p.type === 'department'"
            :model-value="params[p.key] ? { type: p.type, uuid: params[p.key] as string } : null"
            :types="[p.type]"
            :input-id="`dlp-${p.key}`"
            @update:model-value="(v: { uuid: string | null } | null) => (params[p.key] = v?.uuid ?? null)"
          />
          <InputText v-else :id="`dlp-${p.key}`" v-model="params[p.key] as string" size="small" class="w-full" :data-testid="`download-param-${p.key}`" />
          <p v-if="errors[p.key]" class="m-0 mt-1 text-xs text-danger">{{ errors[p.key] }}</p>
        </div>
        <Message v-if="error" severity="error" :closable="false">{{ error }}</Message>
      </template>

      <template v-if="job">
        <template v-if="!finishedJob(job)">
          <p class="m-0 text-sm">{{ t('dl.preparing') }}</p>
          <ProgressBar :value="job.progress" data-testid="download-progress" />
          <p class="m-0 text-xs text-muted-color">{{ t('dl.leave_open') }}</p>
        </template>
        <template v-else>
          <Message :severity="job.status === 'completed' ? 'success' : job.status === 'failed' ? 'error' : 'warn'" :closable="false" data-testid="download-result">
            {{ job.status === 'completed' ? t('dl.ready', { n: job.rows ?? 0 }) : job.status === 'failed' ? (job.error ?? t('dl.run_failed')) : t(`dl.status.${job.status}`) }}
          </Message>
          <p v-if="job.omitted.length" class="m-0 text-sm text-muted-color flex items-start gap-2">
            <i class="pi pi-eye-slash mt-0.5" aria-hidden="true" />{{ t('dl.omitted_for_you', { columns: joinList(job.omitted, locale) }) }}
          </p>
        </template>
      </template>
    </div>
    <template #footer>
      <template v-if="!job">
        <Button :label="t('common.cancel')" text :disabled="busy" @click="close" />
        <Button :label="t('dl.download')" icon="pi pi-download" :loading="busy" :disabled="!profile" data-testid="download-run" @click="run" />
      </template>
      <Button v-else-if="!finishedJob(job)" :label="t('dl.stop')" severity="secondary" outlined @click="cancel" />
      <template v-else>
        <Button v-if="job.status === 'failed'" :label="t('dl.retry')" icon="pi pi-refresh" severity="secondary" outlined @click="retry" />
        <Button v-if="job.status === 'completed' && job.file" :label="t('dl.save_file')" icon="pi pi-download" outlined data-testid="download-file" @click="fetchFile(job.file)" />
        <Button :label="t('common.close')" data-testid="download-close" @click="close" />
      </template>
    </template>
  </Dialog>
</template>
