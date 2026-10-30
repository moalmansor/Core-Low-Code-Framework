<script setup lang="ts">
import Button from 'primevue/button'
import Column from 'primevue/column'
import DataTable from 'primevue/datatable'
import Dialog from 'primevue/dialog'
import InputText from 'primevue/inputtext'
import Select from 'primevue/select'
import Tag from 'primevue/tag'
import { useToast } from 'primevue/usetoast'
import { computed, onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRouter } from 'vue-router'
import ConfigField from '@/components/config/ConfigField.vue'
import EmptyState from '@/components/config/EmptyState.vue'
import TabIntro from '@/components/config/TabIntro.vue'
import UserPicker from '@/components/UserPicker.vue'
import { formatDatetime } from '@/runtime/format'
import { errorText, fieldErrors } from '../admin/building/shared'
import { personalApi, severityOf, type PersonalRequest, type RequestType } from './api'

/**
 * Personal data requests (specification §4.26): a person asks to see, take
 * away, erase or anonymize what the system holds about them. A request is
 * located first; nothing changes until it is reviewed and approved.
 */
const { t, locale } = useI18n()
const toast = useToast()
const router = useRouter()
const rows = ref<PersonalRequest[]>([])
const loading = ref(true)
async function load(): Promise<void> {
  try {
    rows.value = await personalApi.list()
  } catch (e) {
    toast.add({ severity: 'error', summary: errorText(e, t('ret.load_failed')), life: 6000 })
  } finally {
    loading.value = false
  }
}
onMounted(load)

const creating = ref(false)
const type = ref<RequestType>('locate')
const email = ref('')
const identifier = ref('')
const name = ref('')
const user = ref<{ uuid: string; name: string; email: string } | null>(null)
const errors = ref<Record<string, string>>({})
const busy = ref(false)
const types = computed(() => (['locate', 'export', 'anonymize', 'delete'] as RequestType[]).map((v) => ({ value: v, label: t(`ret.pd_type.${v}`) })))
function openNew(): void {
  type.value = 'locate'
  email.value = identifier.value = name.value = ''
  user.value = null
  errors.value = {}
  creating.value = true
}
async function create(): Promise<void> {
  busy.value = true
  errors.value = {}
  try {
    const out = await personalApi.create(type.value, {
      ...(email.value.trim() ? { email: email.value.trim() } : {}),
      ...(identifier.value.trim() ? { identifier: identifier.value.trim() } : {}),
      ...(name.value.trim() ? { name: name.value.trim() } : {}),
      ...(user.value ? { user: user.value.uuid } : {}),
    })
    creating.value = false
    await router.push({ name: 'admin.retention.request', params: { request: out.uuid } })
  } catch (e) {
    errors.value = fieldErrors(e)
    toast.add({ severity: 'error', summary: errorText(e, t('ret.pd_create_failed')), life: 8000 })
  } finally {
    busy.value = false
  }
}
const open = (uuid: string) => router.push({ name: 'admin.retention.request', params: { request: uuid } })
</script>

<template>
  <div class="flex flex-col gap-4" data-testid="ret-personal">
    <div class="flex flex-wrap items-start gap-3">
      <TabIntro class="flex-1 min-w-0" :title="t('ret.tab.personal')" :text="t('ret.pd_intro')" />
      <Button icon="pi pi-plus" :label="t('ret.pd_new')" size="small" data-testid="ret-pd-new" @click="openNew" />
    </div>
    <EmptyState v-if="!loading && !rows.length" icon="pi pi-id-card" :title="t('ret.pd_empty')" :description="t('ret.pd_empty_text')" testid="ret-pd-empty" />
    <DataTable v-else :value="rows" data-key="uuid" :loading="loading" size="small" class="records-table" row-hover @row-click="(e) => open((e.data as PersonalRequest).uuid)">
      <Column :header="t('ret.received')">
        <template #body="{ data }">{{ data.created_at ? formatDatetime(data.created_at, locale) : '' }}</template>
      </Column>
      <Column :header="t('ret.pd_type_label')">
        <template #body="{ data }">{{ t(`ret.pd_type.${data.type}`) }}</template>
      </Column>
      <Column :header="t('ret.state')">
        <template #body="{ data }"><Tag :severity="severityOf(data.status)" :value="t(`ret.pd_status.${data.status}`)" /></template>
      </Column>
      <Column :header="t('ret.by')">
        <template #body="{ data }"
          ><span dir="auto">{{ data.created_by }}</span></template
        >
      </Column>
    </DataTable>

    <Dialog v-model:visible="creating" modal :header="t('ret.pd_new')" :style="{ width: 'min(36rem, 95vw)' }">
      <div class="flex flex-col gap-4">
        <ConfigField :label="t('ret.pd_type_label')" for="pd-type" width="full" :hint="t(`ret.pd_type_hint.${type}`)">
          <Select v-model="type" input-id="pd-type" :options="types" option-label="label" option-value="value" size="small" data-testid="pd-type" />
        </ConfigField>
        <p class="m-0 text-sm text-muted-color">{{ t('ret.pd_subject_hint') }}</p>
        <ConfigField :label="t('ret.pd_email')" for="pd-email" width="full" :error="errors['subject.email'] ?? errors.subject">
          <InputText id="pd-email" v-model="email" type="email" class="ltr-value" size="small" data-testid="pd-email" />
        </ConfigField>
        <ConfigField :label="t('ret.pd_identifier')" for="pd-identifier" width="full" :hint="t('ret.pd_identifier_hint')">
          <InputText id="pd-identifier" v-model="identifier" size="small" dir="auto" />
        </ConfigField>
        <ConfigField :label="t('ret.pd_name')" for="pd-name" width="full">
          <InputText id="pd-name" v-model="name" size="small" dir="auto" />
        </ConfigField>
        <ConfigField :label="t('ret.pd_user')" for="pd-user" width="full">
          <UserPicker v-model="user" />
        </ConfigField>
        <div class="flex justify-end gap-2">
          <Button :label="t('ret.cancel')" text size="small" @click="creating = false" />
          <Button :label="t('ret.pd_start')" size="small" :loading="busy" data-testid="pd-create" @click="create" />
        </div>
      </div>
    </Dialog>
  </div>
</template>
