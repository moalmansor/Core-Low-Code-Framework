<script setup lang="ts">
import Button from 'primevue/button'
import Column from 'primevue/column'
import DataTable from 'primevue/datatable'
import Dialog from 'primevue/dialog'
import InputNumber from 'primevue/inputnumber'
import InputText from 'primevue/inputtext'
import Tab from 'primevue/tab'
import TabList from 'primevue/tablist'
import TabPanel from 'primevue/tabpanel'
import TabPanels from 'primevue/tabpanels'
import Tabs from 'primevue/tabs'
import Tag from 'primevue/tag'
import ToggleSwitch from 'primevue/toggleswitch'
import { useToast } from 'primevue/usetoast'
import { computed, onMounted, reactive, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { ApiError, get, send } from '@/api/http'
import FormPicker from '@/builder/FormPicker.vue'
import DelegationsPanel from '@/components/DelegationsPanel.vue'
import SubjectPicker from '@/components/SubjectPicker.vue'
import { useSession } from '@/stores/session'
import LocaleFields from './building/LocaleFields.vue'
import { errorText, fieldErrors } from './building/shared'
import { viewsApi, type Path, type PathNode } from './formconfig/api'
import PathPicker from './formconfig/PathPicker.vue'

/**
 * Assignment, queues and delegation (specification §4.25): work queues of a
 * role or department — which forms appear in My Work, with which columns and
 * how long a claim lasts — and every delegation and out-of-office cover.
 */
interface QueueRow {
  uuid: string
  key: string
  name: string
  names: Record<string, string>
  type: 'role' | 'department'
  subject: string | null
  claim_timeout_minutes: number | null
  is_active: boolean
  forms: { form: string; key: string; name: string; columns: Path[] }[]
  updated_at: string | null
}
const { t } = useI18n()
const toast = useToast()
const session = useSession()
const canQueues = computed(() => session.can('system.manage_forms'))
const canDelegations = computed(() => session.can('system.manage_delegation'))
const tab = ref(canQueues.value ? 'queues' : 'delegations')

const queues = ref<QueueRow[]>([])
const loading = ref(false)
async function load(): Promise<void> {
  if (!canQueues.value) return
  loading.value = true
  try {
    queues.value = (await get<{ data: QueueRow[] }>('/queues')).data
  } catch (e) {
    toast.add({ severity: 'error', summary: errorText(e, t('building.load_failed')), life: 6000 })
  } finally {
    loading.value = false
  }
}
onMounted(load)

type Editing = Omit<QueueRow, 'name' | 'forms' | 'subject' | 'uuid' | 'updated_at'> & {
  uuid: string | null
  updated_at: string | null
  subject: { type: string; uuid: string | null } | null
  forms: { form: string | null; columns: Path[] }[]
}
const editing = ref<Editing | null>(null)
const errors = ref<Record<string, string>>({})
const saving = ref(false)
const trees = reactive<Record<string, PathNode[]>>({})
async function tree(form: string | null): Promise<void> {
  if (form && !trees[form]) trees[form] = ((await viewsApi.load(form).catch(() => null))?.extra.fields as PathNode[] | undefined) ?? []
}

function create(): void {
  errors.value = {}
  editing.value = { uuid: null, updated_at: null, key: '', names: {}, type: 'role', subject: null, claim_timeout_minutes: null, is_active: true, forms: [] }
}
function edit(q: QueueRow): void {
  errors.value = {}
  editing.value = {
    uuid: q.uuid,
    updated_at: q.updated_at,
    key: q.key,
    names: { ...q.names },
    type: q.type,
    subject: q.subject ? { type: q.type, uuid: q.subject } : null,
    claim_timeout_minutes: q.claim_timeout_minutes,
    is_active: q.is_active,
    forms: q.forms.map((f) => ({ form: f.form, columns: f.columns.map((c) => [...c]) })),
  }
  q.forms.forEach((f) => void tree(f.form))
}
async function save(): Promise<void> {
  const e = editing.value
  if (!e) return
  saving.value = true
  errors.value = {}
  const body = {
    key: e.key,
    name: e.names,
    type: e.subject?.type ?? e.type,
    subject: e.subject?.uuid ?? null,
    claim_timeout_minutes: e.claim_timeout_minutes,
    is_active: e.is_active,
    forms: e.forms.filter((f) => f.form).map((f) => ({ form: f.form, columns: f.columns.filter((c) => c.length) })),
  }
  try {
    if (e.uuid) await send('patch', `/queues/${e.uuid}`, { ...body, base_updated_at: e.updated_at })
    else await send('post', '/queues', body)
    editing.value = null
    toast.add({ severity: 'success', summary: t('workflow.saved'), life: 3000 })
    await load()
  } catch (err) {
    if (err instanceof ApiError && err.status === 409) {
      toast.add({ severity: 'warn', summary: err.message, life: 8000 })
      editing.value = null
      await load()
    } else {
      errors.value = fieldErrors(err)
      toast.add({ severity: 'error', summary: errorText(err, t('workflow.save_failed')), life: 6000 })
    }
  } finally {
    saving.value = false
  }
}
</script>

<template>
  <div class="flex flex-col gap-3">
    <h1 class="page-title">{{ t('admin.area.assignment_queues') }}</h1>
    <Tabs v-model:value="tab">
      <TabList>
        <Tab v-if="canQueues" value="queues" data-testid="work-tab-queues">{{ t('queues.title') }}</Tab>
        <Tab v-if="canDelegations" value="delegations" data-testid="work-tab-delegations">{{ t('delegation.title') }}</Tab>
      </TabList>
      <TabPanels>
        <TabPanel v-if="canQueues" value="queues">
          <div class="flex flex-col gap-3" data-testid="queues">
            <div class="flex items-center gap-2">
              <p class="text-sm text-muted-color flex-1">{{ t('queues.hint') }}</p>
              <Button icon="pi pi-plus" :label="t('queues.add')" size="small" data-testid="queue-add" @click="create" />
            </div>
            <DataTable :value="queues" :loading="loading" data-key="uuid" size="small" scrollable>
              <Column field="name" :header="t('views.name')" />
              <Column :header="t('workflow.key')"
                ><template #body="{ data }"
                  ><span class="ltr-value">{{ data.key }}</span></template
                ></Column
              >
              <Column :header="t('queues.members')"
                ><template #body="{ data }">{{ t(`subjects.${data.type}`) }}</template></Column
              >
              <Column :header="t('queues.forms')"
                ><template #body="{ data }">{{ data.forms.map((f: { name: string }) => f.name).join(', ') }}</template></Column
              >
              <Column :header="t('reason_codes.state')">
                <template #body="{ data }"><Tag :severity="data.is_active ? 'success' : 'secondary'" :value="data.is_active ? t('reason_codes.active') : t('reason_codes.inactive')" /></template>
              </Column>
              <Column
                ><template #body="{ data }"><Button icon="pi pi-pencil" text size="small" :aria-label="t('common.edit')" @click="edit(data)" /></template
              ></Column>
              <template #empty>{{ t('queues.empty') }}</template>
            </DataTable>
          </div>
        </TabPanel>
        <TabPanel v-if="canDelegations" value="delegations">
          <DelegationsPanel admin />
        </TabPanel>
      </TabPanels>
    </Tabs>

    <Dialog :visible="!!editing" modal :header="editing?.uuid ? t('queues.edit') : t('queues.add')" class="w-full max-w-2xl" @update:visible="(v) => !v && (editing = null)">
      <form v-if="editing" class="flex flex-col gap-3" @submit.prevent="save">
        <div class="field">
          <label for="q-key">{{ t('workflow.key') }}</label>
          <InputText id="q-key" v-model="editing.key" class="ltr-value" maxlength="48" :invalid="!!errors.key" />
          <span v-if="errors.key" class="field-error">{{ errors.key }}</span>
        </div>
        <LocaleFields v-model="editing.names" :label="t('views.name')" field="name" :errors="errors" id-prefix="q-name" />
        <div class="field">
          <span class="text-sm font-medium">{{ t('queues.members') }}</span>
          <SubjectPicker v-model="editing.subject" :types="['role', 'department']" :invalid="!!errors.subject" />
          <span v-if="errors.subject" class="field-error">{{ errors.subject }}</span>
        </div>
        <div class="flex flex-wrap items-end gap-4">
          <div class="field w-48">
            <label for="q-claim">{{ t('queues.claim_timeout') }}</label>
            <InputNumber v-model="editing.claim_timeout_minutes" input-id="q-claim" :min="1" :max="525600" :suffix="` ${t('workflow.minutes')}`" />
          </div>
          <label class="flex items-center gap-2 text-sm pb-2"><ToggleSwitch v-model="editing.is_active" />{{ t('reason_codes.active') }}</label>
        </div>
        <h3 class="font-semibold">{{ t('queues.forms') }}</h3>
        <section v-for="(f, i) in editing.forms" :key="i" class="rounded-lg border border-line p-2 flex flex-col gap-2">
          <div class="flex items-end gap-2">
            <div class="field flex-1">
              <label :for="`q-form-${i}`">{{ t('queues.form') }}</label>
              <FormPicker v-model="f.form" kind="form" :input-id="`q-form-${i}`" @update:model-value="(v) => ((f.columns = []), tree(v ?? null))" />
            </div>
            <Button icon="pi pi-trash" text severity="danger" size="small" :aria-label="t('workflow.remove')" @click="editing.forms.splice(i, 1)" />
          </div>
          <span class="text-sm font-medium">{{ t('queues.columns') }}</span>
          <div v-for="(_, j) in f.columns" :key="j" class="flex items-end gap-2">
            <PathPicker v-model="f.columns[j]" :tree="(trees[f.form ?? ''] ?? []).filter((n) => n.type !== 'system')" class="flex-1" />
            <Button icon="pi pi-trash" text severity="danger" size="small" :aria-label="t('workflow.remove')" @click="f.columns.splice(j, 1)" />
          </div>
          <div><Button icon="pi pi-plus" :label="t('views.add_column')" size="small" outlined :disabled="!f.form || f.columns.length >= 12" @click="f.columns.push([])" /></div>
        </section>
        <div><Button icon="pi pi-plus" :label="t('queues.add_form')" size="small" outlined @click="editing.forms.push({ form: null, columns: [] })" /></div>
        <div class="flex justify-end gap-2">
          <Button type="button" :label="t('workflow.cancel')" text @click="editing = null" />
          <Button type="submit" :label="t('workflow.save')" icon="pi pi-check" :loading="saving" data-testid="queue-save" />
        </div>
      </form>
    </Dialog>
  </div>
</template>
