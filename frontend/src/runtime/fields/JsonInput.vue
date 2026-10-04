<script setup lang="ts">
import Button from 'primevue/button'
import InputText from 'primevue/inputtext'
import Textarea from 'primevue/textarea'
import { computed, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import type { InputProps } from './props'

/** JSON editor (validated as you type) and key-value pairs editor. */
const props = defineProps<InputProps>()
const emit = defineEmits<{ 'update:modelValue': [value: unknown]; focus: []; blur: [] }>()
const { t } = useI18n()

// JSON
const text = ref('')
const parseError = ref(false)
function fromValue(v: unknown): string {
  return v === null || v === undefined ? '' : JSON.stringify(v, null, 2)
}
watch(
  () => props.modelValue,
  (v) => {
    if (props.field.type !== 'json') return
    let current: unknown
    try {
      current = text.value.trim() === '' ? null : JSON.parse(text.value)
    } catch {
      current = undefined
    }
    if (JSON.stringify(current ?? null) !== JSON.stringify(v ?? null)) text.value = fromValue(v)
  },
  { immediate: true },
)
function onJson(v: string | undefined): void {
  text.value = v ?? ''
  if (text.value.trim() === '') {
    parseError.value = false
    emit('update:modelValue', null)
    return
  }
  try {
    const parsed: unknown = JSON.parse(text.value)
    parseError.value = false
    emit('update:modelValue', parsed)
  } catch {
    parseError.value = true
  }
}

// Key-value pairs (kept as rows while editing so empty and duplicate keys can be typed).
const rows = ref<{ key: string; value: string }[]>([])
const pairs = computed(() => (props.modelValue && typeof props.modelValue === 'object' && !Array.isArray(props.modelValue) ? (props.modelValue as Record<string, unknown>) : {}))
watch(
  pairs,
  (p) => {
    const fromRows = Object.fromEntries(rows.value.filter((r) => r.key.trim() !== '').map((r) => [r.key.trim(), r.value]))
    if (JSON.stringify(fromRows) !== JSON.stringify(p)) rows.value = Object.entries(p).map(([key, value]) => ({ key, value: value === null || value === undefined ? '' : String(value) }))
  },
  { immediate: true },
)
const duplicate = computed(() => {
  const keys = rows.value.map((r) => r.key.trim()).filter(Boolean)
  return new Set(keys).size !== keys.length
})
function commitRows(): void {
  const out: Record<string, string> = {}
  for (const r of rows.value) if (r.key.trim() !== '') out[r.key.trim()] = r.value
  emit('update:modelValue', Object.keys(out).length ? out : null)
}
function setRow(i: number, part: 'key' | 'value', v: string | undefined): void {
  rows.value[i] = { ...rows.value[i]!, [part]: v ?? '' }
  commitRows()
}
function addRow(): void {
  rows.value.push({ key: '', value: '' })
}
function removeRow(i: number): void {
  rows.value.splice(i, 1)
  commitRows()
}
</script>

<template>
  <div v-if="field.type === 'json'">
    <Textarea
      :id="inputId"
      :model-value="text"
      rows="8"
      auto-resize
      fluid
      class="ltr-value font-mono text-sm"
      :disabled="disabled"
      :invalid="invalid || parseError"
      spellcheck="false"
      @update:model-value="onJson"
      @focus="emit('focus')"
      @blur="emit('blur')"
    />
    <small v-if="parseError" class="field-error">{{ t('runtime.json_invalid') }}</small>
  </div>
  <div v-else class="flex flex-col gap-2" @focusin="emit('focus')" @focusout="emit('blur')">
    <div v-for="(r, i) in rows" :key="i" class="flex gap-2">
      <InputText
        :id="i === 0 ? inputId : undefined"
        :model-value="r.key"
        :placeholder="t('runtime.kv_key')"
        class="flex-1"
        :disabled="disabled"
        :invalid="invalid"
        :aria-label="t('runtime.kv_key')"
        @update:model-value="(v) => setRow(i, 'key', v)"
      />
      <InputText
        :model-value="r.value"
        :placeholder="t('runtime.kv_value')"
        class="flex-1"
        :disabled="disabled"
        :aria-label="t('runtime.kv_value')"
        @update:model-value="(v) => setRow(i, 'value', v)"
      />
      <Button type="button" icon="pi pi-times" text rounded severity="danger" :disabled="disabled" :aria-label="t('runtime.kv_remove')" @click="removeRow(i)" />
    </div>
    <div>
      <Button type="button" icon="pi pi-plus" :label="t('runtime.kv_add')" size="small" outlined :disabled="disabled" @click="addRow" />
    </div>
    <small v-if="duplicate" class="field-error">{{ t('runtime.kv_duplicate') }}</small>
  </div>
</template>
