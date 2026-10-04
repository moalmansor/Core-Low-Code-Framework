<script setup lang="ts">
import Button from 'primevue/button'
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { Decimal } from '@/expressions'
import { useRenderer } from './context'
import FieldNode from './FieldNode.vue'
import { formatDecimal } from './format'
import GroupBody from './GroupBody.vue'
import { pickText } from './i18nText'
import { fieldState, isHidden, newRow } from './rules'
import type { ClientField, ClientGroup, Row } from './types'

/**
 * A repeater (child table rows) as an editable table or as cards, with
 * minimum/maximum rows, add/remove/reorder, and row aggregates.
 */
const props = defineProps<{ group: ClientGroup }>()
const ctx = useRenderer()
const { t } = useI18n()

const key = computed(() => props.group.key)
const cfg = computed(() => props.group.repeater ?? {})
const rows = computed<Row[]>(() => (Array.isArray(ctx.values.value[key.value]) ? (ctx.values.value[key.value] as Row[]) : []))
const fields = computed(() => ctx.index.value.rowFields(props.group.uuid).filter((f) => f.type !== 'hidden'))
const title = computed(() => pickText(props.group.i18n?.title, ctx.locale.value))
const description = computed(() => pickText(props.group.i18n?.description, ctx.locale.value))
const editable = computed(() => {
  if (ctx.mode.value !== 'create' && ctx.mode.value !== 'edit') return false
  if (props.group.access === 'read_only') return false
  const s = ctx.state.value
  return !ctx.index.value.groupChain(props.group.uuid).some((g) => g.access === 'read_only' || s.flag(g.uuid, 'readOnly') || s.flag(g.uuid, 'disabled'))
})

/**
 * Row permissions name roles by uuid, which the client cannot match against
 * the user's roles, so a restricted action is withheld here (secure default).
 */
function permitted(kind: 'add' | 'remove' | 'reorder'): boolean {
  return editable.value && (cfg.value.rowPermissions?.[kind] ?? []).length === 0
}
const canAdd = computed(() => permitted('add') && (cfg.value.maxRows === null || cfg.value.maxRows === undefined || rows.value.length < cfg.value.maxRows))
const canRemove = computed(() => permitted('remove') && rows.value.length > (cfg.value.minRows ?? 0))
const canReorder = computed(() => permitted('reorder') && rows.value.length > 1)

function add(): void {
  const row = newRow(ctx.index.value, props.group.uuid, ctx.values.value, ctx.env.value)
  ctx.setRows(key.value, [...rows.value, row])
}
function remove(i: number): void {
  ctx.setRows(
    key.value,
    rows.value.filter((_r, j) => j !== i),
  )
}
function move(i: number, delta: number): void {
  const next = [...rows.value]
  const j = i + delta
  if (j < 0 || j >= next.length) return
  ;[next[i], next[j]] = [next[j]!, next[i]!]
  ctx.setRows(key.value, next)
}

function columnVisible(f: ClientField): boolean {
  return rows.value.length === 0 ? !isHidden(ctx.index.value, f, ctx.state.value, null) : rows.value.some((_r, i) => !isHidden(ctx.index.value, f, ctx.state.value, [key.value, i]))
}
const columns = computed(() => fields.value.filter(columnVisible))
function required(f: ClientField): boolean {
  return fieldState(ctx.index.value, f, ctx.state.value, rows.value.length ? [key.value, 0] : null, ctx.mode.value).required
}

const aggregates = computed(() =>
  (cfg.value.aggregates ?? []).map((a) => {
    const field = ctx.index.value.fields.get(a.field)
    const nums: Decimal[] = []
    for (const r of rows.value) {
      const v = field ? r[field.key] : null
      const raw = typeof v === 'object' && v !== null ? (v as { amount?: unknown }).amount : v
      const d = raw === null || raw === undefined ? null : Decimal.parse(String(raw))
      if (d !== null) nums.push(d)
    }
    let value: string
    if (a.fn === 'count') value = String(rows.value.length)
    else if (nums.length === 0) value = '—'
    else if (a.fn === 'min' || a.fn === 'max') value = nums.reduce((m, d) => ((a.fn === 'min' ? d.compare(m) < 0 : d.compare(m) > 0) ? d : m)).toString()
    else {
      let sum = Decimal.zero()
      for (const d of nums) {
        const s = sum.add(d)
        if (s instanceof Decimal) sum = s
      }
      const avg = sum.div(Decimal.of(nums.length), 4)
      value = a.fn === 'sum' ? sum.toString() : avg instanceof Decimal ? avg.toString() : '—'
    }
    const label = pickText(a.label, ctx.locale.value) ?? `${t(`runtime.aggregate.${a.fn}`)} ${field ? (pickText(field.i18n.label, ctx.locale.value) ?? field.key) : ''}`
    const display = /^-?\d+(\.\d+)?$/.test(value) ? formatDecimal(value, { decimals: field?.behavior.number?.decimals ?? null, locale: ctx.locale.value }) : value
    return { label, value: display, field: field?.key ?? a.field }
  }),
)
const errors = computed(() => ctx.errorsAt(key.value))
</script>

