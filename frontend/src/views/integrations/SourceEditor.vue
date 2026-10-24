<script setup lang="ts">
import Button from 'primevue/button'
import Column from 'primevue/column'
import DataTable from 'primevue/datatable'
import InputNumber from 'primevue/inputnumber'
import InputText from 'primevue/inputtext'
import Password from 'primevue/password'
import Select from 'primevue/select'
import Textarea from 'primevue/textarea'
import { useConfirm } from 'primevue/useconfirm'
import { useToast } from 'primevue/usetoast'
import { computed, onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { onBeforeRouteLeave, useRoute, useRouter } from 'vue-router'
import I18nInput from '@/builder/I18nInput.vue'
import ConfigField from '@/components/config/ConfigField.vue'
import ConfigSaveBar from '@/components/config/ConfigSaveBar.vue'
import ConfigSection from '@/components/config/ConfigSection.vue'
import EmptyState from '@/components/config/EmptyState.vue'
import SettingSwitch from '@/components/config/SettingSwitch.vue'
import { provideConfigScreen } from '@/components/config/configScreen'
import { joinList } from '@/runtime/lists'
import { errorText, fieldErrors } from '../admin/building/shared'
import { sourcesApi, type CachePolicy, type SourceAuth, type SourceDoc, type SourceTest, type SourceType } from './api'
import NameValueRow from './NameValueRow.vue'
import SourceColumnRow from './SourceColumnRow.vue'

/**
 * An external data source (specification §4.33, ADR-0057): where it reads
 * from (a REST endpoint through the egress gateway, or a view of a database
 * connection defined for this installation), how it authenticates (secrets
 * are write-only), how each record maps to a value, a label, a parent and
 * extra columns, and how long results are kept. "Test" calls the source now
 * and shows what came back, to help with the mapping.
 */
provideConfigScreen()
const { t, locale } = useI18n()
const toast = useToast()
const confirm = useConfirm()
const route = useRoute()
const router = useRouter()
const isNew = computed(() => route.params.source === 'new')
const blankRest = () => ({ url: '', method: 'GET' as const, query: [], body: null, records: '' })
const blank = (): SourceDoc => ({
  key: '',
  type: 'rest',
  name: {},
  rest_config: blankRest(),
  db_connection: null,
  db_view: null,
  auth_type: 'none',
  credentials: {},
  headers: [],
  response_mapping: { value: '', label: '', parent: null, columns: [] },
  cache_policy: 'ttl',
  cache_ttl_seconds: 300,
  active: true,
})
const doc = ref<SourceDoc>(blank())
const loaded = ref(false)
const saved = ref('')
const saving = ref(false)
const errors = ref<Record<string, string>>({})
const connections = ref<string[]>([])
const authFields = ref<Partial<Record<SourceAuth, Record<string, boolean>>>>({})
const secrets = ref<Partial<Record<SourceAuth, string[]>>>({})
const testing = ref(false)
const result = ref<SourceTest | null>(null)

onMounted(async () => {
  try {
    const [list, d] = await Promise.all([sourcesApi.list(), isNew.value ? Promise.resolve(blank()) : sourcesApi.load(String(route.params.source))])
    connections.value = list.meta.connections
    authFields.value = list.meta.auth_fields
    secrets.value = list.meta.secrets
    accept(d)
    loaded.value = true
  } catch (e) {
    toast.add({ severity: 'error', summary: errorText(e, t('intg.load_failed')), life: 6000 })
  }
})
const editable = (d: SourceDoc) => ({
  key: d.key,
  type: d.type,
  name: d.name,
  rest_config: d.rest_config,
  db_connection: d.db_connection,
  db_view: d.db_view,
  auth_type: d.auth_type,
  credentials: d.credentials,
  headers: d.headers,
  response_mapping: d.response_mapping,
  cache_policy: d.cache_policy,
  cache_ttl_seconds: d.cache_ttl_seconds,
  active: d.active,
})
function accept(d: SourceDoc): void {
  if (Array.isArray(d.name)) d.name = {}
  if (Array.isArray(d.credentials)) d.credentials = {}
  d.rest_config = d.rest_config ?? blankRest()
  d.rest_config.query = d.rest_config.query ?? []
  d.response_mapping.columns = d.response_mapping.columns ?? []
  doc.value = d
  saved.value = JSON.stringify(editable(d))
}
const dirty = computed(() => loaded.value && JSON.stringify(editable(doc.value)) !== saved.value)
onBeforeRouteLeave(() => !dirty.value || window.confirm(t('records.leave_unsaved')))
const err = (k: string) => errors.value[k]
const rest = computed(() => doc.value.rest_config!)

const typeOptions = computed(() => (['rest', 'db_view'] as SourceType[]).map((v) => ({ value: v, label: t(`xds.type.${v}`) })))
const authOptions = computed(() => (Object.keys(authFields.value) as SourceAuth[]).map((v) => ({ value: v, label: t(`xds.auth.${v}`) })))
const cacheOptions = computed(() => (['none', 'ttl', 'stale_while_revalidate'] as CachePolicy[]).map((v) => ({ value: v, label: t(`xds.cache.${v}`) })))
const credentialFields = computed(() =>
  Object.entries(authFields.value[doc.value.auth_type] ?? {}).map(([name, required]) => ({ name, required, secret: (secrets.value[doc.value.auth_type] ?? []).includes(name) })),
)
function setAuth(v: SourceAuth): void {
  doc.value.auth_type = v
  doc.value.credentials = {}
}
const credential = (name: string) => (doc.value.credentials[name] ?? '') as string
const setCredential = (name: string, v: string | undefined) => (doc.value.credentials = { ...doc.value.credentials, [name]: v ?? '' })

async function save(): Promise<void> {
  saving.value = true
  errors.value = {}
  try {
    const body = editable(doc.value) as SourceDoc
    const out = isNew.value ? await sourcesApi.create(body) : await sourcesApi.save(doc.value.uuid!, body, doc.value.hash!)
    accept(out)
    toast.add({ severity: 'success', summary: t('xds.saved'), life: 3000 })
    if (isNew.value) await router.replace({ name: 'admin.integrations.source', params: { source: out.uuid } })
  } catch (e) {
    errors.value = fieldErrors(e)
    toast.add({ severity: 'error', summary: errorText(e, t('xds.save_failed')), life: 8000 })
  } finally {
    saving.value = false
  }
}
function discard(): void {
  accept({ ...doc.value, ...(JSON.parse(saved.value) as Partial<SourceDoc>) })
  errors.value = {}
}
async function runTest(): Promise<void> {
  testing.value = true
  try {
    result.value = await sourcesApi.test(doc.value.uuid!)
  } catch (e) {
    toast.add({ severity: 'error', summary: errorText(e, t('xds.test_failed')), life: 8000 })
  } finally {
    testing.value = false
  }
}
function remove(): void {
  confirm.require({
    header: t('xds.delete_title'),
    message: t('xds.delete_text'),
    acceptProps: { label: t('xds.delete'), severity: 'danger' },
    rejectProps: { label: t('xds.cancel'), text: true },
    accept: async () => {
      try {
        await sourcesApi.remove(doc.value.uuid!)
        saved.value = JSON.stringify(editable(doc.value))
        await router.push({ name: 'admin.integrations', params: { tab: 'sources' } })
      } catch (e) {
        toast.add({ severity: 'error', summary: errorText(e, t('xds.delete_failed')), life: 8000 })
      }
    },
  })
}
const sampleText = computed(() => (result.value?.sample == null ? '' : JSON.stringify(result.value.sample, null, 2)))
const columnKeys = computed(() => doc.value.response_mapping.columns.map((c) => c.key).filter(Boolean))
</script>

<template>
  <div class="flex flex-col gap-6 max-w-[64rem]" data-testid="source-editor">
    <header class="page-head">
      <div class="flex-1 min-w-0">
        <RouterLink :to="{ name: 'admin.integrations', params: { tab: 'sources' } }" class="text-sm text-primary inline-flex items-center gap-1 mb-1">
          <i class="pi pi-arrow-left rtl:rotate-180" aria-hidden="true" />{{ t('intg.tab.sources') }}
        </RouterLink>
        <h1 class="page-title !mb-1" dir="auto">{{ isNew ? t('xds.new') : (doc.label ?? t('xds.source')) }}</h1>
        <p class="m-0 text-sm text-muted-color">{{ t('xds.editor_subtitle') }}</p>
      </div>
      <Button v-if="!isNew && loaded" icon="pi pi-trash" :label="t('xds.delete')" size="small" severity="danger" text data-testid="source-delete" @click="remove" />
    </header>

    <template v-if="loaded">
      <ConfigSection id="source-basics" :title="t('xds.section.basics')">
        <I18nInput v-model="doc.name" class="w-field-md" :label="t('intg.name')" :maxlength="255" :invalid="!!err('name')" />
        <small v-if="err('name')" class="text-danger">{{ err('name') }}</small>
        <div class="cfg-row">
          <ConfigField :label="t('xds.key')" for="source-key" width="md" :hint="t('xds.key_hint')" :error="err('key')">
            <InputText id="source-key" v-model="doc.key" class="ltr-value" size="small" maxlength="48" data-testid="source-key" />
          </ConfigField>
          <ConfigField :label="t('xds.kind')" for="source-type" width="md" :error="err('type')">
            <Select v-model="doc.type" input-id="source-type" :options="typeOptions" option-label="label" option-value="value" size="small" data-testid="source-type" />
          </ConfigField>
        </div>
        <SettingSwitch id="source-active" v-model="doc.active" :label="t('intg.active')" :description="t('xds.active_desc')" />
        <p v-if="doc.used_by?.length" class="m-0 text-sm text-muted-color" data-testid="source-used-by">{{ t('xds.used_by', { what: joinList(doc.used_by, locale) }) }}</p>
      </ConfigSection>

      <ConfigSection v-if="doc.type === 'rest'" id="source-rest" :title="t('xds.section.rest')" :description="t('xds.rest_hint')">
        <div class="cfg-row">
          <ConfigField :label="t('xds.method')" for="source-method" width="sm" :error="err('rest_config.method')">
            <Select v-model="rest.method" input-id="source-method" :options="['GET', 'POST']" size="small" />
          </ConfigField>
          <ConfigField :label="t('xds.url')" for="source-url" width="lg" :hint="t('xds.url_hint')" :error="err('rest_config.url')" required>
            <InputText id="source-url" v-model="rest.url" class="ltr-value" size="small" placeholder="https://" data-testid="source-url" />
          </ConfigField>
        </div>
        <ConfigField :label="t('xds.query')" width="lg" :hint="t('xds.query_hint')">
          <div class="flex flex-col gap-2">
            <NameValueRow
              v-for="(_, i) in rest.query"
              :key="i"
              v-model="rest.query[i]!"
              :name-label="t('xds.param_name')"
              :value-label="t('xds.param_value')"
              :error="err(`rest_config.query.${i}`)"
              @remove="rest.query.splice(i, 1)"
            />
            <div><Button icon="pi pi-plus" :label="t('xds.add_param')" size="small" text @click="rest.query.push({ name: '', value: '' })" /></div>
          </div>
        </ConfigField>
        <ConfigField v-if="rest.method === 'POST'" :label="t('xds.body')" for="source-body" width="lg" :hint="t('xds.body_hint')" :error="err('rest_config.body')">
          <Textarea
            id="source-body"
            :model-value="rest.body ?? ''"
            rows="4"
            class="ltr-value font-mono text-sm w-full"
            spellcheck="false"
            @update:model-value="(v: string) => (rest.body = v || null)"
          />
        </ConfigField>
        <ConfigField :label="t('xds.records')" for="source-records" width="md" :hint="t('xds.records_hint')" :error="err('rest_config.records')">
          <InputText id="source-records" v-model="rest.records" class="ltr-value" size="small" placeholder="data.items" data-testid="source-records" />
        </ConfigField>
      </ConfigSection>

      <ConfigSection v-else id="source-db" :title="t('xds.section.db')" :description="t('xds.db_hint')">
        <EmptyState v-if="!connections.length" icon="pi pi-database" :title="t('xds.no_connections_title')" :description="t('xds.no_connections_text')" testid="source-no-connections" />
        <div v-else class="cfg-row">
          <ConfigField :label="t('xds.connection')" for="source-connection" width="md" :error="err('db_connection')">
            <Select v-model="doc.db_connection" input-id="source-connection" :options="connections" size="small" data-testid="source-connection" />
          </ConfigField>
          <ConfigField :label="t('xds.view')" for="source-view" width="md" :hint="t('xds.view_hint')" :error="err('db_view')">
            <InputText id="source-view" :model-value="doc.db_view ?? ''" class="ltr-value" size="small" data-testid="source-view" @update:model-value="(v) => (doc.db_view = v || null)" />
          </ConfigField>
        </div>
      </ConfigSection>

      <ConfigSection v-if="doc.type === 'rest'" id="source-auth" :title="t('xds.section.auth')" :description="t('xds.auth_hint')">
        <ConfigField :label="t('xds.auth_type')" for="source-auth-type" width="md" :error="err('auth_type')">
          <Select
            :model-value="doc.auth_type"
            input-id="source-auth-type"
            :options="authOptions"
            option-label="label"
            option-value="value"
            size="small"
            data-testid="source-auth"
            @update:model-value="setAuth"
          />
        </ConfigField>
        <ConfigField
          v-for="f in credentialFields"
          :key="f.name"
          :label="t(`xds.credential.${f.name}`)"
          :for="`source-cred-${f.name}`"
          width="md"
          :required="f.required"
          :error="err(`credentials.${f.name}`)"
        >
          <Password
            v-if="f.secret"
            :model-value="credential(f.name)"
            :input-id="`source-cred-${f.name}`"
            :feedback="false"
            toggle-mask
            size="small"
            fluid
            input-class="ltr-value"
            :placeholder="doc.credentials[`${f.name}_set`] ? t('xds.kept') : ''"
            :data-testid="`source-cred-${f.name}`"
            @update:model-value="(v) => setCredential(f.name, v)"
          />
          <InputText
            v-else
            :id="`source-cred-${f.name}`"
            :model-value="credential(f.name)"
            class="ltr-value"
            size="small"
            :data-testid="`source-cred-${f.name}`"
            @update:model-value="(v) => setCredential(f.name, v)"
          />
        </ConfigField>
        <ConfigField :label="t('xds.headers')" width="lg" :hint="t('xds.headers_hint')">
          <div class="flex flex-col gap-2">
            <NameValueRow
              v-for="(_, i) in doc.headers"
              :key="i"
              v-model="doc.headers[i]!"
              secret
              :name-label="t('xds.header_name')"
              :value-label="t('xds.header_value')"
              :error="err(`headers.${i}`)"
              @remove="doc.headers.splice(i, 1)"
            />
            <div><Button icon="pi pi-plus" :label="t('xds.add_header')" size="small" text @click="doc.headers.push({ name: '', value: '' })" /></div>
          </div>
        </ConfigField>
      </ConfigSection>

      <ConfigSection id="source-mapping" :title="t('xds.section.mapping')" :description="t(`xds.mapping_hint.${doc.type}`)">
        <div class="cfg-row">
          <ConfigField :label="t('xds.map_value')" for="source-map-value" width="sm" :hint="t('xds.map_value_hint')" :error="err('response_mapping.value')" required>
            <InputText id="source-map-value" v-model="doc.response_mapping.value" class="ltr-value" size="small" data-testid="source-map-value" />
          </ConfigField>
          <ConfigField :label="t('xds.map_label')" for="source-map-label" width="sm" :hint="t('xds.map_label_hint')" :error="err('response_mapping.label')" required>
            <InputText id="source-map-label" v-model="doc.response_mapping.label" class="ltr-value" size="small" data-testid="source-map-label" />
          </ConfigField>
          <ConfigField :label="t('xds.map_parent')" for="source-map-parent" width="sm" :hint="t('xds.map_parent_hint')" :error="err('response_mapping.parent')">
            <InputText id="source-map-parent" :model-value="doc.response_mapping.parent ?? ''" class="ltr-value" size="small" @update:model-value="(v) => (doc.response_mapping.parent = v || null)" />
          </ConfigField>
        </div>
        <div class="flex flex-col gap-2">
          <h3 class="text-sm font-semibold m-0">{{ t('xds.columns') }}</h3>
          <p class="m-0 text-xs text-muted-color">{{ t('xds.columns_hint') }}</p>
          <SourceColumnRow
            v-for="(_, i) in doc.response_mapping.columns"
            :key="i"
            v-model="doc.response_mapping.columns[i]!"
            :index="i"
            :error="err(`response_mapping.columns.${i}`)"
            @remove="doc.response_mapping.columns.splice(i, 1)"
          />
          <div><Button icon="pi pi-plus" :label="t('xds.add_column')" size="small" text @click="doc.response_mapping.columns.push({ key: '', path: '', label: {} })" /></div>
        </div>
      </ConfigSection>

      <ConfigSection id="source-cache" :title="t('xds.section.cache')" :description="t('xds.cache_hint')">
        <div class="cfg-row">
          <ConfigField :label="t('xds.cache_policy')" for="source-cache-policy" width="md" :hint="t(`xds.cache_desc.${doc.cache_policy}`)" :error="err('cache_policy')">
            <Select v-model="doc.cache_policy" input-id="source-cache-policy" :options="cacheOptions" option-label="label" option-value="value" size="small" />
          </ConfigField>
          <ConfigField v-if="doc.cache_policy !== 'none'" :label="t('xds.cache_ttl')" for="source-cache-ttl" width="sm" :hint="t('xds.cache_ttl_hint')" :error="err('cache_ttl_seconds')">
            <InputNumber v-model="doc.cache_ttl_seconds" input-id="source-cache-ttl" :min="30" :max="604800" size="small" />
          </ConfigField>
        </div>
      </ConfigSection>

      <ConfigSection id="source-test" :title="t('xds.section.test')" :description="t('xds.test_hint')">
        <EmptyState v-if="isNew" icon="pi pi-play" :title="t('xds.test_after_save')" :description="t('xds.test_after_save_text')" testid="source-test-later" />
        <template v-else>
          <div><Button icon="pi pi-play" :label="t('xds.run_test')" size="small" outlined :loading="testing" :disabled="dirty" data-testid="source-run-test" @click="runTest" /></div>
          <template v-if="result">
            <p v-if="!result.ok" class="m-0 text-sm text-danger" data-testid="source-test-error">{{ result.error }}</p>
            <template v-else>
              <p class="m-0 text-sm text-success" data-testid="source-test-ok"><i class="pi pi-check me-1" aria-hidden="true" />{{ t('xds.test_rows', { n: result.count }) }}</p>
              <DataTable :value="result.rows" size="small" class="records-table" data-testid="source-test-rows">
                <template #empty>{{ t('xds.test_no_rows') }}</template>
                <Column :header="t('xds.map_value')"
                  ><template #body="{ data }"
                    ><span class="ltr-value">{{ data.value }}</span></template
                  ></Column
                >
                <Column :header="t('xds.map_label')"
                  ><template #body="{ data }"
                    ><span dir="auto">{{ data.label }}</span></template
                  ></Column
                >
                <Column v-if="doc.response_mapping.parent" :header="t('xds.map_parent')"
                  ><template #body="{ data }"
                    ><span class="ltr-value">{{ data.parent }}</span></template
                  ></Column
                >
                <Column v-for="k in columnKeys" :key="k" :header="k"
                  ><template #body="{ data }"
                    ><span dir="auto">{{ data.cells[k] }}</span></template
                  ></Column
                >
              </DataTable>
              <details v-if="sampleText">
                <summary class="text-sm cursor-pointer">{{ t('xds.sample') }}</summary>
                <pre class="ltr-value text-xs bg-subtle rounded-md p-3 overflow-auto max-h-64" data-testid="source-sample">{{ sampleText }}</pre>
              </details>
            </template>
          </template>
        </template>
      </ConfigSection>

      <ConfigSaveBar :dirty="dirty || isNew" :saving="saving" testid="source" @save="save" @discard="discard" />
    </template>
  </div>
</template>
