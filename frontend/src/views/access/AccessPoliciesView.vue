<script setup lang="ts">
import Button from 'primevue/button'
import Column from 'primevue/column'
import DataTable from 'primevue/datatable'
import Dialog from 'primevue/dialog'
import InputNumber from 'primevue/inputnumber'
import InputText from 'primevue/inputtext'
import Message from 'primevue/message'
import MultiSelect from 'primevue/multiselect'
import Select from 'primevue/select'
import Tag from 'primevue/tag'
import Textarea from 'primevue/textarea'
import { useConfirm } from 'primevue/useconfirm'
import { useToast } from 'primevue/usetoast'
import { computed, onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { get } from '@/api/http'
import I18nInput from '@/builder/I18nInput.vue'
import ConfigField from '@/components/config/ConfigField.vue'
import ConfigSection from '@/components/config/ConfigSection.vue'
import { provideConfigScreen } from '@/components/config/configScreen'
import EmptyState from '@/components/config/EmptyState.vue'
import EmptyValue from '@/components/EmptyValue.vue'
import SettingSwitch from '@/components/config/SettingSwitch.vue'
import { errorText, fieldErrors } from '../admin/building/shared'
import { policiesApi, timeZones, WEEKDAYS, weekdayName, type PolicyDoc } from './api'

/**
 * Access policies (specification §4.36, ADR-0060): where, when and how the
 * holders of some roles may work. A user governed by several gets the
 * strictest combination.
 */
provideConfigScreen()
const { t, locale } = useI18n()
const toast = useToast()
const confirm = useConfirm()
const list = ref<PolicyDoc[]>([])
const loading = ref(true)
const roles = ref<{ uuid: string; name: string }[]>([])
const zones = timeZones()
const days = computed(() => WEEKDAYS.map((d) => ({ value: d, label: weekdayName(d, locale.value) })))
const roleName = (uuid: string) => roles.value.find((r) => r.uuid === uuid)?.name ?? ''

type Editing = { doc: PolicyDoc; ips: string }
const editing = ref<Editing | null>(null)
const errors = ref<Record<string, string>>({})
const saving = ref(false)

async function load(): Promise<void> {
  loading.value = true
  try {
    const [l, r] = await Promise.all([policiesApi.list(), get<{ data: { uuid: string; name: string }[] }>('/role-options').then((x) => x.data)])
    list.value = l
    roles.value = r
  } catch (e) {
    toast.add({ severity: 'error', summary: errorText(e, t('pa.load_failed')), life: 6000 })
  } finally {
    loading.value = false
  }
}
onMounted(load)

function create(): void {
  errors.value = {}
  editing.value = {
    doc: {
      key: '',
      i18n: { name: {} },
      ip_allowlist: [],
      time_windows: [],
      max_concurrent_sessions: null,
      session_idle_minutes: 30,
      session_absolute_minutes: 720,
      require_trusted_device: false,
      require_2fa: false,
      is_admin_policy: false,
      roles: [],
    },
    ips: '',
  }
}
function edit(p: PolicyDoc): void {
  errors.value = {}
  editing.value = { doc: JSON.parse(JSON.stringify(p)) as PolicyDoc, ips: p.ip_allowlist.join('\n') }
}
function addWindow(): void {
  editing.value?.doc.time_windows.push({ days: [1, 2, 3, 4, 5], from: '08:00', to: '17:00', timezone: Intl.DateTimeFormat().resolvedOptions().timeZone || 'UTC' })
}
/** The first error of a time window, whichever part it is about. */
const windowError = (i: number) => errors.value[`time_windows.${i}.days`] ?? errors.value[`time_windows.${i}.from`] ?? errors.value[`time_windows.${i}.timezone`]
const ipError = computed(() => Object.entries(errors.value).find(([k]) => k.startsWith('ip_allowlist'))?.[1])

async function save(): Promise<void> {
  const e = editing.value
  if (!e) return
  saving.value = true
  errors.value = {}
  const doc: PolicyDoc = {
    ...e.doc,
    ip_allowlist: e.ips
      .split(/[\s,]+/)
      .map((x) => x.trim())
      .filter(Boolean),
  }
  try {
    if (doc.uuid) await policiesApi.save(doc.uuid, doc, doc.hash!)
    else await policiesApi.create(doc)
    editing.value = null
    toast.add({ severity: 'success', summary: t('pa.saved'), life: 3000 })
    await load()
  } catch (err) {
    errors.value = fieldErrors(err)
    toast.add({ severity: 'error', summary: errorText(err, t('pa.save_failed')), life: 8000 })
  } finally {
    saving.value = false
  }
}
function remove(p: PolicyDoc): void {
  confirm.require({
    header: t('pa.delete_title'),
    message: t('pa.delete_text'),
    acceptProps: { label: t('pa.delete'), severity: 'danger' },
    rejectProps: { label: t('pa.cancel'), text: true },
    accept: async () => {
      try {
        await policiesApi.remove(p.uuid!)
        editing.value = null
        await load()
      } catch (err) {
        toast.add({ severity: 'error', summary: errorText(err, t('pa.delete_failed')), life: 6000 })
      }
    },
  })
}
</script>

<template>
  <div class="flex flex-col gap-4" data-testid="access-policies">
    <header class="page-head">
      <div class="flex-1 min-w-0">
        <h1 class="page-title !mb-1">{{ t('admin.area.access_policies') }}</h1>
        <p class="m-0 text-sm text-muted-color max-w-[var(--measure)]">{{ t('pa.subtitle') }}</p>
      </div>
      <Button icon="pi pi-plus" :label="t('pa.new')" size="small" data-testid="policy-new" @click="create" />
    </header>
    <EmptyState v-if="!loading && !list.length" icon="pi pi-shield" :title="t('pa.empty_title')" :description="t('pa.empty_text')" testid="policies-empty">
      <Button icon="pi pi-plus" :label="t('pa.new')" size="small" @click="create" />
    </EmptyState>
    <DataTable v-else :value="list" data-key="uuid" :loading="loading" size="small" class="records-table" row-hover @row-click="(e) => edit(e.data as PolicyDoc)">
      <Column :header="t('pa.name')">
        <template #body="{ data }"
          ><span class="font-medium" dir="auto">{{ data.label }}</span></template
        >
      </Column>
      <Column :header="t('pa.applies_to')">
        <template #body="{ data }">
          <div class="flex flex-wrap gap-1">
            <Tag v-if="data.is_admin_policy" severity="warn" :value="t('pa.admins')" />
            <Tag v-for="r in data.roles" :key="r" severity="secondary" :value="roleName(r)" />
            <EmptyValue v-if="!data.is_admin_policy && !data.roles.length" />
          </div>
        </template>
      </Column>
      <Column :header="t('pa.rules')">
        <template #body="{ data }">
          <div class="flex flex-wrap gap-1 text-xs">
            <Tag v-if="data.ip_allowlist.length" severity="info" :value="t('pa.networks', { n: data.ip_allowlist.length })" />
            <Tag v-if="data.time_windows.length" severity="info" :value="t('pa.windows', { n: data.time_windows.length })" />
            <Tag v-if="data.max_concurrent_sessions" severity="info" :value="t('pa.sessions_max', { n: data.max_concurrent_sessions })" />
            <Tag v-if="data.require_2fa" severity="info" :value="t('pa.two_factor')" />
            <Tag v-if="data.require_trusted_device" severity="info" :value="t('pa.trusted_device')" />
          </div>
        </template>
      </Column>
      <Column :header="t('pa.idle')">
        <template #body="{ data }">{{ t('pa.minutes', { n: data.session_idle_minutes }) }}</template>
      </Column>
    </DataTable>
  </div>

  <Dialog :visible="!!editing" modal :header="editing?.doc.uuid ? t('pa.edit') : t('pa.new')" class="w-[46rem] max-w-[95vw]" @update:visible="(v: boolean) => !v && (editing = null)">
    <form v-if="editing" class="flex flex-col gap-4" data-testid="policy-form" @submit.prevent="save">
      <Message v-if="errors.policy" severity="error" data-testid="policy-lockout">{{ errors.policy }}</Message>
      <ConfigSection id="pa-who" :title="t('pa.section.who')">
        <I18nInput v-model="editing.doc.i18n.name" :label="t('pa.name')" :maxlength="255" :invalid="!!errors['i18n.name']" />
        <small v-if="errors['i18n.name']" class="text-danger">{{ errors['i18n.name'] }}</small>
        <ConfigField :label="t('pa.key')" for="pa-key" width="md" :hint="t('pa.key_hint')" :error="errors.key">
          <InputText id="pa-key" v-model="editing.doc.key" class="ltr-value" size="small" data-testid="policy-key" />
        </ConfigField>
        <ConfigField :label="t('pa.roles')" for="pa-roles" width="lg" :hint="t('pa.roles_hint')" :error="errors.roles">
          <MultiSelect v-model="editing.doc.roles" input-id="pa-roles" :options="roles" option-label="name" option-value="uuid" filter display="chip" size="small" data-testid="policy-roles" />
        </ConfigField>
        <SettingSwitch id="pa-admin" v-model="editing.doc.is_admin_policy" :label="t('pa.admin_policy')" :description="t('pa.admin_policy_desc')" />
      </ConfigSection>

      <ConfigSection id="pa-where" :title="t('pa.section.where_when')">
        <ConfigField :label="t('pa.networks_label')" for="pa-ips" width="lg" :hint="t('pa.networks_hint')" :error="ipError">
          <Textarea id="pa-ips" v-model="editing.ips" rows="3" auto-resize class="ltr-value" data-testid="policy-ips" />
        </ConfigField>
        <div class="flex flex-col gap-2">
          <span class="font-medium text-sm">{{ t('pa.windows_label') }}</span>
          <small class="text-muted-color">{{ t('pa.windows_hint') }}</small>
          <div v-for="(w, i) in editing.doc.time_windows" :key="i" class="flex flex-wrap items-end gap-2 border border-surface rounded-lg p-2" :data-testid="`policy-window-${i}`">
            <ConfigField :label="t('pa.days')" :for="`pa-days-${i}`" width="md">
              <MultiSelect v-model="w.days" :input-id="`pa-days-${i}`" :options="days" option-label="label" option-value="value" display="chip" size="small" />
            </ConfigField>
            <ConfigField :label="t('pa.from')" :for="`pa-from-${i}`" width="xs">
              <InputText :id="`pa-from-${i}`" v-model="w.from" type="time" size="small" class="ltr-value" />
            </ConfigField>
            <ConfigField :label="t('pa.to')" :for="`pa-to-${i}`" width="xs">
              <InputText :id="`pa-to-${i}`" v-model="w.to" type="time" size="small" class="ltr-value" />
            </ConfigField>
            <ConfigField :label="t('pa.timezone')" :for="`pa-tz-${i}`" width="sm">
              <Select v-model="w.timezone" :input-id="`pa-tz-${i}`" :options="zones" filter size="small" class="ltr-value" />
            </ConfigField>
            <Button type="button" icon="pi pi-trash" text severity="danger" :aria-label="t('pa.remove_window')" @click="editing.doc.time_windows.splice(i, 1)" />
            <small v-if="windowError(i)" class="text-danger basis-full">{{ windowError(i) }}</small>
          </div>
          <Button type="button" icon="pi pi-plus" :label="t('pa.add_window')" text size="small" class="self-start" data-testid="policy-add-window" @click="addWindow" />
        </div>
      </ConfigSection>

      <ConfigSection id="pa-sessions" :title="t('pa.section.sessions')">
        <div class="cfg-row">
          <ConfigField :label="t('pa.max_sessions')" for="pa-max" width="sm" :hint="t('pa.max_sessions_hint')" :error="errors.max_concurrent_sessions">
            <InputNumber v-model="editing.doc.max_concurrent_sessions" input-id="pa-max" :min="1" :max="100" :use-grouping="false" size="small" />
          </ConfigField>
          <ConfigField :label="t('pa.idle')" for="pa-idle" width="sm" :error="errors.session_idle_minutes">
            <InputNumber v-model="editing.doc.session_idle_minutes" input-id="pa-idle" :min="5" :max="1440" :use-grouping="false" :suffix="` ${t('pa.min')}`" size="small" />
          </ConfigField>
          <ConfigField :label="t('pa.absolute')" for="pa-absolute" width="sm" :error="errors.session_absolute_minutes">
            <InputNumber v-model="editing.doc.session_absolute_minutes" input-id="pa-absolute" :min="15" :max="43200" :use-grouping="false" :suffix="` ${t('pa.min')}`" size="small" />
          </ConfigField>
        </div>
        <SettingSwitch id="pa-2fa" v-model="editing.doc.require_2fa" :label="t('pa.require_2fa')" :description="t('pa.require_2fa_desc')" />
        <SettingSwitch id="pa-device" v-model="editing.doc.require_trusted_device" :label="t('pa.require_device')" :description="t('pa.require_device_desc')" />
      </ConfigSection>

      <div class="flex flex-wrap justify-end gap-2">
        <Button v-if="editing.doc.uuid" type="button" severity="danger" text icon="pi pi-trash" :label="t('pa.delete')" class="me-auto" @click="remove(editing.doc)" />
        <Button type="button" severity="secondary" :label="t('pa.cancel')" @click="editing = null" />
        <Button type="submit" :label="t('pa.save')" :loading="saving" data-testid="policy-save" />
      </div>
    </form>
  </Dialog>
</template>
