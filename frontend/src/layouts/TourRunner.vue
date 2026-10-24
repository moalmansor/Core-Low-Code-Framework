<script setup lang="ts">
import Button from 'primevue/button'
import { computed, nextTick, onBeforeUnmount, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { finishTour, tours } from '@/runtime/tours'

/**
 * Runs a guided tour (specification §4.37): each step highlights the part of
 * the screen it points at and explains it in a card beside it. A step whose
 * part is not on screen (a hidden field, a narrow window) shows as a centred
 * note. Escape closes the tour; the arrow keys move between steps.
 */
const { t, n } = useI18n()
const index = ref(0)
const rect = ref<DOMRect | null>(null)
const card = ref<HTMLElement | null>(null)
const cardPos = ref<{ top: number; left: number } | null>(null)
const tour = computed(() => tours.running)
const step = computed(() => tour.value?.steps[index.value] ?? null)
const last = computed(() => !!tour.value && index.value === tour.value.steps.length - 1)

function target(): HTMLElement | null {
  const selector = step.value?.target
  if (!selector) return null
  try {
    const el = document.querySelector<HTMLElement>(selector)
    return el && el.getClientRects().length ? el : null
  } catch {
    return null
  }
}

/** Places the highlight on the step's part of the screen and the card next to it, inside the window. */
async function place(): Promise<void> {
  const el = target()
  if (el) el.scrollIntoView({ block: 'center', inline: 'nearest' })
  await nextTick()
  rect.value = el ? el.getBoundingClientRect() : null
  await nextTick()
  const c = card.value
  if (!c || !rect.value) {
    cardPos.value = null
    return
  }
  const r = rect.value
  const w = c.offsetWidth
  const h = c.offsetHeight
  const gap = 12
  const rtl = document.documentElement.dir === 'rtl'
  const placement = step.value?.placement ?? 'auto'
  let top = r.bottom + gap
  let left = r.left
  if (placement === 'top' || (placement === 'auto' && r.bottom + gap + h > window.innerHeight && r.top - gap - h > 0)) top = r.top - gap - h
  if (placement === 'start' || placement === 'end') {
    const toLeft = (placement === 'start') !== rtl
    left = toLeft ? r.left - gap - w : r.right + gap
    top = r.top
  }
  cardPos.value = { top: Math.max(8, Math.min(top, window.innerHeight - h - 8)), left: Math.max(8, Math.min(left, window.innerWidth - w - 8)) }
}

watch(
  () => [tour.value?.uuid, index.value],
  () => void place(),
)
watch(
  () => tour.value?.uuid,
  () => (index.value = 0),
)

function go(delta: number): void {
  if (!tour.value) return
  const next = index.value + delta
  if (next >= tour.value.steps.length) void finishTour(tour.value, 'completed')
  else if (next >= 0) index.value = next
}
function close(): void {
  if (tour.value) void finishTour(tour.value, last.value ? 'completed' : 'dismissed')
}
function onKey(e: KeyboardEvent): void {
  if (!tour.value) return
  if (e.key === 'Escape') close()
  const forward = document.documentElement.dir === 'rtl' ? 'ArrowLeft' : 'ArrowRight'
  const back = document.documentElement.dir === 'rtl' ? 'ArrowRight' : 'ArrowLeft'
  if (e.key === forward) go(1)
  if (e.key === back) go(-1)
}
const onResize = () => void place()
watch(
  tour,
  (v) => {
    if (v) {
      window.addEventListener('keydown', onKey)
      window.addEventListener('resize', onResize)
      void nextTick(() => card.value?.focus())
    } else {
      window.removeEventListener('keydown', onKey)
      window.removeEventListener('resize', onResize)
    }
  },
  { immediate: true },
)
onBeforeUnmount(() => {
  window.removeEventListener('keydown', onKey)
  window.removeEventListener('resize', onResize)
})
</script>

<template>
  <Teleport to="body">
    <div v-if="tour && step" class="tour-layer" data-testid="tour">
      <div v-if="rect" class="tour-highlight" :style="{ top: `${rect.top - 4}px`, left: `${rect.left - 4}px`, width: `${rect.width + 8}px`, height: `${rect.height + 8}px` }" />
      <div v-else class="tour-dim" />
      <div
        ref="card"
        class="tour-card"
        :class="{ 'tour-card-centred': !rect || !cardPos }"
        :style="rect && cardPos ? { top: `${cardPos.top}px`, left: `${cardPos.left}px` } : {}"
        role="dialog"
        aria-modal="true"
        :aria-label="tour.name ?? t('tour.title')"
        tabindex="-1"
      >
        <p class="m-0 text-xs text-muted-color">{{ t('tour.step_of', { n: n(index + 1), total: n(tour.steps.length) }) }}</p>
        <h2 v-if="step.title" class="m-0 text-base font-semibold" dir="auto">{{ step.title }}</h2>
        <p class="m-0 text-sm whitespace-pre-line" dir="auto">{{ step.body }}</p>
        <div class="flex items-center gap-2 pt-1">
          <Button :label="t('tour.close')" text size="small" class="me-auto" data-testid="tour-close" @click="close" />
          <Button v-if="index > 0" :label="t('tour.back')" severity="secondary" size="small" @click="go(-1)" />
          <Button :label="last ? t('tour.done') : t('tour.next')" size="small" data-testid="tour-next" @click="go(1)" />
        </div>
      </div>
    </div>
  </Teleport>
</template>

<style scoped>
.tour-layer {
  position: fixed;
  inset: 0;
  z-index: 1200;
}
.tour-dim {
  position: absolute;
  inset: 0;
  background: var(--overlay);
}
.tour-highlight {
  position: fixed;
  border-radius: 0.5rem;
  box-shadow: 0 0 0 9999px var(--overlay);
  outline: 2px solid var(--primary);
  pointer-events: none;
}
.tour-card {
  position: fixed;
  display: flex;
  flex-direction: column;
  gap: 0.5rem;
  width: min(22rem, calc(100vw - 1rem));
  padding: 1rem;
  border-radius: 0.75rem;
  border: 1px solid var(--border);
  background: var(--bg-surface);
  color: var(--text);
  box-shadow: 0 8px 24px var(--shadow-color);
}
.tour-card:focus {
  outline: none;
}
.tour-card-centred {
  top: 50%;
  left: 50%;
  transform: translate(-50%, -50%);
}
</style>
