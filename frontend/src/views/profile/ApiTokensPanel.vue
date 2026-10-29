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
import ConfigField from '@/components/config/ConfigField.vue'
import EmptyState from '@/components/config/EmptyState.vue'
import EmptyValue from '@/components/EmptyValue.vue'
import { ApiError } from '@/api/http'
import { errorText, formatDateTime } from '../admin/building/shared'
import SecretDialog from '../integrations/SecretDialog.vue'
import { tokensApi, type ApiToken, type Grantable } from '../integrations/api'

/**
 * Personal API tokens (specification §4.22): each reaches only the forms
 * chosen for it, read-only or read-and-write, never beyond what its holder
 * may do, for a limited time and optionally only from listed addresses. The
 * token is shown once.
 */
const { t, locale } = useI18n()
const toast = useToast()
const confirm = useConfirm()
const rows = ref<ApiToken[]>([])
const grantable = ref<Grantable[]>([])
const maxDays = ref(365)
const enabled = ref(true)
const loading = ref(true)
async function load(): Promise<void> {
  loading.value = true
  try {
    const r = await tokensApi.mine()
    rows.value = r.rows
    grantable.value = r.grantable
    maxDays.value = r.maxDays
    enabled.value = r.enabled
  } catch (e) {
    toast.add({ severity: 'error', summary: errorText(e, t('intg.load_failed')), life: 6000 })
  } finally {
    loading.value = false
  }
}
onMounted(load)
const formName = (uuid: string) => grantable.value.find((g) => g.form === uuid)?.name ?? t('intg.form_gone')

// A new token.
const making = ref(false)
const open = ref(false)
const secret = ref<string | null>(null)
const errors = ref<Record<string, string>>({})
const draft = ref({ name: '', access: {} as Record<string, 'none' | 'read' | 'write'>, days: 90 as number | null, ips: '' })
function start(): void {
  draft.value = { name: '', access: {}, days: Math.min(90, maxDays.value), ips: '' }
  errors.value = {}
  open.value = true
}
const accessOptions = (g: Grantable) =>
  [{ value: 'none', label: t('intg.access.none') }, { value: 'read', label: t('intg.access.read') }, ...(g.write ? [{ value: 'write', label: t('intg.access.write') }] : [])] as {
    value: string
    label: string
  }[]
const abilities = computed(() =>
  Object.entries(draft.value.access)
    .filter(([, a]) => a !== 'none')
    .map(([form, a]) => `form:${form}:${a}`),
)
async function make(): Promise<void> {
  making.value = true
  errors.value = {}
  try {
    const ips = draft.value.ips
      .split(/[\s,]+/)
      .map((s) => s.trim())
      .filter(Boolean)
    const r = await tokensApi.create({ name: draft.value.name, abilities: abilities.value, expires_in_days: draft.value.days, ip_allowlist: ips, rate_limit_per_minute: null })
    open.value = false
    secret.value = r.secret
    await load()
  } catch (e) {
    errors.value = e instanceof ApiError ? e.fieldErrors : {}
    if (!Object.keys(errors.value).length) toast.add({ severity: 'error', summary: errorText(e, t('intg.token_failed')), life: 6000 })
  } finally {
    making.value = false
  }
}
const otherErrors = computed(() => Object.entries(errors.value).filter(([k]) => !['name', 'expires_in_days'].includes(k) && !k.startsWith('ip_allowlist')))

function revoke(row: ApiToken): void {
  confirm.require({
    header: t('intg.token_revoke_title'),
    message: t('intg.token_revoke_text', { name: row.name }),
    acceptLabel: t('intg.token_revoke'),
    rejectLabel: t('common.cancel'),
    acceptProps: { severity: 'danger' },
    accept: async () => {
      try {
        await tokensApi.revokeMine(row.uuid)
        await load()
      } catch (e) {
        toast.add({ severity: 'error', summary: errorText(e, t('intg.token_revoke_failed')), life: 6000 })
      }
    },
  })
}
const state = (r: ApiToken) => (r.revoked_at ? 'revoked' : r.active ? 'active' : 'expired')
const describe = (r: ApiToken) =>
  r.abilities.map((a) => {
    const [, form, level] = a.split(':')
    return t(`intg.ability.${level}`, { form: formName(form!) })
  })
</script>

