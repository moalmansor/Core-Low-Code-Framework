<script setup lang="ts">
import { inject, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { CLIENT_EXTENSIONS, FrameHost } from './frames'
import type { ClientField } from '../types'

/**
 * A field shown by a custom field component (a deployed client extension):
 * its sandboxed frame shows the input and sends the value back. The value
 * is stored as text and validated on the server like any other.
 */
const props = defineProps<{ field: ClientField; modelValue: unknown; disabled?: boolean; inputId?: string }>()
const emit = defineEmits<{ 'update:modelValue': [v: unknown]; blur: [] }>()
const { locale } = useI18n()
const list = inject(CLIENT_EXTENSIONS, ref([]))
const holder = ref<HTMLElement | null>(null)
const height = ref(40)
let host: FrameHost | null = null
let last: unknown = props.modelValue

onMounted(() => {
  const ext = list.value.find((e) => e.type === 'client_field' && e.uuid === props.field.component)
  if (!ext || !holder.value) return
  host = new FrameHost(ext.frame, ext.label, false)
  host.iframe.id = props.inputId ?? ''
  Object.assign(host.iframe.style, { width: '100%', border: '0', display: 'block' })
  host.iframe.style.height = `${height.value}px`
  host.on((m) => {
    if (m.type === 'height' && typeof m.px === 'number') host!.iframe.style.height = `${Math.min(800, Math.max(24, m.px))}px`
    if (m.type === 'value') {
      const v = typeof m.value === 'string' || m.value === null ? m.value : JSON.stringify(m.value)
      last = v
      emit('update:modelValue', v)
    }
  })
  holder.value.appendChild(host.iframe)
  void host.post({ type: 'init', value: props.modelValue, readonly: !!props.disabled, locale: locale.value, dir: document.documentElement.dir === 'rtl' ? 'rtl' : 'ltr' })
})
watch(
  () => props.modelValue,
  (v) => {
    if (v !== last) {
      last = v
      void host?.post({ type: 'value', value: v })
    }
  },
)
onBeforeUnmount(() => host?.dispose())
</script>

<template>
  <div ref="holder" class="ext-field" />
</template>

<style scoped>
.ext-field {
  border: 1px solid var(--border);
  border-radius: 0.375rem;
  overflow: hidden;
}
</style>
