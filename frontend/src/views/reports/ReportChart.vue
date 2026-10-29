<script setup lang="ts">
import { BarChart, LineChart, PieChart } from 'echarts/charts'
import { GridComponent, LegendComponent, TooltipComponent } from 'echarts/components'
import { init, use, type ECharts, type EChartsCoreOption } from 'echarts/core'
import { CanvasRenderer } from 'echarts/renderers'
import SelectButton from 'primevue/selectbutton'
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import EmptyValue from '@/components/EmptyValue.vue'
import { joinList } from '@/runtime/lists'
import { chartPalette, token } from '@/theme/chartPalette'
import type { GroupRow, ReportOptions, Result, Visualization } from './api'

use([BarChart, LineChart, PieChart, GridComponent, LegendComponent, TooltipComponent, CanvasRenderer])

/**
 * A report result drawn as its visualization (ECharts for charts, plain
 * tables for tables and pivots, figures for key figures). Every chart can be
 * switched to its table of values, which is also what a screen reader reads.
 */
const props = withDefaults(defineProps<{ result: Result; visualization: Visualization; options?: ReportOptions | null; height?: number }>(), { options: null, height: 320 })
const { t, locale } = useI18n()
const el = ref<HTMLDivElement | null>(null)
let chart: ECharts | null = null
let observer: MutationObserver | null = null
let resize: ResizeObserver | null = null
const isChart = computed(() => ['bar', 'line', 'area', 'pie'].includes(props.visualization) && props.result.mode === 'grouped')
const view = ref<'chart' | 'table'>('chart')
const views = computed(() => [
  { value: 'chart', label: t('rep.view_chart') },
  { value: 'table', label: t('rep.view_table') },
])
const MAX_SERIES = 8

const fmt = computed(() => new Intl.NumberFormat(locale.value === 'ar' ? 'ar-u-nu-latn' : locale.value, { maximumFractionDigits: 2 }))
const blank = (v: unknown) => v === null || v === undefined || v === ''
function show(v: unknown): string {
  if (blank(v)) return ''
  return typeof v === 'number' ? fmt.value.format(v) : String(v)
}
const grouped = computed(() => (props.result.mode === 'grouped' ? (props.result.rows as GroupRow[]) : []))
const dims = computed(() => props.result.dimensions ?? [])
const measures = computed(() => props.result.measures ?? [])

/** A two-dimension result as a cross table: rows by the first grouping, columns by the second. */
const pivot = computed(() => {
  const rows: string[] = []
  const cols: string[] = []
  const cells = new Map<string, (number | string | null)[]>()
  for (const r of grouped.value) {
    const a = r.labels[0] ?? ''
    const b = r.labels[1] ?? ''
    if (!rows.includes(a)) rows.push(a)
    if (!cols.includes(b)) cols.push(b)
    cells.set(`${a}\u0000${b}`, r.values)
  }
  return { rows, cols, cell: (a: string, b: string, i: number) => cells.get(`${a}\u0000${b}`)?.[i] ?? null }
})

