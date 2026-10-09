<script setup lang="ts">
import Button from 'primevue/button'
import Dialog from 'primevue/dialog'
import Message from 'primevue/message'
import ProgressBar from 'primevue/progressbar'
import { computed, onBeforeUnmount, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { ApiError } from '@/api/http'
import { uploadFile } from '../api'
import { useRenderer } from '../context'
import FileList from './FileList.vue'
import ImageCropper from './ImageCropper.vue'
import { processImage, type Rect } from './images'
import { prop, type InputProps } from './props'

/**
 * File, multiple files (drag-and-drop zone), image (crop and resize before
 * upload) and camera capture. Files go to `POST /files` (scanned and kept
 * temporary until the record references them); the field holds the uuid(s).
 */
const props = defineProps<InputProps>()
const emit = defineEmits<{ 'update:modelValue': [value: unknown]; focus: []; blur: [] }>()
const ctx = useRenderer()
const { t } = useI18n()

const multiple = computed(() => ctx.index.value.storage(props.field) === 'files')
const uuids = computed<string[]>(() => {
  const v = props.modelValue
  if (v === null || v === undefined || v === '') return []
  return (Array.isArray(v) ? v : [v]).map(String)
})
const rules = computed(() => props.field.validation.file ?? {})
const image = computed(() => props.field.type === 'image_upload' || props.field.type === 'camera')
const accept = computed(() => {
  if (image.value) return 'image/*'
  const list = [...(rules.value.types ?? []).map((x) => `.${x}`), ...(rules.value.mimes ?? [])]
  return list.length ? list.join(',') : undefined
})
const maxCount = computed(() => (multiple.value ? (rules.value.maxCount ?? null) : 1))
const canAdd = computed(() => !props.disabled && !!ctx.formUuid.value && (maxCount.value === null || uuids.value.length < maxCount.value || !multiple.value))
const uploading = ref(0)
const problems = ref<string[]>([])
const dragging = ref(false)
const input = ref<HTMLInputElement | null>(null)

function check(file: File): string | null {
  const ext = file.name.includes('.') ? file.name.split('.').pop()!.toLowerCase() : ''
  if (rules.value.types?.length && !rules.value.types.includes(ext) && !image.value) return t('runtime.file_type_not_allowed', { name: file.name, types: rules.value.types.join(', ') })
  if (rules.value.maxSizeKb && file.size > rules.value.maxSizeKb * 1024) return t('runtime.file_too_large', { name: file.name, kb: rules.value.maxSizeKb })
  if (image.value && !file.type.startsWith('image/')) return t('runtime.file_not_image', { name: file.name })
  return null
}

async function upload(blob: Blob, name: string): Promise<void> {
  uploading.value++
  try {
    const meta = await uploadFile(blob, name, ctx.formUuid.value, props.field.key)
    ctx.rememberFile(meta)
    if (multiple.value) emit('update:modelValue', [...uuids.value, meta.uuid])
    else emit('update:modelValue', meta.uuid)
  } catch (e) {
    problems.value.push(e instanceof ApiError ? (Object.values(e.fieldErrors)[0] ?? e.message) : t('runtime.upload_failed', { name }))
  } finally {
    uploading.value--
  }
}

// Image crop queue: each picked image may be cropped, then is resized to the configured limits.
const cropQueue = ref<File[]>([])
const cropping = computed(() => cropQueue.value[0] ?? null)
const wantsCrop = computed(() => props.field.type === 'image_upload' && prop(props.field, 'crop', false))
const aspect = computed(() => {
  const a = Number(props.field.ui.props?.aspectRatio ?? 0)
  return a > 0 ? a : null
})
const maxW = computed(() => Number(props.field.ui.props?.maxWidth ?? 0) || null)
const maxH = computed(() => Number(props.field.ui.props?.maxHeight ?? 0) || null)

async function processAndUpload(file: File, crop: Rect | null): Promise<void> {
  try {
    const blob = image.value ? await processImage(file, crop, maxW.value, maxH.value) : file
    await upload(blob, file.name)
  } catch {
    problems.value.push(t('runtime.upload_failed', { name: file.name }))
  }
}

async function add(list: FileList | File[] | null): Promise<void> {
  problems.value = []
  const files = [...(list ?? [])]
  const room = maxCount.value === null ? files.length : multiple.value ? Math.max(0, maxCount.value - uuids.value.length) : 1
  if (files.length > room) problems.value.push(t('runtime.too_many_files', { max: maxCount.value ?? 0 }))
  for (const file of files.slice(0, room)) {
    const problem = check(file)
    if (problem) {
      problems.value.push(problem)
      continue
    }
    if (wantsCrop.value) cropQueue.value.push(file)
    else await processAndUpload(file, null)
  }
  if (input.value) input.value.value = ''
}

async function cropped(rect: Rect | null): Promise<void> {
  const file = cropQueue.value.shift()
  if (file) await processAndUpload(file, rect)
}

function remove(uuid: string): void {
  if (multiple.value) {
    const next = uuids.value.filter((u) => u !== uuid)
    emit('update:modelValue', next.length ? next : null)
  } else emit('update:modelValue', null)
}

function drop(e: DragEvent): void {
  dragging.value = false
  if (canAdd.value) void add(e.dataTransfer?.files ?? null)
}

// Camera capture through the browser (getUserMedia); falls back to the device camera picker.
const cameraOpen = ref(false)
const video = ref<HTMLVideoElement | null>(null)
const cameraError = ref('')
let stream: MediaStream | null = null
async function openCamera(): Promise<void> {
  cameraError.value = ''
  if (!navigator.mediaDevices?.getUserMedia) {
    input.value?.click()
    return
  }
  cameraOpen.value = true
  try {
    stream = await navigator.mediaDevices.getUserMedia({ video: { facingMode: prop(props.field, 'facing', 'environment') === 'user' ? 'user' : 'environment' } })
    await new Promise((r) => setTimeout(r, 0))
    if (video.value) {
      video.value.srcObject = stream
      await video.value.play()
    }
  } catch {
    cameraError.value = t('runtime.camera_unavailable')
  }
}
function closeCamera(): void {
  stream?.getTracks().forEach((tr) => tr.stop())
  stream = null
  cameraOpen.value = false
}
async function capture(): Promise<void> {
  const v = video.value
  if (!v || !v.videoWidth) return
  const canvas = document.createElement('canvas')
  canvas.width = v.videoWidth
  canvas.height = v.videoHeight
  canvas.getContext('2d')!.drawImage(v, 0, 0)
  const blob = await new Promise<Blob | null>((r) => canvas.toBlob(r, 'image/jpeg', 0.9))
  closeCamera()
  if (blob) await processAndUpload(new File([blob], `camera-${Date.now()}.jpg`, { type: 'image/jpeg' }), null)
}
onBeforeUnmount(closeCamera)
</script>

<template>
  <div @focusin="emit('focus')" @focusout="emit('blur')">
    <Message v-if="!ctx.formUuid.value" severity="secondary" size="small" class="mb-2">{{ t('runtime.uploads_need_publish') }}</Message>
    <FileList v-if="uuids.length" :uuids="uuids" :images="image" :removable="!disabled" class="mb-2" @remove="remove" />
    <input
      :id="inputId"
      ref="input"
      type="file"
      class="sr-only"
      :accept="accept"
      :multiple="multiple"
      :capture="field.type === 'camera' ? 'environment' : undefined"
      :disabled="!canAdd"
      @change="add(($event.target as HTMLInputElement).files)"
    />
    <div
      v-if="canAdd && (multiple || !uuids.length)"
      class="rounded-lg border-2 border-dashed p-4 flex flex-wrap items-center justify-center gap-3 text-center"
      :class="[dragging ? 'border-primary bg-primary-subtle' : 'border-line-strong', invalid ? 'border-danger' : '']"
      data-testid="drop-zone"
      @dragover.prevent="dragging = true"
      @dragleave="dragging = false"
      @drop.prevent="drop"
    >
      <i class="pi pi-cloud-upload text-2xl text-muted-color" />
      <span class="text-sm text-muted-color">{{ multiple ? t('runtime.drop_files') : t('runtime.drop_file') }}</span>
      <Button v-if="field.type === 'camera'" type="button" icon="pi pi-camera" :label="t('runtime.take_photo')" size="small" @click="openCamera" />
      <Button type="button" icon="pi pi-paperclip" :label="t('runtime.choose_file')" size="small" :outlined="field.type === 'camera'" @click="input?.click()" />
    </div>
    <ProgressBar v-if="uploading > 0" mode="indeterminate" class="mt-2 !h-1.5" />
    <small v-if="rules.maxSizeKb || rules.types?.length" class="block text-muted-color mt-1">
      {{
        [rules.types?.length ? t('runtime.allowed_types', { types: rules.types.join(', ') }) : '', rules.maxSizeKb ? t('runtime.max_size', { kb: rules.maxSizeKb }) : ''].filter(Boolean).join(' · ')
      }}
    </small>
    <p v-for="(p, i) in problems" :key="i" class="field-error text-sm">{{ p }}</p>
    <ImageCropper :file="cropping" :aspect="aspect" @done="cropped" @cancel="cropQueue.shift()" />
    <Dialog :visible="cameraOpen" modal :header="t('runtime.take_photo')" :style="{ width: '32rem' }" @update:visible="(v: boolean) => !v && closeCamera()">
      <video ref="video" class="w-full rounded bg-media" muted playsinline />
      <p v-if="cameraError" class="field-error mt-2">{{ cameraError }}</p>
      <template #footer>
        <Button :label="t('common.cancel')" severity="secondary" @click="closeCamera" />
        <Button :label="t('runtime.capture')" icon="pi pi-camera" :disabled="!!cameraError" @click="capture" />
      </template>
    </Dialog>
  </div>
</template>
