<script setup lang="ts">
import Column from 'primevue/column'
import DataTable from 'primevue/datatable'
import InputText from 'primevue/inputtext'
import Select from 'primevue/select'
import Tag from 'primevue/tag'
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { joinList } from '@/runtime/lists'
import { actionsFor, BLOCKING, type Choice, type ReportRow } from './api'

/**
 * The drift report of a package against this environment, object by object,
 * with what to do with each when the package is being imported.
 */
const props = defineProps<{ rows: ReportRow[]; choices?: Record<string, Choice> | null; errors?: Record<string, string> }>()
const { t, locale } = useI18n()
const editable = computed(() => props.choices != null)

const severity = (s: string) => (BLOCKING.includes(s as ReportRow['state']) ? 'danger' : s === 'same' ? 'secondary' : s === 'new' ? 'success' : s === 'key_taken' ? 'warn' : 'info')

function differences(row: ReportRow): string {
  return joinList(
    row.differences.map((d) => {
      const m = /^(groups|fields|relations|conditions)([+\-~])(.+)$/.exec(d)
      if (m) return t(`pkg.diff.${m[1]}${m[2] === '+' ? '_added' : m[2] === '-' ? '_removed' : '_changed'}`, { name: m[3] })
      return t(`pkg.diff_part.${d}`, d)
    }),
    locale.value,
  )
}
function options(row: ReportRow) {
  return actionsFor(row).map((a) => ({ value: a, label: t(`pkg.action.${a}`) }))
}
</script>

<template>
  <DataTable :value="rows" data-key="uuid" size="small" class="records-table" data-testid="pkg-report">
    <template #empty>
      <span class="text-sm text-muted-color">{{ t('pkg.no_objects') }}</span>
    </template>
    <Column :header="t('pkg.object')">
      <template #body="{ data }">
        <div class="flex flex-col">
          <span class="font-medium" dir="auto">{{ data.label }}</span>
          <span class="text-xs text-muted-color">{{ t(`pkg.type.${data.type}`) }}</span>
        </div>
      </template>
    </Column>
    <Column :header="t('pkg.state')" style="width: 12rem">
      <template #body="{ data }">
        <Tag :severity="severity(data.state)" :value="t(`pkg.state_name.${data.state}`)" :data-testid="`pkg-state-${data.key}`" />
      </template>
    </Column>
    <Column :header="t('pkg.differences')">
      <template #body="{ data }">
        <span v-if="data.missing.length" class="text-sm text-danger">{{ t('pkg.missing_dependency') }}</span>
        <span v-else-if="data.differences.length" class="text-sm">{{ differences(data) }}</span>
        <span v-else class="text-sm text-muted-color">{{ t(`pkg.state_hint.${data.state}`) }}</span>
      </template>
    </Column>
    <Column v-if="editable" :header="t('pkg.choice')" style="width: 16rem">
      <template #body="{ data }">
        <div v-if="choices![data.uuid]" class="flex flex-col gap-1">
          <Select
            v-model="choices![data.uuid]!.action"
            :options="options(data)"
            option-label="label"
            option-value="value"
            size="small"
            :placeholder="t('pkg.choose')"
            :aria-label="t('pkg.choice_for', { name: data.label })"
            :data-testid="`pkg-choice-${data.key}`"
          />
          <InputText
            v-if="choices![data.uuid]!.action === 'rename'"
            v-model="choices![data.uuid]!.key"
            dir="ltr"
            class="ltr-value"
            size="small"
            maxlength="48"
            :placeholder="t('pkg.new_key')"
            :aria-label="t('pkg.new_key_for', { name: data.label })"
            :data-testid="`pkg-key-${data.key}`"
          />
          <small v-if="errors?.[`choices.${data.uuid}`]" class="text-danger">{{ errors[`choices.${data.uuid}`] }}</small>
        </div>
        <span v-else class="text-sm text-danger">{{ t('pkg.resolve_here') }}</span>
      </template>
    </Column>
  </DataTable>
</template>
