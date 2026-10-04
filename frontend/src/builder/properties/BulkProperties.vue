<script setup lang="ts">
import Button from 'primevue/button'
import Select from 'primevue/select'
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import type { FieldDef, JustificationLevel } from '../types'
import { useBuilder } from '../useBuilder'

/** Changes common properties of every selected field at once (specification §4.3 multi-select). */
const { t } = useI18n()
const builder = useBuilder()

const fields = computed(() => (builder.doc?.fields ?? []).filter((f) => builder.selection.includes(f.uuid) && builder.typeInfo(f.type)?.stored))
const groups = computed(() => (builder.doc?.groups ?? []).filter((g) => builder.selection.includes(g.uuid)))

interface Prop {
  key: string
  get: (f: FieldDef) => unknown
  set: (f: FieldDef, v: never) => void
  options: { value: unknown; label: string }[]
}
const yesNo = computed(() => [
  { value: true, label: t('builder.yes') },
  { value: false, label: t('builder.no') },
])
const properties = computed<Prop[]>(() => [
  { key: 'required', get: (f) => f.validation?.required ?? false, set: (f, v: boolean) => ((f.validation ??= {}).required = v), options: yesNo.value },
  {
    key: 'width',
    get: (f) => f.ui?.width?.md ?? 12,
    set: (f, v: number) => ((f.ui ??= {}).width = { ...(f.ui.width ?? {}), md: v }),
    options: [12, 9, 8, 6, 4, 3, 2].map((n) => ({ value: n, label: `${n} / 12` })),
  },
  {
    key: 'label_position',
    get: (f) => f.ui?.labelPosition ?? 'top',
    set: (f, v: 'top' | 'side' | 'hidden') => ((f.ui ??= {}).labelPosition = v),
    options: (['top', 'side', 'hidden'] as const).map((v) => ({ value: v, label: t(`builder.ui.label_${v}`) })),
  },
  {
    key: 'size',
    get: (f) => f.ui?.size ?? 'medium',
    set: (f, v: 'small' | 'medium' | 'large') => ((f.ui ??= {}).size = v),
    options: (['small', 'medium', 'large'] as const).map((v) => ({ value: v, label: t(`builder.ui.size_${v}`) })),
  },
  { key: 'table_visible', get: (f) => f.table?.visible ?? true, set: (f, v: boolean) => ((f.table ??= {}).visible = v), options: yesNo.value },
  { key: 'sortable', get: (f) => f.table?.sortable ?? false, set: (f, v: boolean) => ((f.table ??= {}).sortable = v), options: yesNo.value },
  { key: 'filterable', get: (f) => f.table?.filterable ?? false, set: (f, v: boolean) => ((f.table ??= {}).filterable = v), options: yesNo.value },
  { key: 'searchable', get: (f) => f.table?.searchable ?? false, set: (f, v: boolean) => ((f.table ??= {}).searchable = v), options: yesNo.value },
  { key: 'exportable', get: (f) => f.export?.exportable ?? true, set: (f, v: boolean) => ((f.export ??= {}).exportable = v), options: yesNo.value },
  { key: 'track_changes', get: (f) => f.flags?.trackChanges ?? true, set: (f, v: boolean) => ((f.flags ??= {}).trackChanges = v), options: yesNo.value },
  { key: 'personal', get: (f) => f.flags?.personal ?? false, set: (f, v: boolean) => ((f.flags ??= {}).personal = v), options: yesNo.value },
  {
    key: 'justification',
    get: (f) => f.justification ?? 'inherit',
    set: (f, v: JustificationLevel) => (f.justification = v),
    options: (['inherit', 'not_required', 'optional', 'mandatory'] as const).map((v) => ({ value: v, label: t(`builder.justification.${v}`) })),
  },
])

function common(p: Prop): unknown {
  const values = new Set(fields.value.map((f) => JSON.stringify(p.get(f))))
  return values.size === 1 ? p.get(fields.value[0]!) : undefined
}
function apply(p: Prop, value: unknown): void {
  const uuids = new Set(fields.value.map((f) => f.uuid))
  builder.mutate((d) => {
    for (const f of d.fields) if (uuids.has(f.uuid)) p.set(f, value as never)
  })
}
</script>

<template>
  <div class="flex flex-col gap-3" data-testid="bulk-properties">
    <header>
      <h2 class="font-semibold">{{ t('builder.bulk.title', { n: builder.selection.length }) }}</h2>
      <p class="text-xs text-muted-color">{{ t('builder.bulk.hint', { fields: fields.length, groups: groups.length }) }}</p>
    </header>
    <template v-if="fields.length">
      <label v-for="p in properties" :key="p.key" class="field"
        ><span>{{ t(`builder.bulk.${p.key}`) }}</span>
        <Select :model-value="common(p)" :options="p.options" option-label="label" option-value="value" :placeholder="t('builder.bulk.mixed')" size="small" @update:model-value="(v) => apply(p, v)" />
      </label>
    </template>
    <div class="flex flex-wrap gap-2">
      <Button size="small" severity="secondary" icon="pi pi-copy" :label="t('builder.action.duplicate')" @click="builder.duplicate()" />
      <Button size="small" severity="danger" icon="pi pi-trash" :label="t('builder.action.delete')" @click="builder.remove()" />
    </div>
  </div>
</template>
