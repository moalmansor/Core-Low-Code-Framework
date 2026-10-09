<script setup lang="ts">
import Button from 'primevue/button'
import InputNumber from 'primevue/inputnumber'
import InputText from 'primevue/inputtext'
import MultiSelect from 'primevue/multiselect'
import Select from 'primevue/select'
import ToggleSwitch from 'primevue/toggleswitch'
import { computed, onMounted, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { referenceApi } from '../api'
import ExpressionInput from '../conditions/ExpressionInput.vue'
import FormulaEditor from '../conditions/FormulaEditor.vue'
import { pick, staticType, type ExpressionScope } from '../conditions/scope'
import FieldSelect from '../FieldSelect.vue'
import { targetFields, type TargetField } from '../targets'
import type { BehaviorDef, DefaultKind, FieldDef, FieldTypeInfo } from '../types'
import { useBuilder } from '../useBuilder'

/** Default value, calculation, transformations, formatting and auto-fill (specification §4.6 "Behavior"). */
const props = defineProps<{ info: FieldTypeInfo; scope: ExpressionScope }>()
const field = defineModel<FieldDef>('field', { required: true })
const { t } = useI18n()
const builder = useBuilder()

const b = computed<BehaviorDef>(() => field.value.behavior!)
const storage = computed(() => props.info.storage)
const isText = computed(() => ['string', 'text', 'longtext', 'phone'].includes(storage.value))
const isNumber = computed(() => ['decimal', 'int', 'currency', 'formula', 'number'].includes(storage.value) || props.info.value_type === 'number')
const isDate = computed(() => ['date', 'datetime', 'time', 'range_date', 'range_time', 'range_datetime'].includes(storage.value))
const isFile = computed(() => ['file', 'files'].includes(storage.value))
const isLookup = computed(() => ['lookup', 'multi_lookup'].includes(storage.value))
const expected = computed(() => {
  const s = staticType(props.info)
  return ['text', 'number', 'boolean', 'date', 'datetime', 'time', 'duration'].includes(s) ? s : null
})

// ---------------------------------------------------------------- default value

const KINDS: DefaultKind[] = ['static', 'current_user', 'current_department', 'now', 'today', 'url_param', 'field', 'formula', 'reference']
const kinds = computed(() => [{ value: null, label: t('builder.none') }, ...KINDS.map((k) => ({ value: k, label: t(`builder.behavior.default_${k}`) }))])
function setKind(kind: DefaultKind | null): void {
  if (!kind) {
    b.value.default = null
    return
  }
  const d: NonNullable<BehaviorDef['default']> = { kind }
  if (kind === 'static') d.value = props.info.value_type === 'boolean' ? false : ''
  if (kind === 'url_param') d.param = field.value.key.replace(/_/g, '')
  if (kind === 'field') d.field = null
  if (kind === 'formula') d.expr = null
  if (kind === 'reference') d.reference = { path: [field.value.key] }
  b.value.default = d
}
const staticText = computed({
  get: () => String(b.value.default?.value ?? ''),
  set: (s: string) => {
    if (b.value.default)
      b.value.default.value =
        props.info.value_type === 'list'
          ? s
              .split(',')
              .map((x) => x.trim())
              .filter(Boolean)
          : s
  },
})
const referencePath = computed({
  get: () => (b.value.default?.reference?.path ?? []).join('.'),
  set: (s: string) => {
    const path = s.split('.').filter((x) => /^[a-z][a-z0-9_]{0,47}$/.test(x))
    if (b.value.default) b.value.default.reference = path.length ? { path: path.slice(0, 5) } : null
  },
})

// ---------------------------------------------------------------- formatting

const transforms = computed(() => (['trim', 'uppercase', 'lowercase', 'collapse_spaces'] as const).map((x) => ({ value: x, label: t(`builder.behavior.transform_${x}`) })))
function setNumber(key: keyof NonNullable<BehaviorDef['number']>, value: unknown): void {
  b.value.number = { ...(b.value.number ?? {}), [key]: value }
}
function setDateOpt(key: keyof NonNullable<BehaviorDef['date']>, value: unknown): void {
  b.value.date = { ...(b.value.date ?? {}), [key]: value }
}
function setFile(key: keyof NonNullable<BehaviorDef['file']>, value: unknown): void {
  b.value.file = { ...(b.value.file ?? {}), [key]: value }
}
const calendars = computed(() => (['gregorian', 'hijri', 'dual'] as const).map((x) => ({ value: x, label: t(`builder.behavior.calendar_${x}`) })))
const digitOptions = computed(() => (['locale', 'western', 'arabic_indic'] as const).map((x) => ({ value: x, label: t(`builder.behavior.digits_${x}`) })))
const weekdays = computed(() => Array.from({ length: 7 }, (_, i) => ({ value: i, label: t(`builder.weekday.${i}`) })))
const timezoneMode = computed(() => {
  const tz = b.value.date?.timezone ?? 'user'
  return tz.startsWith('fixed:') ? 'fixed' : tz
})
const fixedZone = computed(() => (b.value.date?.timezone ?? '').replace(/^fixed:/, ''))
const currencyInvalid = computed(() => !!b.value.number?.currency && !/^[A-Z]{3}$/.test(b.value.number.currency))
const maskInvalid = computed(() => !!b.value.mask && !/^[9aA*#()\-+ ./:]*$/.test(b.value.mask))
const formatInvalid = computed(() => !!b.value.date?.displayFormat && !/^[dMyHhmsa /.:,-]{0,32}$/.test(b.value.date.displayFormat))
const folderInvalid = computed(() => !!b.value.file?.folder && !/^[A-Za-z0-9_{}/-]{0,128}$/.test(b.value.file.folder))

// ---------------------------------------------------------------- auto-fill and numbering

const relationTarget = computed(() => builder.doc?.relations.find((r) => r.uuid === field.value.relation)?.target ?? null)
const lookupFields = ref<TargetField[]>([])
watch(
  relationTarget,
  async (uuid) => {
    lookupFields.value = uuid ? await targetFields(uuid).catch(() => []) : []
  },
  { immediate: true },
)
const lookupKeys = computed(() => lookupFields.value.map((f) => ({ value: f.key, label: `${pick(f.label, builder.locale, f.key)} (${f.key})` })))
const sequences = ref<{ value: string; label: string }[]>([])
onMounted(async () => {
  if (storage.value === 'auto_number') sequences.value = (await referenceApi.sequences()).map((s) => ({ value: s.uuid, label: `${s.key}${s.next_preview ? ` — ${s.next_preview}` : ''}` }))
})
</script>

<template>
  <div class="flex flex-col gap-3">
    <section v-if="info.stored && storage !== 'auto_number' && storage !== 'formula'" class="flex flex-col gap-2">
      <label class="field"
        ><span class="font-medium">{{ t('builder.behavior.default') }}</span>
        <Select :model-value="b.default?.kind ?? null" :options="kinds" option-label="label" option-value="value" size="small" data-testid="default-kind" @update:model-value="setKind" />
      </label>
      <template v-if="b.default">
        <Select
          v-if="b.default.kind === 'static' && info.value_type === 'boolean'"
          :model-value="b.default.value === true"
          :options="[
            { value: true, label: t('builder.true') },
            { value: false, label: t('builder.false') },
          ]"
          option-label="label"
          option-value="value"
          size="small"
          :aria-label="t('builder.behavior.default_value')"
          @update:model-value="(x: boolean) => (b.default!.value = x)"
        />
        <InputText v-else-if="b.default.kind === 'static'" v-model="staticText" size="small" :aria-label="t('builder.behavior.default_value')" :placeholder="t('builder.behavior.default_value')" />
        <label v-else-if="b.default.kind === 'url_param'" class="field"
          ><span>{{ t('builder.behavior.url_param') }}</span>
          <InputText
            :model-value="b.default.param ?? ''"
            size="small"
            class="ltr-value font-mono"
            :invalid="!!b.default.param && !/^[A-Za-z][A-Za-z0-9_]{0,63}$/.test(b.default.param)"
            @update:model-value="(x: string | undefined) => (b.default!.param = x ? x : null)"
          />
        </label>
        <label v-else-if="b.default.kind === 'field'" class="field"
          ><span>{{ t('builder.behavior.from_field') }}</span>
          <FieldSelect v-model="b.default.field" :exclude="field.uuid" />
        </label>
        <FormulaEditor v-else-if="b.default.kind === 'formula'" v-model="b.default.expr" :scope="scope" :expected="expected" :label="t('builder.behavior.default_formula')" />
        <label v-else-if="b.default.kind === 'reference'" class="field"
          ><span>{{ t('builder.behavior.reference_path') }}</span>
          <InputText v-model.lazy="referencePath" size="small" class="ltr-value font-mono" placeholder="employee.department" />
          <span class="text-xs text-muted-color">{{ t('builder.behavior.reference_hint') }}</span>
        </label>
      </template>
    </section>

    <section v-if="info.calculated || info.stored">
      <ExpressionInput
        :model-value="b.formula ?? null"
        :scope="scope"
        :expected="info.calculated ? null : expected"
        :visual="false"
        :label="info.calculated ? t('builder.behavior.formula_required') : t('builder.behavior.formula')"
        @update:model-value="(a) => (b.formula = a ?? null)"
      />
    </section>

    <label v-if="storage === 'auto_number'" class="field"
      ><span class="font-medium">{{ t('builder.behavior.sequence') }}</span>
      <Select :model-value="b.autoNumber ?? null" :options="sequences" option-label="label" option-value="value" show-clear size="small" @update:model-value="(x) => (b.autoNumber = x ?? null)" />
      <span class="text-xs text-muted-color">{{ t('builder.behavior.sequence_hint') }}</span>
    </label>

    <section v-if="isText" class="grid grid-cols-1 gap-2">
      <label class="field"
        ><span>{{ t('builder.behavior.transforms') }}</span>
        <MultiSelect :model-value="b.transforms ?? []" :options="transforms" option-label="label" option-value="value" display="chip" size="small" @update:model-value="(x) => (b.transforms = x)" />
      </label>
      <label class="field"
        ><span>{{ t('builder.behavior.mask') }}</span>
        <InputText
          :model-value="b.mask ?? ''"
          size="small"
          class="ltr-value font-mono"
          maxlength="64"
          :invalid="maskInvalid"
          placeholder="999-999-9999"
          @update:model-value="(x: string | undefined) => (b.mask = x ? x : null)"
        />
        <span class="text-xs text-muted-color">{{ t('builder.behavior.mask_hint') }}</span>
      </label>
    </section>

    <fieldset v-if="isNumber" class="grid grid-cols-2 gap-2 rounded border border-line p-2">
      <legend class="text-sm font-medium px-1">{{ t('builder.behavior.number_format') }}</legend>
      <label class="flex items-center gap-2 text-sm col-span-2"
        ><ToggleSwitch :model-value="b.number?.thousandSeparator ?? false" @update:model-value="(x: boolean) => setNumber('thousandSeparator', x)" />{{
          t('builder.behavior.thousand_separator')
        }}</label
      >
      <label class="field"
        ><span>{{ t('builder.behavior.decimals') }}</span
        ><InputNumber :model-value="b.number?.decimals ?? null" :min="0" :max="18" size="small" @update:model-value="(x) => setNumber('decimals', x ?? null)"
      /></label>
      <label v-if="storage === 'currency'" class="field"
        ><span>{{ t('builder.behavior.currency') }}</span
        ><InputText
          :model-value="b.number?.currency ?? ''"
          size="small"
          class="ltr-value uppercase"
          maxlength="3"
          :invalid="currencyInvalid"
          @update:model-value="(x: string | undefined) => setNumber('currency', x ? x.toUpperCase() : null)"
      /></label>
      <label v-if="storage === 'currency'" class="field"
        ><span>{{ t('builder.behavior.symbol_position') }}</span>
        <Select
          :model-value="b.number?.symbolPosition ?? 'before'"
          :options="[
            { value: 'before', label: t('builder.behavior.before') },
            { value: 'after', label: t('builder.behavior.after') },
          ]"
          option-label="label"
          option-value="value"
          size="small"
          @update:model-value="(x) => setNumber('symbolPosition', x)"
      /></label>
      <label class="field"
        ><span>{{ t('builder.behavior.unit') }}</span
        ><InputText :model-value="b.number?.unit ?? ''" size="small" class="ltr-value" maxlength="16" @update:model-value="(x: string | undefined) => setNumber('unit', x ? x : null)"
      /></label>
    </fieldset>

    <fieldset v-if="isDate" class="grid grid-cols-2 gap-2 rounded border border-line p-2">
      <legend class="text-sm font-medium px-1">{{ t('builder.behavior.calendar_settings') }}</legend>
      <label class="field"
        ><span>{{ t('builder.behavior.calendar') }}</span>
        <Select :model-value="b.date?.calendar ?? 'gregorian'" :options="calendars" option-label="label" option-value="value" size="small" @update:model-value="(x) => setDateOpt('calendar', x)"
      /></label>
      <label class="field"
        ><span>{{ t('builder.behavior.display_format') }}</span
        ><InputText
          :model-value="b.date?.displayFormat ?? ''"
          size="small"
          class="ltr-value"
          :invalid="formatInvalid"
          placeholder="dd/MM/yyyy"
          @update:model-value="(x: string | undefined) => setDateOpt('displayFormat', x ? x : null)"
      /></label>
      <label class="field"
        ><span>{{ t('builder.behavior.first_day') }}</span>
        <Select :model-value="b.date?.firstDayOfWeek ?? 0" :options="weekdays" option-label="label" option-value="value" size="small" @update:model-value="(x) => setDateOpt('firstDayOfWeek', x)"
      /></label>
      <label class="field"
        ><span>{{ t('builder.behavior.time_step') }}</span
        ><InputNumber :model-value="b.date?.timeStep ?? null" :min="1" :max="720" size="small" @update:model-value="(x) => setDateOpt('timeStep', x ?? undefined)"
      /></label>
      <label class="field"
        ><span>{{ t('builder.behavior.hour_cycle') }}</span>
        <Select
          :model-value="b.date?.hourCycle ?? '24h'"
          :options="[
            { value: '24h', label: t('builder.behavior.h24') },
            { value: '12h', label: t('builder.behavior.h12') },
          ]"
          option-label="label"
          option-value="value"
          size="small"
          @update:model-value="(x) => setDateOpt('hourCycle', x)"
      /></label>
      <label class="field"
        ><span>{{ t('builder.behavior.timezone') }}</span>
        <Select
          :model-value="timezoneMode"
          :options="[
            { value: 'user', label: t('builder.behavior.tz_user') },
            { value: 'utc', label: t('builder.behavior.tz_utc') },
            { value: 'fixed', label: t('builder.behavior.tz_fixed') },
          ]"
          option-label="label"
          option-value="value"
          size="small"
          @update:model-value="(x: string) => setDateOpt('timezone', x === 'fixed' ? 'fixed:Asia/Riyadh' : x)"
      /></label>
      <label v-if="timezoneMode === 'fixed'" class="field col-span-2"
        ><span>{{ t('builder.behavior.tz_name') }}</span
        ><InputText
          :model-value="fixedZone"
          size="small"
          class="ltr-value"
          :invalid="!/^[A-Za-z_]+(\/[A-Za-z_+-]+){0,2}$/.test(fixedZone)"
          @update:model-value="(x: string | undefined) => setDateOpt('timezone', `fixed:${x ?? ''}`)"
      /></label>
    </fieldset>

    <label v-if="isNumber || isDate" class="field"
      ><span>{{ t('builder.behavior.digits') }}</span>
      <Select :model-value="b.digits ?? 'locale'" :options="digitOptions" option-label="label" option-value="value" size="small" @update:model-value="(x) => (b.digits = x)" />
    </label>

    <fieldset v-if="isFile" class="grid grid-cols-2 gap-2 rounded border border-line p-2">
      <legend class="text-sm font-medium px-1">{{ t('builder.behavior.file_storage') }}</legend>
      <label class="field"
        ><span>{{ t('builder.behavior.disk') }}</span>
        <Select
          :model-value="b.file?.disk ?? 'private'"
          :options="[
            { value: 'private', label: t('builder.behavior.disk_private') },
            { value: 'public', label: t('builder.behavior.disk_public') },
          ]"
          option-label="label"
          option-value="value"
          size="small"
          @update:model-value="(x) => setFile('disk', x)"
      /></label>
      <label class="field"
        ><span>{{ t('builder.behavior.naming') }}</span>
        <Select
          :model-value="b.file?.naming ?? 'uuid'"
          :options="[
            { value: 'uuid', label: t('builder.behavior.naming_uuid') },
            { value: 'original_sanitized', label: t('builder.behavior.naming_original') },
          ]"
          option-label="label"
          option-value="value"
          size="small"
          @update:model-value="(x) => setFile('naming', x)"
      /></label>
      <label class="field col-span-2"
        ><span>{{ t('builder.behavior.folder') }}</span
        ><InputText
          :model-value="b.file?.folder ?? ''"
          size="small"
          class="ltr-value font-mono"
          :invalid="folderInvalid"
          placeholder="{form}/{yyyy}/{MM}"
          @update:model-value="(x: string | undefined) => setFile('folder', x ?? '')"
      /></label>
    </fieldset>

    <fieldset v-if="isLookup" class="flex flex-col gap-2 rounded border border-line p-2">
      <legend class="text-sm font-medium px-1">{{ t('builder.behavior.autofill') }}</legend>
      <p class="text-xs text-muted-color">{{ t('builder.behavior.autofill_hint') }}</p>
      <div v-for="(a, i) in b.autofill ?? []" :key="i" class="flex flex-wrap items-center gap-1">
        <Select
          :model-value="a.from[0]"
          :options="lookupKeys"
          option-label="label"
          option-value="value"
          filter
          size="small"
          class="flex-1 min-w-32"
          @update:model-value="(k: string) => (a.from = [k])"
        />
        <i class="pi pi-arrow-right rtl:rotate-180" aria-hidden="true" />
        <FieldSelect v-model="a.to" :exclude="field.uuid" class="flex-1 min-w-32" />
        <label class="flex items-center gap-1 text-xs"
          ><ToggleSwitch :model-value="a.overwrite ?? false" @update:model-value="(x: boolean) => (a.overwrite = x)" />{{ t('builder.behavior.overwrite') }}</label
        >
        <Button size="small" text severity="danger" icon="pi pi-times" :aria-label="t('builder.remove')" @click="b.autofill!.splice(i, 1)" />
      </div>
      <Button
        v-if="lookupKeys.length && (b.autofill ?? []).length < 20 && (builder.doc?.fields.length ?? 0) > 1"
        size="small"
        text
        icon="pi pi-plus"
        :label="t('builder.behavior.add_autofill')"
        class="self-start"
        @click="(b.autofill ??= []).push({ from: [lookupKeys[0]!.value], to: builder.doc!.fields.find((f) => f.uuid !== field.uuid)!.uuid, overwrite: false })"
      />
      <p v-if="!relationTarget" class="text-xs text-muted-color">{{ t('builder.behavior.autofill_needs_relation') }}</p>
    </fieldset>
  </div>
</template>
