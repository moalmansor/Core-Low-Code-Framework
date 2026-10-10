<script setup lang="ts">
import Button from 'primevue/button'
import Rating from 'primevue/rating'
import { computed, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRenderer } from '../context'
import { fieldType } from '../fieldTypes'
import { formatValue } from '../format'
import ReferenceDrawer from '../ReferenceDrawer.vue'
import type { RowRef } from '../rules'
import SafeHtml, { safeHref } from '../SafeHtml'
import type { ClientField } from '../types'
import FileList from './FileList.vue'
import { hasComponent } from './index'

/** A field's value shown read-only (view and print modes, read-only fields, computed values, and unknown types). */
const props = defineProps<{ field: ClientField; value: unknown; row: RowRef | null; compact?: boolean }>()
const ctx = useRenderer()
const { t } = useI18n()

const known = computed(() => fieldType(props.field.type) !== null && hasComponent(props.field.type))
const storage = computed(() => ctx.index.value.storage(props.field))
const empty = computed(() => props.value === null || props.value === undefined || props.value === '' || (Array.isArray(props.value) && props.value.length === 0))
const text = computed(() =>
  formatValue(ctx.index.value, props.field, props.value, { locale: ctx.locale.value, references: ctx.references.value, files: ctx.files.value, yes: t('runtime.yes'), no: t('runtime.no') }),
)
const uuids = computed(() => (empty.value ? [] : (Array.isArray(props.value) ? props.value : [props.value]).map(String)))
const target = computed(() => ctx.index.value.relationOf(props.field)?.target ?? null)
const isRef = computed(() => ctx.index.value.isReference(props.field))
const titles = computed(() => ctx.references.value[props.field.key] ?? {})
const drawer = ref<string | null>(null)
const link = computed(() => {
  if (typeof props.value !== 'string') return null
  if (props.field.type === 'email') return `mailto:${props.value}`
  if (props.field.type === 'url') return safeHref(props.value)
  if (props.field.type === 'tel') return `tel:${props.value.replace(/[^\d+]/g, '')}`
  return null
})
</script>

<template>
  <div class="lcf-value min-h-9 flex items-center py-1" :data-testid="`value-${field.key}`">
    <div v-if="!known" class="flex flex-col gap-1">
      <span class="text-sm text-warning"><i class="pi pi-exclamation-triangle me-1" />{{ t('runtime.unsupported_type', { type: field.type }) }}</span>
      <span v-if="!empty" class="ltr-value text-sm">{{ typeof value === 'object' ? JSON.stringify(value) : String(value) }}</span>
    </div>
    <!-- An empty value looks different from a filled one everywhere (design system §5.6). -->
    <span v-else-if="empty && compact" class="lcf-empty" :title="t('runtime.empty_value')"
      ><span aria-hidden="true">—</span><span class="sr-only">{{ t('runtime.empty_value') }}</span></span
    >
    <span v-else-if="empty" class="lcf-empty" data-empty="true">{{ t('runtime.empty_value') }}</span>
    <FileList v-else-if="storage === 'file' || storage === 'files'" :uuids="uuids" :images="['image_upload', 'camera', 'signature'].includes(field.type)" class="w-full" />
    <SafeHtml v-else-if="field.type === 'rich_text'" :html="String(value)" class="w-full" />
    <SafeHtml v-else-if="field.type === 'markdown'" :html="String(value)" markdown class="w-full" />
    <pre v-else-if="field.type === 'code' || field.type === 'json'" class="ltr-value text-sm whitespace-pre-wrap w-full bg-subtle rounded p-2">{{
      field.type === 'json' ? JSON.stringify(value, null, 2) : String(value)
    }}</pre>
    <span v-else-if="field.type === 'color' || field.type === 'color_palette'" class="flex items-center gap-2">
      <span class="inline-block w-5 h-5 rounded border border-line-strong" :style="{ background: String(value) }" />
      <span class="ltr-value">{{ text }}</span>
    </span>
    <Rating v-else-if="field.type === 'rating'" :model-value="Number(value)" :stars="Number(field.validation.number?.max ?? field.ui.props?.stars ?? 5) || 5" readonly />
    <span v-else-if="isRef && target" class="flex flex-wrap gap-1">
      <Button v-for="u in uuids" :key="u" type="button" :label="titles[u] ?? u" link size="small" class="!p-0" @click="drawer = u" />
    </span>
    <a v-else-if="link" :href="link" class="text-primary underline ltr-value" :target="field.type === 'url' ? '_blank' : undefined" rel="noopener noreferrer">{{ text }}</a>
    <span v-else :class="{ 'ltr-value': ['iban', 'national_id', 'barcode', 'password'].includes(field.type) }" class="whitespace-pre-wrap break-words" dir="auto">{{
      field.type === 'password' ? '••••••••' : text
    }}</span>
    <ReferenceDrawer v-if="target && drawer" :form="target" :record="drawer" @close="drawer = null" />
  </div>
</template>
