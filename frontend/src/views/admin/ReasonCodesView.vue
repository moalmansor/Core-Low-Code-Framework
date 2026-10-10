<script setup lang="ts">
import Button from 'primevue/button'
import Column from 'primevue/column'
import DataTable from 'primevue/datatable'
import Dialog from 'primevue/dialog'
import InputNumber from 'primevue/inputnumber'
import InputText from 'primevue/inputtext'
import Select from 'primevue/select'
import Tag from 'primevue/tag'
import { useToast } from 'primevue/usetoast'
import { computed, onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { ApiError, get, send } from '@/api/http'
import ConfigField from '@/components/config/ConfigField.vue'
import EmptyState from '@/components/config/EmptyState.vue'
import SettingSwitch from '@/components/config/SettingSwitch.vue'
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
const editing = ref<(Partial<Code> & { labels: Record<string, string>; requires_note: boolean; is_active: boolean; sort_order: number }) | null>(null)
// Codes are grouped in sets; a new code joins an existing set unless "New set" is chosen, so a typo never creates a second set silently.
const NEW_SET = '\u0000new'
const sets = computed(() => [...new Set(codes.value.map((c) => c.set_key))].sort())
const setChoice = ref<string>(NEW_SET)
const newSet = ref('')
const setOptions = computed(() => [...sets.value.map((k) => ({ value: k, label: k })), { value: NEW_SET, label: t('reason_codes.new_set') }])
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

function create(set?: string): void {
  errors.value = {}
  setChoice.value = set ?? sets.value[0] ?? NEW_SET
  newSet.value = ''
  editing.value = { set_key: '', code: '', labels: {}, requires_note: false, sort_order: nextOrder(set ?? sets.value[0]), is_active: true }
}
function nextOrder(set: string | undefined): number {
  const orders = codes.value.filter((c) => c.set_key === set).map((c) => c.sort_order)
  return orders.length ? Math.max(...orders) + 10 : 0
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
    else await send('post', '/justification-reason-codes', { ...body, set_key: setChoice.value === NEW_SET ? newSet.value.trim() : setChoice.value, code: e.code })
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
  <div class="flex flex-col gap-4" data-testid="reason-codes">
    <header class="flex flex-wrap items-start gap-3">
      <div class="flex-1 min-w-0">
        <h1 class="page-title !mb-1">{{ t('admin.area.reason_codes') }}</h1>
        <p class="m-0 text-sm text-muted-color max-w-[var(--measure)]">{{ t('reason_codes.hint') }}</p>
      </div>
      <Button v-if="codes.length" icon="pi pi-plus" :label="t('reason_codes.add')" size="small" data-testid="reason-code-add" @click="create()" />
    </header>
    <EmptyState v-if="!loading && !codes.length" icon="pi pi-comment" :title="t('reason_codes.empty')" :description="t('reason_codes.empty_text')" testid="reason-codes-empty">
      <Button icon="pi pi-plus" :label="t('reason_codes.add')" size="small" data-testid="reason-code-add" @click="create()" />
    </EmptyState>
    <DataTable v-else :value="codes" :loading="loading" data-key="uuid" size="small" row-group-mode="subheader" group-rows-by="set_key" scrollable>
      <template #groupheader="{ data }">
        <div class="flex items-center gap-2">
          <span class="font-semibold ltr-value flex-1">{{ data.set_key }}</span>
          <Button icon="pi pi-plus" :label="t('reason_codes.add_to_set')" text size="small" :data-testid="`reason-code-add-${data.set_key}`" @click="create(data.set_key)" />
        </div>
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
    </DataTable>

    <Dialog :visible="!!editing" modal :header="editing?.uuid ? t('reason_codes.edit') : t('reason_codes.add')" class="w-full max-w-xl" @update:visible="(v) => !v && (editing = null)">
      <form v-if="editing" class="dlg-form" data-testid="reason-code-dialog" @submit.prevent="save">
        <section class="dlg-group">
          <h3 class="dlg-heading">{{ t('reason_codes.identity') }}</h3>
          <div class="cfg-row">
            <ConfigField v-if="editing.uuid" :label="t('reason_codes.set')" width="sm"
              ><span class="ltr-value py-2">{{ editing.set_key }}</span></ConfigField
            >
            <ConfigField v-else :label="t('reason_codes.set')" for="rc-set" width="md" :hint="t('reason_codes.set_hint')" :error="setChoice === NEW_SET ? undefined : errors.set_key" required>
              <Select
                v-model="setChoice"
                input-id="rc-set"
                :options="setOptions"
                option-label="label"
                option-value="value"
                data-testid="reason-code-set"
                @change="editing.sort_order = nextOrder(setChoice)"
              />
            </ConfigField>
            <ConfigField
              v-if="!editing.uuid && setChoice === NEW_SET"
              :label="t('reason_codes.new_set_key')"
              for="rc-new-set"
              width="sm"
              :hint="t('reason_codes.new_set_hint')"
              :error="errors.set_key"
              required
            >
              <InputText id="rc-new-set" v-model="newSet" class="ltr-value" maxlength="48" :invalid="!!errors.set_key" data-testid="reason-code-new-set" />
            </ConfigField>
          </div>
          <ConfigField v-if="editing.uuid" :label="t('reason_codes.code')" width="sm"
            ><span class="ltr-value py-2">{{ editing.code }}</span></ConfigField
          >
          <ConfigField v-else :label="t('reason_codes.code')" for="rc-code" width="sm" :hint="t('reason_codes.code_hint')" :error="errors.code" required>
            <InputText id="rc-code" v-model="editing.code" class="ltr-value" maxlength="48" :invalid="!!errors.code" data-testid="reason-code-code" />
          </ConfigField>
          <div class="w-field-md flex flex-col gap-3">
            <LocaleFields v-model="editing.labels" :label="t('reason_codes.label')" field="label" :errors="errors" id-prefix="rc-label" />
          </div>
        </section>
        <section class="dlg-group">
          <h3 class="dlg-heading">{{ t('reason_codes.behaviour') }}</h3>
          <ConfigField :label="t('reason_codes.order')" for="rc-order" width="xs" :hint="t('reason_codes.order_hint')">
            <InputNumber v-model="editing.sort_order" input-id="rc-order" :min="0" :max="100000" :use-grouping="false" />
          </ConfigField>
          <SettingSwitch id="rc-note" v-model="editing.requires_note" :label="t('reason_codes.requires_note')" :description="t('reason_codes.requires_note_desc')" />
          <SettingSwitch id="rc-active" v-model="editing.is_active" :label="t('reason_codes.active')" :description="t('reason_codes.active_desc')" />
        </section>
        <div class="flex justify-end gap-2">
          <Button type="button" :label="t('workflow.cancel')" text @click="editing = null" />
          <Button type="submit" :label="t('workflow.save')" icon="pi pi-check" :loading="saving" data-testid="reason-code-save" />
        </div>
      </form>
    </Dialog>
  </div>
</template>
