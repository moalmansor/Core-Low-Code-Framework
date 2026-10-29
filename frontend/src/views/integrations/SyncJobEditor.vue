<script setup lang="ts">
import Button from 'primevue/button'
import Column from 'primevue/column'
import DataTable from 'primevue/datatable'
import InputText from 'primevue/inputtext'
import Select from 'primevue/select'
import Tag from 'primevue/tag'
import { useConfirm } from 'primevue/useconfirm'
import { useToast } from 'primevue/usetoast'
import { computed, onMounted, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { onBeforeRouteLeave, useRoute, useRouter } from 'vue-router'
import FormPicker from '@/builder/FormPicker.vue'
import I18nInput from '@/builder/I18nInput.vue'
import ConfigField from '@/components/config/ConfigField.vue'
import ConfigSaveBar from '@/components/config/ConfigSaveBar.vue'
import ConfigSection from '@/components/config/ConfigSection.vue'
import EmptyState from '@/components/config/EmptyState.vue'
import EmptyValue from '@/components/EmptyValue.vue'
import SettingSwitch from '@/components/config/SettingSwitch.vue'
import UserPicker from '@/components/UserPicker.vue'
import { provideConfigScreen } from '@/components/config/configScreen'
import { formatDatetime } from '@/runtime/format'
import { humanize } from '@/runtime/i18nText'
import { errorText, fieldErrors } from '../admin/building/shared'
import { inboundFieldsApi, sourcesApi, syncApi, type ConflictRule, type MappableField, type SourceOption, type SyncDirection, type SyncJobDoc, type SyncRun } from './api'
import SyncFieldRow from './SyncFieldRow.vue'

/**
 * A sync job (specification §4.33, ADR-0057): import rows of an external
 * data source into a form, or export a form's records to a REST address;
 * which field takes which value, the fields that identify a record, what
 * happens to records that already exist, a schedule, dry runs, and the
 * log of every run.
 */
provideConfigScreen()
const { t, locale } = useI18n()
const toast = useToast()
const confirm = useConfirm()
const route = useRoute()
const router = useRouter()
const isNew = computed(() => route.params.job === 'new')
const blank = (): SyncJobDoc => ({
  name: {},
  direction: 'import',
  source: null,
  form: null,
  run_as: null,
  fields: [],
  export: null,
  match: [],
  updated_from: null,
  conflict_rule: 'source_wins',
  active: true,
  schedule: null,
})
const doc = ref<SyncJobDoc>(blank())
const loaded = ref(false)
const saved = ref('')
const saving = ref(false)
const errors = ref<Record<string, string>>({})
const sources = ref<SourceOption[]>([])
const formFields = ref<MappableField[]>([])
const values = ref<Record<string, string>>({})
const matches = ref<Record<string, boolean>>({})
const runAsName = ref<string | null>(null)
const runs = ref<SyncRun[]>([])
const running = ref(false)
const cron = ref('')
const timezone = ref(Intl.DateTimeFormat().resolvedOptions().timeZone || 'UTC')

onMounted(async () => {
  try {
    const [s, d] = await Promise.all([sourcesApi.options(), isNew.value ? Promise.resolve(blank()) : syncApi.load(String(route.params.job))])
    sources.value = s
    accept(d)
    loaded.value = true
    if (!isNew.value) runs.value = await syncApi.runs(d.uuid!)
  } catch (e) {
    toast.add({ severity: 'error', summary: errorText(e, t('intg.load_failed')), life: 6000 })
  }
})
function accept(d: SyncJobDoc): void {
  if (Array.isArray(d.name)) d.name = {}
  doc.value = d
  values.value = Object.fromEntries(d.fields.map((f) => [f.field, (d.direction === 'import' ? f.from : f.to) ?? '']))
  matches.value = Object.fromEntries(d.match.map((k) => [k, true]))
  cron.value = d.schedule?.cron ?? ''
  if (d.schedule) timezone.value = d.schedule.timezone
  runAsName.value = d.run_as_name ?? null
  saved.value = JSON.stringify(body())
}
/** The document as the server takes it: the mapping rows built from the per-field choices. */
function body(): SyncJobDoc {
  const d = doc.value
  const mapped = Object.entries(values.value).filter(([, v]) => v.trim() !== '')
  return {
    name: d.name,
    direction: d.direction,
    source: d.source,
    form: d.form,
    run_as: d.run_as,
    fields: mapped.map(([field, v]) => (d.direction === 'import' ? { field, from: v } : { field, to: v.trim() })),
    export: d.direction === 'export' ? (d.export ?? { url: '', method: 'POST' }) : null,
    match: d.direction === 'import' ? mapped.filter(([k]) => matches.value[k]).map(([k]) => k) : [],
    updated_from: d.direction === 'import' ? d.updated_from : null,
    conflict_rule: d.conflict_rule,
    active: d.active,
    schedule: cron.value.trim() ? { cron: cron.value.trim(), timezone: timezone.value } : null,
  }
}
const dirty = computed(() => loaded.value && JSON.stringify(body()) !== saved.value)
onBeforeRouteLeave(() => !dirty.value || window.confirm(t('records.leave_unsaved')))
const err = (k: string) => errors.value[k]

watch(
  () => doc.value.form,
  async (form) => {
    formFields.value = form ? (await inboundFieldsApi.list(form).catch(() => [])).filter((f) => !f.rows) : []
    for (const f of formFields.value) {
      values.value[f.key] ??= ''
      matches.value[f.key] ??= false
    }
  },
)
const source = computed(() => sources.value.find((s) => s.uuid === doc.value.source) ?? null)
const fromOptions = computed(() => [
  { value: 'value', label: t('xds.map_value') },
  { value: 'label', label: t('xds.map_label') },
  ...(source.value?.cascades ? [{ value: 'parent', label: t('xds.map_parent') }] : []),
  ...(source.value?.columns ?? []).map((c) => ({ value: c.key, label: c.label ?? humanize(c.key) })),
])
const directionOptions = computed(() => (['import', 'export'] as SyncDirection[]).map((v) => ({ value: v, label: t(`sync.direction.${v}`) })))
const ruleOptions = computed(() => (['source_wins', 'target_wins', 'newest_wins', 'skip'] as ConflictRule[]).map((v) => ({ value: v, label: t(`sync.rule.${v}`) })))
const exportUrl = computed({
  get: () => doc.value.export?.url ?? '',
  set: (v: string) => (doc.value.export = { url: v, method: doc.value.export?.method ?? 'POST' }),
})
const exportMethod = computed({
  get: () => doc.value.export?.method ?? 'POST',
  set: (v: 'POST' | 'PUT') => (doc.value.export = { url: doc.value.export?.url ?? '', method: v }),
})
function setDirection(v: SyncDirection): void {
  doc.value.direction = v
  values.value = {}
  matches.value = {}
}
function setRunAs(u: { uuid: string; name: string } | null): void {
  if (!u) return
  doc.value.run_as = u.uuid.toLowerCase()
  runAsName.value = u.name
}
const fieldError = (key: string) => {
  const i = Object.entries(values.value)
    .filter(([, v]) => v.trim() !== '')
    .findIndex(([k]) => k === key)
  return i < 0 ? undefined : (err(`fields.${i}.from`) ?? err(`fields.${i}.to`) ?? err(`fields.${i}.field`))
}

async function save(): Promise<void> {
  saving.value = true
  errors.value = {}
  try {
    const out = isNew.value ? await syncApi.create(body()) : await syncApi.save(doc.value.uuid!, body(), doc.value.hash!)
    accept(out)
    toast.add({ severity: 'success', summary: t('sync.saved'), life: 3000 })
    if (isNew.value) await router.replace({ name: 'admin.integrations.sync', params: { job: out.uuid } })
  } catch (e) {
    errors.value = fieldErrors(e)
    toast.add({ severity: 'error', summary: errorText(e, t('sync.save_failed')), life: 8000 })
  } finally {
    saving.value = false
  }
}
function discard(): void {
  accept(JSON.parse(JSON.stringify({ ...doc.value, ...JSON.parse(saved.value) })) as SyncJobDoc)
  errors.value = {}
}
async function run(dry: boolean): Promise<void> {
  running.value = true
  try {
    await syncApi.run(doc.value.uuid!, dry)
    toast.add({ severity: 'success', summary: dry ? t('sync.dry_started') : t('sync.started'), life: 3000 })
    runs.value = await syncApi.runs(doc.value.uuid!)
  } catch (e) {
    toast.add({ severity: 'error', summary: errorText(e, t('sync.run_failed')), life: 8000 })
  } finally {
    running.value = false
  }
}
async function refreshRuns(): Promise<void> {
  runs.value = await syncApi.runs(doc.value.uuid!)
}
function remove(): void {
  confirm.require({
    header: t('sync.delete_title'),
    message: t('sync.delete_text'),
    acceptProps: { label: t('xds.delete'), severity: 'danger' },
    rejectProps: { label: t('xds.cancel'), text: true },
    accept: async () => {
      try {
        await syncApi.remove(doc.value.uuid!)
        saved.value = JSON.stringify(body())
        await router.push({ name: 'admin.integrations', params: { tab: 'sync' } })
      } catch (e) {
        toast.add({ severity: 'error', summary: errorText(e, t('sync.delete_failed')), life: 8000 })
      }
    },
  })
}
const severity = (s: string) => (s === 'succeeded' ? 'success' : s === 'failed' ? 'danger' : s === 'partially_failed' ? 'warn' : 'secondary')
</script>

<template>
  <div class="flex flex-col gap-6 max-w-[64rem]" data-testid="sync-editor">
    <header class="page-head">
      <div class="flex-1 min-w-0">
        <RouterLink :to="{ name: 'admin.integrations', params: { tab: 'sync' } }" class="text-sm text-primary inline-flex items-center gap-1 mb-1">
          <i class="pi pi-arrow-left rtl:rotate-180" aria-hidden="true" />{{ t('intg.tab.sync') }}
        </RouterLink>
        <h1 class="page-title !mb-1" dir="auto">{{ isNew ? t('sync.new') : (doc.label ?? t('sync.job')) }}</h1>
        <p class="m-0 text-sm text-muted-color">{{ t('sync.editor_subtitle') }}</p>
      </div>
      <div v-if="!isNew && loaded" class="flex flex-wrap gap-2">
        <Button icon="pi pi-eye" :label="t('sync.dry_run')" size="small" outlined :loading="running" :disabled="dirty" data-testid="sync-dry-run" @click="run(true)" />
        <Button icon="pi pi-play" :label="t('sync.run_now')" size="small" :loading="running" :disabled="dirty || !doc.active" data-testid="sync-run" @click="run(false)" />
        <Button icon="pi pi-trash" :label="t('xds.delete')" size="small" severity="danger" text data-testid="sync-delete" @click="remove" />
      </div>
    </header>

    <template v-if="loaded">
      <ConfigSection id="sync-basics" :title="t('sync.section.basics')">
        <I18nInput v-model="doc.name" class="w-field-md" :label="t('intg.name')" :maxlength="255" :invalid="!!err('name')" />
        <small v-if="err('name')" class="text-danger">{{ err('name') }}</small>
        <div class="cfg-row">
          <ConfigField :label="t('sync.direction_label')" for="sync-direction" width="sm" :error="err('direction')">
            <Select
              :model-value="doc.direction"
              input-id="sync-direction"
              :options="directionOptions"
              option-label="label"
              option-value="value"
              size="small"
              data-testid="sync-direction"
              @update:model-value="setDirection"
            />
          </ConfigField>
          <ConfigField :label="t('xds.source')" for="sync-source" width="md" :error="err('source')">
            <Select
              v-model="doc.source"
              input-id="sync-source"
              :options="sources"
              option-label="label"
              option-value="uuid"
              size="small"
              :empty-message="t('panels.external_none')"
              data-testid="sync-source"
            />
          </ConfigField>
          <ConfigField :label="t('sync.form')" for="sync-form" width="md" :error="err('form')">
            <FormPicker v-model="doc.form" kind="form" input-id="sync-form" />
          </ConfigField>
        </div>
        <ConfigField :label="t('intg.run_as')" for="sync-run-as" width="md" :hint="t('sync.run_as_hint')" :error="err('run_as')">
          <div class="flex flex-wrap items-center gap-2">
            <span v-if="runAsName" class="text-sm" data-testid="sync-run-as-name">{{ runAsName }}</span>
            <UserPicker input-id="sync-run-as" :model-value="null" @update:model-value="setRunAs" />
          </div>
        </ConfigField>
        <SettingSwitch id="sync-active" v-model="doc.active" :label="t('intg.active')" :description="t('sync.active_desc')" />
      </ConfigSection>

      <ConfigSection v-if="doc.direction === 'export'" id="sync-target" :title="t('sync.section.target')" :description="t('sync.target_hint')">
        <div class="cfg-row">
          <ConfigField :label="t('xds.method')" for="sync-method" width="sm" :error="err('export.method')">
            <Select v-model="exportMethod" input-id="sync-method" :options="['POST', 'PUT']" size="small" />
          </ConfigField>
          <ConfigField :label="t('xds.url')" for="sync-url" width="lg" :hint="t('xds.url_hint')" :error="err('export.url')">
            <InputText id="sync-url" v-model="exportUrl" class="ltr-value" size="small" placeholder="https://" data-testid="sync-url" />
          </ConfigField>
        </div>
      </ConfigSection>

      <ConfigSection id="sync-mapping" :title="t('sync.section.mapping')" :description="t(`sync.mapping_hint.${doc.direction}`)">
        <EmptyState v-if="!doc.form || (doc.direction === 'import' && !doc.source)" icon="pi pi-arrow-right-arrow-left" :title="t('sync.pick_first')" :description="t('sync.pick_first_text')" />
        <template v-else>
          <SyncFieldRow
            v-for="f in formFields"
            :key="f.key"
            v-model:value="values[f.key]!"
            v-model:match="matches[f.key]!"
            :field-key="f.key"
            :label="f.label"
            :direction="doc.direction"
            :options="fromOptions"
            :matchable="f.matchable"
            :error="fieldError(f.key)"
          />
          <small v-if="err('fields')" class="text-danger">{{ err('fields') }}</small>
          <small v-if="err('match')" class="text-danger">{{ err('match') }}</small>
        </template>
      </ConfigSection>

      <ConfigSection v-if="doc.direction === 'import'" id="sync-conflicts" :title="t('sync.section.conflicts')" :description="t('sync.conflicts_hint')">
        <div class="cfg-row">
          <ConfigField :label="t('sync.rule_label')" for="sync-rule" width="md" :hint="t(`sync.rule_desc.${doc.conflict_rule}`)" :error="err('conflict_rule')">
            <Select v-model="doc.conflict_rule" input-id="sync-rule" :options="ruleOptions" option-label="label" option-value="value" size="small" data-testid="sync-rule" />
          </ConfigField>
          <ConfigField v-if="doc.conflict_rule === 'newest_wins'" :label="t('sync.updated_from')" for="sync-updated" width="md" :hint="t('sync.updated_from_hint')" :error="err('updated_from')">
            <Select v-model="doc.updated_from" input-id="sync-updated" :options="fromOptions" option-label="label" option-value="value" size="small" />
          </ConfigField>
        </div>
      </ConfigSection>

      <ConfigSection id="sync-schedule" :title="t('sync.section.schedule')" :description="t('sync.schedule_hint')">
        <div class="cfg-row">
          <ConfigField :label="t('sync.cron')" for="sync-cron" width="md" :hint="t('sync.cron_hint')" :error="err('schedule.cron')">
            <InputText id="sync-cron" v-model="cron" class="ltr-value" size="small" placeholder="0 2 * * *" data-testid="sync-cron" />
          </ConfigField>
          <ConfigField :label="t('sync.timezone')" for="sync-tz" width="md">
            <InputText id="sync-tz" v-model="timezone" class="ltr-value" size="small" />
          </ConfigField>
        </div>
        <p v-if="doc.next_run_at" class="m-0 text-sm text-muted-color">{{ t('sync.next_run', { when: formatDatetime(doc.next_run_at, locale) }) }}</p>
      </ConfigSection>

      <ConfigSection v-if="!isNew" id="sync-runs" :title="t('sync.section.runs')" :description="t('sync.runs_hint')" :count="runs.length">
        <div><Button icon="pi pi-refresh" :label="t('sync.refresh')" size="small" text @click="refreshRuns" /></div>
        <DataTable :value="runs" data-key="uuid" size="small" class="records-table" data-testid="sync-runs">
          <template #empty>{{ t('sync.no_runs') }}</template>
          <Column :header="t('sync.when')">
            <template #body="{ data }"
              ><span v-if="data.started_at">{{ formatDatetime(data.started_at, locale) }}</span
              ><EmptyValue v-else
            /></template>
          </Column>
          <Column :header="t('sync.state')">
            <template #body="{ data }">
              <div class="flex flex-wrap items-center gap-2">
                <Tag :severity="severity(data.status)" :value="t(`sync.status.${data.status}`)" />
                <Tag v-if="data.dry_run" severity="secondary" :value="t('sync.dry')" />
              </div>
              <small v-if="data.error" class="text-danger block">{{ data.error }}</small>
            </template>
          </Column>
          <Column :header="t('sync.counts')">
            <template #body="{ data }"
              ><span class="text-sm">{{ t('sync.counts_text', { created: data.created, updated: data.updated, skipped: data.skipped, errors: data.errors }) }}</span></template
            >
          </Column>
          <Column :header="t('sync.by')">
            <template #body="{ data }"
              ><span v-if="data.triggered_by" dir="auto">{{ data.triggered_by }}</span
              ><span v-else class="text-muted-color">{{ t('sync.by_schedule') }}</span></template
            >
          </Column>
          <Column>
            <template #body="{ data }">
              <a v-if="data.has_log" :href="syncApi.logUrl(doc.uuid!, data.uuid)" class="text-link text-sm" download>{{ t('sync.download_log') }}</a>
            </template>
          </Column>
        </DataTable>
      </ConfigSection>

      <ConfigSaveBar :dirty="dirty || isNew" :saving="saving" testid="sync" @save="save" @discard="discard" />
    </template>
  </div>
</template>
