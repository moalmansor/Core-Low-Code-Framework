<script setup lang="ts">
import AutoComplete from 'primevue/autocomplete'
import Button from 'primevue/button'
import Column from 'primevue/column'
import DataTable from 'primevue/datatable'
import Dialog from 'primevue/dialog'
import Select from 'primevue/select'
import SelectButton from 'primevue/selectbutton'
import Tag from 'primevue/tag'
import Textarea from 'primevue/textarea'
import { useToast } from 'primevue/usetoast'
import { computed, onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { get } from '@/api/http'
import type { AstNode } from '@/expressions/ast'
import ExpressionInput from '@/builder/conditions/ExpressionInput.vue'
import ConfigField from '@/components/config/ConfigField.vue'
import EmptyState from '@/components/config/EmptyState.vue'
import TabIntro from '@/components/config/TabIntro.vue'
import EmptyValue from '@/components/EmptyValue.vue'
import { formatDatetime } from '@/runtime/format'
import FormScope from '../admin/notifications/FormScope.vue'
import { errorText, fieldErrors } from '../admin/building/shared'
import { holdsApi, type LegalHold } from './api'

/**
 * Legal holds (specification §4.26): one record, or every record of a form
 * matching a condition, is kept from any deletion — by people, the recycle
 * bin, retention or personal data requests — until the hold is lifted. The
 * reason is recorded when it is placed and when it is lifted.
 */
const { t, locale } = useI18n()
const toast = useToast()
const rows = ref<LegalHold[]>([])
const forms = ref<{ uuid: string; label: string }[]>([])
const loading = ref(true)
async function load(): Promise<void> {
  try {
    rows.value = await holdsApi.list()
  } catch (e) {
    toast.add({ severity: 'error', summary: errorText(e, t('ret.load_failed')), life: 6000 })
  } finally {
    loading.value = false
  }
}
onMounted(async () => {
  await load()
  forms.value = await holdsApi.forms().catch(() => [])
})

// Placing a hold
const placing = ref(false)
const form = ref<string | null>(null)
const mode = ref<'record' | 'condition'>('record')
const record = ref<{ uuid: string; label: string } | null>(null)
const condition = ref<AstNode | null>(null)
const reason = ref('')
const errors = ref<Record<string, string>>({})
const busy = ref(false)
const suggestions = ref<{ uuid: string; label: string }[]>([])
const modes = computed(() => [
  { value: 'record', label: t('ret.hold_one') },
  { value: 'condition', label: t('ret.hold_matching') },
])
function openPlace(): void {
  form.value = null
  mode.value = 'record'
  record.value = null
  condition.value = null
  reason.value = ''
  errors.value = {}
  placing.value = true
}
async function search(e: { query: string }): Promise<void> {
  if (!form.value) return
  const r = await get<{ data: { uuid: string; system?: { record_number?: string | null }; title?: string | null }[] }>(`/r/${form.value}`, { search: e.query, per_page: 10 })
  suggestions.value = r.data.map((x) => ({ uuid: x.uuid, label: x.title ?? x.system?.record_number ?? x.uuid }))
}
async function place(): Promise<void> {
  busy.value = true
  errors.value = {}
  try {
    const out = await holdsApi.place({
      form: form.value ?? '',
      record: mode.value === 'record' ? (record.value?.uuid ?? null) : null,
      condition: mode.value === 'condition' ? (condition.value as unknown as Record<string, unknown> | null) : null,
      reason: reason.value,
    })
    toast.add({ severity: 'success', summary: t('ret.hold_placed', { count: out.held }), life: 5000 })
    placing.value = false
    await load()
  } catch (e) {
    errors.value = fieldErrors(e)
    toast.add({ severity: 'error', summary: errorText(e, t('ret.hold_failed')), life: 8000 })
  } finally {
    busy.value = false
  }
}

// Lifting a hold
const lifting = ref<LegalHold | null>(null)
const liftReason = ref('')
async function lift(): Promise<void> {
  if (!lifting.value) return
  busy.value = true
  try {
    await holdsApi.lift(lifting.value.uuid, liftReason.value)
    toast.add({ severity: 'success', summary: t('ret.hold_lifted'), life: 4000 })
    lifting.value = null
    await load()
  } catch (e) {
    toast.add({ severity: 'error', summary: errorText(e, t('ret.hold_failed')), life: 8000 })
  } finally {
    busy.value = false
  }
}
</script>

<template>
  <div class="flex flex-col gap-4" data-testid="ret-holds">
    <div class="flex flex-wrap items-start gap-3">
      <TabIntro class="flex-1 min-w-0" :title="t('ret.tab.holds')" :text="t('ret.holds_intro')" />
      <Button icon="pi pi-lock" :label="t('ret.place_hold')" size="small" data-testid="ret-place-hold" @click="openPlace" />
    </div>
    <EmptyState v-if="!loading && !rows.length" icon="pi pi-lock" :title="t('ret.holds_empty')" :description="t('ret.holds_empty_text')" testid="ret-holds-empty" />
    <DataTable v-else :value="rows" data-key="uuid" :loading="loading" size="small" class="records-table">
      <Column :header="t('ret.form')">
        <template #body="{ data }"
          ><span v-if="data.form" dir="auto">{{ data.form.label }}</span
          ><EmptyValue v-else
        /></template>
      </Column>
      <Column :header="t('ret.covers')">
        <template #body="{ data }">{{ data.record ? t('ret.covers_record', { number: data.record.number ?? '' }) : t('ret.covers_condition') }}</template>
      </Column>
      <Column :header="t('ret.reason')">
        <template #body="{ data }">
          <span dir="auto">{{ data.reason }}</span>
          <small class="block text-muted-color">{{ t('ret.placed_by', { who: data.placed_by ?? '', when: data.placed_at ? formatDatetime(data.placed_at, locale) : '' }) }}</small>
        </template>
      </Column>
      <Column :header="t('ret.state')">
        <template #body="{ data }">
          <template v-if="data.lifted_at">
            <Tag severity="secondary" :value="t('ret.lifted')" />
            <small class="block text-muted-color" dir="auto">{{ data.lift_reason }}</small>
          </template>
          <Tag v-else severity="warn" :value="t('ret.in_force')" />
        </template>
      </Column>
      <Column>
        <template #body="{ data }">
          <Button v-if="!data.lifted_at" icon="pi pi-lock-open" :label="t('ret.lift')" size="small" text :data-testid="`ret-lift-${data.uuid}`" @click="((lifting = data), (liftReason = ''))" />
        </template>
      </Column>
    </DataTable>

    <Dialog v-model:visible="placing" modal :header="t('ret.place_hold')" :style="{ width: 'min(40rem, 95vw)' }">
      <div class="flex flex-col gap-4">
        <ConfigField :label="t('ret.form')" for="hold-form" width="full" :error="errors.form">
          <Select v-model="form" input-id="hold-form" :options="forms" option-label="label" option-value="uuid" size="small" filter data-testid="hold-form" />
        </ConfigField>
        <SelectButton v-model="mode" :options="modes" option-label="label" option-value="value" :allow-empty="false" size="small" />
        <ConfigField v-if="mode === 'record'" :label="t('ret.record')" for="hold-record" width="full" :error="errors.record">
          <AutoComplete v-model="record" input-id="hold-record" :suggestions="suggestions" option-label="label" force-selection :disabled="!form" @complete="search" />
        </ConfigField>
        <FormScope v-else :key="form ?? 'none'" v-slot="{ scope }" :form="form">
          <ExpressionInput v-if="scope" v-model="condition" :scope="scope" expected="boolean" :allow-empty="false" :label="t('ret.condition')" />
          <small v-if="errors.condition" class="text-danger">{{ errors.condition }}</small>
        </FormScope>
        <ConfigField :label="t('ret.reason')" for="hold-reason" width="full" :hint="t('ret.reason_hint')" :error="errors.reason">
          <Textarea id="hold-reason" v-model="reason" rows="3" auto-resize data-testid="hold-reason" />
        </ConfigField>
        <div class="flex justify-end gap-2">
          <Button :label="t('ret.cancel')" text size="small" @click="placing = false" />
          <Button :label="t('ret.place_hold')" size="small" :loading="busy" :disabled="!form || reason.trim().length < 3" data-testid="hold-confirm" @click="place" />
        </div>
      </div>
    </Dialog>

    <Dialog :visible="!!lifting" modal :header="t('ret.lift_title')" :style="{ width: 'min(32rem, 95vw)' }" @update:visible="(v: boolean) => !v && (lifting = null)">
      <div class="flex flex-col gap-4">
        <p class="m-0 text-sm">{{ t('ret.lift_text') }}</p>
        <ConfigField :label="t('ret.reason')" for="lift-reason" width="full">
          <Textarea id="lift-reason" v-model="liftReason" rows="3" auto-resize data-testid="lift-reason" />
        </ConfigField>
        <div class="flex justify-end gap-2">
          <Button :label="t('ret.cancel')" text size="small" @click="lifting = null" />
          <Button :label="t('ret.lift')" size="small" :loading="busy" :disabled="liftReason.trim().length < 3" data-testid="lift-confirm" @click="lift" />
        </div>
      </div>
    </Dialog>
  </div>
</template>