<template>
  <section class="rounded-lg border border-surface-200 dark:border-surface-700 p-3" :data-group="group.key" :data-testid="`repeater-${group.key}`">
    <header class="flex items-center gap-2 mb-2">
      <h3 class="text-base font-semibold flex-1">{{ title ?? group.key }}</h3>
      <span class="text-sm text-muted-color">{{ t('runtime.rows_count', { count: rows.length }) }}</span>
    </header>
    <p v-if="description" class="text-sm text-muted-color mb-2">{{ description }}</p>

    <div v-if="cfg.display !== 'cards'" class="overflow-x-auto">
      <table class="w-full text-sm border-collapse">
        <thead>
          <tr class="border-b border-surface-200 dark:border-surface-700">
            <th class="p-2 text-start w-10">#</th>
            <th v-for="f in columns" :key="f.uuid" class="p-2 text-start font-medium whitespace-nowrap">
              {{ pickText(f.i18n.label, ctx.locale.value) ?? f.key }}<span v-if="required(f)" class="text-red-500 ms-1" aria-hidden="true">*</span>
            </th>
            <th v-if="editable" class="p-2 w-28 lcf-no-print">
              <span class="sr-only">{{ t('common.actions') }}</span>
            </th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="(r, i) in rows" :key="(r.uuid as string | undefined) ?? i" class="border-b border-surface-100 dark:border-surface-800 align-top" :data-testid="`row-${i}`">
            <td class="p-2 text-muted-color">{{ i + 1 }}</td>
            <td v-for="f in columns" :key="f.uuid" class="p-1 min-w-40">
              <FieldNode :field="f" :row="[key, i]" compact />
            </td>
            <td v-if="editable" class="p-1 whitespace-nowrap lcf-no-print">
              <Button v-if="canReorder" type="button" icon="pi pi-arrow-up" text rounded size="small" :disabled="i === 0" :aria-label="t('runtime.move_up')" @click="move(i, -1)" />
              <Button v-if="canReorder" type="button" icon="pi pi-arrow-down" text rounded size="small" :disabled="i === rows.length - 1" :aria-label="t('runtime.move_down')" @click="move(i, 1)" />
              <Button
                v-if="canRemove"
                type="button"
                icon="pi pi-trash"
                text
                rounded
                size="small"
                severity="danger"
                :aria-label="t('runtime.remove_row')"
                :data-testid="`remove-row-${i}`"
                @click="remove(i)"
              />
            </td>
          </tr>
          <tr v-if="rows.length === 0">
            <td :colspan="columns.length + 2" class="p-3 text-center text-muted-color">{{ t('runtime.no_rows') }}</td>
          </tr>
        </tbody>
        <tfoot v-if="aggregates.length">
          <tr v-for="a in aggregates" :key="a.label" class="font-semibold">
            <td class="p-2" :colspan="columns.length + 1">{{ a.label }}</td>
            <td class="p-2 ltr-value" :data-testid="`aggregate-${a.field}`">{{ a.value }}</td>
          </tr>
        </tfoot>
      </table>
    </div>

    <div v-else class="flex flex-col gap-3">
      <div v-for="(r, i) in rows" :key="(r.uuid as string | undefined) ?? i" class="rounded-lg border border-surface-200 dark:border-surface-700 p-3" :data-testid="`row-${i}`">
        <div class="flex items-center gap-1 mb-2">
          <span class="font-semibold flex-1">{{ t('runtime.row_number', { n: i + 1 }) }}</span>
          <template v-if="editable">
            <Button v-if="canReorder" type="button" icon="pi pi-arrow-up" text rounded size="small" :disabled="i === 0" :aria-label="t('runtime.move_up')" @click="move(i, -1)" />
            <Button v-if="canReorder" type="button" icon="pi pi-arrow-down" text rounded size="small" :disabled="i === rows.length - 1" :aria-label="t('runtime.move_down')" @click="move(i, 1)" />
            <Button v-if="canRemove" type="button" icon="pi pi-trash" text rounded size="small" severity="danger" :aria-label="t('runtime.remove_row')" @click="remove(i)" />
          </template>
        </div>
        <GroupBody :group="group" :row="[key, i]" />
      </div>
      <p v-if="rows.length === 0" class="text-center text-muted-color p-3">{{ t('runtime.no_rows') }}</p>
      <dl v-if="aggregates.length" class="grid grid-cols-2 gap-2 font-semibold">
        <template v-for="a in aggregates" :key="a.label">
          <dt>{{ a.label }}</dt>
          <dd class="ltr-value" :data-testid="`aggregate-${a.field}`">{{ a.value }}</dd>
        </template>
      </dl>
    </div>

    <div class="flex flex-wrap items-center gap-3 mt-2">
      <Button v-if="canAdd" type="button" icon="pi pi-plus" :label="t('runtime.add_row')" size="small" outlined class="lcf-no-print" :data-testid="`add-row-${group.key}`" @click="add" />
      <span v-if="cfg.minRows || cfg.maxRows" class="text-xs text-muted-color">{{ t('runtime.rows_limits', { min: cfg.minRows ?? 0, max: cfg.maxRows ?? '∞' }) }}</span>
    </div>
    <p v-for="(e, i) in errors" :key="i" class="field-error mt-1">{{ e }}</p>
  </section>
</template>