<template>
  <div class="flex flex-col gap-4 max-w-[56rem]" data-testid="api-tokens-panel">
    <div class="flex flex-wrap items-start gap-3">
      <p class="m-0 flex-1 min-w-0 text-sm text-muted-color">{{ t('intg.my_tokens_intro') }}</p>
      <Button icon="pi pi-plus" :label="t('intg.token_new')" size="small" :disabled="!enabled || !grantable.length" data-testid="token-new" @click="start" />
    </div>
    <p v-if="!enabled" class="m-0 text-sm text-danger">{{ t('intg.api_off') }}</p>
    <EmptyState v-if="!loading && !rows.length" icon="pi pi-key" :title="t('intg.my_tokens_empty_title')" :description="t('intg.my_tokens_empty_text')" testid="my-tokens-empty" />
    <DataTable v-else :value="rows" data-key="uuid" :loading="loading" size="small" class="records-table">
      <Column :header="t('intg.token_name')">
        <template #body="{ data }"
          ><span class="font-medium" dir="auto">{{ data.name }}</span></template
        >
      </Column>
      <Column :header="t('intg.token_access')">
        <template #body="{ data }">
          <ul class="m-0 ps-4 text-sm">
            <li v-for="(line, i) in describe(data)" :key="i" dir="auto">{{ line }}</li>
          </ul>
        </template>
      </Column>
      <Column :header="t('intg.token_last_used')">
        <template #body="{ data }">
          <span v-if="data.last_used_at">{{ formatDateTime(data.last_used_at, locale) }}</span>
          <EmptyValue v-else :text="t('intg.never_used')" />
        </template>
      </Column>
      <Column :header="t('intg.token_expires')">
        <template #body="{ data }">{{ formatDateTime(data.expires_at, locale) }}</template>
      </Column>
      <Column :header="t('intg.state')" style="width: 8rem">
        <template #body="{ data }">
          <Tag :severity="state(data) === 'active' ? 'success' : 'secondary'" :value="t(`intg.token_state.${state(data)}`)" />
        </template>
      </Column>
      <Column style="width: 4rem">
        <template #body="{ data }">
          <Button
            v-if="state(data) === 'active'"
            icon="pi pi-ban"
            text
            rounded
            severity="danger"
            size="small"
            :aria-label="t('intg.token_revoke')"
            :data-testid="`token-revoke-${data.uuid}`"
            @click="revoke(data)"
          />
        </template>
      </Column>
    </DataTable>

    <Dialog v-model:visible="open" modal :header="t('intg.token_new')" :style="{ width: '40rem', maxWidth: 'calc(100vw - 2rem)' }" data-testid="token-dialog">
      <div class="flex flex-col gap-4">
        <ConfigField :label="t('intg.token_name')" for="token-name" width="md" :hint="t('intg.token_name_hint')" :error="errors.name" required>
          <InputText id="token-name" v-model="draft.name" size="small" maxlength="255" data-testid="token-name" />
        </ConfigField>
        <section class="flex flex-col gap-2">
          <h3 class="m-0 text-sm font-semibold">{{ t('intg.token_access') }}</h3>
          <p class="m-0 text-xs text-muted-color">{{ t('intg.token_access_hint') }}</p>
          <div v-for="g in grantable" :key="g.form" class="flex flex-wrap items-center gap-3">
            <label :for="`token-form-${g.key}`" class="w-field-md text-sm" dir="auto">{{ g.name }}</label>
            <Select
              :model-value="draft.access[g.form] ?? 'none'"
              :input-id="`token-form-${g.key}`"
              :options="accessOptions(g)"
              option-label="label"
              option-value="value"
              size="small"
              class="w-field-sm"
              :data-testid="`token-form-${g.key}`"
              @update:model-value="(v: 'none' | 'read' | 'write') => (draft.access[g.form] = v)"
            />
          </div>
          <p v-if="errors.abilities" class="m-0 text-sm text-danger">{{ errors.abilities }}</p>
        </section>
        <div class="cfg-row">
          <ConfigField :label="t('intg.token_days')" for="token-days" width="xs" :hint="t('intg.token_days_hint', { max: maxDays })" :error="errors.expires_in_days">
            <InputNumber v-model="draft.days" input-id="token-days" :min="1" :max="maxDays" size="small" fluid />
          </ConfigField>
          <ConfigField :label="t('intg.token_ips')" for="token-ips" width="md" :hint="t('intg.token_ips_hint')" :error="Object.entries(errors).find(([k]) => k.startsWith('ip_allowlist'))?.[1]">
            <Textarea id="token-ips" v-model="draft.ips" rows="2" dir="ltr" class="ltr-value w-full" auto-resize />
          </ConfigField>
        </div>
        <ul v-if="otherErrors.length" class="m-0 ps-5 text-sm text-danger">
          <li v-for="[k, m] in otherErrors" :key="k">{{ m }}</li>
        </ul>
      </div>
      <template #footer>
        <Button :label="t('common.cancel')" text @click="open = false" />
        <Button :label="t('intg.token_make')" icon="pi pi-check" :loading="making" :disabled="!draft.name || !abilities.length" data-testid="token-make" @click="make" />
      </template>
    </Dialog>
    <SecretDialog :secret="secret" :title="t('intg.token_secret_title')" :text="t('intg.token_secret_text')" @close="secret = null" />
  </div>
</template>
