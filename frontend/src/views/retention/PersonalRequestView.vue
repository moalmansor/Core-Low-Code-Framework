<script setup lang="ts">
import Button from 'primevue/button'
import Column from 'primevue/column'
import DataTable from 'primevue/datatable'
import Tag from 'primevue/tag'
import Textarea from 'primevue/textarea'
import { useToast } from 'primevue/usetoast'
import { computed, onBeforeUnmount, onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRoute } from 'vue-router'
import { ApiError } from '@/api/http'
import ConfigField from '@/components/config/ConfigField.vue'
import ConfigSection from '@/components/config/ConfigSection.vue'
import EmptyValue from '@/components/EmptyValue.vue'
import StepUpDialog from '@/components/StepUpDialog.vue'
import { formatDatetime } from '@/runtime/format'
import { errorText } from '../admin/building/shared'
import { personalApi, severityOf, type PersonalRequest } from './api'

/**
 * One personal data request: who it is about, what the search found (records
 * by form, the account, logs), and the review — approve with a reason (and a
 * fresh two-factor code to delete or anonymize) or reject with a reason.
 * Records under legal hold are shown and are never changed.
 */
const { t, locale } = useI18n()
const toast = useToast()
const route = useRoute()
const uuid = String(route.params.request)
const req = ref<PersonalRequest | null>(null)
const reason = ref('')
const busy = ref(false)
const stepUp = ref<InstanceType<typeof StepUpDialog> | null>(null)
let timer: ReturnType<typeof setTimeout> | null = null

async function load(): Promise<void> {
  try {
    req.value = await personalApi.load(uuid)
    if (req.value.status === 'received' || req.value.status === 'locating' || req.value.status === 'executing') timer = setTimeout(load, 1500)
  } catch (e) {
    toast.add({ severity: 'error', summary: errorText(e, t('ret.load_failed')), life: 6000 })
  }
}
onMounted(load)
onBeforeUnmount(() => timer && clearTimeout(timer))

const findings = computed(() => req.value?.findings ?? null)
const subjectLines = computed(() => {
  const s = req.value?.subject ?? {}
  return (['email', 'identifier', 'name'] as const).filter((k) => s[k]).map((k) => ({ key: k, value: s[k]! }))
})
const logs = computed(() => Object.entries(findings.value?.logs ?? {}).map(([key, count]) => ({ key, count })))
const destructive = computed(() => req.value?.type === 'delete' || req.value?.type === 'anonymize')

async function approve(): Promise<void> {
  if (!req.value) return
  busy.value = true
  try {
    let code: string | null = null
    if (destructive.value) {
      code = (await stepUp.value?.ask()) ?? null
      if (!code) return
    }
    req.value = await personalApi.execute(uuid, reason.value, code)
    toast.add({ severity: 'success', summary: t('ret.pd_done'), life: 5000 })
  } catch (e) {
    const msg = e instanceof ApiError ? (e.fieldErrors.confirmation_code ?? null) : null
    toast.add({ severity: 'error', summary: msg ?? errorText(e, t('ret.pd_failed')), life: 8000 })
  } finally {
    busy.value = false
  }
}
async function reject(): Promise<void> {
  busy.value = true
  try {
    req.value = await personalApi.reject(uuid, reason.value)
  } catch (e) {
    toast.add({ severity: 'error', summary: errorText(e, t('ret.pd_failed')), life: 8000 })
  } finally {
    busy.value = false
  }
}
async function locateAgain(): Promise<void> {
  req.value = await personalApi.locate(uuid)
  timer = setTimeout(load, 1500)
}
</script>

