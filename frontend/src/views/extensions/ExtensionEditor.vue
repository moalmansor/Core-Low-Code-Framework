<script setup lang="ts">
import Button from 'primevue/button'
import Column from 'primevue/column'
import DataTable from 'primevue/datatable'
import Dialog from 'primevue/dialog'
import InputNumber from 'primevue/inputnumber'
import InputText from 'primevue/inputtext'
import Select from 'primevue/select'
import Tag from 'primevue/tag'
import Textarea from 'primevue/textarea'
import { useConfirm } from 'primevue/useconfirm'
import { useToast } from 'primevue/usetoast'
import { computed, onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { onBeforeRouteLeave, useRoute, useRouter } from 'vue-router'
import FormPicker from '@/builder/FormPicker.vue'
import I18nInput from '@/builder/I18nInput.vue'
import CodeEditor from '@/components/code/CodeEditor.vue'
import ConfigField from '@/components/config/ConfigField.vue'
import ConfigSaveBar from '@/components/config/ConfigSaveBar.vue'
import ConfigSection from '@/components/config/ConfigSection.vue'
import EmptyValue from '@/components/EmptyValue.vue'
import { provideConfigScreen } from '@/components/config/configScreen'
import { ApiError } from '@/api/http'
import { errorText, formatDateTime } from '../admin/building/shared'
import { EXT_TYPES, extensionsApi, isServer, needsForm, STARTERS, statusSeverity, type ExtDetail, type ExtDoc, type ExtMeta, type ExtType, type ExtVersion, type TestReport } from './api'

/**
 * One developer extension (specification §4.19): its settings and code in
 * the Monaco editor, a test run in the sandbox, review (submit, approve or
 * reject by someone else) and deployment, and every version with rollback.
 */
const route = useRoute()
const router = useRouter()
const { t, locale } = useI18n()
const toast = useToast()
const confirm = useConfirm()
provideConfigScreen()

const uuid = ref(String(route.params.extension))
const isNew = computed(() => uuid.value === 'new')
const detail = ref<ExtDetail | null>(null)
const doc = ref<ExtDoc | null>(null)
const saved = ref('')
const saving = ref(false)
const errors = ref<Record<string, string>>({})
const meta = ref<ExtMeta>({ points: [], permissions: [], timeout: { min: 50, max: 10000 } })
const canWrite = ref(false)
const dirty = computed(() => doc.value !== null && JSON.stringify(doc.value) !== saved.value)

const types = computed(() => EXT_TYPES.map((v) => ({ value: v, label: t(`ext.type_name.${v}`) })))
const points = computed(() => meta.value.points.map((p) => ({ value: p, label: t(`ext.point.${p}`) })))
const language = computed(() => (doc.value && isServer(doc.value.type) ? 'php' : 'typescript'))

function fromDetail(d: ExtDetail): ExtDoc {
  return {
    key: d.key,
    type: d.type,
    hook_point: d.hook_point,
    form: d.form,
    permission: d.permission,
    timeout_ms: d.timeout_ms,
    description: d.description ?? '',
    label: { ...d.label },
    source: d.source,
  }
}
function apply(d: ExtDetail): void {
  detail.value = d
  doc.value = fromDetail(d)
  saved.value = JSON.stringify(doc.value)
}
async function load(): Promise<void> {
  try {
    const [m, list] = await Promise.all([extensionsApi.meta(), extensionsApi.list()])
    meta.value = m
    canWrite.value = list.canWrite
    if (isNew.value) {
      doc.value = { key: '', type: 'server_hook', hook_point: 'beforeSave', form: null, permission: null, timeout_ms: 2000, description: '', label: {}, source: STARTERS.server_hook }
      saved.value = JSON.stringify(doc.value)
    } else {
      apply(await extensionsApi.get(uuid.value))
    }
  } catch (e) {
    toast.add({ severity: 'error', summary: errorText(e, t('ext.load_failed')), life: 6000 })
  }
}
onMounted(load)

function setType(type: ExtType): void {
  if (!doc.value) return
  const untouched = Object.values(STARTERS).includes(doc.value.source) || doc.value.source.trim() === ''
  doc.value.type = type
  if (untouched) doc.value.source = STARTERS[type]
  if (type === 'server_hook' && !doc.value.hook_point) doc.value.hook_point = 'beforeSave'
}

async function save(): Promise<void> {
  if (!doc.value) return
  saving.value = true
  errors.value = {}
  try {
    const result = isNew.value ? await extensionsApi.create(doc.value) : await extensionsApi.save(uuid.value, doc.value)
    apply(result)
    toast.add({ severity: 'success', summary: t('ext.saved'), life: 3000 })
    if (isNew.value) {
      uuid.value = result.uuid
      await router.replace({ name: 'admin.extensions.edit', params: { extension: result.uuid } })
    }
  } catch (e) {
    errors.value = e instanceof ApiError ? e.fieldErrors : {}
    if (e instanceof ApiError && Array.isArray(e.body.errors?.source)) errors.value.source = e.body.errors.source.join('\n')
    toast.add({ severity: 'error', summary: errorText(e, t('ext.save_failed')), life: 6000 })
  } finally {
    saving.value = false
  }
}
function discard(): void {
  if (saved.value) doc.value = JSON.parse(saved.value) as ExtDoc
  errors.value = {}
}
onBeforeRouteLeave(() => !dirty.value || window.confirm(t('records.leave_unsaved')))
const err = (path: string) => errors.value[path]

// The sandbox test.
const sample = ref('{\n  \n}')
const sampleRecord = ref('')
const sampleTransition = ref('{ "transition": "", "from": "", "to": "" }')
const report = ref<TestReport | null>(null)
const testing = ref(false)
const testError = ref('')
async function runTest(): Promise<void> {
  if (!doc.value) return
  testError.value = ''
  let parsed: Record<string, unknown> | null
  let extra: Record<string, unknown> | null
  try {
    parsed = sample.value.trim() ? (JSON.parse(sample.value) as Record<string, unknown>) : null
    extra = doc.value.hook_point === 'onStatusChange' || doc.value.hook_point === 'onAction' ? (JSON.parse(sampleTransition.value) as Record<string, unknown>) : null
  } catch {
    testError.value = t('ext.bad_json')
    return
  }
  testing.value = true
  try {
    const input = doc.value.type === 'api_endpoint' ? { body: parsed } : { values: parsed, record: sampleRecord.value.trim() || null, extra }
    const r = await extensionsApi.test(uuid.value, input)
    report.value = r.report
    apply(r.extension)
  } catch (e) {
    testError.value = errorText(e, t('ext.test_failed'))
  } finally {
    testing.value = false
  }
}

// Review and deployment.
const busy = ref(false)
async function step(run: () => Promise<ExtDetail>, done: string): Promise<void> {
  busy.value = true
  try {
    apply(await run())
    toast.add({ severity: 'success', summary: done, life: 4000 })
  } catch (e) {
    toast.add({ severity: 'error', summary: errorText(e, t('ext.step_failed')), life: 7000 })
  } finally {
    busy.value = false
  }
}
const submit = () => step(() => extensionsApi.submit(uuid.value), t('ext.submitted'))
const approve = () => step(() => extensionsApi.approve(uuid.value), t('ext.approved'))
const rejectOpen = ref(false)
const rejectReason = ref('')
async function reject(): Promise<void> {
  rejectOpen.value = false
  await step(() => extensionsApi.reject(uuid.value, rejectReason.value), t('ext.rejected'))
  rejectReason.value = ''
}
function deployVersion(v: ExtVersion): void {
  confirm.require({
    header: t('ext.deploy_title', { n: v.version }),
    message: detail.value?.deployed_version && v.version < detail.value.deployed_version ? t('ext.rollback_text', { n: v.version }) : t('ext.deploy_text', { n: v.version }),
    acceptLabel: t('ext.deploy'),
    rejectLabel: t('common.cancel'),
    accept: () => step(() => extensionsApi.deploy(uuid.value, v.uuid), t('ext.deployed')),
  })
}
function toggle(off: boolean): void {
  void step(() => extensionsApi.disable(uuid.value, off), off ? t('ext.turned_off') : t('ext.turned_on'))
}
function remove(): void {
  confirm.require({
    header: t('ext.delete_title'),
    message: t('ext.delete_text'),
    acceptLabel: t('ext.delete'),
    rejectLabel: t('common.cancel'),
    acceptProps: { severity: 'danger' },
    accept: async () => {
      try {
        await extensionsApi.remove(uuid.value)
        saved.value = JSON.stringify(doc.value)
        await router.push({ name: 'admin.extensions' })
      } catch (e) {
        toast.add({ severity: 'error', summary: errorText(e, t('ext.step_failed')), life: 7000 })
      }
    },
  })
}
const viewing = ref<(ExtVersion & { source: string }) | null>(null)
async function view(v: ExtVersion): Promise<void> {
  try {
    viewing.value = await extensionsApi.version(uuid.value, v.uuid)
  } catch (e) {
    toast.add({ severity: 'error', summary: errorText(e, t('ext.load_failed')), life: 6000 })
  }
}
const current = computed(() => detail.value?.current ?? null)
const canDeployCurrent = computed(() => canWrite.value && current.value?.approved_at && detail.value?.deployed_version !== current.value.version && detail.value?.status !== 'disabled')
</script>

<template>
  <div class="flex flex-col gap-6 max-w-[64rem]" data-testid="extension-editor">
    <header class="page-head">
      <div class="flex-1 min-w-0">
        <RouterLink :to="{ name: 'admin.extensions' }" class="text-sm text-primary inline-flex items-center gap-1 mb-1">
          <i class="pi pi-arrow-left rtl:rotate-180" aria-hidden="true" />{{ t('admin.area.extensions') }}
        </RouterLink>
        <h1 class="page-title !mb-1">
          <template v-if="isNew">{{ t('ext.new') }}</template>
          <span v-else class="ltr-value">{{ detail?.key }}</span>
        </h1>
        <p class="m-0 text-sm text-muted-color">{{ t('ext.editor_subtitle') }}</p>
      </div>
      <template v-if="detail">
        <Tag :severity="statusSeverity(detail.status)" :value="t(`ext.status_name.${detail.status}`)" data-testid="ext-status" />
        <span class="text-sm text-muted-color">{{ detail.deployed_version ? t('ext.running_version', { n: detail.deployed_version }) : t('ext.not_running') }}</span>
      </template>
    </header>

    <template v-if="doc">
      <ConfigSection id="ext-basics" :title="t('ext.section.basics')">
        <div class="cfg-row">
          <ConfigField :label="t('ext.key')" for="ext-key" width="sm" :hint="t('ext.key_hint')" :error="err('key')" required>
            <InputText id="ext-key" v-model="doc.key" dir="ltr" class="ltr-value" size="small" maxlength="48" :disabled="!isNew" data-testid="ext-key" />
          </ConfigField>
          <ConfigField :label="t('ext.type')" for="ext-type" width="md" :hint="t(`ext.type_hint.${doc.type}`)" :error="err('type')">
            <Select
              :model-value="doc.type"
              input-id="ext-type"
              :options="types"
              option-label="label"
              option-value="value"
              size="small"
              :disabled="!isNew"
              data-testid="ext-type"
              @update:model-value="setType"
            />
          </ConfigField>
        </div>
        <div class="cfg-row">
          <ConfigField v-if="needsForm(doc.type)" :label="t('ext.form')" for="ext-form" width="md" :error="err('form')" required>
            <FormPicker v-model="doc.form" input-id="ext-form" kind="form" />
          </ConfigField>
          <ConfigField
            v-if="doc.type === 'server_hook'"
            :label="t('ext.hook_point')"
            for="ext-point"
            width="md"
            :hint="doc.hook_point ? t(`ext.point_hint.${doc.hook_point}`) : undefined"
            :error="err('hook_point')"
          >
            <Select v-model="doc.hook_point" input-id="ext-point" :options="points" option-label="label" option-value="value" size="small" data-testid="ext-point" />
          </ConfigField>
          <ConfigField v-if="doc.type === 'api_endpoint'" :label="t('ext.permission')" for="ext-permission" width="lg" :hint="t('ext.permission_hint')" :error="err('permission')" required>
            <Select v-model="doc.permission" input-id="ext-permission" :options="meta.permissions" filter size="small" class="ltr-value" />
          </ConfigField>
          <ConfigField v-if="isServer(doc.type)" :label="t('ext.timeout')" for="ext-timeout" width="xs" :hint="t('ext.timeout_hint', meta.timeout)" :error="err('timeout_ms')">
            <InputNumber v-model="doc.timeout_ms" input-id="ext-timeout" :min="meta.timeout.min" :max="meta.timeout.max" size="small" fluid />
          </ConfigField>
        </div>
        <I18nInput v-if="doc.type === 'client_field'" v-model="doc.label" class="w-field-md" :label="t('ext.palette_label')" :maxlength="255" />
        <ConfigField :label="t('ext.description')" for="ext-description" width="lg">
          <Textarea id="ext-description" v-model="doc.description" rows="2" auto-resize maxlength="2000" class="w-full" />
        </ConfigField>
        <p v-if="doc.type === 'api_endpoint' && !isNew" class="m-0 text-sm">
          {{ t('ext.endpoint_address') }} <span class="ltr-value font-medium">/api/v1/ext-api/{{ doc.key }}</span>
        </p>
      </ConfigSection>

      <ConfigSection id="ext-code" :title="t('ext.section.code')" :description="t(`ext.code_hint.${doc.type}`)">
        <CodeEditor v-model="doc.source" :language="language" :readonly="!canWrite" :label="t('ext.section.code')" />
        <pre v-if="err('source')" class="m-0 text-sm text-danger whitespace-pre-wrap" dir="ltr" data-testid="ext-source-error">{{ err('source') }}</pre>
      </ConfigSection>

      <ConfigSection v-if="!isNew && canWrite" id="ext-test" :title="t('ext.section.test')" :description="t(isServer(doc.type) ? 'ext.test_hint_server' : 'ext.test_hint_client')">
        <template v-if="isServer(doc.type)">
          <div class="cfg-row">
            <ConfigField :label="doc.type === 'api_endpoint' ? t('ext.sample_body') : t('ext.sample_values')" for="ext-sample" width="lg" :hint="t('ext.sample_hint')">
              <Textarea id="ext-sample" v-model="sample" rows="5" dir="ltr" class="ltr-value w-full font-mono text-sm" data-testid="ext-sample" />
            </ConfigField>
            <div v-if="doc.type === 'server_hook'" class="flex flex-col gap-3">
              <ConfigField :label="t('ext.sample_record')" for="ext-sample-record" width="md" :hint="t('ext.sample_record_hint')">
                <InputText id="ext-sample-record" v-model="sampleRecord" dir="ltr" class="ltr-value" size="small" />
              </ConfigField>
              <ConfigField v-if="doc.hook_point === 'onStatusChange' || doc.hook_point === 'onAction'" :label="t('ext.sample_extra')" for="ext-sample-extra" width="md">
                <Textarea id="ext-sample-extra" v-model="sampleTransition" rows="2" dir="ltr" class="ltr-value w-full font-mono text-sm" />
              </ConfigField>
            </div>
          </div>
        </template>
        <div class="flex flex-wrap items-center gap-3">
          <Button icon="pi pi-play" :label="t('ext.run_test')" size="small" :loading="testing" :disabled="dirty" data-testid="ext-run-test" @click="runTest" />
          <span v-if="dirty" class="text-sm text-muted-color">{{ t('ext.save_before_test') }}</span>
          <span v-if="testError" class="text-sm text-danger">{{ testError }}</span>
        </div>
        <div v-if="report" class="ext-report" data-testid="ext-report">
          <div class="flex flex-wrap items-center gap-3">
            <Tag
              :severity="report.ok ? (report.refused ? 'warn' : 'success') : 'danger'"
              :value="report.ok ? (report.refused ? t('ext.result_refused') : t('ext.result_passed')) : t('ext.result_failed')"
            />
            <span class="text-sm text-muted-color">{{ t('ext.took', { n: report.duration_ms }) }}</span>
          </div>
          <p v-if="report.refused" class="m-0 text-sm" dir="auto">{{ report.refused }}</p>
          <pre v-if="report.error" class="m-0 text-sm text-danger whitespace-pre-wrap" dir="ltr">{{ report.error }}</pre>
          <template v-if="report.changed?.length">
            <h3 class="m-0 text-sm font-semibold">{{ t('ext.changed_values') }}</h3>
            <pre class="ext-code" dir="ltr">{{ JSON.stringify(Object.fromEntries(report.changed.map((k) => [k, report!.values?.[k]])), null, 2) }}</pre>
          </template>
          <template v-if="report.errors && Object.keys(report.errors).length">
            <h3 class="m-0 text-sm font-semibold">{{ t('ext.field_errors') }}</h3>
            <pre class="ext-code" dir="ltr">{{ JSON.stringify(report.errors, null, 2) }}</pre>
          </template>
          <template v-if="report.answer !== undefined">
            <h3 class="m-0 text-sm font-semibold">{{ t('ext.answer') }}</h3>
            <pre class="ext-code" dir="ltr">{{ JSON.stringify(report.answer, null, 2) }}</pre>
          </template>
          <template v-if="report.logs.length">
            <h3 class="m-0 text-sm font-semibold">{{ t('ext.logs') }}</h3>
            <pre class="ext-code" dir="ltr">{{ report.logs.join('\n') }}</pre>
          </template>
          <p v-if="report.compiled" class="m-0 text-sm">{{ t('ext.compiled', { n: report.bytes }) }}</p>
        </div>
      </ConfigSection>

      <ConfigSection v-if="detail" id="ext-review" :title="t('ext.section.review')" :description="t('ext.review_hint')">
        <p v-if="current?.rejected_reason && detail.status === 'rejected'" class="m-0 text-sm text-danger" dir="auto">{{ t('ext.rejected_because', { reason: current.rejected_reason }) }}</p>
        <div class="flex flex-wrap gap-2">
          <Button
            v-if="canWrite && ['draft', 'testing', 'rejected'].includes(detail.status)"
            icon="pi pi-send"
            :label="t('ext.submit')"
            size="small"
            :disabled="dirty || current?.test_status !== 'passed' || busy"
            data-testid="ext-submit"
            @click="submit"
          />
          <template v-if="detail.status === 'pending_approval'">
            <Button v-if="detail.can_approve" icon="pi pi-check" :label="t('ext.approve')" size="small" :disabled="busy" data-testid="ext-approve" @click="approve" />
            <Button v-if="detail.can_approve" icon="pi pi-times" :label="t('ext.reject')" size="small" severity="danger" outlined :disabled="busy" @click="rejectOpen = true" />
            <span v-if="!detail.can_approve" class="text-sm text-muted-color">{{ t('ext.waiting_approval') }}</span>
          </template>
          <Button
            v-if="canDeployCurrent"
            icon="pi pi-upload"
            :label="t('ext.deploy_current', { n: current?.version })"
            size="small"
            :disabled="busy"
            data-testid="ext-deploy"
            @click="deployVersion(current!)"
          />
          <Button v-if="detail.status === 'disabled'" icon="pi pi-power-off" :label="t('ext.turn_on')" size="small" outlined :disabled="busy" @click="toggle(false)" />
          <Button
            v-else-if="detail.deployed_version"
            icon="pi pi-power-off"
            :label="t('ext.turn_off')"
            size="small"
            severity="danger"
            outlined
            :disabled="busy"
            data-testid="ext-turn-off"
            @click="toggle(true)"
          />
          <Button v-if="canWrite && !detail.versions.some((v) => v.deployed_at)" icon="pi pi-trash" :label="t('ext.delete')" size="small" severity="danger" text @click="remove" />
        </div>
      </ConfigSection>

      <ConfigSection v-if="detail" id="ext-versions" :title="t('ext.section.versions')" :description="t('ext.versions_hint')" :count="detail.versions.length">
        <DataTable :value="detail.versions" data-key="uuid" size="small" class="records-table">
          <template #empty>{{ t('ext.no_versions') }}</template>
          <Column :header="t('ext.version')">
            <template #body="{ data }">
              {{ t('ext.version_n', { n: data.version }) }}
              <Tag v-if="data.deployed" severity="success" :value="t('ext.running')" class="ms-2" />
            </template>
          </Column>
          <Column :header="t('ext.author')">
            <template #body="{ data }"
              ><span dir="auto">{{ data.author }}</span></template
            >
          </Column>
          <Column :header="t('ext.test')">
            <template #body="{ data }">{{ t(`ext.test_status.${data.test_status}`) }}</template>
          </Column>
          <Column :header="t('ext.approved_by')">
            <template #body="{ data }">
              <span v-if="data.approved_by" dir="auto">{{ data.approved_by }} · {{ formatDateTime(data.approved_at, locale) }}</span>
              <EmptyValue v-else />
            </template>
          </Column>
          <Column style="width: 12rem">
            <template #body="{ data }">
              <div class="flex gap-1 justify-end">
                <Button icon="pi pi-eye" text rounded size="small" :aria-label="t('ext.view_source')" @click="view(data)" />
                <Button
                  v-if="canWrite && data.approved_at && !data.deployed && detail.status !== 'disabled'"
                  icon="pi pi-upload"
                  text
                  rounded
                  size="small"
                  :aria-label="t('ext.deploy')"
                  @click="deployVersion(data)"
                />
              </div>
            </template>
          </Column>
        </DataTable>
      </ConfigSection>

      <ConfigSaveBar v-if="canWrite" :dirty="dirty || isNew" :saving="saving" testid="ext" @save="save" @discard="discard" />
    </template>

    <Dialog v-model:visible="rejectOpen" modal :header="t('ext.reject')" :style="{ width: '32rem', maxWidth: 'calc(100vw - 2rem)' }">
      <ConfigField :label="t('ext.reject_reason')" for="ext-reject" width="full" required>
        <Textarea id="ext-reject" v-model="rejectReason" rows="3" auto-resize maxlength="2000" class="w-full" />
      </ConfigField>
      <template #footer>
        <Button :label="t('common.cancel')" text @click="rejectOpen = false" />
        <Button :label="t('ext.reject')" severity="danger" :disabled="!rejectReason.trim()" @click="reject" />
      </template>
    </Dialog>
    <Dialog
      :visible="viewing !== null"
      modal
      :header="viewing ? t('ext.version_n', { n: viewing.version }) : ''"
      :style="{ width: '56rem', maxWidth: 'calc(100vw - 2rem)' }"
      @update:visible="(v: boolean) => !v && (viewing = null)"
    >
      <div v-if="viewing" class="flex flex-col gap-2">
        <p class="m-0 text-xs text-muted-color ltr-value">sha256 {{ viewing.checksum }}</p>
        <CodeEditor :model-value="viewing.source" :language="language" readonly height="24rem" :label="t('ext.view_source')" />
      </div>
    </Dialog>
  </div>
</template>

<style scoped>
.ext-report {
  display: flex;
  flex-direction: column;
  gap: 0.5rem;
  padding: 0.75rem;
  border: 1px solid var(--border);
  border-radius: 0.5rem;
}
.ext-code {
  margin: 0;
  padding: 0.5rem;
  border-radius: 0.375rem;
  background: var(--bg-subtle);
  font-size: 0.75rem;
  max-height: 14rem;
  overflow: auto;
  white-space: pre-wrap;
}
</style>
