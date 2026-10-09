<script setup lang="ts">
import SelectButton from 'primevue/selectbutton'
import Textarea from 'primevue/textarea'
import { computed, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRenderer } from '../context'
import { pickText } from '../i18nText'
import SafeHtml from '../SafeHtml'
import { prop, type InputProps } from './props'

/** Multi-line text: plain text area, code editor (monospace, tab key indents), and Markdown with a live preview. */
const props = defineProps<InputProps>()
const emit = defineEmits<{ 'update:modelValue': [value: unknown]; focus: []; blur: [] }>()
const ctx = useRenderer()
const { t } = useI18n()

const text = computed(() => (typeof props.modelValue === 'string' ? props.modelValue : ''))
const placeholder = computed(() => pickText(props.field.i18n.placeholder, ctx.locale.value) ?? undefined)
const rows = computed(() => prop(props.field, 'rows', props.field.type === 'textarea' ? 4 : 10))
const code = computed(() => props.field.type === 'code')
const tab = ref<'write' | 'preview'>('write')
const tabs = computed(() => [
  { value: 'write', label: t('runtime.markdown_write') },
  { value: 'preview', label: t('runtime.markdown_preview') },
])

function set(v: string | undefined): void {
  emit('update:modelValue', v ? v : null)
}

function onKeydown(e: KeyboardEvent): void {
  if (!code.value || e.key !== 'Tab' || e.shiftKey) return
  const el = e.target as HTMLTextAreaElement
  e.preventDefault()
  const start = el.selectionStart
  const end = el.selectionEnd
  set(`${text.value.slice(0, start)}  ${text.value.slice(end)}`)
  requestAnimationFrame(() => el.setSelectionRange(start + 2, start + 2))
}
</script>

<template>
  <div>
    <div v-if="field.type === 'markdown'" class="mb-2">
      <SelectButton v-model="tab" :options="tabs" option-label="label" option-value="value" :allow-empty="false" size="small" :aria-label="t('runtime.markdown_mode')" />
    </div>
    <SafeHtml v-if="field.type === 'markdown' && tab === 'preview'" :html="text" markdown class="min-h-24 rounded border border-surface-200 dark:border-surface-700 p-3" />
    <Textarea
      v-else
      :id="inputId"
      :model-value="text"
      :rows="rows"
      auto-resize
      fluid
      :class="code ? 'ltr-value font-mono text-sm' : ''"
      :disabled="disabled"
      :invalid="invalid"
      :placeholder="placeholder"
      :maxlength="field.validation.length?.max ?? undefined"
      :spellcheck="code ? false : (field.ui.spellcheck ?? undefined)"
      :aria-required="required"
      @update:model-value="set"
      @keydown="onKeydown"
      @focus="emit('focus')"
      @blur="emit('blur')"
    />
    <small v-if="code && prop(field, 'language', '')" class="text-muted-color">{{ prop(field, 'language', '') }}</small>
  </div>
</template>