<template>
  <div class="flex flex-col gap-6 max-w-[64rem]" data-testid="ret-pd-request">
    <header class="page-head">
      <div class="flex-1 min-w-0">
        <RouterLink :to="{ name: 'admin.retention', params: { tab: 'personal' } }" class="text-sm text-primary inline-flex items-center gap-1 mb-1">
          <i class="pi pi-arrow-left rtl:rotate-180" aria-hidden="true" />{{ t('ret.tab.personal') }}
        </RouterLink>
        <h1 class="page-title !mb-1">{{ req ? t(`ret.pd_type.${req.type}`) : t('ret.pd_request') }}</h1>
        <p v-if="req" class="m-0 text-sm text-muted-color flex flex-wrap items-center gap-2">
          <Tag :severity="severityOf(req.status)" :value="t(`ret.pd_status.${req.status}`)" />
          <span v-if="req.created_at">{{ t('ret.pd_received_by', { who: req.created_by ?? '', when: formatDatetime(req.created_at, locale) }) }}</span>
        </p>
      </div>
      <div v-if="req && ['awaiting_review', 'failed'].includes(req.status)" class="flex gap-2">
        <Button icon="pi pi-search" :label="t('ret.pd_locate_again')" size="small" outlined @click="locateAgain" />
      </div>
    </header>

    <template v-if="req">
      <ConfigSection id="pd-subject" :title="t('ret.pd_subject')">
        <dl class="grid grid-cols-[max-content_1fr] gap-x-6 gap-y-2 m-0 text-sm">
          <template v-for="s in subjectLines" :key="s.key">
            <dt class="text-muted-color">{{ t(`ret.pd_${s.key}`) }}</dt>
            <dd class="m-0" dir="auto">{{ s.value }}</dd>
          </template>
          <template v-if="findings?.user">
            <dt class="text-muted-color">{{ t('ret.pd_user') }}</dt>
            <dd class="m-0" dir="auto">
              {{ findings.user.name }} <span class="ltr-value text-muted-color">{{ findings.user.email }}</span>
            </dd>
          </template>
        </dl>
      </ConfigSection>

      <ConfigSection id="pd-found" :title="t('ret.pd_found')" :description="findings ? t('ret.pd_found_hint', { when: formatDatetime(findings.located_at, locale) }) : t('ret.pd_locating')">
        <DataTable :value="findings?.forms ?? []" data-key="uuid" size="small" class="records-table" data-testid="pd-forms">
          <template #empty>{{ findings ? t('ret.pd_no_records') : t('ret.pd_locating') }}</template>
          <Column :header="t('ret.form')">
            <template #body="{ data }"
              ><span dir="auto">{{ data.name }}</span></template
            >
          </Column>
          <Column :header="t('ret.pd_matched')">
            <template #body="{ data }">{{ t('ret.pd_matched_text', { count: data.records.length }) }}</template>
          </Column>
          <Column :header="t('ret.pd_owned')">
            <template #body="{ data }">{{ data.owned.length }}</template>
          </Column>
          <Column :header="t('ret.pd_held')">
            <template #body="{ data }"><Tag v-if="data.held" severity="warn" :value="String(data.held)" /><EmptyValue v-else /></template>
          </Column>
          <Column :header="t('ret.pd_unsearchable')">
            <template #body="{ data }"
              ><span v-if="data.unsearchable.length" class="text-sm">{{ t('ret.pd_unsearchable_text', { count: data.unsearchable.length }) }}</span
              ><EmptyValue v-else
            /></template>
          </Column>
        </DataTable>
        <DataTable v-if="findings" :value="logs" data-key="key" size="small" class="records-table">
          <template #empty>{{ t('ret.pd_no_records') }}</template>
          <Column :header="t('ret.pd_log')">
            <template #body="{ data }">{{ t(`ret.source.${data.key}`) }}</template>
          </Column>
          <Column :header="t('ret.pd_entries')">
            <template #body="{ data }">{{ data.count }}</template>
          </Column>
        </DataTable>
        <p v-if="findings?.result" class="m-0 text-sm" data-testid="pd-result">{{ t('ret.pd_result', { records: findings.result.records, held: findings.result.skipped_legal_hold }) }}</p>
        <a v-if="req.has_file" :href="personalApi.fileUrl(uuid)" class="text-link text-sm" download data-testid="pd-download">{{ t('ret.pd_download') }}</a>
      </ConfigSection>

      <ConfigSection v-if="req.status === 'awaiting_review'" id="pd-review" :title="t('ret.pd_review')" :description="t(`ret.pd_review_hint.${req.type}`)">
        <ConfigField :label="t('ret.reason')" for="pd-reason" width="full" :hint="t('ret.pd_reason_hint')">
          <Textarea id="pd-reason" v-model="reason" rows="3" auto-resize data-testid="pd-reason" />
        </ConfigField>
        <div class="flex flex-wrap gap-2">
          <Button
            :icon="destructive ? 'pi pi-exclamation-triangle' : 'pi pi-check'"
            :label="t(`ret.pd_approve.${req.type}`)"
            :severity="destructive ? 'danger' : undefined"
            size="small"
            :loading="busy"
            :disabled="reason.trim().length < 10"
            data-testid="pd-approve"
            @click="approve"
          />
          <Button :label="t('ret.pd_reject')" size="small" outlined :disabled="busy || reason.trim().length < 10" data-testid="pd-reject" @click="reject" />
        </div>
      </ConfigSection>
      <ConfigSection v-else-if="req.reason" id="pd-decision" :title="t('ret.pd_decision')">
        <p class="m-0 text-sm" dir="auto">{{ req.reason }}</p>
        <p v-if="req.completed_at" class="m-0 text-sm text-muted-color">{{ t('ret.pd_decided_by', { who: req.completed_by ?? '', when: formatDatetime(req.completed_at, locale) }) }}</p>
      </ConfigSection>
    </template>
    <StepUpDialog ref="stepUp" />
  </div>
</template>
