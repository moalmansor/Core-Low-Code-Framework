<script setup lang="ts">
import Button from 'primevue/button'
import Column from 'primevue/column'
import DataTable from 'primevue/datatable'
import DatePicker from 'primevue/datepicker'
import Dialog from 'primevue/dialog'
import MultiSelect from 'primevue/multiselect'
import SelectButton from 'primevue/selectbutton'
import Tag from 'primevue/tag'
import Textarea from 'primevue/textarea'
import { useConfirm } from 'primevue/useconfirm'
import { useToast } from 'primevue/usetoast'
import { computed, onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { get, send } from '@/api/http'
import { searchForms } from '@/builder/targets'
import SubjectPicker from '@/components/SubjectPicker.vue'
import { useSession } from '@/stores/session'
import { errorText, fieldErrors } from '@/views/admin/building/shared'

/**
 * Delegation and out-of-office cover (specification §4.25). On its own a user
 * delegates their own work for a period with a reason; with Manage Delegation
 * an administrator sees every delegation and sets them for others. A
 * delegation can be limited to forms; it is revoked, never deleted.
 */
interface Delegation {
  uuid: string
  type: 'delegation' | 'out_of_office'
  delegator: { uuid: string; name: string | null }
  delegate: { uuid: string; name: string | null }
  starts_at: string
  ends_at: string
  reason: string
  forms: { uuid: string; key: string }[] | null
  status: 'scheduled' | 'active' | 'expired' | 'revoked'
}
const props = defineProps<{ admin: boolean }>()
const { t } = useI18n()
const toast = useToast()
const confirm = useConfirm()
const session = useSession()
const rows = ref<Delegation[]>([])
const loading = ref(true)
const formOptions = ref<{ uuid: string; label: string }[]>([])
const canPickForms = computed(() => session.can('system.manage_forms'))

async function load(): Promise<void> {
  loading.value = true
  try {
    rows.value = (await get<{ data: Delegation[] }>('/delegations', { scope: props.admin ? 'all' : 'mine' })).data
  } catch (e) {
    toast.add({ severity: 'error', summary: errorText(e, t('building.load_failed')), life: 6000 })
  } finally {
    loading.value = false
  }
}
onMounted(async () => {
  await load()
  if (canPickForms.value) formOptions.value = (await searchForms('').catch(() => [])).map((f) => ({ uuid: f.uuid, label: f.name ?? f.key }))
})

const editing = ref<{
  delegator: { type: string; uuid: string | null } | null
  delegate: { type: string; uuid: string | null } | null
  type: 'delegation' | 'out_of_office'
  range: Date[] | null
  reason: string
  forms: string[]
} | null>(null)
const errors = ref<Record<string, string>>({})
const saving = ref(false)
const types = computed(() => (['delegation', 'out_of_office'] as const).filter((v) => props.admin || v === 'delegation').map((v) => ({ value: v, label: t(`delegation.type.${v}`) })))

function create(): void {
  errors.value = {}
  const start = new Date()
  const end = new Date(start.getTime() + 7 * 86400000)
  editing.value = { delegator: null, delegate: null, type: 'delegation', range: [start, end], reason: '', forms: [] }
}
async function save(): Promise<void> {
  const e = editing.value
  if (!e) return
  saving.value = true
  errors.value = {}
  try {
    await send('post', '/delegations', {
      delegator: props.admin && e.delegator?.uuid ? e.delegator.uuid : undefined,
      delegate: e.delegate?.uuid ?? null,
      type: e.type,
      starts_at: e.range?.[0]?.toISOString() ?? null,
      ends_at: e.range?.[1]?.toISOString() ?? null,
      reason: e.reason,
      forms: e.forms.length ? e.forms : undefined,
    })
    editing.value = null
    toast.add({ severity: 'success', summary: t('workflow.saved'), life: 3000 })
    await load()
  } catch (err) {
    errors.value = fieldErrors(err)
    toast.add({ severity: 'error', summary: errorText(err, t('workflow.save_failed')), life: 6000 })
  } finally {
    saving.value = false
  }
}
function revoke(d: Delegation): void {
  confirm.require({
    message: t('delegation.revoke_confirm'),
    header: t('delegation.revoke'),
    acceptProps: { label: t('delegation.revoke'), severity: 'danger' },
    rejectProps: { label: t('workflow.cancel'), text: true },
    accept: async () => {
      try {
        await send('delete', `/delegations/${d.uuid}`)
        await load()
      } catch (e) {
        toast.add({ severity: 'error', summary: errorText(e, t('workflow.save_failed')), life: 6000 })
      }
    },
  })
}
const when = (iso: string) => new Date(iso).toLocaleString(session.locale)
const severity = (s: Delegation['status']) => ({ active: 'success', scheduled: 'info', expired: 'secondary', revoked: 'secondary' })[s]
const canRevoke = (d: Delegation) => (d.status === 'active' || d.status === 'scheduled') && (props.admin || d.delegator.uuid === session.me?.uuid)
</script>

<template>
  <div class="flex flex-col gap-3" data-testid="delegations">
    <div class="flex items-center gap-2">
      <p class="text-sm text-muted-color flex-1">{{ admin ? t('delegation.admin_hint') : t('delegation.hint') }}</p>
      <Button icon="pi pi-plus" :label="t('delegation.add')" size="small" data-testid="delegation-add" @click="create" />
    </div>
    <DataTable :value="rows" :loading="loading" data-key="uuid" size="small" scrollable>
      <Column :header="t('delegation.from')"
        ><template #body="{ data }">{{ data.delegator.name }}</template></Column
      >
      <Column :header="t('delegation.to')"
        ><template #body="{ data }">{{ data.delegate.name }}</template></Column
      >
      <Column :header="t('delegation.type_label')"
        ><template #body="{ data }">{{ t(`delegation.type.${data.type}`) }}</template></Column
      >
      <Column :header="t('delegation.period')"
        ><template #body="{ data }">{{ when(data.starts_at) }} – {{ when(data.ends_at) }}</template></Column
      >
      <Column :header="t('delegation.forms')">
        <template #body="{ data }">{{ data.forms === null ? t('delegation.all_forms') : data.forms.map((f: { key: string }) => f.key).join(', ') }}</template>
      </Column>
      <Column field="reason" :header="t('delegation.reason')" />
      <Column :header="t('reason_codes.state')"
        ><template #body="{ data }"><Tag :severity="severity(data.status)" :value="t(`delegation.status.${data.status}`)" /></template
      ></Column>
      <Column>
        <template #body="{ data }">
          <Button v-if="canRevoke(data)" icon="pi pi-ban" text severity="danger" size="small" :aria-label="t('delegation.revoke')" @click="revoke(data)" />
        </template>
      </Column>
      <template #empty>{{ t('delegation.empty') }}</template>
    </DataTable>

    <Dialog :visible="!!editing" modal :header="t('delegation.add')" class="w-full max-w-xl" @update:visible="(v) => !v && (editing = null)">
      <form v-if="editing" class="flex flex-col gap-3" @submit.prevent="save">
        <SelectButton v-if="types.length > 1" v-model="editing.type" :options="types" option-label="label" option-value="value" :allow-empty="false" />
        <div v-if="admin" class="field">
          <span class="text-sm font-medium">{{ t('delegation.from') }}</span>
          <SubjectPicker v-model="editing.delegator" :types="['user']" />
          <small class="text-muted-color">{{ t('delegation.from_hint') }}</small>
        </div>
        <div class="field">
          <span class="text-sm font-medium">{{ t('delegation.to') }}</span>
          <SubjectPicker v-model="editing.delegate" :types="['user']" :invalid="!!errors.delegate" />
          <span v-if="errors.delegate" class="field-error">{{ errors.delegate }}</span>
        </div>
        <div class="field">
          <label for="dl-range">{{ t('delegation.period') }}</label>
          <DatePicker v-model="editing.range" input-id="dl-range" selection-mode="range" show-time hour-format="24" :manual-input="false" :invalid="!!errors.ends_at || !!errors.starts_at" />
          <span v-if="errors.ends_at || errors.starts_at" class="field-error">{{ errors.ends_at ?? errors.starts_at }}</span>
        </div>
        <div v-if="canPickForms" class="field">
          <label for="dl-forms">{{ t('delegation.forms') }}</label>
          <MultiSelect v-model="editing.forms" input-id="dl-forms" :options="formOptions" option-label="label" option-value="uuid" filter display="chip" :placeholder="t('delegation.all_forms')" />
        </div>
        <div class="field">
          <label for="dl-reason">{{ t('delegation.reason') }}</label>
          <Textarea id="dl-reason" v-model="editing.reason" rows="3" maxlength="2000" :invalid="!!errors.reason" />
          <span v-if="errors.reason" class="field-error">{{ errors.reason }}</span>
        </div>
        <div class="flex justify-end gap-2">
          <Button type="button" :label="t('workflow.cancel')" text @click="editing = null" />
          <Button type="submit" :label="t('workflow.save')" icon="pi pi-check" :loading="saving" data-testid="delegation-save" />
        </div>
      </form>
    </Dialog>
  </div>
</template>
