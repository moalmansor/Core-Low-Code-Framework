<script setup lang="ts">
import Button from 'primevue/button'
import InputNumber from 'primevue/inputnumber'
import InputText from 'primevue/inputtext'
import Select from 'primevue/select'
import { useConfirm } from 'primevue/useconfirm'
import { useToast } from 'primevue/usetoast'
import { computed, onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { onBeforeRouteLeave, useRoute, useRouter } from 'vue-router'
import I18nInput from '@/builder/I18nInput.vue'
import ConfigField from '@/components/config/ConfigField.vue'
import ConfigSaveBar from '@/components/config/ConfigSaveBar.vue'
import ConfigSection from '@/components/config/ConfigSection.vue'
import SettingSwitch from '@/components/config/SettingSwitch.vue'
import { provideConfigScreen } from '@/components/config/configScreen'
import { formatDatetime } from '@/runtime/format'
import { errorText, fieldErrors } from '../admin/building/shared'
import { retentionApi, type PolicyDoc, type PolicyOptions } from './api'

/**
 * A retention policy (specification §4.26): what it applies to, how many
 * days data is kept and from which date, what happens then, and when it runs.
 * Audit logs can only be archived; the server refuses anything else.
 */
provideConfigScreen()
const { t, locale } = useI18n()
const toast = useToast()
const confirm = useConfirm()
const route = useRoute()
const router = useRouter()
const isNew = computed(() => route.params.policy === 'new')
const blank = (): PolicyDoc => ({
  name: {},
  data_class: 'records',
  form: null,
  application: null,
  retention_days: 365,
  date_basis: 'created_at',
  end_action: 'archive',
  archive_disk: 'archive',
  schedule: { cron: '30 2 * * *', timezone: Intl.DateTimeFormat().resolvedOptions().timeZone || 'UTC' },
  active: true,
})
const doc = ref<PolicyDoc>(blank())
const options = ref<PolicyOptions | null>(null)
const loaded = ref(false)
const saved = ref('')
const saving = ref(false)
const running = ref(false)
const errors = ref<Record<string, string>>({})
const err = (k: string) => errors.value[k]

onMounted(async () => {
  try {
    const [o, d] = await Promise.all([retentionApi.options(), isNew.value ? Promise.resolve(blank()) : retentionApi.load(String(route.params.policy))])
    options.value = o
    accept(d)
    loaded.value = true
  } catch (e) {
    toast.add({ severity: 'error', summary: errorText(e, t('ret.load_failed')), life: 6000 })
  }
})
function accept(d: PolicyDoc): void {
  if (Array.isArray(d.name)) d.name = {}
  d.schedule ??= blank().schedule
  doc.value = d
  saved.value = JSON.stringify(body())
}
function body(): PolicyDoc {
  const d = doc.value
  const scoped = ['records', 'recycle_bin', 'attachments'].includes(d.data_class)
  return {
    name: d.name,
    data_class: d.data_class,
    form: scoped ? d.form : null,
    application: scoped && !d.form ? d.application : null,
    retention_days: d.retention_days,
    date_basis: d.data_class === 'records' ? d.date_basis : 'created_at',
    end_action: d.data_class === 'audit_logs' ? 'archive' : d.end_action,
    archive_disk: d.archive_disk,
    schedule: d.schedule,
    active: d.active,
  }
}
const dirty = computed(() => loaded.value && JSON.stringify(body()) !== saved.value)
onBeforeRouteLeave(() => !dirty.value || window.confirm(t('records.leave_unsaved')))

const classOptions = computed(() => (options.value?.data_classes ?? []).map((v) => ({ value: v, label: t(`ret.class.${v}`) })))
const actionOptions = computed(() => (options.value?.end_actions ?? []).filter((v) => doc.value.data_class !== 'audit_logs' || v === 'archive').map((v) => ({ value: v, label: t(`ret.action.${v}`) })))
const basisOptions = computed(() => (options.value?.date_bases ?? []).map((v) => ({ value: v, label: t(`ret.basis.${v}`) })))
const diskOptions = computed(() => (options.value?.archive_disks ?? []).map((v) => ({ value: v, label: t(`ret.disk.${v}`) })))
const scoped = computed(() => ['records', 'recycle_bin', 'attachments'].includes(doc.value.data_class))
const cron = computed({ get: () => doc.value.schedule?.cron ?? '', set: (v: string) => (doc.value.schedule = { cron: v, timezone: doc.value.schedule?.timezone ?? 'UTC' }) })
const timezone = computed({ get: () => doc.value.schedule?.timezone ?? 'UTC', set: (v: string) => (doc.value.schedule = { cron: doc.value.schedule?.cron ?? '', timezone: v }) })

async function save(): Promise<void> {
  saving.value = true
  errors.value = {}
  try {
    const out = isNew.value ? await retentionApi.create(body()) : await retentionApi.save(doc.value.uuid!, body(), doc.value.hash!)
    accept(out)
    toast.add({ severity: 'success', summary: t('ret.saved'), life: 3000 })
    if (isNew.value) await router.replace({ name: 'admin.retention.policy', params: { policy: out.uuid } })
  } catch (e) {
    errors.value = fieldErrors(e)
    toast.add({ severity: 'error', summary: errorText(e, t('ret.save_failed')), life: 8000 })
  } finally {
    saving.value = false
  }
}
function discard(): void {
  accept({ ...doc.value, ...JSON.parse(saved.value) } as PolicyDoc)
  errors.value = {}
}
async function runNow(): Promise<void> {
  running.value = true
  try {
    await retentionApi.run(doc.value.uuid!)
    toast.add({ severity: 'success', summary: t('ret.run_started'), life: 4000 })
  } catch (e) {
    toast.add({ severity: 'error', summary: errorText(e, t('ret.run_failed')), life: 8000 })
  } finally {
    running.value = false
  }
}
function remove(): void {
  confirm.require({
    header: t('ret.delete_title'),
    message: t('ret.delete_text'),
    acceptProps: { label: t('ret.delete'), severity: 'danger' },
    rejectProps: { label: t('ret.cancel'), text: true },
    accept: async () => {
      try {
        await retentionApi.remove(doc.value.uuid!)
        saved.value = JSON.stringify(body())
        await router.push({ name: 'admin.retention' })
      } catch (e) {
        toast.add({ severity: 'error', summary: errorText(e, t('ret.delete_failed')), life: 8000 })
      }
    },
  })
}
</script>

<template>
  <div class="flex flex-col gap-6 max-w-[64rem]" data-testid="ret-policy-editor">
    <header class="page-head">
      <div class="flex-1 min-w-0">
        <RouterLink :to="{ name: 'admin.retention' }" class="text-sm text-primary inline-flex items-center gap-1 mb-1">
          <i class="pi pi-arrow-left rtl:rotate-180" aria-hidden="true" />{{ t('admin.area.retention') }}
        </RouterLink>
        <h1 class="page-title !mb-1" dir="auto">{{ isNew ? t('ret.new_policy') : (doc.label ?? t('ret.policy')) }}</h1>
        <p class="m-0 text-sm text-muted-color">{{ t('ret.editor_subtitle') }}</p>
      </div>
      <div v-if="!isNew && loaded" class="flex flex-wrap gap-2">
        <Button icon="pi pi-play" :label="t('ret.run_now')" size="small" :loading="running" :disabled="dirty || !doc.active" data-testid="ret-run" @click="runNow" />
        <Button icon="pi pi-trash" :label="t('ret.delete')" size="small" severity="danger" text data-testid="ret-delete" @click="remove" />
      </div>
    </header>

    <template v-if="loaded">
      <ConfigSection id="ret-what" :title="t('ret.section.what')">
        <I18nInput v-model="doc.name" class="w-field-md" :label="t('ret.name')" :maxlength="255" :invalid="!!err('name')" />
        <small v-if="err('name')" class="text-danger">{{ err('name') }}</small>
        <div class="cfg-row">
          <ConfigField :label="t('ret.applies_to')" for="ret-class" width="md" :hint="t(`ret.class_hint.${doc.data_class}`)" :error="err('data_class')">
            <Select v-model="doc.data_class" input-id="ret-class" :options="classOptions" option-label="label" option-value="value" size="small" data-testid="ret-class" />
          </ConfigField>
          <ConfigField v-if="scoped" :label="t('ret.form')" for="ret-form" width="md" :hint="t('ret.form_hint')" :error="err('form')">
            <Select v-model="doc.form" input-id="ret-form" :options="options?.forms ?? []" option-label="label" option-value="uuid" size="small" show-clear filter />
          </ConfigField>
          <ConfigField v-if="scoped && !doc.form" :label="t('ret.application')" for="ret-app" width="md" :error="err('application')">
            <Select v-model="doc.application" input-id="ret-app" :options="options?.applications ?? []" option-label="label" option-value="uuid" size="small" show-clear />
          </ConfigField>
        </div>
        <SettingSwitch id="ret-active" v-model="doc.active" :label="t('ret.active')" :description="t('ret.active_desc')" />
      </ConfigSection>

      <ConfigSection id="ret-how" :title="t('ret.section.how')">
        <div class="cfg-row">
          <ConfigField :label="t('ret.days')" for="ret-days" width="sm" :hint="t('ret.days_hint')" :error="err('retention_days')">
            <InputNumber v-model="doc.retention_days" input-id="ret-days" :min="1" :max="36500" size="small" data-testid="ret-days" />
          </ConfigField>
          <ConfigField v-if="doc.data_class === 'records'" :label="t('ret.basis_label')" for="ret-basis" width="md" :error="err('date_basis')">
            <Select v-model="doc.date_basis" input-id="ret-basis" :options="basisOptions" option-label="label" option-value="value" size="small" />
          </ConfigField>
        </div>
        <div class="cfg-row">
          <ConfigField :label="t('ret.end_action')" for="ret-action" width="md" :hint="t(`ret.action_hint.${doc.data_class === 'audit_logs' ? 'audit' : doc.end_action}`)" :error="err('end_action')">
            <Select v-model="doc.end_action" input-id="ret-action" :options="actionOptions" option-label="label" option-value="value" size="small" data-testid="ret-action" />
          </ConfigField>
          <ConfigField v-if="doc.end_action === 'archive' || doc.data_class === 'audit_logs'" :label="t('ret.disk_label')" for="ret-disk" width="sm" :error="err('archive_disk')">
            <Select v-model="doc.archive_disk" input-id="ret-disk" :options="diskOptions" option-label="label" option-value="value" size="small" />
          </ConfigField>
        </div>
      </ConfigSection>

      <ConfigSection id="ret-when" :title="t('ret.section.when')" :description="t('ret.schedule_hint')">
        <div class="cfg-row">
          <ConfigField :label="t('ret.cron')" for="ret-cron" width="md" :hint="t('ret.cron_hint')" :error="err('schedule.cron')">
            <InputText id="ret-cron" v-model="cron" class="ltr-value" size="small" placeholder="30 2 * * *" data-testid="ret-cron" />
          </ConfigField>
          <ConfigField :label="t('ret.timezone')" for="ret-tz" width="md">
            <InputText id="ret-tz" v-model="timezone" class="ltr-value" size="small" />
          </ConfigField>
        </div>
        <p v-if="doc.next_run_at" class="m-0 text-sm text-muted-color">{{ t('ret.next_run', { when: formatDatetime(doc.next_run_at, locale) }) }}</p>
      </ConfigSection>

      <ConfigSaveBar :dirty="dirty || isNew" :saving="saving" testid="ret-policy" @save="save" @discard="discard" />
    </template>
  </div>
</template>