function option(): EChartsCoreOption {
  const dark = document.documentElement.classList.contains('app-dark')
  const colors = [...chartPalette(dark)]
  const ink = token('--text')
  const muted = token('--text-muted')
  const line = token('--border')
  const surface = token('--bg-surface')
  const rtl = document.documentElement.dir === 'rtl'
  const o = props.options
  const textStyle = { color: ink, fontFamily: getComputedStyle(document.body).fontFamily }
  const tooltip = { renderMode: 'richText', backgroundColor: surface, borderColor: line, textStyle, valueFormatter: (v: unknown) => show(v) }
  if (props.visualization === 'pie') {
    return {
      color: colors,
      textStyle,
      tooltip: { ...tooltip, trigger: 'item' },
      legend: o?.showLegend === false ? { show: false } : { type: 'scroll', bottom: 0, textStyle: { color: muted } },
      series: [
        {
          type: 'pie',
          radius: ['40%', '70%'],
          itemStyle: { borderColor: surface, borderWidth: 2, borderRadius: 4 },
          label: { show: o?.showValues ?? false, color: ink, formatter: (p: { name: string; value: number }) => `${p.name}: ${show(p.value)}` },
          data: grouped.value.slice(0, MAX_SERIES).map((r) => ({ name: r.labels[0], value: r.values[0] })),
        },
      ],
    }
  }
  const categories: string[] = []
  for (const r of grouped.value) if (!categories.includes(r.labels[0] ?? '')) categories.push(r.labels[0] ?? '')
  let series: { name: string; data: (number | string | null)[] }[]
  if (dims.value.length === 2) {
    const names: string[] = []
    for (const r of grouped.value) if (!names.includes(r.labels[1] ?? '')) names.push(r.labels[1] ?? '')
    series = names.slice(0, MAX_SERIES).map((n) => ({ name: n, data: categories.map((c) => grouped.value.find((r) => r.labels[0] === c && r.labels[1] === n)?.values[0] ?? null) }))
  } else {
    series = measures.value.slice(0, MAX_SERIES).map((m, i) => ({ name: m.label, data: categories.map((c) => grouped.value.find((r) => r.labels[0] === c)?.values[i] ?? null) }))
  }
  const kind = props.visualization === 'bar' ? 'bar' : 'line'
  return {
    color: colors,
    textStyle,
    animation: !window.matchMedia('(prefers-reduced-motion: reduce)').matches,
    grid: { left: 8, right: 8, top: 16, bottom: series.length > 1 && o?.showLegend !== false ? 40 : 8, containLabel: true },
    tooltip: { ...tooltip, trigger: 'axis', axisPointer: { type: kind === 'bar' ? 'shadow' : 'line' } },
    legend: series.length > 1 && o?.showLegend !== false ? { type: 'scroll', bottom: 0, textStyle: { color: muted } } : { show: false },
    xAxis: { type: 'category', data: categories, inverse: rtl, axisLabel: { color: muted, hideOverlap: true }, axisLine: { lineStyle: { color: line } }, axisTick: { show: false } },
    yAxis: { type: 'value', position: rtl ? 'right' : 'left', axisLabel: { color: muted, formatter: (v: number) => show(v) }, splitLine: { lineStyle: { color: line, opacity: 0.6 } } },
    series: series.map((s) => ({
      ...s,
      type: kind,
      stack: o?.stacked ? 'all' : undefined,
      areaStyle: props.visualization === 'area' ? { opacity: 0.25 } : undefined,
      smooth: false,
      symbolSize: 8,
      lineStyle: { width: 2 },
      barMaxWidth: 40,
      itemStyle: kind === 'bar' ? { borderRadius: [4, 4, 0, 0], borderColor: surface, borderWidth: o?.stacked ? 2 : 0 } : undefined,
      label: { show: o?.showValues ?? false, position: o?.stacked ? 'inside' : 'top', color: ink, formatter: (p: { value: number }) => show(p.value) },
    })),
  }
}

function draw(): void {
  if (!isChart.value || view.value !== 'chart' || !el.value) return
  chart ??= init(el.value, undefined, { renderer: 'canvas' })
  chart.setOption(option(), true)
}
onMounted(() => {
  void nextTick(draw)
  observer = new MutationObserver(draw)
  observer.observe(document.documentElement, { attributes: true, attributeFilter: ['class', 'dir'] })
  if (el.value) {
    resize = new ResizeObserver(() => chart?.resize())
    resize.observe(el.value)
  }
})
onBeforeUnmount(() => {
  observer?.disconnect()
  resize?.disconnect()
  chart?.dispose()
})
watch(
  () => [props.result, props.visualization, props.options, view.value, locale.value],
  () => {
    if (view.value === 'table') {
      chart?.dispose()
      chart = null
      return
    }
    void nextTick(draw)
  },
  { deep: true },
)

/** The chart as a PNG data URL (for PDF export), or null when no chart is drawn. */
function image(): string | null {
  return chart && isChart.value && view.value === 'chart' ? chart.getDataURL({ type: 'png', pixelRatio: 2, backgroundColor: token('--bg-surface') }) : null
}
defineExpose({ image })
const seriesCut = computed(() => {
  if (!isChart.value) return false
  if (dims.value.length === 2) return new Set(grouped.value.map((r) => r.labels[1])).size > MAX_SERIES
  return props.visualization === 'pie' && grouped.value.length > MAX_SERIES
})
</script>

