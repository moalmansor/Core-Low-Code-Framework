<script setup lang="ts">
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { pathOf, useRenderer, valueOf } from './context'
import { componentFor, DISPLAY_TYPES, INLINE_LABEL_TYPES, READ_ONLY_TYPES } from './fields'
import ValueDisplay from './fields/ValueDisplay.vue'
import { pickText } from './i18nText'
import { fieldState, type RowRef } from './rules'
import type { ClientField } from './types'

/**
 * One field: label, help, tooltip, prefix/suffix, the input component of its
 * type (or its read-only value), and its messages. Access level, conditions
 * and enclosing groups decide hidden / read-only / disabled / required.
 */
const props = defineProps<{ field: ClientField; row: RowRef | null; compact?: boolean }>()
const ctx = useRenderer()
const { t } = useI18n()

const s = computed(() => fieldState(ctx.index.value, props.field, ctx.state.value, props.row, ctx.mode.value))
const value = computed(() => valueOf(ctx.values.value, props.field, props.row))
const path = computed(() => pathOf(props.field, props.row))
const inputId = computed(() => `f-${path.value.replace(/\./g, '-')}`)
const errors = computed(() => ctx.errorsAt(path.value))
const locale = computed(() => ctx.locale.value)
const label = computed(() => pickText(props.field.i18n.label, locale.value) ?? props.field.key)
const help = computed(() => pickText(props.field.i18n.help, locale.value))
const description = computed(() => pickText(props.field.i18n.description, locale.value))
const tooltip = computed(() => pickText(props.field.i18n.tooltip, locale.value))
const prefix = computed(() => pickText(props.field.i18n.prefix, locale.value))
const suffix = computed(() => pickText(props.field.i18n.suffix, locale.value))
const position = computed(() => (props.compact ? 'hidden' : (props.field.ui.labelPosition ?? 'top')))
const isDisplay = computed(() => DISPLAY_TYPES.has(props.field.type))
const component = computed(() => componentFor(props.field.type))
const readOnlyView = computed(() => s.value.readOnly || READ_ONLY_TYPES.has(props.field.type) || component.value === null)
const inlineLabel = computed(() => INLINE_LABEL_TYPES.has(props.field.type) && !readOnlyView.value)
const size = computed(() => (props.field.ui.size === 'small' ? 'small' : props.field.ui.size === 'large' ? 'large' : undefined))

function update(v: unknown): void {
  ctx.setValue(props.field, v, props.row)
}
function onBlur(): void {
  ctx.touch(path.value)
  ctx.fieldEvent(props.field, 'blur', props.row)
}
function onFocus(): void {
  ctx.fieldEvent(props.field, 'focus', props.row)
}
</script>

<template>
  <div v-if="!s.hidden && field.type !== 'hidden'" :class="['lcf-field', field.ui.cssClass ?? '']" :data-field="field.key" :data-testid="`field-${path}`">
    <component :is="component" v-if="isDisplay && component" :field="field" :row="row" />
    <div v-else :class="position === 'side' ? 'grid gap-2 md:grid-cols-[minmax(8rem,30%)_1fr] md:items-start' : 'flex flex-col gap-1.5'">
      <label v-if="!inlineLabel" :for="inputId" :class="position === 'hidden' ? 'sr-only' : ['lcf-label text-sm font-medium', position === 'side' ? 'md:pt-2' : '']">
        <i v-if="field.ui.icon" :class="`${field.ui.icon} me-1 text-muted-color`" />{{ label
        }}<span v-if="s.required && !readOnlyView" class="text-danger ms-1" :aria-label="t('runtime.required')">*</span>
        <i v-if="tooltip" v-tooltip.top="tooltip" class="pi pi-info-circle ms-1 text-muted-color" tabindex="0" :aria-label="tooltip" />
      </label>
      <div class="min-w-0">
        <p v-if="description && !compact" class="text-sm text-muted-color mb-1">{{ description }}</p>
        <div class="flex items-stretch gap-2">
          <span v-if="prefix" class="self-center text-muted-color text-sm">{{ prefix }}</span>
          <div class="flex-1 min-w-0">
            <ValueDisplay v-if="readOnlyView" :field="field" :value="value" :row="row" :compact="compact" />
            <component
              :is="component"
              v-else
              :field="field"
              :model-value="value"
              :row="row"
              :disabled="s.disabled"
              :required="s.required"
              :invalid="errors.length > 0"
              :input-id="inputId"
              :size="size"
              @update:model-value="update"
              @blur="onBlur"
              @focus="onFocus"
            />
          </div>
          <span v-if="suffix" class="self-center text-muted-color text-sm">{{ suffix }}</span>
        </div>
        <small v-if="help && !compact" :id="`${inputId}-help`" class="block text-muted-color mt-1">{{ help }}</small>
        <div v-if="errors.length" :id="`${inputId}-err`" role="alert">
          <small v-for="(e, i) in errors" :key="i" class="field-error block" data-testid="field-error">{{ e }}</small>
        </div>
      </div>
    </div>
  </div>
</template>
