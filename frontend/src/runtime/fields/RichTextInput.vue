<script setup lang="ts">
import Button from 'primevue/button'
import { onMounted, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { cleanHtml, safeHref } from '../SafeHtml'
import type { InputProps } from './props'

/**
 * Rich text editor on a content-editable area. Whatever is typed or pasted
 * is rebuilt from the same tag allowlist SafeHtml renders, so the stored HTML
 * never carries scripts, styles or event handlers.
 */
const props = defineProps<InputProps>()
const emit = defineEmits<{ 'update:modelValue': [value: unknown]; focus: []; blur: [] }>()
const { t } = useI18n()
const editor = ref<HTMLDivElement | null>(null)
let last = ''

function sync(): void {
  const v = typeof props.modelValue === 'string' ? props.modelValue : ''
  if (editor.value && v !== last) {
    const clean = cleanHtml(v)
    editor.value.innerHTML = clean
    last = v
  }
}
onMounted(sync)
watch(() => props.modelValue, sync)

function onInput(): void {
  if (!editor.value) return
  const html = cleanHtml(editor.value.innerHTML)
  const empty = editor.value.textContent?.trim() === '' && !/<(li|hr|br)/.test(html)
  last = empty ? '' : html
  emit('update:modelValue', empty ? null : html)
}

function onPaste(e: ClipboardEvent): void {
  e.preventDefault()
  const html = e.clipboardData?.getData('text/html')
  const text = e.clipboardData?.getData('text/plain') ?? ''
  if (html) document.execCommand('insertHTML', false, cleanHtml(html))
  else document.execCommand('insertText', false, text)
  onInput()
}

function run(command: string, value?: string): void {
  if (props.disabled) return
  editor.value?.focus()
  document.execCommand(command, false, value)
  onInput()
}

function link(): void {
  const href = safeHref(window.prompt(t('runtime.rich_link_prompt'), 'https://') ?? null)
  if (href) run('createLink', href)
}

const tools = [
  { cmd: 'bold', icon: 'pi pi-bold', label: 'runtime.rich_bold' },
  { cmd: 'italic', icon: 'pi pi-italic', label: 'runtime.rich_italic' },
  { cmd: 'underline', icon: 'pi pi-underline', label: 'runtime.rich_underline' },
  { cmd: 'insertUnorderedList', icon: 'pi pi-list', label: 'runtime.rich_bullets' },
  { cmd: 'insertOrderedList', icon: 'pi pi-sort-numeric-down', label: 'runtime.rich_numbers' },
]
</script>

<template>
  <div class="rounded-md border" :class="invalid ? 'border-red-500' : 'border-surface-300 dark:border-surface-600'">
    <div class="flex flex-wrap gap-1 border-b border-surface-200 dark:border-surface-700 p-1" role="toolbar" :aria-label="t('runtime.rich_toolbar')">
      <Button v-for="tool in tools" :key="tool.cmd" type="button" :icon="tool.icon" text size="small" :disabled="disabled" :aria-label="t(tool.label)" @mousedown.prevent @click="run(tool.cmd)" />
      <Button type="button" icon="pi pi-link" text size="small" :disabled="disabled" :aria-label="t('runtime.rich_link')" @mousedown.prevent @click="link" />
      <Button type="button" label="H" text size="small" :disabled="disabled" :aria-label="t('runtime.rich_heading')" @mousedown.prevent @click="run('formatBlock', 'h3')" />
      <Button type="button" icon="pi pi-eraser" text size="small" :disabled="disabled" :aria-label="t('runtime.rich_clear')" @mousedown.prevent @click="run('removeFormat')" />
    </div>
    <div
      :id="inputId"
      ref="editor"
      class="lcf-rich min-h-32 p-3 outline-none"
      :contenteditable="!disabled"
      role="textbox"
      aria-multiline="true"
      :aria-required="required"
      :aria-invalid="invalid"
      dir="auto"
      @input="onInput"
      @paste="onPaste"
      @focus="emit('focus')"
      @blur="emit('blur')"
    />
  </div>
</template>