<template>
  <div class="flex flex-col gap-2 min-w-0" data-testid="report-chart">
    <div v-if="isChart" class="flex justify-end">
      <SelectButton v-model="view" :options="views" option-label="label" option-value="value" :allow-empty="false" size="small" :aria-label="t('rep.view_as')" data-testid="report-view-toggle" />
    </div>
    <div v-if="isChart && view === 'chart'" ref="el" class="w-full" :style="{ height: `${height}px` }" role="img" :aria-label="t('rep.chart_alt')" data-testid="report-canvas" />
    <p v-if="seriesCut && view === 'chart'" class="m-0 text-xs text-muted-color">{{ t('rep.series_cut', { n: MAX_SERIES }) }}</p>

    <!-- Key figures -->
    <div v-if="visualization === 'kpi' && result.mode === 'grouped'" class="grid gap-3 [grid-template-columns:repeat(auto-fit,minmax(10rem,1fr))]" data-testid="report-kpis">
      <div v-for="(m, i) in measures" :key="m.uuid" class="flex flex-col gap-1 rounded-[var(--radius-card)] border border-[var(--border)] p-3">
        <span class="text-sm text-muted-color">{{ m.label }}</span>
        <span class="text-2xl font-semibold ltr-value" data-testid="report-kpi-value"
          ><EmptyValue v-if="blank(result.totals?.[i])" /><template v-else>{{ show(result.totals?.[i]) }}</template></span
        >
      </div>
    </div>

    <!-- Pivot -->
    <div v-else-if="visualization === 'pivot' && result.mode === 'grouped' && dims.length === 2" class="overflow-x-auto">
      <table class="report-table" data-testid="report-pivot">
        <thead>
          <tr>
            <th scope="col">{{ dims[0]?.label }}</th>
            <template v-for="c in pivot.cols" :key="c">
              <th v-for="m in measures" :key="m.uuid" scope="col">{{ measures.length > 1 ? `${c} · ${m.label}` : c }}</th>
            </template>
          </tr>
        </thead>
        <tbody>
          <tr v-for="r in pivot.rows" :key="r">
            <th scope="row">{{ r }}</th>
            <template v-for="c in pivot.cols" :key="c">
              <td v-for="(m, i) in measures" :key="m.uuid" class="num">
                <EmptyValue v-if="blank(pivot.cell(r, c, i))" /><template v-else>{{ show(pivot.cell(r, c, i)) }}</template>
              </td>
            </template>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Grouped table (also the table view of every chart) -->
    <div v-else-if="result.mode === 'grouped' && (!isChart || view === 'table')" class="overflow-x-auto">
      <table class="report-table" data-testid="report-table">
        <thead>
          <tr>
            <th v-for="d in dims" :key="d.uuid" scope="col">{{ d.label }}</th>
            <th v-for="m in measures" :key="m.uuid" scope="col">{{ m.label }}</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="(r, i) in grouped" :key="i">
            <td v-for="(l, j) in r.labels" :key="`l${j}`">{{ l }}</td>
            <td v-for="(v, j) in r.values" :key="`v${j}`" class="num">
              <EmptyValue v-if="blank(v)" /><template v-else>{{ show(v) }}</template>
            </td>
          </tr>
          <tr v-if="!grouped.length">
            <td :colspan="dims.length + measures.length" class="text-muted-color">{{ t('rep.no_data') }}</td>
          </tr>
        </tbody>
        <tfoot v-if="dims.length && (options?.showTotals ?? false)">
          <tr>
            <th scope="row" :colspan="dims.length">{{ t('rep.total') }}</th>
            <td v-for="(v, j) in result.totals" :key="j" class="num">
              <EmptyValue v-if="blank(v)" /><template v-else>{{ show(v) }}</template>
            </td>
          </tr>
        </tfoot>
      </table>
    </div>

    <!-- A list of records -->
    <div v-else-if="result.mode === 'detail'" class="overflow-x-auto">
      <table class="report-table" data-testid="report-table">
        <thead>
          <tr>
            <th v-for="c in result.columns" :key="c.uuid" scope="col">{{ c.label }}</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="(r, i) in result.rows as (string | number | null)[][]" :key="i">
            <td v-for="(v, j) in r" :key="j" :class="{ num: result.columns?.[j]?.type === 'number' }">
              <EmptyValue v-if="blank(v)" /><template v-else>{{ show(v) }}</template>
            </td>
          </tr>
          <tr v-if="!result.rows.length">
            <td :colspan="result.columns?.length ?? 1" class="text-muted-color">{{ t('rep.no_data') }}</td>
          </tr>
        </tbody>
      </table>
    </div>

    <p v-if="result.truncated" class="m-0 text-xs text-muted-color">{{ t('rep.truncated', { n: result.records }) }}</p>
    <p v-if="result.omitted?.length" class="m-0 text-xs text-muted-color">{{ t('rep.omitted', { list: joinList(result.omitted, locale) }) }}</p>
  </div>
</template>

<style scoped>
.report-table {
  border-collapse: collapse;
  width: 100%;
  font-size: var(--text-size-sm);
}
.report-table th,
.report-table td {
  border-bottom: 1px solid var(--border);
  padding: 0.375rem 0.5rem;
  text-align: start;
  vertical-align: top;
}
.report-table thead th {
  color: var(--text-muted);
  font-weight: 600;
}
.report-table td.num {
  text-align: end;
  font-variant-numeric: tabular-nums;
  unicode-bidi: plaintext;
}
.report-table tfoot th,
.report-table tfoot td {
  font-weight: 600;
  border-bottom: 0;
}
</style>
