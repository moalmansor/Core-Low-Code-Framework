<script setup lang="ts">
import Button from 'primevue/button'
import Dialog from 'primevue/dialog'
import { onBeforeUnmount, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import type { Rect } from './images'

/** Crop dialog: drag a rectangle over the image (optionally at a fixed aspect ratio), then apply. */
const props = defineProps<{ file: Blob | null; aspect?: number | null }>()
const emit = defineEmits<{ done: [crop: Rect | null]; cancel: [] }>()
const { t } = useI18n()
const url = ref<string | null>(null)
const img = ref<HTMLImageElement | null>(null)
const sel = ref<Rect | null>(null)
let start: { x: number; y: number } | null = null

watch(
  () => props.file,
  (f) => {
    if (url.value) URL.revokeObjectURL(url.value)
    url.value = f ? URL.createObjectURL(f) : null
    sel.value = null
  },
  { immediate: true },
)
onBeforeUnmount(() => url.value && URL.revokeObjectURL(url.value))

function point(e: PointerEvent): { x: number; y: number } {
  const r = img.value!.getBoundingClientRect()
  return { x: Math.min(Math.max(e.clientX - r.left, 0), r.width), y: Math.min(Math.max(e.clientY - r.top, 0), r.height) }
}
function down(e: PointerEvent): void {
  ;(e.target as HTMLElement).setPointerCapture(e.pointerId)
  start = point(e)
  sel.value = { x: start.x, y: start.y, width: 0, height: 0 }
}
function move(e: PointerEvent): void {
  if (!start) return
  const p = point(e)
  let width = Math.abs(p.x - start.x)
  let height = Math.abs(p.y - start.y)
  if (props.aspect) height = width / props.aspect
  sel.value = { x: Math.min(start.x, p.x), y: p.y < start.y && props.aspect ? start.y - height : Math.min(start.y, p.y), width, height }
}
function up(): void {
  start = null
}
function apply(): void {
  const el = img.value
  if (!el || !sel.value || sel.value.width < 4 || sel.value.height < 4) {
    emit('done', null)
    return
  }
  const r = el.getBoundingClientRect()
  const fx = el.naturalWidth / r.width
  const fy = el.naturalHeight / r.height
  emit('done', { x: Math.round(sel.value.x * fx), y: Math.round(sel.value.y * fy), width: Math.round(sel.value.width * fx), height: Math.round(sel.value.height * fy) })
}
</script>

<template>
  <Dialog :visible="!!file" modal :header="t('runtime.crop_image')" :style="{ width: '40rem' }" @update:visible="(v: boolean) => !v && emit('cancel')">
    <p class="text-sm text-muted-color mb-2">{{ t('runtime.crop_hint') }}</p>
    <div class="relative inline-block select-none touch-none" dir="ltr">
      <img v-if="url" ref="img" :src="url" :alt="t('runtime.crop_image')" class="max-w-full max-h-[60vh] block" draggable="false" @pointerdown="down" @pointermove="move" @pointerup="up" />
      <div
        v-if="sel && sel.width > 0"
        class="absolute border-2 border-primary bg-primary/10 pointer-events-none"
        :style="{ left: `${sel.x}px`, top: `${sel.y}px`, width: `${sel.width}px`, height: `${sel.height}px` }"
      />
    </div>
    <template #footer>
      <Button :label="t('runtime.crop_skip')" severity="secondary" @click="emit('done', null)" />
      <Button :label="t('runtime.crop_apply')" icon="pi pi-check" @click="apply" />
    </template>
  </Dialog>
</template>
