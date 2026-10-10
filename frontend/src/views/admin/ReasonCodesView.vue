<script setup lang="ts">
import Button from 'primevue/button'
import Column from 'primevue/column'
import DataTable from 'primevue/datatable'
import Dialog from 'primevue/dialog'
import InputNumber from 'primevue/inputnumber'
import InputText from 'primevue/inputtext'
import Tag from 'primevue/tag'
import ToggleSwitch from 'primevue/toggleswitch'
import { useToast } from 'primevue/usetoast'
import { onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { ApiError, get, send } from '@/api/http'
import LocaleFields from './building/LocaleFields.vue'
import { errorText, fieldErrors } from './building/shared'

/**
 * Justification reason codes (specification §4.24): sets of codes a
 * justification rule can require, each with a label per language, whether
 * it needs a written note, its order and whether it is offered. Codes are
 * deactivated, never deleted, so saved justifications keep their meaning.
 */
interface Code {
  uuid: string
  set_key: string
  code: string
  label: string
  labels: Record<string, string>
  requires_note: boolean
  sort_order: number
  is_active: boolean
  updated_at: string | null
}
const { t } = useI18n()
const toast = useToast()
const codes = ref<Code[]>([])
const loading = ref(true)
const editing = ref<(Partial<Code> & { labels: Record<string, string> }) | null>(null)
const errors = ref<Record<string, string>>({})
const saving = ref(false)

async function load(): Promise<void> {
  loading.value = true
  try {
    codes.value = (await get<{ data: Code[] }>('/justification-reason-codes')).data
  } catch (e) {
    toast.add({ severity: 'error', summary: errorText(e, t('building.load_failed')), life: 6000 })
  } finally {
    loading.value = false
  }
}
onMounted(load)

function create(): void {
  errors.value = {}
  editing.value = { set_key: '', code: '', labels: {}, requires_note: false, sort_order: 0, is_active: true }
}
function edit(c: Code): void {
  errors.value = {}
  editing.value = { ...c, labels: { ...c.labels } }
}
async function save(): Promise<void> {
  const e = editing.value
  if (!e) return
  saving.value = true
  errors.value = {}
  const body = { label: e.labels, requires_note: e.requires_note, sort_order: e.sort_order, is_active: e.is_active }
  try {
    if (e.uuid) await send('patch', `/justification-reason-codes/${e.uuid}`, { ...body, base_updated_at: e.updated_at })
    else await send('post', '/justification-reason-codes', { ...body, set_key: e.set_key, code: e.code })
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
  <div class="flex flex-col gap-3" data-testid="reason-codes">
    <div class="flex items-center gap-2">
      <h1 class="page-title m-0 flex-1">{{ t('admin.area.reason_codes') }}</h1>
      <Button icon="pi pi-plus" :label="t('reason_codes.add')" size="small" data-testid="reason-code-add" @click="create" />
    </div>
    <p class="text-sm text-muted-color">{{ t('reason_codes.hint') }}</p>
    <DataTable :value="codes" :loading="loading" data-key="uuid" size="small" row-group-mode="subheader" group-rows-by="set_key" scrollable>
      <template #groupheader="{ data }">
        <span class="font-semibold ltr-value">{{ data.set_key }}</span>
      </template>
      <Column field="code" :header="t('reason_codes.code')"
        ><template #body="{ data }"
          ><span class="ltr-value">{{ data.code }}</span></template
        ></Column
      >
      <Column field="label" :header="t('reason_codes.label')" />
      <Column :header="t('reason_codes.requires_note')"
        ><template #body="{ data }"><i v-if="data.requires_note" class="pi pi-check" :aria-label="t('common.yes')" /></template
      ></Column>
      <Column field="sort_order" :header="t('reason_codes.order')" />
      <Column :header="t('reason_codes.state')">
        <template #body="{ data }"><Tag :severity="data.is_active ? 'success' : 'secondary'" :value="data.is_active ? t('reason_codes.active') : t('reason_codes.inactive')" /></template>
      </Column>
      <Column>
        <template #body="{ data }"><Button icon="pi pi-pencil" text size="small" :aria-label="t('common.edit')" @click="edit(data)" /></template>
      </Column>
      <template #empty>{{ t('reason_codes.empty') }}</template>
    </DataTable>

    <Dialog :visible="!!editing" modal :header="editing?.uuid ? t('reason_codes.edit') : t('reason_codes.add')" class="w-full max-w-lg" @update:visible="(v) => !v && (editing = null)">
      <form v-if="editing" class="flex flex-col gap-3" @submit.prevent="save">
        <div class="grid gap-3 grid-cols-2">
          <div class="field">
            <label for="rc-set">{{ t('reason_codes.set') }}</label>
            <InputText id="rc-set" v-model="editing.set_key" :disabled="!!editing.uuid" class="ltr-value" maxlength="48" :invalid="!!errors.set_key" />
            <span v-if="errors.set_key" class="field-error">{{ errors.set_key }}</span>
          </div>
          <div class="field">
            <label for="rc-code">{{ t('reason_codes.code') }}</label>
            <InputText id="rc-code" v-model="editing.code" :disabled="!!editing.uuid" class="ltr-value" maxlength="48" :invalid="!!errors.code" />
            <span v-if="errors.code" class="field-error">{{ errors.code }}</span>
          </div>
        </div>
        <LocaleFields v-model="editing.labels" :label="t('reason_codes.label')" field="label" :errors="errors" id-prefix="rc-label" />
        <div class="flex flex-wrap items-end gap-4">
          <div class="field w-32">
            <label for="rc-order">{{ t('reason_codes.order') }}</label>
            <InputNumber v-model="editing.sort_order" input-id="rc-order" :min="0" :max="100000" />
          </div>
          <label class="flex items-center gap-2 text-sm pb-2"><ToggleSwitch v-model="editing.requires_note" />{{ t('reason_codes.requires_note') }}</label>
          <label class="flex items-center gap-2 text-sm pb-2"><ToggleSwitch v-model="editing.is_active" />{{ t('reason_codes.active') }}</label>
        </div>
        <div class="flex justify-end gap-2">
          <Button type="button" :label="t('workflow.cancel')" text @click="editing = null" />
          <Button type="submit" :label="t('workflow.save')" icon="pi pi-check" :loading="saving" data-testid="reason-code-save" />
        </div>
      </form>
    </Dialog>
  </div>
</template>
