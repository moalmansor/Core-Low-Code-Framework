<script setup lang="ts">
import Button from 'primevue/button'
import Column from 'primevue/column'
import DataTable from 'primevue/datatable'
import Dialog from 'primevue/dialog'
import InputNumber from 'primevue/inputnumber'
import InputText from 'primevue/inputtext'
import Message from 'primevue/message'
import Select from 'primevue/select'
import Textarea from 'primevue/textarea'
import { useToast } from 'primevue/usetoast'
import { computed, onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { get, send } from '@/api/http'
import { useSession } from '@/stores/session'
import { PATTERN_TOKENS, previewNext, validPattern, type NumberCalendar, type ResetPeriod } from '../numberPattern'
import { errorText, fetchFormOptions, FORM_OPTION_PERMISSIONS, fieldErrors, type FormOption } from '../shared'

interface Sequence {
  uuid: string
  key: string
  scope: 'form' | 'shared'
  pattern: string
  prefix: string | null
  padding: number
  step: number
  reset_period: ResetPeriod
  calendar: NumberCalendar
  period_key: string
  current_value: number
  next_preview: string
  form: string | null
}

const { t } = useI18n()
const toast = useToast()
const session = useSession()
const rows = ref<Sequence[]>([])
const loading = ref(false)
const forms = ref<FormOption[]>([])
const canForms = FORM_OPTION_PERMISSIONS.some((p) => session.can(p))
const formKey = (uuid: string | null) => (uuid ? (forms.value.find((f) => f.uuid === uuid)?.key ?? uuid) : '—')

async function load(): Promise<void> {
  loading.value = true
  try {
    rows.value = (await get<{ data: Sequence[] }>('/number-sequences')).data
  } catch (e) {
    toast.add({ severity: 'error', summary: errorText(e, t('building.load_failed')), life: 6000 })
  } finally {
    loading.value = false
  }
}
onMounted(async () => {
  await load()
  if (canForms) forms.value = await fetchFormOptions()
})

const resetOptions = computed(() => (['never', 'daily', 'monthly', 'yearly'] as const).map((v) => ({ value: v, label: t(`building.ref.reset.${v}`) })))
const calendarOptions = computed(() => (['gregorian', 'hijri'] as const).map((v) => ({ value: v, label: t(`settings.option.system.${v}`) })))
const scopeOptions = computed(() => (['shared', 'form'] as const).map((v) => ({ value: v, label: t(`building.ref.scope.${v}`) })))
const formOptions = computed(() => forms.value.map((f) => ({ value: f.uuid, label: `${f.name} (${f.key})` })))

// Create / edit with a pattern builder and live preview
interface Editing {
  uuid?: string
  key: string
  scope: 'form' | 'shared'
  form: string | null
  pattern: string
  prefix: string
  padding: number
  step: number
  reset_period: ResetPeriod
  calendar: NumberCalendar
  period_key: string
  current_value: number
}
const editing = ref<Editing | null>(null)
const errors = ref<Record<string, string>>({})
const patternInput = ref<{ $el: HTMLInputElement } | null>(null)
function create(): void {
  errors.value = {}
  editing.value = {
    key: '',
    scope: 'shared',
    form: null,
    pattern: '{prefix}-{yyyy}-{seq}',
    prefix: '',
    padding: 5,
    step: 1,
    reset_period: 'yearly',
    calendar: 'gregorian',
    period_key: '',
    current_value: 0,
  }
}
function edit(s: Sequence): void {
  errors.value = {}
  editing.value = {
    uuid: s.uuid,
    key: s.key,
    scope: s.scope,
    form: s.form,
    pattern: s.pattern,
    prefix: s.prefix ?? '',
    padding: s.padding,
    step: s.step,
    reset_period: s.reset_period,
    calendar: s.calendar,
    period_key: s.period_key,
    current_value: s.current_value,
  }
}
function insertToken(token: string): void {
  const e = editing.value!
  const el = patternInput.value?.$el
  const at = el && typeof el.selectionStart === 'number' ? el.selectionStart : e.pattern.length
  e.pattern = e.pattern.slice(0, at) + token + e.pattern.slice(el?.selectionEnd ?? at)
}
const patternValid = computed(() => !!editing.value && validPattern(editing.value.pattern))
const livePreview = computed(() => (editing.value && patternValid.value ? previewNext({ ...editing.value, prefix: editing.value.prefix }) : ''))
async function save(): Promise<void> {
  const e = editing.value!
  errors.value = {}
  const body = {
    key: e.key,
    scope: e.scope,
    form: e.scope === 'form' ? e.form : null,
    pattern: e.pattern,
    prefix: e.prefix || null,
    padding: e.padding,
    step: e.step,
    reset_period: e.reset_period,
    calendar: e.calendar,
  }
  try {
    if (e.uuid) await send('patch', `/number-sequences/${e.uuid}`, body)
    else await send('post', '/number-sequences', body)
    editing.value = null
    toast.add({ severity: 'success', summary: t('common.saved'), life: 3000 })
    await load()
  } catch (err) {
    errors.value = fieldErrors(err)
    toast.add({ severity: 'error', summary: errorText(err, t('building.save_failed')), life: 6000 })
  }
}

// Audited manual adjustment
const adjusting = ref<{ seq: Sequence; value: number; reason: string } | null>(null)
const adjustErrors = ref<Record<string, string>>({})
function openAdjust(s: Sequence): void {
  adjustErrors.value = {}
  adjusting.value = { seq: s, value: s.current_value, reason: '' }
}
const adjustPreview = computed(() => {
  const a = adjusting.value
  return a ? previewNext({ ...a.seq, current_value: a.value }) : ''
})
async function submitAdjust(): Promise<void> {
  const a = adjusting.value!
  adjustErrors.value = {}
  try {
    await send('post', `/number-sequences/${a.seq.uuid}/adjust`, { current_value: a.value, reason: a.reason.trim() })
    adjusting.value = null
    toast.add({ severity: 'success', summary: t('building.ref.adjusted'), life: 4000 })
    await load()
  } catch (e) {
    adjustErrors.value = fieldErrors(e)
    toast.add({ severity: 'error', summary: errorText(e, t('building.save_failed')), life: 6000 })
  }
}
</script>

<template>
  <div class="flex flex-wrap items-center gap-2 mb-3">
    <p class="text-sm text-muted-color flex-1">{{ t('building.ref.sequences_hint') }}</p>
    <Button icon="pi pi-plus" :label="t('building.ref.new_sequence')" data-testid="seq-new" @click="create" />
  </div>
  <DataTable :value="rows" :loading="loading" data-key="uuid" size="small" striped-rows data-testid="seq-table">
    <template #empty>{{ t('building.ref.no_sequences') }}</template>
    <Column :header="t('building.key')">
      <template #body="{ data }"
        ><span class="ltr-value font-medium">{{ data.key }}</span></template
      >
    </Column>
    <Column :header="t('building.ref.scope_label')">
      <template #body="{ data }"
        >{{ t(`building.ref.scope.${data.scope}`) }} <span v-if="data.form" class="text-xs text-muted-color ltr-value">{{ formKey(data.form) }}</span></template
      >
    </Column>
    <Column :header="t('building.ref.pattern')">
      <template #body="{ data }"
        ><code class="ltr-value text-xs">{{ data.pattern }}</code></template
      >
    </Column>
    <Column :header="t('building.ref.next_number')">
      <template #body="{ data }"
        ><span class="ltr-value font-medium">{{ data.next_preview }}</span></template
      >
    </Column>
    <Column :header="t('building.ref.current_value')" field="current_value" />
    <Column :header="t('building.ref.reset_label')">
      <template #body="{ data }">{{ t(`building.ref.reset.${data.reset_period}`) }}</template>
    </Column>
    <Column class="w-28">
      <template #body="{ data }">
        <Button icon="pi pi-pencil" text rounded :aria-label="t('common.edit')" @click="edit(data)" />
        <Button icon="pi pi-sliders-h" text rounded severity="warn" :aria-label="t('building.ref.adjust')" :data-testid="`seq-adjust-${data.key}`" @click="openAdjust(data)" />
      </template>
    </Column>
  </DataTable>

  <Dialog
    :visible="!!editing"
    modal
    :header="editing?.uuid ? t('building.ref.edit_sequence') : t('building.ref.new_sequence')"
    :style="{ width: '44rem' }"
    @update:visible="(v: boolean) => !v && (editing = null)"
  >
    <form v-if="editing" class="flex flex-col gap-3" @submit.prevent="save">
      <div class="form-grid">
        <div class="field">
          <label for="seq-key">{{ t('building.key') }}</label>
          <InputText id="seq-key" v-model="editing.key" class="ltr-value" data-testid="seq-key" />
          <span v-if="errors.key" class="field-error">{{ errors.key }}</span>
        </div>
        <div class="field">
          <label for="seq-scope">{{ t('building.ref.scope_label') }}</label>
          <Select v-model="editing.scope" input-id="seq-scope" :options="scopeOptions" option-label="label" option-value="value" />
        </div>
        <div v-if="editing.scope === 'form'" class="field">
          <label for="seq-form">{{ t('building.ref.form') }}</label>
          <Select v-if="canForms" v-model="editing.form" input-id="seq-form" :options="formOptions" option-label="label" option-value="value" filter show-clear />
          <InputText v-else id="seq-form" :model-value="editing.form ?? '—'" disabled class="ltr-value" />
          <span v-if="errors.form" class="field-error">{{ errors.form }}</span>
        </div>
      </div>
      <div class="field">
        <label for="seq-pattern">{{ t('building.ref.pattern') }}</label>
        <div class="flex flex-wrap gap-1 mb-1" dir="ltr">
          <Button v-for="tok in PATTERN_TOKENS" :key="tok" type="button" size="small" severity="secondary" outlined :label="tok" @click="insertToken(tok)" />
        </div>
        <InputText id="seq-pattern" ref="patternInput" v-model="editing.pattern" class="ltr-value font-mono" data-testid="seq-pattern" />
        <small class="text-muted-color">{{ t('building.ref.pattern_hint') }}</small>
        <span v-if="!patternValid" class="field-error">{{ t('building.ref.pattern_invalid') }}</span>
        <span v-else-if="errors.pattern" class="field-error">{{ errors.pattern }}</span>
      </div>
      <div class="form-grid">
        <div class="field">
          <label for="seq-prefix">{{ t('building.ref.prefix') }}</label>
          <InputText id="seq-prefix" v-model="editing.prefix" class="ltr-value" maxlength="32" />
          <span v-if="errors.prefix" class="field-error">{{ errors.prefix }}</span>
        </div>
        <div class="field">
          <label for="seq-pad">{{ t('building.ref.padding') }}</label>
          <InputNumber v-model="editing.padding" input-id="seq-pad" :min="1" :max="18" :use-grouping="false" show-buttons />
        </div>
        <div class="field">
          <label for="seq-step">{{ t('building.ref.step') }}</label>
          <InputNumber v-model="editing.step" input-id="seq-step" :min="1" :max="1000" :use-grouping="false" show-buttons />
        </div>
        <div class="field">
          <label for="seq-reset">{{ t('building.ref.reset_label') }}</label>
          <Select v-model="editing.reset_period" input-id="seq-reset" :options="resetOptions" option-label="label" option-value="value" />
        </div>
        <div class="field">
          <label for="seq-cal">{{ t('building.ref.date_calendar') }}</label>
          <Select v-model="editing.calendar" input-id="seq-cal" :options="calendarOptions" option-label="label" option-value="value" />
        </div>
      </div>
      <Message v-if="patternValid" severity="info" data-testid="seq-preview">
        {{ t('building.ref.live_preview') }}: <span class="ltr-value font-semibold">{{ livePreview }}</span>
      </Message>
      <div class="flex justify-end gap-2">
        <Button type="button" severity="secondary" :label="t('common.cancel')" @click="editing = null" />
        <Button type="submit" :label="t('common.save')" :disabled="!patternValid" data-testid="seq-save" />
      </div>
    </form>
  </Dialog>

  <Dialog :visible="!!adjusting" modal :header="t('building.ref.adjust')" :style="{ width: '32rem' }" @update:visible="(v: boolean) => !v && (adjusting = null)">
    <form v-if="adjusting" class="flex flex-col gap-3" @submit.prevent="submitAdjust">
      <Message severity="warn" size="small">{{ t('building.ref.adjust_hint') }}</Message>
      <p class="text-sm">
        {{ t('building.ref.current_value') }}: <b>{{ adjusting.seq.current_value }}</b>
      </p>
      <div class="field">
        <label for="adj-value">{{ t('building.ref.new_value') }}</label>
        <InputNumber v-model="adjusting.value" input-id="adj-value" :min="0" :use-grouping="false" data-testid="adj-value" />
        <span v-if="adjustErrors.current_value" class="field-error">{{ adjustErrors.current_value }}</span>
      </div>
      <p class="text-sm">
        {{ t('building.ref.next_would_be') }} <span class="ltr-value font-semibold">{{ adjustPreview }}</span>
      </p>
      <div class="field">
        <label for="adj-reason">{{ t('building.ref.reason') }}</label>
        <Textarea id="adj-reason" v-model="adjusting.reason" rows="3" auto-resize data-testid="adj-reason" />
        <small class="text-muted-color">{{ t('building.ref.reason_hint') }}</small>
        <span v-if="adjustErrors.reason" class="field-error">{{ adjustErrors.reason }}</span>
      </div>
      <div class="flex justify-end gap-2">
        <Button type="button" severity="secondary" :label="t('common.cancel')" @click="adjusting = null" />
        <Button type="submit" severity="warn" :label="t('building.ref.adjust')" :disabled="adjusting.reason.trim().length < 10" data-testid="adj-submit" />
      </div>
    </form>
  </Dialog>
</template>
