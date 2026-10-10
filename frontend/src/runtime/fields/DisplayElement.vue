<script setup lang="ts">
import Divider from 'primevue/divider'
import Message from 'primevue/message'
import ProgressBar from 'primevue/progressbar'
import { computed, ref, watch } from 'vue'
import { fileUrl } from '../api'
import { useRenderer } from '../context'
import { formatDecimal } from '../format'
import { pickText, humanize } from '../i18nText'
import type { RowRef } from '../rules'
import SafeHtml, { safeHref } from '../SafeHtml'
import type { ClientField } from '../types'
import { prop } from './props'

/** Display elements: heading, rich text block, divider, spacer, image, alert, link, and calculated output, progress and meter. */
const props = defineProps<{ field: ClientField; row: RowRef | null }>()
const ctx = useRenderer()
const locale = computed(() => ctx.locale.value)
const label = computed(() => pickText(props.field.i18n.label, locale.value))
const content = computed(() => pickText(props.field.i18n.content, locale.value) ?? '')
const help = computed(() => pickText(props.field.i18n.help, locale.value) ?? pickText(props.field.i18n.description, locale.value))
const level = computed(() => Math.min(6, Math.max(1, Number(prop(props.field, 'level', 2)))))
const headingClass = computed(() => ['text-2xl', 'text-xl', 'text-lg', 'text-base', 'text-sm', 'text-sm'][level.value - 1])
const severity = computed(() => {
  const s = prop(props.field, 'severity', 'info')
  return s === 'warning' ? 'warn' : ['info', 'success', 'warn', 'error', 'secondary'].includes(s) ? s : 'info'
})
const spacer = computed(() => {
  const s = props.field.ui.props?.size
  if (typeof s === 'number') return `${Math.min(20, Math.max(0, s))}rem`
  return { sm: '0.5rem', md: '1.5rem', lg: '3rem' }[String(s ?? 'md')] ?? '1.5rem'
})
const href = computed(() => safeHref(prop(props.field, 'url', '') || null))
const newTab = computed(() => prop(props.field, 'newTab', false) || /^https?:/i.test(href.value ?? ''))

// Image: an uploaded file (signed URL), or a same-origin / data image path.
const imageSrc = ref<string | null>(null)
watch(
  () => [props.field.ui.props?.file, props.field.ui.props?.src],
  async () => {
    imageSrc.value = null
    const file = prop(props.field, 'file', '')
    const src = prop(props.field, 'src', '')
    if (file && /^[0-9a-f-]{36}$/i.test(file)) {
      try {
        imageSrc.value = await fileUrl(file)
      } catch {
        imageSrc.value = null
      }
    } else if (src && ((src.startsWith('/') && !src.startsWith('//')) || /^data:image\/(png|jpe?g|gif|webp);base64,/i.test(src))) {
      imageSrc.value = src
    }
  },
  { immediate: true },
)

// Calculated elements
const raw = computed(() => ctx.displayValue(props.field, props.row))
const numeric = computed(() => {
  const n = Number(raw.value)
  return raw.value === null || raw.value === undefined || raw.value === '' || Number.isNaN(n) ? null : n
})
const min = computed(() => Number(prop(props.field, 'min', 0)))
const max = computed(() => Number(prop(props.field, 'max', 100)) || 100)
const percent = computed(() => (numeric.value === null ? 0 : Math.round(((numeric.value - min.value) / (max.value - min.value || 1)) * 100)))
const outputText = computed(() => {
  const v = raw.value
  if (v === null || v === undefined || v === '') return '—'
  if (typeof v === 'string' && /^-?\d+(\.\d+)?$/.test(v)) return formatDecimal(v, { decimals: props.field.behavior.number?.decimals ?? null, locale: locale.value })
  return typeof v === 'object' ? JSON.stringify(v) : String(v)
})
const width = computed(() => {
  const w = props.field.ui.props?.width
  return typeof w === 'number' && w > 0 ? `${w}px` : undefined
})
</script>

<template>
  <div :data-display="field.type">
    <component :is="`h${level}`" v-if="field.type === 'heading'" :class="[headingClass, 'font-semibold']">{{ label }}</component>
    <SafeHtml v-else-if="field.type === 'static_html'" :html="content" />
    <Divider v-else-if="field.type === 'divider'" :align="label ? 'center' : undefined">
      <span v-if="label" class="text-sm text-muted-color">{{ label }}</span>
    </Divider>
    <div v-else-if="field.type === 'spacer'" :style="{ height: spacer }" aria-hidden="true" />
    <figure v-else-if="field.type === 'display_image'">
      <img v-if="imageSrc" :src="imageSrc" :alt="label ?? ''" class="max-w-full rounded" :style="{ width }" />
      <figcaption v-if="help" class="text-sm text-muted-color mt-1">{{ help }}</figcaption>
    </figure>
    <Message v-else-if="field.type === 'alert_box'" :severity="severity">
      <div v-if="label && content" class="font-semibold">{{ label }}</div>
      <SafeHtml v-if="content" :html="content" />
      <span v-else>{{ label }}</span>
    </Message>
    <a
      v-else-if="field.type === 'link' && href"
      :href="href"
      :target="newTab ? '_blank' : undefined"
      :rel="newTab ? 'noopener noreferrer' : undefined"
      class="text-primary underline inline-flex items-center gap-1"
    >
      {{ label ?? href }}<i v-if="newTab" class="pi pi-external-link text-xs" />
    </a>
    <div v-else-if="field.type === 'output'" class="flex flex-col gap-1">
      <span class="text-sm font-medium">{{ label }}</span>
      <output class="text-lg font-semibold" :data-testid="`output-${field.key}`">{{ outputText }}</output>
    </div>
    <div v-else-if="field.type === 'progress'" class="flex flex-col gap-1">
      <span class="text-sm font-medium">{{ label }}</span>
      <ProgressBar :value="Math.min(100, Math.max(0, percent))" :aria-label="label ?? humanize(field.key)" />
    </div>
    <div v-else-if="field.type === 'meter'" class="flex flex-col gap-1">
      <span class="text-sm font-medium">{{ label }}</span>
      <meter
        class="w-full h-4"
        :min="min"
        :max="max"
        :low="prop(field, 'low', min)"
        :high="prop(field, 'high', max)"
        :optimum="prop(field, 'optimum', max)"
        :value="numeric ?? min"
        :aria-label="label ?? humanize(field.key)"
      >
        {{ outputText }}
      </meter>
    </div>
    <small v-if="help && ['output', 'progress', 'meter', 'heading'].includes(field.type)" class="block text-muted-color">{{ help }}</small>
  </div>
</template>
