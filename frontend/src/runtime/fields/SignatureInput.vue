<script setup lang="ts">
import Button from 'primevue/button'
import Message from 'primevue/message'
import { computed, onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { ApiError } from '@/api/http'
import { uploadFile } from '../api'
import { useRenderer } from '../context'
import FileList from './FileList.vue'
import type { InputProps } from './props'

/** Signature pad: draw with mouse, pen or finger; saved as a PNG upload whose uuid the field holds. */
const props = defineProps<InputProps>()
const emit = defineEmits<{ 'update:modelValue': [value: unknown]; focus: []; blur: [] }>()
const ctx = useRenderer()
const { t } = useI18n()
const canvas = ref<HTMLCanvasElement | null>(null)
const drawn = ref(false)
const saving = ref(false)
const error = ref('')
const uuid = computed(() => (typeof props.modelValue === 'string' ? props.modelValue : null))
let drawing = false

function setup(): void {
  const c = canvas.value
  if (!c) return
  const ratio = window.devicePixelRatio || 1
  c.width = c.clientWidth * ratio
  c.height = c.clientHeight * ratio
  const g = c.getContext('2d')
  if (!g) return
  g.scale(ratio, ratio)
  g.lineWidth = 2.2
  g.lineCap = 'round'
  g.lineJoin = 'round'
  g.strokeStyle = '#111827'
}
onMounted(setup)

function pos(e: PointerEvent): [number, number] {
  const r = canvas.value!.getBoundingClientRect()
  return [e.clientX - r.left, e.clientY - r.top]
}
function down(e: PointerEvent): void {
  if (props.disabled) return
  canvas.value!.setPointerCapture(e.pointerId)
  drawing = true
  const g = canvas.value!.getContext('2d')!
  g.beginPath()
  g.moveTo(...pos(e))
}
function move(e: PointerEvent): void {
  if (!drawing) return
  const g = canvas.value!.getContext('2d')!
  g.lineTo(...pos(e))
  g.stroke()
  drawn.value = true
}
function up(): void {
  drawing = false
}
function clear(): void {
  const c = canvas.value
  if (!c) return
  c.getContext('2d')!.clearRect(0, 0, c.width, c.height)
  drawn.value = false
}
async function save(): Promise<void> {
  const c = canvas.value
  if (!c || !drawn.value) return
  error.value = ''
  saving.value = true
  try {
    const blob = await new Promise<Blob | null>((r) => c.toBlob(r, 'image/png'))
    if (!blob) throw new Error('canvas')
    const meta = await uploadFile(blob, `signature-${Date.now()}.png`, ctx.formUuid.value, props.field.key)
    ctx.rememberFile(meta)
    emit('update:modelValue', meta.uuid)
    clear()
  } catch (e) {
    error.value = e instanceof ApiError ? e.message : t('runtime.upload_failed', { name: t('runtime.signature') })
  } finally {
    saving.value = false
  }
}
</script>

<template>
  <div @focusin="emit('focus')" @focusout="emit('blur')">
    <Message v-if="!ctx.formUuid.value" severity="secondary" size="small" class="mb-2">{{ t('runtime.uploads_need_publish') }}</Message>
    <div v-if="uuid" class="flex items-center gap-2">
      <FileList :uuids="[uuid]" images class="flex-1" :removable="!disabled" @remove="emit('update:modelValue', null)" />
    </div>
    <template v-else>
      <canvas
        :id="inputId"
        ref="canvas"
        class="w-full h-40 rounded-md border bg-white touch-none"
        :class="invalid ? 'border-red-500' : 'border-surface-300 dark:border-surface-600'"
        role="img"
        :aria-label="t('runtime.signature_pad')"
        @pointerdown="down"
        @pointermove="move"
        @pointerup="up"
        @pointerleave="up"
      />
      <div class="flex gap-2 mt-2">
        <Button type="button" :label="t('runtime.signature_clear')" icon="pi pi-eraser" severity="secondary" size="small" outlined :disabled="!drawn || disabled" @click="clear" />
        <Button type="button" :label="t('runtime.signature_save')" icon="pi pi-check" size="small" :loading="saving" :disabled="!drawn || disabled || !ctx.formUuid.value" @click="save" />
      </div>
    </template>
    <p v-if="error" class="field-error text-sm">{{ error }}</p>
  </div>
</template>
