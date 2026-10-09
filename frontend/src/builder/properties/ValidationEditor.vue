<script setup lang="ts">
import Button from 'primevue/button'
import InputNumber from 'primevue/inputnumber'
import InputText from 'primevue/inputtext'
import MultiSelect from 'primevue/multiselect'
import Select from 'primevue/select'
import ToggleSwitch from 'primevue/toggleswitch'
import { computed, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { isSafe } from '@/expressions/safeRegex'
import ExpressionInput from '../conditions/ExpressionInput.vue'
import { pick, type ExpressionScope } from '../conditions/scope'
import FieldSelect from '../FieldSelect.vue'
import FormPicker from '../FormPicker.vue'
import I18nInput from '../I18nInput.vue'
import { targetFields, type TargetField } from '../targets'
import type { FieldDef, FieldTypeInfo, I18nText, ValidationDef } from '../types'
import { useBuilder } from '../useBuilder'

/**
 * Validation rules allowed for the field type (FieldTypeRegistry), each with
 * an error message per locale (specification §4.6 "Validation").
 */
const props = defineProps<{ info: FieldTypeInfo; scope: ExpressionScope }>()
const field = defineModel<FieldDef>('field', { required: true })
const { t } = useI18n()
const builder = useBuilder()

const v = computed<ValidationDef>(() => field.value.validation!)
const allows = (rule: string) => props.info.validation.includes(rule)
const DECIMAL = /^-?[0-9]{1,30}(\.[0-9]{1,18})?$/

function message(rule: string): I18nText | undefined {
  return field.value.i18n?.messages?.[rule]
}
function setMessage(rule: string, text: I18nText | undefined): void {
  field.value.i18n ??= {}
  const messages = { ...(field.value.i18n.messages ?? {}) }
  if (text && Object.keys(text).length) messages[rule] = text
  else delete messages[rule]
  field.value.i18n.messages = messages
}

function decimalModel(key: 'min' | 'max' | 'step') {
  return computed({
    get: () => v.value.number?.[key] ?? '',
    set: (s: string) => {
      v.value.number = { ...(v.value.number ?? {}), [key]: s === '' ? null : s }
    },
  })
}
const numMin = decimalModel('min')
const numMax = decimalModel('max')
const numStep = decimalModel('step')

const formats = computed(() => [
  { value: null, label: t('builder.none') },
  ...(['email', 'url', 'phone', 'national_id', 'iban', 'numeric', 'arabic', 'english', 'alphanumeric'] as const).map((f) => ({ value: f, label: t(`builder.validation.format_${f}`) })),
])
const patternUnsafe = computed(() => !!v.value.pattern && !isSafe(v.value.pattern))
const weekdays = computed(() => Array.from({ length: 7 }, (_, i) => ({ value: i, label: t(`builder.weekday.${i}`) })))
const listText = (key: 'disabledDates' | 'types' | 'mimes') =>
  computed({
    get: () => (key === 'disabledDates' ? (v.value.date?.disabledDates ?? []) : (v.value.file?.[key] ?? [])).join(', '),
    set: (s: string) => {
      const items = s
        .split(',')
        .map((x) => x.trim().toLowerCase())
        .filter((x) => x !== '')
      if (key === 'disabledDates') v.value.date = { ...(v.value.date ?? {}), disabledDates: items }
      else v.value.file = { ...(v.value.file ?? {}), [key]: items }
    },
  })
const disabledDates = listText('disabledDates')
const fileTypes = listText('types')
const fileMimes = listText('mimes')
const datesInvalid = computed(() => (v.value.date?.disabledDates ?? []).some((d) => !/^[0-9]{4}-[0-9]{2}-[0-9]{2}$/.test(d)))

function setDate<K extends 'min' | 'max' | 'disabledWeekdays' | 'noPast' | 'noFuture'>(key: K, value: NonNullable<ValidationDef['date']>[K]): void {
  v.value.date = { ...(v.value.date ?? {}), [key]: value }
}
function setFile(key: 'maxSizeKb' | 'maxCount', value: number | null): void {
  v.value.file = { ...(v.value.file ?? {}), [key]: value }
}
function setImage(key: 'minWidth' | 'maxWidth' | 'minHeight' | 'maxHeight', value: number | null): void {
  const file = { ...(v.value.file ?? {}) }
  file.image = { ...(file.image ?? {}), [key]: value }
  v.value.file = file
}
function setLength(key: 'min' | 'max', value: number | null): void {
  v.value.length = { ...(v.value.length ?? {}), [key]: value }
}

const compareOps = computed(() => (['eq', 'neq', 'gt', 'gte', 'lt', 'lte', 'before', 'after'] as const).map((o) => ({ value: o, label: t(`builder.validation.compare_${o}`) })))

// Asynchronous existence check against a collection.
const asyncFields = ref<TargetField[]>([])
watch(
  () => v.value.async?.collection,
  async (c) => {
    asyncFields.value = c ? await targetFields(c).catch(() => []) : []
  },
  { immediate: true },
)
const asyncKeys = computed(() => asyncFields.value.map((f) => ({ value: f.key, label: `${pick(f.label, builder.locale, f.key)} (${f.key})` })))
function setAsync(collection: string | null | undefined): void {
  v.value.async = collection ? { type: v.value.async?.type ?? 'exists_in', collection, path: v.value.async?.collection === collection ? v.value.async.path : ['code'] } : null
}

function addCustom(): void {
  const used = new Set((v.value.custom ?? []).map((c) => c.messageKey))
  let n = 1
  while (used.has(`custom_${n}`)) n++
  ;(v.value.custom ??= []).push({ when: { k: 'lit', t: 'boolean', v: false }, messageKey: `custom_${n}` })
}
function removeCustom(i: number): void {
  const [removed] = v.value.custom!.splice(i, 1)
  if (removed) setMessage(removed.messageKey, undefined)
}
</script>

<template>
  <div class="flex flex-col gap-3">
    <!-- Whether the field is required is set under General > Basics; its message is set here. -->
    <section v-if="allows('required') && v.required" class="rule">
      <h4>{{ t('builder.validation.required') }}</h4>
      <I18nInput :model-value="message('required')" :label="t('builder.validation.message')" @update:model-value="(m) => setMessage('required', m)" />
    </section>

    <section v-if="allows('length')" class="rule">
      <h4>{{ t('builder.validation.length') }}</h4>
      <div class="grid grid-cols-2 gap-2">
        <label class="field"
          ><span>{{ t('builder.min') }}</span
          ><InputNumber :model-value="v.length?.min ?? null" :min="0" size="small" @update:model-value="(x) => setLength('min', x ?? null)"
        /></label>
        <label class="field"
          ><span>{{ t('builder.max') }}</span
          ><InputNumber :model-value="v.length?.max ?? null" :min="1" size="small" @update:model-value="(x) => setLength('max', x ?? null)"
        /></label>
      </div>
      <I18nInput v-if="v.length?.min != null || v.length?.max != null" :model-value="message('length')" :label="t('builder.validation.message')" @update:model-value="(m) => setMessage('length', m)" />
    </section>

    <section v-if="allows('number')" class="rule">
      <h4>{{ t('builder.validation.number') }}</h4>
      <div class="grid grid-cols-3 gap-2">
        <label class="field"
          ><span>{{ t('builder.min') }}</span
          ><InputText v-model="numMin" size="small" class="ltr-value" :invalid="!!numMin && !DECIMAL.test(numMin)" inputmode="decimal"
        /></label>
        <label class="field"
          ><span>{{ t('builder.max') }}</span
          ><InputText v-model="numMax" size="small" class="ltr-value" :invalid="!!numMax && !DECIMAL.test(numMax)" inputmode="decimal"
        /></label>
        <label class="field"
          ><span>{{ t('builder.validation.step') }}</span
          ><InputText v-model="numStep" size="small" class="ltr-value" :invalid="!!numStep && !/^[0-9]{1,30}(\.[0-9]{1,18})?$/.test(numStep)" inputmode="decimal"
        /></label>
      </div>
      <I18nInput v-if="numMin || numMax || numStep" :model-value="message('number')" :label="t('builder.validation.message')" @update:model-value="(m) => setMessage('number', m)" />
    </section>

    <section v-if="allows('pattern')" class="rule">
      <label class="field"
        ><span class="font-medium">{{ t('builder.validation.pattern') }}</span>
        <InputText
          :model-value="v.pattern ?? ''"
          size="small"
          class="ltr-value font-mono"
          maxlength="256"
          :invalid="patternUnsafe"
          @update:model-value="(x: string | undefined) => (v.pattern = x ? x : null)"
        />
        <span v-if="patternUnsafe" class="field-error">{{ t('builder.validation.pattern_unsafe') }}</span>
        <span v-else class="text-xs text-muted-color">{{ t('builder.validation.pattern_hint') }}</span>
      </label>
      <I18nInput v-if="v.pattern" :model-value="message('pattern')" :label="t('builder.validation.message')" @update:model-value="(m) => setMessage('pattern', m)" />
    </section>

    <section v-if="allows('format')" class="rule">
      <label class="field"
        ><span class="font-medium">{{ t('builder.validation.format') }}</span>
        <Select :model-value="v.format ?? null" :options="formats" option-label="label" option-value="value" size="small" @update:model-value="(x) => (v.format = x)" />
      </label>
      <I18nInput v-if="v.format" :model-value="message('format')" :label="t('builder.validation.message')" @update:model-value="(m) => setMessage('format', m)" />
    </section>

    <section v-if="allows('date')" class="rule">
      <h4>{{ t('builder.validation.date') }}</h4>
      <ExpressionInput :model-value="v.date?.min ?? null" :scope="scope" expected="date" :label="t('builder.validation.date_min')" @update:model-value="(a) => setDate('min', a ?? null)" />
      <ExpressionInput :model-value="v.date?.max ?? null" :scope="scope" expected="date" :label="t('builder.validation.date_max')" @update:model-value="(a) => setDate('max', a ?? null)" />
      <p class="text-xs text-muted-color">{{ t('builder.validation.date_hint') }}</p>
      <label class="field"
        ><span>{{ t('builder.validation.disabled_weekdays') }}</span>
        <MultiSelect
          :model-value="v.date?.disabledWeekdays ?? []"
          :options="weekdays"
          option-label="label"
          option-value="value"
          display="chip"
          size="small"
          @update:model-value="(x) => setDate('disabledWeekdays', x)"
        />
      </label>
      <label class="field"
        ><span>{{ t('builder.validation.disabled_dates') }}</span>
        <InputText v-model.lazy="disabledDates" size="small" class="ltr-value" :invalid="datesInvalid" placeholder="2026-12-01, 2026-12-02" />
      </label>
      <div class="flex flex-wrap gap-4">
        <label class="flex items-center gap-2 text-sm"
          ><ToggleSwitch :model-value="v.date?.noPast ?? false" @update:model-value="(x: boolean) => setDate('noPast', x)" />{{ t('builder.validation.no_past') }}</label
        >
        <label class="flex items-center gap-2 text-sm"
          ><ToggleSwitch :model-value="v.date?.noFuture ?? false" @update:model-value="(x: boolean) => setDate('noFuture', x)" />{{ t('builder.validation.no_future') }}</label
        >
      </div>
      <I18nInput v-if="v.date" :model-value="message('date')" :label="t('builder.validation.message')" @update:model-value="(m) => setMessage('date', m)" />
    </section>

    <section v-if="allows('file')" class="rule">
      <h4>{{ t('builder.validation.file') }}</h4>
      <label class="field"
        ><span>{{ t('builder.validation.file_types') }}</span>
        <InputText v-model.lazy="fileTypes" size="small" class="ltr-value" placeholder="pdf, png, jpg" />
      </label>
      <label class="field"
        ><span>{{ t('builder.validation.file_mimes') }}</span>
        <InputText v-model.lazy="fileMimes" size="small" class="ltr-value" placeholder="application/pdf, image/png" />
      </label>
      <div class="grid grid-cols-2 gap-2">
        <label class="field"
          ><span>{{ t('builder.validation.max_size_kb') }}</span
          ><InputNumber :model-value="v.file?.maxSizeKb ?? null" :min="1" :max="1048576" size="small" @update:model-value="(x) => setFile('maxSizeKb', x ?? null)"
        /></label>
        <label class="field"
          ><span>{{ t('builder.validation.max_count') }}</span
          ><InputNumber :model-value="v.file?.maxCount ?? null" :min="1" :max="100" size="small" @update:model-value="(x) => setFile('maxCount', x ?? null)"
        /></label>
        <label v-for="k in ['minWidth', 'maxWidth', 'minHeight', 'maxHeight'] as const" :key="k" class="field"
          ><span>{{ t(`builder.validation.image_${k}`) }}</span
          ><InputNumber :model-value="v.file?.image?.[k] ?? null" :min="1" size="small" @update:model-value="(x) => setImage(k, x ?? null)"
        /></label>
      </div>
      <I18nInput v-if="v.file" :model-value="message('file')" :label="t('builder.validation.message')" @update:model-value="(m) => setMessage('file', m)" />
    </section>

    <section v-if="allows('unique')" class="rule">
      <label class="flex items-center gap-2 text-sm font-medium"
        ><ToggleSwitch :model-value="!!v.unique" @update:model-value="(x: boolean) => (v.unique = x ? { scope: [], includeDeleted: false } : null)" />{{ t('builder.validation.unique') }}</label
      >
      <template v-if="v.unique">
        <label class="field"
          ><span>{{ t('builder.validation.unique_scope') }}</span>
          <FieldSelect v-model:values="v.unique.scope" multiple :exclude="field.uuid" />
        </label>
        <label class="flex items-center gap-2 text-sm"
          ><ToggleSwitch :model-value="v.unique.includeDeleted ?? false" @update:model-value="(x: boolean) => (v.unique!.includeDeleted = x)" />{{ t('builder.validation.include_deleted') }}</label
        >
        <I18nInput :model-value="message('unique')" :label="t('builder.validation.message')" @update:model-value="(m) => setMessage('unique', m)" />
      </template>
    </section>

    <section v-if="allows('compare')" class="rule">
      <h4>{{ t('builder.validation.compare') }}</h4>
      <div v-for="(c, i) in v.compare ?? []" :key="i" class="flex gap-1">
        <Select v-model="c.op" :options="compareOps" option-label="label" option-value="value" size="small" class="w-40" />
        <FieldSelect v-model="c.field" :exclude="field.uuid" class="flex-1" />
        <Button size="small" text severity="danger" icon="pi pi-times" :aria-label="t('builder.remove')" @click="v.compare!.splice(i, 1)" />
      </div>
      <Button
        v-if="(v.compare ?? []).length < 10"
        size="small"
        text
        icon="pi pi-plus"
        :label="t('builder.validation.add_compare')"
        class="self-start"
        :disabled="(builder.doc?.fields.length ?? 0) < 2"
        @click="(v.compare ??= []).push({ op: 'eq', field: builder.doc!.fields.find((f) => f.uuid !== field.uuid)!.uuid })"
      />
      <I18nInput v-if="(v.compare ?? []).length" :model-value="message('compare')" :label="t('builder.validation.message')" @update:model-value="(m) => setMessage('compare', m)" />
    </section>

    <section v-if="allows('async')" class="rule">
      <h4>{{ t('builder.validation.async') }}</h4>
      <label class="field"
        ><span>{{ t('builder.validation.async_collection') }}</span>
        <FormPicker :model-value="v.async?.collection ?? null" @update:model-value="setAsync" />
      </label>
      <template v-if="v.async">
        <div class="grid grid-cols-2 gap-2">
          <label class="field"
            ><span>{{ t('builder.validation.async_type') }}</span>
            <Select
              v-model="v.async.type"
              :options="[
                { value: 'exists_in', label: t('builder.validation.exists_in') },
                { value: 'not_exists_in', label: t('builder.validation.not_exists_in') },
              ]"
              option-label="label"
              option-value="value"
              size="small"
          /></label>
          <label class="field"
            ><span>{{ t('builder.validation.async_path') }}</span>
            <Select :model-value="v.async.path[0]" :options="asyncKeys" option-label="label" option-value="value" filter size="small" @update:model-value="(k: string) => (v.async!.path = [k])"
          /></label>
        </div>
        <I18nInput :model-value="message('async')" :label="t('builder.validation.message')" @update:model-value="(m) => setMessage('async', m)" />
      </template>
    </section>

    <section v-if="allows('custom')" class="rule">
      <h4>{{ t('builder.validation.custom') }}</h4>
      <p class="text-xs text-muted-color">{{ t('builder.validation.custom_hint') }}</p>
      <div v-for="(c, i) in v.custom ?? []" :key="c.messageKey" class="rounded border border-line p-2 flex flex-col gap-2">
        <div class="flex items-center gap-2">
          <span class="text-xs font-mono ltr-value flex-1">{{ c.messageKey }}</span>
          <Button size="small" text severity="danger" icon="pi pi-trash" :aria-label="t('builder.remove')" @click="removeCustom(i)" />
        </div>
        <ExpressionInput :model-value="c.when" :scope="scope" expected="boolean" :allow-empty="false" :label="t('builder.validation.custom_when')" @update:model-value="(a) => a && (c.when = a)" />
        <I18nInput :model-value="message(c.messageKey)" :label="t('builder.validation.message')" @update:model-value="(m) => setMessage(c.messageKey, m)" />
      </div>
      <Button v-if="(v.custom ?? []).length < 20" size="small" text icon="pi pi-plus" :label="t('builder.validation.add_custom')" class="self-start" @click="addCustom" />
    </section>
  </div>
</template>

<style scoped>
.rule {
  display: flex;
  flex-direction: column;
  gap: 0.5rem;
  padding-block-end: 0.75rem;
  border-block-end: 1px solid var(--border);
}
.rule h4 {
  font-size: 0.875rem;
  font-weight: 600;
}
</style>
