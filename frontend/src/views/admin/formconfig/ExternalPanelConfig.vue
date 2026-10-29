<script setup lang="ts">
import InputNumber from 'primevue/inputnumber'
import MultiSelect from 'primevue/multiselect'
import Select from 'primevue/select'
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import ConfigField from '@/components/config/ConfigField.vue'
import { humanize } from '@/runtime/i18nText'
import type { SourceOption } from '@/views/integrations/api'

/**
 * An external table panel (ADR-0057): rows of a data source whose chosen
 * column equals a field of the record, with the columns to show.
 */
const config = defineModel<Record<string, unknown>>({ required: true })
const props = defineProps<{ index: number; sources: SourceOption[]; fields: { value: string; label: string }[]; errors: (path: string) => string | undefined }>()
const { t } = useI18n()
const source = computed(() => props.sources.find((s) => s.uuid === config.value.source) ?? null)
const columns = computed(() => (source.value?.columns ?? []).map((c) => ({ value: c.key, label: c.label ?? humanize(c.key) })))
const matchOptions = computed(() => [{ value: 'value', label: t('panels.external_value') }, ...columns.value])
const set = (k: string, v: unknown) => (config.value = { ...config.value, [k]: v })
</script>

<template>
  <div class="flex flex-col gap-3" :data-testid="`pn-external-${index}`">
    <div class="cfg-row">
      <ConfigField :label="t('panels.external_source')" :for="`pn-xsrc-${index}`" width="md" :error="errors('config.source')">
        <Select
          :model-value="config.source ?? null"
          :input-id="`pn-xsrc-${index}`"
          :options="sources"
          option-label="label"
          option-value="uuid"
          size="small"
          :empty-message="t('panels.external_none')"
          @update:model-value="
            (v) => {
              config = { ...config, source: v, match: null, columns: [] }
            }
          "
        />
      </ConfigField>
      <ConfigField :label="t('panels.external_limit')" :for="`pn-xlim-${index}`" width="xs">
        <InputNumber :model-value="(config.limit as number) ?? 20" :input-id="`pn-xlim-${index}`" :min="1" :max="100" size="small" @update:model-value="(v) => set('limit', v ?? 20)" />
      </ConfigField>
    </div>
    <div class="cfg-row">
      <ConfigField :label="t('panels.external_match')" :for="`pn-xmatch-${index}`" width="md" :hint="t('panels.external_match_hint')" :error="errors('config.match')">
        <Select
          :model-value="config.match ?? null"
          :input-id="`pn-xmatch-${index}`"
          :options="matchOptions"
          option-label="label"
          option-value="value"
          size="small"
          @update:model-value="(v) => set('match', v)"
        />
      </ConfigField>
      <ConfigField :label="t('panels.external_field')" :for="`pn-xfield-${index}`" width="md" :error="errors('config.field')">
        <Select
          :model-value="config.field ?? null"
          :input-id="`pn-xfield-${index}`"
          :options="fields"
          option-label="label"
          option-value="value"
          size="small"
          @update:model-value="(v) => set('field', v)"
        />
      </ConfigField>
    </div>
    <ConfigField :label="t('panels.external_columns')" :for="`pn-xcols-${index}`" width="lg">
      <MultiSelect
        :model-value="(config.columns as string[]) ?? []"
        :input-id="`pn-xcols-${index}`"
        :options="columns"
        option-label="label"
        option-value="value"
        display="chip"
        size="small"
        @update:model-value="(v) => set('columns', v)"
      />
    </ConfigField>
  </div>
</template>
