<script setup lang="ts">
import { onBeforeUnmount, onMounted, ref, shallowRef, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import type * as Monaco from 'monaco-editor/esm/vs/editor/editor.api'
import { loadMonaco } from './monaco'

/**
 * A code editor (Monaco) for extension source. Code is always left to
 * right, whatever the interface language.
 */
const props = withDefaults(defineProps<{ language: 'php' | 'typescript'; readonly?: boolean; height?: string; label: string }>(), { readonly: false, height: '28rem' })
const model = defineModel<string>({ required: true })
const { t } = useI18n()
const host = ref<HTMLElement | null>(null)
const failed = ref(false)
const editor = shallowRef<Monaco.editor.IStandaloneCodeEditor | null>(null)

onMounted(async () => {
  try {
    const monaco = await loadMonaco()
    if (!host.value) return
    const dark = () => document.documentElement.classList.contains('app-dark')
    editor.value = monaco.editor.create(host.value, {
      value: model.value,
      language: props.language,
      readOnly: props.readonly,
      automaticLayout: true,
      minimap: { enabled: false },
      fontSize: 13,
      tabSize: 4,
      scrollBeyondLastLine: false,
      theme: dark() ? 'vs-dark' : 'vs',
      ariaLabel: props.label,
    })
    editor.value.onDidChangeModelContent(() => {
      const v = editor.value?.getValue() ?? ''
      if (v !== model.value) model.value = v
    })
  } catch {
    failed.value = true
  }
})
watch(model, (v) => {
  if (editor.value && editor.value.getValue() !== v) editor.value.setValue(v)
})
watch(
  () => props.readonly,
  (r) => editor.value?.updateOptions({ readOnly: r }),
)
onBeforeUnmount(() => editor.value?.dispose())
</script>

<template>
  <div>
    <textarea v-if="failed" v-model="model" class="code-fallback" dir="ltr" :readonly="readonly" :aria-label="label" spellcheck="false" />
    <div v-else ref="host" class="code-host" dir="ltr" :style="{ height }" :aria-label="label" />
    <p v-if="failed" class="m-0 mt-1 text-xs text-muted-color">{{ t('ext.editor_fallback') }}</p>
  </div>
</template>

<style scoped>
.code-host {
  border: 1px solid var(--border);
  border-radius: 0.5rem;
  overflow: hidden;
}
.code-fallback {
  width: 100%;
  min-height: 20rem;
  font-family: ui-monospace, monospace;
  font-size: 0.8125rem;
  border: 1px solid var(--border);
  border-radius: 0.5rem;
  padding: 0.5rem;
}
</style>
