<script setup lang="ts">
import Button from 'primevue/button'
import { onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'

/**
 * A surface to draw a signature with a mouse, pen or finger. The drawing is
 * returned as a PNG data URL; the server re-encodes it before stamping it.
 * Colours come from the page's text colour, so it follows the theme.
 */
const emit = defineEmits<{ change: [dataUrl: string | null] }>()
const { t } = useI18n()
const canvas = ref<HTMLCanvasElement | null>(null)
let drawing = false
let drawn = false
let last: { x: number; y: number } | null = null

onMounted(() => {
  const c = canvas.value
  if (!c) return
  const ratio = window.devicePixelRatio || 1
  c.width = c.clientWidth * ratio
  c.height = c.clientHeight * ratio
  const ctx = c.getContext('2d')
  if (!ctx) return
  ctx.scale(ratio, ratio)
  ctx.lineWidth = 2.2
  ctx.lineCap = 'round'
  ctx.lineJoin = 'round'
  ctx.strokeStyle = getComputedStyle(c).color
})
function point(e: PointerEvent): { x: number; y: number } {
  const r = canvas.value!.getBoundingClientRect()
  return { x: e.clientX - r.left, y: e.clientY - r.top }
}
function down(e: PointerEvent): void {
  drawing = true
  last = point(e)
  canvas.value?.setPointerCapture(e.pointerId)
}
function move(e: PointerEvent): void {
  if (!drawing || !last || !canvas.value) return
  const ctx = canvas.value.getContext('2d')
  const p = point(e)
  if (!ctx) return
  ctx.beginPath()
  ctx.moveTo(last.x, last.y)
  ctx.lineTo(p.x, p.y)
  ctx.stroke()
  last = p
  drawn = true
}
function up(): void {
  if (!drawing) return
  drawing = false
  last = null
  if (drawn && canvas.value) emit('change', canvas.value.toDataURL('image/png'))
}
function clear(): void {
  const c = canvas.value
  c?.getContext('2d')?.clearRect(0, 0, c.width, c.height)
  drawn = false
  emit('change', null)
}
</script>

<template>
  <div class="flex flex-col gap-2">
    <canvas
      ref="canvas"
      class="w-full h-40 rounded-lg border border-surface bg-card text-color touch-none cursor-crosshair"
      role="img"
      :aria-label="t('xp_sign.pad')"
      data-testid="x-sign-pad"
      @pointerdown="down"
      @pointermove="move"
      @pointerup="up"
      @pointerleave="up"
    />
    <div><Button :label="t('xp_sign.clear')" icon="pi pi-eraser" size="small" text @click="clear" /></div>
  </div>
</template>
