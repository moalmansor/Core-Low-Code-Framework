<script setup lang="ts">
import Button from 'primevue/button'
import Dialog from 'primevue/dialog'
import InputText from 'primevue/inputtext'
import Password from 'primevue/password'
import QRCode from 'qrcode'
import { computed, onBeforeUnmount, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { PICKER_FALLBACK } from '@/theme/color'
import { useRenderer } from '../context'
import { pickText } from '../i18nText'
import { applyMask } from './mask'
import type { InputProps } from './props'

/** Single-line text inputs: text, password, e-mail, phone, URL, search, national ID, IBAN, barcode, datalist and color. */
const props = defineProps<InputProps>()
const emit = defineEmits<{ 'update:modelValue': [value: unknown]; focus: []; blur: [] }>()
const ctx = useRenderer()
const { t } = useI18n()

const text = computed(() => (props.modelValue === null || props.modelValue === undefined ? '' : String(props.modelValue)))
const placeholder = computed(() => pickText(props.field.i18n.placeholder, ctx.locale.value) ?? undefined)
const type = computed(() => ({ email: 'email', tel: 'tel', url: 'url', search: 'search' })[props.field.type] ?? 'text')
const ltr = computed(() => ['email', 'url', 'tel', 'iban', 'national_id', 'barcode', 'password', 'color'].includes(props.field.type))
const inputmode = computed(() => (props.field.type === 'national_id' ? 'numeric' : props.field.type === 'tel' ? 'tel' : undefined))
const maxlength = computed(() => props.field.validation.length?.max ?? props.field.storage.length ?? undefined)
const datalistId = computed(() => `${props.inputId}-list`)
const suggestions = computed(() => (props.field.options?.static ?? []).filter((o) => o.active !== false).map((o) => ({ value: o.value, label: pickText(o.i18n?.label, ctx.locale.value) ?? o.value })))

function set(raw: string): void {
  let v = props.field.behavior.mask ? applyMask(props.field.behavior.mask, raw) : raw
  if (props.field.type === 'iban') v = v.toUpperCase()
  emit('update:modelValue', v === '' ? null : v)
}

// Barcode / QR: show the code and scan with the camera where the browser can.
const qr = ref<string | null>(null)
watch(
  () => (props.field.type === 'barcode' ? text.value : ''),
  async (v) => {
    qr.value = v ? await QRCode.toDataURL(v, { margin: 1, width: 128 }).catch(() => null) : null
  },
  { immediate: true },
)
const canScan = computed(() => props.field.type === 'barcode' && typeof window !== 'undefined' && 'BarcodeDetector' in window && !!navigator.mediaDevices?.getUserMedia)
const scanning = ref(false)
const video = ref<HTMLVideoElement | null>(null)
let stream: MediaStream | null = null
let timer: number | null = null
const scanError = ref('')

interface Detector {
  detect(source: HTMLVideoElement): Promise<{ rawValue: string }[]>
}

async function startScan(): Promise<void> {
  scanError.value = ''
  scanning.value = true
  try {
    stream = await navigator.mediaDevices.getUserMedia({ video: { facingMode: 'environment' } })
    await new Promise((r) => setTimeout(r, 0))
    if (video.value) {
      video.value.srcObject = stream
      await video.value.play()
    }
    const Ctor = (window as unknown as { BarcodeDetector: new () => Detector }).BarcodeDetector
    const detector = new Ctor()
    timer = window.setInterval(async () => {
      if (!video.value) return
      const found = await detector.detect(video.value).catch(() => [])
      if (found.length) {
        set(found[0]!.rawValue)
        stopScan()
      }
    }, 400)
  } catch {
    scanError.value = t('runtime.camera_unavailable')
  }
}
function stopScan(): void {
  if (timer !== null) window.clearInterval(timer)
  timer = null
  stream?.getTracks().forEach((tr) => tr.stop())
  stream = null
  scanning.value = false
}
onBeforeUnmount(stopScan)
</script>

<template>
  <div v-if="field.type === 'color'" class="flex items-center gap-2">
    <input
      :id="inputId"
      type="color"
      class="h-10 w-14 rounded border border-line-strong bg-transparent"
      :value="text || PICKER_FALLBACK"
      :disabled="disabled"
      :aria-invalid="invalid"
      @input="set(($event.target as HTMLInputElement).value)"
      @focus="emit('focus')"
      @blur="emit('blur')"
    />
    <InputText :model-value="text" class="ltr-value w-32" :disabled="disabled" :invalid="invalid" maxlength="16" :aria-label="t('runtime.color_code')" @update:model-value="(v) => set(v ?? '')" />
  </div>
  <Password
    v-else-if="field.type === 'password'"
    :input-id="inputId"
    :model-value="text"
    :feedback="false"
    toggle-mask
    fluid
    :disabled="disabled"
    :invalid="invalid"
    :placeholder="placeholder"
    :input-props="{ autocomplete: field.ui.autocomplete ?? 'new-password', maxlength }"
    input-class="ltr-value"
    @update:model-value="(v: string) => set(v ?? '')"
    @focus="emit('focus')"
    @blur="emit('blur')"
  />
  <div v-else>
    <div class="flex gap-2">
      <InputText
        :id="inputId"
        :model-value="text"
        :type="type"
        fluid
        :class="{ 'ltr-value': ltr }"
        :disabled="disabled"
        :invalid="invalid"
        :placeholder="placeholder"
        :maxlength="maxlength"
        :inputmode="inputmode"
        :autocomplete="field.ui.autocomplete ?? undefined"
        :spellcheck="field.ui.spellcheck ?? undefined"
        :autofocus="field.ui.autofocus ?? false"
        :tabindex="field.ui.tabIndex ?? undefined"
        :list="field.type === 'datalist' ? datalistId : undefined"
        :size="size"
        :aria-required="required"
        @update:model-value="(v) => set(v ?? '')"
        @focus="emit('focus')"
        @blur="emit('blur')"
      />
      <Button v-if="canScan && !disabled" type="button" icon="pi pi-camera" outlined :aria-label="t('runtime.scan_code')" @click="startScan" />
    </div>
    <datalist v-if="field.type === 'datalist'" :id="datalistId">
      <option v-for="o in suggestions" :key="o.value" :value="o.value">{{ o.label }}</option>
    </datalist>
    <img v-if="qr" :src="qr" :alt="t('runtime.code_image')" class="mt-2 w-24 h-24" />
    <Dialog :visible="scanning" modal :header="t('runtime.scan_code')" :style="{ width: '28rem' }" @update:visible="(v: boolean) => !v && stopScan()">
      <video ref="video" class="w-full rounded" muted playsinline />
      <p v-if="scanError" class="field-error mt-2">{{ scanError }}</p>
    </Dialog>
  </div>
</template>
