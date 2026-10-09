<script setup lang="ts">
import InputNumber from 'primevue/inputnumber'
import InputText from 'primevue/inputtext'
import Message from 'primevue/message'
import Select from 'primevue/select'
import ToggleSwitch from 'primevue/toggleswitch'
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import FieldSelect from '../FieldSelect.vue'
import type { FieldDef, FieldTypeInfo, StorageDef } from '../types'
import { useBuilder } from '../useBuilder'

/** Database binding of a field (specification §4.6 "Data & Database Binding"). */
const props = defineProps<{ info: FieldTypeInfo }>()
const field = defineModel<FieldDef>('field', { required: true })
const { t } = useI18n()
const builder = useBuilder()

const storage = computed<StorageDef>(() => field.value.storage!)
const flags = computed(() => field.value.flags!)
const bound = computed(() => builder.doc?.form.bindingMode === 'bound')
const DB_TYPES = ['string', 'text', 'longtext', 'int', 'bigint', 'decimal', 'bool', 'date', 'time', 'datetime', 'json'] as const
const dbTypes = computed(() => [{ value: null, label: t('builder.storage.db_type_auto') }, ...DB_TYPES.map((v) => ({ value: v, label: v }))])
const indexes = computed(() => (['none', 'index', 'unique'] as const).map((v) => ({ value: v, label: t(`builder.storage.index_${v}`) })))
const effectiveType = computed(() => storage.value.dbType ?? null)
const hasLength = computed(() => ['string', 'choice', 'auto_number', 'phone'].includes(props.info.storage) || effectiveType.value === 'string')
const hasPrecision = computed(() => ['decimal', 'currency', 'formula'].includes(props.info.storage) || effectiveType.value === 'decimal')
const encryptable = computed(() => ['string', 'text', 'longtext', 'json', 'phone'].includes(props.info.storage))
const columnInvalid = computed(() => !!storage.value.column && !/^[a-z][a-z0-9_]{0,47}$/.test(storage.value.column))
const defaultText = computed({
  get: () => (storage.value.default === null || storage.value.default === undefined ? '' : String(storage.value.default)),
  set: (v: string) => {
    if (v === '') delete storage.value.default
    else storage.value.default = v
  },
})
function nullableNumber(v: number | null | undefined, key: 'length' | 'precision' | 'scale'): void {
  storage.value[key] = v ?? null
}
</script>

<template>
  <div class="flex flex-col gap-3">
    <Message v-if="!info.stored" severity="secondary" size="small">{{ t('builder.storage.not_stored') }}</Message>
    <template v-else>
      <div class="grid grid-cols-2 gap-2">
        <label class="field col-span-2"
          ><span>{{ t('builder.storage.column') }}</span>
          <InputText
            :model-value="storage.column ?? ''"
            size="small"
            class="ltr-value font-mono"
            :invalid="columnInvalid"
            :placeholder="field.key"
            maxlength="48"
            @update:model-value="(v: string | undefined) => (storage.column = v ? v : null)"
          />
          <span class="text-xs text-muted-color">{{ t('builder.storage.column_hint') }}</span>
        </label>
        <label v-if="bound" class="field col-span-2"
          ><span>{{ t('builder.storage.bound_column') }}</span>
          <InputText
            :model-value="storage.boundColumn ?? ''"
            size="small"
            class="ltr-value font-mono"
            maxlength="60"
            @update:model-value="(v: string | undefined) => (storage.boundColumn = v ? v : null)"
          />
          <span class="text-xs text-muted-color">{{ t('builder.storage.bound_column_hint') }}</span>
        </label>
        <label class="field col-span-2"
          ><span>{{ t('builder.storage.db_type') }}</span>
          <Select :model-value="storage.dbType ?? null" :options="dbTypes" option-label="label" option-value="value" size="small" @update:model-value="(v) => (storage.dbType = v)" />
        </label>
        <label v-if="hasLength" class="field"
          ><span>{{ t('builder.storage.length') }}</span>
          <InputNumber :model-value="storage.length ?? null" :min="1" :max="4000" size="small" @update:model-value="(v) => nullableNumber(v, 'length')" />
        </label>
        <template v-if="hasPrecision">
          <label class="field"
            ><span>{{ t('builder.storage.precision') }}</span>
            <InputNumber :model-value="storage.precision ?? null" :min="1" :max="38" size="small" @update:model-value="(v) => nullableNumber(v, 'precision')" />
          </label>
          <label class="field"
            ><span>{{ t('builder.storage.scale') }}</span>
            <InputNumber :model-value="storage.scale ?? null" :min="0" :max="18" size="small" @update:model-value="(v) => nullableNumber(v, 'scale')" />
          </label>
        </template>
        <label class="field"
          ><span>{{ t('builder.storage.default') }}</span>
          <InputText v-model="defaultText" size="small" maxlength="255" />
        </label>
        <label class="flex items-center gap-2 text-sm col-span-2"
          ><ToggleSwitch :model-value="storage.nullable ?? true" @update:model-value="(v: boolean) => (storage.nullable = v)" />{{ t('builder.storage.nullable') }}</label
        >
        <label class="field"
          ><span>{{ t('builder.storage.index') }}</span>
          <Select :model-value="storage.index ?? 'none'" :options="indexes" option-label="label" option-value="value" size="small" @update:model-value="(v) => (storage.index = v)" />
        </label>
        <div v-if="storage.index === 'unique'" class="field col-span-2">
          <span class="text-sm font-medium">{{ t('builder.storage.unique_scope') }}</span>
          <FieldSelect v-model:values="storage.uniqueScope" multiple :exclude="field.uuid" />
        </div>
        <label v-if="info.storage === 'currency'" class="flex items-center gap-2 text-sm col-span-2"
          ><ToggleSwitch :model-value="storage.multiCurrency ?? false" @update:model-value="(v: boolean) => (storage.multiCurrency = v)" />{{ t('builder.storage.multi_currency') }}</label
        >
      </div>
      <fieldset class="flex flex-col gap-2 rounded border border-line p-2">
        <legend class="text-sm font-medium px-1">{{ t('builder.flags.title') }}</legend>
        <label v-if="encryptable" class="flex items-center gap-2 text-sm"
          ><ToggleSwitch :model-value="flags.encrypted ?? false" @update:model-value="(v: boolean) => (flags.encrypted = v)" />{{ t('builder.flags.encrypted') }}</label
        >
        <label v-if="flags.encrypted" class="flex items-center gap-2 text-sm"
          ><ToggleSwitch :model-value="flags.blindIndex ?? false" @update:model-value="(v: boolean) => (flags.blindIndex = v)" />{{ t('builder.flags.blind_index') }}</label
        >
        <label class="flex items-center gap-2 text-sm"
          ><ToggleSwitch :model-value="flags.sensitive ?? false" @update:model-value="(v: boolean) => (flags.sensitive = v)" />{{ t('builder.flags.sensitive') }}</label
        >
        <label class="flex items-center gap-2 text-sm"
          ><ToggleSwitch :model-value="flags.personal ?? false" @update:model-value="(v: boolean) => (flags.personal = v)" />{{ t('builder.flags.personal') }}</label
        >
        <label class="flex items-center gap-2 text-sm"
          ><ToggleSwitch :model-value="flags.trackChanges ?? true" @update:model-value="(v: boolean) => (flags.trackChanges = v)" />{{ t('builder.flags.track_changes') }}</label
        >
      </fieldset>
    </template>
  </div>
</template>
